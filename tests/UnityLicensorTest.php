<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use DOMDocument;
use Slothsoft\Core\DOMHelper;

/**
 * UnityEditorTest
 *
 * @see UnityLicensor
 */
class UnityLicensorTest extends TestCase {

    private const ENV_FILE = '.env.local';

    private function tryPrepareEnvironment(string ...$variables): bool {
        if (is_file(self::ENV_FILE)) {
            Dotenv::createImmutable(getcwd(), self::ENV_FILE)->load();

            foreach ($variables as $variable) {
                if (isset($_ENV[$variable])) {
                    putenv($variable . '=' . $_ENV[$variable]);
                }
            }
        }

        $missing = [];

        foreach ($variables as $variable) {
            if (! getenv($variable)) {
                $missing[] = $variable;
            }
        }

        if ($missing) {
            $this->markTestSkipped(sprintf('Missing environment variables [%s]', implode(', ', $missing)));
            return false;
        } else {
            return true;
        }
    }

    private const EDITOR_VERSION = '2021.2.7f1';

    private const EDITOR_CHANGESET = '6bd9e232123f';

    private function initEditor(): ?UnityEditor {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return null;
        }

        $hub->registerChangeset(self::EDITOR_VERSION, self::EDITOR_CHANGESET);
        $editor = $hub->getEditorByVersion(self::EDITOR_VERSION);

        if (! $editor->isInstalled() and ! $editor->install()) {
            return null;
        }

        return $editor;
    }

    public function testClassExists(): void {
        $this->assertTrue(class_exists(UnityLicensor::class), "Failed to load class 'Slothsoft\Unity\UnityLicensor'!");
    }

    /**
     *
     * @runInSeparateProcess
     */
    public function testErrorWithoutUser() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=test');
        $this->expectExceptionMessage('UnityLicensor requires the environment variable "' . UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '" to be set.');
        $sut = new UnityLicensor();
        $this->assertFalse($sut->hasCredentials);
    }

    /**
     *
     * @runInSeparateProcess
     */
    public function testErrorWithoutPassword() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=test');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=');
        $this->expectExceptionMessage('UnityLicensor requires the environment variable "' . UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '" to be set.');
        $sut = new UnityLicensor();
        $this->assertFalse($sut->hasCredentials);
    }

    /**
     *
     * @runInSeparateProcess
     */
    public function testNoErrorWithBothAsParam() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=');
        $sut = new UnityLicensor('test', 'test');
        $this->assertTrue($sut->hasCredentials);
    }

    /**
     *
     * @runInSeparateProcess
     */
    public function testNoErrorWithBothAsEnv() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=test');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=test');
        $sut = new UnityLicensor();
        $this->assertTrue($sut->hasCredentials);
    }

    /**
     *
     * @runInSeparateProcess
     */
    public function testHasCredentialsIsFalseWithoutUser() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=test');
        $this->assertFalse(UnityLicensor::hasCredentialsInEnvironment());
    }

    /**
     *
     * @runInSeparateProcess
     */
    public function testHasCredentialsIsFalseWithoutPassword() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=test');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=');
        $this->assertFalse(UnityLicensor::hasCredentialsInEnvironment());
    }

    /**
     *
     * @runInSeparateProcess
     */
    public function testHasCredentialsIsTrueWithBoth() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=test');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=test');
        $this->assertTrue(UnityLicensor::hasCredentialsInEnvironment());
    }

    public function testInstallEditor() {
        $isLogging = UnityHub::getLoggingEnabled();
        UnityHub::setLoggingEnabled(true);
        $editor = $this->initEditor();
        UnityHub::setLoggingEnabled($isLogging);

        $this->assertNotNull($editor, sprintf('Failed to install editor "%s".', self::EDITOR_VERSION));
    }

    public function testSign() {
        if ($this->tryPrepareEnvironment(UnityLicensor::ENV_UNITY_LICENSE_EMAIL, UnityLicensor::ENV_UNITY_LICENSE_PASSWORD, MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW)) {
            if ($editor = $this->initEditor()) {
                if ($file = $editor->createLicenseFile()) {
                    $sut = new UnityLicensor();
                    $file = $sut->sign($file);
                    $this->assertFileExists($file, 'Failed to create a signed license file.');
                    $document = DOMHelper::loadDocument($file);
                    $this->assertInstanceOf(DOMDocument::class, $document);
                    $signatures = $document->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
                    $this->assertNotNull($signatures->item(0), 'Alleged ulf file is missing the <Signature xmlns="http://www.w3.org/2000/09/xmldsig#"> element.');

                    if (! $editor->useLicenseFile($file)) {
                        $this->markTestIncomplete("Failed to activate via -manualLicenseFile '$file'!");
                    }
                } else {
                    $this->markTestSkipped('Failed to create the license activation file.');
                }
            }
        }
    }
}