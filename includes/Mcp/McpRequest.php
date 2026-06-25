<?php
declare(strict_types=1);

/**
 * Parses a single MCP/JSON-RPC 2.0 request from the request body.
 */
final class McpRequest
{
    public ?string $method = null;
    public array $params = [];
    /** @var int|string|null */
    public $id = null;
    public bool $valid = false;

    public static function fromBody(string $raw): self
    {
        $req = new self();
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['jsonrpc'] ?? null) !== '2.0' || !isset($data['method'])) {
            return $req;
        }
        $req->valid = true;
        $req->method = (string)$data['method'];
        $req->params = is_array($data['params'] ?? null) ? $data['params'] : [];
        $req->id = $data['id'] ?? null;
        return $req;
    }
}
