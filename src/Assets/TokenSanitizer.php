<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Core\IO\Sanitizer\SanitizerInterface;

class TokenSanitizer implements SanitizerInterface {

    private $default;

    public function __construct(array $default = []) {
        $this->default = $default;
    }

    public function apply($value) {
        if (is_string($value)) {
            $result = [];
            foreach (preg_split('~\s+~', $value) as $val) {
                $val = trim($val);
                if (strlen($val)) {
                    $result[] = $val;
                }
            }
            return $result;
        }
        if (is_array($value)) {
            return $value;
        }
        return $this->getDefault();
    }

    public function getDefault() {
        return $this->default;
    }
}

