<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class UnityProjectTest extends TestCase {

    public function testClassExists() {
        $this->assertTrue(class_exists(UnityProject::class));
    }
}