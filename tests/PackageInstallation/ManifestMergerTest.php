<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\PackageInstallation;

use PHPUnit\Framework\TestCase;

final class ManifestMergerTest extends TestCase {
    private ManifestMerger $merger;

    protected function setUp(): void {
        $this->merger = new ManifestMerger();
    }

    public function testMergesNestedObjectsAndLetsIncomingConflictsWin(): void {
        $existing = [
            'name' => 'existing',
            'settings' => [
                'preserved' => true,
                'conflict' => [
                    'old' => true
                ],
                'nested' => [
                    'left' => 1,
                    'value' => 'old'
                ]
            ]
        ];
        $incoming = [
            'name' => 'incoming',
            'settings' => [
                'conflict' => 'replacement',
                'nested' => [
                    'right' => 2,
                    'value' => 'new'
                ]
            ]
        ];

        $actual = $this->merger->merge($this->asObject($existing), $this->asObject($incoming));

        $this->assertSame([
            'name' => 'incoming',
            'settings' => [
                'preserved' => true,
                'conflict' => 'replacement',
                'nested' => [
                    'left' => 1,
                    'value' => 'new',
                    'right' => 2
                ]
            ]
        ], $this->asArray($actual));
    }

    public function testMergesDependenciesByNameWithCompleteIncomingValuesWinning(): void {
        $existing = [
            'dependencies' => [
                'com.example.keep' => '1.0.0',
                'com.example.replace' => [
                    'version' => '1.0.0',
                    'source' => 'existing'
                ]
            ]
        ];
        $incoming = [
            'dependencies' => [
                'com.example.replace' => [
                    'version' => '2.0.0'
                ],
                'com.example.add' => '3.0.0'
            ]
        ];

        $actual = $this->merger->merge($this->asObject($existing), $this->asObject($incoming));

        $this->assertSame([
            'com.example.keep' => '1.0.0',
            'com.example.replace' => [
                'version' => '2.0.0'
            ],
            'com.example.add' => '3.0.0'
        ], $this->asArray($actual->dependencies));
    }

    public function testEmptyIncomingDependencyObjectPreservesExistingDependencies(): void {
        $actual = $this->merger->merge($this->asObject([
            'dependencies' => [
                'com.example.keep' => '1.0.0'
            ]
        ]), (object) [
            'dependencies' => (object) []
        ]);

        $this->assertSame([
            'com.example.keep' => '1.0.0'
        ], $this->asArray($actual->dependencies));
    }

    public function testAppendsGeneralListsAndRemovesOnlyStrictDuplicates(): void {
        $existing = [
            'values' => [
                1,
                '1',
                [
                    'key' => 'value'
                ],
                null
            ]
        ];
        $incoming = [
            'values' => [
                '1',
                1.0,
                [
                    'key' => 'value'
                ],
                false,
                null,
                false
            ]
        ];

        $actual = $this->merger->merge($this->asObject($existing), $this->asObject($incoming));

        $this->assertSame([
            1,
            '1',
            [
                'key' => 'value'
            ],
            null,
            1.0,
            false
        ], $this->asArrayValue($actual->values));
    }

    public function testNormalizesDuplicatesInListsThatOnlyExistOnOneSide(): void {
        $actual = $this->merger->merge($this->asObject([]), $this->asObject([
            'values' => [
                'first',
                'first',
                'second'
            ]
        ]));

        $this->assertSame([
            'first',
            'second'
        ], $actual->values);
    }

    public function testMergesScopedRegistriesByUrlWithIncomingFieldsAndUnionedScopes(): void {
        $existing = [
            'scopedRegistries' => [
                [
                    'name' => 'Existing Name',
                    'url' => 'https://packages.example.test',
                    'scopes' => [
                        'com.example',
                        'com.shared'
                    ],
                    'metadata' => [
                        'existing' => true
                    ],
                    'preserved' => true
                ],
                [
                    'name' => 'Untouched',
                    'url' => 'https://untouched.example.test',
                    'scopes' => [
                        'org.untouched'
                    ]
                ]
            ]
        ];
        $incoming = [
            'scopedRegistries' => [
                [
                    'name' => 'Incoming Name',
                    'url' => 'https://packages.example.test',
                    'scopes' => [
                        'com.shared',
                        'org.incoming',
                        'org.incoming'
                    ],
                    'metadata' => [
                        'incoming' => true
                    ]
                ],
                [
                    'name' => 'Added',
                    'url' => 'https://added.example.test',
                    'scopes' => [
                        'org.added'
                    ]
                ]
            ]
        ];

        $actual = $this->merger->merge($this->asObject($existing), $this->asObject($incoming));

        $this->assertSame([
            [
                'name' => 'Incoming Name',
                'url' => 'https://packages.example.test',
                'scopes' => [
                    'com.example',
                    'com.shared',
                    'org.incoming'
                ],
                'metadata' => [
                    'incoming' => true
                ],
                'preserved' => true
            ],
            [
                'name' => 'Untouched',
                'url' => 'https://untouched.example.test',
                'scopes' => [
                    'org.untouched'
                ]
            ],
            [
                'name' => 'Added',
                'url' => 'https://added.example.test',
                'scopes' => [
                    'org.added'
                ]
            ]
        ], $this->asArrayValue($actual->scopedRegistries));
    }

    public function testPreservesAndStrictlyDeduplicatesRegistryEntriesWithoutUrls(): void {
        $entry = [
            'name' => 'No URL'
        ];

        $actual = $this->merger->merge($this->asObject([
            'scopedRegistries' => [$entry]
        ]), $this->asObject([
            'scopedRegistries' => [
                $entry,
                [
                    'name' => 'Another No URL'
                ]
            ]
        ]));

        $this->assertSame([
            $entry,
            [
                'name' => 'Another No URL'
            ]
        ], $this->asArrayValue($actual->scopedRegistries));
    }

    public function testProducesTheSameResultForRepeatedMerges(): void {
        $existing = [
            'dependencies' => [
                'com.example.package' => '1.0.0'
            ],
            'values' => [
                'a'
            ]
        ];
        $incoming = [
            'dependencies' => [
                'com.example.package' => '2.0.0'
            ],
            'values' => [
                'a',
                'b'
            ]
        ];

        $incoming = $this->asObject($incoming);
        $once = $this->merger->merge($this->asObject($existing), $incoming);
        $twice = $this->merger->merge($once, $incoming);

        $this->assertEquals($once, $twice);
    }

    private function asObject(array $value): \stdClass {
        $object = json_decode(json_encode((object) $value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $this->assertInstanceOf(\stdClass::class, $object);

        return $object;
    }

    private function asArray(\stdClass $value): array {
        return $this->asArrayValue($value);
    }

    private function asArrayValue(mixed $value): array {
        $array = json_decode(json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($array);

        return $array;
    }
}
