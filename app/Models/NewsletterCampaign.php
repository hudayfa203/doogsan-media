<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    protected $fillable = ['locale', 'subject', 'intro', 'article_ids', 'status', 'recipients_count', 'sent_at', 'created_by'];

    protected function casts(): array
    {
        return ['article_ids' => 'array', 'sent_at' => 'datetime'];
    }

    public function articles()
    {
        return ArticleTranslation::with('article.featuredMedia')
            ->whereIn('article_id', $this->article_ids ?? [])
            ->where('locale', $this->locale)->live()->get();
    }
}
