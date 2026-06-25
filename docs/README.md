# MAMPSlate CMS Documentation

This folder contains the specifications and operating notes for a generic
PHP/MySQL CMS hosted on Apache, MySQL, and PHP (MAMP/LAMP). It is intended as a
reusable **base CMS** — copy it to a new project and specialize it.

## Documents

- [System Requirements](requirements.md) — PHP modules, server, and database prerequisites.
- [First-Run Setup](setup.md) — the browser-based setup wizard and site-master password.
- [Architecture](architecture.md) — request flow, routes, and subsystems.
- [Permissions](permissions.md) — the role → capability model and capability catalog.
- [Content Management](content-management.md) — articles, categories, tags, media, comments, and SEO.
- [User Management](user-management.md) — users, roles, signup, and federated login.
- [OAuth Setup](oauth-setup.md) — configuring Google and GitHub login.
- [Database Specification](database-specification.md) — tables and schema.
- [API Specification](api-specification.md) — JSON API conventions (bootstrap API).
- [API v1](api-v1.md) — versioned CRUD API reference (+ [openapi-v1.yaml](openapi-v1.yaml)).
- [Extending the CMS](extending-the-cms.md) — subsystem pattern and completion checklist for AI agents.
- [MCP AI Management](mcp-ai-management.md) — MCP endpoint for AI clients.
- [Backup & Restore](backup-restore.md) — backup/restore runbook and CLI helpers.
- [Coding Standards](coding-standards.md) — PHP conventions for this codebase.
- [Security Standards](security-standards.md) — security principles and controls.
- [Style Preferences](style-preferences.md) — UI and content style guidance.
- [Deployment Checklist](deployment-checklist.md) — launch procedure.

These documents describe the current implementation and should be revised as
product requirements, branding, editorial workflow, and business rules become
concrete.
