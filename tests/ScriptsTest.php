<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ScriptsTest extends TestCase {

    public function testUnityTests(): void {
        $process = new Process([
            PHP_BINARY,
            'scripts/unity-tests'
        ]);

        $code = $process->run();
        $result = $process->getOutput();
        $errors = $process->getErrorOutput();

        $this->assertEquals('', $errors, 'Calling unity-tests failed! Command:' . PHP_EOL . $process->getCommandLine());

        $this->assertEquals(0, $code, 'Calling unity-tests failed! Command:' . PHP_EOL . $process->getCommandLine());

        $this->assertStringContainsString('composer exec unity-tests', $result, 'Calling unity-tests failed! Command:' . PHP_EOL . $process->getCommandLine());
    }
}