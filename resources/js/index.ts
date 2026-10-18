import '../css/main.css';
import { designerHandlers } from './handlers/designerHandlers';
import { submissionHandlers } from './handlers/submissionHandlers';

const identifier = 'glitter-form_builder';
export type Dispatcher = G7ActionDispatcher;
const registeredDispatchers = new WeakSet<object>();

export function registerHandlers(dispatcher: Dispatcher): boolean {
  if (registeredDispatchers.has(dispatcher)) return false;
  const existing = new Set(dispatcher.getRegisteredHandlers?.() ?? []);
  Object.entries({ ...designerHandlers, ...submissionHandlers }).forEach(([name, handler]) => {
    const fullName = `${identifier}.${name}`;
    if (!existing.has(fullName)) dispatcher.registerHandler(fullName, handler, { category: 'module', source: identifier });
  });
  registeredDispatchers.add(dispatcher); return true;
}
export function initModule(attempt = 0): void {
  const dispatcher = window.G7Core?.getActionDispatcher?.();
  if (dispatcher) { registerHandlers(dispatcher); return; }
  if (attempt < 50) window.setTimeout(() => initModule(attempt + 1), 100);
}
if (typeof window !== 'undefined') {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initModule(), { once: true }); else initModule();
}
