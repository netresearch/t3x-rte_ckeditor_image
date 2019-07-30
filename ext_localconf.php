<?php
defined('TYPO3_MODE') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['softRefParser']['rtehtmlarea_images'] = \Netresearch\RteCKEditorImage\Database\RteImagesSoftReferenceIndex::class;

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
    'rte_ckeditor_image', 'setup',
    '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.txt">'
);
