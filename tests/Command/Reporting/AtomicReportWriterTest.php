<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Reporting;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

final class AtomicReportWriterTest extends TestCase {
    
    private AtomicReportWriter $writer;
    
    private string $directory;
    
    protected function setUp(): void {
        $this->writer = new AtomicReportWriter();
        $this->directory = temp_dir(__METHOD__);
    }
    
    public function testCreatesNestedDirectoriesAndResolvesRelativePathsFromWorkingDirectory(): void {
        $workingDirectory = getcwd();
        $this->assertNotFalse($workingDirectory);
        $relativeDirectory = Path::makeRelative($this->directory, $workingDirectory);
        $relativePath = $relativeDirectory . '/nested/report.xml';
        
        $actual = $this->writer->write($relativePath, '<?xml version="1.0" encoding="UTF-8"?><testsuites />');
        
        $expected = Path::makeAbsolute($relativePath, $workingDirectory);
        $this->assertSame($expected, $actual);
        $this->assertFileExists($expected);
        $this->assertSame('<?xml version="1.0" encoding="UTF-8"?><testsuites />', file_get_contents($expected));
        $this->assertSame([], glob(dirname($expected) . DIRECTORY_SEPARATOR . '.unity-junit-*'));
    }
    
    public function testAtomicallyReplacesExistingReport(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'report.xml';
        file_put_contents($path, 'old report');
        
        $actual = $this->writer->write($path, 'new report');
        
        $this->assertSame(Path::canonicalize($path), $actual);
        $this->assertSame('new report', file_get_contents($path));
        $this->assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '.unity-junit-*'));
    }
    
    public function testFailedReplacementLeavesNoTemporaryFile(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'report.xml';
        mkdir($path);
        
        try {
            $this->writer->write($path, 'report');
            $this->fail('Replacing a directory with a report should fail.');
        } catch (ReportWriteException $error) {
            $this->assertStringContainsString('atomically replace', $error->getMessage());
            $this->assertDirectoryExists($path);
            $this->assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '.unity-junit-*'));
        }
    }
    
    public function testRejectsStdoutSentinelAsFilePath(): void {
        $this->expectException(ReportWriteException::class);
        $this->expectExceptionMessage("stdout destination '-'");
        
        $this->writer->write('-', '<testsuites />');
    }
}
