<?php
namespace Slothsoft\Unity\DocFX;

use Symfony\Component\Filesystem\Filesystem;
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

    const DIR_API = 'api';

    const DIR_DOCS = 'docs';

    private Filesystem $fileSystem;

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
                '_enableSearch' => true,
                '_enableNewTab' => true,
                '_disableContribution' => true
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
                    'src' => self::DIR_API,
                    'files' => '*',
                    'dest' => self::DIR_API
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
        self::DIR_API . '/' => 'API'
    ];

    private ?\SplFileInfo $docs = null;

    private ?\SplFileInfo $readme = null;

    private ?\SplFileInfo $changelog = null;

    private ?\SplFileInfo $license = null;

    private array $markdowns = [];

    private array $projects = [];

    public function __construct(string $path) {
        $this->fileSystem = new Filesystem();
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

        $this->addRootDirectory();

        if ($this->docs) {
            $this->toc[self::DIR_DOCS . '/'] = 'Docs';
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
            'dest' => self::DIR_API
        ];
    }

    private function addDirectory(string $directory, callable $include = null): void {
        $directory = new \RecursiveDirectoryIterator($this->path . DIRECTORY_SEPARATOR . $directory);
        $iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $file) {
            if ($include !== null and ! $include($file)) {
                continue;
            }
            $this->processFile($file);
        }
    }

    private function addRootDirectory(): void {
        $directory = new \DirectoryIterator($this->path);
        foreach ($directory as $file) {
            if ($file->isDot()) {
                continue;
            }
            $this->processFile(new \SplFileInfo($file->getRealPath()));
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
        }
        if ($file->isDir()) {
            switch ($file->getFilename()) {
                case 'Documentation~':
                case 'Documentation':
                    $this->docs = $file;
                    break;
            }
        }
    }

    private function addManual(): void {
        $this->data['build']['content'][] = [
            'src' => 'docs',
            'files' => [
                '**/*.yml',
                '**/*.md'
            ],
            'dest' => 'docs'
        ];
        $this->data['build']['resource'] = [
            [
                'src' => 'docs',
                'files' => [
                    '**/*.png',
                    '**/*.jpg',
                    '**/*.svg',
                    '**/*.webp'
                ],
                'dest' => 'docs'
            ]
        ];
    }

    public function export(string $target = null): string {
        if ($target === null) {
            $target = $this->path . DIRECTORY_SEPARATOR . '.Documentation';
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

        $docsDir = $target . DIRECTORY_SEPARATOR . self::DIR_DOCS;
        $hasOwnToC = file_exists($docsDir . DIRECTORY_SEPARATOR . self::FILE_TOC);
        if ($this->docs and ($hasOwnToC or $toc = $this->createToc($this->docs))) {
            $this->fileSystem->mirror($this->docs->getRealPath(), $docsDir);
            if (! $hasOwnToC) {
                file_put_contents($docsDir . DIRECTORY_SEPARATOR . self::FILE_TOC, $this->encodeToC($toc));
            }
        }

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

    private function createToC(\SplFileInfo $dir): array {
        $toc = [];
        $directory = new \DirectoryIterator($dir->getRealPath());
        foreach ($directory as $file) {
            if ($file->isFile()) {
                $ext = $file->getExtension();
                switch ($ext) {
                    case 'yml':
                    case 'md':
                        $toc[$file->getFilename()] = $file->getBasename(".$ext");
                        break;
                }
            }
        }
        return $toc;
    }

    public function __toString(): string {
        return $this->encode($this->data);
    }
}

