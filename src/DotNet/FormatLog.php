<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\DotNet;

use Slothsoft\Core\FileSystem;

class FormatLog {

    private int $time;

    private array $reports = [];

    private static array $reportAttributes = [
        'FileName',
        'FilePath'
    ];

    private static array $changeAttributes = [
        'LineNumber',
        'CharNumber',
        'DiagnosticId',
        'FormatDescription'
    ];

    public function __construct(string $path) {
        if (! realpath($path)) {
            throw new \InvalidArgumentException("Missing format-report.json '$path'!");
        }

        $path = realpath($path);

        $this->time = FileSystem::changetime($path);

        $json = file_get_contents($path);
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


