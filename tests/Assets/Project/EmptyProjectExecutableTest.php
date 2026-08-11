<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Assets\Project;

use PHPUnit\Framework\TestCase;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\Module\Module;
use Slothsoft\Unity\Assets\ExecutableBase;
use Slothsoft\Unity\UnityHub;

final class EmptyProjectExecutableTest extends TestCase {
    
    /**
     * @runInSeparateProcess
     */
    public function testRegisteredAssetReturnsProcessResultForValidationError(): void {
        UnityHub::setThrowOnFailure(false);
        $url = FarahUrl::createFromReference('farah://slothsoft@unity/project/empty-project');
        
        $document = Module::resolveToDOMWriter($url)->toDocument();
        
        $this->assertSame('result', $document->documentElement->tagName);
        $this->assertSame(1, $document->getElementsByTagName('process')->length);
        $this->assertSame(1, $document->getElementsByTagName('error')->length);
        $this->assertSame('AssertParameter', $document->getElementsByTagName('error')->item(0)->getAttribute('type'));
    }
    
    public function testUsesExecutableBaseAndDedicatedParameters(): void {
        $this->assertTrue(is_subclass_of(EmptyProjectExecutable::class, ExecutableBase::class));
        $this->assertTrue(class_exists(EmptyProjectParameters::class));
    }
}
