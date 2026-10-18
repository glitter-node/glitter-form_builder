import { afterEach, describe, expect, it, vi } from 'vitest';
import { designerHandlers, type DesignerContext } from '../handlers/designerHandlers';
import { registerHandlers, type Dispatcher } from '../index';
import { submissionHandlers } from '../handlers/submissionHandlers';
import { initialDesignerState, updateFormMetadata, type DesignerState } from '../designer/store';

function context(): DesignerContext & { state: Record<string, unknown> } {
  const value: DesignerContext & { state: Record<string, unknown> } = { state: {} };
  value.setState = updates => { value.state = { ...value.state, ...updates }; };
  return value;
}
function batchedContext(): DesignerContext & { state: Record<string, unknown>; flush: () => void } {
  const value = { state: {}, pending: null as Record<string, unknown> | null } as DesignerContext & {
    state: Record<string, unknown>;
    pending: Record<string, unknown> | null;
    flush: () => void;
  };
  value.setState = updates => { value.pending = { ...value.state, ...updates }; };
  value.flush = () => { if (value.pending) value.state = value.pending; value.pending = null; };
  return value;
}
function staleContext(snapshot: DesignerState): DesignerContext & { externalLayoutState: Record<string, unknown> } {
  const frozenState = { formDesigner: snapshot };
  const value: DesignerContext & { externalLayoutState: Record<string, unknown> } = {
    state: frozenState,
    data: { _local: frozenState },
    externalLayoutState: frozenState,
  };
  value.setState = updates => { value.externalLayoutState = { ...value.externalLayoutState, ...updates }; };
  return value;
}
const empty = { schemaVersion: '1.0', fields: [] };
const stateAction = (state: DesignerContext & { state: Record<string, unknown> }, params: Record<string, unknown> = {}) => ({ params: { ...params, designerState: state.state.formDesigner } });
afterEach(() => { vi.restoreAllMocks(); Reflect.deleteProperty(globalThis, 'window'); });

describe('handler registration and local lifetime', () => {
  it('registers namespaced handlers once per dispatcher', () => {
    const names: string[] = []; const dispatcher: Dispatcher = { registerHandler: name => { names.push(name); }, getRegisteredHandlers: () => names };
    expect(registerHandlers(dispatcher)).toBe(true); expect(registerHandlers(dispatcher)).toBe(false);
    expect(names.length).toBe(Object.keys(designerHandlers).length + Object.keys(submissionHandlers).length); expect(names.every(name => name.startsWith('glitter-form_builder.'))).toBe(true);
  });
  it('keeps create/edit contexts isolated and resets stale selection', () => {
    const create = context(); const edit = context();
    designerHandlers.initialize({ params: { schema: empty } }, create);
    designerHandlers.initialize({ params: { schema: { schemaVersion: '1.0', fields: [] } } }, edit);
    designerHandlers.selectField({ params: { key: 'stale', index: 4 } }, edit);
    designerHandlers.initialize({ params: { schema: empty } }, edit);
    expect(create.state.formDesigner).not.toBe(edit.state.formDesigner);
    expect((edit.state.formDesigner as { selectedFieldKey: string | null }).selectedFieldKey).toBeNull();
  });
  it('initializes edit state from FormResource empty-map JSON', () => {
    const state = context();
    const field = { key: 'email_1', type: 'email', provider: 'glitter-form_builder', contractVersion: '1.0', label: { ko: '이메일' }, description: [], required: false, default: null, options: [], validation: [], config: [], sensitive: true, indexable: false, exportable: true };
    designerHandlers.initialize({ params: { schema: { schemaVersion: '1.0', fields: [field] }, metadata: { slug: 'contact', title: { ko: '문의' } } } }, state);
    expect((state.state.formDesigner as DesignerState).schema.fields[0]).toMatchObject({ description: {}, validation: {}, config: {}, options: [] });
  });
  it('returns a hydrated edit state from the actual data-source success resource without mutating a stale context', () => {
    const stale = initialDesignerState(empty);
    const state = staleContext(stale);
    const field = (key: string, type: string, label: string) => ({ key, type, provider: 'glitter-form_builder', contractVersion: '1.0', label: { ko: label }, description: [], required: false, default: null, options: [], validation: [], config: [], sensitive: false, indexable: false, exportable: true });
    const resource = {
      uuid: '019fba5a-dcc4-7397-8aa6-09299a7fc03e', slug: 'phase7-submission-test-20260801-2',
      title: { ko: 'Phase 7 Submission Test76' }, description: { ko: 'Phase 7 Submission Test46' }, access_mode: 'guest_and_authenticated',
      retention: { mode: 'manual', days: null, acknowledged_at: '2026-07-31T22:45:44.000000Z' },
      draft_schema: { schemaVersion: '1.0', fields: [field('text_1', 'text', '이름'), field('email_1', 'email', '이메일'), field('consent_1', 'consent', '개인정보 수집에 동의합니다.')] },
      abilities: { can_update: true, can_publish: true },
    };

    const hydrated = designerHandlers.hydrateForm({ params: { resource } }, state);

    expect(state.externalLayoutState.formDesigner).toBe(stale);
    expect(hydrated.metadata).toMatchObject({ slug: resource.slug, title: resource.title, description: resource.description, accessMode: 'guest_and_authenticated', retentionMode: 'manual', retentionAcknowledged: true });
    expect(hydrated.schema.fields.map(item => item.key)).toEqual(['text_1', 'email_1', 'consent_1']);
    expect(hydrated.abilities).toEqual({ canUpdate: true, canPublish: true });
    expect(hydrated).toMatchObject({ dirty: false, selectedFieldKey: null, saving: false, validating: false, publishing: false, errors: [], warnings: [], metadataErrors: {}, globalErrors: [] });
  });
  it('commits every field-local action through the layout-local setState contract without API calls', () => {
    const state = context();
    const post = vi.fn(); const put = vi.fn();
    Object.assign(globalThis, { window: { G7Core: { api: { post, put } } } });
    const field = (key: string, type: string) => ({ key, type, provider: 'glitter-form_builder', contractVersion: '1.0', label: { ko: key }, description: {}, required: false, default: null, options: [], validation: {}, config: {}, sensitive: false, indexable: false, exportable: true });
    const setState = vi.spyOn(state, 'setState');
    designerHandlers.initialize({ params: { schema: { schemaVersion: '1.0', fields: [field('name', 'text'), field('email', 'email'), field('consent', 'consent')] } } }, state);
    designerHandlers.selectField({ params: { key: 'email', index: 1 } }, state);
    designerHandlers.moveFieldUp({ params: { key: 'email' } }, state);
    designerHandlers.moveFieldDown({ params: { key: 'email' } }, state);
    designerHandlers.duplicateField({ params: { key: 'email' } }, state);
    designerHandlers.removeField({ params: { key: 'email' } }, state);
    const designer = state.state.formDesigner as { schema: { fields: Array<{ key: string }> }; dirty: boolean; selectedFieldKey: string | null };
    expect(setState).toHaveBeenCalledTimes(6);
    expect(designer.schema.fields.map(item => item.key)).toEqual(['name', 'email_1', 'consent']);
    expect(designer.selectedFieldKey).toBe('email_1');
    expect(designer.dirty).toBe(true);
    expect(post).not.toHaveBeenCalled();
    expect(put).not.toHaveBeenCalled();
  });
  it('resolves a palette type from the API catalog and commits canonical locale keys', () => {
    const state = context();
    const definitionTemplate = { key: '', type: 'text', provider: 'glitter-form_builder', contractVersion: '1.0', label: { ko: '텍스트' }, description: {}, required: false, default: null, options: [], validation: {}, config: { trim: true }, sensitive: false, indexable: false, exportable: true };
    state.data = { fieldTypes: { data: { fieldTypes: [{ type: 'text', provider: 'glitter-form_builder', contractVersion: '1.0', name: '텍스트', description: '', capabilities: { options: false, default: true, index: true }, renderer: { identifier: 'glitter-form_builder:text', contractMajor: 1, available: true }, definitionTemplate }] } } } as never;
    designerHandlers.initialize({ params: { schema: empty } }, state);
    designerHandlers.addField({ params: { type: 'text', locale: 'ko', supportedLocales: ['ko', 'en'] } }, state);
    designerHandlers.updateSelectedField({ params: { property: 'key', value: 'name' } }, state);
    designerHandlers.updateSelectedField({ params: { property: 'label', locale: 'ko', value: '이름' } }, state);
    const field = (state.state.formDesigner as { schema: { fields: Array<{ key: string; label: Record<string, string> }> } }).schema.fields[0];
    expect(field).toMatchObject({ key: 'name', label: { ko: '이름' } });
    expect(Object.keys(field.label)).toEqual(['ko']);
    expect(JSON.stringify(field)).not.toContain('{{$locale}}');
  });
  it('adds a field from the actual PHP field-types envelope with empty map values', () => {
    const initial = initialDesignerState(empty);
    const state = staleContext(initial);
    const definitionTemplate = { key: '', type: 'text', provider: 'glitter-form_builder', contractVersion: '1.0', label: { ko: '텍스트' }, description: [], required: false, default: null, options: [], validation: [], config: [], sensitive: false, indexable: false, exportable: true };
    state.data = {
      ...state.data,
      fieldTypes: { success: true, data: { fieldTypes: [{ type: 'text', provider: 'glitter-form_builder', contractVersion: '1.0', name: '텍스트', description: '한 줄 일반 텍스트입니다.', category: 'basic', capabilities: { options: false, default: true, index: true }, renderer: { identifier: 'glitter-form_builder:text', contractMajor: 1, available: true }, definitionTemplate }] } },
    } as never;

    designerHandlers.addField({ params: { type: 'text', locale: 'ko', supportedLocales: ['ko', 'en'] } }, state);

    const designer = state.externalLayoutState.formDesigner as DesignerState;
    expect(designer.schema.fields[0]).toMatchObject({ key: 'text_1', type: 'text', label: { ko: '텍스트' }, description: {}, validation: {}, config: {} });
    expect(designer.selectedFieldKey).toBe('text_1'); expect(designer.dirty).toBe(true);
  });
  it.each([
    ['missing type', undefined, null],
    ['unknown type', 'unknown', null],
    ['malformed catalog', 'text', { type: 'text', provider: 'glitter-form_builder', contractVersion: '1.0', name: '텍스트', definitionTemplate: {} }],
  ])('keeps state clean and exposes a safe action error for %s', (_case, type, catalogEntry) => {
    const initial = initialDesignerState(empty);
    const state = staleContext(initial);
    state.data = {
      ...state.data,
      fieldTypes: { data: { fieldTypes: catalogEntry ? [catalogEntry] : [] } },
    } as never;
    const post = vi.fn(); const put = vi.fn();
    Object.assign(globalThis, { window: { G7Core: { api: { post, put } } } });

    expect(() => designerHandlers.addField({ params: { type, locale: 'ko', supportedLocales: ['ko', 'en'], errorMessage: '필드를 추가할 수 없습니다.' } }, state)).not.toThrow();

    const designer = state.externalLayoutState.formDesigner as DesignerState;
    expect(designer.schema.fields).toEqual([]); expect(designer.dirty).toBe(false);
    expect(designer.globalErrors).toEqual(['필드를 추가할 수 없습니다.']);
    expect(post).not.toHaveBeenCalled(); expect(put).not.toHaveBeenCalled();
  });
  it('keeps one idempotency key across a submit attempt and rotates it when a value changes', async () => {
    const submission = context();
    submissionHandlers.initializeSubmission({}, submission);
    const firstKey = (submission.state.publicSubmission as { idempotencyKey: string }).idempotencyKey;
    submissionHandlers.updateSubmissionField({ params: { key: 'email', value: 'user@example.com' } }, submission);
    const changedKey = (submission.state.publicSubmission as { idempotencyKey: string }).idempotencyKey;
    expect(changedKey).not.toBe(firstKey);
    const post = vi.fn().mockResolvedValue({ data: { data: { submission: { uuid: 'receipt-uuid', status: 'received' } } } });
    Object.assign(globalThis, { window: { G7Core: { api: { post, put: vi.fn() } } } });
    await submissionHandlers.submitForm({ params: { slug: 'contact', locale: 'ko', timezone: 'Asia/Seoul' } }, submission);
    expect(post).toHaveBeenCalledWith(
      '/api/modules/glitter-form_builder/forms/contact/submissions',
      { fields: { email: 'user@example.com' }, context: { locale: 'ko', timezone: 'Asia/Seoul' } },
      { headers: { 'Idempotency-Key': changedKey } },
    );
    expect((submission.state.publicSubmission as { submitted: boolean }).submitted).toBe(true);
  });
  it('restores validating after a network error', async () => {
    const state = context(); designerHandlers.initialize({ params: { schema: empty } }, state);
    Object.assign(globalThis, { window: { G7Core: { api: { post: vi.fn().mockRejectedValue(new Error('network')), put: vi.fn() } } } });
    await expect(designerHandlers.validateSchema(stateAction(state), state)).rejects.toThrow('network');
    expect((state.state.formDesigner as { validating: boolean }).validating).toBe(false);
  });
  it('does not publish without permission', async () => {
    const state = context(); designerHandlers.initialize({ params: { schema: empty } }, state);
    const post = vi.fn(); Object.assign(globalThis, { window: { G7Core: { api: { post, put: vi.fn() } }, confirm: vi.fn() } });
    await designerHandlers.publish(stateAction(state, { formId: 'uuid', canPublish: false }), state); expect(post).not.toHaveBeenCalled();
  });
  it('validates canonical draft before create and navigates by public UUID', async () => {
    const state = context(); designerHandlers.initialize({ params: { schema: empty, metadata: { slug: 'contact', title: { ko: '문의' } } } }, state);
    const post = vi.fn().mockResolvedValueOnce({ data: { valid: true, errors: [], warnings: [] } }).mockResolvedValueOnce({ data: { uuid: 'public-uuid' } });
    const navigate = vi.fn(); state.navigate = navigate;
    Object.assign(globalThis, { window: { G7Core: { api: { post, put: vi.fn() } }, confirm: vi.fn() } });
    await designerHandlers.createForm(stateAction(state, { canCreate: true }), state);
    expect(post.mock.calls[0][0]).toContain('validate-schema'); expect(post.mock.calls[1][0]).toContain('/admin/forms');
    expect(navigate).toHaveBeenCalledWith('/admin/form-builder/forms/public-uuid/edit');
  });
  it('uses one explicit click-time snapshot for validate and create despite a stale action context', async () => {
    const stale = initialDesignerState({ schemaVersion: '1.0', fields: [] }, { slug: '', title: {} });
    let latest = updateFormMetadata(stale, 'slug', 'phase7-submission-test');
    latest = updateFormMetadata(latest, 'title', 'Phase 7 Submission Test', 'ko');
    latest = { ...latest, schema: { schemaVersion: '1.0', fields: [{ key: 'text_1', type: 'text', provider: 'glitter-form_builder', contractVersion: '1.0', label: { ko: '이름' }, description: {}, required: false, default: null, options: [], validation: {}, config: {}, sensitive: false, indexable: false, exportable: true }] } };
    const state = staleContext(stale);
    const post = vi.fn().mockResolvedValueOnce({ data: { valid: true, errors: [], warnings: [] } }).mockResolvedValueOnce({ data: { uuid: 'public-uuid' } });
    state.navigate = vi.fn();
    Object.assign(globalThis, { window: { G7Core: { api: { post, put: vi.fn() } } } });

    await designerHandlers.createForm({ params: { canCreate: true, designerState: latest } }, state);

    expect(post).toHaveBeenCalledTimes(2);
    expect(post.mock.calls[0][1]).toMatchObject({ schema: latest.schema });
    expect(post.mock.calls[1][1]).toMatchObject({ slug: 'phase7-submission-test', title: { ko: 'Phase 7 Submission Test' }, draft_schema: latest.schema });
    expect(state.navigate).toHaveBeenCalledWith('/admin/form-builder/forms/public-uuid/edit');
  });
  it('uses the explicit click-time snapshot for save and standalone validation', async () => {
    const stale = initialDesignerState({ schemaVersion: '1.0', fields: [] }, { slug: '', title: {} });
    const latest = updateFormMetadata(initialDesignerState({ schemaVersion: '1.0', fields: [] }, { slug: 'saved', title: { ko: '저장' } }), 'description', '최신 설명', 'ko');
    const state = staleContext(stale);
    const post = vi.fn().mockResolvedValue({ data: { valid: true, errors: [], warnings: [] } });
    const put = vi.fn().mockResolvedValue({ data: { slug: 'saved', title: { ko: '저장' }, description: { ko: '최신 설명' } } });
    Object.assign(globalThis, { window: { G7Core: { api: { post, put } } } });

    await designerHandlers.validateSchema({ params: { designerState: latest } }, state);
    await designerHandlers.saveDraft({ params: { formId: 'uuid', designerState: latest } }, state);

    expect(post).toHaveBeenCalledWith(expect.stringContaining('validate-schema'), expect.objectContaining({ schema: latest.schema }));
    expect(put).toHaveBeenCalledWith(expect.stringContaining('/uuid'), expect.objectContaining({ slug: 'saved', title: { ko: '저장' }, description: { ko: '최신 설명' }, draft_schema: latest.schema }));
  });
  it('fails closed without an explicit designer snapshot', async () => {
    const state = context(); designerHandlers.initialize({ params: { schema: empty, metadata: { slug: 'context-only', title: { ko: '전송 금지' }, retention_acknowledged: true } } }, state);
    const post = vi.fn(); const put = vi.fn();
    Object.assign(globalThis, { window: { G7Core: { api: { post, put } } } });

    await designerHandlers.validateSchema({ params: {} }, state);
    await designerHandlers.createForm({ params: { canCreate: true } }, state);
    await designerHandlers.saveDraft({ params: { formId: 'uuid' } }, state);
    await designerHandlers.publish({ params: { formId: 'uuid', canPublish: true } }, state);

    expect(post).not.toHaveBeenCalled(); expect(put).not.toHaveBeenCalled();
  });
  it('fails closed when the explicit designer snapshot is malformed', async () => {
    const state = context(); designerHandlers.initialize({ params: { schema: empty } }, state);
    const post = vi.fn(); const put = vi.fn();
    Object.assign(globalThis, { window: { G7Core: { api: { post, put } } } });
    const malformed = { metadata: { slug: 'must-not-send', title: { ko: '전송 금지' } }, schema: { schemaVersion: '1.0', fields: [{}] } };

    await expect(designerHandlers.createForm({ params: { canCreate: true, designerState: malformed } }, state)).resolves.toBeUndefined();
    await expect(designerHandlers.saveDraft({ params: { formId: 'uuid', designerState: malformed } }, state)).resolves.toBeUndefined();

    expect(post).not.toHaveBeenCalled(); expect(put).not.toHaveBeenCalled();
  });
  it('saves metadata, schema, and normalized retention in one update request', async () => {
    const state = context();
    designerHandlers.initialize({ params: { schema: empty, metadata: { slug: 'old', title: { ko: '이전' }, retention: { mode: 'manual', days: null, acknowledged_at: null } } } }, state);
    designerHandlers.updateMetadata({ params: { changes: { slug: 'new', title: { en: 'New' }, retention_mode: 'days', retention_days: '30', retention_acknowledged: true } } }, state);
    const put = vi.fn().mockResolvedValue({ data: { slug: 'new', title: { ko: '이전', en: 'New' }, retention: { mode: 'days', days: 30, acknowledged_at: '2026-08-01T00:00:00Z' } } });
    Object.assign(globalThis, { window: { G7Core: { api: { post: vi.fn(), put } } } });
    await designerHandlers.saveDraft(stateAction(state, { formId: 'uuid' }), state);
    expect(put).toHaveBeenCalledTimes(1);
    expect(put.mock.calls[0][1]).toMatchObject({ slug: 'new', title: { ko: '이전', en: 'New' }, retention_mode: 'days', retention_days: 30, retention_acknowledged: true, draft_schema: empty });
  });
  it('commits create metadata and dirty state atomically under batched layout state updates', () => {
    const state = batchedContext();
    designerHandlers.initialize({ params: { schema: empty, metadata: { slug: '', title: {}, description: {}, access_mode: 'authenticated_only', retention_mode: 'manual', retention_acknowledged: false } } }, state);
    state.flush();

    designerHandlers.updateMetadata({ params: { property: 'slug', value: 'phase7-submission-test' } }, state);
    state.flush();

    expect((state.state.formDesigner as { metadata: { slug: string }; dirty: boolean }).metadata.slug).toBe('phase7-submission-test');
    expect((state.state.formDesigner as { dirty: boolean }).dirty).toBe(true);
  });
  it('serializes localized metadata as objects and maps create 422 errors without losing input', async () => {
    const state = context();
    designerHandlers.initialize({ params: { schema: empty, metadata: { slug: '', title: {}, description: {}, access_mode: 'authenticated_only', retention_mode: 'manual', retention_acknowledged: false } } }, state);
    designerHandlers.updateMetadata({ params: { property: 'slug', value: 'phase7-submission-test' } }, state);
    designerHandlers.updateMetadata({ params: { property: 'title', locale: 'ko', value: 'Phase 7 Submission Test' } }, state);
    designerHandlers.updateMetadata({ params: { property: 'description', locale: 'ko', value: '설명' } }, state);
    designerHandlers.updateMetadata({ params: { property: 'retention_acknowledged', value: true } }, state);
    const rejection = { response: { data: { message: 'validation failed', errors: { slug: ['slug error'], title: ['title error'], retention_acknowledged: ['ack error'] } } } };
    const post = vi.fn().mockResolvedValueOnce({ data: { valid: true, errors: [], warnings: [] } }).mockRejectedValueOnce(rejection);
    Object.assign(globalThis, { window: { G7Core: { api: { post, put: vi.fn() } } } });

    await designerHandlers.createForm(stateAction(state, { canCreate: true }), state);

    expect(post.mock.calls[1][1]).toMatchObject({
      slug: 'phase7-submission-test',
      title: { ko: 'Phase 7 Submission Test' },
      description: { ko: '설명' },
      retention_acknowledged: true,
    });
    expect(Array.isArray(post.mock.calls[1][1].title)).toBe(false);
    expect((state.state.formDesigner as { metadata: object }).metadata).toMatchObject({ slug: 'phase7-submission-test', title: { ko: 'Phase 7 Submission Test' } });
    expect((state.state.formDesigner as { metadataErrors: Record<string, string[]>; creating: boolean }).metadataErrors).toEqual({ slug: ['slug error'], title: ['title error'], retention_acknowledged: ['ack error'] });
    expect((state.state.formDesigner as { creating: boolean }).creating).toBe(false);
  });
  it('blocks duplicate create clicks until the first request completes', async () => {
    const state = context();
    designerHandlers.initialize({ params: { schema: empty, metadata: { slug: 'contact', title: { ko: '문의' }, retention_mode: 'manual', retention_acknowledged: true } } }, state);
    let release: (() => void) | undefined;
    const validation = new Promise(resolve => { release = () => resolve({ data: { valid: true, errors: [], warnings: [] } }); });
    const post = vi.fn().mockImplementationOnce(() => validation).mockResolvedValueOnce({ data: { uuid: 'form-uuid' } });
    Object.assign(globalThis, { window: { G7Core: { api: { post, put: vi.fn() } } } });
    const action = stateAction(state, { canCreate: true });
    const first = designerHandlers.createForm(action, state);
    const duplicate = designerHandlers.createForm(action, state);
    release?.(); await Promise.all([first, duplicate]);
    expect(post).toHaveBeenCalledTimes(2);
  });
  it('synchronizes a successful create and leaves create mode before UUID navigation', async () => {
    const state = context(); state.state.createMode = true;
    designerHandlers.initialize({ params: { schema: empty, metadata: { slug: 'contact', title: { ko: '문의' }, retention_acknowledged: true } } }, state);
    const post = vi.fn().mockResolvedValueOnce({ data: { valid: true, errors: [], warnings: [] } }).mockResolvedValueOnce({ data: { uuid: 'form-uuid', slug: 'contact', title: { ko: '문의' }, description: {}, access_mode: 'authenticated_only', retention: { mode: 'manual', days: null, acknowledged_at: '2026-08-01T00:00:00Z' }, draft_schema: empty } });
    state.navigate = vi.fn(); Object.assign(globalThis, { window: { G7Core: { api: { post, put: vi.fn() } } } });
    await designerHandlers.createForm(stateAction(state, { canCreate: true }), state);
    expect(post).toHaveBeenCalledTimes(2);
    expect(state.state.createMode).toBe(false);
    expect(state.navigate).toHaveBeenCalledWith('/admin/form-builder/forms/form-uuid/edit');
    expect((state.state.formDesigner as { creating: boolean; dirty: boolean }).creating).toBe(false);
    expect((state.state.formDesigner as { dirty: boolean }).dirty).toBe(false);
  });
  it('normalizes the real 201 FormResource schema before create navigation and clears stale issues', async () => {
    const state = context(); state.state.createMode = true;
    const field = (key: string, type: string) => ({ key, type, provider: 'glitter-form_builder', contractVersion: '1.0', label: { ko: key }, description: {}, required: false, default: null, options: [], validation: {}, config: {}, sensitive: false, indexable: false, exportable: true });
    designerHandlers.initialize({ params: { schema: { schemaVersion: '1.0', fields: [field('text_1', 'text'), field('email_1', 'email')] }, metadata: { slug: 'phase7-submission-test-20260801', title: { ko: 'Phase 7 Submission Test' }, retention_acknowledged: true } } }, state);
    state.state.formDesigner = { ...(state.state.formDesigner as DesignerState), metadataErrors: { slug: ['이미 사용 중입니다.'] }, globalErrors: ['old'], errors: [{ code: 'old', path: 'slug', message: 'old', severity: 'error' }], warnings: [{ code: 'old', path: 'slug', message: 'old', severity: 'warning' }] };
    const resourceFields = [
      { ...field('text_1', 'text'), description: [], validation: [], config: { trim: true } },
      { ...field('email_1', 'email'), description: [], validation: [], config: [] },
    ];
    const response = { success: true, data: { uuid: '019fba4b-15a6-726d-95a4-9db170e15cda', slug: 'phase7-submission-test-20260801', status: 'draft', draft_schema: { schemaVersion: '1.0', fields: resourceFields }, abilities: { can_create: true, can_update: true, can_delete: true, can_publish: true } } };
    const post = vi.fn().mockResolvedValueOnce({ data: { valid: true, errors: [], warnings: [] } }).mockResolvedValueOnce(response);
    state.navigate = vi.fn(); Object.assign(globalThis, { window: { G7Core: { api: { post, put: vi.fn() } } } });

    await designerHandlers.createForm(stateAction(state, { canCreate: true }), state);

    const designer = state.state.formDesigner as DesignerState;
    expect(state.navigate).toHaveBeenCalledTimes(1);
    expect(state.navigate).toHaveBeenCalledWith('/admin/form-builder/forms/019fba4b-15a6-726d-95a4-9db170e15cda/edit');
    expect(designer.schema.fields[0]).toMatchObject({ description: {}, validation: {}, config: { trim: true } });
    expect(designer.schema.fields[1]).toMatchObject({ description: {}, validation: {}, config: {} });
    expect(designer.metadataErrors).toEqual({}); expect(designer.globalErrors).toEqual([]); expect(designer.errors).toEqual([]); expect(designer.warnings).toEqual([]);
    expect(post).toHaveBeenCalledTimes(2);
  });
  it('keeps key and label edits local and saves the edited canonical key once', async () => {
    const state = context();
    const field = { key: 'email_1', type: 'email', provider: 'glitter-form_builder', contractVersion: '1.0', label: { ko: '이메일' }, description: {}, required: false, default: null, options: [], validation: {}, config: { trim: true }, sensitive: true, indexable: false, exportable: true };
    designerHandlers.initialize({ params: { schema: { schemaVersion: '1.0', fields: [field] }, metadata: { slug: 'contact', title: { ko: '문의' }, retention_mode: 'manual' } } }, state);
    designerHandlers.selectField({ params: { key: 'email_1', index: 0 } }, state);
    const post = vi.fn(); const put = vi.fn().mockResolvedValue({ data: { retention: { mode: 'manual', days: null, acknowledged_at: null } } });
    Object.assign(globalThis, { window: { G7Core: { api: { post, put } } } });
    designerHandlers.updateSelectedField({ params: { changes: { key: 'contact_email' } } }, state);
    designerHandlers.updateSelectedField({ params: { changes: { label: { ko: '연락 이메일' } } } }, state);
    expect(post).not.toHaveBeenCalled(); expect(put).not.toHaveBeenCalled();
    await designerHandlers.saveDraft(stateAction(state, { formId: 'form-uuid' }), state);
    expect(put).toHaveBeenCalledTimes(1);
    expect(put.mock.calls[0][1]).toMatchObject({ draft_schema: { schemaVersion: '1.0', fields: [{ key: 'contact_email', label: { ko: '연락 이메일' }, config: { trim: true } }] } });
  });
  it('normalizes a save FormResource response and clears stale state', async () => {
    const state = context();
    const field = { key: 'email_1', type: 'email', provider: 'glitter-form_builder', contractVersion: '1.0', label: { ko: '이메일' }, description: {}, required: false, default: null, options: [], validation: {}, config: {}, sensitive: true, indexable: false, exportable: true };
    designerHandlers.initialize({ params: { schema: { schemaVersion: '1.0', fields: [field] }, metadata: { slug: 'contact', title: { ko: '문의' } } } }, state);
    state.state.formDesigner = { ...(state.state.formDesigner as DesignerState), dirty: true, metadataErrors: { slug: ['old'] }, globalErrors: ['old'], errors: [{ code: 'old', path: 'slug', message: 'old', severity: 'error' }], warnings: [{ code: 'old', path: 'slug', message: 'old', severity: 'warning' }] };
    const put = vi.fn().mockResolvedValue({ success: true, data: { uuid: 'form-uuid', slug: 'contact', title: { ko: '문의' }, draft_schema: { schemaVersion: '1.0', fields: [{ ...field, description: [], validation: [], config: [] }] } } });
    Object.assign(globalThis, { window: { G7Core: { api: { post: vi.fn(), put } } } });

    await designerHandlers.saveDraft(stateAction(state, { formId: 'form-uuid' }), state);

    const designer = state.state.formDesigner as DesignerState;
    expect(put).toHaveBeenCalledTimes(1);
    expect(designer.schema.fields[0]).toMatchObject({ description: {}, validation: {}, config: {} });
    expect(designer.dirty).toBe(false); expect(designer.metadataErrors).toEqual({}); expect(designer.globalErrors).toEqual([]); expect(designer.errors).toEqual([]); expect(designer.warnings).toEqual([]);
  });
  it('blocks publish before API validation when retention is not acknowledged', async () => {
    const state = context(); designerHandlers.initialize({ params: { schema: empty, metadata: { retention_acknowledged: false } } }, state);
    const post = vi.fn(); Object.assign(globalThis, { window: { G7Core: { api: { post, put: vi.fn() } }, confirm: vi.fn() } });
    await designerHandlers.publish(stateAction(state, { formId: 'uuid', canPublish: true, retentionRequired: 'required' }), state);
    expect(post).not.toHaveBeenCalled();
    expect((state.state.formDesigner as { errors: Array<{ code: string }> }).errors[0].code).toBe('form.retention_not_acknowledged');
  });
  it.each([
    ['permission', { formId: 'uuid', designerState: initialDesignerState(empty, { retention_acknowledged: true }), canPublish: false }, 'permission'],
    ['form id', { designerState: initialDesignerState(empty, { retention_acknowledged: true }), canPublish: true }, 'form'],
    ['designer state', { formId: 'uuid', canPublish: true }, 'state'],
  ])('fails closed with a visible error when publish %s is unavailable', async (_case, actionParams, message) => {
    const state = context(); designerHandlers.initialize({ params: { schema: empty, metadata: { retention_acknowledged: true } } }, state);
    const post = vi.fn(); Object.assign(globalThis, { window: { G7Core: { api: { post, put: vi.fn() } }, confirm: vi.fn() } });
    await designerHandlers.publish({ params: { ...actionParams, publishPermissionError: 'permission', publishFormError: 'form', publishStateError: 'state' } }, state);
    expect(post).not.toHaveBeenCalled();
    expect((state.state.formDesigner as DesignerState).globalErrors).toEqual([message]);
  });
  it('saves and validates the same explicit snapshot before publishing', async () => {
    const stale = initialDesignerState(empty, { slug: '', title: {}, retention_acknowledged: false });
    const latest = { ...initialDesignerState({ schemaVersion: '1.0', fields: [] }, { slug: 'publish-me', title: { ko: '게시' }, retention_acknowledged: true }), dirty: true };
    const state = staleContext(stale);
    const calls: string[] = [];
    const put = vi.fn().mockImplementation(async (_url, payload) => { calls.push(`save:${String(payload.slug)}`); return { data: {} }; });
    const post = vi.fn().mockImplementation(async (url, payload) => {
      if (url.includes('validate-schema')) { calls.push(`validate:${String(payload.schema.schemaVersion)}`); return { data: { valid: true, errors: [], warnings: [] } }; }
      calls.push('publish'); return { data: {} };
    });
    Object.assign(globalThis, { window: { G7Core: { api: { post, put } }, confirm: vi.fn() } });

    await designerHandlers.publish({ params: { formId: 'uuid', canPublish: true, designerState: latest } }, state);

    expect(calls).toEqual(['save:publish-me', 'validate:1.0', 'publish']);
    expect(put.mock.calls[0][1]).toMatchObject({ slug: 'publish-me', title: { ko: '게시' }, draft_schema: latest.schema });
    expect(post.mock.calls[0][1]).toMatchObject({ schema: latest.schema });
  });
  it('blocks duplicate publish clicks and restores publishing', async () => {
    const state = context(); designerHandlers.initialize({ params: { schema: empty, metadata: { retention_acknowledged: true } } }, state);
    let release: (() => void) | undefined;
    const first = new Promise<void>(resolve => { release = resolve; });
    const post = vi.fn().mockImplementationOnce(async () => { await first; return { data: { valid: true, errors: [], warnings: [] } }; }).mockResolvedValue({ data: {} });
    Object.assign(globalThis, { window: { G7Core: { api: { post, put: vi.fn() } }, confirm: vi.fn() } });
    const action = stateAction(state, { formId: 'uuid', canPublish: true });
    const one = designerHandlers.publish(action, state); const two = designerHandlers.publish(action, state);
    release?.(); await Promise.all([one, two]);
    expect(post).toHaveBeenCalledTimes(2); expect((state.state.formDesigner as { publishing: boolean }).publishing).toBe(false);
  });
  it('maps publish 422 issues and restores publishing', async () => {
    const state = context(); designerHandlers.initialize({ params: { schema: empty, metadata: { retention_acknowledged: true } } }, state);
    const issue = { code: 'field.key_duplicate', path: 'fields.1.key', message: 'duplicate', severity: 'error' };
    const post = vi.fn().mockResolvedValueOnce({ data: { valid: true, errors: [], warnings: [] } }).mockRejectedValueOnce({ response: { data: { errors: [issue], warnings: [] } } });
    Object.assign(globalThis, { window: { G7Core: { api: { post, put: vi.fn() } }, confirm: vi.fn() } });
    await expect(designerHandlers.publish(stateAction(state, { formId: 'uuid', canPublish: true, publishRequestError: 'publish failed' }), state)).resolves.toBeUndefined();
    expect((state.state.formDesigner as { errors: Array<{ code: string }>; publishing: boolean }).errors[0].code).toBe(issue.code);
    expect((state.state.formDesigner as { publishing: boolean }).publishing).toBe(false);
    expect((state.state.formDesigner as DesignerState).globalErrors).toEqual(['publish failed']);
  });
});
