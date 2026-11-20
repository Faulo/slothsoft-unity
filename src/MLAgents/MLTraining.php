<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\MLAgents;

use InvalidArgumentException;
use LogicException;
use Slothsoft\Core\FileSystem;
use Slothsoft\Unity\UnityProject;

/**
 * Coordinates legacy ML-Agents training runs for Unity projects.
 *
 * @author Daniel Schulz
 * @since 2020-12-25
 * @deprecated Legacy ML-Agents automation should not be used for new code.
 */
final class MLTraining {
    
    private const MLAGENTS_PACKAGE = 'com.unity.ml-agents';
    
    const HYPERPARAMETER_EXTENSION = 'yaml';
    
    /** @return iterable<self> */
    public static function determineTrainings(UnityProject $project, string $serverPath, string $todoPath, string $modelPath): iterable {
        $packages = $project->getPackages();
        $package = $packages[self::MLAGENTS_PACKAGE] ?? null;
        $packageVersion = is_array($package) ? ($package['version'] ?? null) : null;
        if (! is_string($packageVersion) || $packageVersion === '') {
            throw new InvalidArgumentException("Project at '{$project->getProjectPath()}' does not have a valid ML-Agents package version.");
        }
        
        if (! isset(MLContext::PACKAGE_MAPPING[$packageVersion])) {
            throw new InvalidArgumentException("Package version $packageVersion can't be mapped to an ML-Agents version, help!");
        }
        $mlVersion = MLContext::PACKAGE_MAPPING[$packageVersion];
        $ml = new MLContext($serverPath, $mlVersion);
        
        if (! is_dir($todoPath)) {
            mkdir($todoPath, 0777, true);
        }
        if (! is_dir($modelPath)) {
            mkdir($modelPath, 0777, true);
        }
        
        $todoDirectory = realpath($todoPath);
        $modelRoot = realpath($modelPath);
        if ($todoDirectory === false or ! is_dir($todoDirectory) or $modelRoot === false or ! is_dir($modelRoot)) {
            throw new InvalidArgumentException("Unable to resolve training directories '$todoPath' and '$modelPath'.");
        }
        
        foreach (FileSystem::scanDir($todoDirectory, FileSystem::SCANDIR_REALPATH) as $hyperFile) {
            if (is_string($hyperFile) and pathinfo($hyperFile, PATHINFO_EXTENSION) === self::HYPERPARAMETER_EXTENSION) {
                $runId = pathinfo($hyperFile, PATHINFO_FILENAME);
                $modelDirectory = $modelRoot . DIRECTORY_SEPARATOR . $runId;
                yield new MLTraining($ml, $runId, $hyperFile, $modelDirectory);
            }
        }
    }
    
    private MLContext $ml;
    
    public string $runId;
    
    private string $hyperFile;
    
    private string $modelDirectory;
    
    private ?MLParameters $args = null;
    
    public function __construct(MLContext $ml, string $runId, string $hyperFile, string $modelDirectory) {
        assert(is_file($hyperFile), "File $hyperFile not found");
        
        $this->ml = $ml;
        $this->runId = $runId;
        $this->hyperFile = $hyperFile;
        $this->modelDirectory = $modelDirectory;
    }
    
    public function init(): void {
        $this->ml->loadPath();
        $this->ml->loadLock();
        $this->loadModel();
        $this->loadHyper();
    }
    
    private function loadModel(): void {
        if (! is_dir($this->modelDirectory)) {
            mkdir($this->modelDirectory, 0777, true);
        }
        assert(is_dir($this->modelDirectory), "Path $this->modelDirectory not found");
    }
    
    private function loadHyper(): void {
        $config = file_get_contents($this->hyperFile);
        if ($config === false) {
            throw new \RuntimeException("Unable to read training config '$this->hyperFile'.");
        }
        $args = new MLParameters();
        $args->registerArgument('num-envs', 1);
        $args->registerArgument('time-scale', 1.0);
        $args->registerArgument('cpu', true);
        $args->registerArgument('no-graphics', true);
        $args->registerArgument('quality-level', 0);
        $args->registerArgument('initialize-from', '');
        $args->registerArgument('base-port', 5005);
        $args->registerArgument('-tot-students', '');
        $args->registerArgument('-tot-lessons', '');
        $args->loadFromString($config);
        $this->args = $args;
    }
    
    public function needsTraining(): bool {
        return ! is_dir($this->modelDirectory) || FileSystem::scanDir($this->modelDirectory, FileSystem::SCANDIR_EXCLUDE_DIRS, '~\.o?nnx?$~') === [];
    }
    
    /** Runs training after init() loads the configuration. */
    public function train(string $workDirectory, string $executableFile): int {
        if ($this->args === null) {
            throw new LogicException('Call init() before starting ML-Agents training.');
        }
        $executablePath = realpath($executableFile);
        if ($executablePath === false) {
            throw new InvalidArgumentException("Executable '$executableFile' not found.");
        }
        
        $args = [];
        $args[] = escapeshellarg($this->hyperFile);
        $args[] = escapeshellarg($this->runId);
        $args[] = escapeshellarg($executablePath);
        $args[] = $this->args->asShellArgument();
        
        $command = vsprintf('%s --run-id=%s --env=%s --force %s', $args);
        
        return $this->ml->learn($workDirectory, $command);
    }
}
