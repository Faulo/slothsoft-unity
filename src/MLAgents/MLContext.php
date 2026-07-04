<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\MLAgents;

use Slothsoft\Core\CLI;

/**
 * Manages a legacy ML-Agents Python environment for Unity training runs.
 *
 * @author Daniel Schulz
 * @since 2020-12-25
 * @deprecated Legacy ML-Agents automation should not be used for new code.
 */
final class MLContext {
    
    const PACKAGE_MAPPING = [
        '1.7.0-preview' => '0.23',
        '1.6.0-preview' => '0.22',
        '1.5.0-preview' => '0.21',
        '1.4.0-preview' => '0.20',
        '1.3.0-preview' => '0.19',
        '1.2.0-preview' => '0.18',
        '1.1.0-preview' => '0.17',
        '1.0.3' => '0.16',
        '1.0.2' => '0.16'
    ];
    
    const ADDITIONAL_PACKAGES = [
        '0.16' => 'numpy~=1.18.0',
        '0.17' => 'numpy~=1.18.0',
        '0.18' => 'numpy~=1.18.0',
        '0.19' => 'numpy~=1.18.0',
        '0.20' => 'numpy~=1.18.0',
        '0.21' => 'numpy~=1.18.0',
        '0.22' => 'numpy~=1.18.0 torch===1.7.1+cpu torchvision===0.8.2+cpu torchaudio===0.7.2',
        '0.23' => 'torch===1.7.1+cpu torchvision===0.8.2+cpu torchaudio===0.7.2'
    ];
    
    const ADDITIONAL_REPOSITORIES = '-f https://download.pytorch.org/whl/torch_stable.html';
    
    const ROOT_PATH = 'python';
    
    const LOCK_EXTENSION = '.lock';
    
    const UPGRADE_PIP = 'python.exe -m pip install --upgrade pip';
    
    public static function determineContexts(string $serverPath): iterable {
        foreach (array_reverse(array_unique(self::PACKAGE_MAPPING)) as $mlVersion) {
            yield new MLContext($serverPath, $mlVersion);
        }
    }
    
    private string $workDirectory;
    
    public string $version;
    
    private string $pythonPath;
    
    private string $pythonLock;
    
    private string $scriptsPath;
    
    public function __construct(string $workDirectory, string $version) {
        assert(is_dir($workDirectory));
        $this->workDirectory = realpath($workDirectory);
        $this->version = $version;
        $this->pythonPath = $this->workDirectory . DIRECTORY_SEPARATOR . self::ROOT_PATH . DIRECTORY_SEPARATOR . $version;
        $this->pythonLock = $this->pythonPath . self::LOCK_EXTENSION;
        $this->scriptsPath = $this->pythonPath . DIRECTORY_SEPARATOR . 'Scripts';
    }
    
    public function lockExists(): bool {
        return is_file($this->pythonLock);
    }
    
    public function loadLock(): void {
        if ($this->lockExists()) {
            $this->install();
        } else {
            $this->update();
            $this->freeze();
        }
        assert($this->lockExists());
        $this->pythonLock = realpath($this->pythonLock);
    }
    
    public function pathExists(): bool {
        return is_dir($this->pythonPath);
    }
    
    public function loadPath(): void {
        if (! $this->pathExists()) {
            $this->setup();
        }
        assert($this->pathExists());
        $this->pythonPath = realpath($this->pythonPath);
        $this->pythonLock = $this->pythonPath . self::LOCK_EXTENSION;
        $this->scriptsPath = $this->pythonPath . DIRECTORY_SEPARATOR . 'Scripts';
    }
    
    public function setup(): void {
        CLI::execute(sprintf('virtualenv %s', escapeshellarg($this->pythonPath)));
    }
    
    public function install(): void {
        $this->executeIn($this->scriptsPath, self::UPGRADE_PIP);
        $this->executeIn($this->scriptsPath, sprintf('pip install --no-cache-dir --no-warn-script-location -r %s %s', escapeshellarg($this->pythonLock), self::ADDITIONAL_REPOSITORIES));
    }
    
    public function update(): void {
        $this->executeIn($this->scriptsPath, self::UPGRADE_PIP);
        $this->executeIn($this->scriptsPath, sprintf('pip install mlagents==%s.* tensorflow %s --no-cache-dir --upgrade --upgrade-strategy eager %s', $this->version, self::ADDITIONAL_PACKAGES[$this->version] ?? '', self::ADDITIONAL_REPOSITORIES));
    }
    
    public function freeze(): void {
        $this->executeIn($this->scriptsPath, sprintf('pip freeze > %s', escapeshellarg($this->pythonLock)));
    }
    
    public function learn(string $workDirectory, string $arguments): int {
        return $this->executeIn(dirname($this->workDirectory), "mlagents-learn $arguments");
    }
    
    private function executeIn(string $workDirectory, string $command): int {
        $command = $this->scriptsPath . DIRECTORY_SEPARATOR . $command;
        return CLI::execute($command, $workDirectory);
    }
}
