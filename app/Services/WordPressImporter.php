<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Redirect;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Imports posts from a WordPress site through its public REST API
 * (/wp-json/wp/v2/posts). Each post is filed into the Somali or English
 * newsroom by language detection; authors, sections, tags and images come
 * across, and a 301 redirect is created from the old WordPress URL.
 *
 * Re-running is safe: posts already imported (matched by WordPress id)
 * are skipped, or refreshed with --update.
 */
class WordPressImporter
{
    /** WordPress category slug => Doogsan section key. Unmapped categories become tags. */
    public const CATEGORY_MAP = [
        'politics' => 'politics', 'siyaasad' => 'politics', 'siyaasadda' => 'politics',
        'somalia' => 'somalia', 'soomaaliya' => 'somalia',
        'puntland' => 'puntland',
        'world' => 'world', 'caalamka' => 'world', 'international' => 'world',
        'africa' => 'africa', 'afrika' => 'africa',
        'business' => 'business', 'economy' => 'business', 'dhaqaalaha' => 'business', 'ganacsi' => 'business',
        'tech' => 'technology', 'technology' => 'technology', 'tiknoolajiyad' => 'technology',
        'health' => 'health', 'caafimaad' => 'health', 'caafimaadka' => 'health',
        'education' => 'education', 'waxbarasho' => 'education',
        'environment' => 'environment', 'climate' => 'environment', 'deegaanka' => 'environment',
        'security' => 'security', 'amni' => 'security', 'amniga' => 'security',
        'sports' => 'sports', 'sport' => 'sports', 'ciyaaraha' => 'sports',
    ];

    /** WordPress category slug => Doogsan format. */
    public const FORMAT_MAP = ['opinion' => 'opinion', 'aragti' => 'opinion', 'analysis' => 'analysis', 'interview' => 'interview', 'editorial' => 'editorial'];

    protected const SO_WORDS = ['iyo', 'waa', 'ka', 'ku', 'oo', 'ee', 'ayaa', 'ah', 'uu', 'ay', 'aan', 'wuxuu', 'waxay', 'waxaa', 'kale', 'dowladda', 'xukuumadda', 'madaxweyne', 'magaalada', 'gobolka', 'sidoo', 'kadib', 'inta', 'soo', 'ugu', 'kala', 'lagu', 'loo', 'hore', 'shir', 'warar', 'maanta', 'dalka'];

    protected const EN_WORDS = ['the', 'and', 'of', 'to', 'is', 'in', 'for', 'on', 'that', 'with', 'was', 'as', 'by', 'are', 'said', 'from', 'has', 'have', 'it', 'at', 'this', 'which', 'were', 'will', 'its', 'been', 'their', 'an', 'be', 'government', 'president'];

    protected array $categoryIds = [];

    protected array $log = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'images' => 0, 'redirects' => 0];

    public function __construct(
        protected HtmlToDocument $html,
        protected BodyRenderer $renderer,
        protected ArticleService $articles,
        protected MediaService $media,
    ) {}

    /**
     * @param  callable|null  $progress  fn(array $row) called per post
     * @return array{rows: array, counts: array}
     */
    public function run(string $siteUrl, array $options = [], ?callable $progress = null): array
    {
        $dry = (bool) ($options['dry_run'] ?? false);
        $update = (bool) ($options['update'] ?? false);
        $limit = (int) ($options['limit'] ?? 0);
        $since = ! empty($options['since']) ? Carbon::parse($options['since']) : null;
        $withImages = ! ($options['no_images'] ?? false);
        $status = in_array($options['status'] ?? 'published', ['published', 'draft'], true) ? $options['status'] ?? 'published' : 'published';
        $force = $options['force_edition'] ?? null;

        $base = rtrim($siteUrl, '/');
        $rows = [];
        $page = 1;
        $done = 0;

        if (! empty($options['wxr'])) {
            return $this->runFromExport($base, $options['wxr'], compact('dry', 'update', 'withImages', 'status', 'force', 'limit', 'since'), $progress, $options['uploads_dir'] ?? null);
        }

        do {
            $query = ['per_page' => 50, 'page' => $page, '_embed' => 1, 'orderby' => 'date', 'order' => 'asc'];
            if ($since) {
                $query['after'] = $since->toIso8601String();
            }
            $res = $this->fetchPosts($base, $query);

            if ($res->status() === 400 && $page > 1) {
                break; // past the last page
            }
            if (! $res->successful()) {
                throw new \RuntimeException("WordPress API returned HTTP {$res->status()} for page {$page}");
            }
            $posts = $res->json() ?: [];
            $totalPages = (int) ($res->header('X-WP-TotalPages') ?: 1);

            foreach ($posts as $post) {
                if ($limit && $done >= $limit) {
                    break 2;
                }
                $done++;
                try {
                    $row = $this->importPost($post, $base, compact('dry', 'update', 'withImages', 'status', 'force'));
                } catch (\Throwable $e) {
                    $this->log['failed']++;
                    $row = ['id' => $post['id'] ?? null, 'title' => Str::limit(html_entity_decode(strip_tags($post['title']['rendered'] ?? '')), 60), 'edition' => '-', 'action' => 'failed', 'note' => Str::limit($e->getMessage(), 120)];
                }
                $rows[] = $row;
                $progress && $progress($row);
            }
            $page++;
        } while ($page <= $totalPages);

        if (! $dry && ($this->log['created'] || $this->log['updated'])) {
            AuditLog::record('import.wordpress', "WordPress import from {$base}: {$this->log['created']} created, {$this->log['updated']} updated");
        }

        return ['rows' => $rows, 'counts' => $this->log];
    }

    /** Local copy of wp-content/uploads, used instead of downloading images. */
    protected ?string $uploadsDir = null;

    /**
     * Import from a WordPress export file (WP Admin → Tools → Export → Posts),
     * for sites where the REST API is blocked. No database credentials needed.
     */
    protected function runFromExport(string $base, string $file, array $o, ?callable $progress, ?string $uploadsDir): array
    {
        if (! is_file($file)) {
            throw new \RuntimeException("Export file not found: {$file}");
        }
        $this->uploadsDir = $uploadsDir && is_dir($uploadsDir) ? rtrim($uploadsDir, '/') : null;

        $xml = simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        if (! $xml || ! isset($xml->channel)) {
            throw new \RuntimeException('Not a WordPress export (WXR) file.');
        }
        $ns = $xml->getNamespaces(true);
        $wpNs = $ns['wp'] ?? 'http://wordpress.org/export/1.2/';

        // Attachments first, so featured images can be resolved by id.
        $attachments = [];
        foreach ($xml->channel->item as $item) {
            $wp = $item->children($wpNs);
            if ((string) $wp->post_type !== 'attachment') {
                continue;
            }
            $meta = [];
            foreach ($wp->postmeta as $m) {
                $meta[(string) $m->meta_key] = (string) $m->meta_value;
            }
            $attachments[(string) $wp->post_id] = [
                'source_url' => (string) $wp->attachment_url,
                'alt_text' => $meta['_wp_attachment_image_alt'] ?? '',
                'caption' => ['rendered' => (string) $item->children($ns['excerpt'] ?? 'http://wordpress.org/export/1.2/excerpt/')->encoded],
                'title' => ['rendered' => (string) $item->title],
            ];
        }

        $rows = [];
        $done = 0;
        foreach ($xml->channel->item as $item) {
            $wp = $item->children($wpNs);
            if ((string) $wp->post_type !== 'post' || (string) $wp->status !== 'publish') {
                continue;
            }
            $date = (string) $wp->post_date;
            if ($o['since'] && Carbon::parse($date)->lt($o['since'])) {
                continue;
            }
            if ($o['limit'] && $done >= $o['limit']) {
                break;
            }
            $done++;

            $meta = [];
            foreach ($wp->postmeta as $m) {
                $meta[(string) $m->meta_key] = (string) $m->meta_value;
            }
            $cats = $tags = [];
            foreach ($item->category as $c) {
                $domain = (string) $c['domain'];
                $term = ['taxonomy' => $domain, 'slug' => (string) $c['nicename'], 'name' => (string) $c];
                if ($domain === 'category') {
                    $cats[] = $term;
                } elseif ($domain === 'post_tag') {
                    $tags[] = $term;
                }
            }
            $gmt = (string) $wp->post_date_gmt;
            $gmt = ($gmt && ! str_starts_with($gmt, '0000')) ? $gmt : $date;
            $thumb = $meta['_thumbnail_id'] ?? null;

            $post = [
                'id' => (string) $wp->post_id,
                'status' => 'publish',
                'date' => $date,
                'date_gmt' => $gmt,
                'modified_gmt' => $gmt,
                'link' => (string) $item->link,
                'title' => ['rendered' => (string) $item->title],
                'excerpt' => ['rendered' => (string) $item->children($ns['excerpt'] ?? 'http://wordpress.org/export/1.2/excerpt/')->encoded],
                'content' => ['rendered' => $this->autop((string) $item->children($ns['content'] ?? 'http://purl.org/rss/1.0/modules/content/')->encoded)],
                '_embedded' => [
                    'author' => [['name' => (string) $item->children($ns['dc'] ?? 'http://purl.org/dc/elements/1.1/')->creator]],
                    'wp:featuredmedia' => ($thumb && isset($attachments[$thumb])) ? [$attachments[$thumb]] : [],
                    'wp:term' => [$cats, $tags],
                ],
            ];

            try {
                $row = $this->importPost($post, $base, ['dry' => $o['dry'], 'update' => $o['update'], 'withImages' => $o['withImages'], 'status' => $o['status'], 'force' => $o['force']]);
            } catch (\Throwable $e) {
                $this->log['failed']++;
                $row = ['id' => $post['id'], 'title' => Str::limit((string) $item->title, 60), 'edition' => '-', 'action' => 'failed', 'note' => Str::limit($e->getMessage(), 120)];
            }
            $rows[] = $row;
            $progress && $progress($row);
        }

        if (! $o['dry'] && ($this->log['created'] || $this->log['updated'])) {
            AuditLog::record('import.wordpress', "WordPress import (export file): {$this->log['created']} created, {$this->log['updated']} updated");
        }

        return ['rows' => $rows, 'counts' => $this->log];
    }

    /** Minimal wpautop: raw WordPress content separates paragraphs with blank lines. */
    protected function autop(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $out = '';
        foreach (preg_split('/\n\s*\n/', $text) as $b) {
            $b = trim($b);
            if ($b === '') {
                continue;
            }
            $out .= preg_match('#^<(p|div|h[1-6]|ul|ol|li|blockquote|figure|table|iframe|hr|pre|section|!--)#i', $b)
                ? $b."\n"
                : '<p>'.nl2br($b, false)."</p>\n";
        }

        return $out;
    }

    /** Endpoint style that worked (pretty /wp-json/ or ?rest_route= when permalinks/REST rewrites are off). */
    protected ?string $endpoint = null;

    protected function fetchPosts(string $base, array $query): \Illuminate\Http\Client\Response
    {
        $styles = $this->endpoint ? [$this->endpoint] : ['pretty', 'rest_route', 'index_rest_route'];
        $last = null;
        foreach ($styles as $style) {
            [$url, $q] = match ($style) {
                'pretty' => [$base.'/wp-json/wp/v2/posts', $query],
                'rest_route' => [$base.'/', ['rest_route' => '/wp/v2/posts'] + $query],
                'index_rest_route' => [$base.'/index.php', ['rest_route' => '/wp/v2/posts'] + $query],
            };
            $last = Http::timeout(40)->retry(2, 1500, throw: false)->acceptJson()
                // Some security plugins reject unknown user agents.
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; DoogsanMedia-Importer/1.0; +https://doogsanmedia.com)'])
                ->get($url, $q);
            if ($last->successful() && is_array($last->json())) {
                $this->endpoint = $style;

                return $last;
            }
            if ($this->endpoint) {
                return $last; // e.g. 400 past the last page
            }
        }

        return $last;
    }

    public function detectLanguage(string $text): array
    {
        $words = preg_split('/[^\p{L}\']+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $so = $en = 0;
        $soSet = array_flip(self::SO_WORDS);
        $enSet = array_flip(self::EN_WORDS);
        foreach ($words as $w) {
            if (isset($soSet[$w])) {
                $so++;
            }
            if (isset($enSet[$w])) {
                $en++;
            }
            // Somali orthography: doubled vowels and "dh/kh/sh" with x/c are rare in English.
            if (preg_match('/(aa|ii|uu|oo|ee).*(aa|ii|uu|oo|ee)|^x|dh$/u', $w)) {
                $so += 0.25;
            }
        }
        $total = max(1, $so + $en);
        $edition = $so > $en ? 'so' : 'en';

        return ['edition' => $edition, 'confidence' => round(max($so, $en) / $total, 2)];
    }

    protected function importPost(array $post, string $base, array $o): array
    {
        $wpId = (string) $post['id'];
        $title = trim(html_entity_decode(strip_tags($post['title']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $contentHtml = $post['content']['rendered'] ?? '';
        $excerpt = trim(html_entity_decode(strip_tags($post['excerpt']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $excerpt = trim(preg_replace('/\s*(\[&hellip;\]|\[…\]|…)\s*$/u', '', $excerpt));

        $lang = $this->detectLanguage($title.' '.$excerpt.' '.strip_tags($contentHtml));
        $edition = $o['force'] ?: $lang['edition'];
        $row = ['id' => $wpId, 'title' => Str::limit($title, 60), 'edition' => $edition, 'confidence' => $lang['confidence'], 'action' => '', 'note' => ''];

        $existing = Article::withTrashed()->where('import_source', 'wordpress')->where('import_id', $wpId)->first();
        if ($existing && ! $o['update']) {
            $this->log['skipped']++;

            return $row + ['action' => 'skip', 'note' => 'already imported'];
        }
        if (($post['status'] ?? 'publish') !== 'publish' || $title === '') {
            $this->log['skipped']++;

            return array_merge($row, ['action' => 'skip', 'note' => 'not published / no title']);
        }

        $terms = $post['_embedded']['wp:term'] ?? [];
        $wpCats = collect($terms[0] ?? [])->where('taxonomy', 'category');
        $wpTags = collect($terms[1] ?? [])->where('taxonomy', 'post_tag');
        $sectionKey = $wpCats->map(fn ($c) => self::CATEGORY_MAP[$c['slug']] ?? null)->filter()->first();
        $format = $wpCats->map(fn ($c) => self::FORMAT_MAP[$c['slug']] ?? null)->filter()->first() ?? 'news';
        $extraTags = $wpCats->filter(fn ($c) => ! isset(self::CATEGORY_MAP[$c['slug']]) && ! isset(self::FORMAT_MAP[$c['slug']]) && $c['slug'] !== 'uncategorized')
            ->map(fn ($c) => html_entity_decode($c['name']));
        $tagNames = $wpTags->map(fn ($t) => html_entity_decode($t['name']))->merge($extraTags)->unique()->take(12)->values();

        $authorName = trim($post['_embedded']['author'][0]['name'] ?? '') ?: 'Doogsan Media';
        $featured = $post['_embedded']['wp:featuredmedia'][0] ?? null;
        $published = Carbon::parse($post['date_gmt'] ?? $post['date'], 'UTC');
        $modified = Carbon::parse($post['modified_gmt'] ?? $post['modified'] ?? $post['date_gmt'], 'UTC');
        $oldPath = Redirect::normalize($post['link'] ?? '');

        $row['action'] = $existing ? 'update' : 'create';
        $row['note'] = trim(($sectionKey ?: 'no section').' · '.$format.($featured ? ' · image' : ''));

        if ($o['dry']) {
            return $row;
        }

        DB::transaction(function () use ($existing, $edition, $wpId, $title, $excerpt, $contentHtml, $sectionKey, $format, $tagNames, $authorName, $featured, $published, $modified, $oldPath, $o) {
            $author = $this->author($authorName);
            $featuredId = ($o['withImages'] && $featured) ? $this->image($featured['source_url'] ?? null, $edition, [
                'alt' => $featured['alt_text'] ?? null,
                'caption' => trim(html_entity_decode(strip_tags($featured['caption']['rendered'] ?? ''))) ?: null,
                'title' => html_entity_decode($featured['title']['rendered'] ?? ''),
            ])?->id : null;

            $doc = $this->html->convert($contentHtml);
            if ($o['withImages']) {
                $doc = $this->localiseImages($doc, $edition);
            }
            $rendered = $this->renderer->render($doc, $edition);

            $article = $existing ?? new Article(['import_source' => 'wordpress', 'import_id' => $wpId]);
            if ($existing?->trashed()) {
                $existing->restore();
            }
            $article->fill([
                'edition' => $existing?->edition ?? $edition,
                'format' => $format,
                'category_id' => $sectionKey ? $this->categoryId($sectionKey) : $article->category_id,
                'author_id' => $author->id,
                'featured_media_id' => $featuredId ?? $article->featured_media_id,
            ]);
            $article->created_at ??= $published;
            $article->save();

            $locale = $article->edition;
            $t = $article->translations()->where('locale', $locale)->first() ?? new ArticleTranslation(['locale' => $locale, 'article_id' => $article->id]);
            $isPublished = $o['status'] === 'published';
            $t->fill([
                'title' => Str::limit($title, 250, ''),
                'slug' => $t->slug ?: $this->articles->uniqueSlug($locale, $title),
                'standfirst' => $excerpt !== '' ? Str::limit($excerpt, 600) : null,
                'body' => json_encode($doc, JSON_UNESCAPED_UNICODE),
                'body_html' => $rendered['html'],
                'search_text' => mb_substr($rendered['text'], 0, 60000),
                'reading_minutes' => $rendered['minutes'],
                'status' => $t->exists ? $t->status : ($isPublished ? 'published' : 'draft'),
                'published_at' => $t->published_at ?? ($isPublished ? $published : null),
                'first_published_at' => $t->first_published_at ?? ($isPublished ? $published : null),
                'content_updated_at' => $modified->gt($published->copy()->addMinutes(30)) ? $modified : null,
            ]);
            $t->save();
            $t->tags()->sync($tagNames->map(fn ($n) => Tag::findOrCreateFor($locale, $n)->id)->all());

            if ($oldPath !== '/' && $isPublished) {
                $to = parse_url($t->url(), PHP_URL_PATH) ?: $t->url();
                if ($oldPath !== $to) {
                    Redirect::updateOrCreate(['from_path' => $oldPath], ['to_url' => $to, 'status_code' => 301]);
                    $this->log['redirects']++;
                }
            }

            $this->log[$existing ? 'updated' : 'created']++;
        });

        return $row;
    }

    protected function author(string $name): Author
    {
        $slug = Str::slug($name) ?: 'doogsan-media';

        return Author::firstOrCreate(['slug' => $slug], ['name' => $name, 'is_staff' => true, 'show_on_team_page' => false]);
    }

    protected function categoryId(string $key): ?int
    {
        return $this->categoryIds[$key] ??= Category::where('key', $key)->value('id');
    }

    /** Downloads an image once (deduplicated by source URL) into the media library. */
    protected function image(?string $url, string $edition, array $meta = []): ?MediaAsset
    {
        if (! $url || ! preg_match('#^https?://#i', $url)) {
            return null;
        }
        if ($asset = MediaAsset::where('source_url', $url)->first()) {
            return $asset;
        }
        $body = $mime = null;
        // Prefer the local uploads folder when the WordPress files are on this server.
        if ($this->uploadsDir && ($pos = strpos($url, '/wp-content/uploads/')) !== false) {
            $rel = ltrim(rawurldecode((string) parse_url(substr($url, $pos + strlen('/wp-content/uploads/')), PHP_URL_PATH)), '/');
            $local = $this->uploadsDir.'/'.$rel;
            if (! str_contains($rel, '..') && is_file($local) && filesize($local) <= 15 * 1024 * 1024) {
                $body = file_get_contents($local);
                $mime = mime_content_type($local) ?: null;
            }
        }
        if ($body === null) {
            $res = Http::timeout(30)->retry(1, 1000, throw: false)->get($url);
            $mime = strtok((string) $res->header('Content-Type'), ';');
            if (! $res->successful() || strlen($res->body()) > 15 * 1024 * 1024) {
                return null;
            }
            $body = $res->body();
        }
        if (! str_starts_with((string) $mime, 'image/')) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'wpimg');
        file_put_contents($tmp, $body);
        $name = basename(parse_url($url, PHP_URL_PATH)) ?: 'image.jpg';
        try {
            $asset = $this->media->store(new UploadedFile($tmp, $name, $mime, null, true), null, array_filter([
                'title' => $meta['title'] ?? null,
                "alt_{$edition}" => $meta['alt'] ?? null,
                "caption_{$edition}" => $meta['caption'] ?? null,
                'folder' => 'wordpress-import',
            ]));
            $asset->forceFill(['source_url' => $url])->save();
            $this->log['images']++;

            return $asset;
        } finally {
            @unlink($tmp);
        }
    }

    /** Swap remote <img> sources in the body for media-library copies. */
    protected function localiseImages(array $node, string $edition): array
    {
        if (($node['type'] ?? null) === 'image' && ! empty($node['attrs']['src'])) {
            $asset = $this->image($node['attrs']['src'], $edition, ['alt' => $node['attrs']['alt'] ?? null, 'caption' => $node['attrs']['caption'] ?? null]);
            if ($asset) {
                $node['attrs'] = array_filter(['mediaId' => $asset->id, 'caption' => $node['attrs']['caption'] ?? null, 'alt' => $node['attrs']['alt'] ?? null]);
            }

            return $node;
        }
        if (isset($node['content'])) {
            $node['content'] = array_map(fn ($c) => $this->localiseImages($c, $edition), $node['content']);
        }

        return $node;
    }
}
