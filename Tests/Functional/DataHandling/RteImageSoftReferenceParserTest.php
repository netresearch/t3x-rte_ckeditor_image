<?php
declare(strict_types=1);
namespace Netresearch\RteCKEditorImage\Tests\Functional\DataHandling;

use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class RteImageSoftReferenceParserTest extends FunctionalTestCase
{
    protected $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    /**
     * @test
     */
    public function updateReferenceIndexAddsIndexEntryForImage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ReferenceIndex/UpdateReferenceIndexImport.csv');
        (new ReferenceIndex())->updateIndex(false);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ReferenceIndex/UpdateReferenceIndexResult.csv');
    }
}
