<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Slothsoft\Unity\ExecutionError;
use Symfony\Component\Process\Process;

/** @runTestsInSeparateProcesses */
final class CommandLineRedactorTest extends TestCase {

    public function testMasksOnlyTheThreeKnownEnvironmentPasswords(): void {
        putenv('UNITY_CREDENTIALS_PSW=unity secret');
        putenv('STEAM_CREDENTIALS_PSW=steam secret');
        putenv('EMAIL_CREDENTIALS_PSW=mail secret');
        $process = new Process(['unity', '--opaque', 'unity secret', 'steam secret', 'mail secret', 'other password']);
        $original = $process->getCommandLine();

        $display = CommandLineRedactor::fromEnvironment()->redact($original);

        foreach (['unity secret', 'steam secret', 'mail secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $display);
            $this->assertStringContainsString($secret, $original);
        }
        $this->assertStringContainsString('other password', $display);
        $this->assertSame(3, substr_count($display, CommandLineRedactor::MARKER));
        $this->assertSame($original, $process->getCommandLine());
    }

    public function testEmptyPasswordsAreIgnored(): void {
        putenv('UNITY_CREDENTIALS_PSW=');
        putenv('STEAM_CREDENTIALS_PSW');
        putenv('EMAIL_CREDENTIALS_PSW');

        $this->assertSame('unity --opaque value', CommandLineRedactor::fromEnvironment()->redact('unity --opaque value'));
    }

    public function testProcessErrorMasksItsCommandLineButPreservesProcessOutput(): void {
        $secret = 'error secret';
        putenv('UNITY_CREDENTIALS_PSW=' . $secret);
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/test-files/Command/echo-arguments.php', '0', $secret]);
        $process->run();

        $error = ExecutionError::Error('Synthetic', 'failed', $process);
        $document = new DOMDocument();
        $document->appendChild($error->asNode($document));

        $this->assertStringNotContainsString($secret, $document->saveXML());
        $this->assertStringContainsString(CommandLineRedactor::MARKER, $document->saveXML());
        $this->assertStringContainsString($secret, $error->getStdOut());
    }
}
