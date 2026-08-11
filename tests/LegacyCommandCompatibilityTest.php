<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Slothsoft\Unity\Command\Reporting\JUnitReportValidator;
use Symfony\Component\Process\Process;

final class LegacyCommandCompatibilityTest extends TestCase {
    
    /**
     * @dataProvider legacyHelpProvider
     */
    public function testLegacyDefaultOutputRemainsExact(string $script, string $expectedOutput): void {
        $process = new Process([
            PHP_BINARY,
            'scripts/' . $script
        ]);
        
        $code = $process->run();
        
        $this->assertSame(0, $code, $process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput());
        $this->assertSame($expectedOutput, $process->getOutput());
    }
    
    public function legacyHelpProvider(): iterable {
        yield 'unity-build' => [
            'unity-build',
            <<<'HELP'
            Instruct Unity to create the executable for a project.
                
            Usage:
            composer exec unity-build "path/to/project" ["path/to/build"] [platform]
            
            path/to/build defaults to a "build" directory inside the project directory.
            platform defaults to "windows".
            
            Supported platforms:
             - windows
             - linux
             - mac
            
            HELP
        ];
        yield 'unity-empty-project' => [
            'unity-empty-project',
            <<<'HELP'
            Create a new empty Unity project using the latest matching editor version.
                
            Usage:
            composer exec unity-empty-project "path/to/new-project" ["version"]
            
            HELP
        ];
        yield 'unity-method' => [
            'unity-method',
            <<<'HELP'
            Run a specific method inside a Unity project.
                
            Usage:
            composer exec unity-method "path/to/project" "Method.To.Execute" ["additional params", ...]
            
            HELP
        ];
        yield 'unity-start' => [
            'unity-start',
            <<<'HELP'
            Start a specific method inside a Unity project.
            That method must call EditorApplication.Quit() or otherwise arrange for its own death.
            
            Usage:
            composer exec unity-start "path/to/project" "Method.To.Execute" ["additional params", ...]
            
            HELP
        ];
        yield 'unity-module-install' => [
            'unity-module-install',
            <<<'HELP'
            Install modules for use in a Unity project. Check for available modules by using the "unity-help" command.
                
            Usage:
            composer exec unity-module-install "path/to/project" [module-id]+
            
            HELP
        ];
        yield 'unity-package-install' => [
            'unity-package-install',
            <<<'HELP'
            Create a new Unity project and install a local package into it.
                
            Usage:
            composer exec unity-package-install "path/to/project/Packages/path-to-package" "path/to/new-project"
            
            HELP
        ];
        yield 'unity-tests' => [
            'unity-tests',
            <<<'HELP'
            Run all tests inside a Unity project.
                
            Usage:
            composer exec unity-tests "path/to/project" [EditMode|PlayMode|Platform]+
            
            HELP
        ];
    }

    /**
     * @dataProvider legacyInvalidInvocationProvider
     */
    public function testLegacyInvalidInvocationRetainsArgumentsStreamsAndExitBehavior(string $script, array $argumentTemplates, string $errorPathKey, string $testNameTemplate): void {
        $directory = temp_dir(str_replace(':', '-', __METHOD__) . '-' . $script);
        $values = [
            '{package}' => $directory . DIRECTORY_SEPARATOR . 'missing-package',
            '{workspace}' => $directory . DIRECTORY_SEPARATOR . 'missing-workspace'
        ];
        $arguments = array_map(static fn (string $argument): string => strtr($argument, $values), $argumentTemplates);
        $process = new Process([
            PHP_BINARY,
            'scripts/' . $script,
            ...$arguments
        ]);

        $code = $process->run();

        $this->assertSame(0, $code, $process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput(), 'Legacy failures must remain encoded as JUnit on stdout.');
        $this->assertStringStartsWith('<?xml', $process->getOutput());
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($process->getOutput()));
        (new JUnitReportValidator())->assertValid($document);
        $xpath = new DOMXPath($document);
        $this->assertSame('AssertDirectory', $xpath->evaluate('string(/testsuites/testsuite/testcase/error/@type)'));
        $this->assertStringContainsString($values['{' . $errorPathKey . '}'], $xpath->evaluate('string(/testsuites/testsuite/testcase/error/@message)'));
        $this->assertSame(strtr($testNameTemplate, $values), $xpath->evaluate('string(/testsuites/testsuite/testcase/@name)'));
    }

    public function legacyInvalidInvocationProvider(): iterable {
        yield 'unity-build defaults' => [
            'unity-build',
            ['{workspace}'],
            'workspace',
            'Build("windows")'
        ];
        yield 'unity-method argument order' => [
            'unity-method',
            ['{workspace}', 'Namespace.Type.Method'],
            'workspace',
            'Namespace.Type.Method()'
        ];
        yield 'unity-start argument order' => [
            'unity-start',
            ['{workspace}', 'Namespace.Type.Method'],
            'workspace',
            'Namespace.Type.Method()'
        ];
        yield 'unity-module-install arguments' => [
            'unity-module-install',
            ['{workspace}', 'windows-mono'],
            'workspace',
            'InstallModules("windows-mono")'
        ];
        yield 'unity-package-install package first' => [
            'unity-package-install',
            ['{package}', '{workspace}'],
            'package',
            'CreateEmptyProject("{workspace}")'
        ];
        yield 'unity-tests arguments' => [
            'unity-tests',
            ['{workspace}', 'EditMode'],
            'workspace',
            'RunTests("EditMode")'
        ];
    }
}
