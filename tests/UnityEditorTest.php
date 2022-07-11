<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

/**
 * UnityEditorTest
 *
 * @see UnityEditor
 *
 * @todo auto-generated
 */
class UnityEditorTest extends TestCase {

    public function testClassExists(): void {
        $this->assertTrue(class_exists(UnityEditor::class), "Failed to load class 'Slothsoft\Unity\UnityEditor'!");
    }
}