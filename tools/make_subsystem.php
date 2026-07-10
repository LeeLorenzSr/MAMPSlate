<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from CLI: php tools/make_subsystem.php <name> [--dry-run]\n");
    exit(1);
}
$name = strtolower(trim((string)($argv[1] ?? '')));
$dryRun = in_array('--dry-run', $argv, true);
if (!preg_match('/^[a-z][a-z0-9_-]{1,79}$/', $name)) {
    fwrite(STDERR, "Name must use lowercase letters, numbers, dashes, or underscores.\n");
    exit(1);
}
$root = dirname(__DIR__);
$class = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', rtrim($name, 's'))));
$numbers = [];
foreach (glob($root . '/sql_init/*.sql') ?: [] as $file) {
    if (preg_match('/^(\d{3})_/', basename($file), $match)) { $numbers[] = (int)$match[1]; }
}
$migration = sprintf('%03d_%s.sql', max($numbers ?: [0]) + 1, str_replace('-', '_', $name));
$files = [
    $root . '/modules/' . $name . '/module.php' => "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'name' => '{$name}',\n    'entity_types' => ['{$name}'],\n    // Optional: 'sitemap_entries' => static fn(string \$baseUrl): array => [],\n];\n",
    $root . '/includes/' . $class . "Repository.php" => "<?php\ndeclare(strict_types=1);\n\nfinal class {$class}Repository\n{\n    public function __construct(private PDO \$pdo) {}\n}\n",
    $root . '/public_html/admin/' . $name . ".php" => "<?php\ndeclare(strict_types=1);\n\nrequire_once dirname(__DIR__, 2) . '/includes/bootstrap.php';\nrequire_once dirname(__DIR__, 2) . '/includes/layout.php';\n\n\$currentUser = \$auth->requireCapability('{$name}.manage');\nrenderHeader('" . ucfirst($name) . "', \$currentUser);\n?>\n<section class=\"panel\"><p>Implement {$name} administration here.</p></section>\n<?php renderFooter();\n",
    $root . '/public_html/' . $name . ".php" => "<?php\ndeclare(strict_types=1);\n\nrequire_once dirname(__DIR__) . '/includes/bootstrap.php';\nrequire_once dirname(__DIR__) . '/includes/layout.php';\nrenderHeader('" . ucfirst($name) . "');\n?>\n<section class=\"panel\"><p>Implement the public {$name} route here.</p></section>\n<?php renderFooter();\n",
    $root . '/docs/' . $name . ".md" => "# " . ucfirst($name) . " subsystem\n\nGenerated scaffold. Complete the migration, repository, routes, capabilities, API/OpenAPI, MCP, tests, and this document before enabling the module.\n",
    $root . '/sql_init/' . $migration => "-- {$name} subsystem migration.\n-- Add tables, capabilities, administrator grants, and feature defaults here.\n",
];
foreach (array_keys($files) as $path) {
    if (file_exists($path)) {
        fwrite(STDERR, "Refusing to overwrite existing file: {$path}\n");
        exit(1);
    }
}
foreach ($files as $path => $content) {
    echo ($dryRun ? '[would create] ' : '[created] ') . substr($path, strlen($root) + 1) . PHP_EOL;
    if (!$dryRun) {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create ' . $directory);
        }
        file_put_contents($path, $content);
    }
}
echo $dryRun ? "No files written.\n" : "Complete the generated checklist before enabling the subsystem.\n";
