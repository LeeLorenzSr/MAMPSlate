# Modules and scaffolding

Optional local modules live under `modules/{name}/module.php`. A manifest must
return an array with a lowercase `name`; it may declare `entity_types` and an
optional `sitemap_entries` callable receiving the base URL. Manifests are local
PHP code, so install only code you trust.

Use `php tools/make_subsystem.php tracks --dry-run` to preview a module
manifest, repository, admin/public route stubs, documentation stub, and the
next numbered migration. The normal command refuses to overwrite files.

The OpenAPI file remains a hand-authored contract. Run
`php tools/generate_openapi.php` to refresh its generated route inventory; use
`--check` in automation. This intentionally avoids adding a YAML dependency.
