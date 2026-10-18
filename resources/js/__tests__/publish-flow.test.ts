import { describe, expect, it, vi } from 'vitest';
import { runPublishFlow } from '../designer/publishFlow';
const result = (errors: any[] = [], warnings: any[] = []) => ({ valid: errors.length === 0, errors, warnings });
describe('publish flow', () => {
  it('does not publish errors', async () => { const publish = vi.fn(); await runPublishFlow({ saveDraft: vi.fn(), validatePublish: async () => result([{ code: 'x', path: '', message: '', severity: 'error' }]), confirmWarnings: vi.fn(), publish }, false); expect(publish).not.toHaveBeenCalled(); });
  it('requires warning confirmation', async () => { const publish = vi.fn(); const warning = { code: 'field.renderer_unavailable', path: 'fields.0.type', message: '', severity: 'warning' }; await runPublishFlow({ saveDraft: vi.fn(), validatePublish: async () => result([], [warning]), confirmWarnings: async () => false, publish }, false); expect(publish).not.toHaveBeenCalled(); });
  it('saves a dirty draft before validation and publishing', async () => { const order: string[] = []; await runPublishFlow({ saveDraft: async () => { order.push('save'); }, validatePublish: async () => { order.push('validate'); return result(); }, confirmWarnings: async () => true, publish: async () => { order.push('publish'); } }, true); expect(order).toEqual(['save', 'validate', 'publish']); });
});
