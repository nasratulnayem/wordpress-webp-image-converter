# WordPress WebP Image Converter

[![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-blue?logo=wordpress)](https://wordpress.org)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%202.0%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/nasratulnayem/wordpress-webp-image-converter/pulls)

**Effortlessly convert WordPress images to WebP from the dashboard. Serves WebP automatically to supported browsers — safe, fast, no originals lost.**

---

## Features

- **One-click scan** — counts all convertible JPEG (and optional PNG) images in your media library
- **Batch conversion** — processes images in configurable batches via AJAX, no server timeout worries
- **Automatic serving** — swaps image URLs to `.webp` for browsers that support it (via `Accept` header)
- **Srcset support** — converts `srcset` URLs so responsive images also benefit
- **Content rewriting** — optionally swaps hardcoded image URLs inside `the_content` and `post_thumbnail_html`
- **Safe by design** — originals are never deleted; a failed conversion won't break your site
- **Imagick + GD fallback** — works with either library
- **Full dashboard UI** — no WP-CLI, no external tools needed

---

## Screenshots

> _Screenshots will be added in a future release. Contributions welcome!_

```
┌─────────────────────────────────────────────┐
│  Effortless WebP Converter                   │
│                                              │
│  ┌──────────────┐  ┌──────────────┐          │
│  │ Status        │  │ Settings     │          │
│  │ Total: 142    │  │ Batch size: 10│         │
│  │ Converted: 89 │  │ Quality: 82  │          │
│  │ Progress: ██░ │  │ Include PNG ☑│         │
│  └──────────────┘  └──────────────┘          │
│  ┌──────────────────────────────────┐         │
│  │ Environment                      │         │
│  │ ✓ Imagick Available             │         │
│  │ ✓ GD WebP Available             │         │
│  └──────────────────────────────────┘         │
│  ┌──────────────────────────────────┐         │
│  │ Recent Log                       │         │
│  │ [12:34] Scan complete.           │         │
│  │ [12:35] Attachment 42 converted  │         │
│  └──────────────────────────────────┘         │
└─────────────────────────────────────────────┘
```

---

## Requirements

| Requirement         | Minimum        |
|---------------------|----------------|
| WordPress           | 6.0+           |
| PHP                 | 7.4+           |
| Image library       | Imagick (with WebP) **or** GD (`imagewebp()`) |

---

## Installation

1. Download the latest release from [GitHub Releases](https://github.com/nasratulnayem/wordpress-webp-image-converter/releases)
2. Upload the `effortless-webp-converter` folder to `/wp-content/plugins/`
3. Activate **Effortless WebP Converter** from the WordPress **Plugins** screen
4. Go to **Tools → WebP Converter**
5. Click **Scan Library** to count convertible images
6. Click **Start Conversion** to begin batch processing

### Quick install via WP-CLI

```bash
wp plugin install https://github.com/nasratulnayem/wordpress-webp-image-converter/releases/download/v0.1.0/effortless-webp-converter.zip --activate
wp eval 'do_action("ewc_scan");'
```

---

## Usage

### Dashboard

Navigate to **Tools → WebP Converter** in the WordPress admin sidebar.

| Action | Description |
|--------|-------------|
| **Scan Library** | Counts all convertible JPEG/PNG attachments in the media library |
| **Start Conversion** | Processes images in batches — runs until all are converted |
| **Reset Report** | Clears the conversion log and stats |

### Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Batch size | 10 | Images processed per AJAX request (1–50) |
| WebP quality | 82 | Output quality (40–100) |
| PNG conversion | On | Also convert PNG uploads to WebP |
| Hardcoded content images | On | Swap image URLs in post content and thumbnails |

---

## How it works

1. **Scan** — queries the `wp_posts` table for attachments with `post_mime_type IN ('image/jpeg', 'image/png')`
2. **Convert** — for each attachment, generates `.webp` copies of the original file and all registered thumbnail sizes using Imagick or GD
3. **Serve** — intercepts `wp_get_attachment_url`, `wp_calculate_image_srcset`, and optionally `the_content` / `post_thumbnail_html` to swap URLs when:
   - The browser sends `Accept: image/webp`
   - The corresponding `.webp` file exists on disk

Originals are **never modified or deleted**. The plugin only creates new `.webp` files alongside them.

---

## Development

```bash
# Clone the repo
git clone https://github.com/nasratulnayem/wordpress-webp-image-converter.git

# Install dependencies (if any)
# No build step required — this is a vanilla PHP plugin
```

### Coding standards

This plugin follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).

```bash
composer lint
composer lint:fix
```

---

## Frequently Asked Questions

### Does this delete my original images?

**No.** The plugin never deletes or modifies original files. It only creates new `.webp` copies alongside them.

### What if a conversion fails?

The plugin skips the failed file and continues with the rest. Your original image remains untouched, so your site is never affected.

### Does it work with CDNs?

Yes, as long as the CDN serves the `.webp` file when requested. The URL swap happens on the WordPress side before the CDN fetches the resource.

### Does it support PNG?

Yes. Enable **PNG conversion** in the settings. PNGs with transparency are handled correctly (Imagick preserves the alpha channel; GD converts palette to true colour and saves alpha).

### Will this work with page caching plugins?

Yes. The WebP serving logic runs during page rendering (via filters), so cached pages will serve the format the original request accepted. For best results, ensure your cache varies on the `Accept` header.

---

## Changelog

### 0.1.0 — Initial Release

- Scan media library for convertible JPEG/PNG images
- Batch conversion with configurable batch size and quality
- Automatic WebP serving via `wp_get_attachment_url` and `wp_calculate_image_srcset`
- Optional content URL rewriting (`the_content`, `post_thumbnail_html`)
- Dashboard admin page with progress bar, stats, log viewer
- Supports both Imagick and GD
- Safe mode — originals never modified

---

## Contributing

Contributions are welcome! Please open an [issue](https://github.com/nasratulnayem/wordpress-webp-image-converter/issues) or [pull request](https://github.com/nasratulnayem/wordpress-webp-image-converter/pulls).

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) for details.

---

## Support

- [GitHub Issues](https://github.com/nasratulnayem/wordpress-webp-image-converter/issues)
- [WordPress Plugin Support](https://wordpress.org/support/plugin/effortless-webp-converter)
