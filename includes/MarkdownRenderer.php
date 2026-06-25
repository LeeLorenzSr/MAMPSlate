<?php
declare(strict_types=1);

/**
 * Minimal, dependency-free Markdown renderer covering the common subset:
 * headings, paragraphs, bold, italic, inline code, fenced code blocks,
 * links, images, blockquotes, unordered/ordered lists, and horizontal rules.
 *
 * Output is XSS-safe: raw HTML in the source is escaped, and link/image URLs
 * are restricted to http(s), mailto, and safe relative URLs. This is the single
 * seam to replace with Parsedown or a WYSIWYG + HTML Purifier path later.
 */
final class MarkdownRenderer
{
    public function render(string $markdown): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $text);
        $html = [];
        $count = count($lines);
        $i = 0;

        while ($i < $count) {
            $line = $lines[$i];

            if (trim($line) === '') {
                $i++;
                continue;
            }

            // Fenced code block.
            if (preg_match('/^```(.*)$/', $line, $m)) {
                $code = [];
                $i++;
                while ($i < $count && !preg_match('/^```$/', $lines[$i])) {
                    $code[] = $lines[$i];
                    $i++;
                }
                $i++; // skip closing fence
                $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $code), ENT_QUOTES, 'UTF-8') . '</code></pre>';
                continue;
            }

            // Heading.
            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
                $level = strlen($m[1]);
                $html[] = '<h' . $level . '>' . $this->inline($m[2]) . '</h' . $level . '>';
                $i++;
                continue;
            }

            // Horizontal rule.
            if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', trim($line))) {
                $html[] = '<hr>';
                $i++;
                continue;
            }

            // Blockquote.
            if (preg_match('/^>\s?(.*)$/', $line)) {
                $quote = [];
                while ($i < $count && preg_match('/^>\s?(.*)$/', $lines[$i], $m)) {
                    $quote[] = $m[1];
                    $i++;
                }
                $html[] = '<blockquote>' . $this->inline(implode("\n", $quote)) . '</blockquote>';
                continue;
            }

            // List.
            if (preg_match('/^([-*]|\d+\.)\s+(.*)$/', $line, $m)) {
                $ordered = preg_match('/^\d+\./', $m[1]) === 1;
                $items = [];
                while ($i < $count && preg_match('/^([-*]|\d+\.)\s+(.*)$/', $lines[$i], $m)) {
                    $items[] = $m[2];
                    $i++;
                }
                $tag = $ordered ? 'ol' : 'ul';
                $list = '<' . $tag . '>';
                foreach ($items as $item) {
                    $list .= '<li>' . $this->inline($item) . '</li>';
                }
                $list .= '</' . $tag . '>';
                $html[] = $list;
                continue;
            }

            // Paragraph: gather consecutive plain lines.
            $para = [];
            while (
                $i < $count
                && trim($lines[$i]) !== ''
                && !preg_match('/^```/', $lines[$i])
                && !preg_match('/^#{1,6}\s+/', $lines[$i])
                && !preg_match('/^(-{3,}|\*{3,}|_{3,})$/', trim($lines[$i]))
                && !preg_match('/^>\s?/', $lines[$i])
                && !preg_match('/^([-*]|\d+\.)\s+/', $lines[$i])
            ) {
                $para[] = $lines[$i];
                $i++;
            }
            $html[] = '<p>' . $this->inline(implode("\n", $para)) . '</p>';
        }

        return implode("\n", $html);
    }

    private function inline(string $text): string
    {
        // Escape all HTML first so no raw markup passes through.
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Inline code spans (protect from further inline processing).
        $codes = [];
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
            $codes[] = '<code>' . $m[1] . '</code>';
            return "\x00CODE" . (count($codes) - 1) . "\x00";
        }, $text);

        // Images: ![alt](url)
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($m) {
            $url = $this->safeUrl($m[2]);
            if ($url === null) {
                return htmlspecialchars($m[0], ENT_QUOTES, 'UTF-8');
            }
            return '<img src="' . $url . '" alt="' . trim($m[1]) . '">';
        }, $text);

        // Links: [text](url)
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
            $url = $this->safeUrl($m[2]);
            if ($url === null) {
                return htmlspecialchars($m[0], ENT_QUOTES, 'UTF-8');
            }
            return '<a href="' . $url . '">' . $m[1] . '</a>';
        }, $text);

        // Bold then italic.
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*([^*\n]+)\*/', '<em>$1</em>', $text);

        // Soft line breaks within a paragraph.
        $text = nl2br($text, false);

        // Restore code spans.
        $text = preg_replace_callback('/\x00CODE(\d+)\x00/', function ($m) use (&$codes) {
            return $codes[(int)$m[1]] ?? '';
        }, $text);

        return $text;
    }

    /**
     * Return a URL-safe for use in href/src, or null if the scheme is not allowed.
     * Input has already been HTML-escaped, so & is &amp; etc.
     */
    private function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // Decode entities produced by htmlspecialchars so we can inspect the raw scheme.
        $raw = htmlspecialchars_decode($url, ENT_QUOTES);

        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $raw, $m)) {
            $scheme = strtolower($m[0]);
            if (!in_array($scheme, ['http:', 'https:', 'mailto:'], true)) {
                return null;
            }
            return $url;
        }

        // Relative URL (path, query, fragment). Reject any embedded scheme via //.
        if (str_starts_with($raw, '//')) {
            return null;
        }

        return $url;
    }
}
