<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Converts legacy HTML (e.g. WordPress post content) into the editor's JSON
 * document. Only structure the newsroom editor understands survives:
 * paragraphs, headings, lists, quotes, images, tables, YouTube embeds and
 * basic inline marks. Scripts, styles, shortcodes and page-builder markup
 * are discarded, so imported stories are as safe as ones written in the CMS.
 */
class HtmlToDocument
{
    public function convert(string $html): array
    {
        $html = $this->clean($html);
        if (trim(strip_tags($html, '<img><iframe>')) === '') {
            return ['type' => 'doc', 'content' => []];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        $content = $body ? $this->blocks($body) : [];

        return ['type' => 'doc', 'content' => array_values(array_filter($content))];
    }

    protected function clean(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|form)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('/\[\/?[a-z_][a-z0-9_-]*[^\]]*\]/i', '', $html); // WP shortcodes / WPBakery
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        return trim($html);
    }

    /** @return array<int, array> */
    protected function blocks(DOMNode $parent): array
    {
        $out = [];
        $inline = [];
        $flush = function () use (&$inline, &$out) {
            $inline = $this->trimInline($inline);
            if ($inline) {
                $out[] = ['type' => 'paragraph', 'content' => $inline];
            }
            $inline = [];
        };

        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMText) {
                $text = preg_replace('/\s+/u', ' ', $node->textContent);
                if (trim($text) !== '') {
                    $inline[] = ['type' => 'text', 'text' => $text];
                }

                continue;
            }
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            switch ($tag) {
                case 'p':
                    $flush();
                    $images = $this->images($node);
                    $inl = $this->trimInline($this->inline($node));
                    if ($inl) {
                        $out[] = ['type' => 'paragraph', 'content' => $inl];
                    }
                    array_push($out, ...$images);
                    break;
                case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                    $flush();
                    $inl = $this->trimInline($this->inline($node));
                    if ($inl) {
                        $out[] = ['type' => 'heading', 'attrs' => ['level' => min(4, max(2, (int) $tag[1]))], 'content' => $inl];
                    }
                    break;
                case 'ul': case 'ol':
                    $flush();
                    $items = [];
                    foreach ($node->childNodes as $li) {
                        if ($li instanceof DOMElement && strtolower($li->tagName) === 'li') {
                            $inner = $this->blocks($li) ?: [['type' => 'paragraph']];
                            $items[] = ['type' => 'listItem', 'content' => $inner];
                        }
                    }
                    if ($items) {
                        $out[] = ['type' => $tag === 'ul' ? 'bulletList' : 'orderedList', 'content' => $items];
                    }
                    break;
                case 'blockquote':
                    $flush();
                    $class = $node->getAttribute('class');
                    if (str_contains($class, 'twitter-tweet') || str_contains($class, 'instagram-media') || str_contains($class, 'tiktok-embed')) {
                        $link = $this->lastLink($node);
                        if ($link) {
                            $out[] = ['type' => 'embed', 'attrs' => ['url' => $link]];
                        }
                        break;
                    }
                    $inner = $this->blocks($node);
                    if ($inner) {
                        $out[] = ['type' => 'blockquote', 'content' => $inner];
                    }
                    break;
                case 'figure':
                    $flush();
                    $iframe = $node->getElementsByTagName('iframe')->item(0);
                    if ($iframe && ($yt = $this->youtube($iframe->getAttribute('src')))) {
                        $out[] = $yt;
                        break;
                    }
                    $table = $node->getElementsByTagName('table')->item(0);
                    if ($table) {
                        $out[] = $this->table($table);
                        break;
                    }
                    $caption = $node->getElementsByTagName('figcaption')->item(0)?->textContent;
                    array_push($out, ...$this->images($node, $caption ? trim($caption) : null));
                    break;
                case 'img':
                    $flush();
                    array_push($out, ...$this->images($node));
                    break;
                case 'iframe':
                    $flush();
                    if ($yt = $this->youtube($node->getAttribute('src'))) {
                        $out[] = $yt;
                    }
                    break;
                case 'table':
                    $flush();
                    $out[] = $this->table($node);
                    break;
                case 'hr':
                    $flush();
                    $out[] = ['type' => 'horizontalRule'];
                    break;
                case 'br':
                    $inline[] = ['type' => 'hardBreak'];
                    break;
                case 'div': case 'section': case 'article': case 'main': case 'center': case 'span':
                    if ($tag === 'span' || ! $this->hasBlockChildren($node)) {
                        if ($tag === 'span') {
                            array_push($inline, ...$this->inline($node));
                            break;
                        }
                        $flush();
                        $inl = $this->trimInline($this->inline($node));
                        if ($inl) {
                            $out[] = ['type' => 'paragraph', 'content' => $inl];
                        }
                        array_push($out, ...$this->images($node));
                        break;
                    }
                    $flush();
                    array_push($out, ...$this->blocks($node));
                    break;
                default:
                    array_push($inline, ...$this->inline($node, [], $tag));
            }
        }
        $flush();

        return $out;
    }

    protected function hasBlockChildren(DOMElement $el): bool
    {
        foreach ($el->childNodes as $c) {
            if ($c instanceof DOMElement && in_array(strtolower($c->tagName), ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'blockquote', 'figure', 'table', 'section', 'iframe', 'hr'])) {
                return true;
            }
        }

        return false;
    }

    /** Inline content with marks. */
    protected function inline(DOMNode $node, array $marks = [], ?string $selfTag = null): array
    {
        $children = $selfTag !== null ? [$node] : iterator_to_array($node->childNodes);
        $out = [];
        foreach ($children as $c) {
            if ($c instanceof DOMText) {
                $text = preg_replace('/\s+/u', ' ', $c->textContent);
                if ($text !== '') {
                    $out[] = ['type' => 'text', 'text' => $text] + ($marks ? ['marks' => $marks] : []);
                }

                continue;
            }
            if (! $c instanceof DOMElement) {
                continue;
            }
            $t = strtolower($c->tagName);
            $m = $marks;
            switch ($t) {
                case 'strong': case 'b': $m[] = ['type' => 'bold']; break;
                case 'em': case 'i': $m[] = ['type' => 'italic']; break;
                case 'u': $m[] = ['type' => 'underline']; break;
                case 's': case 'del': case 'strike': $m[] = ['type' => 'strike']; break;
                case 'sub': $m[] = ['type' => 'subscript']; break;
                case 'sup': $m[] = ['type' => 'superscript']; break;
                case 'code': $m[] = ['type' => 'code']; break;
                case 'a':
                    $href = trim($c->getAttribute('href'));
                    if (preg_match('#^(https?://|mailto:|/)#i', $href)) {
                        $m[] = ['type' => 'link', 'attrs' => ['href' => $href]];
                    }
                    break;
                case 'br':
                    $out[] = ['type' => 'hardBreak'];

                    continue 2;
                case 'img': case 'iframe': case 'script': case 'style': case 'figure':
                    continue 2;
            }
            array_push($out, ...$this->inline($c, $m));
        }

        return $out;
    }

    protected function trimInline(array $inline): array
    {
        while ($inline && ($inline[0]['type'] === 'hardBreak' || ($inline[0]['type'] === 'text' && trim($inline[0]['text']) === ''))) {
            array_shift($inline);
        }
        while ($inline && (end($inline)['type'] === 'hardBreak' || (end($inline)['type'] === 'text' && trim(end($inline)['text']) === ''))) {
            array_pop($inline);
        }
        if ($inline && $inline[0]['type'] === 'text') {
            $inline[0]['text'] = ltrim($inline[0]['text']);
        }
        $last = count($inline) - 1;
        if ($last >= 0 && $inline[$last]['type'] === 'text') {
            $inline[$last]['text'] = rtrim($inline[$last]['text']);
        }

        return array_values(array_filter($inline, fn ($n) => $n['type'] !== 'text' || $n['text'] !== ''));
    }

    /** Image nodes found inside an element (src kept; the importer may swap it for a media id). */
    protected function images(DOMElement $el, ?string $caption = null): array
    {
        $imgs = strtolower($el->tagName) === 'img' ? [$el] : iterator_to_array($el->getElementsByTagName('img'));
        $out = [];
        foreach ($imgs as $img) {
            $src = $img->getAttribute('data-src') ?: $img->getAttribute('src');
            if (! preg_match('#^https?://#i', $src)) {
                continue;
            }
            $out[] = ['type' => 'image', 'attrs' => array_filter([
                'src' => $src,
                'alt' => trim($img->getAttribute('alt')) ?: null,
                'caption' => $caption,
            ])];
        }

        return $out;
    }

    protected function youtube(string $src): ?array
    {
        return preg_match('#(youtube\.com|youtube-nocookie\.com|youtu\.be)/#i', $src) ? ['type' => 'youtube', 'attrs' => ['src' => $src]] : null;
    }

    protected function lastLink(DOMElement $el): ?string
    {
        $links = $el->getElementsByTagName('a');
        for ($i = $links->length - 1; $i >= 0; $i--) {
            $h = $links->item($i)->getAttribute('href');
            if (preg_match('#^https://#', $h)) {
                return $h;
            }
        }

        return null;
    }

    protected function table(DOMElement $table): array
    {
        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'])) {
                    $inl = $this->trimInline($this->inline($cell));
                    $cells[] = [
                        'type' => strtolower($cell->tagName) === 'th' ? 'tableHeader' : 'tableCell',
                        'content' => [['type' => 'paragraph'] + ($inl ? ['content' => $inl] : [])],
                    ];
                }
            }
            if ($cells) {
                $rows[] = ['type' => 'tableRow', 'content' => $cells];
            }
        }

        return $rows ? ['type' => 'table', 'content' => $rows] : [];
    }
}
