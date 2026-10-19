<?php

namespace App\Services;

use App\Models\ArticleTranslation;
use App\Models\MediaAsset;
use Illuminate\Support\Str;

/**
 * Renders the editor's JSON document (TipTap/ProseMirror schema) into
 * sanitized HTML on the server. Editors never write raw HTML: only the
 * whitelisted node and mark types below are emitted, every attribute is
 * escaped, and links are restricted to http(s)/mailto/relative URLs.
 */
class BodyRenderer
{
    /** @var array<int, array{id:string,text:string,level:int}> */
    protected array $toc = [];

    protected array $usedIds = [];

    protected string $locale = 'en';

    public function render(array|string|null $doc, string $locale = 'en'): array
    {
        $this->toc = [];
        $this->usedIds = [];
        $this->locale = $locale;

        if (is_string($doc)) {
            $doc = json_decode($doc, true);
        }

        if (! is_array($doc) || ($doc['type'] ?? null) !== 'doc') {
            return ['html' => '', 'toc' => [], 'text' => '', 'minutes' => 1];
        }

        $html = $this->children($doc);
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)))));
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text));

        return [
            'html' => $html,
            'toc' => $this->toc,
            'text' => $text,
            'minutes' => max(1, (int) ceil($words / 220)),
        ];
    }

    public function toc(): array
    {
        return $this->toc;
    }

    protected function children(array $node): string
    {
        $out = '';
        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                $out .= $this->node($child);
            }
        }

        return $out;
    }

    protected function node(array $n): string
    {
        $a = $n['attrs'] ?? [];

        return match ($n['type'] ?? '') {
            'paragraph' => '<p>'.$this->children($n).'</p>',
            'heading' => $this->heading($n),
            'text' => $this->text($n),
            'hardBreak' => '<br>',
            'bulletList' => '<ul>'.$this->children($n).'</ul>',
            'orderedList' => '<ol>'.$this->children($n).'</ol>',
            'listItem' => '<li>'.$this->children($n).'</li>',
            'blockquote' => '<blockquote>'.$this->children($n).'</blockquote>',
            'horizontalRule' => '<hr>',
            'image' => $this->image($a),
            'youtube' => $this->youtube($a),
            'table' => '<div class="table-wrap"><table>'.$this->children($n).'</table></div>',
            'tableRow' => '<tr>'.$this->children($n).'</tr>',
            'tableHeader' => '<th>'.$this->children($n).'</th>',
            'tableCell' => '<td>'.$this->children($n).'</td>',
            // Doogsan custom blocks
            'pullQuote' => $this->pullQuote($n, $a),
            'relatedStory' => $this->relatedStory($a),
            'factBox' => '<aside class="fact-box"><h4>'.e($a['title'] ?? __('Key facts')).'</h4>'.$this->children($n).'</aside>',
            'embed' => $this->embed($a),
            default => $this->children($n),
        };
    }

    protected function heading(array $n): string
    {
        $level = in_array((int) ($n['attrs']['level'] ?? 2), [2, 3, 4], true) ? (int) $n['attrs']['level'] : 2;
        $inner = $this->children($n);
        $plain = trim(html_entity_decode(strip_tags($inner)));
        $id = Str::slug($plain) ?: 'section';
        $base = $id;
        $i = 2;
        while (in_array($id, $this->usedIds, true)) {
            $id = $base.'-'.$i++;
        }
        $this->usedIds[] = $id;

        if ($level <= 3 && $plain !== '') {
            $this->toc[] = ['id' => $id, 'text' => $plain, 'level' => $level];
        }

        return "<h{$level} id=\"{$id}\">{$inner}</h{$level}>";
    }

    protected function text(array $n): string
    {
        $html = e($n['text'] ?? '');

        foreach ($n['marks'] ?? [] as $mark) {
            $html = match ($mark['type'] ?? '') {
                'bold' => "<strong>{$html}</strong>",
                'italic' => "<em>{$html}</em>",
                'underline' => "<u>{$html}</u>",
                'strike' => "<s>{$html}</s>",
                'code' => "<code>{$html}</code>",
                'subscript' => "<sub>{$html}</sub>",
                'superscript' => "<sup>{$html}</sup>",
                'link' => $this->link($html, $mark['attrs'] ?? []),
                default => $html,
            };
        }

        return $html;
    }

    protected function link(string $inner, array $a): string
    {
        $href = $this->safeUrl($a['href'] ?? '');
        if (! $href) {
            return $inner;
        }
        $external = str_starts_with($href, 'http') && ! str_starts_with($href, config('app.url'));
        $rel = $external ? ' rel="noopener nofollow" target="_blank"' : '';

        return '<a href="'.e($href).'"'.$rel.'>'.$inner.'</a>';
    }

    protected function image(array $a): string
    {
        $media = isset($a['mediaId']) ? MediaAsset::find($a['mediaId']) : null;
        $src = $media ? $media->url(1600) : $this->safeUrl($a['src'] ?? '');
        if (! $src) {
            return '';
        }
        $alt = $media ? $media->altFor($this->locale) : ($a['alt'] ?? '');
        $caption = $a['caption'] ?? ($media?->captionFor($this->locale));
        $credit = $media?->credit;
        $srcset = $media?->srcset();

        $img = '<img src="'.e($src).'" alt="'.e($alt).'" loading="lazy" decoding="async"'
            .($srcset ? ' srcset="'.e($srcset).'" sizes="(min-width: 1024px) 720px, 100vw"' : '')
            .($media?->width ? ' width="'.$media->width.'" height="'.$media->height.'"' : '')
            .' data-zoom>';

        $cap = '';
        if ($caption || $credit) {
            $cap = '<figcaption>'.e((string) $caption)
                .($credit ? ' <span class="credit">'.e($credit).'</span>' : '').'</figcaption>';
        }

        return '<figure class="story-figure">'.$img.$cap.'</figure>';
    }

    protected function youtube(array $a): string
    {
        $id = $this->youtubeId($a['src'] ?? '');
        if (! $id) {
            return '';
        }

        return '<figure class="story-embed"><div class="ratio-16x9"><iframe src="https://www.youtube-nocookie.com/embed/'.e($id)
            .'" title="YouTube video" loading="lazy" allow="accelerometer; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe></div></figure>';
    }

    protected function embed(array $a): string
    {
        $url = $this->safeUrl($a['url'] ?? '');
        if (! $url) {
            return '';
        }
        if ($id = $this->youtubeId($url)) {
            return $this->youtube(['src' => $url]);
        }
        // Interactive visualisations are allowed only from configured hosts.
        $host = parse_url($url, PHP_URL_HOST);
        $allowed = array_filter(array_map('trim', explode(',', (string) setting('embed_hosts', 'datawrapper.dwcdn.net,flo.uri.sh,www.google.com'))));
        if (in_array($host, $allowed, true)) {
            $h = (int) ($a['height'] ?? 480);

            return '<figure class="story-embed"><iframe src="'.e($url).'" title="'.e($a['title'] ?? 'Embedded content')
                .'" loading="lazy" style="width:100%;height:'.max(200, min($h, 1200)).'px;border:0" sandbox="allow-scripts allow-same-origin allow-popups"></iframe>'
                .(! empty($a['caption']) ? '<figcaption>'.e($a['caption']).'</figcaption>' : '').'</figure>';
        }

        return '<p class="link-preview"><a href="'.e($url).'" rel="noopener nofollow" target="_blank">'.e($a['title'] ?? $url).'</a></p>';
    }

    protected function pullQuote(array $n, array $a): string
    {
        $cite = ! empty($a['cite']) ? '<cite>'.e($a['cite']).'</cite>' : '';

        return '<figure class="pull-quote"><blockquote>'.$this->children($n).'</blockquote>'.$cite.'</figure>';
    }

    protected function relatedStory(array $a): string
    {
        $t = ArticleTranslation::with('article.featuredMedia', 'article.category.translations')
            ->where('article_id', $a['articleId'] ?? 0)
            ->where('locale', $this->locale)->live()->first();

        if (! $t) {
            return '';
        }
        $img = $t->article->featuredMedia?->url(480);

        return '<aside class="related-inline"><a href="'.e($t->url()).'">'
            .($img ? '<img src="'.e($img).'" alt="" loading="lazy">' : '')
            .'<span><small>'.e(__('Read more')).'</small><strong>'.e($t->title).'</strong></span></a></aside>';
    }

    protected function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true) ? $url : null;
    }

    protected function youtubeId(string $url): ?string
    {
        if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Build an editor document from plain paragraphs (seeders, imports). */
    public static function docFromParagraphs(array $blocks): array
    {
        $content = [];
        foreach ($blocks as $block) {
            if (is_array($block)) {
                $content[] = $block;

                continue;
            }
            if (str_starts_with($block, '## ')) {
                $content[] = ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => substr($block, 3)]]];
            } elseif (str_starts_with($block, '> ')) {
                $content[] = ['type' => 'pullQuote', 'attrs' => ['cite' => null], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => substr($block, 2)]]]]];
            } else {
                $content[] = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $block]]];
            }
        }

        return ['type' => 'doc', 'content' => $content];
    }
}
