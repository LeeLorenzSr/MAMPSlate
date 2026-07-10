<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$currentUser = $auth->requireCapability('settings.manage');
$logo = null;
$logoId = (int)setting('theme.logo_media_id', 0);
if ($logoId > 0) { $logo = $media->findById($logoId); }
$accent = (string)setting('theme.accent_color', '#2f6fec');
$font = (string)setting('theme.font_family', 'montserrat');
$logoPath = $logo && str_starts_with((string)$logo['mime_type'], 'image/') ? '/uploads/' . $logo['stored_name'] : '/assets/img/logo.png';
renderHeader('Theme preview', $currentUser);
?>
<section class="panel"><h2>Brand assets</h2><p class="muted">Current saved settings only; update values at <a href="/admin/settings">Site settings</a>.</p><div class="theme-preview-grid"><div class="theme-swatch theme-swatch-light"><img src="<?= e($logoPath) ?>" alt="Current logo"><strong><?= e(setting('site.name')) ?></strong><p style="color:<?= e($accent) ?>">Accent preview</p><span style="font-family:<?= e($font === 'serif' ? 'Georgia, serif' : 'inherit') ?>">Typography sample</span></div><div class="theme-swatch theme-swatch-dark"><img src="<?= e($logoPath) ?>" alt="Current logo"><strong><?= e(setting('site.name')) ?></strong><p style="color:<?= e($accent) ?>">Accent preview</p><span>Dark mode logo/background check</span></div><div><h3>Open Graph</h3><img class="theme-og-preview" src="/assets/img/og-default.png" alt="Default social share image"><p class="muted">Per-content cover images override this default.</p></div><div><h3>Favicon</h3><p><img src="/assets/img/icon-32.png" width="32" height="32" alt="Current favicon"> <img src="/assets/img/apple-touch-icon.png" width="48" height="48" alt="Apple touch icon"></p><p class="muted">Favicon files remain managed in `public_html/assets/img/`.</p></div></div></section>
<?php renderFooter();
