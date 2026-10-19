<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['status', 'path', 'size_bytes', 'message'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
