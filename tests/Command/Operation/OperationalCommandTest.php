<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Operation;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Unity\Command\AssetExecutionResult;
use Slothsoft\Unity\Command\AssetExecutorInterface;
use Slothsoft\Unity\Command\ApplicationFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;

final class OperationalCommandTest extends TestCase {
    
    public function testBuildMapsDefaultsToBaseAsset(): void {
        $executor = new RecordingOperationAssetExecutor();
        $tester = new CommandTester(new BuildCommand($executor));
        
        $code = $tester->execute([
            'workspace' => 'workspace'
        ]);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame(1, $executor->executionCount);
        $this->assertSame(sprintf('farah://slothsoft@unity/project/build?path=%s&target=windows&workspace=workspace', rawurlencode('workspace' . DIRECTORY_SEPARATOR . 'build')), (string) $executor->url);
    }
    
    public function testBuildMapsExplicitArgumentsToBaseAsset(): void {
        $executor = new RecordingOperationAssetExecutor();
        $tester = new CommandTester(new BuildCommand($executor));
        
        $code = $tester->execute([
            'workspace' => 'workspace',
            'build-path' => 'output',
            'platform' => 'linux'
        ]);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame('farah://slothsoft@unity/project/build?path=output&target=linux&workspace=workspace', (string) $executor->url);
    }
    
    public function testEmptyProjectMapsVersionToBaseAsset(): void {
        $executor = new RecordingOperationAssetExecutor();
        $tester = new CommandTester(new EmptyProjectCommand($executor));
        
        $code = $tester->execute([
            'workspace' => 'workspace',
            'version' => '2022.1'
        ]);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame('farah://slothsoft@unity/project/empty-project?version=2022.1&workspace=workspace', (string) $executor->url);
    }
    
    public function testEmptyProjectMapsEmptyVersionByDefault(): void {
        $executor = new RecordingOperationAssetExecutor();
        $tester = new CommandTester(new EmptyProjectCommand($executor));
        
        $code = $tester->execute([
            'workspace' => 'workspace'
        ]);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame('farah://slothsoft@unity/project/empty-project?version&workspace=workspace', (string) $executor->url);
    }
    
    public function testMethodForwardsOptionLikeArgumentsAfterTerminator(): void {
        $executor = new RecordingOperationAssetExecutor();
        $code = $this->executeArgv(new MethodCommand($executor), [
            'workspace',
            'Namespace.Type.Method',
            '--',
            '--method-option',
            'value'
        ]);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame('farah://slothsoft@unity/project/method?args[]=--method-option&args[]=value&method=Namespace.Type.Method&quit=1&workspace=workspace', (string) $executor->url);
    }
    
    public function testStartForwardsOptionLikeArgumentsAfterTerminator(): void {
        $executor = new RecordingOperationAssetExecutor();
        $code = $this->executeArgv(new StartCommand($executor), [
            'workspace',
            'Namespace.Type.Method',
            '--',
            '--method-option',
            'value'
        ]);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame('farah://slothsoft@unity/project/method?args[]=--method-option&args[]=value&method=Namespace.Type.Method&quit=0&workspace=workspace', (string) $executor->url);
    }
    
    public function testModuleInstallMapsAllModulesToBaseAsset(): void {
        $executor = new RecordingOperationAssetExecutor();
        $tester = new CommandTester(new ModuleInstallCommand($executor));
        
        $code = $tester->execute([
            'workspace' => 'workspace',
            'modules' => [
                'linux-il2cpp',
                'windows-mono'
            ]
        ]);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame('farah://slothsoft@unity/project/module?modules[]=linux-il2cpp&modules[]=windows-mono&workspace=workspace', (string) $executor->url);
    }
    
    public function testTestsMapsAllModesToBaseAsset(): void {
        $executor = new RecordingOperationAssetExecutor();
        $tester = new CommandTester(new TestsCommand($executor));
        
        $code = $tester->execute([
            'workspace' => 'workspace',
            'modes' => [
                'EditMode',
                'PlayMode'
            ]
        ]);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame('farah://slothsoft@unity/project/tests?modes[]=EditMode&modes[]=PlayMode&workspace=workspace', (string) $executor->url);
    }
    
    public function testPackageInstallUsesWorkspaceFirstBaseAsset(): void {
        $executor = new RecordingOperationAssetExecutor();
        $tester = new CommandTester(new PackageInstallCommand($executor));
        
        $code = $tester->execute([
            'workspace' => 'workspace',
            'package' => 'package'
        ]);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame('farah://slothsoft@unity/package/install-workspace?package=package&workspace=workspace', (string) $executor->url);
    }
    
    /**
     * @dataProvider commandHelpProvider
     */
    public function testCommandHelp(string $commandClass, string $name, string $description): void {
        $command = new $commandClass(new RecordingOperationAssetExecutor());
        $application = new Application('unity-command');
        $application->setAutoExit(false);
        $application->add($command);
        $tester = new ApplicationTester($application);
        
        $code = $tester->run([
            'command' => $name,
            '--help' => true
        ], [
            'decorated' => false
        ]);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Usage:', $tester->getDisplay());
        $this->assertStringContainsString($name, $tester->getDisplay());
        $this->assertStringContainsString($description, $tester->getDisplay());
    }
    
    public function commandHelpProvider(): iterable {
        yield 'build' => [
            BuildCommand::class,
            'build',
            'Build a Unity project.'
        ];
        yield 'empty-project' => [
            EmptyProjectCommand::class,
            'empty-project',
            'Create an empty Unity project.'
        ];
        yield 'method' => [
            MethodCommand::class,
            'method',
            'Run an editor method inside a Unity project.'
        ];
        yield 'start' => [
            StartCommand::class,
            'start',
            'Start an editor method inside a Unity project.'
        ];
        yield 'module-install' => [
            ModuleInstallCommand::class,
            'module-install',
            'Install Unity editor modules for a project.'
        ];
        yield 'tests' => [
            TestsCommand::class,
            'tests',
            'Run Unity Test Runner tests.'
        ];
        yield 'package-install' => [
            PackageInstallCommand::class,
            'package-install',
            'Install an embedded package into a Unity workspace.'
        ];
    }

    /**
     * @dataProvider missingRequiredInputProvider
     */
    public function testMissingRequiredInputUsesSymfonyInvalidExitCode(string $commandClass, string $name, array $arguments): void {
        $application = ApplicationFactory::createReporting([
            new $commandClass(new RecordingOperationAssetExecutor())
        ]);
        $tester = new ApplicationTester($application);

        $code = $tester->run([
            'command' => $name,
            ...$arguments
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);

        $this->assertSame(Command::INVALID, $code);
        $this->assertStringContainsString('Not enough arguments', $tester->getErrorOutput());
    }

    public function missingRequiredInputProvider(): iterable {
        yield 'build' => [BuildCommand::class, 'build', []];
        yield 'empty-project' => [EmptyProjectCommand::class, 'empty-project', []];
        yield 'method' => [MethodCommand::class, 'method', ['workspace' => 'workspace']];
        yield 'start' => [StartCommand::class, 'start', ['workspace' => 'workspace']];
        yield 'module-install' => [ModuleInstallCommand::class, 'module-install', ['workspace' => 'workspace']];
        yield 'tests' => [TestsCommand::class, 'tests', ['workspace' => 'workspace']];
        yield 'package-install' => [PackageInstallCommand::class, 'package-install', ['workspace' => 'workspace']];
    }
    
    private function executeArgv(Command $command, array $arguments): int {
        $application = new Application('unity-command');
        $application->setAutoExit(false);
        $application->add($command);
        
        return $application->run(new ArgvInput([
            'unity-command',
            $command->getName(),
            ...$arguments
        ]), new BufferedOutput());
    }
}

final class RecordingOperationAssetExecutor implements AssetExecutorInterface {
    
    public ?FarahUrl $url = null;
    
    public int $executionCount = 0;
    
    public function execute(FarahUrl $url, OutputInterface $output): AssetExecutionResult {
        $this->url = $url;
        $this->executionCount ++;
        if ($url->getPath() === '/project/tests') {
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->loadXML('<test-run failed="0" inconclusive="0" />');
            return new AssetExecutionResult(Command::SUCCESS, $document);
        }
        return new AssetExecutionResult(Command::SUCCESS);
    }
}
