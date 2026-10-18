import type { SchemaIssue } from './schema';
export interface ValidationResult { valid: boolean; errors: SchemaIssue[]; warnings: SchemaIssue[] }
export interface PublishDependencies {
  saveDraft(): Promise<void>; validatePublish(): Promise<ValidationResult>;
  confirmWarnings(warnings: SchemaIssue[]): Promise<boolean>; publish(): Promise<void>;
}
export async function runPublishFlow(deps: PublishDependencies, dirty: boolean): Promise<ValidationResult> {
  if (dirty) await deps.saveDraft();
  const result = await deps.validatePublish();
  if (!result.valid || result.errors.length > 0) return result;
  if (result.warnings.length > 0 && !await deps.confirmWarnings(result.warnings)) return result;
  await deps.publish(); return result;
}
