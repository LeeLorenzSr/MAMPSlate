<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('settings.manage');
$message = null;
$error = null;

$featureToggles = ['articles', 'pages', 'comments', 'media', 'categories', 'tags', 'seo_sitemap', 'rss_feed', 'custom_fields', 'relationships', 'taxonomies', 'link_manager', 'embeds', 'collections', 'webhooks', 'analytics', 'accessibility_checker', 'media_documents', 'media_audio', 'media_video'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();

    try {
        $siteName = trim((string)($_POST['site_name'] ?? ''));
        if ($siteName === '') {
            throw new RuntimeException('Site name is required.');
        }

        $signupMode = (string)($_POST['signup_mode'] ?? 'off');
        if (!in_array($signupMode, ['open', 'restricted', 'invite', 'off'], true)) {
            $signupMode = 'off';
        }

        $commentsPerMinute = max(1, (int)($_POST['comments_per_minute'] ?? 5));
        $mediaMaxBytes = max(1, (int)($_POST['media_max_upload_bytes'] ?? 5242880));
        $mediaMaxWidth = max(0, (int)($_POST['media_image_max_width'] ?? 1600));
        $accentColor = trim((string)($_POST['theme_accent_color'] ?? '#2f6fec'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) {
            throw new RuntimeException('Accent color must be a six-digit hex color.');
        }
        $fontFamily = (string)($_POST['theme_font_family'] ?? 'montserrat');
        if (!in_array($fontFamily, ['montserrat', 'system', 'serif'], true)) {
            $fontFamily = 'montserrat';
        }
        $socialLinks = [];
        foreach (preg_split('/\r\n|\r|\n/', (string)($_POST['theme_social_links'] ?? '')) ?: [] as $line) {
            [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($label === '' && $url === '') { continue; }
            if ($label === '' || $url === '') { throw new RuntimeException('Each social link needs a label and URL.'); }
            $socialLinks[] = ['label' => mb_substr($label, 0, 80), 'url' => ListingLinkNormalizer::normalizeUrl($url)];
        }

        $kv = [
            'site.name' => $siteName,
            'site.tagline' => trim((string)($_POST['site_tagline'] ?? '')),
            'default_meta_title' => trim((string)($_POST['default_meta_title'] ?? '')),
            'default_meta_description' => trim((string)($_POST['default_meta_description'] ?? '')),
            'signup_mode' => $signupMode,
            'comments_require_approval' => isset($_POST['comments_require_approval']) ? '1' : '0',
            'comments_per_minute' => (string)$commentsPerMinute,
            'media_max_upload_bytes' => (string)$mediaMaxBytes,
            'media_image_max_width' => (string)$mediaMaxWidth,
            'theme.accent_color' => $accentColor,
            'theme.font_family' => $fontFamily,
            'theme.logo_media_id' => (string)max(0, (int)($_POST['theme_logo_media_id'] ?? 0)),
            'theme.homepage_layout' => in_array($_POST['theme_homepage_layout'] ?? '', ['articles', 'listings', 'mixed'], true) ? (string)$_POST['theme_homepage_layout'] : 'mixed',
            'theme.footer_text' => mb_substr(trim((string)($_POST['theme_footer_text'] ?? '')), 0, 240),
            'theme.social_links' => json_encode($socialLinks, JSON_UNESCAPED_SLASHES) ?: '[]',
        ];

        foreach ($featureToggles as $f) {
            $kv['features.' . $f] = isset($_POST['feature_' . $f]) ? '1' : '0';
        }

        $settings->setMany($kv);
        $audit->log('settings.updated', (int)$currentUser['id'], 'settings', null, ['keys' => implode(',', array_keys($kv))]);
        $message = 'Settings saved.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

renderHeader('Site settings', $currentUser);
?>
<?php if ($message): ?>
    <p class="notice success"><?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="notice error"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" class="settings-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

    <section class="panel">
        <h2>Site</h2>
        <label>Site name
            <input type="text" name="site_name" value="<?= e((string)setting('site.name')) ?>" required maxlength="120">
        </label>
        <label>Site tagline
            <input type="text" name="site_tagline" value="<?= e((string)setting('site.tagline')) ?>" maxlength="160">
        </label>
    </section>

    <section class="panel">
        <h2>SEO defaults</h2>
        <label>Default meta title
            <input type="text" name="default_meta_title" value="<?= e((string)setting('default_meta_title')) ?>" maxlength="200">
        </label>
        <label>Default meta description
            <textarea name="default_meta_description" rows="2" maxlength="320"><?= e((string)setting('default_meta_description')) ?></textarea>
        </label>
    </section>

    <section class="panel">
        <h2>Brand and layout</h2>
        <label>Accent color
            <input type="color" name="theme_accent_color" value="<?= e((string)setting('theme.accent_color', '#2f6fec')) ?>">
        </label>
        <label>Font family
            <select name="theme_font_family">
                <?php foreach (['montserrat' => 'Montserrat', 'system' => 'System sans', 'serif' => 'Editorial serif'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= setting('theme.font_family', 'montserrat') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Logo media ID (optional)
            <input type="number" min="0" name="theme_logo_media_id" value="<?= e((string)setting('theme.logo_media_id', '0')) ?>">
        </label>
        <label>Homepage layout
            <select name="theme_homepage_layout">
                <?php foreach (['mixed' => 'Mixed', 'articles' => 'Articles first', 'listings' => 'Listings first'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= setting('theme.homepage_layout', 'mixed') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Footer text
            <input type="text" name="theme_footer_text" value="<?= e((string)setting('theme.footer_text', '')) ?>" maxlength="240">
        </label>
        <label>Social links (one per line: Label | https://example.com)
            <?php $socialLinks = json_decode((string)setting('theme.social_links', '[]'), true); ?>
            <textarea name="theme_social_links" rows="3"><?php foreach (is_array($socialLinks) ? $socialLinks : [] as $socialLink) { echo e(($socialLink['label'] ?? '') . ' | ' . ($socialLink['url'] ?? '')) . "\n"; } ?></textarea>
        </label>
        <p class="muted">The header/footer preview updates on the next page load. Choose an uploaded logo by its media ID.</p>
    </section>

    <section class="panel">
        <h2>Signup</h2>
        <label>Signup mode
            <select name="signup_mode">
                <?php foreach (['open', 'restricted', 'invite', 'off'] as $m): ?>
                    <option value="<?= e($m) ?>" <?= setting('signup_mode') === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </section>

    <section class="panel">
        <h2>Comments</h2>
        <label class="inline">
            <input type="checkbox" name="comments_require_approval" <?= (string)setting('comments_require_approval') === '1' ? 'checked' : '' ?>>
            Require approval before comments are visible
        </label>
        <label>Comments per minute (per user)
            <input type="number" name="comments_per_minute" min="1" value="<?= e((string)setting('comments_per_minute')) ?>">
        </label>
    </section>

    <section class="panel">
        <h2>Media</h2>
        <label>Max upload size (bytes)
            <input type="number" name="media_max_upload_bytes" min="1" value="<?= e((string)setting('media_max_upload_bytes')) ?>">
        </label>
        <label>Max image width (px, 0 to skip resize)
            <input type="number" name="media_image_max_width" min="0" value="<?= e((string)setting('media_image_max_width')) ?>">
        </label>
    </section>

    <section class="panel">
        <h2>Features</h2>
        <p class="muted">Disabled features hide their admin nav and return 404 on their public routes.</p>
        <?php foreach ($featureToggles as $f): ?>
            <label class="inline">
                <input type="checkbox" name="feature_<?= e($f) ?>" <?= feature($f) ? 'checked' : '' ?>>
                <?= e($f) ?>
            </label>
        <?php endforeach; ?>
    </section>

    <button type="submit">Save settings</button>
</form>
<?php renderFooter();
