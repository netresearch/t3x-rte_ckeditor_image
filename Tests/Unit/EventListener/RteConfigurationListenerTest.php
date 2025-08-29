<?php

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\EventListener;

use Netresearch\RteCKEditorImage\EventListener\RteConfigurationListener;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;

class RteConfigurationListenerTest extends TestCase
{
    protected RteConfigurationListener $subject;
    protected UriBuilder $uriBuilderMock;
    protected AfterPrepareConfigurationForEditorEvent $eventMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new RteConfigurationListener();
        $this->uriBuilderMock = $this->createMock(UriBuilder::class);
        GeneralUtility::setSingletonInstance(UriBuilder::class, $this->uriBuilderMock);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    public function testInvokeAddsDefaultMaxDimensionsToConfiguration(): void
    {
        $configuration = [];
        $data = ['config' => []];
        
        $this->eventMock = $this->createMock(AfterPrepareConfigurationForEditorEvent::class);
        $this->eventMock->method('getConfiguration')->willReturn($configuration);
        $this->eventMock->method('getData')->willReturn($data);
        
        $this->uriBuilderMock->method('buildUriFromRoute')->willReturn('test-route-url');
        
        $expectedConfiguration = [
            'style' => [
                'typo3image' => [
                    'routeUrl' => 'test-route-url',
                    'maxWidth' => 1920,
                    'maxHeight' => 9999
                ]
            ]
        ];
        
        $this->eventMock->expects(self::once())
            ->method('setConfiguration')
            ->with($expectedConfiguration);
        
        $this->subject->__invoke($this->eventMock);
    }

    public function testInvokeUsesCustomTsConfigValuesForMaxDimensions(): void
    {
        $configuration = [];
        $data = [
            'config' => [
                'tsConfig' => [
                    'buttons.' => [
                        'image.' => [
                            'options.' => [
                                'magic.' => [
                                    'maxWidth' => '1024',
                                    'maxHeight' => '768'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $this->eventMock = $this->createMock(AfterPrepareConfigurationForEditorEvent::class);
        $this->eventMock->method('getConfiguration')->willReturn($configuration);
        $this->eventMock->method('getData')->willReturn($data);
        
        $this->uriBuilderMock->method('buildUriFromRoute')->willReturn('test-route-url');
        
        $expectedConfiguration = [
            'style' => [
                'typo3image' => [
                    'routeUrl' => 'test-route-url',
                    'maxWidth' => 1024,
                    'maxHeight' => 768
                ]
            ]
        ];
        
        $this->eventMock->expects(self::once())
            ->method('setConfiguration')
            ->with($expectedConfiguration);
        
        $this->subject->__invoke($this->eventMock);
    }

    public function testInvokeHandlesPartialTsConfigValues(): void
    {
        $configuration = [];
        $data = [
            'config' => [
                'tsConfig' => [
                    'buttons.' => [
                        'image.' => [
                            'options.' => [
                                'magic.' => [
                                    'maxWidth' => '800',
                                    // maxHeight not set, should use default
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $this->eventMock = $this->createMock(AfterPrepareConfigurationForEditorEvent::class);
        $this->eventMock->method('getConfiguration')->willReturn($configuration);
        $this->eventMock->method('getData')->willReturn($data);
        
        $this->uriBuilderMock->method('buildUriFromRoute')->willReturn('test-route-url');
        
        $expectedConfiguration = [
            'style' => [
                'typo3image' => [
                    'routeUrl' => 'test-route-url',
                    'maxWidth' => 800,
                    'maxHeight' => 9999
                ]
            ]
        ];
        
        $this->eventMock->expects(self::once())
            ->method('setConfiguration')
            ->with($expectedConfiguration);
        
        $this->subject->__invoke($this->eventMock);
    }
}
