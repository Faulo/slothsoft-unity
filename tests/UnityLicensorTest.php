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
 * @see UnityEditor
 *
 * @todo auto-generated
 */
class UnityLicensorTest extends TestCase {

    public function testClassExists(): void {
        $this->assertTrue(class_exists(UnityLicensor::class), "Failed to load class 'Slothsoft\Unity\UnityLicensor'!");
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
            $this->markTestSkipped('Failed to install editor.');
            return null;
        }

        return $editor;
    }

    public function testErrorWithoutUser() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=test');
        $this->expectErrorMessage('UnityLicensor requires the environment variable "' . UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '" to be set.');
        new UnityLicensor();
    }

    public function testErrorWithoutPassword() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=test');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=');
        $this->expectErrorMessage('UnityLicensor requires the environment variable "' . UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '" to be set.');
        new UnityLicensor();
    }

    public function testNoErrorWithBothAsParam() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=');
        new UnityLicensor('test', 'test');
    }

    public function testNoErrorWithBothAsEnv() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=test');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=test');
        new UnityLicensor();
    }

    public function testHasCredentialsIsFalseWithoutUser() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=test');
        $this->assertFalse(UnityLicensor::hasCredentials());
    }

    public function testHasCredentialsIsFalseWithoutPassword() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=test');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=');
        $this->assertFalse(UnityLicensor::hasCredentials());
    }

    public function testHasCredentialsIsTrueWithBoth() {
        putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=test');
        putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=test');
        $this->assertTrue(UnityLicensor::hasCredentials());
    }

    public function testSign() {
        if (is_file('.env.local')) {
            $env = Dotenv::createImmutable(getcwd(), '.env.local')->load();
            putenv(UnityLicensor::ENV_UNITY_LICENSE_EMAIL . '=' . $env[UnityLicensor::ENV_UNITY_LICENSE_EMAIL]);
            putenv(UnityLicensor::ENV_UNITY_LICENSE_PASSWORD . '=' . $env[UnityLicensor::ENV_UNITY_LICENSE_PASSWORD]);
            putenv(MailboxAccess::ENV_EMAIL_USR . '=' . $env[MailboxAccess::ENV_EMAIL_USR]);
            putenv(MailboxAccess::ENV_EMAIL_PSW . '=' . $env[MailboxAccess::ENV_EMAIL_PSW]);

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
        } else {
            $this->markTestSkipped('Missing unity login file ".env.local".');
        }
    }
}