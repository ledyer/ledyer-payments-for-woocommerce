import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    outDir: 'assets',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        'js/ledyer-payments': resolve(__dirname, 'src/assets/js/ledyer-payments.js'),
      },
      output: {
        entryFileNames: '[name].min.js',
        chunkFileNames: '[name].min.js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name.endsWith('.css')) {
            return 'css/[name].min.[ext]';
          }
          return '[name].min.[ext]';
        },
      },
    },
    minify: 'terser',
    sourcemap: true,
  },
});
