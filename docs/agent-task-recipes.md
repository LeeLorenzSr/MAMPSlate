# Agent task recipes

Use these bounded recipes when asking Codex, Claude, Copilot, or another coding
agent to specialize a copy of MAMPSlate. Keep the generated subsystem generic
until the project explicitly needs vertical behavior.

## Add a new content type

1. Run `php tools/make_subsystem.php events --dry-run`, then run it without
   `--dry-run` after reviewing the paths.
2. Complete the generated migration: table, indexes, capability, administrator
   grant, and feature default.
3. Implement the repository, admin/public routes, module manifest, sitemap
   callback, and permissions checks.
4. Add documentation, verifier checks, and demo content only if requested.

## Add a public listing page

1. Prefer `listing` plus custom fields, terms, links, and relationships before
   adding a new table.
2. Define fields at `/admin/content-model`, terms at `/admin/taxonomies`, then
   save the listing once before attaching extensions.
3. Verify public visibility with a future scheduled timestamp and with all
   optional feature flags disabled/enabled as appropriate.

## Add API support

1. Add routes in `public_html/api/v1/index.php`; use capability checks and
   validated JSON, never raw SQL or raw HTML.
2. Update `docs/api-v1.md` and `docs/openapi-v1.yaml`.
3. Run `php tools/generate_openapi.php`, then `php tools/verify.php`.

## Add MCP tools

1. Add an explicit schema with `additionalProperties: false` in
   `CmsMcpTools`.
2. Require the narrowest capability and feature; mark mutations as write tools.
3. Honor MCP dry-run/config safety gates, use repositories, and audit safe
   metadata only.
4. Update `docs/mcp-ai-management.md` and test `tools/list` with a restricted
   API key.

## Add tests and checks

1. Add deterministic repository/unit checks to `tools/verify.php`.
2. Use the opt-in disposable MySQL smoke path for schema/repository behavior.
3. Run `php -l` and `php tools/verify.php`; document any external dependency.
