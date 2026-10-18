import { describe, expect, it } from 'vitest';
import { addField, createFieldDefinition, duplicateField, duplicateOptionValues, getSelectedField, initialDesignerState, mapIssues, moveField, normalizeServerFormResource, normalizeServerSchema, removeField, selectField, serializeMetadata, updateField, updateFormMetadata, updateSelectedField } from '../designer/store';
import { toCanonicalSchema } from '../designer/schema';
import type { FieldTypeCatalogItem } from '../designer/schema';
const item = (type: string): FieldTypeCatalogItem => ({ type, provider: 'glitter-form_builder', contractVersion: '1.0', name: type, description: '', capabilities: { options: false, default: true, index: true }, renderer: { identifier: `glitter-form_builder:${type}`, contractMajor: 1, available: false }, definitionTemplate: { key: '', type, provider: 'glitter-form_builder', contractVersion: '1.0', label: { ko: type }, description: {}, required: false, default: null, options: [], validation: {}, config: {}, sensitive: false, indexable: false, exportable: true } });
const phpCatalogItem = (type: string): FieldTypeCatalogItem => ({
  ...item(type),
  definitionTemplate: { ...item(type).definitionTemplate, description: [], validation: [], config: [] } as unknown as FieldTypeCatalogItem['definitionTemplate'],
});
describe('canonical designer state', () => {
  it('creates text, email, and consent without literal binding tokens', () => {
    const fields = ['text', 'email', 'consent'].map(type => createFieldDefinition(item(type), [], 'ko'));
    expect(fields.map(field => field.key)).toEqual(['text_1', 'email_1', 'consent_1']);
    for (const field of fields) {
      expect(field.key).toMatch(/^[a-z][a-z0-9_]{0,63}$/);
      expect(Object.keys(field.label)).toEqual(['ko']);
      expect(JSON.stringify(field)).not.toContain('{{$locale}}');
      expect(JSON.stringify(field)).not.toMatch(/\{\{(?:field|type|\$fieldType)/);
    }
  });
  it('creates every built-in with canonical identity, defaults, and reusable keys', () => {
    const types = ['text', 'textarea', 'email', 'number', 'select', 'radio', 'checkbox', 'date', 'datetime', 'hidden', 'consent'];
    let state = initialDesignerState();
    for (const type of types) state = addField(state, item(type), 'en');
    expect(state.schema.fields.map(field => field.key)).toEqual(types.map(type => `${type}_1`));
    expect(state.schema.fields.every(field => field.provider === 'glitter-form_builder' && field.contractVersion === '1.0' && Object.keys(field.label)[0] === 'en')).toBe(true);
    state = addField(state, item('text'), 'en'); state = addField(state, item('text'), 'en');
    expect(state.schema.fields.slice(-2).map(field => field.key)).toEqual(['text_2', 'text_3']);
    state = removeField(state, 'text_2'); state = addField(state, item('text'), 'en');
    expect(state.schema.fields.at(-1)?.key).toBe('text_2');
  });
  it('creates every built-in from PHP empty-map JSON without weakening canonical schema parsing', () => {
    const types = ['text', 'textarea', 'email', 'number', 'select', 'radio', 'checkbox', 'date', 'datetime', 'hidden', 'consent'];
    let state = initialDesignerState();
    for (const type of types) state = addField(state, phpCatalogItem(type), 'ko', ['ko', 'en']);
    expect(state.schema.fields.map(field => field.key)).toEqual(types.map(type => `${type}_1`));
    expect(state.schema.fields.every(field => Object.keys(field.label).join() === 'ko')).toBe(true);
    expect(state.schema.fields.every(field => !Array.isArray(field.description) && !Array.isArray(field.validation) && !Array.isArray(field.config))).toBe(true);
    expect(() => toCanonicalSchema({ schemaVersion: '1.0', fields: [phpCatalogItem('text').definitionTemplate] })).toThrow('fields.0.description.invalid_map');
  });
  it('normalizes only empty FormResource map arrays before strict canonical parsing', () => {
    const field = { ...item('email').definitionTemplate, key: 'email_1', description: [], validation: [], config: [], options: [] };
    const schema = normalizeServerSchema({ schemaVersion: '1.0', fields: [field] });
    expect(schema.fields[0]).toMatchObject({ description: {}, validation: {}, config: {}, options: [] });
    expect(Array.isArray(schema.fields[0].options)).toBe(true);
    expect(() => normalizeServerSchema({ schemaVersion: '1.0', fields: [{ ...field, description: ['invalid'] }] })).toThrow('fields.0.description.invalid_map');
    expect(() => normalizeServerSchema({ schemaVersion: '1.0', fields: [{ ...field, validation: ['invalid'] }] })).toThrow('fields.0.validation.invalid_object');
    expect(() => normalizeServerSchema({ schemaVersion: '1.0', fields: [{ ...field, config: ['invalid'] }] })).toThrow('fields.0.config.invalid_object');
  });
  it('normalizes a FormResource while preserving fallback metadata and abilities', () => {
    const resource = normalizeServerFormResource({
      uuid: 'form-uuid', abilities: { can_update: true, can_publish: true },
      draft_schema: { schemaVersion: '1.0', fields: [{ ...item('text').definitionTemplate, key: 'text_1', description: [], validation: [], config: [] }] },
    }, undefined, { slug: 'contact', title: { ko: '문의' }, retention_acknowledged: true });
    expect(resource.uuid).toBe('form-uuid');
    expect(resource.metadata).toMatchObject({ slug: 'contact', title: { ko: '문의' }, retentionAcknowledged: true });
    expect(resource.abilities).toEqual({ canUpdate: true, canPublish: true });
    expect(resource.schema.fields[0]).toMatchObject({ description: {}, validation: {}, config: {} });
  });
  it('keeps create metadata in designer state and serializes locale maps as objects', () => {
    let state = initialDesignerState(undefined, { slug: '', title: {}, description: {}, accessMode: 'authenticated_only', retentionMode: 'manual', retentionDays: null, retentionAcknowledged: false });
    state = updateFormMetadata(state, 'slug', 'phase7-submission-test');
    state = updateFormMetadata(state, 'title', 'Phase 7 Submission Test', 'ko');
    state = updateFormMetadata(state, 'retentionAcknowledged', true);
    expect(state.metadata).toMatchObject({ slug: 'phase7-submission-test', title: { ko: 'Phase 7 Submission Test' }, retentionAcknowledged: true });
    expect(serializeMetadata(state.metadata)).toMatchObject({ slug: 'phase7-submission-test', title: { ko: 'Phase 7 Submission Test' }, description: {}, retention_acknowledged: true });
    expect(Array.isArray(serializeMetadata(state.metadata).title)).toBe(false);
    expect(state.dirty).toBe(true);
  });
  it('preserves locales and sibling metadata while updating selects, days, and descriptions', () => {
    let state = initialDesignerState(undefined, { slug: 'contact', title: { ko: '문의' }, description: {}, access_mode: 'authenticated_only', retention_mode: 'manual' });
    state = updateFormMetadata(state, 'title', 'Contact', 'en');
    state = updateFormMetadata(state, 'description', '설명', 'ko');
    state = updateFormMetadata(state, 'accessMode', 'guest_and_authenticated');
    state = updateFormMetadata(state, 'retentionMode', 'days');
    state = updateFormMetadata(state, 'retentionDays', '30');
    expect(state.metadata).toMatchObject({ slug: 'contact', title: { ko: '문의', en: 'Contact' }, description: { ko: '설명' }, accessMode: 'guest_and_authenticated', retentionMode: 'days', retentionDays: 30 });
    expect(serializeMetadata(state.metadata)).toMatchObject({ access_mode: 'guest_and_authenticated', retention_mode: 'days', retention_days: 30 });
    state = updateFormMetadata(state, 'retentionMode', 'forever');
    expect(serializeMetadata(state.metadata).retention_days).toBeNull();
  });
  it('adds stable keys and preserves schemaVersion', () => { let state = addField(initialDesignerState(), item('text')); state = addField(state, item('text')); expect(state.schema.schemaVersion).toBe('1.0'); expect(state.schema.fields.map(f => f.key)).toEqual(['text_1', 'text_2']); });
  it('moves, duplicates and removes fields', () => { let state = addField(initialDesignerState(), item('text')); state = addField(state, item('email')); state = moveField(state, 'email_1', -1); state = duplicateField(state, 'email_1'); state = removeField(state, 'text_1'); expect(state.schema.fields.map(f => f.key)).toEqual(['email_1', 'email_2']); });
  it('maps dot paths without exposing parameters', () => { const issue = { code: 'field.renderer_unavailable', path: 'fields.2.type', message: 'x', severity: 'warning' as const, parameters: { secret: 'hidden' } }; expect(mapIssues([issue]).get(2)?.[0].code).toBe(issue.code); });
  it('keeps checkbox boolean semantics', () => { const checkbox = item('checkbox'); checkbox.definitionTemplate.default = false; expect(addField(initialDesignerState(), checkbox).schema.fields[0].options).toEqual([]); });
  it('keeps decimal input as a string', () => { const number = item('number'); number.definitionTemplate.default = '12.30'; expect(addField(initialDesignerState(), number).schema.fields[0].default).toBe('12.30'); });
  it('strips frontend-only keys without mutating the source', () => { const source = { schemaVersion: '1.0', fields: [{ ...item('text').definitionTemplate, uiExpanded: true }], selectedFieldKey: 'text_1' }; const result = toCanonicalSchema(source); expect(result).not.toHaveProperty('selectedFieldKey'); expect(result.fields[0]).not.toHaveProperty('uiExpanded'); expect(source.fields[0]).toHaveProperty('uiExpanded'); });
  it.each([undefined, () => true, new Date(), Number.NaN])('rejects non JSON-safe defaults', invalid => { const field = item('text').definitionTemplate; field.default = invalid as never; expect(() => toCanonicalSchema({ schemaVersion: '1.0', fields: [field] })).toThrow(); });
  it('rejects malformed UTF-16 strings', () => { const field = item('text').definitionTemplate; field.label = { ko: '\uD800' }; expect(() => toCanonicalSchema({ schemaVersion: '1.0', fields: [field] })).toThrow(); });
  it('uses deterministic smallest available key after deletion', () => { let state = addField(initialDesignerState(), item('text')); state = addField(state, item('text')); state = removeField(state, 'text_1'); state = addField(state, item('text')); expect(state.schema.fields.map(field => field.key)).toEqual(['text_2', 'text_1']); });
  it('keeps selection and clears stale issues across reorder', () => { let state = addField(initialDesignerState(), item('text')); state = addField(state, item('email')); state = { ...state, errors: [{ code: 'x', path: 'fields.1.key', message: 'x', severity: 'error' }] }; state = moveField(state, 'email_1', -1); expect(state.selectedFieldIndex).toBe(0); expect(state.errors).toEqual([]); });
  it('deletes the selected field immutably and reconciles selection and issues', () => { let state = addField(initialDesignerState(), item('text')); state = addField(state, item('email')); state = addField(state, item('consent')); const original = state.schema.fields; state = { ...state, selectedFieldKey: 'email_1', selectedFieldIndex: 1, errors: [{ code: 'x', path: 'fields.1.key', message: 'x', severity: 'error' }] }; const result = removeField(state, 'email_1'); expect(result.schema.fields.map(field => field.key)).toEqual(['text_1', 'consent_1']); expect(result.selectedFieldKey).toBe('consent_1'); expect(result.selectedFieldIndex).toBe(1); expect(result.errors).toEqual([]); expect(result.dirty).toBe(true); expect(state.schema.fields).toBe(original); });
  it('duplicates adjacent with a deep-cloned smallest key and clears stale issues', () => { let state = addField(initialDesignerState(), item('email')); state.schema.fields[0].config = { nested: { enabled: true } }; state.schema.fields[0].options = [{ value: 'a', label: { ko: 'A' } }]; state = { ...state, errors: [{ code: 'x', path: 'fields.0.key', message: 'x', severity: 'error' }] }; const result = duplicateField(state, 'email_1'); expect(result.schema.fields.map(field => field.key)).toEqual(['email_1', 'email_2']); expect(result.selectedFieldKey).toBe('email_2'); expect(result.selectedFieldIndex).toBe(1); expect(result.errors).toEqual([]); expect(result.dirty).toBe(true); expect(result.schema.fields[1].config).not.toBe(result.schema.fields[0].config); expect(result.schema.fields[1].options).not.toBe(result.schema.fields[0].options); });
  it('moves by key, preserves selection and safely no-ops at boundaries', () => { let state = addField(initialDesignerState(), item('text')); state = addField(state, item('email')); state = addField(state, item('consent')); state = { ...state, selectedFieldKey: 'email_1', selectedFieldIndex: 1, dirty: false, warnings: [{ code: 'x', path: 'fields.1.key', message: 'x', severity: 'warning' }] }; const up = moveField(state, 'email_1', -1); expect(up.schema.fields.map(field => field.key)).toEqual(['email_1', 'text_1', 'consent_1']); expect(up.selectedFieldIndex).toBe(0); expect(up.warnings).toEqual([]); expect(up.dirty).toBe(true); expect(moveField(up, 'email_1', -1)).toBe(up); const down = moveField(up, 'email_1', 1); expect(down.schema.fields.map(field => field.key)).toEqual(['text_1', 'email_1', 'consent_1']); });
  it('allows duplicate edited keys for server-authoritative validation', () => { let state = addField(initialDesignerState(), item('text')); state = addField(state, item('email')); state = updateField(state, 'email_1', { key: 'text_1' }); expect(state.schema.fields.map(field => field.key)).toEqual(['text_1', 'text_1']); });
  it('detects stable duplicate option values', () => { const field = item('select').definitionTemplate; field.options = [{ value: 'b', label: { ko: 'B' } }, { value: 'a', label: { ko: 'A' } }, { value: 'b', label: { ko: 'B2' } }]; expect(duplicateOptionValues(field)).toEqual(['b']); });
  it('does not normalize scientific notation or datetime values', () => { const number = item('number').definitionTemplate; number.default = '1e3'; const datetime = item('datetime').definitionTemplate; datetime.default = '2026-07-31T12:00'; const schema = toCanonicalSchema({ schemaVersion: '1.0', fields: [number, datetime] }); expect(schema.fields.map(field => field.default)).toEqual(['1e3', '2026-07-31T12:00']); });
  it('resolves selected fields by canonical key and safely handles none or stale selection', () => { let state = addField(initialDesignerState(), item('text')); state = addField(state, item('email')); state = addField(state, item('consent')); state = selectField(state, 'email_1'); expect(getSelectedField(state)?.key).toBe('email_1'); expect(getSelectedField(state)?.label.ko).toBe('email'); expect(getSelectedField(selectField(state, 'missing'))).toBeNull(); expect(getSelectedField({ ...state, selectedFieldKey: null, selectedFieldIndex: null })).toBeNull(); });
  it('updates only the selected field key atomically without changing its label or nested configuration', () => { let state = addField(initialDesignerState(), item('text')); state = addField(state, item('email')); state.schema.fields[1].config = { nested: { enabled: true } }; state = selectField(state, 'email_1'); const original = state; const result = updateSelectedField(state, { key: 'contact_email' }); expect(result.schema.fields.map(field => field.key)).toEqual(['text_1', 'contact_email']); expect(result.selectedFieldKey).toBe('contact_email'); expect(result.selectedFieldIndex).toBe(1); expect(result.schema.fields[1].label.ko).toBe('email'); expect(result.schema.fields[1].config).toEqual({ nested: { enabled: true } }); expect(result.dirty).toBe(true); expect(original.schema.fields[1].key).toBe('email_1'); });
  it('preserves sequential key and label edits across selection changes', () => { let state = addField(initialDesignerState(), item('text')); state = addField(state, item('email')); state = addField(state, item('consent')); state = selectField(state, 'email_1'); state = updateSelectedField(state, { key: 'contact_email' }); state = updateSelectedField(state, { label: { ko: '연락처 이메일' } }); state = selectField(state, 'text_1'); state = selectField(state, 'contact_email'); expect(getSelectedField(state)?.key).toBe('contact_email'); expect(getSelectedField(state)?.label.ko).toBe('연락처 이메일'); expect(state.schema.fields[0].key).toBe('text_1'); });
  it('keeps edited duplicate and empty keys for server-authoritative validation', () => { let state = addField(initialDesignerState(), item('text')); state = addField(state, item('email')); state = selectField(state, 'email_1'); state = updateSelectedField(state, { key: 'text_1' }); expect(state.schema.fields.map(field => field.key)).toEqual(['text_1', 'text_1']); expect(state.selectedFieldIndex).toBe(1); state = updateSelectedField(state, { key: '' }); expect(state.schema.fields[1].key).toBe(''); expect(state.selectedFieldKey).toBe(''); });
});
