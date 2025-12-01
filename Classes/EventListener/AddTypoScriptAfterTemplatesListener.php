<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Event\AfterTemplatesHaveBeenDeterminedEvent;

/**
 * Event listener to inject RTE CKEditor Image TypoScript after all sys_template rows.
 *
 * This ensures our lib.parseFunc_RTE.tags.img configuration is loaded AFTER
 * Bootstrap Package's "lib.parseFunc_RTE < lib.parseFunc" copy operation,
 * which would otherwise overwrite our configuration.
 *
 * This approach provides true zero-configuration setup for TYPO3 v13+.
 */
#[AsEventListener(
    identifier: 'rte-ckeditor-image/add-typoscript-after-templates',
    after: 'typo3/cms-core/site-sets-as-templates',
)]
final class AddTypoScriptAfterTemplatesListener
{
    public function __invoke(AfterTemplatesHaveBeenDeterminedEvent $event): void
    {
        $templateRows = $event->getTemplateRows();

        // Only add our template if there are existing templates (site has TypoScript)
        if ($templateRows === []) {
            return;
        }

        // Check if our TypoScript is already included via static template
        foreach ($templateRows as $row) {
            $includeStaticFile = $row['include_static_file'] ?? '';
            if (str_contains($includeStaticFile, 'rte_ckeditor_image')) {
                // Already included manually, don't add again
                return;
            }
        }

        // Add a virtual template row that includes our TypoScript
        // This loads AFTER all other templates, ensuring proper override order
        $templateRows[] = [
            'uid' => 'rte_ckeditor_image_auto',
            'pid' => 0,
            'title' => 'RTE CKEditor Image (auto-injected)',
            'root' => 0,
            'clear' => 0,
            'include_static_file' => 'EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/',
            'constants' => '',
            'config' => '',
            'basedOn' => '',
            'includeStaticAfterBasedOn' => 0,
            'static_file_mode' => 0,
        ];

        $event->setTemplateRows($templateRows);
    }
}
