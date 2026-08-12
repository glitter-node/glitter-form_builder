type SubmissionState = {
  values: Record<string, unknown>;
  errors: Record<string, string[]>;
  globalErrors: string[];
  submitting: boolean;
  submitted: boolean;
  receipt: Record<string, unknown> | null;
  idempotencyKey: string;
};

const record = (value: unknown): Record<string, unknown> | null => value !== null && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : null;
const state = (context: G7ActionContext): SubmissionState => {
  const current = record(context.state?.publicSubmission);
  return {
    values: record(current?.values) ?? {}, errors: record(current?.errors) as Record<string, string[]> ?? {},
    globalErrors: Array.isArray(current?.globalErrors) ? current.globalErrors.filter((item): item is string => typeof item === 'string') : [],
    submitting: current?.submitting === true, submitted: current?.submitted === true,
    receipt: record(current?.receipt), idempotencyKey: typeof current?.idempotencyKey === 'string' ? current.idempotencyKey : createKey(),
  };
};
const commit = (context: G7ActionContext, next: SubmissionState): void => context.setState?.({ publicSubmission: next });
const createKey = (): string => {
  if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
  const bytes = new Uint8Array(16); crypto.getRandomValues(bytes);
  return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
};
const api = (): G7ApiClient => {
  const client = window.G7Core?.api;
  if (!client) throw new Error('glitter_formbuilder.api_unavailable');
  return client;
};

export const submissionHandlers: Record<string, G7ActionHandler> = {
  initializeSubmission: (_action, context): void => commit(context, state(context)),
  updateSubmissionField: (action, context): void => {
    const key = typeof action.params?.key === 'string' ? action.params.key : '';
    if (!key) return;
    const current = state(context);
    commit(context, { ...current, values: { ...current.values, [key]: action.params?.value }, errors: {}, globalErrors: [], idempotencyKey: createKey() });
  },
  submitForm: async (action, context): Promise<void> => {
    const current = state(context); const slug = typeof action.params?.slug === 'string' ? action.params.slug : '';
    if (!slug || current.submitting || current.submitted) return;
    commit(context, { ...current, submitting: true, errors: {}, globalErrors: [] });
    try {
      const response = await api().post(`/api/modules/glitter-form_builder/forms/${encodeURIComponent(slug)}/submissions`, {
        fields: current.values,
        context: { locale: action.params?.locale, timezone: action.params?.timezone },
      }, { headers: { 'Idempotency-Key': current.idempotencyKey } });
      const root = record(response) ?? {}; const data = record(root.data) ?? root; const payload = record(data.data) ?? data;
      commit(context, { ...state(context), submitting: false, submitted: true, receipt: record(payload.submission), errors: {}, globalErrors: [] });
    } catch (error) {
      const response = record(record(error)?.response); const data = record(response?.data) ?? response ?? {};
      const errors = record(data.errors) as Record<string, string[]> | null;
      commit(context, { ...state(context), submitting: false, errors: errors ?? {}, globalErrors: errors ? [] : ['submission_failed'] });
    }
  },
  resetSubmission: (_action, context): void => commit(context, { values: {}, errors: {}, globalErrors: [], submitting: false, submitted: false, receipt: null, idempotencyKey: createKey() }),
};
