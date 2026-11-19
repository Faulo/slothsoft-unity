<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\DotNet;

use Slothsoft\Core\FileSystem;

/**
 * Converts dotnet format JSON reports into XML documents.
 *
 * @author Daniel Schulz
 * @since 2024-05-30
 */
final class FormatLog {
    
    private int $time;
    
    /** @var array<array-key, mixed> */
    private array $reports = [];
    
    /** @var list<string> */
    private static array $reportAttributes = [
        'FileName',
        'FilePath'
    ];
    
    /** @var list<string> */
    private static array $changeAttributes = [
        'LineNumber',
        'CharNumber',
        'DiagnosticId',
        'FormatDescription'
    ];
    
    public function __construct(string $path) {
        $resolved = realpath($path);
        if ($resolved === false or ! is_file($resolved)) {
            throw new \InvalidArgumentException("Missing format-report.json '$path'!");
        }
        
        $this->time = FileSystem::changetime($resolved)
            ?? throw new \InvalidArgumentException("Unable to read modification time of '$path'.");
        
        $json = file_get_contents($resolved);
        if ($json === false) {
            throw new \InvalidArgumentException("Unable to read format-report.json '$path'.");
        }
        $reports = json_decode($json, true);
        if (! is_array($reports)) {
            throw new \InvalidArgumentException("Malformed format-report.json '$path':" . PHP_EOL . $json);
        }
        
        $this->reports = $reports;
    }
    
    public function asDocument(): \DOMDocument {
        $document = new \DOMDocument();
        
        $rootNode = $document->createElement('Reports');
        
        $rootNode->setAttribute('Time', date(DATE_W3C, $this->time));
        
        foreach ($this->reports as $report) {
            $node = $document->createElement('Report');
            
            foreach (self::$reportAttributes as $attribute) {
                $node->setAttribute($attribute, (string) ($report[$attribute] ?? ''));
            }
            
            $changes = $report['FileChanges'] ?? [];
            foreach ($changes as $change) {
                $changeNode = $document->createElement('FileChange');
                
                foreach (self::$changeAttributes as $attribute) {
                    $changeNode->setAttribute($attribute, (string) ($change[$attribute] ?? ''));
                }
                
                $node->appendChild($changeNode);
            }
            
            $rootNode->appendChild($node);
        }
        
        $document->appendChild($rootNode);
        return $document;
    }
}

