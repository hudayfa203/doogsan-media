<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsTip extends Model
{
    protected $fillable = ['locale', 'name', 'contact', 'wants_anonymity', 'location', 'details', 'media_id', 'status', 'assigned_to'];

    protected $hidden = ['name', 'contact', 'details'];

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'contact' => 'encrypted',
            'details' => 'encrypted',
            'wants_anonymity' => 'boolean',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
