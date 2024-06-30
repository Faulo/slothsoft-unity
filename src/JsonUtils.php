<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

class JsonUtils {

    public static function load(string $path): array {
        return json_decode(file_get_contents($path), true);
    }

    public static function save(string $path, array $data, int $tabLength = 4, $eot = ''): void {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($tabLength !== 4) {
            $json = str_replace('    ', str_pad('', $tabLength, ' '), $json);
        }

        $json .= $eot;

        file_put_contents($path, $json);
    }
}

