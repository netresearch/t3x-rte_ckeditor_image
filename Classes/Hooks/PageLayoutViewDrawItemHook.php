<?php

namespace Netresearch\RteCKEditorImage\Hooks;

use TYPO3\CMS\Backend\View\PageLayoutView;
use TYPO3\CMS\Backend\View\PageLayoutViewDrawItemHookInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hook to render preview widget of custom content elements in page module
 *
 * PHP version 7
 *
 * @category   Netresearch
 * @package    RteCKEditor
 * @subpackage Hooks
 * @author     Mathias Uhlmann <mathias.uhlmann@netresearch.de>
 * @license    http://www.gnu.de/documents/gpl-2.0.de.html GPL 2.0+
 * @link       http://www.netresearch.de
 *
 * @see \TYPO3\CMS\Backend\View\PageLayoutView::tt_content_drawItem()
 */
class PageLayoutViewDrawItemHook implements PageLayoutViewDrawItemHookInterface {

    /**
     * Rendering for custom content elements
     *
     * @param PageLayoutView $parentObject
     * @param bool $drawItem
     * @param string $headerContent
     * @param string $itemContent
     * @param array $row
     */
    public function preProcess(PageLayoutView &$parentObject, &$drawItem, &$headerContent, &$itemContent, array &$row) {
        if($row['CType'] !== 'text') return;

        $drawItem = false;
        $header = '<strong>' . htmlspecialchars($row['header']) . '</strong><br />';
        $headerContent = $parentObject->linkEditContent($header, $row);
        $content = $this->parseImages($row['bodytext']);

        // Configure template file
        /** @var \TYPO3\CMS\Fluid\View\StandaloneView $fluidTemplate */
        $fluidTmplFilePath = GeneralUtility::getFileAbsFileName('typo3conf/ext/rte_ckeditor_image/Resources/Private/Templates/Preview/Text.html');
        $fluidTmpl = GeneralUtility::makeInstance('TYPO3\CMS\Fluid\View\StandaloneView');
        $fluidTmpl->setTemplatePathAndFilename($fluidTmplFilePath);
        $fluidTmpl->assign('content', $content);

        // Renter template
        $itemContent = $parentObject->linkEditContent($fluidTmpl->render(), $row);
    }

    /**
     * Replaces images with dummy icon
     *
     * @param string $content
     * @return string
     */
    public function parseImages(string $content) {
        $dummyImage = '../typo3conf/ext/rte_ckeditor_image/Resources/Public/Icons/picture.png';
        return preg_replace('/(<)([img])(\w+)([^>]*>)/', '<span><img src="' . $dummyImage . '" /></span>', $content);
    }
}
