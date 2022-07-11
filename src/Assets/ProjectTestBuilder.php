<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Core\IO\FileInfoFactory;
use Slothsoft\Core\IO\Writable\Delegates\ChunkWriterFromChunksDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Module;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ChunkWriterResultBuilder;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\FileWriterResultBuilder;
use Slothsoft\Unity\UnityHub;
use Generator;
use Throwable;

class ProjectTestBuilder implements ExecutableBuilderStrategyInterface {

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $debug = $args->get('debug');
        $resultsFile = temp_file(__NAMESPACE__);
        $chunkDelegate = function () use ($context, $args, $resultsFile): Generator {
            try {
                $url = $context->createUrl($args)->withPath('/project/install');
                yield "Installing project via: $url" . PHP_EOL;
                yield from Module::resolveToChunkWriter($url)->toChunks();

                $hub = UnityHub::getInstance();
                $id = $args->get('id');
                $branch = $args->get('branch');
                $projectPath = $hub->getProjectPath($id, $branch);
                $unity = $hub->loadProject($projectPath);

                yield from $unity->executeTestRunner($resultsFile, 'StandaloneWindows64');
            } catch (Throwable $e) {
                yield $e->getMessage();
            }
        };
        if ($debug) {
            $writer = new ChunkWriterFromChunksDelegate($chunkDelegate);
            $resultBuilder = new ChunkWriterResultBuilder($writer, "test.txt", false);
        } else {
            $result = '';
            foreach ($chunkDelegate() as $tmp) {
                $result .= $tmp;
            }
            if (file_exists($resultsFile)) {
                // success! get XML
                $file = FileInfoFactory::createFromPath($resultsFile);
                $resultBuilder = new FileWriterResultBuilder($file, 'test.xml');
            } else {
                // failure! get log
                $file = FileInfoFactory::createFromString($result);
                $resultBuilder = new FileWriterResultBuilder($file, 'test.log');
            }
        }
        return new ExecutableStrategies($resultBuilder);
    }
}

