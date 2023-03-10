<?php
namespace Slothsoft\Unity\DocFX;

use Spyc;

class Settings {

    const DEFAULT_INDEX = <<<EOT
    # Documentation
    
    Add a README.md to your repository to change this page.
    EOT;

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
            [
                'src' => [],
                'dest' => 'api'
            ]
        ],
        'build' => [
            'globalMetadata' => [
                '_appTitle' => 'App Title',
                '_appFooter' => 'App Footer',
                '_enableSearch' => true
            ],
            'content' => [
                [
                    'src' => '.',
                    'files' => [
                        '*.yml',
                        '*.md'
                    ],
                    'dest' => '.'
                ],
                [
                    'src' => 'api',
                    'files' => '*',
                    'dest' => 'api'
                ]
            ],
            'xref' => [
                'https://normanderwan.github.io/UnityXrefMaps/xrefmap.yml'
            ],
            'xrefService' => [
                'https://xref.docs.microsoft.com/query?uid={uid}'
            ],
            'dest' => 'html'
        ]
    ];

    private array $toc = [
        'api/' => 'Scripting API'
    ];

    public function __construct(string $path) {
        $this->path = realpath($path);

        $plugins = realpath($this->path . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'Plugins');
        if ($plugins) {
            $this->addDirectory('Assets', function (\SplFileInfo $file) use ($plugins): bool {
                return strpos($file->getRealPath(), $plugins) === false;
            });
        } else {
            $this->addDirectory('Assets');
        }

        $this->addDirectory('Packages');
    }

    private function addDirectory(string $directory, callable $include = null) {
        $directory = new \RecursiveDirectoryIterator($this->path . DIRECTORY_SEPARATOR . $directory);
        $iterator = new \RecursiveIteratorIterator($directory);

        $src = [
            'src' => '..',
            'files' => []
        ];
        foreach ($iterator as $file) {
            if ($include !== null and ! $include($file)) {
                continue;
            }
            if ($file->isFile() and $file->getExtension() === 'asmdef') {
                $src['files'][] = $file->getBasename('.asmdef') . '.csproj';
            }
        }
        $this->data['metadata'][0]['src'][] = $src;
    }

    public function export(string $target = null): string {
        if ($target === null) {
            $target = $this->path . DIRECTORY_SEPARATOR . 'Documentation~';
        }

        $this->ensureDirectory($target);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'docfx.json', $this->encode($this->data));
        file_put_contents($target . DIRECTORY_SEPARATOR . 'index.md', self::DEFAULT_INDEX);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'toc.yml', $this->encodeToC($this->toc));

        $configDir = $target . DIRECTORY_SEPARATOR . '.config';
        $this->ensureDirectory($configDir);
        file_put_contents($configDir . DIRECTORY_SEPARATOR . 'dotnet-tools.json', $this->encode($this->config));

        return realpath($target);
    }

    private function ensureDirectory(string $directory): void {
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    private function encode(array $data): string {
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    private function encodeToC(array $toc): string {
        $yaml = [];
        foreach ($toc as $key => $val) {
            $yaml[] = [
                'name' => $val,
                'href' => $key
            ];
        }
        return Spyc::YAMLDump($yaml);
    }

    public function __toString(): string {
        return $this->encode($this->data);
    }
}

