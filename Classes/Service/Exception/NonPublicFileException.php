<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Exception;

use RuntimeException;

/**
 * The referenced file lies in a non-public storage and must not be rendered in the frontend.
 *
 * Thrown by ImageResolverService::resolve() so that callers can tell a blocked file
 * apart from a file that could not be resolved (which yields null). A caller that
 * catches it must output nothing for the image: the stored src of an RTE image is a
 * public URL of the file and would expose it.
 *
 * @author  Netresearch DTT GmbH
 * @license AGPL-3.0-or-later
 */
final class NonPublicFileException extends RuntimeException
{
    public function __construct(public readonly int $fileUid)
    {
        parent::__construct(sprintf('File %d is in a non-public storage', $fileUid), 1795284388);
    }
}
