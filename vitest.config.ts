import { defineConfig } from 'vitest/config';
export default defineConfig({
  root: import.meta.dirname,
  test: {
    environment: 'node',
    include: ['resources/js/__tests__/**/*.test.{ts,mjs}'],
  },
});
