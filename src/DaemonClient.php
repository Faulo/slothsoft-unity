<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

class DaemonClient {

    private $port;

    public function __construct(int $port) {
        $this->port = $port;
    }

    public function call(string $message): iterable {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($socket, '127.0.0.1', $this->port);
        socket_write($socket, $message);

        while (is_resource($socket)) {
            $data = (string) socket_read($socket, 65535);
            if ($data !== '') {
                if ($data === "\0") {
                    socket_close($socket);
                } else {
                    yield $data;
                }
            }
        }
    }
}

