// Copyright (c) 2026 Netresearch DTT GmbH
// SPDX-License-Identifier: AGPL-3.0-or-later

// Lints the CKEditor plugin modules that ship in Resources/Public/JavaScript.
// The config lives beside the test suite because that is where the npm
// dependencies are. Run it from the repository root (npm run lint does) so
// that the root is ESLint's base path.
import js from '@eslint/js';
import globals from 'globals';

export default [
    {
        files: ['Resources/Public/JavaScript/**/*.js'],
        ...js.configs.recommended,
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.browser },
        },
        rules: {
            ...js.configs.recommended.rules,
            semi: ['error', 'always'],
        },
    },
];
