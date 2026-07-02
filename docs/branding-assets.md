# Branding & Site Images

Static branding and theme images live in **`public_html/assets/img/`**. This
folder is version-controlled and served directly by Apache (the `assets/`
folder only sets `Options -Indexes`, so images are publicly readable but not
listable). Keep **only shipped, non-sensitive assets** here.

Do **not** put user-uploaded media here — that belongs in `public_html/uploads/`
(the media library writes cover images and profile pictures there). `assets/img/`
is for the site's own identity: logos, favicons, social/share images, and
default banners.

## Conventions

- Filenames: lowercase, hyphen-separated, no spaces (e.g. `apple-touch-icon.png`).
- Prefer **SVG** for logos and icons — scalable, crisp on retina, and themeable
  via CSS. Use **PNG** for raster favicons and the Open Graph image, and **JPG**
  for photographic banners.
- Provide light/dark variants (or a single theme-neutral asset) where a logo or
  icon has poor contrast on one theme. The site supports a light/dark toggle.
- Apache serves `.svg` as `image/svg+xml` by default on MAMP/LAMP; verify if you
  add unusual extensions.

## The basic set

These are the images a themed install needs. "Required" entries should exist for
a polished default; the rest are optional enhancements.

| File | Dimensions | Format | Purpose | Wires into |
|------|-----------|--------|---------|------------|
| `logo.svg` | ~40px tall (vector) | SVG | Primary logo mark in the header brand link. Should read on light backgrounds. | `includes/layout.php` header `.brand` |
| `logo-dark.svg` | same as logo | SVG | Logo variant for dark theme (omit if `logo.svg` is theme-neutral). | `.brand` via `data-theme="dark"` |
| `logo-full.svg` | vector | SVG | Logo + wordmark lockup for footer, auth pages, and the setup wizard. Optional. | footer / `setup.php` brand |
| `favicon.ico` | 16/32/48 multi-size | ICO | Legacy favicon for browser tabs. | `<head>` in `layout.php` |
| `icon-32.png` | 32×32 | PNG | Standard favicon (hi-DPI tab). | `<head>` |
| `icon-16.png` | 16×16 | PNG | Legacy favicon size. | `<head>` |
| `apple-touch-icon.png` | 180×180 | PNG | iOS home-screen icon (no transparency). | `<head>` |
| `icon-192.png` | 192×192 | PNG | PWA manifest icon. | `site.webmanifest` |
| `icon-512.png` | 512×512 | PNG | PWA manifest icon / splash. | `site.webmanifest` |
| `og-default.png` | 1200×630 | PNG | Default social share image — the `og:image` fallback for pages/articles with no cover and for the homepage. Keep under ~300KB. | `og:image` fallback in `layout.php` |
| `default-cover.jpg` | ~1600×900 | JPG | Fallback cover image for articles/pages that have no cover media selected. | article/page cover fallback |
| `hero.jpg` | ~1600×900 | JPG/AVIF | Optional homepage hero image (the current homepage hero is text-only). | `index.php` `.hero` section |

### Optional extras

- `default-avatar.svg` — fallback user avatar. The UI currently renders initials
  for users with no profile picture (`renderAvatar()` in `layout.php`); an image
  is only needed if you prefer a glyph over initials.
- `auth-banner.jpg` — decorative banner for login/signup pages.
- `icon-maskable.svg` / `icon-maskable-512.png` — maskable PWA icon for adaptive
  Android icon display.
- `site.webmanifest` — (text, not an image) PWA manifest referencing
  `icon-192.png` / `icon-512.png` and the app name/theme color.

## Wiring (next step)

These images are **not yet referenced** in the templates. Once the base assets
exist, wire them in:

1. **Favicons + manifest** — add `<link>` tags to the `<head>` in
   `includes/layout.php` (favicon, apple-touch-icon, manifest) and to
   `public_html/setup.php`'s `<head>`.
2. **Header logo** — replace the text-only `.brand` link (`<?= e($appName) ?>`)
   in `layout.php` with an `<img src="/assets/img/logo.svg">` (keep the app name
   as `alt`), with a dark-theme variant via CSS.
3. **Default OG image** — in `layout.php`, fall back to `/assets/img/og-default.png`
   when a page/article has no `og_image` of its own.
4. **Default cover** — in `article.php` / `page.php`, fall back to
   `/assets/img/default-cover.jpg` when no cover media is set.

Keep the text app-name fallback (`$appName`) as the `alt`/fallback so the site
still renders correctly before images are added.

## Theming notes

- Logos and icons that must adapt to light/dark can ship as two files selected
  by `html[data-theme="..."]` in `site.css`, or as a single SVG that uses
  `currentColor` so it inherits theme color.
- Treat `assets/img/` as the themer's surface: swapping the files here (and
  adjusting `site.css`) is the primary way to rebrand a copied site.
