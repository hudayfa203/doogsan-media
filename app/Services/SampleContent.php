<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\Author;
use App\Models\BreakingAlert;
use App\Models\BroadcastChannel;
use App\Models\BroadcastProgram;
use App\Models\Category;
use App\Models\LiveEvent;
use App\Models\MediaAsset;
use App\Models\PageView;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
use App\Models\WorkflowTransition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Review-only SAMPLE content. Everything created here is recorded in the
 * `sample_records` setting and flagged/labelled as sample, so
 * `php artisan doogsan:sample --remove` deletes exactly these records and
 * never touches real newsroom content.
 */
class SampleContent
{
    protected array $created = [];

    protected array $media = [];

    public function installed(): bool
    {
        return ! empty(Setting::get('sample_records'));
    }

    public function install(string $password): array
    {
        if ($this->installed()) {
            throw new \RuntimeException('Sample content is already installed. Remove it first with --remove.');
        }

        DB::transaction(function () use ($password) {
            $renderer = app(BodyRenderer::class);
            $admin = User::whereHas('role', fn ($q) => $q->where('key', 'super-admin'))->first();

            // Journalists, each assigned to a newsroom to show the separation.
            $people = [
                'amina' => ['Amina Warsame', 'journalist', ['so', 'en'], 'Senior Correspondent, Puntland', 'Weriye Sare, Puntland', 'Puntland'],
                'abdirahman' => ['Abdirahman Yusuf', 'news-editor', ['so'], 'Political Editor', 'Tifaftiraha Siyaasadda', 'Politics'],
                'hodan' => ['Hodan Ali', 'journalist', ['en', 'so'], 'Economy Correspondent', 'Weriyaha Dhaqaalaha', 'Business'],
                'mohamed' => ['Mohamed Farah Jama', 'journalist', ['en'], 'Investigations Reporter', 'Weriyaha Baaritaannada', 'Investigations'],
                'sahra' => ['Sahra Nuur', 'journalist', ['so', 'en'], 'Health & Climate Reporter', 'Weriyaha Caafimaadka & Cimilada', 'Society'],
                'liban' => ['Liban Hassan', 'seo-manager', ['en', 'so'], 'Technology & Fact-Check Editor', 'Tifaftiraha Tiknoolajiyadda & Xaqiijinta', 'Digital'],
                'faisal' => ['Faisal Osman', 'social-media-manager', ['en', 'so'], 'Sports Reporter', 'Weriyaha Ciyaaraha', 'Sports'],
                'ayan' => ['Ayan Mohamud', 'managing-editor', ['so', 'en'], 'Managing Editor', 'Maareeyaha Tifaftirka', 'Editorial'],
                'khalid' => ['Khalid Abdi', 'video-producer', ['so', 'en'], 'Video Producer', 'Soo-saaraha Muuqaallada', 'Video'],
                'fartun' => ['Fartun Said', 'translator', ['en'], 'World Desk Editor', 'Tifaftiraha Caalamka', 'World'],
            ];
            $authors = [];
            $i = 0;
            foreach ($people as $key => [$name, $role, $rooms, $titleEn, $titleSo, $desk]) {
                $user = User::create([
                    'name' => $name.' (Sample)', 'email' => "{$key}@sample.doogsanmedia.com", 'password' => $password,
                    'role_id' => Role::where('key', $role)->value('id'), 'is_active' => true, 'newsrooms' => $rooms,
                ]);
                $this->track($user);
                $authors[$key] = $this->track(Author::create([
                    'user_id' => $user->id, 'name' => $name, 'slug' => Str::slug($name), 'title_en' => $titleEn, 'title_so' => $titleSo,
                    'desk' => $desk, 'bio_en' => 'Sample journalist profile for design review.', 'bio_so' => 'Muuqaal weriye oo tusaale ah.',
                    'is_staff' => true, 'show_on_team_page' => true, 'team_order' => $i++ * 10, 'is_sample' => true,
                ]));
            }

            foreach (glob(public_path('images/samples/*.jpg')) as $file) {
                $name = basename($file, '.jpg');
                [$w, $h] = getimagesize($file);
                $this->media[$name] = $this->track(MediaAsset::create([
                    'disk' => 'public', 'path' => "/images/samples/{$name}.jpg", 'type' => 'image', 'mime_type' => 'image/jpeg',
                    'original_name' => "{$name}.jpg", 'size_bytes' => filesize($file), 'width' => $w, 'height' => $h,
                    'variants' => [480 => "/images/samples/{$name}-480.webp", 960 => "/images/samples/{$name}-960.webp", 1600 => "/images/samples/{$name}.jpg"],
                    'title' => 'Sample: '.Str::headline($name), 'alt_en' => 'Sample illustration', 'alt_so' => 'Sawir tusaale ah',
                    'credit' => 'Sample illustration', 'folder' => 'samples', 'usage_notes' => 'Review sample — removed with doogsan:sample --remove',
                ]));
            }

            // Independent stories per newsroom.
            $sets = require database_path('samples/stories.php');
            $made = ['en' => [], 'so' => []];
            foreach ($sets as $edition => $stories) {
                foreach ($stories as [$cat, $format, $img, $author, $hours, $priority, $breaking, $title, $standfirst, $body]) {
                    $published = now()->subMinutes((int) ($hours * 60));
                    $a = $this->track(Article::create([
                        'edition' => $edition, 'format' => $format, 'category_id' => Category::where('key', $cat)->value('id'),
                        'author_id' => $authors[$author]->id, 'featured_media_id' => $this->media[$img]->id ?? null,
                        'priority' => $priority, 'is_breaking' => $breaking, 'is_sample' => true, 'created_by' => $authors[$author]->user_id,
                        'location' => $edition === 'so' ? 'Garoowe' : 'Garowe',
                    ]));
                    $doc = BodyRenderer::docFromParagraphs($body);
                    $r = $renderer->render($doc, $edition);
                    $t = $a->translations()->create([
                        'locale' => $edition, 'title' => $title, 'slug' => Str::slug(Str::limit($title, 90, '')), 'standfirst' => $standfirst,
                        'body' => json_encode($doc, JSON_UNESCAPED_UNICODE), 'body_html' => $r['html'], 'search_text' => $r['text'],
                        'reading_minutes' => $r['minutes'], 'status' => 'published', 'published_at' => $published, 'first_published_at' => $published, 'version' => 2,
                    ]);
                    $tags = ['puntland' => ['Puntland', $edition === 'so' ? 'Garoowe' : 'Garowe'], 'politics' => [$edition === 'so' ? 'Siyaasad' : 'Parliament'], 'business' => [$edition === 'so' ? 'Ganacsi' : 'Economy']][$cat] ?? [];
                    $t->tags()->sync(collect($tags)->map(fn ($n) => Tag::findOrCreateFor($edition, $n)->id));
                    WorkflowTransition::create(['article_translation_id' => $t->id, 'user_id' => $authors[$author]->user_id, 'from_status' => 'draft', 'to_status' => 'submitted', 'created_at' => $published->copy()->subMinutes(30)]);
                    WorkflowTransition::create(['article_translation_id' => $t->id, 'user_id' => $admin?->id, 'from_status' => 'approved', 'to_status' => 'published', 'created_at' => $published]);
                    ArticleRevision::create(['article_translation_id' => $t->id, 'user_id' => $admin?->id, 'version' => 2, 'reason' => 'status', 'title' => $t->title,
                        'standfirst' => $t->standfirst, 'body' => $t->body, 'body_html' => $t->body_html, 'status' => 'published', 'note' => 'Sample']);
                    if ($format === 'gallery') {
                        foreach (['green-hills', 'dunes', 'herders', 'floods'] as $p => $g) {
                            $a->gallery()->attach($this->media[$g]->id, ['role' => 'gallery', 'position' => $p]);
                        }
                    }
                    $made[$edition][] = $t;
                }
            }

            // Workflow examples (unpublished, newsroom-only).
            foreach ([['en', 'submitted', 'Water authority outlines plan for new desalination plant', 'sahra', 'environment'],
                ['en', 'approved', 'University launches Somali-language computing course', 'liban', 'technology'],
                ['so', 'editing', 'Shirka maalgashiga Garoowe oo la qabanayo bisha soo socota', 'hodan', 'business'],
                ['so', 'draft', 'Iskaashatada kalluumaysatada oo diiwaangelisay xilli kalluumaysi oo wanaagsan', 'amina', 'puntland']] as [$edition, $status, $title, $au, $cat]) {
                $a = $this->track(Article::create(['edition' => $edition, 'format' => 'news', 'category_id' => Category::where('key', $cat)->value('id'),
                    'author_id' => $authors[$au]->id, 'priority' => 3, 'is_sample' => true, 'created_by' => $authors[$au]->user_id,
                    'assignee_id' => $authors['ayan']->user_id, 'deadline_at' => now()->addHours(6)]));
                $doc = BodyRenderer::docFromParagraphs(['Sample story in the editorial queue. It shows how drafts move through review and approval. It is not public.']);
                $r = $renderer->render($doc, $edition);
                $a->translations()->create(['locale' => $edition, 'title' => $title, 'slug' => Str::slug($title), 'standfirst' => 'Sample story in the workflow.',
                    'body' => json_encode($doc), 'body_html' => $r['html'], 'search_text' => $r['text'], 'status' => $status, 'version' => 1]);
                $a->comments()->create(['user_id' => $authors['ayan']->user_id, 'locale' => $edition, 'body' => 'Sample note: add a second source and a photo credit before approval.']);
            }

            // Live coverage — one per newsroom, different events.
            $live = [
                'en' => ['Live: Puntland budget session — updates from parliament', 'Sample live coverage of the regional parliament budget debate.', [
                    ['Session opens', 'The Speaker opened the session and invited the finance committee chair to present the committee’s report.', true, false],
                    [null, 'Members are debating allocations for water infrastructure; several called for more funding for rural boreholes.', false, false],
                    ['Committee recommends changes', 'The finance committee recommended shifting part of the administrative budget to health and education.', false, true],
                ]],
                'so' => ['Toos: Olole tallaal xoolaha — wararkii ugu dambeeyay', 'Tebin toos ah oo tusaale ah oo ku saabsan ololaha tallaalka xoolaha.', [
                    ['Ololaha oo bilaabmay', 'Kooxaha xannaanada xoolaha ayaa saaka ka ambabaxay Garoowe iyagoo u socda degmooyinka miyiga.', true, false],
                    [null, 'Goobaha tallaalka ee Dangoroyo iyo Eyl ayaa la sheegay in dad badan ay isugu yimaadeen.', false, false],
                    ['Tirada xoolaha la tallaalay', 'Saraakiisha ayaa sheegay in maalinta koowaad kumanaan xoolo ah la tallaalay.', false, true],
                ]],
            ];
            $events = [];
            foreach ($live as $edition => [$title, $summary, $updates]) {
                $ev = $this->track(LiveEvent::create(['locale' => $edition, 'title' => $title, 'slug' => Str::slug($title), 'summary' => $summary, 'status' => 'live',
                    'starts_at' => now()->subHours(2), 'cover_media_id' => $this->media[$edition === 'en' ? 'parliament' : 'herders']->id ?? null,
                    'category_id' => Category::where('key', $edition === 'en' ? 'politics' : 'puntland')->value('id'), 'created_by' => $admin?->id, 'is_sample' => true]));
                $ev->journalists()->sync([$authors['amina']->user_id, $authors['abdirahman']->user_id]);
                foreach ($updates as $n => [$headline, $body, $pinned, $key]) {
                    $ev->allUpdates()->create(['headline' => $headline, 'body' => '<p>'.e($body).'</p>', 'is_pinned' => $pinned, 'is_key_event' => $key,
                        'author_id' => $authors['amina']->id, 'status' => 'published', 'posted_at' => now()->subMinutes(100 - $n * 30)]);
                }
                $events[$edition] = $ev;
            }

            // Breaking alerts — each edition its own ticker.
            $this->track(BreakingAlert::create(['locale' => 'en', 'headline' => 'SAMPLE: Bosaso port’s second container berth to open before year-end, officials say', 'article_id' => $made['en'][0]->article_id, 'priority' => 2, 'expires_at' => now()->addDays(7), 'created_by' => $admin?->id, 'is_sample' => true]));
            $this->track(BreakingAlert::create(['locale' => 'en', 'headline' => 'SAMPLE: Regional parliament debates budget — follow live updates', 'url' => $events['en']->url(), 'priority' => 2, 'expires_at' => now()->addDays(7), 'created_by' => $admin?->id, 'is_sample' => true]));
            $this->track(BreakingAlert::create(['locale' => 'so', 'headline' => 'TUSAALE: Olole tallaal xoolaha oo laga bilaabay degmooyinka miyiga', 'article_id' => $made['so'][0]->article_id, 'priority' => 2, 'expires_at' => now()->addDays(7), 'created_by' => $admin?->id, 'is_sample' => true]));
            $this->track(BreakingAlert::create(['locale' => 'so', 'headline' => 'TUSAALE: Golaha Wakiilada oo dood ka furay miisaaniyadda sannadka cusub', 'article_id' => $made['so'][2]->article_id, 'priority' => 2, 'expires_at' => now()->addDays(7), 'created_by' => $admin?->id, 'is_sample' => true]));

            // Video & podcasts (no file attached — the player says so honestly).
            foreach ([['en', 'video', 'Inside the newsroom: how a breaking story is verified', 'studio', 184, true], ['en', 'documentary', 'Water Keepers: a year with the berkad builders', 'dunes', 1720, false],
                ['en', 'interview', 'Interview: what graduates need from employers', 'press', 612, false], ['en', 'podcast', 'The Doogsan Briefing — Episode 1', 'studio', 1340, false],
                ['so', 'video', 'Gudaha tifaftirka: sida war deg-deg ah loo xaqiijiyo', 'studio', 184, true], ['so', 'documentary', 'Ilaalinta Biyaha: dhisayaasha barkadaha', 'dunes', 1720, false],
                ['so', 'interview', 'Waraysi: dhallinyarada iyo shaqada', 'press', 612, false], ['so', 'podcast', 'Warbixinta Doogsan — Barnaamijka 1aad', 'studio', 1340, false]] as $k => [$edition, $kind, $title, $thumb, $dur, $featured]) {
                $this->track(Video::create(['locale' => $edition, 'kind' => $kind, 'title' => $title, 'slug' => Str::slug($title), 'provider' => $kind === 'podcast' ? 'audio' : 'file',
                    'description' => $edition === 'en' ? 'Sample item — attach the real video or YouTube link in Admin → Video & Podcasts.' : 'Tusaale — ku dar muuqaalka dhabta ah ee Maamulka.',
                    'thumbnail_media_id' => $this->media[$thumb]->id ?? null, 'duration_seconds' => $dur, 'status' => 'published', 'is_featured' => $featured,
                    'is_sample' => true, 'published_at' => now()->subHours(6 + $k * 9), 'author_id' => $authors['khalid']->id]));
            }

            // Broadcast schedule. The channel itself stays OFF AIR — never a fake live stream.
            if ($channel = BroadcastChannel::first()) {
                foreach ([['Morning News', 'Wararka Subaxa', 6, 7], ['Midday Bulletin', 'Warbixinta Duhurka', 12, 12.5], ['Evening News', 'Wararka Fiidka', 19, 20], ['Night Debate', 'Doodda Habeenka', 21, 22]] as [$en, $so, $s, $e]) {
                    for ($d = 0; $d < 3; $d++) {
                        $day = now()->setTimezone(config('doogsan.timezone'))->startOfDay()->addDays($d);
                        $this->track(BroadcastProgram::create(['broadcast_channel_id' => $channel->id, 'title_en' => $en, 'title_so' => $so, 'presenter' => 'Sample presenter',
                            'starts_at' => $day->copy()->addMinutes((int) ($s * 60))->utc(), 'ends_at' => $day->copy()->addMinutes((int) ($e * 60))->utc()]));
                    }
                }
            }

            // Labelled sample readership so "Trending" can be reviewed (source = 'sample').
            foreach ($made as $edition => $stories) {
                foreach (collect($stories)->take(8)->values() as $rank => $t) {
                    for ($v = 0; $v < (40 - $rank * 4); $v++) {
                        PageView::create(['locale' => $edition, 'content_type' => 'article', 'content_id' => $t->id, 'path' => parse_url($t->url(), PHP_URL_PATH),
                            'source' => 'sample', 'device' => $v % 3 ? 'mobile' : 'desktop', 'visitor_hash' => 'sample-'.$edition.'-'.$rank.'-'.$v, 'created_at' => now()->subMinutes(random_int(10, 2000))]);
                    }
                }
            }

            Setting::put('sample_records', $this->created);
        });

        $this->flush();

        return collect($this->created)->map(fn ($ids) => count($ids))->all();
    }

    public function remove(): array
    {
        $records = (array) Setting::get('sample_records', []);
        $counts = [];
        DB::transaction(function () use ($records, &$counts) {
            // Children first.
            $order = [BreakingAlert::class, PageView::class, Video::class, BroadcastProgram::class, LiveEvent::class, Article::class, Author::class, MediaAsset::class, User::class];
            PageView::where('source', 'sample')->delete();
            foreach ($order as $class) {
                $ids = $records[$class] ?? [];
                if (! $ids) {
                    continue;
                }
                $counts[class_basename($class)] = count($ids);
                if ($class === Article::class) {
                    Article::withTrashed()->whereIn('id', $ids)->get()->each->forceDelete();
                } elseif ($class === User::class) {
                    User::whereIn('id', $ids)->delete();
                } else {
                    $class::whereIn('id', $ids)->delete();
                }
            }
            Tag::doesntHave('translations')->delete();
            Setting::put('sample_records', null);
        });
        $this->flush();

        return $counts;
    }

    protected function track($model)
    {
        $this->created[$model::class][] = $model->getKey();

        return $model;
    }

    protected function flush(): void
    {
        foreach (array_keys(editions()) as $l) {
            Cache::forget("home.{$l}");
            Cache::forget("pulse.{$l}");
            Cache::forget("menus.{$l}");
        }
    }
}
