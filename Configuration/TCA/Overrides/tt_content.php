<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

defined('TYPO3') || exit;

// Override RTE configuration for bodytext field to use our preset
if (isset($GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'])) {
    $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config']['richtextConfiguration'] = 'rteWithImages';
}
