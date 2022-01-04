<?php
namespace Slothsoft\Unity\Testing;

use Slothsoft\Core\CLI;

class GitProject {

    private $projectPath;

    public function __construct(string $projectPath) {
        assert(is_dir($projectPath), "Path $projectPath not found");

        $this->projectPath = realpath($projectPath);
    }

    public function add(string $flags = '.') {
        $this->execute("add $flags");
    }

    public function commit(string $message) {
        $this->execute(sprintf('commit -m %s', escapeshellarg($message)));
    }

    public function pull(string $flags = '-f') {
        $this->execute("pull $flags");
    }

    public function push(string $flags = '') {
        $this->execute("push $flags");
    }

    public function reset(string $flags = '--hard') {
        $this->execute("reset $flags");
    }

    public function clean(string $flags = '-d -f') {
        $this->execute("clean $flags");
    }

    public function branch(string $name, bool $checkout = false) {
        $this->execute("branch $name");
        if ($checkout) {
            $this->checkout($name);
        }
    }

    public function checkout(string $name) {
        $this->execute("checkout $name");
    }

    public function checkoutLatest() {
        $branch = $this->branches()[0];
        $this->checkout("-B $branch --track origin/$branch");
    }

    public function branches(): array {
        $gitArgs = 'branch --sort=-committerdate -r';
        $command = sprintf('git -C %s %s', escapeshellarg($this->projectPath), $gitArgs);
        $output = [];
        exec($command, $output);
        $ret = [];
        foreach ($output as $line) {
            $match = [];
            if (preg_match('~^\s*origin/([^\s]+)$~', $line, $match)) {
                $ret[] = $match[1];
            }
        }
        return $ret;
    }

    public function execute($gitArgs) {
        $command = sprintf('git -C %s %s', escapeshellarg($this->projectPath), $gitArgs);
        CLI::execute($command);
    }
}

