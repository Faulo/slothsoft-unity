<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use Slothsoft\Farah\FarahUrl\FarahUrl;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractAssetCommand extends Command {
    
    public function __construct(private readonly AssetExecutorInterface $executor, readonly ?string $name = null) {
        parent::__construct($name);
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int {
        return $this->executor->execute($this->createAssetUrl($input), $output)->getExitCode();
    }
    
    abstract protected function createAssetUrl(InputInterface $input): FarahUrl;
}
