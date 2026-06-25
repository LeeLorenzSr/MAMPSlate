<?php
declare(strict_types=1);

/**
 * JSON-RPC 2.0 response helpers for the MCP endpoint.
 */
final class McpResponse
{
    public static function result($id, array $result): never
    {
        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error($id, int $code, string $message, $data = null): never
    {
        $err = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $err['data'] = $data;
        }
        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $err,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
