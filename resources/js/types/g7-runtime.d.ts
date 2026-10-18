interface G7Action {
  params?: Record<string, unknown>;
}

interface G7ActionContext {
  state?: Record<string, unknown>;
  data?: { _local?: Record<string, unknown> };
  setState?: (updates: Record<string, unknown>) => void;
  navigate?: (path: string) => void;
}

type G7ActionHandler = (action: G7Action, context: G7ActionContext) => unknown | Promise<unknown>;

interface G7ActionDispatcher {
  registerHandler(
    name: string,
    handler: G7ActionHandler,
    options: { category: 'module'; source: string },
  ): void;
  getRegisteredHandlers?(): string[];
}

interface G7ApiClient {
  post(url: string, body: unknown, config?: { headers?: Record<string, string> }): Promise<unknown>;
  put(url: string, body: unknown): Promise<unknown>;
}

interface Window {
  G7Core?: {
    api?: G7ApiClient;
    getActionDispatcher?(): G7ActionDispatcher;
  };
}
