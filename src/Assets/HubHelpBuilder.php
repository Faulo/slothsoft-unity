<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ChunkWriterResultBuilder;
use Slothsoft\Unity\UnityHub;

class HubHelpBuilder implements ExecutableBuilderStrategyInterface {

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $hub = new UnityHub();
        $generator = $hub->executeStream([
            'help'
        ]);
        $writer = new ChunkWriterFromGenerator($generator);
        $resultBuilder = new ChunkWriterResultBuilder($writer, "help.txt", false);
        return new ExecutableStrategies($resultBuilder);
    }
}

