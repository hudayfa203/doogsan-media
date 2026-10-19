<?php

namespace App\Events;

use App\Models\ArticleTranslation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ArticlePublished implements ShouldBroadcastNow
{
    public function __construct(public ArticleTranslation $translation) {}

    public function broadcastOn(): array
    {
        return [new Channel("edition.{$this->translation->locale}")];
    }

    public function broadcastAs(): string
    {
        return 'article.published';
    }

    public function broadcastWith(): array
    {
        $t = $this->translation->loadMissing('article.category.translations', 'article.featuredMedia', 'article.author');

        return [
            'id' => $t->id,
            'title' => $t->title,
            'url' => $t->url(),
            'standfirst' => $t->standfirst,
            'category' => $t->article->category?->nameFor($t->locale),
            'image' => $t->article->featuredMedia?->url(480),
            'author' => $t->article->author?->name,
            'published_at' => $t->published_at?->toIso8601String(),
            'is_breaking' => $t->article->is_breaking,
        ];
    }
}
