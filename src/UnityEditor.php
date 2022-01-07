<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;

class UnityEditor {

    public $hub;

    public $executable;

    public $version;

    public $isInstalled;

    public function __construct(UnityHub $hub, string $executable, string $version) {
        $this->hub = $hub;
        $this->executable = $executable;
        $this->version = $version;
        $this->isInstalled = is_file($executable);
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