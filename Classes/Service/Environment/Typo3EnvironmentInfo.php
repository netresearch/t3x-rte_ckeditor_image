<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Environment;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TYPO3 implementation of EnvironmentInfoInterface.
 *
 * Provides access to TYPO3 environment information through static calls,
 * wrapped in an injectable service for testability.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final class Typo3EnvironmentInfo implements EnvironmentInfoInterface
{
    public function getSiteUrl(): string
    {
        $siteUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');

        return is_string($siteUrl) ? $siteUrl : '';
    }

    public function getRequestHost(): string
    {
        $requestHost = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST');

        return is_string($requestHost) ? $requestHost : '';
    }

    public function getPublicPath(): string
    {
        return Environment::getPublicPath();
    }

    public function isBackendRequest(): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        if (!$request instanceof ServerRequestInterface) {
            return false;
        }

        return ApplicationType::fromRequest($request)->isBackend();
    }

    public function getBackendUser(): ?BackendUserAuthentication
    {
        $beUser = $GLOBALS['BE_USER'] ?? null;

        return $beUser instanceof BackendUserAuthentication ? $beUser : null;
    }
}
