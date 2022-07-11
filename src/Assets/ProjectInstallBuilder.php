<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Core\FileSystem;
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
                $hub = UnityHub::getInstance();
                if (! $hub->isInstalled()) {
                    yield 'Unity Hub not installed or not found!' . PHP_EOL;
                    return;
                }

                $id = $args->get('id');
                $href = $args->get('href');
                if ($id === '') {
                    $id = parse_url($href, PHP_URL_PATH);
                    $id = str_replace('/', '.', $id);
                    $id = preg_replace([
                        '~^\.~',
                        '~\.$~'
                    ], '', $id);
                    $id = FileSystem::filenameSanitize($id);
                    $args->set('id', $id);
                }
                $branch = $args->get('branch');
                $projectPath = $hub->getProjectPath($id, $branch);

                $url = $context->createUrl($args);

                if (is_dir($projectPath)) {
                    $url = $url->withPath('/git/fetch');
                    yield "Updating project $id at '$projectPath' via: $url" . PHP_EOL;
                    yield from Module::resolveToChunkWriter($url)->toChunks();
                } else {
                    $url = $url->withPath('/git/clone');
                    yield "Creating project $id at '$projectPath' via: $url" . PHP_EOL;
                    yield from Module::resolveToChunkWriter($url)->toChunks();
                }

                if ($branch === '') {
                    $url = $url->withPath('/git/pull');
                    yield "Updating current branch via: $url" . PHP_EOL;
                    yield from Module::resolveToChunkWriter($url)->toChunks();
                } else {
                    $url = $url->withPath('/git/checkout');
                    yield "Switching to branch '$branch' via: $url" . PHP_EOL;
                    yield from Module::resolveToChunkWriter($url)->toChunks();
                }

                $version = UnityProject::guessVersion($projectPath);
                $hub->loadEditors();
                if (isset($hub->editors[$version])) {
                    yield "Editor v$version already installed, skipping installation" . PHP_EOL;
                } else {
                    $hubArgs = FarahUrlArguments::createFromValueList([
                        'version' => $version,
                        'modules' => 'windows-il2cpp webgl'
                    ]);
                    $url = $url->withPath('/hub/install')->withQueryArguments($hubArgs);
                    yield "Installing Editor v$version via: $url" . PHP_EOL;
                    yield from Module::resolveToChunkWriter($url)->toChunks();
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

