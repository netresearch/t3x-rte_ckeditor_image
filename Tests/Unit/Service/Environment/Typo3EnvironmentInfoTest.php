<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service\Environment;

use Netresearch\RteCKEditorImage\Service\Environment\Typo3EnvironmentInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for Typo3EnvironmentInfo.
 */
#[CoversClass(Typo3EnvironmentInfo::class)]
final class Typo3EnvironmentInfoTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private Typo3EnvironmentInfo $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new Typo3EnvironmentInfo();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST'], $GLOBALS['BE_USER']);
        parent::tearDown();
    }

    #[Test]
    public function isBackendRequestReturnsFalseWhenNoRequestExists(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        $result = $this->subject->isBackendRequest();

        self::assertFalse($result);
    }

    #[Test]
    public function isBackendRequestReturnsFalseWhenRequestIsNotServerRequestInterface(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = new stdClass();

        $result = $this->subject->isBackendRequest();

        self::assertFalse($result);
    }

    #[Test]
    public function getBackendUserReturnsNullWhenNoBeUserExists(): void
    {
        unset($GLOBALS['BE_USER']);

        $result = $this->subject->getBackendUser();

        self::assertNull($result);
    }

    #[Test]
    public function getBackendUserReturnsNullWhenBeUserIsNotBackendUserAuthentication(): void
    {
        $GLOBALS['BE_USER'] = new stdClass();

        $result = $this->subject->getBackendUser();

        self::assertNull($result);
    }

    #[Test]
    public function getBackendUserReturnsBackendUserAuthenticationWhenValid(): void
    {
        $beUserMock         = $this->createMock(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $beUserMock;

        $result = $this->subject->getBackendUser();

        self::assertSame($beUserMock, $result);
    }

    #[Test]
    public function getPublicPathReturnsString(): void
    {
        // Environment::getPublicPath() needs TYPO3 bootstrap, skip in unit tests
        // This test verifies the method exists and can be called
        self::markTestSkipped('TYPO3 Environment requires bootstrap - tested in functional tests');
    }

    #[Test]
    public function getSiteUrlReturnsString(): void
    {
        // GeneralUtility::getIndpEnv() requires TYPO3 Environment bootstrap
        // which is only available in functional tests
        self::markTestSkipped('TYPO3 Environment requires bootstrap - tested in functional tests');
    }

    #[Test]
    public function getRequestHostReturnsString(): void
    {
        // GeneralUtility::getIndpEnv() requires TYPO3 Environment bootstrap
        // which is only available in functional tests
        self::markTestSkipped('TYPO3 Environment requires bootstrap - tested in functional tests');
    }
}
