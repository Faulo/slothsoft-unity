<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

final class UnityArgvInputTest extends TestCase {

    public function testAcceptsSeparatedStdoutDestination(): void {
        $input = new UnityArgvInput([
            'unity-command',
            '--junit',
            '-',
            'workspace'
        ], new InputDefinition([
            new InputOption('junit', null, InputOption::VALUE_REQUIRED),
            new InputArgument('workspace', InputArgument::REQUIRED)
        ]));

        $this->assertSame('-', $input->getOption('junit'));
        $this->assertSame('workspace', $input->getArgument('workspace'));
    }

    public function testDoesNotRewriteForwardedArguments(): void {
        $input = new UnityArgvInput([
            'unity-command',
            '--',
            '--junit',
            '-'
        ], new InputDefinition([
            new InputArgument('arguments', InputArgument::IS_ARRAY)
        ]));

        $this->assertSame([
            '--junit',
            '-'
        ], $input->getArgument('arguments'));
    }
}
