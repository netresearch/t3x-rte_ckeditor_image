<?php

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Backend\Preview;

use Netresearch\RteCKEditorImage\Backend\Preview\RteImagePreviewRenderer;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;

class RteImagePreviewRendererTest extends TestCase
{
    private RteImagePreviewRenderer $previewRenderer;

    protected function setUp(): void
    {
        parent::setUp();

        // mock RteImagePreviewRenderer
        $this->previewRenderer = $this->getMockBuilder(RteImagePreviewRenderer::class)->onlyMethods(['linkEditContent'])->getMock();
    }

    public static function additionProvider(): array
    {
        $prefix = '<?xml encoding="UTF-8">';
        $suffix = "\n";
        return [
            'control_characters' => [
                'input' => "Valid\x00Text\x08With\x0BControl\x0CChars",
                'expected' => $prefix . '<p>Valid&#65533;Text&#65533;With&#65533;Control&#65533;Chars</p>' . $suffix,
            ],
            'invalid UTF-8 surrogates' => [
                'input' => "Valid\xED\xA0\x80Text\xED\xBF\xBFWithSurrogates",
                'expected' => $prefix . '<p>Valid&#65533;Text&#65533;WithSurrogates</p>' . $suffix,
            ],
            'empty' => [
                'input' => '',
                'expected' => $prefix . $suffix,
            ],
            'text-only' => [
                'input' => 'Sample text',
                'expected' => $prefix . '<p>Sample text</p>' . $suffix,
            ],
            'text-with-image' => [
                'input' => '<p>Sample text</p><img src="image.jpg" alt="Image">',
                'expected' => $prefix . '<p>Sample text</p><img src="image.jpg" alt="Image">' . $suffix,
            ],
            'text-with-image-uuid' => [
                'input' => '<p>Sample text</p><img src="image.jpg" alt="Image">',
                'expected' => $prefix . '<p>Sample text</p><img src="image.jpg" alt="Image">' . $suffix,
            ],
            'sanitize-control-chars_2' => [
                'input' => "Hel\x00lo World ",
                'expected' => $prefix . '<p>He&#65533;l&#65533;lo Wo&#65533;rld&#65533; </p>' . $suffix,
            ],
            'sanitize-control-chars' => [
                'input' => "This\x07is\x1Fa\x0Btest",
                'expected' => $prefix . '<p>This&#65533;is&#65533;a&#65533;test</p>' . $suffix,
            ],
            'sanitize_surrogates' => [
                'input' => "Invalid\xED\xA0\x80surrogate",
                'expected' => $prefix . '<p>Invalid&#65533;surrogate</p>' . $suffix,
            ],
            'sanitize_non_chars' => [
                'input' => "Invalid\xF4\x8F\xBF\xBFnon-char-",
                'expected' => $prefix . '<p>Invalid&#65533;non-char</p>' . $suffix,
            ],
            'sanitize_non_chars' => [
                'input' => "Non\xEF\xBF\xBEcharacter",
                'expected' => $prefix . '<p>Non&#65533;character</p>' . $suffix,
            ],
        ];
    }

    /**
     * @dataProvider additionProvider
     */
    public function testRenderPageModulePreviewContent(mixed $input, string $expected): void
    {
        $gridColumnItem = $this->createMock(GridColumnItem::class);
        $gridColumnItem->expects($this->once())
            ->method('getRecord')
            ->willReturn(['bodytext' => $input]);


        $this->previewRenderer->expects($this->once())
            ->method('linkEditContent')
            ->with($this->equalTo($expected));

        $result = $this->previewRenderer->renderPageModulePreviewContent($gridColumnItem);

        #$this->assertSame($expected, $result);
    }
}
