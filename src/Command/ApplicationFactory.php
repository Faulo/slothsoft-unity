<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Command;

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
}
