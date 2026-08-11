<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Operation;

use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\Command\AbstractAssetCommand;
use Slothsoft\Unity\Command\AssetExecutorInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

final class PackageInstallCommand extends AbstractAssetCommand {
    
    public function __construct(AssetExecutorInterface $executor) {
        parent::__construct($executor, 'package-install');
    }
    
    protected function configure(): void {
        parent::configure();
        $this->setDescription('Install an embedded package into a Unity workspace.')
            ->setHelp('Initializes a missing or empty workspace, or reuses an exact Unity project root. Existing manifest data is merged and an existing embedded package directory is fully replaced.')
            ->addArgument('workspace', InputArgument::REQUIRED, 'Missing, empty, or exact Unity project root.')
            ->addArgument('package', InputArgument::REQUIRED, 'Path to the Unity package.');
    }
    
    protected function createAssetUrl(InputInterface $input): FarahUrl {
        return FarahUrl::createFromComponents('slothsoft@unity', '/package/install-workspace', FarahUrlArguments::createFromValueList([
            'workspace' => (string) $input->getArgument('workspace'),
            'package' => (string) $input->getArgument('package')
        ]));
    }
}
