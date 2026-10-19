<?php

namespace App\Services;

use App\Events\ArticlePublished;
use App\Events\NewsroomActivity;
use App\Models\ArticleRevision;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkflowTransition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Editorial state machine. Each language version moves independently:
 * Draft → Submitted → Editing → Approved → Scheduled/Published → Archived.
 */
class WorkflowService
{
    public const REQUIRES = [
        'draft' => 'articles.submit',
        'submitted' => 'articles.submit',
        'editing' => 'articles.review',
        'approved' => 'articles.review',
        'scheduled' => 'articles.publish',
        'published' => 'articles.publish',
        'archived' => 'articles.archive',
    ];

    public function allowedTargets(ArticleTranslation $t, ?User $user = null): array
    {
        $targets = config("doogsan.workflow.{$t->status}", []);

        if (! $user) {
            return $targets;
        }

        return array_values(array_filter($targets, fn ($to) => $this->userMay($user, $t, $to)));
    }

    public function userMay(User $user, ArticleTranslation $t, string $to): bool
    {
        // Pulling a published story back, or reviving an archived one, needs publish rights.
        if (in_array($t->status, ['published', 'archived'], true) && in_array($to, ['editing', 'draft'], true)) {
            return $user->hasPermission('articles.publish');
        }
        // Sending back to draft from the desk is a review decision.
        if ($to === 'draft' && in_array($t->status, ['submitted', 'editing'], true)) {
            return $user->hasPermission('articles.review');
        }

        return $user->hasPermission(self::REQUIRES[$to] ?? 'articles.publish');
    }

    /** Hard blockers and soft warnings shown in the publishing checklist. */
    public function checklist(ArticleTranslation $t): array
    {
        $a = $t->article;
        $items = [
            ['key' => 'title', 'ok' => mb_strlen(trim($t->title)) >= 8, 'required' => true, 'label' => __('Headline written')],
            ['key' => 'body', 'ok' => mb_strlen(strip_tags((string) $t->body_html)) >= 80, 'required' => true, 'label' => __('Story body has content')],
            ['key' => 'category', 'ok' => (bool) $a->category_id, 'required' => true, 'label' => __('Section selected')],
            ['key' => 'author', 'ok' => (bool) $a->author_id, 'required' => true, 'label' => __('Byline attributed')],
            ['key' => 'mt', 'ok' => ! $t->is_machine_translated || $t->translation_approved_by, 'required' => true, 'label' => __('Machine translation approved by an editor')],
            ['key' => 'standfirst', 'ok' => filled($t->standfirst), 'required' => false, 'label' => __('Standfirst / summary')],
            ['key' => 'image', 'ok' => (bool) $a->featured_media_id, 'required' => false, 'label' => __('Featured image')],
            ['key' => 'image_credit', 'ok' => ! $a->featured_media_id || filled($a->featuredMedia?->credit), 'required' => false, 'label' => __('Image credit recorded')],
            ['key' => 'seo', 'ok' => filled($t->seo_description) || filled($t->standfirst), 'required' => false, 'label' => __('Search description')],
        ];

        return $items;
    }

    public function blockers(ArticleTranslation $t): array
    {
        return array_values(array_filter($this->checklist($t), fn ($i) => $i['required'] && ! $i['ok']));
    }

    public function transition(ArticleTranslation $t, string $to, User $user, ?string $note = null, ?Carbon $scheduleAt = null): ArticleTranslation
    {
        $from = $t->status;

        if (! in_array($to, config("doogsan.workflow.{$from}", []), true)) {
            throw ValidationException::withMessages(['status' => __('Cannot move a story from :from to :to.', ['from' => __("status.{$from}"), 'to' => __("status.{$to}")])]);
        }
        if (! $this->userMay($user, $t, $to)) {
            throw ValidationException::withMessages(['status' => __('You do not have permission for this step.')]);
        }
        if (in_array($to, ['published', 'scheduled'], true) && $blockers = $this->blockers($t)) {
            throw ValidationException::withMessages(['status' => __('Publishing checklist incomplete: :items', ['items' => collect($blockers)->pluck('label')->implode(', ')])]);
        }
        if ($to === 'scheduled' && (! $scheduleAt || $scheduleAt->lte(now()))) {
            throw ValidationException::withMessages(['scheduled_at' => __('Choose a future publication time.')]);
        }

        DB::transaction(function () use ($t, $from, $to, $user, $note, $scheduleAt) {
            $t->status = $to;

            if ($to === 'published') {
                $t->published_at = $t->published_at && $from === 'scheduled' ? $t->published_at : now();
                $t->first_published_at ??= $t->published_at;
                $t->scheduled_at = null;
                if ($t->unpublish_at && $t->unpublish_at->lte(now())) {
                    $t->unpublish_at = null;
                }
            } elseif ($to === 'scheduled') {
                $t->scheduled_at = $scheduleAt;
                $t->published_at = $scheduleAt;
            } elseif ($to !== 'archived') {
                if ($from === 'published') {
                    $t->published_at = null; // pulled back for edits — no longer public
                }
            }

            $t->version++;
            $t->save();

            WorkflowTransition::create([
                'article_translation_id' => $t->id,
                'user_id' => $user->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
            ]);

            ArticleRevision::create([
                'article_translation_id' => $t->id,
                'user_id' => $user->id,
                'version' => $t->version,
                'reason' => 'status',
                'title' => $t->title,
                'standfirst' => $t->standfirst,
                'body' => $t->body,
                'body_html' => $t->body_html,
                'status' => $to,
                'note' => $note ?: "{$from} → {$to}",
            ]);

            AuditLog::record("article.{$to}", "“{$t->title}” ({$t->locale}) {$from} → {$to}", $t);
        });

        $t->refresh();

        safe_broadcast(new NewsroomActivity($user->name, "moved “{$t->title}” to {$to}", $t->article_id));

        if ($to === 'published') {
            app(HomepageService::class)->flush($t->locale);
            safe_broadcast(new ArticlePublished($t));
        }

        return $t;
    }

    /** Called every minute by the scheduler: publish due stories, unpublish expired ones. */
    public function runSchedule(): array
    {
        $published = 0;
        $unpublished = 0;

        ArticleTranslation::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get()
            ->each(function (ArticleTranslation $t) use (&$published) {
                DB::transaction(function () use ($t) {
                    $t->update([
                        'status' => 'published',
                        'published_at' => $t->scheduled_at,
                        'first_published_at' => $t->first_published_at ?? $t->scheduled_at,
                        'scheduled_at' => null,
                    ]);
                    WorkflowTransition::create(['article_translation_id' => $t->id, 'from_status' => 'scheduled', 'to_status' => 'published', 'note' => 'Scheduled publication']);
                    AuditLog::record('article.published', "Scheduled publication: “{$t->title}” ({$t->locale})", $t);
                });
                app(HomepageService::class)->flush($t->locale);
                safe_broadcast(new ArticlePublished($t));
                $published++;
            });

        ArticleTranslation::where('status', 'published')->whereNotNull('unpublish_at')->where('unpublish_at', '<=', now())->get()
            ->each(function (ArticleTranslation $t) use (&$unpublished) {
                DB::transaction(function () use ($t) {
                    $t->update(['status' => 'archived']);
                    WorkflowTransition::create(['article_translation_id' => $t->id, 'from_status' => 'published', 'to_status' => 'archived', 'note' => 'Scheduled unpublishing']);
                    AuditLog::record('article.archived', "Scheduled unpublishing: “{$t->title}” ({$t->locale})", $t);
                });
                app(HomepageService::class)->flush($t->locale);
                $unpublished++;
            });

        return compact('published', 'unpublished');
    }
}
