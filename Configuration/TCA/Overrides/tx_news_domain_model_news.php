<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

defined('TYPO3') || exit;

/**
 * TCA override for tx_news_domain_model_news table.
 * Adds soft reference configuration for RTE images to work with EXT:news.
 */
call_user_func(
    static function (): void {
        // Only configure if EXT:news is installed
        if (!isset($GLOBALS['TCA']['tx_news_domain_model_news'])) {
            return;
        }

        // Only configure if bodytext field exists
        if (!isset($GLOBALS['TCA']['tx_news_domain_model_news']['columns']['bodytext'])) {
            return;
        }

        /** @var string[] $cleanSoftReferences */
        $cleanSoftReferences = explode(
            ',',
            (string) ($GLOBALS['TCA']['tx_news_domain_model_news']['columns']['bodytext']['config']['softref'] ?? ''),
        );

        // Remove obsolete soft reference key 'images', the references from RTE content to the original
        // images are handled with the key 'rtehtmlarea_images'
        $cleanSoftReferences   = array_diff($cleanSoftReferences, ['images']);
        $cleanSoftReferences[] = 'rtehtmlarea_images';

        // Set up soft reference index parsing for RTE images
        $GLOBALS['TCA']['tx_news_domain_model_news']['columns']['bodytext']['config']['softref'] = implode(
            ',',
            $cleanSoftReferences,
        );
    },
);
