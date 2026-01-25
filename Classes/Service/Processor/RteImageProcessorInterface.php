<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
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
