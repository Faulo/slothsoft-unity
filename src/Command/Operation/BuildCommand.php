<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Operation;

use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\Command\AbstractAssetCommand;
use Slothsoft\Unity\Command\AssetExecutorInterface;
use Slothsoft\Unity\UnityBuildTarget;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

final class BuildCommand extends AbstractAssetCommand {
    
    public function __construct(AssetExecutorInterface $executor) {
        parent::__construct($executor, 'build');
    }
    
    protected function configure(): void {
        parent::configure();
        $this->setDescription('Build a Unity project.')
            ->setHelp('Builds a Unity project for the requested platform. The build path defaults to a "build" directory inside the workspace, and the platform defaults to "windows".')
            ->addArgument('workspace', InputArgument::REQUIRED, 'Path to the Unity project.')
            ->addArgument('build-path', InputArgument::OPTIONAL, 'Destination directory for the build.')
            ->addArgument('platform', InputArgument::OPTIONAL, 'Build platform.', UnityBuildTarget::WINDOWS);
    }
    
    protected function createAssetUrl(InputInterface $input): FarahUrl {
        $workspace = (string) $input->getArgument('workspace');
        $buildPath = $input->getArgument('build-path');
        if ($buildPath === null) {
            $buildPath = $workspace . DIRECTORY_SEPARATOR . 'build';
        }
        
        return FarahUrl::createFromComponents('slothsoft@unity', '/project/build', FarahUrlArguments::createFromValueList([
            'workspace' => $workspace,
            'target' => (string) $input->getArgument('platform'),
            'path' => (string) $buildPath
        ]));
    }
}
