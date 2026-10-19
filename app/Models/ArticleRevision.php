<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'article_translation_id', 'user_id', 'version', 'reason', 'title', 'standfirst', 'body', 'body_html', 'status', 'note',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(ArticleTranslation::class, 'article_translation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
