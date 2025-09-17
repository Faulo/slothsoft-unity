<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\MLAgents;

use Slothsoft\Core\FileSystem;
use Slothsoft\Unity\UnityProject;

class MLTraining {
    
    const HYPERPARAMETER_EXTENSION = 'yaml';
    
    public static function determineTrainings(UnityProject $project, string $serverPath, string $todoPath, string $modelPath): iterable {
        assert(isset($project->packages[MLAGENTS_PACKAGE]), 'Project at ' . PATH_PROJECT . 'does not appear to have the ml-agents package installed!');
        
        $packageVersion = $project->packages[MLAGENTS_PACKAGE]['version'];
        
        if (! isset(MLContext::PACKAGE_MAPPING[$packageVersion])) {
            throw new \InvalidArgumentException("Package version $packageVersion can't be mapped to an ML-Agents version, help!");
        }
        $mlVersion = MLContext::PACKAGE_MAPPING[$packageVersion];
        $ml = new MLContext($serverPath, $mlVersion);
        
        if (! is_dir($todoPath)) {
            mkdir($todoPath, 0777, true);
        }
        if (! is_dir($modelPath)) {
            mkdir($modelPath, 0777, true);
        }
        
        assert(is_dir($todoPath), "Path $todoPath not found");
        assert(is_dir($modelPath), "Path $modelPath not found");
        
        $todoPath = realpath($todoPath);
        $modelPath = realpath($modelPath);
        
        foreach (FileSystem::scanDir($todoPath, FileSystem::SCANDIR_REALPATH) as $hyperFile) {
            if (pathinfo($hyperFile, PATHINFO_EXTENSION) === self::HYPERPARAMETER_EXTENSION) {
                $runId = pathinfo($hyperFile, PATHINFO_FILENAME);
                $modelDirectory = $modelPath . DIRECTORY_SEPARATOR . $runId;
                yield new MLTraining($ml, $runId, $hyperFile, $modelDirectory);
            }
        }
    }
    
    private $ml;
    
    public $runId;
    
    private $hyperFile;
    
    private $modelDirectory;
    
    public function __construct(MLContext $ml, string $runId, string $hyperFile, string $modelDirectory) {
        assert(is_file($hyperFile), "File $hyperFile not found");
        
        $this->ml = $ml;
        $this->runId = $runId;
        $this->hyperFile = $hyperFile;
        $this->modelDirectory = $modelDirectory;
    }
    
    public function init() {
        $this->ml->loadPath();
        $this->ml->loadLock();
        $this->loadModel();
        $this->loadHyper();
    }
    
    private function loadModel() {
        if (! is_dir($this->modelDirectory)) {
            mkdir($this->modelDirectory, 0777, true);
        }
        assert(is_dir($this->modelDirectory), "Path $this->modelDirectory not found");
    }
    
    private function loadHyper() {
        $this->args = new MLParameters();
        $this->args->registerArgument('num-envs', 1);
        $this->args->registerArgument('time-scale', 1.0);
        $this->args->registerArgument('cpu', true);
        $this->args->registerArgument('no-graphics', true);
        $this->args->registerArgument('quality-level', 0);
        $this->args->registerArgument('initialize-from', '');
        $this->args->registerArgument('base-port', 5005);
        $this->args->registerArgument('-tot-students', '');
        $this->args->registerArgument('-tot-lessons', '');
        $this->args->loadFromString(file_get_contents($this->hyperFile));
    }
    
    public function needsTraining(): bool {
        return is_dir($this->modelDirectory) ? FileSystem::scanDir($this->modelDirectory, FileSystem::SCANDIR_EXCLUDE_DIRS, '~\.o?nnx?$~') === [] : true;
    }
    
    public function train(string $workDirectory, string $executableFile): int {
        assert(file_exists($executableFile), "File $executableFile not found");
        $executableFile = realpath($executableFile);
        
        $args = [];
        $args[] = escapeshellarg($this->hyperFile);
        $args[] = escapeshellarg($this->runId);
        $args[] = escapeshellarg($executableFile);
        $args[] = $this->args->asShellArgument();
        
        $command = vsprintf('%s --run-id=%s --env=%s --force %s', $args);
        
        return $this->ml->learn($workDirectory, $command);
    }
}

