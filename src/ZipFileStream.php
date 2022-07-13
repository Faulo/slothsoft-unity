<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use GuzzleHttp\Psr7\Stream;
use PhpZip\ZipFile;
use Psr\Http\Message\StreamInterface;
use Slothsoft\Core\StreamWrapper\StreamWrapperInterface;

class ZipFileStream extends ZipFile {

    public function outputAsStream(): StreamInterface {
        $handle = fopen('php://temp', StreamWrapperInterface::MODE_CREATE_READWRITE);
        $this->createZipWriter()->write($handle);
        rewind($handle);
        return new Stream($handle);
    }
}

