<?php

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit;

use Netresearch\RteCKEditorImage\EventListener\RteConfigurationListener;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;

class EventListenerTest extends TestCase
{
    public function testItModifiesEditorConfigurationCorrectly(): void
    {
        // Create the initial configuration for the event
        $initialConfiguration = [
            'format_tags' => 'p;h1;h2;h3',
            'toolbarGroups' => [
                ['name' => 'basicstyles', 'groups' => ['basicstyles', 'cleanup']],
                ['name' => 'paragraph', 'groups' => ['list', 'indent', 'blocks', 'align']],
            ],
        ];

        // Expected configuration after the event listener modifies it
        $expectedConfiguration = [
            'format_tags' => 'p;h1;h2;h3',
            'toolbarGroups' => [
                ['name' => 'basicstyles', 'groups' => ['basicstyles', 'cleanup']],
                ['name' => 'paragraph', 'groups' => ['list', 'indent', 'blocks', 'align']],
            ],
            'style' => [
                'typo3image' => [
                    'routeUrl' => '/rteckeditorimage_wizard_select_image.gif',
                ],
            ],
        ];

        // Create the event with the initial configuration and the necessary data array
        $data = [];
        // Add the appropriate data for the second argument if needed
        $event = new AfterPrepareConfigurationForEditorEvent($initialConfiguration, $data);

        // Mock the UriBuilder
        $uriBuilderMock = $this->createMock(UriBuilder::class);
        $uriBuilderMock->method('buildUriFromRoute')->willReturn('/rteckeditorimage_wizard_select_image.gif');

        // Instantiate the event listener with the mocked UriBuilder
        $listener = new RteConfigurationListener($uriBuilderMock);

        // Invoke the event listener with the event
        $listener($event);

        // Assert that the event's configuration has been modified as expected
        self::assertSame($expectedConfiguration, $event->getConfiguration());
    }
}
