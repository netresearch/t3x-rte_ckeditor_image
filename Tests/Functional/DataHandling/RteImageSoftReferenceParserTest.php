<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\DataHandling;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\CMS\Core\Information\Typo3Version;
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

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ReferenceIndex/UpdateReferenceIndexResult.csv');
    }
}
