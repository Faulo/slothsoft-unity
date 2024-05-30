<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\DotNet;

use PHPUnit\Framework\TestCase;

class FormatLogTests extends TestCase {

    public function testClassExists(): void {
        $this->assertTrue(class_exists(FormatLog::class), "Failed to load class 'Slothsoft\Unity\DotNet\FormatLog'!");
    }

    public function givenMissingFile_WhenConstruct_ThenThrow(): void {
        $file = temp_file(__NAMESPACE__);

        $this->expectExceptionMessage("Missing format-report.json '$file'!");

        new FormatLog($file);
    }
}


