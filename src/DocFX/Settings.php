<?php
namespace Slothsoft\Unity\DocFX;

use Spyc;

class Settings {

    const DEFAULT_INDEX = <<<EOT
    # Documentation
    
    Add a README.md to your repository to change this page.
    EOT;

    const FILE_INDEX = 'index.md';

    const FILE_README = 'README.md';

    const FILE_CHANGELOG = 'CHANGELOG.md';

    const FILE_LICENSE = 'LICENSE.md';

    const FILE_TOC = 'toc.yml';

    const FILE_DOCFX = 'docfx.json';

    const SPECIAL_MDS = [
        self::FILE_README,
        self::FILE_CHANGELOG,
        self::FILE_LICENSE
    ];

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
        'metadata' => [],
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

    private ?\SplFileInfo $documentation = null;

    private ?\SplFileInfo $readme = null;

    private ?\SplFileInfo $changelog = null;

    private ?\SplFileInfo $license = null;

    private array $markdowns = [];

    private array $projects = [];

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

        if ($this->documentation) {
            $this->addManual();
        }

        foreach ($this->markdowns as $file) {
            switch ($file->getFilename()) {
                case self::FILE_README:
                    $this->readme = $file;
                    break;
                case self::FILE_CHANGELOG:
                    $this->changelog = $file;
                    break;
                case self::FILE_LICENSE:
                    $this->license = $file;
                    break;
            }
        }

        if ($this->changelog) {
            $this->toc[self::FILE_CHANGELOG] = 'Changelog';
        }

        if ($this->license) {
            $this->toc[self::FILE_LICENSE] = 'License';
        }

        $this->data['metadata'][] = [
            'src' => [
                [
                    'src' => '..',
                    'files' => array_keys($this->projects)
                ]
            ],
            'dest' => 'api'
        ];
    }

    private function addDirectory(string $directory, callable $include = null) {
        $directory = new \RecursiveDirectoryIterator($this->path . DIRECTORY_SEPARATOR . $directory);
        $iterator = new \RecursiveIteratorIterator($directory);

        foreach ($iterator as $file) {
            if ($include !== null and ! $include($file)) {
                continue;
            }
            $this->processFile($file);
        }
    }

    private function processFile(\SplFileInfo $file): void {
        if ($file->isFile()) {
            switch ($file->getExtension()) {
                case 'asmdef':
                    $this->projects[$file->getBasename('.asmdef') . '.csproj'] = null;
                    break;
                case 'md':
                    $this->markdowns[] = $file;
                    break;
            }
        } else {
            switch ($file->getFilename()) {
                case 'Documentation~':
                case 'Documentation':
                    $this->documentation = $file;
                    break;
            }
        }
    }

    private function addManual() {}

    public function export(string $target = null): string {
        if ($target === null) {
            $target = $this->path . DIRECTORY_SEPARATOR . 'Documentation~';
        }

        $this->ensureDirectory($target);
        file_put_contents($target . DIRECTORY_SEPARATOR . self::FILE_DOCFX, $this->encode($this->data));
        if ($this->readme) {
            copy($this->readme->getRealpath(), $target . DIRECTORY_SEPARATOR . self::FILE_INDEX);
        } else {
            file_put_contents($target . DIRECTORY_SEPARATOR . self::FILE_INDEX, self::DEFAULT_INDEX);
        }
        if ($this->changelog) {
            copy($this->changelog->getRealpath(), $target . DIRECTORY_SEPARATOR . self::FILE_CHANGELOG);
        }
        if ($this->license) {
            copy($this->license->getRealpath(), $target . DIRECTORY_SEPARATOR . self::FILE_LICENSE);
        }
        file_put_contents($target . DIRECTORY_SEPARATOR . self::FILE_TOC, $this->encodeToC($this->toc));

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

