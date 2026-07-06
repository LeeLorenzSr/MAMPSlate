<?php
declare(strict_types=1);

final class ListingLinkNormalizer
{
    /**
     * @param array<int, mixed> $links
     * @return array<int, array{label: string, url: string}>
     */
    public static function fromArray(array $links): array
    {
        $out = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $label = trim((string)($link['label'] ?? 'Link'));
            $url = self::normalizeUrl(trim((string)($link['url'] ?? '')));
            if ($url === '') {
                continue;
            }
            $out[] = [
                'label' => substr($label !== '' ? $label : 'Link', 0, 80),
                'url' => $url,
            ];
        }

        return $out;
    }

    /**
     * Parse admin textarea input: one URL per line, optionally `Label | URL`.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function fromText(string $input): array
    {
        $links = [];
        foreach (preg_split('/\r\n|\r|\n/', $input) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            if (count($parts) === 1) {
                $links[] = ['label' => 'Link', 'url' => $parts[0]];
                continue;
            }
            $links[] = ['label' => $parts[0] !== '' ? $parts[0] : 'Link', 'url' => $parts[1]];
        }

        return self::fromArray($links);
    }

    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Listing links must be valid http(s) URLs.');
        }

        return substr($url, 0, 2048);
    }
}
