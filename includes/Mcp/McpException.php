<?php
declare(strict_types=1);

/**
 * Tool-level error thrown by MCP tool handlers. Mapped to a tool result with
 * isError=true (not a JSON-RPC protocol error).
 */
final class McpException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
