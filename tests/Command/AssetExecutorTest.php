<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Command;

use PHPUnit\Framework\TestCase;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Unity\ExecutionError;
use Slothsoft\Unity\UnityHub;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Process\Process;
use DOMDocument;
use ReflectionProperty;
use RuntimeException;

final class AssetExecutorTest extends TestCase {
    
    protected function tearDown(): void {
        UnityHub::setThrowOnFailure(false);
    }
    
    public function testResolvesOnceAndSeparatesProcessOutput(): void {
        $resolver = new SyntheticResolver(function (): DOMDocument {
            $this->assertTrue(UnityHub::getThrowOnFailure());
            $this->assertInstanceOf(SymfonyProcessOutputHandler::class, UnityHub::getProcessOutputHandler());
            $process = new Process([
                PHP_BINARY,
                dirname(__DIR__, 2) . '/test-files/Command/process-output.php',
                '0'
            ]);
            UnityHub::runUnityProcess($process);
            return $this->createInternalDocument();
        });
        $tester = $this->createTester($resolver);
        
        $code = $tester->run([
            'command' => 'fixture'
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame(1, $resolver->resolveCount);
        $this->assertStringContainsString('process-output.php', $tester->getDisplay());
        $this->assertStringContainsString("fixture stdout\n", $tester->getDisplay());
        $this->assertStringContainsString('Process finished with exit code 0.' . PHP_EOL, $tester->getDisplay());
        $this->assertStringNotContainsString('internal-result', $tester->getDisplay());
        $this->assertSame("fixture stderr\n", $tester->getErrorOutput());
        $this->assertFalse(UnityHub::getThrowOnFailure());
        $this->assertNull(UnityHub::getProcessOutputHandler());
    }
    
    public function testReturnsUnityExitCodeUnchanged(): void {
        $resolver = new SyntheticResolver(function (): DOMDocument {
            $process = new Process([
                PHP_BINARY,
                dirname(__DIR__, 2) . '/test-files/Command/process-output.php',
                '42'
            ]);
            UnityHub::runUnityProcess($process);
            return $this->createInternalDocument();
        });
        $tester = $this->createTester($resolver);
        
        $code = $tester->run([
            'command' => 'fixture'
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);
        
        $this->assertSame(42, $code);
        $this->assertSame(1, $resolver->resolveCount);
        $this->assertStringContainsString("fixture stdout\n", $tester->getDisplay());
        $this->assertStringContainsString("fixture stderr\n", $tester->getErrorOutput());
        $this->assertStringContainsString('Process finished with exit code 42.' . PHP_EOL, $tester->getErrorOutput());
        $this->assertStringContainsString('Command failed (underlying exit code 42): Process finished with exit code', $tester->getErrorOutput());
    }
    
    public function testReturnsNegativeUnityExitCodeUnchanged(): void {
        $process = new Process([
            PHP_BINARY,
            dirname(__DIR__, 2) . '/test-files/Command/process-output.php',
            '0'
        ]);
        $process->run();
        $exitCode = new ReflectionProperty(Process::class, 'exitcode');
        $exitCode->setValue($process, - 42);
        $resolver = new SyntheticResolver(function () use ($process): DOMDocument {
            throw ExecutionError::Error('Synthetic', 'negative exit code', $process);
        });
        $tester = $this->createTester($resolver);
        
        $code = $tester->run([
            'command' => 'fixture'
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);
        
        $this->assertSame(- 42, $code);
        $this->assertSame('Command failed (underlying exit code -42): negative exit code' . PHP_EOL, $tester->getErrorOutput());
    }
    
    public function testExecutionErrorWithSuccessfulProcessUsesSymfonyFailureCode(): void {
        $process = new Process([
            PHP_BINARY,
            dirname(__DIR__, 2) . '/test-files/Command/process-output.php',
            '0'
        ]);
        $process->run();
        $resolver = new SyntheticResolver(function () use ($process): DOMDocument {
            throw ExecutionError::Error('Synthetic', 'semantic failure', $process);
        });
        $tester = $this->createTester($resolver);
        
        $code = $tester->run([
            'command' => 'fixture'
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);
        
        $this->assertSame(Command::FAILURE, $code);
        $this->assertSame('Command failed (underlying exit code 0): semantic failure' . PHP_EOL, $tester->getErrorOutput());
    }
    
    public function testApplicationFailureUsesSymfonyFailureCode(): void {
        $resolver = new SyntheticResolver(function (): DOMDocument {
            throw new RuntimeException('synthetic failure');
        });
        $tester = $this->createTester($resolver);
        
        $code = $tester->run([
            'command' => 'fixture'
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);
        
        $this->assertSame(Command::FAILURE, $code);
        $this->assertSame(1, $resolver->resolveCount);
        $this->assertSame('Command failed: synthetic failure' . PHP_EOL, $tester->getErrorOutput());
    }
    
    public function testExecutionErrorWithoutProcessUsesSymfonyFailureCode(): void {
        $resolver = new SyntheticResolver(function (): DOMDocument {
            throw ExecutionError::Error('Synthetic', 'semantic failure');
        });
        $tester = $this->createTester($resolver);
        
        $code = $tester->run([
            'command' => 'fixture'
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);
        
        $this->assertSame(Command::FAILURE, $code);
        $this->assertSame('Command failed (underlying exit code 0): semantic failure' . PHP_EOL, $tester->getErrorOutput());
    }
    
    private function createTester(FarahAssetResolverInterface $resolver): ApplicationTester {
        $executor = new AssetExecutor($resolver);
        return new ApplicationTester(ApplicationFactory::create([
            new ExecutorCommand($executor)
        ]));
    }
    
    private function createInternalDocument(): DOMDocument {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->createElement('internal-result'));
        return $document;
    }
}

final class ExecutorCommand extends AbstractAssetCommand {
    
    public function __construct(AssetExecutorInterface $executor) {
        parent::__construct($executor, 'fixture');
    }
    
    protected function createAssetUrl(InputInterface $input): FarahUrl {
        return FarahUrl::createFromReference('farah://slothsoft@unity/project/method');
    }
}

final class SyntheticResolver implements FarahAssetResolverInterface {
    
    public int $resolveCount = 0;
    
    private \Closure $delegate;
    
    public function __construct(callable $delegate) {
        $this->delegate = \Closure::fromCallable($delegate);
    }
    
    public function resolve(FarahUrl $url): DOMDocument {
        $this->resolveCount ++;
        return ($this->delegate)();
    }
}
