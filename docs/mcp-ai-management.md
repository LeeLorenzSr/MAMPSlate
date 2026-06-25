# MCP AI Management

The CMS exposes a **Model Context Protocol (MCP)** endpoint at `/mcp` so an
authorized AI client can manage content using the exact same authentication,
capability, audit, feature-toggle, and content-rendering rules as the web UI and
the `/api/v1` REST API.

## Endpoint location

- URL: `POST /mcp` (JSON-RPC 2.0 over HTTP).
- The only file under `public_html/` is the bare entry point
  `public_html/mcp/index.php`. All implementation lives in `includes/Mcp/`
  (`McpServer`, `McpToolRegistry`, `McpTool`, `McpRequest`, `McpResponse`,
  `CmsMcpTools`). No secrets, logs, config, or large files are under
  `public_html/mcp`.

## Why only the entry point is public

`public_html/` is the Apache document root; anything under it is web-served.
Keeping the MCP implementation in `includes/Mcp/` (outside the doc root) means
the protocol code, tool handlers, and any internal helpers can never be fetched
directly by URL — only the thin `index.php` dispatcher is reachable, and it
delegates to the server class.

## Authentication

MCP uses the same stateless credentials as `/api/v1`:

- `Authorization: Bearer <api_key>` — an API key created on `/profile` or
  `/admin/api-keys`.
- `Authorization: Session <session_key>` — a temporal session key from
  `POST /api/session`.

Keys are stored only as hashes at rest and are never logged or returned. Missing
or invalid credentials return a JSON-RPC error (`-32001`, HTTP 401). Browser
CSRF is **not** required for bearer/session MCP requests, matching the stateless
API model.

## Authorization (same model as the web UI)

MCP authorizes by **capability**, never by role name. Each tool maps to the same
capability the equivalent web/API action requires. `tools/list` only exposes
tools the authenticated user is allowed to call; `tools/call` re-checks
capability, feature, and MCP-config flags on every invocation (hidden tools are
not the security boundary — the server enforces).

## Enabling and disabling MCP

MCP is **off by default**. Enable it in `config/config.local.php`:

```php
'mcp' => [
    'enabled' => true,        // master switch; false -> /mcp returns 404
    'dry_run' => true,        // mutations return planned action without writing
    'allow_publish' => false, // publish tools require this AND the publish capability
    'allow_delete' => false,  // archive tools require this AND edit capability
    'allow_security_tools' => false, // reserved for future admin tools (not in v1)
    'max_body_chars' => 50000,
    'max_per_page' => 50,
],
```

With `enabled => false`, `/mcp` returns 404 + a JSON-RPC error.

## Creating a dedicated AI user and API key

Do not reuse your personal administrator account for AI automation.

1. As an administrator, create a new user at `/admin/users` (e.g.
   `ai-editor@example.com`).
2. Assign it the role that matches the intended power (see below).
3. Sign in as that user and create an API key on `/profile` (requires
   `apikey.own`), or create one for them on `/admin/api-keys`.
4. Configure the AI client to send `Authorization: Bearer <that key>`.

## Recommended permissions

- **AI editor** — assign the `editor` role (or a custom role with
  `article.*`, `page.*`, `media.upload`). Keep `mcp.allow_publish=false` and
  `mcp.allow_delete=false` so the AI can draft and update but not publish or
  archive. A human reviews and publishes.
- **AI administrator** — give a trusted, restricted role with the specific
  capabilities needed (e.g. add `article.publish`). Enable `allow_publish` only
  if you accept automated publishing. Avoid granting `user.manage`,
  `role.manage`, `settings.manage`, or `audit.view` to AI accounts unless
  strictly necessary.

## Dry-run mode

With `mcp.dry_run=true`, mutation tools validate input and return the planned
action (`"dry_run": true`) without changing the database. Read-only tools
execute normally. Dry-run is the recommended default while integrating an AI
client; flip to `false` once behavior is verified.

## Publishing / deleting safety flags

- `allow_publish=false` → `cms.publish_article` / `cms.publish_page` are not
  listed and fail even if the user has `article.publish` / `page.publish`.
- `allow_delete=false` → `cms.archive_article` / `cms.archive_page` (the v1
  destructive tools) are not listed and cannot execute. Hard delete is not
  exposed via MCP at all.

## Protocol

JSON-RPC 2.0 methods:

- `initialize` → `{protocolVersion, capabilities, serverInfo}`.
- `tools/list` → `{tools: [{name, description, inputSchema}, ...]}` (filtered).
- `tools/call` → `{name, arguments}` → `{content: [{type:"text", text:"..."}]}`
  or `{isError: true, content: [...]}`.

Tool categories: read-only (`cms.health`, `cms.me`, `cms.list_*`, `cms.get_*`,
`cms.get_settings_public_or_nonsecret`), content mutation (create/update article
& page; publish & archive gated), media (base64 upload + metadata), comments
(moderate), menus (list + update item). Settings mutation, user/role/API-key
management, migrations, backup/restore, and audit-log viewing are **not**
implemented in v1. Raw SQL, shell, filesystem access, config inspection, and
secret dumping are never implemented.

Input rules: every tool validates against a strict allowlist
(`additionalProperties: false`); unknown fields are rejected; `body_html` is
never accepted (Markdown only, rendered through the existing renderer);
`author_id` cannot be overridden; `max_body_chars` is enforced.

## Audit

All successful MCP mutations are recorded in `audit_events` with
`source="mcp"`, the tool name, entity type/id, and safe metadata. Authorization
headers, API keys, tokens, passwords, hashes, and long body content are never
logged (the AuditLogger redacts sensitive metadata keys).

## Example client configuration

Using `curl`:

```bash
# initialize
curl -s -X POST http://localhost/mcp \
  -H "Authorization: Bearer mpk_xxx" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize"}'

# list available tools
curl -s -X POST http://localhost/mcp \
  -H "Authorization: Bearer mpk_xxx" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'

# create a draft article (dry_run returns the plan)
curl -s -X POST http://localhost/mcp \
  -H "Authorization: Bearer mpk_xxx" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"cms.create_article","arguments":{"title":"Hello from AI","body_markdown":"# Hi"}}}'
```

For an MCP client config (streamable HTTP transport), point the client at
`http://localhost/mcp` with the `Authorization: Bearer <api_key>` header.

## Manual security checklist

- [ ] `mcp.enabled` is `false` in production until explicitly needed.
- [ ] A dedicated AI user exists; its API key is not shared with humans.
- [ ] The AI user's role has only the capabilities the AI needs.
- [ ] `dry_run` was used during integration; `allow_publish`/`allow_delete` are
      off unless automated publish/archive is intended.
- [ ] `tools/list` for the AI key shows only intended tools.
- [ ] `tools/call` for a disallowed tool returns a forbidden error.
- [ ] No secrets appear in MCP responses, the audit log, or PHP error log.
- [ ] `php -l` passes on all `includes/Mcp/*.php` and `public_html/mcp/index.php`.
