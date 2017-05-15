<?php
defined('TYPO3_MODE') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
    'rte_ckeditor_image', 'setup',
    '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.txt">'
);
