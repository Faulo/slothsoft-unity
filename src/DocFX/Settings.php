<?php
namespace Slothsoft\Unity\DocFX;

class Settings {

    private string $path;

    private array $config = [
        'version' => 1,
        'isRoot' => true,
        'tools' => [
            'docfx' => [
                'version' => '2.62.1',
                'commands' => [
                    'docfx'
                ]
            ]
        ]
    ];

    private array $data = [
        'metadata' => [
            'src' => [],
            'dest' => 'api~'
        ],
        'build' => [
            'globalMetadata' => [
                '_appTitle' => 'App Title',
                '_appFooter' => 'App Footer',
                '_enableSearch' => ''
            ],
            'content' => [
                [
                    'src' => '.',
                    'files' => '*',
                    'dest' => '.'
                ],
                [
                    'src' => 'api~',
                    'files' => '*',
                    'dest' => 'api'
                ]
            ]
        ],
        'xref' => [
            'https://normanderwan.github.io/UnityXrefMaps/xrefmap.yml'
        ],
        'xrefService' => [
            'https://xref.docs.microsoft.com/query?uid={uid}'
        ],
        'dest' => 'html'
    ];

    public function __construct(string $path) {
        $this->path = realpath($path);

        $directory = new \RecursiveDirectoryIterator($this->path);
        $iterator = new \RecursiveIteratorIterator($directory);

        $src = [
            'src' => '..',
            'files' => []
        ];
        foreach ($iterator as $file) {
            if ($file->isFile() and $file->getExtension() === 'asmdef') {
                $src[] = $file->getBasename('.asmdef') . '.csproj';
            }
        }
        $this->data['metadata']['src'][] = $src;
    }

    public function export(string $target = null): void {
        if ($target === null) {
            $target = $this->path . DIRECTORY_SEPARATOR . 'Documentation~';
        }

        $this->ensureDirectory($target);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'docfx.json', $this->encode($this->data));
        file_put_contents($target . DIRECTORY_SEPARATOR . 'index.md', '');
        file_put_contents($target . DIRECTORY_SEPARATOR . 'toc.yml', '');

        $configDir = $target . DIRECTORY_SEPARATOR . '.config';
        $this->ensureDirectory($configDir);
        file_put_contents($configDir . DIRECTORY_SEPARATOR . 'dotnet-tools.json', $this->encode($this->config));
    }

    private function ensureDirectory(string $directory): void {
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    private function encode(array $data): string {
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    public function __toString(): string {
        return $this->encode($this->data);
    }
}

