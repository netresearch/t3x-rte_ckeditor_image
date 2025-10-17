<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

defined('TYPO3_MODE') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_parsehtml_proc.php']['transformation']['rtehtmlarea_images_db']
    = \Netresearch\RteCKEditorImage\Database\RteImagesDbHook::class;

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
    'RTE.default.proc.overruleMode := addToList(default)'
);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
    'RTE.default.proc.overruleMode := addToList(rtehtmlarea_images_db)'
);

// Warn if static template order is wrong
(function () {
    $environmentService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Extbase\Service\EnvironmentService::class
    );
    if (!$environmentService->isEnvironmentInBackendMode()) {
        return;
    }

    $queryBuilder = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Database\ConnectionPool::class
    )->getQueryBuilderForTable('sys_template');

    $templates = $queryBuilder
        ->select('uid', 'title', 'include_static_file')
        ->from('sys_template')
        ->where(
            $queryBuilder->expr()->andX(
                $queryBuilder->expr()->neq(
                    'include_static_file',
                    $queryBuilder->createNamedParameter('')
                ),
                $queryBuilder->expr()->like(
                    'include_static_file',
                    $queryBuilder->createNamedParameter('%fluid_styled_content%')
                )
            )
        )
        ->executeQuery()
        ->fetchAllAssociative();

    foreach ($templates as $template) {
        $paths = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(
            ',',
            (string)$template['include_static_file'],
            true
        );
        $rteImagePos = null;
        $fluidStyledPos = null;
        foreach ($paths as $index => $path) {
            if (strpos($path, 'rte_ckeditor_image/Configuration/TypoScript') !== false) {
                $rteImagePos = $index;
            }
            if (strpos($path, 'fluid_styled_content') !== false) {
                $fluidStyledPos = $index;
            }
        }
        if ($fluidStyledPos !== null && ($rteImagePos === null || $rteImagePos > $fluidStyledPos)) {
            $message = new \TYPO3\CMS\Core\Messaging\FlashMessage(
                'Include "CKEditor Image Support" before "fluid_styled_content" in your TypoScript template.',
                'Static template order',
                \TYPO3\CMS\Core\Messaging\FlashMessage::WARNING,
                true
            );
            \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Messaging\FlashMessageService::class
            )->getMessageQueueByIdentifier()->addMessage($message);

            \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Log\LogManager::class
            )->getLogger('rte_ckeditor_image')
                ->warning('CKEditor Image Support static template should be included before fluid_styled_content (UID ' . $template['uid'] . ')');
            break;
        }
    }
});
