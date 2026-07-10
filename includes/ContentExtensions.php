<?php
declare(strict_types=1);

/** Helpers shared by content editors, public rendering, and the API adapter. */
function contentExtensionInput(array $input): array
{
    $customFields = is_array($input['custom_fields'] ?? null) ? $input['custom_fields'] : [];
    $termIds = is_array($input['term_ids'] ?? null) ? array_map('intval', $input['term_ids']) : [];
    return [
        'custom_fields' => $customFields,
        'term_ids' => $termIds,
        'links' => contentExtensionLines((string)($input['extension_links'] ?? ''), ['label', 'url', 'service_type', 'rel_attributes']),
        'embeds' => contentExtensionLines((string)($input['extension_embeds'] ?? ''), ['source_url', 'provider', 'title']),
        'relationships' => contentExtensionLines((string)($input['extension_relationships'] ?? ''), ['target', 'relationship_type', 'label']),
    ];
}

function contentWorkflowStatuses(): array
{
    return ['draft', 'submitted', 'needs_changes', 'scheduled', 'published', 'archived', 'rejected'];
}

/** @return array{status:string,published_at:?string} */
function contentScheduleForStatus(string $status, ?string $input, ?string $existing): array
{
    if (!in_array($status, contentWorkflowStatuses(), true)) {
        $status = 'draft';
    }
    $publishedAt = $existing;
    $input = trim((string)$input);
    if ($input !== '') {
        $date = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $input) ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $input);
        if (!$date) {
            throw new InvalidArgumentException('Publish date must be a valid local date and time.');
        }
        $publishedAt = $date->format('Y-m-d H:i:s');
    }
    $now = time();
    if ($status === 'published') {
        if ($publishedAt === null || strtotime($publishedAt) <= $now) {
            $publishedAt = date('Y-m-d H:i:s');
        } else {
            $status = 'scheduled';
        }
    }
    if ($status === 'scheduled' && ($publishedAt === null || strtotime($publishedAt) <= $now)) {
        throw new InvalidArgumentException('Scheduled content needs a future publish date.');
    }
    return ['status' => $status, 'published_at' => $publishedAt];
}

function contentIsPublic(array $entity): bool
{
    return in_array((string)($entity['status'] ?? ''), ['published', 'scheduled'], true)
        && !empty($entity['published_at'])
        && strtotime((string)$entity['published_at']) <= time();
}

/** @return array<int,array<string,string>> */
function contentExtensionLines(string $text, array $columns): array
{
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
        $parts = array_map('trim', explode('|', $line));
        if ($parts === [] || $parts[0] === '') {
            continue;
        }
        $row = [];
        foreach ($columns as $index => $column) {
            $row[$column] = $parts[$index] ?? '';
        }
        if (isset($row['target'])) {
            [$type, $id] = array_pad(explode(':', $row['target'], 2), 2, '');
            $row['target_type'] = trim($type);
            $row['target_id'] = trim($id);
            unset($row['target']);
        }
        $rows[] = $row;
    }
    return $rows;
}

function saveContentExtensions(string $entityType, int $entityId, array $input, ?int $actorId = null): void
{
    $data = contentExtensionInput($input);
    $extensions = $GLOBALS['contentExtensions'];
    $extensions->saveFieldValues($entityType, $entityId, $data['custom_fields']);
    $extensions->saveTerms($entityType, $entityId, $data['term_ids']);
    $extensions->saveLinks($entityType, $entityId, $data['links']);
    $extensions->saveEmbeds($entityType, $entityId, $data['embeds']);
    $extensions->saveRelationships($entityType, $entityId, $data['relationships'], $actorId);
}

function renderContentExtensionEditor(string $entityType, int $entityId): void
{
    if ($entityId < 1) {
        echo '<section class="panel"><h2>Extensions</h2><p class="muted">Save this item first to add reusable fields, terms, links, embeds, and relationships.</p></section>';
        return;
    }
    /** @var ContentExtensionRepository $extensions */
    $extensions = $GLOBALS['contentExtensions'];
    /** @var TaxonomyRepository $taxonomies */
    $taxonomies = $GLOBALS['taxonomies'];
    $values = [];
    foreach ($extensions->fieldValues($entityType, $entityId) as $field) {
        $values[$field['key']] = $field['value'];
    }
    $selectedTerms = array_flip(array_map(fn($term) => (int)$term['id'], $extensions->terms($entityType, $entityId)));
    $links = array_map(
        fn($link) => implode(' | ', [$link['label'], $link['url'], $link['service_type'], $link['rel_attributes']]),
        $extensions->links($entityType, $entityId)
    );
    $embeds = array_map(
        fn($embed) => implode(' | ', [$embed['source_url'], $embed['provider'], $embed['title']]),
        $extensions->embeds($entityType, $entityId)
    );
    $relationships = array_map(
        fn($relation) => implode(' | ', [$relation['target_type'] . ':' . $relation['target_id'], $relation['relationship_type'], $relation['label']]),
        $extensions->relationships($entityType, $entityId)
    );
    ?>
    <section class="panel">
        <h2>Custom fields</h2>
        <?php $definitions = $extensions->definitions($entityType); ?>
        <?php if ($definitions === []): ?>
            <p class="muted">No fields have been defined for this content type. <a href="/admin/content-model">Define fields</a>.</p>
        <?php endif; ?>
        <?php foreach ($definitions as $definition): ?>
            <?php $key = $definition['field_key']; $value = $values[$key] ?? ''; ?>
            <label><?= e($definition['label']) ?><?= !empty($definition['is_required']) ? ' *' : '' ?>
                <?php if ($definition['value_type'] === 'textarea'): ?>
                    <textarea name="custom_fields[<?= e($key) ?>]" rows="3"><?= e((string)$value) ?></textarea>
                <?php elseif ($definition['value_type'] === 'select'): ?>
                    <select name="custom_fields[<?= e($key) ?>]">
                        <option value="">Select…</option>
                        <?php foreach ($definition['options'] as $option): ?>
                            <option value="<?= e($option) ?>" <?= $value === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($definition['value_type'] === 'boolean'): ?>
                    <input type="checkbox" name="custom_fields[<?= e($key) ?>]" value="1" <?= !empty($value) ? 'checked' : '' ?>>
                <?php else: ?>
                    <input type="<?= e($definition['value_type'] === 'date' ? 'date' : ($definition['value_type'] === 'number' ? 'number' : ($definition['value_type'] === 'url' ? 'url' : 'text'))) ?>" name="custom_fields[<?= e($key) ?>]" value="<?= e((string)$value) ?>">
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
    </section>

    <section class="panel">
        <h2>Taxonomy terms</h2>
        <label>Reusable terms
            <select name="term_ids[]" multiple size="<?= max(3, min(12, count($taxonomies->allTerms()))) ?>">
                <?php foreach ($taxonomies->allTerms() as $term): ?>
                    <option value="<?= (int)$term['id'] ?>" <?= isset($selectedTerms[(int)$term['id']]) ? 'selected' : '' ?>><?= e($term['taxonomy_name'] . ': ' . $term['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="muted">Manage nested reusable terms in <a href="/admin/taxonomies">Taxonomies</a>.</p>
    </section>

    <section class="panel">
        <h2>External links</h2>
        <label>One link per line: Label | URL | service type | rel attributes
            <textarea name="extension_links" rows="4" placeholder="Official site | https://example.com | website | noopener noreferrer"><?= e(implode("\n", $links)) ?></textarea>
        </label>
        <p class="muted">Only http(s) URLs are accepted. Service types are labels such as website, spotify, or store.</p>
    </section>

    <section class="panel">
        <h2>Embeds</h2>
        <label>One embed per line: URL | provider | title
            <textarea name="extension_embeds" rows="3" placeholder="https://open.spotify.com/... | spotify | Listen on Spotify"><?= e(implode("\n", $embeds)) ?></textarea>
        </label>
        <p class="muted">Allowlisted providers: YouTube, Spotify, Apple Music, Bandcamp, and SoundCloud.</p>
    </section>

    <section class="panel">
        <h2>Relationships</h2>
        <label>One relationship per line: content-type:id | relationship type | label
            <textarea name="extension_relationships" rows="3" placeholder="listing:12 | features | Featured creator"><?= e(implode("\n", $relationships)) ?></textarea>
        </label>
        <p class="muted">Core content types are article, page, and listing.</p>
    </section>
    <?php
}

function contentPublicUrl(string $entityType, array $entity): ?string
{
    $slug = (string)($entity['slug'] ?? '');
    return match ($entityType) {
        'article' => $slug !== '' ? '/articles/' . rawurlencode($slug) : null,
        'page' => $slug !== '' ? '/' . rawurlencode($slug) : null,
        'listing' => $slug !== '' ? '/listings/' . rawurlencode($slug) : null,
        default => null,
    };
}

function contentRelatedEntity(string $entityType, int $entityId): ?array
{
    $repository = match ($entityType) {
        'article' => $GLOBALS['articles'] ?? null,
        'page' => $GLOBALS['pages'] ?? null,
        'listing' => $GLOBALS['listings'] ?? null,
        default => null,
    };
    if ($repository === null || !method_exists($repository, 'findById')) {
        return null;
    }
    $entity = $repository->findById($entityId);
    if (!$entity || ($entity['status'] ?? '') !== 'published' || empty($entity['published_at']) || strtotime((string)$entity['published_at']) > time()) {
        return null;
    }
    return $entity;
}

function renderPublicContentExtensions(string $entityType, int $entityId): void
{
    /** @var ContentExtensionRepository $extensions */
    $extensions = $GLOBALS['contentExtensions'];
    $payload = $extensions->extensionPayload($entityType, $entityId, true);
    $collections = $GLOBALS['collections']->collectionsFor($entityType, $entityId, true);
    if ($payload['custom_fields'] === [] && $payload['links'] === [] && $payload['embeds'] === [] && $payload['terms'] === [] && $payload['relationships'] === [] && $collections === []) {
        return;
    }
    ?>
    <section class="panel content-extensions">
        <?php if ($payload['custom_fields'] !== []): ?>
            <dl class="metadata-list">
                <?php foreach ($payload['custom_fields'] as $field): ?>
                    <dt><?= e($field['label']) ?></dt>
                    <dd><?= e(is_bool($field['value']) ? ($field['value'] ? 'Yes' : 'No') : (string)$field['value']) ?></dd>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
        <?php if ($payload['terms'] !== []): ?>
            <p class="tag-list"><strong>Terms:</strong>
                <?php foreach ($payload['terms'] as $term): ?><span class="tag"><?= e($term['taxonomy_name'] . ': ' . $term['name']) ?></span><?php endforeach; ?>
            </p>
        <?php endif; ?>
        <?php if ($collections !== []): ?>
            <p class="tag-list"><strong>Collections:</strong>
                <?php foreach ($collections as $collection): ?><span class="tag"><?= e($collection['name']) ?></span><?php endforeach; ?>
            </p>
        <?php endif; ?>
        <?php if ($payload['links'] !== []): ?>
            <h2>Links</h2><ul>
                <?php foreach ($payload['links'] as $link): ?>
                    <li><a href="<?= feature('analytics') ? '/go?link=' . (int)$link['id'] : e($link['url']) ?>"<?= feature('analytics') ? '' : ' rel="' . e($link['rel_attributes']) . '" target="_blank"' ?>><?= e($link['label']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($payload['embeds'] !== []): ?>
            <h2>Media</h2>
            <?php foreach ($payload['embeds'] as $embed): ?>
                <?php $iframe = EmbedProvider::iframeUrl($embed); ?>
                <?php if ($iframe): ?>
                    <div class="embed-frame"><iframe src="<?= e($iframe) ?>" title="<?= e($embed['title'] ?: $embed['provider']) ?>" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe></div>
                <?php else: ?>
                    <p><a href="<?= e($embed['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($embed['title'] ?: ('Open ' . $embed['provider'])) ?></a></p>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($payload['relationships'] !== []): ?>
            <h2>Related content</h2><ul>
                <?php foreach ($payload['relationships'] as $relationship): ?>
                    <?php $target = contentRelatedEntity($relationship['target_type'], (int)$relationship['target_id']); $url = $target ? contentPublicUrl($relationship['target_type'], $target) : null; ?>
                    <?php if ($target && $url): ?><li><a href="<?= e($url) ?>"><?= e($relationship['label'] ?: $target['title']) ?></a> <span class="muted">(<?= e($relationship['relationship_type']) ?>)</span></li><?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php
}
