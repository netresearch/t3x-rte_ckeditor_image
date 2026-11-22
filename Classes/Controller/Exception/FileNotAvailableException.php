<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Controller\Exception;

use RuntimeException;

/**
 * The requested FAL file does not exist, is deleted, or is missing in its storage.
 *
 * Extends RuntimeException so that callers catching RuntimeException keep working.
 *
 * @author  Netresearch DTT GmbH
 * @license AGPL-3.0-or-later
 */
final class FileNotAvailableException extends RuntimeException {}
