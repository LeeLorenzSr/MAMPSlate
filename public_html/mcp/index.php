<?php
declare(strict_types=1);

/**
 * MCP (Model Context Protocol) entry point.
 *
 * Bare public entry point only. All implementation lives under includes/Mcp/.
 * Loads bootstrap (auth, repositories, capabilities) then delegates to McpServer.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/Mcp/McpServer.php';

security_headers();
prevent_caching();

$server = new McpServer();
$server->handle();
