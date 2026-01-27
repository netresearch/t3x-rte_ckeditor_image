<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Listener\TCA;

use Netresearch\RteCKEditorImage\Listener\TCA\RteSoftrefEnforcer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for RteSoftrefEnforcer event listener.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(RteSoftrefEnforcer::class)]
final class RteSoftrefEnforcerTest extends UnitTestCase
{
    /**
     * Creates an AfterTcaCompilationEvent with the given TCA and invokes the listener.
     *
     * @param array<string, mixed> $tca
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function invokeListenerAndGetTca(array $tca, array $config = ['enableAutomaticRteSoftref' => true]): array
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration
            ->method('get')
            ->with('rte_ckeditor_image')
            ->willReturn($config);

        $event    = new AfterTcaCompilationEvent($tca);
        $listener = new RteSoftrefEnforcer($extensionConfiguration);

        $listener($event);

        return $event->getTca();
    }

    #[Test]
    public function listenerAddsRteSoftrefToRteEnabledTextField(): void
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
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca);
        $softref     = $modifiedTca['tt_content']['columns']['bodytext']['config']['softref'];

        self::assertSame('rtehtmlarea_images', $softref);
    }

    #[Test]
    public function listenerAppendsRteSoftrefToExistingSoftref(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                            'softref'        => 'typolink_tag,email',
                        ],
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, ['enableAutomaticRteSoftref' => true]);
        $softref     = $modifiedTca['tt_content']['columns']['bodytext']['config']['softref'];

        self::assertSame('typolink_tag,email,rtehtmlarea_images', $softref);
    }

    #[Test]
    public function listenerDoesNotDuplicateRteSoftref(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                            'softref'        => 'rtehtmlarea_images',
                        ],
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, ['enableAutomaticRteSoftref' => true]);
        $softref     = $modifiedTca['tt_content']['columns']['bodytext']['config']['softref'];

        self::assertSame('rtehtmlarea_images', $softref);
    }

    #[Test]
    public function listenerRemovesObsoleteImagesReference(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                            'softref'        => 'images,typolink_tag',
                        ],
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, ['enableAutomaticRteSoftref' => true]);
        $softref     = $modifiedTca['tt_content']['columns']['bodytext']['config']['softref'];

        self::assertSame('typolink_tag,rtehtmlarea_images', $softref);
        self::assertStringNotContainsString('images,', $softref);
    }

    #[Test]
    public function listenerSkipsNonTextFields(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'header' => [
                        'config' => [
                            'type'           => 'input',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, ['enableAutomaticRteSoftref' => true]);

        self::assertArrayNotHasKey('softref', $modifiedTca['tt_content']['columns']['header']['config']);
    }

    #[Test]
    public function listenerSkipsTextFieldsWithoutRichtext(): void
    {
        $tca = [
            'tt_content' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => false,
                        ],
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, ['enableAutomaticRteSoftref' => true]);

        self::assertArrayNotHasKey('softref', $modifiedTca['tt_content']['columns']['bodytext']['config']);
    }

    #[Test]
    public function listenerDoesNotProcessWhenDisabled(): void
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
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, ['enableAutomaticRteSoftref' => false]);

        // TCA should not be modified - no softref should be added
        self::assertArrayNotHasKey('softref', $modifiedTca['tt_content']['columns']['bodytext']['config']);
    }

    #[Test]
    public function listenerRespectsExcludedTables(): void
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
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, [
            'enableAutomaticRteSoftref' => true,
            'excludedTables'            => 'tx_news_domain_model_news',
        ]);

        // tt_content should be processed
        self::assertSame(
            'rtehtmlarea_images',
            $modifiedTca['tt_content']['columns']['bodytext']['config']['softref'],
        );

        // tx_news should be skipped
        self::assertArrayNotHasKey(
            'softref',
            $modifiedTca['tx_news_domain_model_news']['columns']['bodytext']['config'],
        );
    }

    #[Test]
    public function listenerRespectsMultipleExcludedTables(): void
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
            ],
            'sys_template' => [
                'columns' => [
                    'constants' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, [
            'enableAutomaticRteSoftref' => true,
            'excludedTables'            => 'tx_news_domain_model_news, sys_template',
        ]);

        // tt_content should be processed
        self::assertSame(
            'rtehtmlarea_images',
            $modifiedTca['tt_content']['columns']['bodytext']['config']['softref'],
        );

        // Others should be skipped
        self::assertArrayNotHasKey(
            'softref',
            $modifiedTca['tx_news_domain_model_news']['columns']['bodytext']['config'],
        );
        self::assertArrayNotHasKey(
            'softref',
            $modifiedTca['sys_template']['columns']['constants']['config'],
        );
    }

    #[Test]
    public function listenerInclusionListModeProcessesOnlyIncludedTables(): void
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
            ],
            'tx_myext_article' => [
                'columns' => [
                    'bodytext' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, [
            'enableAutomaticRteSoftref' => true,
            'includedTablesOnly'        => 'tt_content,tx_news_domain_model_news',
        ]);

        // Included tables should be processed
        self::assertSame(
            'rtehtmlarea_images',
            $modifiedTca['tt_content']['columns']['bodytext']['config']['softref'],
        );
        self::assertSame(
            'rtehtmlarea_images',
            $modifiedTca['tx_news_domain_model_news']['columns']['bodytext']['config']['softref'],
        );

        // Non-included table should be skipped
        self::assertArrayNotHasKey(
            'softref',
            $modifiedTca['tx_myext_article']['columns']['bodytext']['config'],
        );
    }

    #[Test]
    public function listenerInclusionListOverridesExclusionList(): void
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
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, [
            'enableAutomaticRteSoftref' => true,
            'includedTablesOnly'        => 'tt_content',
            'excludedTables'            => 'tt_content', // Should be ignored due to inclusion list
        ]);

        // tt_content should be processed because inclusion list overrides exclusion list
        self::assertSame(
            'rtehtmlarea_images',
            $modifiedTca['tt_content']['columns']['bodytext']['config']['softref'],
        );
    }

    #[Test]
    public function listenerHandlesMissingColumnsArray(): void
    {
        $tca = [
            'tt_content' => [
                // Missing 'columns' key
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca);

        // Should not throw exception - if we get here, test passed
        self::assertIsArray($modifiedTca);
    }

    #[Test]
    public function listenerHandlesEmptyConfiguration(): void
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
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, []);

        // Should still process (default: enabled)
        self::assertSame(
            'rtehtmlarea_images',
            $modifiedTca['tt_content']['columns']['bodytext']['config']['softref'],
        );
    }

    #[Test]
    public function listenerProcessesMultipleTablesAndFields(): void
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
                    'header' => [
                        'config' => [
                            'type' => 'input',
                        ],
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
                    'teaser' => [
                        'config' => [
                            'type'           => 'text',
                            'enableRichtext' => true,
                        ],
                    ],
                ],
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, ['enableAutomaticRteSoftref' => true]);

        // All RTE fields should be processed
        self::assertSame(
            'rtehtmlarea_images',
            $modifiedTca['tt_content']['columns']['bodytext']['config']['softref'],
        );
        self::assertSame(
            'rtehtmlarea_images',
            $modifiedTca['tx_news_domain_model_news']['columns']['bodytext']['config']['softref'],
        );
        self::assertSame(
            'rtehtmlarea_images',
            $modifiedTca['tx_news_domain_model_news']['columns']['teaser']['config']['softref'],
        );

        // Non-RTE field should not be modified
        self::assertArrayNotHasKey('softref', $modifiedTca['tt_content']['columns']['header']['config']);
    }

    #[Test]
    public function listenerHandlesEmptyAndWhitespaceInTableLists(): void
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
            ],
        ];

        $modifiedTca = $this->invokeListenerAndGetTca($tca, [
            'enableAutomaticRteSoftref' => true,
            'excludedTables'            => '  ,  , ',
        ]);

        // Should process normally (empty exclusions)
        self::assertSame(
            'rtehtmlarea_images',
            $modifiedTca['tt_content']['columns']['bodytext']['config']['softref'],
        );
    }
}
