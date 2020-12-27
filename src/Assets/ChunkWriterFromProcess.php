<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Core\IO\Writable\ChunkWriterInterface;
use Symfony\Component\Process\Process;
use Generator;

class ChunkWriterFromProcess implements ChunkWriterInterface {

    private $process;

    public function __construct(Process $process) {
        $this->process = $process;
    }

    public function toChunks(): Generator {
        $this->process->start();
        foreach ($this->process as $type => $data) {
            if ($type === $this->process::OUT) {
                // STDOUT
                yield $data;
            } else {
                // STDERR
                yield $data;
            }
        }
    }
}

