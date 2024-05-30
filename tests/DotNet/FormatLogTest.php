<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\DotNet;

use PHPUnit\Framework\TestCase;

class FormatLogTest extends TestCase {

    public function testClassExists(): void {
        $this->assertTrue(class_exists(FormatLog::class), "Failed to load class 'Slothsoft\Unity\DotNet\FormatLog'!");
    }

    public function testConstructorThrowsOnMissingFile(): void {
        $file = temp_file(__NAMESPACE__);

        $this->expectExceptionMessage("Missing format-report.json '$file'!");

        new FormatLog($file);
    }

    public function testConstructorThrowsOnInvalidJSON(): void {
        $file = temp_file(__NAMESPACE__);
        file_put_contents($file, '[');
        $file = realpath($file);

        $this->expectExceptionMessage("Malformed format-report.json '$file':" . PHP_EOL . '[');

        new FormatLog($file);
    }

    public function testEmptyJsonGeneratesRoot(): void {
        $file = temp_file(__NAMESPACE__);
        file_put_contents($file, '[]');
        touch($file, 1640991600);

        $log = new FormatLog($file);

        $actual = $log->asDocument();

        $node = $actual->documentElement;
        $this->assertNotEmpty($node);
        $this->assertEquals('Reports', $node->tagName);
        $this->assertEquals(date(DATE_W3C, 1640991600), $node->getAttribute('Time'));
        $this->assertEquals(0, $node->childNodes->length);
    }

    public function testJsonGeneratesReport(): void {
        $report = <<<EOT
        [
          {
            "DocumentId": {
              "ProjectId": {
                "Id": "74c074d7-6964-41f1-8bc3-7f21a1134f09"
              },
              "Id": "b2f2aca9-918a-4718-ae3a-218635dcb754"
            },
            "FileName": "AIEnemyActionUiState_old.cs",
            "FilePath": "R:\\\\Ulisses\\\\Ulisses.HeXXen1733.Battle\\\\Packages\\\\de.ulisses-spiele.hexxen1733.battle\\\\Runtime\\\\UI\\\\States\\\\AIEnemyActionUiState_old.cs",
            "FileChanges": [
              {
                "LineNumber": 96,
                "CharNumber": 17,
                "DiagnosticId": "IDE0019",
                "FormatDescription": "warning IDE0019: Musterabgleich verwenden"
              }
            ]
          }
        ]
        EOT;

        $file = temp_file(__NAMESPACE__);
        file_put_contents($file, $report);

        $log = new FormatLog($file);

        $actual = $log->asDocument();

        $this->assertEquals(1, $actual->documentElement->childNodes->length);

        $node = $actual->documentElement->firstChild;
        $this->assertEquals('Report', $node->tagName);
        $this->assertEquals("AIEnemyActionUiState_old.cs", $node->getAttribute('FileName'));
        $this->assertEquals("R:\\Ulisses\\Ulisses.HeXXen1733.Battle\\Packages\\de.ulisses-spiele.hexxen1733.battle\\Runtime\\UI\\States\\AIEnemyActionUiState_old.cs", $node->getAttribute('FilePath'));
        $this->assertEquals(1, $node->getElementsByTagName('FileChange')->length);

        $node = $node->getElementsByTagName('FileChange')->item(0);
        $this->assertEquals(96, $node->getAttribute('LineNumber'));
        $this->assertEquals(17, $node->getAttribute('CharNumber'));
        $this->assertEquals("IDE0019", $node->getAttribute('DiagnosticId'));
        $this->assertEquals("warning IDE0019: Musterabgleich verwenden", $node->getAttribute('FormatDescription'));
    }
}


