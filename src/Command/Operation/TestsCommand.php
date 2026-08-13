<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Operation;

use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\Command\AbstractAssetCommand;
use Slothsoft\Unity\Command\AssetExecutionResult;
use Slothsoft\Unity\Command\AssetExecutorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

final class TestsCommand extends AbstractAssetCommand {
    
    public function __construct(AssetExecutorInterface $executor) {
        parent::__construct($executor, 'tests');
    }
    
    protected function configure(): void {
        parent::configure();
        $this->setDescription('Run Unity Test Runner tests.')
            ->setHelp('Runs one or more Unity Test Runner modes inside a Unity project.')
            ->addArgument('workspace', InputArgument::REQUIRED, 'Path to the Unity project.')
            ->addArgument('modes', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Unity test modes such as EditMode, PlayMode, or a platform.');
    }
    
    protected function createAssetUrl(InputInterface $input): FarahUrl {
        return FarahUrl::createFromComponents('slothsoft@unity', '/project/tests', FarahUrlArguments::createFromValueList([
            'workspace' => (string) $input->getArgument('workspace'),
            'modes' => $input->getArgument('modes')
        ]));
    }
    
    protected function determineExitCode(AssetExecutionResult $result): int {
        $exitCode = parent::determineExitCode($result);
        if ($exitCode !== Command::SUCCESS) {
            return $exitCode;
        }
        
        $root = $result->getDocument()?->documentElement;
        if ($root === null or $root->nodeName !== 'test-run') {
            return Command::FAILURE;
        }
        $unityExitCode = $root->getAttribute('unity-exit-code');
        if ($unityExitCode !== '') {
            if (filter_var($unityExitCode, FILTER_VALIDATE_INT) === false or (int) $unityExitCode === 0) {
                return Command::FAILURE;
            }
            return (int) $unityExitCode;
        }
        if ((int) $root->getAttribute('failed') > 0) {
            return Command::FAILURE;
        }
        
        foreach ($root->getElementsByTagName('test-case') as $testCase) {
            if ($testCase->getAttribute('result') === 'Failed') {
                return Command::FAILURE;
            }
        }
        
        return Command::SUCCESS;
    }
}
