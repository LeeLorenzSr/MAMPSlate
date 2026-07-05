<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('export.manage');

$dataset = (string)($_GET['dataset'] ?? '');
$format = (string)($_GET['format'] ?? '');
$allowed = ['users', 'articles', 'media', 'settings', 'listings', 'contact_submissions'];

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
                        <td><?= e(str_replace('_', ' ', $name)) ?></td>
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

    return match ($dataset) {
        'users' => array_map(fn($u) => [
            'id' => $u['id'],
            'email' => $u['email'],
            'display_name' => $u['display_name'],
            'role_name' => $u['role_name'],
            'is_active' => $u['is_active'],
            'created_at' => $u['created_at'],
        ], $users->allUsers()),
        'articles' => $articles->listForAdmin(),
        'media' => $media->listAll(),
        'settings' => array_map(fn($k, $v) => ['key' => $k, 'value' => $v], array_keys($settings->all()), array_values($settings->all())),
        'listings' => $listings->listForAdmin(),
        'contact_submissions' => $contacts->submissions(null, 10000),
        default => [],
    };
}
