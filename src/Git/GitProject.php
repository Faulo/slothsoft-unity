<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Git;

use Symfony\Component\Process\Process;

class GitProject {

    private const GIT_TIMEOUT = 3600;

    public $path;

    public $exists;

    public function __construct(string $path) {
        $this->path = $path;
        $this->initialize();
    }

    private function initialize() {
        $this->exists = is_dir($this->path . DIRECTORY_SEPARATOR . '.git');
        if ($this->exists) {
            $this->path = realpath($this->path);
            assert($this->path !== false);
        }
    }

    public function tryCloneFrom(string $url): bool {
        $this->execute(false, 'clone', $url, $this->path);
        $this->initialize();
        return $this->exists;
    }

    public function checkoutLatest(): void {
        $branch = $this->getBranches()[0];
        $this->gitCheckout($branch);
    }

    public function mergeLatest(): void {
        $branch = $this->getBranches()[0];
        $this->gitMerge("origin/$branch");
    }

    public function getBranches(): array {
        $output = $this->execute(true, 'branch', '--sort=-committerdate', '-r');
        $matches = null;
        if (preg_match_all('~^\s*origin/([^\s]+)$~m', $output, $matches, PREG_PATTERN_ORDER)) {
            return $matches[1];
        }
        throw new \RuntimeException('Failed to parse git branch output:' . PHP_EOL . $output);
    }

    private function execute(bool $includePath, string ...$args): string {
        if ($includePath) {
            array_unshift($args, $this->path);
            array_unshift($args, '-C');
        }
        array_unshift($args, 'git');
        $process = new Process($args, null, null, null, self::GIT_TIMEOUT);
        // echo PHP_EOL . $process->getCommandLine() . PHP_EOL;
        $process->run();
        $process->wait();
        return $process->getOutput();
    }

    public function gitPull(): void {
        $this->execute(true, 'pull', '-f');
    }

    public function gitAdd(string $pattern = '.'): void {
        $this->execute(true, 'add', $pattern);
    }

    public function gitCommit(string $message): void {
        $this->execute(true, 'commit', '-m', $message);
    }

    public function gitPush(): void {
        $this->execute(true, 'push');
    }

    public function gitPushBranch(string $branch): void {
        $this->execute(true, 'push', '--set-upstream', 'origin', $branch);
    }

    public function gitReset(): void {
        $this->execute(true, 'reset', '--hard');
    }

    public function gitClean(): void {
        $this->execute(true, 'clean', '-d', '-f');
    }

    public function gitMerge(string $name): void {
        $this->execute(true, 'merge', $name);
    }

    public function gitCheckout(string $branch): void {
        $this->execute(true, 'checkout', '-B', $branch, '--track', "origin/$branch");
    }

    public function gitBranch(string $name, bool $checkout = false): void {
        $this->execute(true, 'branch', $name);
        if ($checkout) {
            $this->checkout($name);
        }
    }
}
