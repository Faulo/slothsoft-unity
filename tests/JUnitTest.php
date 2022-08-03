<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;
use Slothsoft\Core\DOMHelper;
use Slothsoft\Core\FileSystem;

class JUnitTest extends TestCase {

    const SCHEMA_DOCUMENT = 'farah://slothsoft@unity/xsd/junit';

    const TEMPLATE_DOCUMENT = 'farah://slothsoft@unity/xsl/to-junit';

    const EXAMPLE_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . 'ValidTests';

    /**
     *
     * @dataProvider validTests
     */
    public function testTransformationIsValid(string $file): void {
        $dom = new DOMHelper();

        $data = $dom->transformToDocument($file, self::TEMPLATE_DOCUMENT);

        $result = $data->schemaValidate(self::SCHEMA_DOCUMENT);

        $this->assertTrue($result);
    }

    public function validTests(): iterable {
        foreach (FileSystem::scanDir(self::EXAMPLE_DIRECTORY, FileSystem::SCANDIR_REALPATH) as $file) {
            yield basename($file) => [
                $file
            ];
        }
    }
}

