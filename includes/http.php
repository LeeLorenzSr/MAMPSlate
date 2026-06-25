<?php
declare(strict_types=1);

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/**
 * Per-request CSP nonce for inline scripts. Generated once per request.
 */
function csp_nonce(): string
{
    if (!isset($GLOBALS['csp_nonce'])) {
        $GLOBALS['csp_nonce'] = bin2hex(random_bytes(16));
    }
    return $GLOBALS['csp_nonce'];
}

/**
 * Send browser security headers. Pass a nonce for HTML responses that contain
 * nonce'd inline scripts; omit it (or pass null) for JSON/standalone responses.
 */
function security_headers(?string $nonce = null): void
{
    $config = $GLOBALS['config'] ?? [];
    $extra = trim((string)($config['security']['csp_extra'] ?? ''));

    $scriptSrc = $nonce !== null
        ? "script-src 'self' 'nonce-" . $nonce . "'"
        : "script-src 'self'";

    $csp = "default-src 'self'; "
        . $scriptSrc . "; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; "
        . "font-src 'self'; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'none'";
    if ($extra !== '') {
        $csp .= '; ' . $extra;
    }

    $headerName = !empty($config['security']['csp_report_only'])
        ? 'Content-Security-Policy-Report-Only'
        : 'Content-Security-Policy';
    header($headerName . ': ' . $csp);

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Mark the response as not cacheable. Use on authenticated/admin/JSON responses.
 */
function prevent_caching(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

/**
 * Apply a configured rate limit. Returns true if the action is allowed (and
 * records the hit), false if blocked. Blocked attempts are logged via error_log
 * (not the audit table) to avoid log spam. Returns true (unlimited) when the
 * limiter or config is absent.
 */
function rate_limit(string $key, string $configKey): bool
{
    $rl = $GLOBALS['rateLimiter'] ?? null;
    $cfg = $GLOBALS['config']['rate_limits'][$configKey] ?? null;
    if (!$rl instanceof RateLimiter || !is_array($cfg)) {
        return true;
    }
    $max = max(1, (int)($cfg['max'] ?? 5));
    $window = max(1, (int)($cfg['window'] ?? 60));

    if ($rl->attempt($key, $max, $window)) {
        return true;
    }

    error_log('rate_limit blocked: ' . $configKey . ' key=' . hash('sha256', $key));
    return false;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function currentPath(): string
{
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
}

/**
 * Check whether a CMS feature is enabled. Considers DB-backed settings
 * (features.<name>) first, then config['features'], defaulting to true.
 */
function feature(string $name): bool
{
    $settings = $GLOBALS['settings'] ?? null;
    if ($settings instanceof SettingsRepository) {
        $val = $settings->get('features.' . $name, null);
        if ($val !== null) {
            return (bool)$val;
        }
    }
    $features = $GLOBALS['config']['features'] ?? [];
    if (!array_key_exists($name, $features)) {
        return true;
    }
    return (bool)$features[$name];
}

/**
 * Read a site setting (DB-backed with config fallback). See SettingsRepository.
 */
function setting(string $key, $default = null)
{
    $s = $GLOBALS['settings'] ?? null;
    if (!$s instanceof SettingsRepository) {
        return $default;
    }
    return $s->get($key, $default);
}

/**
 * 404 exit when a feature is disabled.
 */
function requireFeature(string $name): void
{
    if (!feature($name)) {
        http_response_code(404);
        exit('Not found');
    }
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    security_headers();
    prevent_caching();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        jsonResponse([
            'ok' => false,
            'error' => 'method_not_allowed',
            'message' => 'This endpoint does not support the requested method.',
        ], 405);
    }
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonResponse([
            'ok' => false,
            'error' => 'bad_request',
            'message' => 'Request body must be valid JSON.',
        ], 400);
    }

    return $data;
}
