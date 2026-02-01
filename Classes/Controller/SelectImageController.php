<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Controller;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TYPO3\CMS\Backend\Controller\ElementBrowserController;
use TYPO3\CMS\Backend\ElementBrowser\ElementBrowserRegistry;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Resource\DefaultUploadFolderResolver;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Controller for the image select wizard.
 *
 * @author  Christian Opitz <christian.opitz@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
class SelectImageController extends ElementBrowserController
{
    /**
     * Minimum allowed image dimension in pixels.
     */
    private const IMAGE_MIN_DIMENSION = 1;

    /**
     * Maximum allowed image dimension in pixels.
     *
     * Prevents resource exhaustion: 10000x10000px ≈ 400MB memory worst case.
     * Values above this limit will be clamped to prevent server crashes.
     */
    private const IMAGE_MAX_DIMENSION = 10000;

    /**
     * Default maximum width for images from TSConfig.
     */
    private const IMAGE_DEFAULT_MAX_WIDTH = 1920;

    /**
     * Default maximum height for images from TSConfig.
     */
    private const IMAGE_DEFAULT_MAX_HEIGHT = 9999;

    /**
     * Constructor with dependency injection.
     *
     * @param ResourceFactory             $resourceFactory        Factory for file resources
     * @param DefaultUploadFolderResolver $uploadFolderResolver   Resolver for default upload folders
     * @param ElementBrowserRegistry      $elementBrowserRegistry Registry for element browsers (required by parent)
     * @param UriBuilder                  $uriBuilder             URI builder for backend routes
     */
    public function __construct(
        private readonly ResourceFactory $resourceFactory,
        private readonly DefaultUploadFolderResolver $uploadFolderResolver,
        ElementBrowserRegistry $elementBrowserRegistry,
        private readonly UriBuilder $uriBuilder,
    ) {
        parent::__construct($elementBrowserRegistry);
    }

    /**
     * Forward to infoAction if wanted.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {

        $parsedBody  = $request->getParsedBody();
        $queryParams = $request->getQueryParams();

        // Extract action from body or query params (avoid nested coalesce for Rector compatibility)
        $actionFromBody = is_array($parsedBody) ? ($parsedBody['action'] ?? null) : null;
        $action         = $actionFromBody ?? $queryParams['action'] ?? null;

        // Dispatch to specific actions
        if ($action === 'info') {
            return $this->infoAction($request);
        }

        if ($action === 'linkBrowser' || $action === 'linkbrowser') {
            return $this->linkBrowserAction($request);
        }

        // Default: show file browser
        $bparams = explode('|', (string) $queryParams['bparams']);

        if (isset($bparams[3]) && ($bparams[3] === '')) {
            $bparams[3]             = $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];
            $queryParams['bparams'] = implode('|', $bparams);
        }

        // Resolve default upload folder for users WITHOUT explicit folder context
        // This fixes issue #290: non-admin users need explicit folder context
        // to avoid InsufficientFolderAccessPermissionsException
        // IMPORTANT: Only set if NOT already provided - allows folder browsing navigation
        if (
            !isset($queryParams['expandFolder'])
            && isset($GLOBALS['BE_USER'])
            && $GLOBALS['BE_USER'] instanceof BackendUserAuthentication
        ) {
            try {
                $folder = $this->uploadFolderResolver->resolve($GLOBALS['BE_USER']);
                if ($folder instanceof Folder) {
                    // Add expandFolder parameter with combined identifier (format: "storage_uid:/path/")
                    // TYPO3 v12+ ElementBrowser requires this parameter for folder resolution
                    $queryParams['expandFolder'] = $folder->getCombinedIdentifier();
                }
            } catch (Exception) {
                // Silently handle exceptions - parent ElementBrowserController will use default behavior
                // This ensures admin users with full access are not affected if folder resolution fails
            }
        }

        $request = $request->withQueryParams($queryParams);

        return parent::mainAction($request);
    }

    /**
     * Return the link browser URL for image linking.
     *
     * This action generates the proper URL for TYPO3's link browser wizard,
     * using the standard FormEngine pattern (not RTE-specific).
     *
     * @param ServerRequestInterface $request The request object
     *
     * @return ResponseInterface JSON response with link browser URL
     */
    public function linkBrowserAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $queryParams = $request->getQueryParams();

            // Validate and sanitize pid - must be a non-negative integer
            $pidParam = $queryParams['pid'] ?? 0;
            $pid      = max(0, (int) (is_numeric($pidParam) ? $pidParam : 0));

            // Sanitize currentValue - ensure it's a string
            $currentValue = is_string($queryParams['currentValue'] ?? '') ? ($queryParams['currentValue'] ?? '') : '';

            // Build URL using FormEngine-style parameters
            // This avoids loading the RTE-specific link browser adapter
            $linkBrowserUrl = (string) $this->uriBuilder->buildUriFromRoute(
                'wizard_link',
                [
                    'P' => [
                        'table'                 => 'tt_content',
                        'uid'                   => 0, // No specific tt_content record; page context via pid
                        'pid'                   => $pid,
                        'field'                 => 'bodytext',
                        'formName'              => 'typo3image_linkform',
                        'itemName'              => 'typo3image_link',
                        'currentValue'          => $currentValue,
                        'currentSelectedValues' => $currentValue,
                        'params'                => [
                            'blindLinkOptions' => '',
                            'blindLinkFields'  => '',
                        ],
                    ],
                ],
            );

            return new JsonResponse([
                'url' => $linkBrowserUrl,
            ]);
        } catch (Exception) {
            // Don't expose internal exception details to client
            return new JsonResponse([
                'error' => 'Failed to generate link browser URL',
            ], 500);
        }
    }

    /**
     * Retrieve image info.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function infoAction(ServerRequestInterface $request): ResponseInterface
    {
        $id              = $request->getQueryParams()['fileId'] ?? null;
        $table           = $request->getQueryParams()['table'] ?? null;
        $params          = $request->getQueryParams()['P'] ?? [];
        $params['table'] = $table;

        // Special case: translations-only request (no file ID required)
        // This allows fetching translations for button labels during plugin initialization
        if ($id === 'translations') {
            return new JsonResponse([
                'lang' => $this->getTranslations(),
            ]);
        }

        if (!$id || !is_numeric($id)) {
            return (new Response())->withStatus(412, 'Precondition Failed');
        }

        try {
            $file = $this->getImage((int) $id);
        } catch (RuntimeException) {
            return (new Response())->withStatus(404, 'Not Found');
        }

        // SECURITY: Verify user has permission to access this file (IDOR protection)
        if (!$this->isFileAccessibleByUser($file)) {
            return (new Response())->withStatus(403, 'Forbidden');
        }

        $maxDimensions = $this->getMaxDimensions($params);
        $processedFile = $this->processImage($file, $params, $maxDimensions);

        // Get original dimensions (uncapped) - allows SVGs and small images to scale up
        $originalWidth  = (int) $file->getProperty('width');
        $originalHeight = (int) $file->getProperty('height');

        // Calculate suggested display dimensions that respect aspect ratio
        $displayDimensions = $this->calculateDisplayDimensions(
            $originalWidth,
            $originalHeight,
            $maxDimensions['width'],
            $maxDimensions['height'],
        );

        return new JsonResponse([
            'uid'           => $file->getUid(),
            'alt'           => $file->getProperty('alternative') ?? '',
            'title'         => $file->getProperty('title') ?? '',
            'width'         => $originalWidth,  // Original width (uncapped) - allows scaling up
            'height'        => $originalHeight, // Original height (uncapped) - allows scaling up
            'url'           => $file->getPublicUrl(),
            'extension'     => strtolower($file->getExtension()), // For SVG detection in frontend
            'storageDriver' => $file->getStorage()->getDriverType(),
            'processed'     => [
                'width'  => $displayDimensions['width'],  // Suggested display width (respects aspect ratio)
                'height' => $displayDimensions['height'], // Suggested display height (respects aspect ratio)
                'url'    => $processedFile->getPublicUrl(),
            ],
            'lang' => $this->getTranslations(),
        ]);
    }

    /**
     * Get all translations used by the image plugin.
     *
     * @return array<string, string|null> Array of translation keys and values
     */
    protected function getTranslations(): array
    {
        return [
            'override' => LocalizationUtility::translate(
                'LLL:EXT:core/Resources/Private/Language/'
                . 'locallang_core.xlf:labels.placeholder.override',
            ),
            'overrideNoDefault' => LocalizationUtility::translate(
                'LLL:EXT:core/Resources/Private/Language/'
                . 'locallang_core.xlf:labels.placeholder.override_not_available',
            ),
            'cssClass' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.cssclass',
            ),
            'width' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.width',
            ),
            'height' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.height',
            ),
            'title' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.title',
            ),
            'alt' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.alt',
            ),
            'clickToEnlarge' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.clicktoenlarge',
            ),
            'enabled' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.enabled',
            ),
            'skipImageProcessing' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.skipimageprocessing',
            ),
            'imageProperties' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.imageproperties',
            ),
            'cancel' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.cancel',
            ),
            'save' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.save',
            ),
            'insertImage' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.insertimage',
            ),
            'noDefaultMetadata' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.nodefaultmetadata',
            ),
            'zoomHelp' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.zoomhelp',
            ),
            'noScaleHelp' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.noscalehelp',
            ),
            // Use local translation instead of deprecated TYPO3 core label
            // (image_zoom_formlabel deprecated in TYPO3 v14)
            'zoom' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.clicktoenlarge',
            ),
            'quality' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality',
            ),
            'qualityNone' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.none',
            ),
            'qualityStandard' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.standard',
            ),
            'qualityRetina' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.retina',
            ),
            'qualityUltra' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.ultra',
            ),
            'qualityPrint' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.print',
            ),
            'qualityLowLabel' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.low.label',
            ),
            'qualityLowTooltip' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.low.tooltip',
            ),
            'qualityStandardLabel' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.standard.label',
            ),
            'qualityStandardTooltip' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.standard.tooltip',
            ),
            'qualityRetinaLabel' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.retina.label',
            ),
            'qualityRetinaTooltip' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.retina.tooltip',
            ),
            'qualityUltraLabel' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.ultra.label',
            ),
            'qualityUltraTooltip' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.ultra.tooltip',
            ),
            'qualityPrintLabel' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.print.label',
            ),
            'qualityPrintTooltip' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.print.tooltip',
            ),
            'qualityExcessiveLabel' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.excessive.label',
            ),
            'qualityExcessiveTooltip' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.quality.excessive.tooltip',
            ),
            'clickBehavior' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.clickBehavior',
            ),
            'clickBehaviorNone' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.clickBehaviorNone',
            ),
            'clickBehaviorEnlarge' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.clickBehaviorEnlarge',
            ),
            'clickBehaviorLink' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.clickBehaviorLink',
            ),
            'linkUrl' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.linkUrl',
            ),
            'linkTarget' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.linkTarget',
            ),
            'linkTitle' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.linkTitle',
            ),
            'linkCssClass' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.linkCssClass',
            ),
            'browse' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.browse',
            ),
            'linkTargetDefault' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.linkTargetDefault',
            ),
            'linkTargetBlank' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.linkTargetBlank',
            ),
            'linkTargetTop' => LocalizationUtility::translate(
                'LLL:EXT:rte_ckeditor_image/Resources/Private/Language/'
                . 'locallang_be.xlf:labels.ckeditor.linkTargetTop',
            ),
        ];
    }

    /**
     * Get the original image.
     *
     * @param int $id The uid of the file to instantiate
     *
     * @return File
     *
     * @throws RuntimeException If file not found, deleted, or missing
     */
    protected function getImage(int $id): File
    {
        try {
            $file = $this->resourceFactory->getFileObject($id);
        } catch (Exception $exception) {
            throw new RuntimeException('File not found', 1734282000, $exception);
        }

        if ($file->isDeleted() || $file->isMissing()) {
            throw new RuntimeException('File is deleted or missing', 1734282001);
        }

        return $file;
    }

    /**
     * Verifies if the current backend user can access the given file.
     * Implements IDOR protection by checking file mount permissions.
     *
     * Uses TYPO3's built-in permission system which correctly checks:
     * - User action permissions (readFile)
     * - File extension restrictions
     * - File mount boundaries (isWithinFileMountBoundaries)
     *
     * @param File $file The file to check access for
     *
     * @return bool True if user can access the file
     */
    protected function isFileAccessibleByUser(File $file): bool
    {
        // Note: $GLOBALS['BE_USER'] is still the standard pattern in TYPO3 13 for backend context
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        // No backend user context - deny access
        if ($backendUser === null) {
            return false;
        }

        // Check if user has general access to sys_file table
        if (!$backendUser->check('tables_select', 'sys_file')) {
            return false;
        }

        // Use TYPO3's built-in permission check which handles:
        // - Admin users (automatic full access)
        // - File mount boundaries
        // - User group permissions
        // This replaces the broken getFileStorageRecords() approach
        return $file->checkActionPermission('read');
    }

    /**
     * Get the processed image.
     *
     * @param File               $file          The original image file
     * @param string[]           $params        The parameters used to process the image
     * @param array<string, int> $maxDimensions The maximum width and height
     *
     * @return ProcessedFile
     */
    protected function processImage(File $file, array $params, array $maxDimensions): ProcessedFile
    {
        $width  = min($maxDimensions['width'], (int) ($params['width'] ?? $file->getProperty('width')));
        $height = min($maxDimensions['height'], (int) ($params['height'] ?? $file->getProperty('height')));

        return $file->process(
            ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
            [
                'width'  => $width,
                'height' => $height,
            ],
        );
    }

    /**
     * Calculate display dimensions that fit within max limits while preserving aspect ratio.
     *
     * Allows small images and SVGs to scale up to TSConfig max dimensions.
     * For larger images, scales down proportionally to fit within limits.
     *
     * @param int $originalWidth  Original image width in pixels
     * @param int $originalHeight Original image height in pixels
     * @param int $maxWidth       Maximum allowed width from TSConfig
     * @param int $maxHeight      Maximum allowed height from TSConfig
     *
     * @return array<string, int> Array with 'width' and 'height' keys for suggested display dimensions
     */
    protected function calculateDisplayDimensions(
        int $originalWidth,
        int $originalHeight,
        int $maxWidth,
        int $maxHeight,
    ): array {
        // If image fits within limits, use original dimensions
        if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
            return ['width' => $originalWidth, 'height' => $originalHeight];
        }

        // Calculate scaling factors for both dimensions
        $widthScale  = $maxWidth / $originalWidth;
        $heightScale = $maxHeight / $originalHeight;

        // Use the smaller scale to ensure both dimensions fit
        $scale = min($widthScale, $heightScale);

        // Calculate new dimensions (round down to avoid exceeding limits)
        $displayWidth  = (int) floor($originalWidth * $scale);
        $displayHeight = (int) floor($originalHeight * $scale);

        return ['width' => $displayWidth, 'height' => $displayHeight];
    }

    /**
     * Get maximum image dimensions from page TSConfig.
     *
     * Reads dimension limits from TSConfig and enforces reasonable bounds
     * to prevent resource exhaustion attacks.
     *
     * @param string[] $params Request parameters including optional 'pid' and 'richtextConfigurationName'
     *
     * @return array<string, int> Array with 'width' and 'height' keys containing validated dimension limits
     */
    protected function getMaxDimensions(array $params): array
    {
        $tsConfig                  = BackendUtility::getPagesTSconfig((int) ($params['pid'] ?? 0));
        $richtextConfigurationName = $params['richtextConfigurationName'] ?? 'default';
        if ($richtextConfigurationName === '') {
            $richtextConfigurationName = 'default';
        }

        // Safe array access: fallback to empty array if TSConfig structure doesn't exist
        $rteConfig    = $tsConfig['RTE.'][$richtextConfigurationName . '.'] ?? [];
        $imageOptions = $rteConfig['buttons.']['image.']['options.']['magic.'] ?? [];

        // Type cast to ensure integers (handles string values from TSConfig)
        $maxHeight = (int) ($imageOptions['maxHeight'] ?? self::IMAGE_DEFAULT_MAX_HEIGHT);
        $maxWidth  = (int) ($imageOptions['maxWidth'] ?? self::IMAGE_DEFAULT_MAX_WIDTH);

        // Enforce reasonable bounds: 1px minimum, 10000px maximum
        // This prevents resource exhaustion (10000x10000 ≈ 400MB vs 50000x50000 ≈ 10GB)
        $maxHeight = max(self::IMAGE_MIN_DIMENSION, min(self::IMAGE_MAX_DIMENSION, $maxHeight));
        $maxWidth  = max(self::IMAGE_MIN_DIMENSION, min(self::IMAGE_MAX_DIMENSION, $maxWidth));

        return ['width' => $maxWidth, 'height' => $maxHeight];
    }
}
