<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['locale', 'content_type', 'content_id', 'path', 'referrer_host', 'source', 'device', 'visitor_hash', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
