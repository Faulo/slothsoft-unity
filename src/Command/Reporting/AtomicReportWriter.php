<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Reporting;

use Symfony\Component\Filesystem\Path;
use Throwable;

/**
 * Publishes a complete report through a same-directory atomic rename.
 */
final readonly class AtomicReportWriter {
    
    public function write(string $path, string $xml): string {
        $target = $this->resolvePath($path);
        $directory = dirname($target);
        
        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new ReportWriteException(sprintf("Unable to create JUnit report directory '%s'.", $directory));
        }
        
        $temporary = @tempnam($directory, '.unity-junit-');
        if ($temporary === false) {
            throw new ReportWriteException(sprintf("Unable to create a temporary JUnit report in '%s'.", $directory));
        }
        
        $handle = null;
        try {
            $handle = @fopen($temporary, 'wb');
            if (! is_resource($handle)) {
                throw new ReportWriteException(sprintf("Unable to open temporary JUnit report '%s'.", $temporary));
            }
            
            $length = strlen($xml);
            $written = 0;
            while ($written < $length) {
                $bytes = @fwrite($handle, substr($xml, $written));
                if ($bytes === false || $bytes === 0) {
                    throw new ReportWriteException(sprintf("Unable to write temporary JUnit report '%s'.", $temporary));
                }
                $written += $bytes;
            }
            if (! @fflush($handle)) {
                throw new ReportWriteException(sprintf("Unable to flush temporary JUnit report '%s'.", $temporary));
            }
            if (function_exists('fsync') && ! @fsync($handle)) {
                throw new ReportWriteException(sprintf("Unable to synchronize temporary JUnit report '%s'.", $temporary));
            }
            if (! @fclose($handle)) {
                throw new ReportWriteException(sprintf("Unable to close temporary JUnit report '%s'.", $temporary));
            }
            $handle = null;
            
            if (! @rename($temporary, $target)) {
                throw new ReportWriteException(sprintf("Unable to atomically replace JUnit report '%s'.", $target));
            }
            $temporary = '';
            return $target;
        } finally {
            if (is_resource($handle)) {
                @fclose($handle);
            }
            if ($temporary !== '' && is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
    
    private function resolvePath(string $path): string {
        if ($path === '') {
            throw new ReportWriteException('The JUnit report path must not be empty.');
        }
        if ($path === '-') {
            throw new ReportWriteException("The stdout destination '-' is not a file path; generate XML with JUnitReporter::toXml() instead.");
        }
        
        $workingDirectory = getcwd();
        if ($workingDirectory === false) {
            throw new ReportWriteException('Unable to determine the process working directory.');
        }
        
        try {
            return Path::makeAbsolute($path, $workingDirectory);
        } catch (Throwable $error) {
            throw new ReportWriteException(sprintf("Invalid JUnit report path '%s'.", $path), 0, $error);
        }
    }
}
