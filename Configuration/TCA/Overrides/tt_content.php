<?php

// Set up soft reference index parsing for RTE images
$GLOBALS['TCA']['tt_content']['columns']['bodytext']['config']['softref'] = implode(',', [
    'rtehtmlarea_images',
    // Remove obsolete soft reference key 'images', the references from RTE content to the original images are handled with the key 'rtehtmlarea_images'
    \TYPO3\CMS\Core\Utility\GeneralUtility::rmFromList(
        'images',
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config']['softref']
    )
]);
