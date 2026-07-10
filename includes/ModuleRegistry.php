<?php
declare(strict_types=1);

/** Loads optional, local module manifests without changing the core boot path. */
final class ModuleRegistry
{
    private array $modules = [];

    public function __construct(private string $modulesRoot)
    {
        foreach (glob($modulesRoot . '/*/module.php') ?: [] as $manifestPath) {
            $manifest = require $manifestPath;
            if (!is_array($manifest) || empty($manifest['name'])) {
                error_log('Ignoring invalid MAMPSlate module manifest: ' . basename(dirname($manifestPath)));
                continue;
            }
            $key = strtolower((string)$manifest['name']);
            if (!preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $key)) {
                error_log('Ignoring module with invalid name: ' . $key);
                continue;
            }
            $manifest['path'] = dirname($manifestPath);
            $this->modules[$key] = $manifest;
        }
    }

    public function all(): array
    {
        return $this->modules;
    }

    public function hasEntityType(string $entityType): bool
    {
        foreach ($this->modules as $module) {
            if (in_array($entityType, $module['entity_types'] ?? [], true)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int,array{loc:string,lastmod?:string}> */
    public function sitemapEntries(string $baseUrl): array
    {
        $entries = [];
        foreach ($this->modules as $module) {
            $callback = $module['sitemap_entries'] ?? null;
            if (is_callable($callback)) {
                try {
                    foreach ($callback($baseUrl) as $entry) {
                        if (is_array($entry) && isset($entry['loc'])) {
                            $entries[] = $entry;
                        }
                    }
                } catch (Throwable $e) {
                    error_log('Module sitemap failed for ' . $module['name'] . ': ' . $e->getMessage());
                }
            }
        }
        return $entries;
    }
}
