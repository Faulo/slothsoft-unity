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
use Symfony\Component\Process\Process;
use Generator;
use Throwable;

class GitBuilder implements ExecutableBuilderStrategyInterface {

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $chunkDelegate = function () use ($context, $args): Generator {
            try {
                $id = $args->get('id');
                $href = $args->get('href');
                $branch = $args->get('branch');
                $hub = new UnityHub();
                if (! $hub->isInstalled) {
                    yield 'Unity Hub not installed or not found!' . PHP_EOL;
                    return;
                }
                $projectPath = $hub->getProjectPath($id, $branch);
                $args = [
                    'git'
                ];
                switch ((string) $context->getUrlPath()) {
                    case '/git/clone':
                        $args[] = 'clone';
                        $args[] = $href;
                        $args[] = $projectPath;
                        break;
                    case '/git/fetch':
                        $args[] = '-C';
                        $args[] = $projectPath;
                        $args[] = 'fetch';
                        $args[] = '--progress';
                        $args[] = '--all';
                        break;
                    case '/git/checkout':
                        $args[] = '-C';
                        $args[] = $projectPath;
                        $args[] = 'checkout';
                        $args[] = '-B';
                        $args[] = $branch;
                        $args[] = '--track';
                        $args[] = "origin/$branch";
                        break;
                }

                $process = new Process($args);
                $process->setTimeout(0);
                yield $process->getCommandLine() . PHP_EOL;
                $process->start();
                yield from $process;
            } catch (Throwable $e) {
                yield $e->getMessage();
            }
        };
        $writer = new ChunkWriterFromChunksDelegate($chunkDelegate);
        $resultBuilder = new ChunkWriterResultBuilder($writer, "install.txt", false);
        return new ExecutableStrategies($resultBuilder);
    }
}

