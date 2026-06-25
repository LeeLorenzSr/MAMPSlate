<?php
declare(strict_types=1);

/**
 * Registry of MCP tools. Builds the dynamic tools/list based on feature toggles,
 * MCP config flags, and the authenticated user's capabilities.
 */
final class McpToolRegistry
{
    /** @var array<string, McpTool> */
    private array $tools = [];

    public function add(McpTool $tool): void
    {
        $this->tools[$tool->name] = $tool;
    }

    public function get(string $name): ?McpTool
    {
        return $this->tools[$name] ?? null;
    }

    public function isAvailable(McpTool $tool, array $caps, array $mcpConfig): bool
    {
        if ($tool->feature !== null && !feature($tool->feature)) {
            return false;
        }
        if ($tool->configFlag !== null && empty($mcpConfig[$tool->configFlag])) {
            return false;
        }
        if ($tool->capabilities !== [] && empty(array_intersect($tool->capabilities, $caps))) {
            return false;
        }
        return true;
    }

    public function listFor(array $caps, array $mcpConfig): array
    {
        $out = [];
        foreach ($this->tools as $tool) {
            if ($this->isAvailable($tool, $caps, $mcpConfig)) {
                $out[] = $tool->describe();
            }
        }
        return $out;
    }
}
