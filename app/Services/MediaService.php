<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploads and generates responsive WebP variants with GD
 * (no external image service required).
 */
class MediaService
{
    public function store(UploadedFile $file, ?User $user = null, array $meta = []): MediaAsset
    {
        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $type = match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };

        $dir = 'media/'.now()->format('Y/m');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $name = Str::lower(Str::ulid()).'.'.$ext;
        $path = $file->storeAs($dir, $name, 'public');

        $asset = new MediaAsset([
            'disk' => 'public',
            'path' => $path,
            'type' => $type,
            'mime_type' => $mime,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $user?->id,
            'title' => $meta['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
        ] + array_intersect_key($meta, array_flip(['alt_en', 'alt_so', 'caption_en', 'caption_so', 'credit', 'copyright', 'license', 'usage_notes', 'folder'])));

        if ($type === 'image') {
            $this->processImage($asset);
        }

        $asset->save();
        AuditLog::record('media.uploaded', "Uploaded {$asset->original_name}", $asset);

        return $asset;
    }

    public function processImage(MediaAsset $asset): void
    {
        $full = Storage::disk($asset->disk)->path($asset->path);
        $info = @getimagesize($full);
        if (! $info) {
            return;
        }
        [$w, $h] = $info;
        $asset->width = $w;
        $asset->height = $h;

        if (! function_exists('imagewebp')) {
            return;
        }

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($full),
            IMAGETYPE_PNG => @imagecreatefrompng($full),
            IMAGETYPE_WEBP => @imagecreatefromwebp($full),
            IMAGETYPE_GIF => @imagecreatefromgif($full),
            default => null,
        };
        if (! $src) {
            return;
        }

        $variants = [];
        $base = preg_replace('/\.[^.]+$/', '', $asset->path);
        foreach (config('doogsan.media.image_widths') as $target) {
            if ($target >= $w && ! empty($variants)) {
                break;
            }
            $tw = min($target, $w);
            $th = (int) round($h * $tw / $w);
            $dst = imagecreatetruecolor($tw, $th);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
            $vPath = "{$base}-{$tw}.webp";
            imagewebp($dst, Storage::disk($asset->disk)->path($vPath), 80);
            imagedestroy($dst);
            $variants[$tw] = $vPath;
        }
        imagedestroy($src);
        $asset->variants = $variants;
    }

    public function delete(MediaAsset $asset): void
    {
        $disk = Storage::disk($asset->disk);
        if (! str_starts_with($asset->path, '/') && ! str_starts_with($asset->path, 'http')) {
            $disk->delete(array_merge([$asset->path], array_values($asset->variants ?? [])));
        }
        AuditLog::record('media.deleted', "Deleted {$asset->original_name}", $asset);
        $asset->delete();
    }
}
