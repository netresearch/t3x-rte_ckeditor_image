<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Controller;

use Netresearch\RteCKEditorImage\Controller\SelectImageController;
use ReflectionClass;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for SelectImageController::isFileAccessibleByUser().
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/749
 */
class SelectImageControllerTest extends UnitTestCase
{
    private ?SelectImageController $subject = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->getMockBuilder(SelectImageController::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    /**
     * Call protected method using reflection.
     */
    private function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass(get_class($object));
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * @test
     */
    public function isFileAccessibleByUserDeniesAccessWithoutBackendUser(): void
    {
        unset($GLOBALS['BE_USER']);

        $file = $this->createMock(File::class);

        $result = $this->invokeMethod($this->subject, 'isFileAccessibleByUser', [$file]);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function isFileAccessibleByUserDeniesAccessWithoutTableSelectPermission(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')
            ->with('tables_select', 'sys_file')
            ->willReturn(false);

        $GLOBALS['BE_USER'] = $backendUser;

        $file = $this->createMock(File::class);

        $result = $this->invokeMethod($this->subject, 'isFileAccessibleByUser', [$file]);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function isFileAccessibleByUserGrantsAccessForNonAdminWithReadPermission(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')
            ->with('tables_select', 'sys_file')
            ->willReturn(true);
        $backendUser->method('isAdmin')
            ->willReturn(false);

        $GLOBALS['BE_USER'] = $backendUser;

        $file = $this->createMock(File::class);
        $file->method('checkActionPermission')
            ->with('read')
            ->willReturn(true);

        $result = $this->invokeMethod($this->subject, 'isFileAccessibleByUser', [$file]);

        self::assertTrue($result, 'Non-admin user with read permission should have access');
    }

    /**
     * @test
     */
    public function isFileAccessibleByUserDeniesAccessForNonAdminWithoutReadPermission(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')
            ->with('tables_select', 'sys_file')
            ->willReturn(true);
        $backendUser->method('isAdmin')
            ->willReturn(false);

        $GLOBALS['BE_USER'] = $backendUser;

        $file = $this->createMock(File::class);
        $file->method('checkActionPermission')
            ->with('read')
            ->willReturn(false);

        $result = $this->invokeMethod($this->subject, 'isFileAccessibleByUser', [$file]);

        self::assertFalse($result, 'Non-admin user without read permission should be denied');
    }

    /**
     * @test
     */
    public function isFileAccessibleByUserGrantsAccessForAdmin(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')
            ->with('tables_select', 'sys_file')
            ->willReturn(true);
        $backendUser->method('isAdmin')
            ->willReturn(true);

        $GLOBALS['BE_USER'] = $backendUser;

        $file = $this->createMock(File::class);
        $file->method('checkActionPermission')
            ->with('read')
            ->willReturn(true);

        $result = $this->invokeMethod($this->subject, 'isFileAccessibleByUser', [$file]);

        self::assertTrue($result, 'Admin user should always have access');
    }
}
