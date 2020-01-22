<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Service;

class SummaryService
{
    private const MIN_SUMMARY_CHARS = 100;

    /**
     * Extracts a lead-paragraph summary from raw feed HTML.
     * Tries `<p>` tags first, then `<br>`-split lines; falls back to $fallback or the full $content.
     */
    public function summarize(string $content, ?string $fallback = null): string
    {
        $dom = new \DOMDocument();
        $clip = '';

        // Try to create a valid summary until it's long enough
        if ($dom->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            $paragraphs = $dom->getElementsByTagName('p');
            $reached = false;
            foreach ($paragraphs as $p) {
                $pHtml = $dom->saveHTML($p);
                // Split at visual paragraph breaks (<br><br>); use only the first section if it's long enough
                if (preg_match('@^<p\b[^>]*>(.*?)<br\b[^>]*/?>\s*<br\b[^>]*/?>@si', $pHtml, $m)
                    && mb_strlen(strip_tags($m[1])) >= self::MIN_SUMMARY_CHARS) {
                    $pHtml = '<p>' . $m[1] . '</p>';
                }
                $clip .= $pHtml;
                if (mb_strlen($clip) >= self::MIN_SUMMARY_CHARS) {
                    $reached = true;
                    break;
                }
            }
            // Discard if we never reached the summary threshold (e.g. only tiny paragraphs)
            if (!$reached) {
                $clip = '';
            }
        }

        // Paragraph breaking failed, try something else
        if (!$clip) {
            $tmp = preg_split("@<br(.*?)>|\R+@si", $content, 3, PREG_SPLIT_NO_EMPTY);
            foreach (array_slice($tmp, 0, 2) as $chunk) {
                if (mb_strlen(strip_tags($chunk)) < self::MIN_SUMMARY_CHARS) {
                    continue;
                }
                $clip = trim($chunk);
                break;
            }
        }

        // All else failed
        if (!$clip) {
            $clip = $fallback ?? $content;
        }

        return trim($this->cleanContent($clip));
    }

    /**
     * Sanitizes raw feed HTML: removes script/style blocks, img tags, and all
     * tag attributes, keeping only safe inline/block elements. Collapses runs of <br>.
     */
    public function cleanContent(string $raw): string
    {
        $raw = preg_replace(
            ['@<script[^>]*?>.*?</script>@si', '@<style[^>]*?>.*?</style>@si'],
            '',
            $raw
        );
        // Unwrap <a> tags before strip_tags so inner text is preserved
        $raw = preg_replace('@<a[^>]*>(.*?)</a>@si', '$1', $raw);
        $raw = preg_replace('@<img\b[^>]*/?>@si', '', $raw);
        $raw = strip_tags($raw, '<p><br><b><strong><em><small><i><code><ol><ul><li>');
        $raw = preg_replace('@<([a-z][a-z0-9]*)\b[^>]*>@i', '<$1>', $raw);
        $raw = preg_replace(['@<p>\s*</p>@si'], '', $raw);
        return preg_replace('@(?:<br>){2,}@si', '<br>', $raw);
    }
}
