<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use PHPUnit\Framework\TestCase;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

final class ApplicationFactoryTest extends TestCase {
    
    public function testCreatesIndependentApplications(): void {
        $command = new TerminatorCommand(new SuccessfulAssetExecutor());
        
        $first = ApplicationFactory::create([
            $command
        ]);
        $second = ApplicationFactory::create();
        
        $this->assertNotSame($first, $second);
        $this->assertTrue($first->has('fixture'));
        $this->assertFalse($second->has('fixture'));
    }
    
    public function testApplicationHelp(): void {
        $tester = new ApplicationTester(ApplicationFactory::create());
        
        $code = $tester->run([
            'command' => 'help'
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Usage:', $tester->getDisplay());
        $this->assertStringContainsString('help [options] [--] [<command_name>]', $tester->getDisplay());
        $this->assertSame('', $tester->getErrorOutput());
    }
    
    public function testUnknownCommandUsesSymfonyBehavior(): void {
        $tester = new ApplicationTester(ApplicationFactory::create());
        
        $code = $tester->run([
            'command' => 'unknown-command'
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);
        
        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('Command "unknown-command" is not defined.', $tester->getErrorOutput());
    }
    
    public function testInvalidOptionUsesSymfonyBehavior(): void {
        $application = ApplicationFactory::create();
        $output = new BufferedOutput();
        
        $code = $application->run(new ArgvInput([
            'unity-command',
            'list',
            '--unknown-option'
        ]), $output);
        
        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('The "--unknown-option" option does not exist.', $output->fetch());
    }
    
    public function testOptionTerminatorForwardsOptionLikeArguments(): void {
        $command = new TerminatorCommand(new SuccessfulAssetExecutor());
        $application = ApplicationFactory::create([
            $command
        ]);
        $output = new BufferedOutput();
        
        $code = $application->run(new ArgvInput([
            'unity-command',
            'fixture',
            '--',
            '--method-option',
            'value'
        ]), $output);
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame([
            '--method-option',
            'value'
        ], $command->arguments);
    }
}

final class TerminatorCommand extends AbstractAssetCommand {
    
    public array $arguments = [];
    
    public function __construct(AssetExecutorInterface $executor) {
        parent::__construct($executor, 'fixture');
    }
    
    protected function configure(): void {
        $this->addArgument('arguments', InputArgument::IS_ARRAY);
    }
    
    protected function createAssetUrl(InputInterface $input): FarahUrl {
        $this->arguments = $input->getArgument('arguments');
        return FarahUrl::createFromReference('farah://slothsoft@unity/project/method');
    }
}

final class SuccessfulAssetExecutor implements AssetExecutorInterface {
    
    public function execute(FarahUrl $url, OutputInterface $output): AssetExecutionResult {
        return new AssetExecutionResult(Command::SUCCESS);
    }
}
