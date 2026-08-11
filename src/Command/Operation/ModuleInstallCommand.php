<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Operation;

use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\Command\AbstractAssetCommand;
use Slothsoft\Unity\Command\AssetExecutorInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

final class ModuleInstallCommand extends AbstractAssetCommand {
    
    public function __construct(AssetExecutorInterface $executor) {
        parent::__construct($executor, 'module-install');
    }
    
    protected function configure(): void {
        parent::configure();
        $this->setDescription('Install Unity editor modules for a project.')
            ->setHelp('Installs one or more Unity editor modules for the editor version used by the project.')
            ->addArgument('workspace', InputArgument::REQUIRED, 'Path to the Unity project.')
            ->addArgument('modules', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Unity Hub module identifiers.');
    }
    
    protected function createAssetUrl(InputInterface $input): FarahUrl {
        return FarahUrl::createFromComponents('slothsoft@unity', '/project/module', FarahUrlArguments::createFromValueList([
            'workspace' => (string) $input->getArgument('workspace'),
            'modules' => $input->getArgument('modules')
        ]));
    }
}
