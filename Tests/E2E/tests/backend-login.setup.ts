/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as setup } from '@playwright/test';
import { loginToBackend } from './helpers/typo3-backend';

/**
 * One backend login before the parallel workers start.
 *
 * Every backend login ends in BackendFormProtection::storeSessionTokenInRegistry(),
 * which calls Registry::set('core', 'formProtectionSessionToken:<uid>'). That
 * method counts the row and INSERTs it when the count is 0, without a lock, so
 * two first logins of the same user at the same moment both insert and the
 * second fails on the unique key sys_registry.entry_identifier: the login
 * answers 500 and the backend does not render (#926). Once the row exists,
 * every later login takes the UPDATE branch, which cannot collide.
 *
 * A freshly provisioned instance has no such row, so this login creates it
 * through the same code path the tests use. Each test still logs in by itself;
 * nothing in the suite logs out, which is the only thing that deletes the row.
 */
setup('create the form protection registry entry of the backend user', async ({ page }) => {
    await loginToBackend(page);
});
