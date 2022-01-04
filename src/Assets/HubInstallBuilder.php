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
        $version = $args->get('version');
        $modules = (array) $args->get('modules');
        $hub = new UnityHub();
        if ($version === '') {
            // create editor index
            $generator = $hub->executeStream([
                'editors',
                '-r'
            ]);
            $writer = new ChunkWriterFromGenerator($generator);
        } else {
            // actually install editor+modules
            $chunkDelegate = function () use ($hub, $version, $modules): Generator {
                $hub->loadEditors();
                if (isset($hub->editors[$version])) {
                    foreach ($modules as $module) {
                        yield "Unity Editor $version already installed, installing module $module..." . PHP_EOL;
                        $args = $hub->createModuleInstallation($version, [
                            $module
                        ]);
                        yield from $hub->executeStream($args);
                    }
                } else {
                    $m = json_encode($modules);
                    yield "Installing Unity Editor version $version with modules $m..." . PHP_EOL;
                    $args = $hub->createEditorInstallation($version, $modules);
                    yield from $hub->executeStream($args);
                }
                yield PHP_EOL . "Done!" . PHP_EOL;
            };
            $writer = new ChunkWriterFromChunksDelegate($chunkDelegate);
        }
        $resultBuilder = new ChunkWriterResultBuilder($writer, "install.txt", false);
        return new ExecutableStrategies($resultBuilder);
    }
}

