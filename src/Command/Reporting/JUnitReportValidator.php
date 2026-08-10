<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Reporting;

use DOMDocument;
use LibXMLError;
use Throwable;

/**
 * Validates generated reports against the configured Farah JUnit schema.
 */
final readonly class JUnitReportValidator {
    
    public const SCHEMA_URI = 'farah://slothsoft@schema/schema/junit/1.0';
    
    public function __construct(private string $schemaUri = self::SCHEMA_URI) {
    }
    
    public function assertValid(DOMDocument $report): void {
        $previousInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        try {
            $valid = @$report->schemaValidate($this->schemaUri);
            $errors = libxml_get_errors();
        } catch (Throwable $error) {
            $errors = libxml_get_errors();
            throw new ReportValidationException($this->formatFailure($errors), 0, $error);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }
        
        if (! $valid) {
            throw new ReportValidationException($this->formatFailure($errors));
        }
    }
    
    /**
     * @param list<LibXMLError> $errors
     */
    private function formatFailure(array $errors): string {
        $message = sprintf("JUnit report failed validation against '%s'.", $this->schemaUri);
        if (! $errors) {
            return $message;
        }
        
        $details = [];
        foreach ($errors as $error) {
            $details[] = sprintf('line %d, column %d: %s', $error->line, $error->column, trim($error->message));
        }
        return $message . "\n" . implode("\n", $details);
    }
}
