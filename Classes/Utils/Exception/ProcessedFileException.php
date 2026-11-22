<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Utils\Exception;

use Exception;

/**
 * A processed file (e.g. a scaled image) could not be created.
 *
 * Extends Exception so that callers catching Exception keep working.
 *
 * @author  Netresearch DTT GmbH
 * @license AGPL-3.0-or-later
 */
final class ProcessedFileException extends Exception {}
