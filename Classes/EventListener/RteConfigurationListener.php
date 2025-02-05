<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\EventListener;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;

class RteConfigurationListener
{
    public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        $configuration                        = $event->getConfiguration();
        $configuration['style']['typo3image'] = [
            'routeUrl' => (string) $uriBuilder->buildUriFromRoute('rteckeditorimage_wizard_select_image'),
        ];
        $event->setConfiguration($configuration);
    }
}
