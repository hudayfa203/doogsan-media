<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $fillable = ['from_path', 'to_url', 'status_code', 'hits'];

    public static function normalize(string $path): string
    {
        $path = '/'.trim(parse_url($path, PHP_URL_PATH) ?? $path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
