<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $fillable = ['email', 'locale', 'token', 'confirmed_at', 'unsubscribed_at', 'source'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }
}
