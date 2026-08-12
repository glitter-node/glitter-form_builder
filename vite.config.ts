import { defineConfig } from 'vite';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const moduleRoot = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  publicDir: false,
  build: {
    lib: {
      entry: path.resolve(moduleRoot, 'resources/js/index.ts'),
      name: 'GlitterFormBuilderModule',
      formats: ['iife'],
      fileName: 'module',
    },
    outDir: 'dist',
    emptyOutDir: true,
    sourcemap: !['0', 'false'].includes(process.env.G7_BUILD_SOURCEMAP ?? ''),
    minify: 'esbuild',
    target: 'es2020',
    chunkSizeWarningLimit: 500,
    rollupOptions: {
      output: {
        entryFileNames: 'js/module.iife.js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: assetInfo => assetInfo.name?.endsWith('.css')
          ? 'css/module[extname]'
          : 'assets/[name][extname]',
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(moduleRoot, 'resources/js'),
    },
  },
});
