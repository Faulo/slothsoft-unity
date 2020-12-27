<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Core\IO\Writable\Delegates\ChunkWriterFromChunksDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ChunkWriterResultBuilder;
use Slothsoft\Unity\UnityHub;
use Generator;

class HubInstallBuilder implements ExecutableBuilderStrategyInterface {

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $hub = new UnityHub();
        $version = $args->get('version');
        $chunkDelegate = function () use ($hub, $version): Generator {
            if ($version === '') {
                yield 'Missing parameter: version';
            } else {
                yield from $hub->installEditor($version);
            }
        };
        $writer = new ChunkWriterFromChunksDelegate($chunkDelegate);
        $resultBuilder = new ChunkWriterResultBuilder($writer, "result.txt");
        return new ExecutableStrategies($resultBuilder);
    }
}

