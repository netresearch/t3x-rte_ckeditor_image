/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Stand-in for the TYPO3 backend module `@typo3/backend/modal.js`, which
 * typo3image.js imports.
 *
 * advanced() records its configuration in `calls` and returns a modal with
 * a recording hideModal(), so a test can fill in the dialog content and
 * trigger a button. Reset `calls` and `hidden` between tests.
 */
export const calls = [];
export const hidden = [];

export default {
  types: { default: 'default', iframe: 'iframe' },
  sizes: { default: 'default', large: 'large' },
  advanced(config) {
    calls.push(config);
    return {
      hideModal(reason) {
        hidden.push(reason);
      },
    };
  },
  dismiss() {},
};
