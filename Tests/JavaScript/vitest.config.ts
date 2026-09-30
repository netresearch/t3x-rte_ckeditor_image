/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig } from 'vitest/config';

export default defineConfig({
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
