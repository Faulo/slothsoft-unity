<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Core\IO\Writable\Delegates\ChunkWriterFromChunksDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Module;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ChunkWriterResultBuilder;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Unity\UnityProject;
use Generator;
use Throwable;

class ProjectInstallBuilder implements ExecutableBuilderStrategyInterface {

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $chunkDelegate = function () use ($context, $args): Generator {
            try {
                $hub = new UnityHub();
                if (! $hub->isInstalled) {
                    yield 'Unity Hub not installed or not found!' . PHP_EOL;
                    return;
                }

                $id = $args->get('id');
                $branch = $args->get('branch');
                $projectPath = $hub->getProjectPath($id, $branch);

                $gitUrl = $context->createUrl($args)->withPath(is_dir($projectPath) ? '/git/fetch' : '/git/clone');
                yield "Loading project $id to '$projectPath' via: $gitUrl" . PHP_EOL;
                yield from Module::resolveToChunkWriter($gitUrl)->toChunks();

                $gitUrl = $gitUrl->withPath('/git/checkout');
                yield "Switching to branch '$branch' via: $gitUrl" . PHP_EOL;
                yield from Module::resolveToChunkWriter($gitUrl)->toChunks();

                $version = UnityProject::guessVersion($projectPath);
                $hub->loadEditors();
                if (isset($hub->editors[$version])) {
                    yield "Editor v$version already installed, skipping installation" . PHP_EOL;
                } else {
                    $hubArgs = FarahUrlArguments::createFromValueList([
                        'version' => $version,
                        'modules' => 'windows-il2cpp webgl'
                    ]);
                    $hubUrl = $gitUrl->withPath('/hub/install')->withQueryArguments($hubArgs);
                    yield "Installing Editor v$version via: $hubUrl" . PHP_EOL;
                    yield from Module::resolveToChunkWriter($hubUrl)->toChunks();
                }
                yield "Succesfully installed project $id to '$projectPath'!" . PHP_EOL;
            } catch (Throwable $e) {
                yield $e->getMessage();
            }
        };
        $writer = new ChunkWriterFromChunksDelegate($chunkDelegate);
        $resultBuilder = new ChunkWriterResultBuilder($writer, "install.txt", false);
        return new ExecutableStrategies($resultBuilder);
    }
}

