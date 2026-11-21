/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Stand-in for the TYPO3 backend module `@typo3/backend/modal.js`, which
 * typo3image.js imports. Tests that load the real plugin never open a modal.
 */
export default {
  advanced() {
    throw new Error('Modal.advanced() is not available in unit tests');
  },
  dismiss() {},
};
