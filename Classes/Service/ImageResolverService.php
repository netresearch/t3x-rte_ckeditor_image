<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service;

use Netresearch\RteCKEditorImage\Domain\Model\ImageRenderingDto;
use Netresearch\RteCKEditorImage\Domain\Model\LinkDto;
use Netresearch\RteCKEditorImage\Utils\ProcessedFilesHandler;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;

/**
 * Business logic and security validation for image rendering.
 *
 * RESPONSIBILITY: Transform raw attributes into validated, type-safe DTOs.
 * ALL security checks MUST happen here before DTO construction.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
class ImageResolverService
{
    /**
     * Quality setting constants for data-quality attribute.
     */
    private const QUALITY_NONE = 'none';

    private const QUALITY_LOW = 'low';

    private const QUALITY_STANDARD = 'standard';

    private const QUALITY_RETINA = 'retina';

    private const QUALITY_ULTRA = 'ultra';

    private const QUALITY_PRINT = 'print';

    /**
     * Quality multipliers mapping.
     *
     * @var array<string, float>
     */
    private const QUALITY_MULTIPLIERS = [
        self::QUALITY_NONE     => 1.0,
        self::QUALITY_LOW      => 0.9,
        self::QUALITY_STANDARD => 1.0,
        self::QUALITY_RETINA   => 2.0,
        self::QUALITY_ULTRA    => 3.0,
        self::QUALITY_PRINT    => 6.0,
    ];

    /**
     * Dangerous URL protocols that must be blocked.
     *
     * @var array<int, string>
     */
    private const DANGEROUS_PROTOCOLS = [
        'javascript:',
        'data:text/html',
        'vbscript:',
        'file:',
    ];

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ResourceFactory $resourceFactory,
        private readonly ProcessedFilesHandler $processedFilesHandler,
        LogManager $logManager,
    ) {
        $this->logger = $logManager->getLogger(self::class);
    }

    /**
     * Resolve image attributes to validated DTO.
     *
     * @param array<string,string>      $attributes     Raw attributes from parser
     * @param array<string,mixed>       $conf           TypoScript configuration
     * @param ServerRequestInterface    $request        Current request
     * @param array<string,string>|null $linkAttributes Optional link attributes for linked images
     *
     * @return ImageRenderingDto|null Validated DTO or null if validation fails
     */
    public function resolve(
        array $attributes,
        array $conf,
        ServerRequestInterface $request,
        ?array $linkAttributes = null,
    ): ?ImageRenderingDto {
        // Extract file UID from data-htmlarea-file-uid attribute
        $fileUid = (int) ($attributes['data-htmlarea-file-uid'] ?? 0);

        if ($fileUid === 0) {
            // No file UID - might be external image, skip processing
            return $this->createDtoFromExternalImage($attributes, $linkAttributes);
        }

        try {
            $systemImage = $this->resourceFactory->getFileObject($fileUid);

            // CRITICAL: File visibility validation (prevents privilege escalation)
            if (!$this->validateFileVisibility($systemImage, $fileUid)) {
                throw new FileDoesNotExistException();
            }

            // Determine noScale with proper priority order
            $noScale = $this->determineNoScale($attributes, $conf);

            // Read maxFileSizeForAuto configuration from TypoScript
            $maxFileSizeForAuto = $this->getMaxFileSizeForAuto($conf);

            // Get display dimensions from HTML attributes
            $displayWidth  = $this->parseIntAttribute($attributes['width'] ?? '', $systemImage->getProperty('width'));
            $displayHeight = $this->parseIntAttribute($attributes['height'] ?? '', $systemImage->getProperty('height'));

            // Get quality multiplier from data-quality attribute
            $qualityMultiplier = $this->getQualityMultiplier($attributes['data-quality'] ?? '');

            // Calculate processing dimensions
            $processingWidth  = (int) round($displayWidth * $qualityMultiplier);
            $processingHeight = (int) round($displayHeight * $qualityMultiplier);

            // Cap processing dimensions at original size (never upscale)
            $originalWidth    = $this->parseIntAttribute('', $systemImage->getProperty('width'));
            $originalHeight   = $this->parseIntAttribute('', $systemImage->getProperty('height'));
            $processingWidth  = min($processingWidth, $originalWidth);
            $processingHeight = min($processingHeight, $originalHeight);

            // Prepare image configuration for processing
            $imageConfiguration = [
                'width'  => $processingWidth,
                'height' => $processingHeight,
            ];

            // Determine if we should skip processing
            if ($this->shouldSkipProcessing($systemImage, $imageConfiguration, $noScale, $maxFileSizeForAuto)) {
                // Use original file
                $src    = $systemImage->getPublicUrl();
                $width  = $displayWidth !== 0 ? $displayWidth : $originalWidth;
                $height = $displayHeight !== 0 ? $displayHeight : $originalHeight;
            } else {
                // Process image
                $processedFile = $this->processedFilesHandler->createProcessedFile($systemImage, $imageConfiguration);
                $src           = $processedFile->getPublicUrl();
                $width         = $displayWidth;
                $height        = $displayHeight;
            }

            if ($src === null) {
                throw new FileDoesNotExistException();
            }

            // Get lazy loading configuration
            $lazyLoading = $this->getLazyLoadingConfiguration($request);

            // Build HTML attributes array
            $htmlAttributes = $this->buildHtmlAttributes($attributes, $lazyLoading);

            // CRITICAL: Caption XSS prevention
            $caption = $this->sanitizeCaption($attributes['data-caption'] ?? '');

            // Build link DTO if link attributes provided OR if popup attributes are present
            $link = null;

            if ($linkAttributes !== null) {
                $link = $this->buildLinkDto($linkAttributes, $attributes, $systemImage, $conf, $request);
            } elseif ($this->isPopupAttributeSet($attributes)) {
                // Auto-generate popup link for standalone images with zoom attributes
                $link = $this->buildPopupLinkDto($systemImage, $attributes, $conf, $request);
            }

            return new ImageRenderingDto(
                src: $src,
                width: $width,
                height: $height,
                alt: $this->getAttributeValue('alt', $attributes, $systemImage),
                title: $this->getAttributeValue('title', $attributes, $systemImage),
                htmlAttributes: $htmlAttributes,
                caption: $caption !== '' ? $caption : null,
                link: $link,
                isMagicImage: true,
            );
        } catch (FileDoesNotExistException $exception) {
            $this->logger->log(
                PsrLogLevel::ERROR,
                'Unable to find requested file',
                ['fileUid' => $fileUid, 'exception' => $exception],
            );

            return null;
        }
    }

    /**
     * Validate file visibility to prevent privilege escalation.
     *
     * SECURITY: Non-public files must never be rendered in frontend.
     *
     * @param File $file    File to validate
     * @param int  $fileUid File UID for logging
     *
     * @return bool True if file is safe to render
     */
    private function validateFileVisibility(File $file, int $fileUid): bool
    {
        if (!$file->getStorage()->isPublic()) {
            $this->logger->log(
                PsrLogLevel::WARNING,
                'Blocked rendering of non-public file in frontend context',
                ['fileUid' => $fileUid, 'storage' => $file->getStorage()->getUid()],
            );

            return false;
        }

        return true;
    }

    /**
     * Determine noScale setting with proper priority order.
     *
     * Priority 1: data-quality="none" (quality dropdown "No Scaling" option)
     * Priority 2: data-noscale attribute (backward compatibility)
     * Priority 3: TypoScript site-wide default
     *
     * @param array<string,string> $attributes Image attributes
     * @param array<string,mixed>  $conf       TypoScript configuration
     *
     * @return bool Whether to skip image processing
     */
    private function determineNoScale(array $attributes, array $conf): bool
    {
        // Priority 1: data-quality="none"
        if (($attributes['data-quality'] ?? '') === self::QUALITY_NONE) {
            return true;
        }

        // Priority 2: data-noscale attribute
        if (isset($attributes['data-noscale'])) {
            $noScaleValue = $attributes['data-noscale'];

            return !in_array($noScaleValue, ['false', '0', false], true);
        }

        // Priority 3: TypoScript site-wide default
        return (bool) ($conf['noScale'] ?? false);
    }

    /**
     * Get maxFileSizeForAuto configuration from TypoScript.
     *
     * @param array<string,mixed> $conf TypoScript configuration
     *
     * @return int Maximum file size for auto-optimization (bytes), 0 = no limit
     */
    private function getMaxFileSizeForAuto(array $conf): int
    {
        if (!isset($conf['noScale.']) || !is_array($conf['noScale.'])) {
            return 0;
        }

        $value = $conf['noScale.']['maxFileSizeForAuto'] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Get quality multiplier from data-quality attribute.
     *
     * @param string $quality Quality setting from data-quality attribute
     *
     * @return float Quality multiplier for image processing
     */
    private function getQualityMultiplier(string $quality): float
    {
        // Empty string defaults to standard quality
        if ($quality === '') {
            return self::QUALITY_MULTIPLIERS[self::QUALITY_STANDARD];
        }

        if (isset(self::QUALITY_MULTIPLIERS[$quality])) {
            return self::QUALITY_MULTIPLIERS[$quality];
        }

        $this->logger->log(
            PsrLogLevel::WARNING,
            'Invalid data-quality value received, defaulting to standard (1.0x)',
            ['qualityValue' => $quality],
        );

        return self::QUALITY_MULTIPLIERS[self::QUALITY_STANDARD];
    }

    /**
     * Determine if image processing should be skipped.
     *
     * @param File              $file               File object
     * @param array<string,int> $imageConfiguration Processing configuration
     * @param bool              $noScale            Whether noScale is enabled
     * @param int               $maxFileSizeForAuto Maximum file size for auto-optimization
     *
     * @return bool True if processing should be skipped
     */
    private function shouldSkipProcessing(
        File $file,
        array $imageConfiguration,
        bool $noScale,
        int $maxFileSizeForAuto,
    ): bool {
        // Always skip SVG files (vector graphics)
        if (strtolower($file->getExtension()) === 'svg') {
            return true;
        }

        // Skip if noScale explicitly enabled
        if ($noScale) {
            return true;
        }

        // Check if requested dimensions match original
        $widthProperty  = $file->getProperty('width');
        $heightProperty = $file->getProperty('height');
        $originalWidth  = is_numeric($widthProperty) ? (int) $widthProperty : 0;
        $originalHeight = is_numeric($heightProperty) ? (int) $heightProperty : 0;

        if ($imageConfiguration['width'] === $originalWidth
            && $imageConfiguration['height'] === $originalHeight
        ) {
            // Dimensions match - check file size threshold
            if ($maxFileSizeForAuto > 0 && $file->getSize() > $maxFileSizeForAuto) {
                return false; // File too large, force processing
            }

            return true; // Dimensions match and file size acceptable
        }

        return false; // Processing needed
    }

    /**
     * Get lazy loading configuration from request.
     *
     * @param ServerRequestInterface $request Current request
     *
     * @return string|null Lazy loading attribute value or null
     */
    private function getLazyLoadingConfiguration(ServerRequestInterface $request): ?string
    {
        $frontendTyposcript = $request->getAttribute('frontend.typoscript');

        if (!$frontendTyposcript instanceof FrontendTypoScript) {
            return null;
        }

        $setupArray  = $frontendTyposcript->getSetupArray();
        $lazyLoading = $this->getNestedTypoScriptValue(
            $setupArray,
            ['lib.', 'contentElement.', 'settings.', 'media.', 'lazyLoading'],
        );

        return is_string($lazyLoading) && $lazyLoading !== '' ? $lazyLoading : null;
    }

    /**
     * Build HTML attributes array from raw attributes.
     *
     * @param array<string,string> $attributes  Raw attributes
     * @param string|null          $lazyLoading Lazy loading setting
     *
     * @return array<string,mixed> HTML attributes
     */
    private function buildHtmlAttributes(array $attributes, ?string $lazyLoading): array
    {
        $htmlAttributes = [];

        // Copy allowed attributes
        // NOTE: 'style' attribute is intentionally excluded to prevent CSS injection attacks
        $allowedAttributes = ['class', 'id'];

        foreach ($allowedAttributes as $attr) {
            if (isset($attributes[$attr]) && $attributes[$attr] !== '') {
                $htmlAttributes[$attr] = $attributes[$attr];
            }
        }

        // Add lazy loading if configured
        if ($lazyLoading !== null) {
            $htmlAttributes['loading'] = $lazyLoading;
        }

        return $htmlAttributes;
    }

    /**
     * Sanitize caption to prevent XSS attacks.
     *
     * SECURITY: Caption text must be sanitized with htmlspecialchars.
     *
     * @param string $caption Raw caption text
     *
     * @return string Sanitized caption text
     */
    private function sanitizeCaption(string $caption): string
    {
        $caption = trim($caption);

        if ($caption === '') {
            return '';
        }

        return htmlspecialchars($caption, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Validate URL to prevent JavaScript injection attacks.
     *
     * SECURITY: Block dangerous protocols like javascript:, vbscript:, data:text/html
     *
     * @param string $url URL to validate
     *
     * @return bool True if URL is safe, false if potentially malicious
     */
    private function validateLinkUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $lowercaseUrl = strtolower(trim($url));

        foreach (self::DANGEROUS_PROTOCOLS as $protocol) {
            if (str_starts_with($lowercaseUrl, $protocol)) {
                $this->logger->log(
                    PsrLogLevel::WARNING,
                    'Blocked potentially malicious URL with dangerous protocol',
                    ['url' => substr($url, 0, 50), 'protocol' => $protocol],
                );

                return false;
            }
        }

        return true;
    }

    /**
     * Check if popup/lightbox attributes are set on the image.
     *
     * @param array<string,string> $attributes Image attributes
     *
     * @return bool True if popup attributes are present
     */
    private function isPopupAttributeSet(array $attributes): bool
    {
        return isset($attributes['data-htmlarea-zoom'])
            || isset($attributes['data-htmlarea-clickenlarge']);
    }

    /**
     * Get attribute value with fallback to file property.
     *
     * @param string               $attribute  Attribute name
     * @param array<string,string> $attributes Attributes array
     * @param File                 $file       File object
     *
     * @return string|null Attribute value
     */
    private function getAttributeValue(string $attribute, array $attributes, File $file): ?string
    {
        // Guard against empty attribute name
        if ($attribute === '') {
            return null;
        }

        // Check for override attribute first
        $overrideKey = 'data-' . $attribute . '-override';

        if (isset($attributes[$overrideKey]) && $attributes[$overrideKey] !== '') {
            return $attributes[$overrideKey];
        }

        // Check for regular attribute
        if (isset($attributes[$attribute]) && $attributes[$attribute] !== '') {
            return $attributes[$attribute];
        }

        // Fallback to file property
        $property = $file->getProperty($attribute);

        return is_string($property) && $property !== '' ? $property : null;
    }

    /**
     * Parse integer attribute with fallback.
     *
     * @param string     $value    Attribute value
     * @param mixed|null $fallback Fallback value from file property
     *
     * @return int Parsed integer value
     */
    private function parseIntAttribute(string $value, mixed $fallback = null): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if ($fallback !== null && is_numeric($fallback)) {
            return (int) $fallback;
        }

        return 0;
    }

    /**
     * Build link DTO from link attributes.
     *
     * @param array<string,string>   $linkAttributes  Link attributes
     * @param array<string,string>   $imageAttributes Image attributes
     * @param File                   $systemImage     System image file
     * @param array<string,mixed>    $conf            TypoScript configuration
     * @param ServerRequestInterface $request         Current request
     *
     * @return LinkDto|null Link DTO or null
     */
    private function buildLinkDto(
        array $linkAttributes,
        array $imageAttributes,
        File $systemImage,
        array $conf,
        ServerRequestInterface $request,
    ): ?LinkDto {
        $url = $linkAttributes['href'] ?? '';

        // SECURITY: Validate URL to prevent JavaScript injection
        if (!$this->validateLinkUrl($url)) {
            return null;
        }

        // Check if this is a popup/lightbox link
        $isPopup = $this->isPopupAttributeSet($imageAttributes);

        $jsConfig = null;

        if ($isPopup) {
            $jsConfig = $this->getPopupConfiguration($request);
        }

        return new LinkDto(
            url: $url,
            target: $linkAttributes['target'] ?? null,
            class: $linkAttributes['class'] ?? null,
            isPopup: $isPopup,
            jsConfig: $jsConfig,
        );
    }

    /**
     * Build popup link DTO for standalone images with zoom attributes.
     *
     * Auto-generates a popup link pointing to the full-size original image
     * when zoom/clickenlarge attributes are present but no explicit link.
     *
     * @param File                   $systemImage System image file
     * @param array<string,string>   $attributes  Image attributes
     * @param array<string,mixed>    $conf        TypoScript configuration
     * @param ServerRequestInterface $request     Current request
     *
     * @return LinkDto|null Popup link DTO or null
     */
    private function buildPopupLinkDto(
        File $systemImage,
        array $attributes,
        array $conf,
        ServerRequestInterface $request,
    ): ?LinkDto {
        // Get the full-size original image URL for popup
        $url = $systemImage->getPublicUrl();

        if ($url === null || $url === '') {
            return null;
        }

        // Get popup configuration from TypoScript
        $jsConfig = $this->getPopupConfiguration($request);

        return new LinkDto(
            url: $url,
            target: '_blank', // Popup opens in new window
            class: 'popup-link', // Default class for popup links
            isPopup: true,
            jsConfig: $jsConfig,
        );
    }

    /**
     * Get popup configuration from TypoScript.
     *
     * @param ServerRequestInterface $request Current request
     *
     * @return array<string,mixed>|null Popup configuration
     */
    private function getPopupConfiguration(ServerRequestInterface $request): ?array
    {
        $frontendTyposcript = $request->getAttribute('frontend.typoscript');

        if (!$frontendTyposcript instanceof FrontendTypoScript) {
            return null;
        }

        $setupArray  = $frontendTyposcript->getSetupArray();
        $popupConfig = $this->getNestedTypoScriptValue(
            $setupArray,
            ['lib.', 'contentElement.', 'settings.', 'media.', 'popup.'],
        );

        if (!is_array($popupConfig)) {
            return null;
        }

        // TypoScript arrays always have string keys
        /** @var array<string, mixed> $popupConfig */
        return $popupConfig;
    }

    /**
     * Create DTO from external image (no file UID).
     *
     * @param array<string,string>      $attributes     Image attributes
     * @param array<string,string>|null $linkAttributes Optional link attributes
     *
     * @return ImageRenderingDto|null DTO or null if src is missing
     */
    private function createDtoFromExternalImage(
        array $attributes,
        ?array $linkAttributes = null,
    ): ?ImageRenderingDto {
        $src = $attributes['src'] ?? '';

        if ($src === '') {
            return null;
        }

        // Add leading slash if only a path is given
        if (strncasecmp($src, 'http', 4) !== 0
            && !str_starts_with($src, '/')
            && !str_starts_with($src, 'data:image')
        ) {
            $src = '/' . $src;
        }

        $width  = $this->parseIntAttribute($attributes['width'] ?? '');
        $height = $this->parseIntAttribute($attributes['height'] ?? '');

        // NOTE: 'style' attribute is intentionally excluded to prevent CSS injection attacks
        $htmlAttributes = [
            'class' => $attributes['class'] ?? '',
            'id'    => $attributes['id'] ?? '',
        ];

        $caption = $this->sanitizeCaption($attributes['data-caption'] ?? '');

        // Check if this is a popup/lightbox link (based on image attributes)
        $isPopup = $this->isPopupAttributeSet($attributes);

        // Build link DTO with URL validation
        $link = null;

        if ($linkAttributes !== null && isset($linkAttributes['href'])) {
            $linkUrl = $linkAttributes['href'];

            // SECURITY: Validate URL to prevent JavaScript injection
            if ($this->validateLinkUrl($linkUrl)) {
                $link = new LinkDto(
                    url: $linkUrl,
                    target: $linkAttributes['target'] ?? null,
                    class: $linkAttributes['class'] ?? null,
                    isPopup: $isPopup,
                    jsConfig: null, // External images don't get popup config
                );
            }
        }

        return new ImageRenderingDto(
            src: $src,
            width: $width,
            height: $height,
            alt: $attributes['alt'] ?? null,
            title: $attributes['title'] ?? null,
            htmlAttributes: $htmlAttributes,
            caption: $caption !== '' ? $caption : null,
            link: $link,
            isMagicImage: false,
        );
    }

    /**
     * Safely get nested TypoScript value with type checking.
     *
     * @param array<mixed>      $array Array to traverse
     * @param array<int,string> $keys  Keys to traverse in order
     *
     * @return mixed Value at path or null if not found
     */
    private function getNestedTypoScriptValue(array $array, array $keys): mixed
    {
        $current = $array;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }

            $current = $current[$key];
        }

        return $current;
    }
}
