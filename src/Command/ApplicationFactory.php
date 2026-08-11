<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Command;

use Slothsoft\Unity\Command\Operation\BuildCommand;
use Slothsoft\Unity\Command\Operation\EmptyProjectCommand;
use Slothsoft\Unity\Command\Operation\MethodCommand;
use Slothsoft\Unity\Command\Operation\ModuleInstallCommand;
use Slothsoft\Unity\Command\Operation\PackageInstallCommand;
use Slothsoft\Unity\Command\Operation\StartCommand;
use Slothsoft\Unity\Command\Operation\TestsCommand;
use Slothsoft\Unity\Command\Reporting\JUnitReporter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

final readonly class ApplicationFactory {
    
    /**
     * @param iterable<Command> $commands
     */
    public static function create(iterable $commands = []): Application {
        $application = new Application('unity-command');
        $application->setAutoExit(false);
        
        foreach ($commands as $command) {
            $application->add($command);
        }
        
        return $application;
    }

    public static function createDefault(): Application {
        $executor = new AssetExecutor(new FarahAssetResolver());
        return self::createReporting([
            new BuildCommand($executor),
            new EmptyProjectCommand($executor),
            new MethodCommand($executor),
            new StartCommand($executor),
            new ModuleInstallCommand($executor),
            new PackageInstallCommand($executor),
            new TestsCommand($executor)
        ]);
    }

    /**
     * @param iterable<Command> $commands
     */
    public static function createReporting(iterable $commands = []): Application {
        $application = new UnityApplication(new JUnitReporter());
        $application->setAutoExit(false);

        foreach ($commands as $command) {
            $application->add($command);
        }

        return $application;
    }
}
