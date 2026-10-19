<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageLayout extends Model
{
    protected $fillable = ['locale', 'label', 'layout', 'active_from', 'created_by'];

    protected function casts(): array
    {
        return ['layout' => 'array', 'active_from' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function activeFor(string $locale): ?self
    {
        return static::where('locale', $locale)->where('active_from', '<=', now())
            ->orderByDesc('active_from')->orderByDesc('id')->first();
    }
}
