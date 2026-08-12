import { emptySchema, toCanonicalSchema, type CanonicalField, type CanonicalFormSchema, type FieldTypeCatalogItem, type SchemaIssue } from './schema';

export interface DesignerState {
  schema: CanonicalFormSchema; selectedFieldKey: string | null; selectedFieldIndex: number | null; dirty: boolean;
  metadata: FormMetadata; saving: boolean; creating: boolean; validating: boolean; publishing: boolean; errors: SchemaIssue[]; warnings: SchemaIssue[];
  metadataErrors: Record<string, string[]>; globalErrors: string[]; abilities: FormAbilities;
}
export interface FormAbilities { canUpdate: boolean; canPublish: boolean }
export interface FormMetadata {
  slug: string; title: Record<string, string>; description: Record<string, string>;
  accessMode: string; retentionMode: string; retentionDays: number | null; retentionAcknowledged: boolean;
}
export interface NormalizedServerFormResource {
  uuid: string | null; schema: CanonicalFormSchema; metadata: FormMetadata; abilities: FormAbilities;
}
const record = (value: unknown): Record<string, unknown> | null => value !== null && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : null;
const localeMap = (value: unknown): Record<string, string> => Object.fromEntries(Object.entries(record(value) ?? {}).filter((entry): entry is [string, string] => typeof entry[1] === 'string'));
export function normalizeFormMetadata(value: unknown): FormMetadata {
  const source = record(value) ?? {}; const retention = record(source.retention);
  const days = retention?.days ?? source.retentionDays ?? source.retention_days;
  return {
    slug: typeof source.slug === 'string' ? source.slug : '', title: localeMap(source.title), description: localeMap(source.description),
    accessMode: typeof (source.accessMode ?? source.access_mode) === 'string' ? String(source.accessMode ?? source.access_mode) : 'authenticated_only',
    retentionMode: typeof (retention?.mode ?? source.retentionMode ?? source.retention_mode) === 'string' ? String(retention?.mode ?? source.retentionMode ?? source.retention_mode) : 'manual',
    retentionDays: days === null || days === undefined || days === '' ? null : Number(days),
    retentionAcknowledged: retention?.acknowledged_at != null || source.retentionAcknowledged === true || source.retention_acknowledged === true,
  };
}
export function initialDesignerState(schema: unknown = emptySchema(), metadata: unknown = {}, abilities: unknown = {}): DesignerState {
  const source = record(abilities) ?? {};
  return { schema: toCanonicalSchema(schema), metadata: normalizeFormMetadata(metadata), selectedFieldKey: null, selectedFieldIndex: null, dirty: false, saving: false, creating: false, validating: false, publishing: false, errors: [], warnings: [], metadataErrors: {}, globalErrors: [], abilities: { canUpdate: (source.canUpdate ?? source.can_update) === true, canPublish: (source.canPublish ?? source.can_publish) === true } };
}
function normalizeServerFieldDefinition(value: unknown): unknown {
  const source = record(value);
  if (!source) return value;
  const field = structuredClone(source);
  for (const property of ['description', 'validation', 'config']) {
    if (Array.isArray(field[property]) && field[property].length === 0) field[property] = {};
  }
  return field;
}
export function normalizeServerSchema(value: unknown): CanonicalFormSchema {
  const source = record(value);
  if (!source || !Array.isArray(source.fields)) return toCanonicalSchema(value);
  return toCanonicalSchema({ ...source, fields: source.fields.map(normalizeServerFieldDefinition) });
}
export function normalizeServerFormResource(value: unknown, fallbackSchema: unknown = emptySchema(), fallbackMetadata: unknown = {}): NormalizedServerFormResource {
  const source = record(value);
  if (!source) throw new TypeError('form_resource.invalid_object');
  const fallback = normalizeFormMetadata(fallbackMetadata);
  const metadata = normalizeFormMetadata({ ...serializeMetadata(fallback), ...source });
  const rawAbilities = record(source.abilities) ?? {};
  const abilities = { canUpdate: (rawAbilities.canUpdate ?? rawAbilities.can_update) === true, canPublish: (rawAbilities.canPublish ?? rawAbilities.can_publish) === true };
  return {
    uuid: typeof source.uuid === 'string' && source.uuid !== '' ? source.uuid : null,
    schema: normalizeServerSchema(source.draft_schema ?? fallbackSchema),
    metadata,
    abilities,
  };
}
export function updateFormMetadata(state: DesignerState, property: keyof FormMetadata, value: unknown, locale = ''): DesignerState {
  let next: FormMetadata = { ...state.metadata, title: { ...state.metadata.title }, description: { ...state.metadata.description } };
  if ((property === 'title' || property === 'description') && locale) next[property] = { ...next[property], [locale]: typeof value === 'string' ? value : '' };
  else if (property === 'retentionAcknowledged') next.retentionAcknowledged = value === true;
  else if (property === 'retentionDays') next.retentionDays = value === '' || value === null ? null : Number(value);
  else if (typeof value === 'string') next = { ...next, [property]: value };
  if (property === 'retentionMode' && next.retentionMode !== 'days') next.retentionDays = null;
  const metadataErrors = { ...state.metadataErrors };
  const errorKey = ({ accessMode: 'access_mode', retentionMode: 'retention_mode', retentionDays: 'retention_days', retentionAcknowledged: 'retention_acknowledged' } as Partial<Record<keyof FormMetadata, string>>)[property] ?? property;
  delete metadataErrors[errorKey];
  return { ...state, metadata: next, dirty: true, metadataErrors, globalErrors: [] };
}
export function serializeMetadata(metadata: FormMetadata): Record<string, unknown> {
  return {
    slug: metadata.slug, title: { ...metadata.title }, description: { ...metadata.description }, access_mode: metadata.accessMode,
    retention_mode: metadata.retentionMode, retention_days: metadata.retentionMode === 'days' ? metadata.retentionDays : null,
    retention_acknowledged: metadata.retentionAcknowledged,
  };
}
export function getSelectedFieldIndex(state: DesignerState): number | null {
  if (state.selectedFieldKey === null) return null;
  const index = state.selectedFieldIndex;
  if (index !== null && index >= 0 && index < state.schema.fields.length && state.schema.fields[index].key === state.selectedFieldKey) return index;
  const resolved = state.schema.fields.findIndex(field => field.key === state.selectedFieldKey);
  return resolved < 0 ? null : resolved;
}
export function getSelectedField(state: DesignerState): CanonicalField | null {
  const index = getSelectedFieldIndex(state);
  return index === null ? null : state.schema.fields[index];
}
export function selectField(state: DesignerState, key: string): DesignerState {
  const index = state.schema.fields.findIndex(field => field.key === key);
  return { ...state, selectedFieldKey: index < 0 ? null : state.schema.fields[index].key, selectedFieldIndex: index < 0 ? null : index };
}
function nextKey(type: string, fields: CanonicalField[]): string {
  const used = new Set(fields.map(field => field.key)); let suffix = 1;
  while (used.has(`${type}_${suffix}`)) suffix++;
  return `${type}_${suffix}`;
}
function resolveFieldLocale(item: FieldTypeCatalogItem, locale: string, supportedLocales: string[] = []): string {
  const supported = supportedLocales.filter(value => /^[a-z]{2}(?:-[A-Z]{2})?$/.test(value));
  if (/^[a-z]{2}(?:-[A-Z]{2})?$/.test(locale) && (supported.length === 0 || supported.includes(locale))) return locale;
  const templateLocale = Object.keys(item.definitionTemplate.label).find(value => /^[a-z]{2}(?:-[A-Z]{2})?$/.test(value) && (supported.length === 0 || supported.includes(value)));
  if (templateLocale) return templateLocale;
  if (supported[0]) return supported[0];
  throw new TypeError('field.locale_unavailable');
}
export function createFieldDefinition(item: FieldTypeCatalogItem, fields: CanonicalField[], locale: string, supportedLocales: string[] = []): CanonicalField {
  if (!/^[a-z][a-z0-9_]{0,63}$/.test(item.type) || item.definitionTemplate.type !== item.type || item.definitionTemplate.provider !== item.provider || item.definitionTemplate.contractVersion !== item.contractVersion) throw new TypeError('field.catalog_contract_invalid');
  const resolvedLocale = resolveFieldLocale(item, locale, supportedLocales);
  const candidate = structuredClone(item.definitionTemplate) as unknown as Record<string, unknown>;
  for (const property of ['description', 'validation', 'config']) {
    if (Array.isArray(candidate[property]) && candidate[property].length === 0) candidate[property] = {};
  }
  candidate.key = nextKey(item.type, fields);
  candidate.label = { [resolvedLocale]: item.name };
  return toCanonicalSchema({ schemaVersion: '1.0', fields: [candidate] }).fields[0];
}
export function addField(state: DesignerState, item: FieldTypeCatalogItem, locale = '', supportedLocales: string[] = []): DesignerState {
  const field = createFieldDefinition(item, state.schema.fields, locale, supportedLocales);
  return { ...state, schema: { ...state.schema, fields: [...state.schema.fields, field] }, selectedFieldKey: field.key, selectedFieldIndex: state.schema.fields.length, dirty: true, errors: [], warnings: [] };
}
export function removeField(state: DesignerState, key: string): DesignerState {
  const removedIndex = state.schema.fields.findIndex(field => field.key === key);
  if (removedIndex < 0) return state;
  const fields = [...state.schema.fields.slice(0, removedIndex), ...state.schema.fields.slice(removedIndex + 1)];
  const selectedIndex = state.selectedFieldKey === key
    ? (fields.length ? Math.min(Math.max(removedIndex, 0), fields.length - 1) : null)
    : fields.findIndex(field => field.key === state.selectedFieldKey);
  return { ...state, schema: { ...state.schema, fields }, selectedFieldKey: selectedIndex === null || selectedIndex < 0 ? null : fields[selectedIndex].key, selectedFieldIndex: selectedIndex === null || selectedIndex < 0 ? null : selectedIndex, dirty: true, errors: [], warnings: [] };
}
export function moveField(state: DesignerState, key: string, offset: -1 | 1): DesignerState {
  const fields = [...state.schema.fields]; const from = fields.findIndex(field => field.key === key); const to = from + offset;
  if (from < 0 || to < 0 || to >= fields.length) return state;
  [fields[from], fields[to]] = [fields[to], fields[from]];
  const selectedFieldIndex = fields.findIndex(field => field.key === state.selectedFieldKey);
  return { ...state, schema: { ...state.schema, fields }, selectedFieldIndex: selectedFieldIndex < 0 ? null : selectedFieldIndex, dirty: true, errors: [], warnings: [] };
}
export function duplicateField(state: DesignerState, key: string): DesignerState {
  const source = state.schema.fields.find(field => field.key === key); if (!source) return state;
  const copy = structuredClone(source); copy.key = nextKey(source.type, state.schema.fields);
  const index = state.schema.fields.indexOf(source);
  const fields = [...state.schema.fields.slice(0, index + 1), copy, ...state.schema.fields.slice(index + 1)];
  return { ...state, schema: { ...state.schema, fields }, selectedFieldKey: copy.key, selectedFieldIndex: index + 1, dirty: true, errors: [], warnings: [] };
}
export function updateField(state: DesignerState, key: string, changes: Partial<CanonicalField>): DesignerState {
  const fields = state.schema.fields.map(field => field.key === key ? {
    ...field, ...structuredClone(changes), type: field.type,
    label: changes.label ? { ...field.label, ...changes.label } : field.label,
    description: changes.description ? { ...field.description, ...changes.description } : field.description,
    config: changes.config ? { ...field.config, ...changes.config } : field.config,
    validation: changes.validation ? { ...field.validation, ...changes.validation } : field.validation,
  } : field);
  const changedKey = changes.key && changes.key !== key ? changes.key : key;
  return { ...state, schema: { ...state.schema, fields }, selectedFieldKey: state.selectedFieldKey === key ? changedKey : state.selectedFieldKey, dirty: true, errors: [], warnings: [] };
}
export function updateSelectedField(state: DesignerState, changes: Partial<CanonicalField>): DesignerState {
  const index = getSelectedFieldIndex(state);
  if (index === null) return state;
  const field = state.schema.fields[index];
  const updated = {
    ...field, ...structuredClone(changes), type: field.type,
    label: changes.label ? { ...field.label, ...changes.label } : field.label,
    description: changes.description ? { ...field.description, ...changes.description } : field.description,
    config: changes.config ? { ...field.config, ...changes.config } : field.config,
    validation: changes.validation ? { ...field.validation, ...changes.validation } : field.validation,
  };
  const fields = state.schema.fields.map((item, itemIndex) => itemIndex === index ? updated : item);
  return { ...state, schema: { ...state.schema, fields }, selectedFieldKey: updated.key, selectedFieldIndex: index, dirty: true, errors: [], warnings: [] };
}
export function mapIssues(issues: SchemaIssue[]): Map<number | 'global', SchemaIssue[]> {
  const mapped = new Map<number | 'global', SchemaIssue[]>();
  for (const issue of issues) {
    const match = /^fields\.(\d+)(?:\.|$)/.exec(issue.path); const key: number | 'global' = match ? Number(match[1]) : 'global';
    mapped.set(key, [...(mapped.get(key) ?? []), issue]);
  }
  return mapped;
}

export function issuesForField(issues: SchemaIssue[], fieldIndex: number): SchemaIssue[] {
  return mapIssues(issues).get(fieldIndex) ?? [];
}

export function duplicateOptionValues(field: CanonicalField): string[] {
  const seen = new Set<string>(); const duplicates = new Set<string>();
  for (const option of field.options) { if (seen.has(option.value)) duplicates.add(option.value); else seen.add(option.value); }
  return [...duplicates].sort((left, right) => left.localeCompare(right));
}
