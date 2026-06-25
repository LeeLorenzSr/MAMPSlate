<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'MusicPromoV2 CMS',
        'env' => 'local',
        'base_url' => 'http://localhost',
        'session_name' => 'musicpromo_session',
        'session_lifetime_minutes' => 120,
        'api_session_lifetime_minutes' => 60,
        'allow_signup' => true,
        'signup_mode' => 'open',
        'allow_oauth' => true,
        'media_max_upload_bytes' => 5242880,
        'media_image_max_width' => 1600,
        'avatar_size' => 256,
        'avatar_max_upload_bytes' => 3145728,
        'articles_per_page' => 10,
        'comments_require_approval' => false,
        'comments_per_minute' => 5,
        'default_meta_description' => '',
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'musicpromov2',
        'user' => 'musicpromov2_user',
        'password' => 'replace-me',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'app_secret' => 'replace-with-at-least-32-random-characters',
        'secure_cookies' => false,
        // Content-Security-Policy extra directives appended to the default
        // policy (e.g. for analytics). The default already allows 'self' and a
        // per-request nonce for inline scripts. Set 'report_only' => true to
        // emit CSP in Report-Only mode while testing.
        'csp_extra' => '',
        'csp_report_only' => false,
    ],
    'mail' => [
        // 'log' (dev), 'mail' (PHP mail()), 'smtp' (reserved).
        'mode' => 'log',
        'from_address' => 'no-reply@example.com',
        'from_name' => 'MusicPromoV2 CMS',
    ],
    'rate_limits' => [
        'login'      => ['max' => 10, 'window' => 60],     // per IP, per minute
        'login_user' => ['max' => 6,  'window' => 3600],   // per email, per hour
        'signup'     => ['max' => 5,  'window' => 3600],
        'forgot'     => ['max' => 5,  'window' => 3600],
        'reset'      => ['max' => 10, 'window' => 3600],
        'api_session'=> ['max' => 10, 'window' => 60],
    ],
    'oauth' => [
        'google' => [
            'enabled' => false,
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => 'http://localhost/auth/google-callback.php',
            'scope' => 'openid email profile',
        ],
        'github' => [
            'enabled' => false,
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => 'http://localhost/auth/github-callback.php',
            'scope' => 'read:user user:email',
        ],
    ],
    // MCP (Model Context Protocol) endpoint for AI management clients.
    // See docs/mcp-ai-management.md. Off by default; enable in config.local.php.
    'mcp' => [
        'enabled' => false,
        'dry_run' => true,
        'allow_publish' => false,
        'allow_delete' => false,
        'allow_security_tools' => false,
        'max_body_chars' => 50000,
        'max_per_page' => 50,
    ],
    // Toggle CMS subsystems when copying this base to a new project.
    'features' => [
        'articles' => true,
        'pages' => true,
        'comments' => true,
        'media' => true,
        'categories' => true,
        'tags' => true,
        'seo_sitemap' => true,
        'rss_feed' => true,
    ],
];
