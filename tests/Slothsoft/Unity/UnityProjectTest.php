<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Error\Warning;

class UnityProjectTest extends TestCase {

    public function testDefaultTotalTimeout() {
        $this->assertTrue(class_exists(UnityProject::class));
    }
}