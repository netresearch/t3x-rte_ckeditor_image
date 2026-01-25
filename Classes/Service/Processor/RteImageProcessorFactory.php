<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Processor;

use Netresearch\RteCKEditorImage\Service\Builder\ImageTagBuilder;
use Netresearch\RteCKEditorImage\Service\Environment\EnvironmentInfoInterface;
use Netresearch\RteCKEditorImage\Service\Fetcher\ExternalImageFetcher;
use Netresearch\RteCKEditorImage\Service\Parser\ImageTagParser;
use Netresearch\RteCKEditorImage\Service\Resolver\ImageFileResolver;
use Netresearch\RteCKEditorImage\Service\Security\SecurityValidatorInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Resource\DefaultUploadFolderResolver;

/**
 * Factory for creating RteImageProcessor instances.
 *
 * Reads extension configuration and creates the processor with
 * the appropriate settings.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final class RteImageProcessorFactory
{
    public function __construct(
        private readonly ImageTagParser $parser,
        private readonly ImageTagBuilder $builder,
        private readonly ImageFileResolver $fileResolver,
        private readonly ExternalImageFetcher $externalFetcher,
        private readonly EnvironmentInfoInterface $environmentInfo,
        private readonly SecurityValidatorInterface $securityValidator,
        private readonly Context $context,
        private readonly DefaultUploadFolderResolver $uploadFolderResolver,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Create a new RteImageProcessor instance.
     *
     * @return RteImageProcessorInterface The configured processor
     */
    public function create(): RteImageProcessorInterface
    {
        $fetchExternalImages = $this->getFetchExternalImagesConfig();

        return new RteImageProcessor(
            $this->parser,
            $this->builder,
            $this->fileResolver,
            $this->externalFetcher,
            $this->environmentInfo,
            $this->securityValidator,
            $this->context,
            $this->uploadFolderResolver,
            $this->logger,
            $fetchExternalImages,
        );
    }

    /**
     * Get the fetchExternalImages configuration value.
     *
     * @return bool True if external images should be fetched
     */
    private function getFetchExternalImagesConfig(): bool
    {
        try {
            return (bool) $this->extensionConfiguration->get(
                'rte_ckeditor_image',
                'fetchExternalImages',
            );
        } catch (Throwable) {
            // Default to false if configuration is not available
            return false;
        }
    }
}
