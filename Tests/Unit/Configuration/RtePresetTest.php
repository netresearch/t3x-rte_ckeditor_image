<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The shipped RTE preset declares its CKEditor 5 module in the descriptor
 * form `{ module, exports }`.
 *
 * rte_ckeditor normalises a plain string to `{ module: <string>, exports:
 * ['default'] }`, but other consumers of the preset read `module` from every
 * entry without that normalisation (FriendsOfTYPO3/visual_editor's
 * TextViewHelper), and a string entry makes them fail on every rich-text field.
 */
#[CoversNothing]
final class RtePresetTest extends UnitTestCase
{
    private const PLUGIN_PRESET = __DIR__ . '/../../../Configuration/RTE/Plugin.yaml';

    private const PLUGIN_MODULE = '@netresearch/rte-ckeditor-image/Plugins/typo3image.js';

    #[Test]
    public function importModulesUseTheModuleDescriptorForm(): void
    {
        $preset = Yaml::parseFile(self::PLUGIN_PRESET);
        self::assertIsArray($preset);

        $importModules = $preset['editor']['config']['importModules'] ?? null;
        self::assertIsArray($importModules);
        self::assertNotEmpty($importModules);

        foreach ($importModules as $index => $entry) {
            self::assertIsArray($entry, sprintf('importModules[%s] must be a { module, exports } map, not a plain string', $index));
            self::assertIsString($entry['module'] ?? null, sprintf('importModules[%s] needs a "module" string', $index));
            self::assertIsArray($entry['exports'] ?? null, sprintf('importModules[%s] needs an "exports" list', $index));
            self::assertNotEmpty($entry['exports'], sprintf('importModules[%s] needs at least one export', $index));
        }
    }

    #[Test]
    public function pluginModuleIsLoadedThroughItsDefaultExport(): void
    {
        $preset = Yaml::parseFile(self::PLUGIN_PRESET);
        self::assertIsArray($preset);

        $importModules = $preset['editor']['config']['importModules'] ?? null;
        self::assertIsArray($importModules);
        self::assertContains(
            ['module' => self::PLUGIN_MODULE, 'exports' => ['default']],
            $importModules,
        );

        // The import map resolves '@netresearch/rte-ckeditor-image/' to
        // Resources/Public/JavaScript/ (Configuration/JavaScriptModules.php).
        $source = file_get_contents(__DIR__ . '/../../../Resources/Public/JavaScript/Plugins/typo3image.js');
        self::assertIsString($source);
        self::assertMatchesRegularExpression('/^export default class Typo3Image extends Plugin\b/m', $source);
    }
}
