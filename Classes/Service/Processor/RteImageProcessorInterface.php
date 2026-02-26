<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Processor;

/**
 * Interface for RTE image processing.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
interface RteImageProcessorInterface
{
    /**
     * Process all img tags in HTML content.
     *
     * @param string $html The HTML content to process
     *
     * @return string The processed HTML content
     */
    public function process(string $html): string;
}
