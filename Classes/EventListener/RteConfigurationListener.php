<?php

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\EventListener;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;

class RteConfigurationListener
{
    public function __construct(private readonly UriBuilder $uriBuilder)
    {
    }

    public function __invoke(AfterPrepareConfigurationForEditorEvent $afterPrepareConfigurationForEditorEvent): void
    {
        $configuration = $afterPrepareConfigurationForEditorEvent->getConfiguration();
        $configuration['style']['typo3image'] = [
            'routeUrl' => (string)$this->uriBuilder->buildUriFromRoute('rteckeditorimage_wizard_select_image'),
        ];
        $afterPrepareConfigurationForEditorEvent->setConfiguration($configuration);
    }
}
