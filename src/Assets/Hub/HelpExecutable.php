<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Hub;

use Slothsoft\Core\IO\Writable\Delegates\ChunkWriterFromChunksDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ChunkWriterResultBuilder;
use Slothsoft\Unity\UnityHub;
use Generator;

class HelpExecutable implements ExecutableBuilderStrategyInterface {

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $delegate = function (): Generator {
            $hub = UnityHub::getInstance();
            if ($hub->isInstalled()) {
                yield from $hub->executeStream('help');
            } else {
                yield 'Unity Hub is not installed!';
            }
        };
        $writer = new ChunkWriterFromChunksDelegate($delegate);
        $resultBuilder = new ChunkWriterResultBuilder($writer, "help.txt", false);
        return new ExecutableStrategies($resultBuilder);
    }
}

