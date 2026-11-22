<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Exception;

use RuntimeException;

/**
 * A temporary file needed to import an external image could not be created.
 *
 * Extends RuntimeException so that callers catching RuntimeException keep working.
 *
 * @author  Netresearch DTT GmbH
 * @license AGPL-3.0-or-later
 */
final class TemporaryFileException extends RuntimeException {}
