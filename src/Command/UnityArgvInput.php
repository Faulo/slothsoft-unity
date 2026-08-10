<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * Accepts the documented stdout destination spelling `--junit -`.
 */
final class UnityArgvInput extends ArgvInput {

    public function __construct(?array $argv = null, ?InputDefinition $definition = null) {
        parent::__construct(self::normalize($argv ?? $_SERVER['argv'] ?? []), $definition);
    }

    private static function normalize(array $argv): array {
        $normalized = [];
        $parseOptions = true;

        for ($index = 0, $count = count($argv); $index < $count; $index ++) {
            $argument = $argv[$index];
            if ($parseOptions and $argument === '--') {
                $parseOptions = false;
            }
            if ($parseOptions and $argument === '--junit' and ($argv[$index + 1] ?? null) === '-') {
                $normalized[] = '--junit=-';
                $index ++;
                continue;
            }
            $normalized[] = $argument;
        }

        return $normalized;
    }
}
