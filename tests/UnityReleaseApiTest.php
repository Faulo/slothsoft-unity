<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

final class UnityReleaseApiTest extends TestCase {

    public function testFindValidatesReleases(): void {
        $requests = [];
        $api = new UnityReleaseApi(static function (string $url) use (&$requests): string {
            $requests[] = $url;
            return json_encode([
                'total' => 3,
                'results' => [
                    [
                        'version' => '2019.4.41f2',
                        'shortRevision' => '6b23d448b533'
                    ],
                    [
                        'version' => '2020.3.49f1',
                        'shortRevision' => '18249dd5551b'
                    ],
                    [
                        'version' => 'invalid revision',
                        'shortRevision' => 'not-a-revision'
                    ]
                ]
            ], JSON_THROW_ON_ERROR);
        });

        $this->assertSame([
            '2019.4.41f2' => '6b23d448b533',
            '2020.3.49f1' => '18249dd5551b'
        ], $api->find('2019'));
        $this->assertCount(1, $requests);
        $this->assertStringContainsString('limit=25', $requests[0]);
        $this->assertStringContainsString('order=RELEASE_DATE_DESC', $requests[0]);
    }

    public function testFindTreatsInvalidResponseAsUnavailable(): void {
        $api = new UnityReleaseApi(static fn (): string => 'not json');

        $this->assertSame([], $api->find('2019.4.41f2'));
    }
}
