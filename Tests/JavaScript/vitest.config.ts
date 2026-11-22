/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const nodeModules = fileURLToPath(new URL('./node_modules/', import.meta.url));

export default defineConfig({
  resolve: {
    alias: [
      // The plugin under test (../../Resources/Public/JavaScript/Plugins/typo3image.js)
      // imports CKEditor packages and TYPO3 modules by bare specifier, resolved
      // by the TYPO3 import map at runtime. Outside this workspace Node
      // resolution cannot find them, so map them to this workspace's copies.
      {
        find: /^@ckeditor\/(ckeditor5-[a-z0-9-]+)$/,
        replacement: `${nodeModules}@ckeditor/$1/dist/index.js`,
      },
      {
        find: /^@typo3\/backend\/modal\.js$/,
        replacement: fileURLToPath(new URL('./mocks/typo3-backend-modal.js', import.meta.url)),
      },
    ],
  },
  test: {
    globals: true,
    environment: 'jsdom',
    include: ['tests/**/*.test.ts', 'tests/**/*.test.js'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html', 'lcov'],
      reportsDirectory: './coverage',
      // Production code lives outside this workspace
      // (../../Resources/Public/JavaScript/), so v8 needs allowExternal
      // to instrument it — without this, lcov.info comes out empty even
      // though tests successfully import from there.
      allowExternal: true,
      include: ['**/Resources/Public/JavaScript/**/*.js'],
      exclude: ['**/node_modules/**', '**/Tests/**', '**/mocks/**'],
    },
  },
});
