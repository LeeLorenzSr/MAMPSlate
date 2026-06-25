<?php
declare(strict_types=1);

/**
 * A single MCP tool definition.
 *
 * - $capabilities: any-of; the authenticated user must hold at least one (empty = any authed user).
 * - $feature: a CMS feature toggle that must be enabled (null = always).
 * - $configFlag: a key in config['mcp'] that must be truthy (null = no extra flag).
 * - $mutation: whether the tool changes data (subject to dry_run + audit).
 */
final class McpTool
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly array $capabilities = [],
        public readonly ?string $feature = null,
        public readonly ?string $configFlag = null,
        public readonly bool $mutation = false,
        public readonly ?\Closure $handler = null,
    ) {
    }

    public function describe(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
        ];
    }
}
