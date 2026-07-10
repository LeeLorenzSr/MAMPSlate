<?php
declare(strict_types=1);

/** Allows only known, HTTPS embed providers and gives templates a safe iframe URL. */
final class EmbedProvider
{
    private const PROVIDERS = [
        'youtube' => ['youtube.com', 'www.youtube.com', 'youtu.be'],
        'spotify' => ['open.spotify.com'],
        'apple_music' => ['music.apple.com'],
        'bandcamp' => ['bandcamp.com'],
        'soundcloud' => ['soundcloud.com'],
    ];

    public static function normalize(string $url, string $requestedProvider = ''): array
    {
        $url = ListingLinkNormalizer::normalizeUrl($url);
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        $provider = '';
        foreach (self::PROVIDERS as $name => $hosts) {
            foreach ($hosts as $allowedHost) {
                if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                    $provider = $name;
                    break 2;
                }
            }
        }
        if ($provider === '' || ($requestedProvider !== '' && $requestedProvider !== $provider)) {
            throw new InvalidArgumentException('Only allowlisted embed providers may be used.');
        }
        return ['provider' => $provider, 'source_url' => $url];
    }

    public static function iframeUrl(array $embed): ?string
    {
        $url = (string)($embed['source_url'] ?? '');
        $provider = (string)($embed['provider'] ?? '');
        if ($provider === 'youtube') {
            $parts = parse_url($url);
            $host = strtolower((string)($parts['host'] ?? ''));
            $id = $host === 'youtu.be' ? trim((string)($parts['path'] ?? ''), '/') : ($parts['query'] ?? '');
            if ($host !== 'youtu.be') {
                parse_str($id, $query);
                $id = (string)($query['v'] ?? '');
            }
            return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) ? 'https://www.youtube.com/embed/' . $id : null;
        }
        if ($provider === 'spotify' && str_starts_with($url, 'https://open.spotify.com/')) {
            return 'https://open.spotify.com/embed/' . ltrim(substr($url, strlen('https://open.spotify.com/')), '/');
        }
        return null;
    }
}
