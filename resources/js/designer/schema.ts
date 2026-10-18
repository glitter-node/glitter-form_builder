export const SCHEMA_VERSION = '1.0' as const;
export type JsonScalar = null | boolean | number | string;
export type JsonValue = JsonScalar | JsonValue[] | { [key: string]: JsonValue };
export type LocaleMap = Record<string, string>;
export interface CanonicalOption { value: string; label: LocaleMap }
export interface CanonicalField {
  key: string; type: string; provider: string; contractVersion: string;
  label: LocaleMap; description: LocaleMap; required: boolean; default: JsonValue;
  options: CanonicalOption[]; validation: Record<string, JsonValue>; config: Record<string, JsonValue>;
  sensitive: boolean; indexable: boolean; exportable: boolean;
}
export interface CanonicalFormSchema { schemaVersion: typeof SCHEMA_VERSION; fields: CanonicalField[] }
export interface FieldTypeCatalogItem {
  type: string; provider: string; contractVersion: string; name: string; description: string;
  capabilities: { options: boolean; default: boolean; index: boolean };
  renderer: { identifier: string; contractMajor: number; available: boolean };
  definitionTemplate: CanonicalField;
}
export interface SchemaIssue {
  code: string; path: string; message: string; message_key?: string;
  parameters?: Record<string, boolean | number | string>; severity: 'error' | 'warning';
}
const FIELD_KEYS = ['key', 'type', 'provider', 'contractVersion', 'label', 'description', 'required', 'default', 'options', 'validation', 'config', 'sensitive', 'indexable', 'exportable'] as const;
export function emptySchema(): CanonicalFormSchema { return { schemaVersion: SCHEMA_VERSION, fields: [] }; }
function isRecord(value: unknown): value is Record<string, unknown> {
  if (value === null || typeof value !== 'object' || Array.isArray(value)) return false;
  const prototype = Object.getPrototypeOf(value);
  return prototype === Object.prototype || prototype === null;
}
function safeString(value: unknown, path: string): string {
  if (typeof value !== 'string' || /[\uD800-\uDFFF]/u.test(value)) throw new TypeError(`${path}.invalid_string`);
  return value;
}
function cloneJson(value: unknown, path: string): JsonValue {
  if (value === null || typeof value === 'boolean') return value;
  if (typeof value === 'string') return safeString(value, path);
  if (typeof value === 'number') {
    if (!Number.isFinite(value)) throw new TypeError(`${path}.non_finite_number`);
    return value;
  }
  if (Array.isArray(value)) return value.map((item, index) => cloneJson(item, `${path}.${index}`));
  if (isRecord(value)) return Object.fromEntries(Object.entries(value).map(([key, item]) => [safeString(key, path), cloneJson(item, `${path}.${key}`)]));
  throw new TypeError(`${path}.non_json_value`);
}
function localeMap(value: unknown, path: string): LocaleMap {
  if (!isRecord(value)) throw new TypeError(`${path}.invalid_map`);
  return Object.fromEntries(Object.entries(value).map(([locale, text]) => [locale, safeString(text, `${path}.${locale}`)]));
}
function jsonObject(value: unknown, path: string): Record<string, JsonValue> {
  const cloned = cloneJson(value, path);
  if (cloned === null || Array.isArray(cloned) || typeof cloned !== 'object') throw new TypeError(`${path}.invalid_object`);
  return cloned;
}
function safeBoolean(value: unknown, path: string): boolean {
  if (typeof value !== 'boolean') throw new TypeError(`${path}.invalid_boolean`);
  return value;
}
function canonicalField(value: unknown, index: number): CanonicalField {
  if (!isRecord(value)) throw new TypeError(`fields.${index}.invalid_object`);
  const source = Object.fromEntries(FIELD_KEYS.filter(key => key in value).map(key => [key, value[key]]));
  if (!Array.isArray(source.options)) throw new TypeError(`fields.${index}.options.invalid_list`);
  return {
    key: safeString(source.key, `fields.${index}.key`), type: safeString(source.type, `fields.${index}.type`),
    provider: safeString(source.provider, `fields.${index}.provider`), contractVersion: safeString(source.contractVersion, `fields.${index}.contractVersion`),
    label: localeMap(source.label, `fields.${index}.label`), description: localeMap(source.description, `fields.${index}.description`),
    required: safeBoolean(source.required, `fields.${index}.required`), default: cloneJson(source.default, `fields.${index}.default`),
    options: source.options.map((option, optionIndex) => {
      if (!isRecord(option)) throw new TypeError(`fields.${index}.options.${optionIndex}.invalid_object`);
      return { value: safeString(option.value, `fields.${index}.options.${optionIndex}.value`), label: localeMap(option.label, `fields.${index}.options.${optionIndex}.label`) };
    }),
    validation: jsonObject(source.validation, `fields.${index}.validation`), config: jsonObject(source.config, `fields.${index}.config`),
    sensitive: safeBoolean(source.sensitive, `fields.${index}.sensitive`), indexable: safeBoolean(source.indexable, `fields.${index}.indexable`), exportable: safeBoolean(source.exportable, `fields.${index}.exportable`),
  };
}
export function toCanonicalSchema(value: unknown): CanonicalFormSchema {
  if (!isRecord(value) || value.schemaVersion !== SCHEMA_VERSION || !Array.isArray(value.fields)) throw new TypeError('schema.invalid_envelope');
  return { schemaVersion: SCHEMA_VERSION, fields: value.fields.map(canonicalField) };
}
