<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Listener\TCA;

use Netresearch\RteCKEditorImage\Backend\Preview\RteImagePreviewRenderer;
use Netresearch\RteCKEditorImage\Listener\TCA\RtePreviewRendererRegistrar;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for RtePreviewRendererRegistrar event listener.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(RtePreviewRendererRegistrar::class)]
final class RtePreviewRendererRegistrarTest extends UnitTestCase
{
    /**
     * Creates an AfterTcaCompilationEvent with the given TCA and invokes the listener.
     *
     * @param array<string, mixed> $tca
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function invokeListenerAndGetTca(array $tca, array $config = ['enableAutomaticPreviewRenderer' => true]): array
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration
            ->method('get')
            ->with('rte_ckeditor_image')
            ->willReturn($config);

        $event    = new AfterTcaCompilationEvent($tca);
        $listener = new RtePreviewRendererRegistrar($extensionConfiguration);

        $listener($event);

        /** @var array<string, mixed> */
        return $event->getTca();
    }

    #[Test]
    public function registersPreviewRendererForTypeWithColumnsOverridesEnableRichtext(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type' => 'text',
                        ],
                    ],
                ],
                'types' => [
                    'textmedia' => [
                        'columnsOverrides' => [
                            'bodytext' => [
                                'config' => [
                                    'enableRichtext' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca);

        self::assertSame(
            RteImagePreviewRenderer::class,
            $modifiedTca['tt_content']['types']['textmedia']['previewRenderer'],
        );
    }

    #[Test]
    public function registersPreviewRendererForTypeWithBaseColumnEnableRichtext(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
                'types' => [
                    'text' => [
                        'showitem' => 'bodytext',
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca);

        self::assertSame(
            RteImagePreviewRenderer::class,
            $modifiedTca['tt_content']['types']['text']['previewRenderer'],
        );
    }

    #[Test]
    public function doesNotOverwriteExistingThirdPartyPreviewRenderer(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type' => 'text',
                        ],
                    ],
                ],
                'types' => [
                    'textmedia' => [
                        'previewRenderer'  => 'Vendor\\Extension\\Preview\\CustomRenderer',
                        'columnsOverrides' => [
                            'bodytext' => [
                                'config' => [
                                    'enableRichtext' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca);

        self::assertSame(
            'Vendor\\Extension\\Preview\\CustomRenderer',
            $modifiedTca['tt_content']['types']['textmedia']['previewRenderer'],
        );
    }

    #[Test]
    public function doesNothingWhenDisabled(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type' => 'text',
                        ],
                    ],
                ],
                'types' => [
                    'textmedia' => [
                        'columnsOverrides' => [
                            'bodytext' => [
                                'config' => [
                                    'enableRichtext' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, ['enableAutomaticPreviewRenderer' => false]);

        self::assertArrayNotHasKey('previewRenderer', $modifiedTca['tt_content']['types']['textmedia']);
    }

    #[Test]
    public function respectsExcludedTables(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
                'types' => [
                    'text' => [
                        'showitem' => 'bodytext',
                    ],
                ],
            ],
            'tx_news_domain_model_news' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
                'types' => [
                    '0' => [
                        'showitem' => 'bodytext',
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, [
            'enableAutomaticPreviewRenderer' => true,
            'excludedTables'                 => 'tx_news_domain_model_news',
        ]);

        self::assertSame(
            RteImagePreviewRenderer::class,
            $modifiedTca['tt_content']['types']['text']['previewRenderer'],
        );
        self::assertArrayNotHasKey(
            'previewRenderer',
            $modifiedTca['tx_news_domain_model_news']['types']['0'],
        );
    }

    #[Test]
    public function respectsIncludedTablesOnly(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
                'types' => [
                    'text' => [
                        'showitem' => 'bodytext',
                    ],
                ],
            ],
            'tx_news_domain_model_news' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
                'types' => [
                    '0' => [
                        'showitem' => 'bodytext',
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, [
            'enableAutomaticPreviewRenderer' => true,
            'includedTablesOnly'             => 'tt_content',
        ]);

        self::assertSame(
            RteImagePreviewRenderer::class,
            $modifiedTca['tt_content']['types']['text']['previewRenderer'],
        );
        self::assertArrayNotHasKey(
            'previewRenderer',
            $modifiedTca['tx_news_domain_model_news']['types']['0'],
        );
    }

    #[Test]
    public function handlesTablesWithoutTypesArrayGracefully(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
                // No 'types' key
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca);

        self::assertArrayNotHasKey('types', $modifiedTca['tt_content']);
    }

    #[Test]
    public function handlesTypesWithoutColumnsOverridesGracefully(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type' => 'text',
                            // No enableRichtext in base column
                        ],
                    ],
                ],
                'types' => [
                    'header' => [
                        'showitem' => 'header',
                        // No columnsOverrides
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca);

        self::assertArrayNotHasKey('previewRenderer', $modifiedTca['tt_content']['types']['header']);
    }

    #[Test]
    public function worksWithMultipleTablesAndTypes(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type' => 'text',
                        ],
                    ],
                ],
                'types' => [
                    'text' => [
                        'columnsOverrides' => [
                            'bodytext' => [
                                'config' => [
                                    'enableRichtext' => true,
                                ],
                            ],
                        ],
                    ],
                    'textmedia' => [
                        'columnsOverrides' => [
                            'bodytext' => [
                                'config' => [
                                    'enableRichtext' => true,
                                ],
                            ],
                        ],
                    ],
                    'textpic' => [
                        'columnsOverrides' => [
                            'bodytext' => [
                                'config' => [
                                    'enableRichtext' => true,
                                ],
                            ],
                        ],
                    ],
                    'image' => [
                        'showitem' => 'image',
                        // No RTE bodytext
                    ],
                ],
            ],
            'tx_news_domain_model_news' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
                'types' => [
                    '0' => [
                        'showitem' => 'bodytext',
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca);

        self::assertSame(
            RteImagePreviewRenderer::class,
            $modifiedTca['tt_content']['types']['text']['previewRenderer'],
        );
        self::assertSame(
            RteImagePreviewRenderer::class,
            $modifiedTca['tt_content']['types']['textmedia']['previewRenderer'],
        );
        self::assertSame(
            RteImagePreviewRenderer::class,
            $modifiedTca['tt_content']['types']['textpic']['previewRenderer'],
        );
        self::assertArrayNotHasKey(
            'previewRenderer',
            $modifiedTca['tt_content']['types']['image'],
        );
        self::assertSame(
            RteImagePreviewRenderer::class,
            $modifiedTca['tx_news_domain_model_news']['types']['0']['previewRenderer'],
        );
    }

    #[Test]
    public function columnsOverridesFalseDoesNotRegister(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
                'types' => [
                    'plain' => [
                        'columnsOverrides' => [
                            'bodytext' => [
                                'config' => [
                                    'enableRichtext' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca);

        self::assertArrayNotHasKey('previewRenderer', $modifiedTca['tt_content']['types']['plain']);
    }

    #[Test]
    public function handlesEmptyConfiguration(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
                'types' => [
                    'text' => [
                        'showitem' => 'bodytext',
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, []);

        // Should still process (default: enabled)
        self::assertSame(
            RteImagePreviewRenderer::class,
            $modifiedTca['tt_content']['types']['text']['previewRenderer'],
        );
    }

    #[Test]
    public function inclusionListOverridesExclusionList(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
                'types' => [
                    'text' => [
                        'showitem' => 'bodytext',
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, [
            'enableAutomaticPreviewRenderer' => true,
            'includedTablesOnly'             => 'tt_content',
            'excludedTables'                 => 'tt_content',
        ]);

        self::assertSame(
            RteImagePreviewRenderer::class,
            $modifiedTca['tt_content']['types']['text']['previewRenderer'],
        );
    }
}
