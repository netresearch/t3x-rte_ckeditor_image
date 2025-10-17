<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Database;

use function count;

use finfo;

use function is_array;
use function is_string;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function strlen;

use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\FileProcessingAspect;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Html\RteHtmlParser;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\DefaultUploadFolderResolver;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Service\MagicImageService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class for processing of the FAL soft references on img tags inserted in RTE content.
 *
 * @author  Stefan Galinski <stefan@sgalinski.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
class RteImagesDbHook
{
    use LoggerAwareTrait;

    /**
     * @var bool
     */
    protected bool $fetchExternalImages;

    /**
     * @var bool
     */
    protected bool $allowSvgImages;

    /**
     * Constructor.
     *
     * @param ExtensionConfiguration $extensionConfiguration
     * @param LogManager             $logManager
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function __construct(
        ExtensionConfiguration $extensionConfiguration,
        LogManager $logManager
    ) {
        $this->fetchExternalImages = (bool) $extensionConfiguration
            ->get('rte_ckeditor_image', 'fetchExternalImages');
        $this->allowSvgImages = (bool) $extensionConfiguration
            ->get('rte_ckeditor_image', 'allowSvgImages');

        $this->logger = $logManager->getLogger(self::class);
    }

    /**
     * Validates if the given URL is allowed for external image fetching and returns the safe IP.
     * Implements SSRF protection by checking against internal IP ranges and private networks.
     * Returns the validated IP address to prevent DNS rebinding attacks.
     *
     * @param string $url The URL to validate
     *
     * @return string|null The validated IP address or null if validation fails
     */
    private function getSafeIpForExternalFetch(string $url): ?string
    {
        $parsedUrl = parse_url($url);
        if (!is_array($parsedUrl) || !isset($parsedUrl['host'])) {
            return null;
        }

        $host = $parsedUrl['host'];

        // Resolve hostname to IP address
        $ip = gethostbyname($host);
        if ($ip === $host) {
            // DNS resolution failed or is already an IP
            $ip = $host;
        }

        // Validate IP is not in private/reserved ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            // Block private IPv4 ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
            // Block loopback (127.0.0.0/8)
            // Block link-local (169.254.0.0/16)
            if (
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ) {
                return null;
            }
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            // Block private IPv6 ranges
            if (
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ) {
                return null;
            }
        } else {
            // Invalid IP format
            return null;
        }

        // Additional check: block cloud metadata endpoints
        $blockedHosts = [
            '169.254.169.254',  // AWS/Azure/GCP metadata
            'metadata.google.internal',
            'instance-data',
        ];

        foreach ($blockedHosts as $blockedHost) {
            if (stripos($host, $blockedHost) !== false || stripos($ip, $blockedHost) !== false) {
                return null;
            }
        }

        return $ip;
    }

    /**
     * Validates file content by MIME type to prevent malicious uploads.
     *
     * @param string $fileContent The file content to validate
     *
     * @return bool True if MIME type is allowed
     */
    private function isValidImageMimeType(string $fileContent): bool
    {
        // Allowed image MIME types for external images
        $allowedMimeTypes = [
            'image/jpeg',  // Standard JPEG format
            'image/png',   // PNG format
            'image/gif',   // GIF format
            'image/webp',  // Modern WebP format
        ];

        // Conditionally allow SVG if explicitly enabled in extension configuration
        // WARNING: SVG files can contain JavaScript, event handlers, and XML entities
        // that pose XSS risks. Only enable if you trust the image sources and have
        // additional sanitization in place.
        if ($this->allowSvgImages) {
            $allowedMimeTypes[] = 'image/svg+xml';
        }

        // Use finfo to detect MIME type from content
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($fileContent);

        return in_array($mimeType, $allowedMimeTypes, true);
    }

    /**
     * This method is called to transform RTE content in the database so the Rich Text Editor
     * can deal with, e.g. links.
     *
     * @param string        $value
     * @param RteHtmlParser $rteHtmlParser
     *
     * @return string
     */
    // @codingStandardsIgnoreStart
    public function transform_rte(
        // @codingStandardsIgnoreEnd
        string $value,
        RteHtmlParser $rteHtmlParser
    ): string {
        // Split content by <img> tags and traverse the resulting array for processing:
        $imgSplit = $rteHtmlParser->splitTags('img', $value);

        if (count($imgSplit) > 1) {
            $siteUrl  = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
            $siteHost = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST');
            $sitePath = '';

            if (!is_string($siteUrl)) {
                $siteUrl = '';
            }

            if (is_string($siteHost)) {
                $sitePath = str_replace(
                    $siteHost,
                    '',
                    $siteUrl,
                );
            }

            foreach ($imgSplit as $key => $v) {
                // Image found
                if (($key % 2) === 1) {
                    // Get the attributes of the img tag
                    [$attribArray] = $rteHtmlParser->get_tag_attributes($v, true);
                    $absoluteUrl   = trim((string) $attribArray['src']);

                    // Transform the src attribute into an absolute url, if it not already
                    if (strncasecmp($absoluteUrl, 'http', 4) !== 0) {
                        // If site is in a sub path (e.g. /~user_jim/) this path needs to be
                        // removed because it will be added with $siteUrl
                        $attribArray['src'] = preg_replace(
                            '#^' . preg_quote($sitePath, '#') . '#',
                            '',
                            (string) $attribArray['src'],
                        );

                        $attribArray['src'] = $siteUrl . $attribArray['src'];
                    }

                    // Must have alt attribute
                    if (!isset($attribArray['alt'])) {
                        $attribArray['alt'] = '';
                    }

                    $imgSplit[$key] = '<img '
                        . GeneralUtility::implodeAttributes($attribArray, true, true)
                        . ' />';
                }
            }
        }

        // Return processed content:
        return implode('', $imgSplit);
    }

    /**
     * Finds width and height from attrib-array
     * If the width and height is found in the style-attribute, use that!
     *
     * @param string $styleAttribute The image style attribute
     * @param string $imageAttribute The image attribute to match in the style attribute (e.g. width, height)
     *
     * @return mixed|null
     */
    private function matchStyleAttribute(string $styleAttribute, string $imageAttribute): ?string
    {
        $regex   = '[[:space:]]*:[[:space:]]*([0-9]*)[[:space:]]*px';
        $matches = [];

        if (preg_match('/' . $imageAttribute . $regex . '/i', $styleAttribute, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extracts the given image attribute value from the image tag attributes. If a style attribute
     * exists the information is extracted from this one.
     *
     * @param string[] $attributes     The attributes of the image tag
     * @param string   $imageAttribute The image attribute to extract (e.g. width, height)
     *
     * @return mixed|null
     */
    private function extractFromAttributeValueOrStyle(array $attributes, string $imageAttribute)
    {
        $style = trim($attributes['style'] ?? '');

        if ($style !== '') {
            $value = $this->matchStyleAttribute($style, $imageAttribute);

            // Return value from style attribute
            if ($value !== null) {
                return $value;
            }
        }

        // Returns value from tag attributes
        return $attributes[$imageAttribute] ?? null;
    }

    /**
     * Returns the width of the image from the image tag attributes. If a style attribute exists
     * the information is extracted from this one.
     *
     * @param string[] $attributes The attributes of the image tag
     *
     * @return int
     */
    private function getImageWidthFromAttributes(array $attributes): int
    {
        return (int) $this->extractFromAttributeValueOrStyle($attributes, 'width');
    }

    /**
     * Returns the height of the image from the image tag attributes. If a style attribute exists
     * the information is extracted from this one.
     *
     * @param string[] $attributes The attributes of the image tag
     *
     * @return int
     */
    private function getImageHeightFromAttributes(array $attributes): int
    {
        return (int) $this->extractFromAttributeValueOrStyle($attributes, 'height');
    }

    /**
     * Process the modified text from TCA text field before its stored in the database.
     *
     * @param mixed[] $fieldArray
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        string $id,
        array &$fieldArray,
        DataHandler &$dataHandler
    ): void {
        foreach ($fieldArray as $field => $fieldValue) {
            // Ignore not existing fields in TCA definition
            if (!isset($GLOBALS['TCA'][$table]['columns'][$field])) {
                continue;
            }

            // Get TCA config for the field
            $tcaFieldConf = $this->resolveFieldConfigurationAndRespectColumnsOverrides($dataHandler, $table, $field);
            // Handle only fields of type "text"
            if (!array_key_exists('type', $tcaFieldConf)) {
                continue;
            }

            if ($tcaFieldConf['type'] !== 'text') {
                continue;
            }

            // Ignore all none RTE text fields
            if (!array_key_exists('enableRichtext', $tcaFieldConf)) {
                continue;
            }

            if ($tcaFieldConf['enableRichtext'] === false) {
                continue;
            }

            if ($fieldValue === null) {
                continue;
            }

            $fieldArray[$field] = $this->modifyRteField($fieldValue);
        }
    }

    private function modifyRteField(string $value): string
    {
        $rteHtmlParser = new HtmlParser();
        $imgSplit      = $rteHtmlParser->splitTags('img', $value);

        if (count($imgSplit) === 0) {
            return $value;
        }

        $siteUrl  = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
        $siteHost = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST');
        $sitePath = '';

        if (!is_string($siteUrl)) {
            $siteUrl = '';
        }

        if (is_string($siteHost)) {
            $sitePath = str_replace(
                $siteHost,
                '',
                $siteUrl,
            );
        }

        $resourceFactory   = GeneralUtility::makeInstance(ResourceFactory::class);
        $magicImageService = GeneralUtility::makeInstance(MagicImageService::class);

        $pageTsConfig = BackendUtility::getPagesTSconfig(0);
        $magicImageService->setMagicImageMaximumDimensions($pageTsConfig['RTE.']['default.'] ?? []);

        foreach ($imgSplit as $key => $v) {
            // Odd numbers contains the <img> tags
            if (($key % 2) === 1) {
                // Get attributes
                [$attribArray] = $rteHtmlParser->get_tag_attributes($v, true);
                // It's always an absolute URL coming from the RTE into the Database.
                $absoluteUrl = trim((string) $attribArray['src']);

                // Make path absolute if it is relative, and we have a site path which is not '/'
                if (($sitePath !== '') && str_starts_with($absoluteUrl, $sitePath)) {
                    // If site is in a subpath (e.g. /~user_jim/) this path needs to be removed
                    // because it will be added with $siteUrl
                    $absoluteUrl = substr($absoluteUrl, strlen($sitePath));
                    $absoluteUrl = $siteUrl . $absoluteUrl;
                }

                // Get image dimensions set in the image tag, if any
                $imageWidth  = $this->getImageWidthFromAttributes($attribArray);
                $imageHeight = $this->getImageHeightFromAttributes($attribArray);

                $originalImageFile = null;
                if (isset($attribArray['data-htmlarea-file-uid'])) {
                    // An original image file uid is available
                    try {
                        $originalImageFile = $resourceFactory
                            ->getFileObject((int) $attribArray['data-htmlarea-file-uid']);
                    } catch (FileDoesNotExistException $exception) {
                        if ($this->logger instanceof LoggerInterface) {
                            // Log the fact the file could not be retrieved.
                            $message = sprintf(
                                'Could not find file with uid "%s"',
                                $attribArray['data-htmlarea-file-uid'],
                            );

                            $this->logger->error($message, ['exception' => $exception]);
                        }
                    }
                }

                // If empty image dimensions but file exists, take file dimensions
                if ($originalImageFile instanceof File) {
                    if ($imageWidth === 0) {
                        $imageWidth = $originalImageFile->getProperty('width');
                    }

                    if ($imageHeight === 0) {
                        $imageHeight = $originalImageFile->getProperty('height');
                    }
                }

                if ($imageWidth > 0) {
                    $attribArray['width'] = $imageWidth;
                }

                if ($imageHeight > 0) {
                    $attribArray['height'] = $imageHeight;
                }

                // Determine application type - fail secure: require backend context
                if (!(($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface)) {
                    throw new RuntimeException('Invalid request context: ServerRequest required', 1734278400);
                }

                $applicationType = ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST']);
                if (!$applicationType->isBackend()) {
                    throw new RuntimeException('Backend context required for image processing', 1734278401);
                }

                if ($originalImageFile instanceof File) {
                    // Build public URL to image, remove trailing slash from site URL
                    $imageFileUrl = rtrim($siteUrl, '/') . $originalImageFile->getPublicUrl();

                    // Public url of local file is relative to the site url, absolute otherwise
                    if (($absoluteUrl !== $imageFileUrl) && ($absoluteUrl !== $originalImageFile->getPublicUrl())) {
                        // Magic image case: get a processed file with the requested configuration
                        $imageConfiguration = [
                            'width'  => $imageWidth,
                            'height' => $imageHeight,
                        ];


                        // ensure we do get a processed file
                        GeneralUtility::makeInstance(Context::class)
                            ->setAspect('fileProcessing', new FileProcessingAspect(false));

                        $magicImage = $magicImageService
                            ->createMagicImage($originalImageFile, $imageConfiguration);

                        $attribArray['width']  = $magicImage->getProperty('width');
                        $attribArray['height'] = $magicImage->getProperty('height');

                        $imgSrc = $magicImage->getPublicUrl();

                        // publicUrl like 'https://www.domain.xy/typo3/image/process?token=...'?
                        // -> generate img source from storage basepath and identifier instead
                        if ($imgSrc !== null && str_contains($imgSrc, 'process?token=')) {
                            $imgSrc = $originalImageFile->getStorage()->getPublicUrl($magicImage);
                        }

                        $attribArray['src'] = $imgSrc;
                    }
                } elseif (
                    $this->fetchExternalImages
                    && !str_starts_with($absoluteUrl, $siteUrl)
                ) {
                    // External image from another URL: in that case, fetch image, unless
                    // the feature is disabled.
                    // Note: Backend context is already validated above (lines 441-444).
                    //
                    // SECURITY: Validate URL and get safe IP to prevent DNS rebinding attacks
                    $safeIp = $this->getSafeIpForExternalFetch($absoluteUrl);
                    if ($safeIp === null) {
                        if ($this->logger instanceof LoggerInterface) {
                            $this->logger->warning(
                                'Blocked external image fetch: URL failed SSRF validation',
                                ['url' => $absoluteUrl],
                            );
                        }

                        continue;
                    }

                    // Fetch the external image using validated IP to prevent DNS rebinding
                    $externalFile = null;
                    try {
                        /** @var RequestFactory $requestFactory */
                        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);

                        $parsedUrl = parse_url($absoluteUrl);
                        $host      = $parsedUrl['host'];
                        $port      = $parsedUrl['port'] ?? (($parsedUrl['scheme'] ?? 'http') === 'https' ? 443 : 80);

                        $response = $requestFactory->request($absoluteUrl, 'GET', [
                            'timeout'         => 5,
                            'allow_redirects' => false, // Prevent redirect to unsafe locations
                            'curl'            => [
                                // Force cURL to use validated IP for this hostname:port
                                CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $host, $port, $safeIp)],
                            ],
                        ]);

                        if ($response->getStatusCode() === 200) {
                            $externalFile = $response->getBody()->getContents();
                        }
                    } catch (Throwable $exception) {
                        if ($this->logger instanceof LoggerInterface) {
                            $this->logger->error(
                                'Failed to fetch external image',
                                ['exception' => $exception],
                            );
                        }

                        // do nothing, further image processing will be skipped
                    }

                    if ($externalFile !== null && $externalFile !== false) {
                        // SECURITY: Validate MIME type from content before processing
                        if (!$this->isValidImageMimeType($externalFile)) {
                            if ($this->logger instanceof LoggerInterface) {
                                $this->logger->warning(
                                    'Blocked external image: Invalid MIME type detected',
                                    ['url' => $absoluteUrl],
                                );
                            }

                            continue;
                        }

                        $pU        = parse_url($absoluteUrl);
                        $path      = is_array($pU) ? ($pU['path'] ?? '') : '';
                        $pI        = pathinfo($path);
                        $extension = strtolower($pI['extension'] ?? '');

                        // Build allowed extensions list based on configuration
                        $allowedExtensions = ['jpg', 'jpeg', 'gif', 'png', 'webp'];
                        if ($this->allowSvgImages) {
                            $allowedExtensions[] = 'svg';
                        }

                        // Validate file extension (defense in depth after MIME check)
                        if (in_array($extension, $allowedExtensions, true)) {
                            $fileName = substr(md5($absoluteUrl), 0, 10) . '.' . ($pI['extension'] ?? '');
                            // We insert this image into the user default upload folder
                            $uploadFolderResolver = GeneralUtility::makeInstance(DefaultUploadFolderResolver::class);
                            $folder               = $uploadFolderResolver->resolve($GLOBALS['BE_USER']);
                            $fileObject           = $folder->createFile($fileName)->setContents($externalFile);
                            $imageConfiguration   = [
                                'width'  => $attribArray['width'],
                                'height' => $attribArray['height'],
                            ];

                            $magicImage = $magicImageService
                                ->createMagicImage($fileObject, $imageConfiguration);

                            $attribArray['width']                  = $magicImage->getProperty('width');
                            $attribArray['height']                 = $magicImage->getProperty('height');
                            $attribArray['data-htmlarea-file-uid'] = $fileObject->getUid();
                            $attribArray['src']                    = $magicImage->getPublicUrl();
                        }
                    }
                } elseif (str_starts_with($absoluteUrl, $siteUrl)) {
                    // Finally, check image as local file (siteURL equals the one of the image)
                    // Image has no data-htmlarea-file-uid attribute
                    // Relative path, rawurldecoded for special characters.
                    $path = rawurldecode(substr($absoluteUrl, strlen($siteUrl)));

                    // SECURITY: Sanitize path to prevent directory traversal
                    $path = str_replace(['../', '..\\', "\0"], '', $path);

                    // Absolute filepath, locked to relative path of this project
                    $filepath = GeneralUtility::getFileAbsFileName($path);

                    // SECURITY: Verify the resolved path is within allowed directory
                    if ($filepath !== '') {
                        $realpath   = realpath($filepath);
                        $publicPath = Environment::getPublicPath();

                        // Ensure realpath succeeded and is within public path
                        if ($realpath === false || !str_starts_with($realpath, $publicPath)) {
                            if ($this->logger instanceof LoggerInterface) {
                                $this->logger->warning(
                                    'Blocked file access: Path traversal attempt detected',
                                    ['path' => $path],
                                );
                            }

                            continue;
                        }
                    }

                    // Check file existence (in relative directory to this installation!)
                    if (($filepath !== '') && is_file($filepath)) {
                        // Let's try to find a file uid for this image
                        try {
                            $fileOrFolderObject = $resourceFactory->retrieveFileOrFolderObject($path);
                            if ($fileOrFolderObject instanceof FileInterface) {
                                $fileIdentifier = $fileOrFolderObject->getIdentifier();
                                $fileObject     = $fileOrFolderObject->getStorage()->getFile($fileIdentifier);
                                if ($fileObject instanceof AbstractFile) {
                                    $fileUid = $fileObject->getUid();
                                    // if the retrieved file is a processed file, get the original file...
                                    if ($fileObject->hasProperty('original')) {
                                        $fileUid = $fileObject->getProperty('original');
                                    }

                                    $attribArray['data-htmlarea-file-uid'] = $fileUid;
                                }
                            }
                        } catch (ResourceDoesNotExistException) {
                            // Nothing to be done if file/folder not found
                        }
                    }
                }

                if (!$attribArray) {
                    // some error occurred, leave the img tag as is
                    continue;
                }

                // Remove width and height from style attribute
                $attribArray['style'] = preg_replace(
                    '/(?:^|[^-])(\\s*(?:width|height)\\s*:[^;]*(?:$|;))/si',
                    '',
                    $attribArray['style'] ?? '',
                );
                // Must have alt attribute
                if (!isset($attribArray['alt'])) {
                    $attribArray['alt'] = '';
                }

                // Convert absolute to relative url
                if (str_starts_with((string) $attribArray['src'], $siteUrl)) {
                    $attribArray['src'] = substr((string) $attribArray['src'], strlen($siteUrl));
                }

                $imgSplit[$key] = '<img ' . GeneralUtility::implodeAttributes($attribArray, true, true) . ' />';
            }
        }

        return implode('', $imgSplit);
    }

    /**
     * @return mixed[]
     */
    private function resolveFieldConfigurationAndRespectColumnsOverrides(
        DataHandler $dataHandler,
        string $table,
        string $field
    ): array {
        $tcaFieldConf = $GLOBALS['TCA'][$table]['columns'][$field]['config'];
        $recordType   = BackendUtility::getTCAtypeValue($table, $dataHandler->checkValue_currentRecord);

        $columnsOverridesConfigOfField
            = $GLOBALS['TCA'][$table]['types'][$recordType]['columnsOverrides'][$field]['config'] ?? null;

        if ($columnsOverridesConfigOfField !== null) {
            ArrayUtility::mergeRecursiveWithOverrule($tcaFieldConf, $columnsOverridesConfigOfField);
        }

        return $tcaFieldConf;
    }
}
