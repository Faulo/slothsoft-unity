<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;

class UnityEditor {

    /** @var UnityHub */
    public $hub;

    /** @var string */
    public $version;

    /** @var string */
    public $executable;

    /** @var bool */
    public $isInstalled;

    public function __construct(UnityHub $hub, string $version) {
        $this->hub = $hub;
        $this->version = $version;
    }

    public function setExecutable(string $executable) {
        assert(is_file($executable));
        $this->executable = $executable;
        $this->isInstalled = true;
    }

    public function install(): bool {
        $this->hub->installEditor($this);
        return $this->isInstalled;
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