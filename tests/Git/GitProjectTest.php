<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Git;

use PHPUnit\Framework\TestCase;

class GitProjectTest extends TestCase {
    
    public function testClassExists(): void {
        $this->assertTrue(class_exists(GitProject::class));
    }
    
    public function testProjectExists(): void {
        $git = new GitProject('.');
        $this->assertTrue($git->exists);
        $this->assertEquals(realpath('.'), $git->path);
    }
    
    public function testProjectDoesNotExist(): void {
        $git = new GitProject(__DIR__);
        $this->assertFalse($git->exists);
    }
    
    public function testGetBranches(): void {
        $git = new GitProject('.');
        $branches = $git->getBranches();
        $this->assertContainsOnly('string', $branches);
    }
}
