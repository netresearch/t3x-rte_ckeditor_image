<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Environment;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Interface for environment information abstraction.
 *
 * Wraps TYPO3 static calls (GeneralUtility::getIndpEnv, Environment::getPublicPath)
 * behind an injectable interface to enable unit testing.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
interface EnvironmentInfoInterface
{
    /**
     * Get the site URL.
     *
     * Wraps GeneralUtility::getIndpEnv('TYPO3_SITE_URL').
     */
    public function getSiteUrl(): string;

    /**
     * Get the request host.
     *
     * Wraps GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST').
     */
    public function getRequestHost(): string;

    /**
     * Get the public path.
     *
     * Wraps Environment::getPublicPath().
     */
    public function getPublicPath(): string;

    /**
     * Check if current request is backend context.
     *
     * @return bool True if backend request, false otherwise
     */
    public function isBackendRequest(): bool;

    /**
     * Get the current backend user.
     *
     * Returns the authenticated backend user from the current session,
     * or null if not in a backend context.
     *
     * @return BackendUserAuthentication|null
     */
    public function getBackendUser(): ?BackendUserAuthentication;
}
