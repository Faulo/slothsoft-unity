<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

final class UnityProcessOutput {
    
    private static ?UnityProcessOutputHandlerInterface $handler = null;
    
    public static function getHandler(): ?UnityProcessOutputHandlerInterface {
        return self::$handler;
    }
    
    public static function whileHandling(UnityProcessOutputHandlerInterface $handler, callable $operation): mixed {
        $previousHandler = self::$handler;
        self::$handler = $handler;
        
        try {
            return $operation(...)();
        } finally {
            self::$handler = $previousHandler;
        }
    }
}
