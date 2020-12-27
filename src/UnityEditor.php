<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;

class UnityEditor {

    public $version;

    public $executable;

    public function __construct(string $executable, string $version) {
        assert(is_file($executable));
        $this->executable = $executable;
        $this->version = $version;
    }

    public function execute(array $arguments): string {
        $command = array_merge([
            $this->executable,
            '-quit',
            '-batchmode',
            '-nographics',
            '-accept-apiupdate'
        ], $arguments);
        $process = new Process($command);
        $process->setTimeout(0);
        $process->start();
        $result = '';
        foreach ($process as $type => $data) {
            if ($type === $process::OUT) {
                $result .= $data;
            } else {
                fwrite(STDERR, $data);
            }
        }
        return trim($result);
    }
}