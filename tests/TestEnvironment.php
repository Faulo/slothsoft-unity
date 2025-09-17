<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class TestEnvironment {
    
    private const ENV_FILE = '.env.local';
    
    private array $variables;
    
    public function __construct(string ...$variables) {
        $this->variables = $variables;
    }
    
    public function prepareVariables(TestCase $test): bool {
        if (is_file(self::ENV_FILE)) {
            Dotenv::createImmutable(getcwd(), self::ENV_FILE)->load();
            
            foreach ($this->variables as $variable) {
                if (isset($_ENV[$variable])) {
                    putenv($variable . '=' . $_ENV[$variable]);
                }
            }
        }
        
        $missing = [];
        
        foreach ($this->variables as $variable) {
            if (! getenv($variable)) {
                $missing[] = $variable;
            }
        }
        
        if ($missing) {
            $test->markTestSkipped(sprintf('Missing environment variables [%s]', implode(', ', $missing)));
            return false;
        } else {
            return true;
        }
    }
}

