<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('export.manage');

$dataset = (string)($_GET['dataset'] ?? '');
$format = (string)($_GET['format'] ?? '');
$datasets = export_datasets();
$allowed = array_keys($datasets);

if (in_array($dataset, $allowed, true) && in_array($format, ['json', 'csv'], true)) {
    $rows = export_rows($dataset);
    $filename = $dataset . '_' . date('Ymd_His') . '.' . $format;
    prevent_caching();
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    $out = fopen('php://output', 'w');
    $headers = [];
    foreach ($rows as $row) {
        $headers = array_values(array_unique(array_merge($headers, array_keys($row))));
    }
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $header) {
            $value = $row[$header] ?? '';
            $line[] = is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value;
        }
        fputcsv($out, $line);
    }
    exit;
}

renderHeader('Exports', $currentUser);
?>
<section class="panel">
    <h2>Export data</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Dataset</th><th>JSON</th><th>CSV</th></tr></thead>
            <tbody>
                <?php foreach ($allowed as $name): ?>
                    <tr>
                        <td>
                            <strong><?= e($datasets[$name]['label']) ?></strong><br>
                            <span class="muted"><?= e(implode(', ', $datasets[$name]['fields'])) ?></span>
                        </td>
                        <td><a href="/admin/exports?dataset=<?= e($name) ?>&format=json">Download JSON</a></td>
                        <td><a href="/admin/exports?dataset=<?= e($name) ?>&format=csv">Download CSV</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter();

function export_rows(string $dataset): array
{
    global $users, $articles, $media, $settings, $listings, $contacts;

    $rows = match ($dataset) {
        'users' => $users->allUsers(),
        'articles' => $articles->listForAdmin(),
        'media' => $media->listAll(),
        'settings' => array_map(fn($k, $v) => ['key' => $k, 'value' => $v], array_keys($settings->all()), array_values($settings->all())),
        'listings' => $listings->listForAdmin(),
        'contact_submissions' => $contacts->submissions(null, 10000),
        default => [],
    };

    return array_map(fn($row) => export_allowlist_row($dataset, $row), $rows);
}

function export_allowlist_row(string $dataset, array $row): array
{
    $fields = export_datasets()[$dataset]['fields'] ?? [];
    $out = [];
    foreach ($fields as $field) {
        $out[$field] = $row[$field] ?? null;
    }

    return $out;
}

function export_datasets(): array
{
    return [
        'users' => [
            'label' => 'Users',
            'fields' => ['id', 'email', 'display_name', 'role_name', 'is_active', 'created_at'],
        ],
        'articles' => [
            'label' => 'Articles',
            'fields' => ['id', 'title', 'slug', 'status', 'published_at', 'updated_at', 'author_user_id', 'author_name', 'category_name'],
        ],
        'media' => [
            'label' => 'Media metadata',
            'fields' => ['id', 'stored_name', 'original_name', 'mime_type', 'file_size', 'width', 'height', 'alt_text', 'title', 'created_at', 'uploader_name'],
        ],
        'settings' => [
            'label' => 'Non-secret settings',
            'fields' => ['key', 'value'],
        ],
        'listings' => [
            'label' => 'Listings',
            'fields' => ['id', 'title', 'slug', 'status', 'published_at', 'updated_at', 'owner_name'],
        ],
        'contact_submissions' => [
            'label' => 'Contact submissions',
            'fields' => ['id', 'form_id', 'form_name', 'form_slug', 'name', 'email', 'subject', 'message', 'status', 'created_at', 'updated_at'],
        ],
    ];
}
