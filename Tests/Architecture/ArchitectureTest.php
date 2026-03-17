<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\BuildStep;
use PHPat\Test\PHPat;

/**
 * Architecture tests for rte_ckeditor_image extension.
 *
 * Enforces clean architecture boundaries and security patterns.
 *
 * Layer dependency rules (allowed dependencies flow downward):
 *
 *   Controller/Backend (presentation)
 *          ↓
 *      Service (application)
 *          ↓
 *   Domain/Model (core DTOs) ←──┐
 *          ↓                    │
 *   Utils (shared helpers) ─────┘ (Utils may use Domain types)
 */
final class ArchitectureTest
{
    // =========================================================================
    // IMMUTABILITY RULES - DTOs must be immutable
    // =========================================================================

    /**
     * DTOs must be readonly.
     *
     * Data Transfer Objects should be immutable value objects.
     */
    public function testDtosMustBeReadonly(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Domain\Model'))
            ->beReadonly()
            ->because('DTOs must be immutable value objects');
    }

    // =========================================================================
    // FINALITY RULES - DTOs must not be extended
    // =========================================================================

    /**
     * DTOs must be final.
     *
     * Prevents inheritance hierarchy manipulation.
     */
    public function testDtosMustBeFinal(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Domain\Model'))
            ->beFinal()
            ->because('DTOs should not be extended');
    }

    // =========================================================================
    // LAYER DEPENDENCY RULES - Enforce clean architecture
    // =========================================================================

    /**
     * Services must not depend on Controllers.
     *
     * Services are application layer, controllers are presentation.
     */
    public function testServicesDoNotDependOnControllers(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Service'))
            ->notDependOn()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Controller'))
            ->because('services should be independent of the presentation layer');
    }

    /**
     * Services must not depend on Backend classes.
     *
     * Backend preview renderers are presentation layer.
     */
    public function testServicesDoNotDependOnBackend(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Service'))
            ->notDependOn()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Backend'))
            ->because('services should be independent of backend presentation layer');
    }

    /**
     * Domain layer must not depend on infrastructure.
     *
     * Domain models should be pure and framework-independent.
     */
    public function testDomainDoesNotDependOnInfrastructure(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Domain'))
            ->notDependOn()
            ->classes(
                Selector::inNamespace('Netresearch\RteCKEditorImage\Controller'),
                Selector::inNamespace('Netresearch\RteCKEditorImage\Backend'),
                Selector::inNamespace('Netresearch\RteCKEditorImage\Database'),
                Selector::inNamespace('Netresearch\RteCKEditorImage\DataHandling'),
                Selector::inNamespace('Netresearch\RteCKEditorImage\Service'),
            )
            ->because('domain layer must be isolated from infrastructure concerns');
    }

    /**
     * Database hooks must not depend on Controllers.
     *
     * Database hooks should use services, not controllers.
     */
    public function testDatabaseDoesNotDependOnControllers(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Database'))
            ->notDependOn()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Controller'))
            ->because('database hooks should use services, not controllers');
    }

    /**
     * DataHandling must not depend on Controllers.
     *
     * Data handling hooks should use services, not controllers.
     */
    public function testDataHandlingDoesNotDependOnControllers(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\DataHandling'))
            ->notDependOn()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Controller'))
            ->because('data handling hooks should use services, not controllers');
    }

    /**
     * TCA Listeners must not depend on Controllers.
     *
     * TCA listeners should only use services.
     */
    public function testListenersDoNotDependOnControllers(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Listener'))
            ->notDependOn()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Controller'))
            ->because('TCA listeners should use services, not controllers');
    }

    /**
     * Utilities must not depend on higher layers.
     *
     * Utilities should be stateless helper functions.
     * They may use Domain types (DTOs) but not application or presentation layers.
     */
    public function testUtilitiesDoNotDependOnHigherLayers(): BuildStep
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Netresearch\RteCKEditorImage\Utils'))
            ->notDependOn()
            ->classes(
                Selector::inNamespace('Netresearch\RteCKEditorImage\Controller'),
                Selector::inNamespace('Netresearch\RteCKEditorImage\Backend'),
                Selector::inNamespace('Netresearch\RteCKEditorImage\Service'),
                Selector::inNamespace('Netresearch\RteCKEditorImage\Database'),
                Selector::inNamespace('Netresearch\RteCKEditorImage\DataHandling'),
            )
            ->because('utilities should be stateless helpers without application layer dependencies');
    }
}
