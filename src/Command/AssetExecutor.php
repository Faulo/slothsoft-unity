<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Unity\ExecutionError;
use Slothsoft\Unity\UnityHub;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final readonly class AssetExecutor implements AssetExecutorInterface {
    
    public function __construct(private FarahAssetResolverInterface $resolver) {
    }
    
    public function execute(FarahUrl $url, OutputInterface $output): AssetExecutionResult {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $handler = new SymfonyProcessOutputHandler($output, $errorOutput);
        $previousConfig = UnityHub::getConfig();
        $executionConfig = clone $previousConfig;
        $executionConfig->throwOnFailure = true;
        $executionConfig->processOutputHandler = $handler;
        UnityHub::setConfig($executionConfig);
        
        try {
            $document = $this->resolver->resolve($url);
            return new AssetExecutionResult(Command::SUCCESS, $document);
        } catch (ExecutionError $error) {
            $errorExitCode = $error->getExitCode();
            $errorOutput->writeln(sprintf('Command failed (underlying exit code %d): %s', $errorExitCode, $error->getMessage()));
            $exitCode = $errorExitCode !== 0 ? $errorExitCode : Command::FAILURE;
            return new AssetExecutionResult($exitCode, null, $error);
        } catch (Throwable $error) {
            $errorOutput->writeln(sprintf('Command failed: %s', $error->getMessage()));
            return new AssetExecutionResult(Command::FAILURE, null, $error);
        } finally {
            UnityHub::setConfig($previousConfig);
        }
    }
}
