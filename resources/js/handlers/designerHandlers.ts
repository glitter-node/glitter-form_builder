import { addField, duplicateField, initialDesignerState, moveField, normalizeServerFormResource, normalizeServerSchema, removeField, selectField, serializeMetadata, updateField, updateFormMetadata, updateSelectedField, type DesignerState, type FormMetadata } from '../designer/store';
import type { CanonicalField, FieldTypeCatalogItem, SchemaIssue } from '../designer/schema';

type Parameters = Record<string, unknown>;
export type DesignerAction = G7Action;
export type DesignerContext = G7ActionContext;
type ValidationPayload = { valid: boolean; errors: SchemaIssue[]; warnings: SchemaIssue[] };
type DesignerHandlers = {
  initialize(action: DesignerAction, context: DesignerContext): void;
  hydrateForm(action: DesignerAction, context: DesignerContext): DesignerState;
  addField(action: DesignerAction, context: DesignerContext): void;
  removeField(action: DesignerAction, context: DesignerContext): void;
  moveFieldUp(action: DesignerAction, context: DesignerContext): void;
  moveFieldDown(action: DesignerAction, context: DesignerContext): void;
  duplicateField(action: DesignerAction, context: DesignerContext): void;
  updateField(action: DesignerAction, context: DesignerContext): void;
  updateSelectedField(action: DesignerAction, context: DesignerContext): void;
  selectField(action: DesignerAction, context: DesignerContext): void;
  updateMetadata(action: DesignerAction, context: DesignerContext): void;
  validateSchema(action: DesignerAction, context: DesignerContext): Promise<void>;
  saveDraft(action: DesignerAction, context: DesignerContext): Promise<void>;
  createForm(action: DesignerAction, context: DesignerContext): Promise<void>;
  publish(action: DesignerAction, context: DesignerContext): Promise<void>;
};
const params = (action: DesignerAction): Parameters => action.params ?? {};
const stringParam = (action: DesignerAction, key: string, fallback = ''): string => typeof params(action)[key] === 'string' ? params(action)[key] as string : fallback;
const current = (context: DesignerContext): DesignerState => {
  const candidate = context.state?.formDesigner ?? context.data?._local?.formDesigner;
  return candidate && typeof candidate === 'object' ? candidate as DesignerState : initialDesignerState();
};
const commit = (context: DesignerContext, value: DesignerState): void => context.setState?.({ formDesigner: value });
const record = (value: unknown): Record<string, unknown> | null => value !== null && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : null;
const responsePayload = (response: unknown): Record<string, unknown> => { const root = record(response) ?? {}; const one = record(root.data) ?? root; return record(one.data) ?? one; };
const issueList = (value: unknown, severity: 'error' | 'warning'): SchemaIssue[] => Array.isArray(value) ? value.flatMap(item => {
  const issue = record(item); return issue && typeof issue.code === 'string' && typeof issue.path === 'string' && typeof issue.message === 'string' ? [{ code: issue.code, path: issue.path, message: issue.message, severity }] : [];
}) : [];
const stateFromAction = (action: DesignerAction): DesignerState | null => {
  const source = record(params(action).designerState);
  const schema = record(source?.schema);
  if (!source || !schema || !Array.isArray(schema.fields) || !record(source.metadata)) return null;
  try {
    const normalized = initialDesignerState(schema, source.metadata, source.abilities);
    return {
      ...normalized,
      selectedFieldKey: typeof source.selectedFieldKey === 'string' ? source.selectedFieldKey : null,
      selectedFieldIndex: typeof source.selectedFieldIndex === 'number' && Number.isInteger(source.selectedFieldIndex) ? source.selectedFieldIndex : null,
      dirty: source.dirty === true,
      saving: source.saving === true,
      creating: source.creating === true,
      validating: source.validating === true,
      publishing: source.publishing === true,
      errors: issueList(source.errors, 'error'),
      warnings: issueList(source.warnings, 'warning'),
      metadataErrors: Object.fromEntries(Object.entries(record(source.metadataErrors) ?? {}).filter((entry): entry is [string, string[]] => Array.isArray(entry[1]) && entry[1].every(message => typeof message === 'string'))),
      globalErrors: Array.isArray(source.globalErrors) ? source.globalErrors.filter((message): message is string => typeof message === 'string') : [],
    };
  } catch { return null; }
};
const api = (): G7ApiClient => {
  const client = window.G7Core?.api;
  if (!client) throw new Error('glitter_formbuilder.api_unavailable');
  return client;
};
const validationPayload = (response: unknown): ValidationPayload => {
  const payload = responsePayload(response); const errors = issueList(payload.errors, 'error'); const warnings = issueList(payload.warnings, 'warning');
  return { valid: payload.valid === true && errors.length === 0, errors, warnings };
};
const catalogItem = (context: DesignerContext, type: string): FieldTypeCatalogItem | null => {
  const source = record(context.data)?.fieldTypes;
  const items = responsePayload(source).fieldTypes;
  if (!Array.isArray(items)) return null;
  const found = items.find(item => record(item)?.type === type);
  return record(found) ? found as FieldTypeCatalogItem : null;
};
const requestErrors = (error: unknown): { fields: Record<string, string[]>; global: string[] } => {
  const response = record(record(error)?.response);
  const payload = responsePayload(response ?? error);
  const raw = record(payload.errors) ?? {};
  const fields: Record<string, string[]> = {};
  for (const [key, messages] of Object.entries(raw)) {
    const values = Array.isArray(messages) ? messages.filter((message): message is string => typeof message === 'string') : [];
    const root = key.split('.')[0];
    if (values.length) fields[root] = [...(fields[root] ?? []), ...values];
  }
  const message = typeof payload.message === 'string' ? payload.message : null;
  return { fields, global: message ? [message] : [] };
};

const requestSchemaValidation = async (action: DesignerAction, snapshot: DesignerState): Promise<ValidationPayload> => {
  const locales = params(action).supportedLocales; const supportedLocales = Array.isArray(locales) ? locales.filter((locale): locale is string => typeof locale === 'string') : ['ko', 'en'];
  const mode = stringParam(action, 'mode', 'draft');
  if (mode !== 'draft' && mode !== 'publish-preview') throw new TypeError('glitter_formbuilder.validation_mode_invalid');
  return validationPayload(await api().post('/api/modules/glitter-form_builder/admin/forms/validate-schema', { schema: snapshot.schema, defaultLocale: stringParam(action, 'defaultLocale', 'ko'), supportedLocales, mode }));
};

export const designerHandlers: DesignerHandlers = {
  initialize: (action: DesignerAction, context: DesignerContext): void => {
    const existing = context.state?.formDesigner ?? context.data?._local?.formDesigner;
    const initialMetadata = params(action).metadata ?? record(existing)?.metadata ?? {};
    const initialSchema = params(action).schema;
    commit(context, initialDesignerState(initialSchema === undefined ? undefined : normalizeServerSchema(initialSchema), initialMetadata));
  },
  hydrateForm: (action: DesignerAction, _context: DesignerContext): DesignerState => {
    const normalized = normalizeServerFormResource(params(action).resource);
    return initialDesignerState(normalized.schema, normalized.metadata, normalized.abilities);
  },
  addField: (action: DesignerAction, context: DesignerContext): void => {
    const type = stringParam(action, 'type'); const item = catalogItem(context, type);
    const before = current(context);
    if (!type || !item) {
      commit(context, { ...before, globalErrors: [stringParam(action, 'errorMessage')] });
      return;
    }
    const locales = params(action).supportedLocales;
    try {
      commit(context, addField(before, item, stringParam(action, 'locale'), Array.isArray(locales) ? locales.filter((locale): locale is string => typeof locale === 'string') : []));
    } catch (error: unknown) {
      if (!(error instanceof TypeError)) throw error;
      commit(context, { ...before, globalErrors: [stringParam(action, 'errorMessage')] });
    }
  },
  removeField: (action: DesignerAction, context: DesignerContext): void => commit(context, removeField(current(context), stringParam(action, 'key'))),
  moveFieldUp: (action: DesignerAction, context: DesignerContext): void => commit(context, moveField(current(context), stringParam(action, 'key'), -1)),
  moveFieldDown: (action: DesignerAction, context: DesignerContext): void => commit(context, moveField(current(context), stringParam(action, 'key'), 1)),
  duplicateField: (action: DesignerAction, context: DesignerContext): void => commit(context, duplicateField(current(context), stringParam(action, 'key'))),
  updateField: (action: DesignerAction, context: DesignerContext): void => { const changes = params(action).changes; if (changes && typeof changes === 'object') commit(context, updateField(current(context), stringParam(action, 'key'), changes as Partial<CanonicalField>)); },
  updateSelectedField: (action: DesignerAction, context: DesignerContext): void => {
    const property = stringParam(action, 'property');
    if (property === 'key') commit(context, updateSelectedField(current(context), { key: stringParam(action, 'value') }));
    else if ((property === 'label' || property === 'description') && stringParam(action, 'locale')) commit(context, updateSelectedField(current(context), { [property]: { [stringParam(action, 'locale')]: stringParam(action, 'value') } }));
    else {
      const changes = params(action).changes;
      if (changes && typeof changes === 'object') commit(context, updateSelectedField(current(context), changes as Partial<CanonicalField>));
    }
  },
  selectField: (action: DesignerAction, context: DesignerContext): void => commit(context, selectField(current(context), stringParam(action, 'key'))),
  updateMetadata: (action: DesignerAction, context: DesignerContext): void => {
    const aliases: Record<string, keyof FormMetadata> = { slug: 'slug', title: 'title', description: 'description', access_mode: 'accessMode', accessMode: 'accessMode', retention_mode: 'retentionMode', retentionMode: 'retentionMode', retention_days: 'retentionDays', retentionDays: 'retentionDays', retention_acknowledged: 'retentionAcknowledged', retentionAcknowledged: 'retentionAcknowledged' };
    const property = stringParam(action, 'property');
    if (property && aliases[property]) {
      commit(context, updateFormMetadata(current(context), aliases[property], params(action).value, stringParam(action, 'locale')));
      return;
    }
    let next = current(context);
    for (const [key, value] of Object.entries(record(params(action).changes) ?? {})) {
      const canonical = aliases[key];
      if (!canonical) continue;
      if ((canonical === 'title' || canonical === 'description') && record(value)) {
        for (const [locale, text] of Object.entries(record(value) ?? {})) next = updateFormMetadata(next, canonical, text, locale);
      } else next = updateFormMetadata(next, canonical, value);
    }
    commit(context, next);
  },
  validateSchema: async (action: DesignerAction, context: DesignerContext): Promise<void> => {
    const snapshot = stateFromAction(action); if (!snapshot || snapshot.validating) return;
    let next: DesignerState = { ...snapshot, validating: true }; commit(context, next);
    try {
      const result = await requestSchemaValidation(action, snapshot);
      next = { ...next, errors: result.errors, warnings: result.warnings };
    } finally { commit(context, { ...next, validating: false }); }
  },
  saveDraft: async (action: DesignerAction, context: DesignerContext): Promise<void> => {
    const snapshot = stateFromAction(action); const formId = stringParam(action, 'formId'); if (!snapshot || !formId || snapshot.saving) return;
    let next: DesignerState = { ...snapshot, saving: true }; commit(context, next);
    try {
      const serialized = serializeMetadata(snapshot.metadata);
      const saved = responsePayload(await api().put(`/api/modules/glitter-form_builder/admin/forms/${encodeURIComponent(formId)}`, { ...serialized, draft_schema: snapshot.schema }));
      const normalized = normalizeServerFormResource(saved, snapshot.schema, snapshot.metadata);
      next = { ...next, schema: normalized.schema, metadata: normalized.metadata, abilities: normalized.abilities, dirty: false, errors: [], warnings: [], metadataErrors: {}, globalErrors: [] };
    }
    finally { commit(context, { ...next, saving: false }); }
  },
  createForm: async (action: DesignerAction, context: DesignerContext): Promise<void> => {
    const snapshot = stateFromAction(action); if (!snapshot || snapshot.creating || current(context).creating || params(action).canCreate !== true) return;
    let next: DesignerState = { ...snapshot, creating: true, metadataErrors: {}, globalErrors: [] }; commit(context, next);
    try {
      const validation = await requestSchemaValidation({ params: { ...params(action), mode: 'draft' } }, snapshot);
      next = { ...next, errors: validation.errors, warnings: validation.warnings };
      if (!validation.valid) return;
      const response = responsePayload(await api().post('/api/modules/glitter-form_builder/admin/forms', { ...serializeMetadata(snapshot.metadata), draft_schema: snapshot.schema }));
      const normalized = normalizeServerFormResource(response, snapshot.schema, snapshot.metadata);
      if (normalized.uuid) {
        next = { ...next, schema: normalized.schema, metadata: normalized.metadata, abilities: normalized.abilities, dirty: false, creating: false, errors: [], warnings: [], metadataErrors: {}, globalErrors: [] };
        context.setState?.({ formDesigner: next, createMode: false });
        context.navigate?.(`/admin/form-builder/forms/${encodeURIComponent(normalized.uuid)}/edit`);
      }
    } catch (error: unknown) {
      const mapped = requestErrors(error);
      next = { ...next, metadataErrors: mapped.fields, globalErrors: mapped.global };
    } finally { commit(context, { ...next, creating: false }); }
  },
  publish: async (action: DesignerAction, context: DesignerContext): Promise<void> => {
    const snapshot = stateFromAction(action); const formId = stringParam(action, 'formId');
    if (!snapshot) { commit(context, { ...current(context), globalErrors: [stringParam(action, 'publishStateError')] }); return; }
    if (!formId) { commit(context, { ...snapshot, globalErrors: [stringParam(action, 'publishFormError')] }); return; }
    if (params(action).canPublish !== true) { commit(context, { ...snapshot, globalErrors: [stringParam(action, 'publishPermissionError')] }); return; }
    if (snapshot.publishing || current(context).publishing) return;
    let next: DesignerState = { ...snapshot, publishing: true }; commit(context, next);
    try {
      if (snapshot.metadata.retentionAcknowledged !== true) {
        next = { ...next, errors: [{ code: 'form.retention_not_acknowledged', path: 'retention_acknowledged', message: stringParam(action, 'retentionRequired'), severity: 'error' }] };
        return;
      }
      if (snapshot.dirty) {
        const serialized = serializeMetadata(snapshot.metadata);
        const saved = responsePayload(await api().put(`/api/modules/glitter-form_builder/admin/forms/${encodeURIComponent(formId)}`, { ...serialized, draft_schema: snapshot.schema }));
        const normalized = normalizeServerFormResource(saved, snapshot.schema, snapshot.metadata);
        next = { ...next, schema: normalized.schema, metadata: normalized.metadata, abilities: normalized.abilities, dirty: false, metadataErrors: {}, globalErrors: [] };
      }
      const result = await requestSchemaValidation({ params: { ...params(action), mode: 'publish-preview' } }, snapshot);
      next = { ...next, errors: result.errors, warnings: result.warnings };
      if (!result.valid || result.errors.length > 0 || result.warnings.length > 0 && !window.confirm(stringParam(action, 'warningConfirmation'))) return;
      await api().post(`/api/modules/glitter-form_builder/admin/forms/${encodeURIComponent(formId)}/publish`, {});
    }
    catch (error: unknown) {
      const response = record(record(error)?.response); const details = responsePayload(response ?? error);
      next = { ...next, errors: issueList(details.errors, 'error'), warnings: issueList(details.warnings, 'warning'), globalErrors: [stringParam(action, 'publishRequestError')] };
    } finally { commit(context, { ...next, publishing: false }); }
  },
};
