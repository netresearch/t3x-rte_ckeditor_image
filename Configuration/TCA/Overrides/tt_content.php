<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

// Set up soft reference index parsing for RTE images
$GLOBALS['TCA']['tt_content']['columns']['bodytext']['config']['softref'] = implode(',', [
    'rtehtmlarea_images',
    // Remove obsolete soft reference key 'images', the references from RTE content to the original images are handled with the key 'rtehtmlarea_images'
    \TYPO3\CMS\Core\Utility\GeneralUtility::rmFromList(
        'images',
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config']['softref']
    )
]);
