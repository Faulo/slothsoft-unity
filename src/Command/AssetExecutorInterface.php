<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use Slothsoft\Farah\FarahUrl\FarahUrl;
use Symfony\Component\Console\Output\OutputInterface;

interface AssetExecutorInterface {
    
    public function execute(FarahUrl $url, OutputInterface $output): AssetExecutionResult;
}
