<?php
namespace Slothsoft\Unity;

use Throwable;

class DaemonServer {

    private $port;

    private $onMessage;

    public function __construct(int $port, callable $onMessage) {
        $this->port = $port;
        $this->onMessage = $onMessage;
    }

    public function run() {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($socket, '127.0.0.1', $this->port);
        socket_listen($socket);
        socket_set_nonblock($socket);

        while (true) {
            if (($client = socket_accept($socket)) !== false) {
                while (is_resource($client)) {
                    $data = (string) socket_read($client, 65535);
                    if ($data !== '') {
                        try {
                            foreach (($this->onMessage)($data) as $response) {
                                socket_write($client, $response);
                            }
                        } catch (Throwable $e) {
                            echo $e->getMessage() . PHP_EOL;
                        }
                        socket_write($client, "\0");
                        socket_close($client);
                    }
                }
            }
        }
    }
}

