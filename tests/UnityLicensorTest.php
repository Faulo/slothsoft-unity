<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

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

    const EDITOR_VERSION = '2021.2.7f1';

    const EDITOR_CHANGESET = '6bd9e232123f';

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

    public function testSign() {
        if ($editor = $this->initEditor()) {
            $log = $editor->execute(false, '-createManualActivationFile')->getOutput();

            $match = [];
            if (preg_match('~(Unity_v[^\s]+\.alf)~', $log, $match) and $file = trim($match[1]) and is_file($file)) {
                $sut = new UnityLicensor();
                $file = $sut->sign($file);
                $this->assertFileExists($file, 'Failed to create a signed license file.');
                $document = DOMHelper::loadDocument($file);
                $this->assertInstanceOf(DOMDocument::class, $document);
                $signatures = $document->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
                $this->assertNotNull($signatures->item(0), 'Alleged ulf file is missing the <Signature xmlns="http://www.w3.org/2000/09/xmldsig#"> element.');

                $result = $editor->execute(false, '-manualLicenseFile', $file)->getExitCode();
                $this->assertEquals(0, $result, "Failed to activate via -manualLicenseFile '$file'!");
            } else {
                $this->markTestSkipped('Failed to create the license activation file.');
            }
        }
    }
}