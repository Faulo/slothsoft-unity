<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Operation;

use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\Command\AbstractAssetCommand;
use Slothsoft\Unity\Command\AssetExecutorInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

final class EmptyProjectCommand extends AbstractAssetCommand {
    
    public function __construct(AssetExecutorInterface $executor) {
        parent::__construct($executor, 'empty-project');
    }
    
    protected function configure(): void {
        parent::configure();
        $this->setDescription('Create an empty Unity project.')
            ->setHelp('Creates an empty Unity project using the latest final editor in the requested version subtree. When VERSION is omitted, the latest available final editor is used.')
            ->addArgument('workspace', InputArgument::REQUIRED, 'Path at which to create the Unity project.')
            ->addArgument('version', InputArgument::OPTIONAL, 'Unity editor version prefix.', '');
    }
    
    protected function createAssetUrl(InputInterface $input): FarahUrl {
        return FarahUrl::createFromComponents('slothsoft@unity', '/project/empty-project', FarahUrlArguments::createFromValueList([
            'workspace' => (string) $input->getArgument('workspace'),
            'version' => (string) $input->getArgument('version')
        ]));
    }
}
