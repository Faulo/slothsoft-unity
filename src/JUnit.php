<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

/**
 * Formats dates for JUnit-compatible XML reports.
 *
 * @author Daniel Schulz
 * @since 2022-07-20
 */
final class JUnit {
    
    public static function formatDate(string $date): string {
        return date("Y-m-d\TH:i:s", strtotime($date));
    }
}
