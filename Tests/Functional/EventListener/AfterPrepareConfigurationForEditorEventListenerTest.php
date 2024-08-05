<?php

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class AfterPrepareConfigurationForEditorEventListenerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    public function setUp(): void
    {
        parent::setUp();
        // Additional setup if required
    }

    #[Test]
    public function it_modifies_editor_configuration_correctly(): void
    {
        $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);

        // Mock the event
        $event = new AfterPrepareConfigurationForEditorEvent(
            [
                'format_tags' => 'p;h1;h2;h3',
                'toolbarGroups' => [
                    ['name' => 'basicstyles', 'groups' => ['basicstyles', 'cleanup']],
                    ['name' => 'paragraph', 'groups' => ['list', 'indent', 'blocks', 'align']],
                ],
            ],
            [],
        );

        // Dispatch the event
        $eventDispatcher->dispatch($event);

        // Assert that the event listener modified the configuration as expected
        $expectedConfiguration = [
            'format_tags' => 'p;h1;h2;h3',
            'toolbarGroups' => [
                ['name' => 'basicstyles', 'groups' => ['basicstyles', 'cleanup']],
                ['name' => 'paragraph', 'groups' => ['list', 'indent', 'blocks', 'align']],
            ],
            'style' => [
                'typo3image' => [
                    'routeUrl' => '/typo3/rte/wizard/selectimage?token=dummyToken&mode=file',
                ],
            ],
        ];

        self::assertSame($expectedConfiguration, $event->getConfiguration());
    }
}
