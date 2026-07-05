# Feature Module Checklist Templates

Use these templates when adding a reusable CMS module. Delete items that do not
apply, but do not skip security or documentation checks silently.

## Migration Template

- [ ] Next numbered `sql_init/0NN_name.sql` file, no numbering gaps.
- [ ] `CREATE TABLE IF NOT EXISTS` and idempotent `ALTER` guards.
- [ ] MySQL 5.7 compatible SQL.
- [ ] Foreign keys include explicit `ON DELETE` behavior.
- [ ] New capabilities inserted with `ON DUPLICATE KEY UPDATE`.
- [ ] Administrator receives new capabilities.
- [ ] Default settings/feature flags are seeded if needed.
- [ ] `docs/database-specification.md` migration list updated.

## Repository Template

- [ ] All SQL lives in `includes/*Repository.php`.
- [ ] Methods for find/list/create/update/delete match local naming patterns.
- [ ] Slugs are unique through `Slug::ensureUnique()`.
- [ ] Search methods escape `LIKE` wildcards.
- [ ] Stored HTML, if any, is generated from Markdown server-side.
- [ ] No secret or hash fields are returned from repository-facing exports.

## Public Route Template

- [ ] `requireFeature()` gates the module.
- [ ] Published-only visibility rules are enforced server-side.
- [ ] SEO title, description, canonical, and optional OG image are set.
- [ ] User input is escaped with `e()`.
- [ ] Pagination is bounded.

## Admin Route Template

- [ ] `requireCapability()` gates every admin page.
- [ ] POST handlers call `requireValidCsrf()`.
- [ ] Mutations are audit-logged.
- [ ] Delete/publish actions use confirmation prompts.
- [ ] Forms preserve submitted values on validation errors.
- [ ] Navigation is added only for users with the capability.

## Permissions Template

- [ ] Capability name follows `<module>.<verb>` or `<module>.manage`.
- [ ] Default grants are documented in `docs/permissions.md`.
- [ ] Admin-only side effects are not exposed through public routes.

## API Docs Template

- [ ] `/api/v1` routes use `{ok,data}` and `{ok,error,message}` shapes.
- [ ] Read endpoints distinguish published visibility from admin visibility.
- [ ] Write endpoints require capabilities and validate status transitions.
- [ ] Object fields and write fields are documented in `docs/api-v1.md`.
- [ ] OpenAPI is updated when the route is intended for third-party clients.

## MCP Tool Checklist

- [ ] Tool input schema has `additionalProperties: false`.
- [ ] Tool only calls existing repository/domain methods.
- [ ] Destructive actions support dry-run.
- [ ] Mutation tools audit-log with `source = mcp`.
- [ ] The tool returns safe fields only; no secrets or hashes.
- [ ] Publishing/deleting honors config-level MCP allow flags when applicable.
