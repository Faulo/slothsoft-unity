<?php
namespace Slothsoft\Unity;

class JUnit {

    public static function formatDate(string $date): string {
        return date("Y-m-d\TH:i:s", strtotime($date));
    }
}

