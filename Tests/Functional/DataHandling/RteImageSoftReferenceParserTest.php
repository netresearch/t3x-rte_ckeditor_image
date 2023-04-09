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
        $result = (new ReferenceIndex())->updateIndex(false);
        //self::assertSame( 'Record tt_content:1 had 1 added indexes and 0 deleted indexes', $result['errors'][0]);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ReferenceIndex/UpdateReferenceIndexResult.csv');
    }
}
