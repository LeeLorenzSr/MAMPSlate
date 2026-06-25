<?php
declare(strict_types=1);

/**
 * MCP JSON-RPC server.
 *
 * Authenticates with the existing API auth (bearer API key or temporal session
 * key), authorizes every tool by capability, honors feature toggles and MCP
 * config flags, supports dry-run for mutations, and delegates to the CMS tool
 * handlers. No business logic lives here.
 */
final class McpServer
{
    public function handle(): never
    {
        header('Content-Type: application/json; charset=utf-8');

        $config = $GLOBALS['config'] ?? [];
        $mcpConfig = $config['mcp'] ?? [];

        $request = McpRequest::fromBody((string)file_get_contents('php://input'));

        // Disabled MCP is unavailable (404 + JSON-RPC error).
        if (empty($mcpConfig['enabled'])) {
            http_response_code(404);
            McpResponse::error($request->id, -32601, 'MCP is disabled.');
        }

        if (!$request->valid) {
            McpResponse::error(null, -32700, 'Parse error: valid JSON-RPC 2.0 request required.');
        }

        // Authenticate with the same model as /api/v1.
        $user = $GLOBALS['apiAuth']->authenticateRequest();
        if (!$user) {
            http_response_code(401);
            McpResponse::error($request->id, -32001, 'Unauthorized: provide a valid API key (Bearer) or session key (Session).');
        }

        $caps = $GLOBALS['capabilities']->capabilitiesForRole((int)$user['role_id']);
        $ctx = [
            'user' => $user,
            'caps' => $caps,
            'mcp' => $mcpConfig,
            'dryRun' => !empty($mcpConfig['dry_run']),
        ];

        $registry = new McpToolRegistry();
        CmsMcpTools::register($registry);

        switch ($request->method) {
            case 'initialize':
                McpResponse::result($request->id, [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => ['tools' => (object)[]],
                    'serverInfo' => ['name' => 'MusicPromoV2 MCP', 'version' => '1.0'],
                ]);

            case 'tools/list':
                McpResponse::result($request->id, [
                    'tools' => $registry->listFor($caps, $mcpConfig),
                ]);

            case 'tools/call':
                $this->callTool($registry, $request, $ctx);
                // callTool always exits.

            default:
                McpResponse::error($request->id, -32601, 'Method not found: ' . $request->method);
        }
    }

    private function callTool(McpToolRegistry $registry, McpRequest $request, array $ctx): never
    {
        $name = (string)($request->params['name'] ?? '');
        $args = is_array($request->params['arguments'] ?? null) ? $request->params['arguments'] : [];

        $tool = $registry->get($name);
        if (!$tool || !$registry->isAvailable($tool, $ctx['caps'], $ctx['mcp'])) {
            http_response_code(403);
            McpResponse::error($request->id, -32002, 'Forbidden: tool not available for this user or configuration.');
        }

        if ($tool->handler === null) {
            McpResponse::error($request->id, -32601, 'Tool has no handler.');
        }

        try {
            $payload = ($tool->handler)($args, $ctx);
            McpResponse::result($request->id, [
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ]],
            ]);
        } catch (McpException $e) {
            McpResponse::result($request->id, [
                'isError' => true,
                'content' => [['type' => 'text', 'text' => $e->getMessage()]],
            ]);
        } catch (Throwable $e) {
            McpResponse::error($request->id, -32603, 'Internal error: ' . $e->getMessage());
        }
    }
}
