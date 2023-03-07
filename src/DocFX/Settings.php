<?php
namespace Slothsoft\Unity\DocFX;

class Settings {

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
        $directory = new \RecursiveDirectoryIterator($path);
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

    public function __toString(): string {
        return json_encode($this->data, JSON_PRETTY_PRINT);
    }
}

