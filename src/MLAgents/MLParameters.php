<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\MLAgents;

/**
 * Parses legacy ML-Agents training parameters and renders shell arguments.
 *
 * @author Daniel Schulz
 * @since 2020-12-25
 * @deprecated Legacy ML-Agents automation should not be used for new code.
 */
final class MLParameters {
    
    private array $data = [];
    
    public function registerArgument(string $key, mixed $defaultValue): void {
        assert(! isset($this->data[$key]));
        $this->data[$key] = $defaultValue;
    }
    
    public function loadFromString(string $config): void {
        $matches = null;
        preg_match_all('~#\s*([^\s]+?)\s*:\s*([^#]*?)[\r\n]~', $config, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[2];
            if (isset($this->data[$key])) {
                $value = json_decode($value);
                if ($value !== null) {
                    settype($value, gettype($this->data[$key]));
                    $this->data[$key] = $value;
                }
            }
        }
    }
    
    public function getArgument(string $key): mixed {
        assert(isset($this->data[$key]));
        return $this->data[$key];
    }
    
    public function asShellArgument(): string {
        $command = '';
        $unity = '';
        foreach ($this->data as $key => $value) {
            if ($key[0] === '-') {
                $value = (string) $value;
                if ($value !== '') {
                    $unity .= ' ' . escapeshellarg("$key=$value");
                }
            } else {
                switch (gettype($value)) {
                    case 'boolean':
                        if ($value) {
                            $command .= " --$key";
                        }
                        break;
                    case 'string':
                        if ($value !== '') {
                            $value = escapeshellarg((string) $value);
                            $command .= " --$key=$value";
                        }
                        break;
                    default:
                        $command .= " --$key=$value";
                }
            }
        }
        if ($unity !== '') {
            $command .= " --env-args $unity";
        }
        return $command;
    }
}
