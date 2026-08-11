<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use Closure;
use JsonException;

/**
 * Resolves Unity editor releases through Unity's official Release API.
 */
final class UnityReleaseApi {

    private const RELEASES_URL = 'https://services.api.unity.com/unity/editor/release/v1/releases';

    private const PAGE_SIZE = 25;

    private Closure $request;

    public function __construct(?Closure $request = null) {
        $this->request = $request ?? static fn (string $url): string|false => @file_get_contents($url);
    }

    /**
     * @return array<string, string>
     */
    public function find(string $version): array {
        $releases = [];
        $url = self::RELEASES_URL . '?' . http_build_query([
            'limit' => self::PAGE_SIZE,
            'order' => 'RELEASE_DATE_DESC',
            'version' => $version
        ]);
        $response = ($this->request)($url);
        if (! is_string($response)) {
            return [];
        }

        try {
            $data = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
        if (! is_array($data) or ! isset($data['results']) or ! is_array($data['results'])) {
            return [];
        }

        foreach ($data['results'] as $release) {
            if (! is_array($release)) {
                continue;
            }
            $releaseVersion = $release['version'] ?? null;
            $shortRevision = $release['shortRevision'] ?? null;
            if (is_string($releaseVersion) and is_string($shortRevision) and preg_match('~^[a-f0-9]{12}$~D', $shortRevision)) {
                $releases[$releaseVersion] = $shortRevision;
            }
        }

        return $releases;
    }
}
