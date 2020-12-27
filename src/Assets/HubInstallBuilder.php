<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ChunkWriterResultBuilder;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Core\IO\Writable\Delegates\ChunkWriterFromChunksDelegate;
use Generator;

class HubInstallBuilder implements ExecutableBuilderStrategyInterface {

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $version = $args->get('version');
        $modules = $args->get('modules');
        $hub = new UnityHub();
        if ($version === '') {
            // create editor index
            $writer = new ChunkWriterFromProcess($hub->createEditorListing());
        } else {
            // actually install editor+modules
            $chunkDelegate = function () use ($hub, $version, $modules): Generator {
                $hub->loadEditors();
                if (isset($hub->editors[$version])) {
                    foreach ($modules as $module) {
                        yield "Unity Editor $version already installed, installing module $module..." . PHP_EOL;
                        $process = $hub->createModuleInstallation($version, [
                            $module
                        ]);
                        yield $process->getCommandLine() . PHP_EOL;
                        $process->start();
                        foreach ($process as $data) {
                            yield $data;
                        }
                    }
                } else {
                    $m = json_encode($modules);
                    yield "Installing Unity Editor version $version with modules $m..." . PHP_EOL;
                    $process = $hub->createEditorInstallation($version, $modules);
                    yield $process->getCommandLine() . PHP_EOL;
                    $process->start();
                    foreach ($process as $data) {
                        yield $data;
                    }
                }
                yield "Done!" . PHP_EOL;
            };
            $writer = new ChunkWriterFromChunksDelegate($chunkDelegate);
        }
        $resultBuilder = new ChunkWriterResultBuilder($writer, "install.txt", false);
        return new ExecutableStrategies($resultBuilder);
    }
}

