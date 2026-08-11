<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Operation;

use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\Command\AbstractAssetCommand;
use Slothsoft\Unity\Command\AssetExecutorInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

final class MethodCommand extends AbstractAssetCommand {
    
    public function __construct(AssetExecutorInterface $executor) {
        parent::__construct($executor, 'method');
    }
    
    protected function configure(): void {
        parent::configure();
        $this->setDescription('Run an editor method inside a Unity project.')
            ->setHelp('Runs an editor method and asks Unity to quit afterward. Place -- before option-like method arguments so Symfony forwards them unchanged.')
            ->addArgument('workspace', InputArgument::REQUIRED, 'Path to the Unity project.')
            ->addArgument('method', InputArgument::REQUIRED, 'Fully qualified editor method name.')
            ->addArgument('arguments', InputArgument::IS_ARRAY, 'Arguments forwarded to the editor method.');
    }
    
    protected function createAssetUrl(InputInterface $input): FarahUrl {
        return FarahUrl::createFromComponents('slothsoft@unity', '/project/method', FarahUrlArguments::createFromValueList([
            'workspace' => (string) $input->getArgument('workspace'),
            'method' => (string) $input->getArgument('method'),
            'quit' => 1,
            'args' => $input->getArgument('arguments')
        ]));
    }
}
