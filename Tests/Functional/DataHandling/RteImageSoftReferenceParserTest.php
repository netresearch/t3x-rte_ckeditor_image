<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\DataHandling;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class RteImageSoftReferenceParserTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    /**
     * Verify RTE image entries against the storage relations declared by Core.
     */
    #[Test]
    public function updateReferenceIndexAddsIndexEntryForImage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ReferenceIndex/UpdateReferenceIndexImport.csv');

        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        if ($versionInformation->getMajorVersion() < 13) {
            GeneralUtility::makeInstance(ReferenceIndex::class)->updateIndex(false);
        } else {
            $this->get(ReferenceIndex::class)->updateIndex(false);
        }
        $storageHasReference = ($this
            ->get(TcaSchemaFactory::class)
            ->get('sys_file')
            ->getField('storage')
            ->getConfiguration()['foreign_table'] ?? '') === 'sys_file_storage';
        // TYPO3 14.3.8 stopped indexing sys_file.storage as a database relation.
        $expectedDataSet = $storageHasReference ? 'UpdateReferenceIndexResult.csv' : 'UpdateReferenceIndexResultWithoutStorageRelation.csv';

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ReferenceIndex/' . $expectedDataSet);
    }
}
