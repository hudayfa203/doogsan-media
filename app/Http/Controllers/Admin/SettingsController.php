<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public const KEYS = [
        'site_name_en', 'site_name_so', 'tagline_en', 'tagline_so', 'site_description_en', 'site_description_so',
        'contact_email', 'contact_phone', 'contact_address', 'editorial_email', 'advertising_email', 'corrections_email',
        'mail_from_address', 'whatsapp_tip_line', 'embed_hosts', 'footer_note_en', 'footer_note_so', 'brand_accent',
    ];

    public const SOCIAL = ['facebook', 'x', 'youtube', 'tiktok', 'instagram', 'linkedin', 'telegram', 'whatsapp'];

    public const LOGOS = ['light', 'dark', 'mark'];

    public function index()
    {
        return view('admin.settings', [
            'values' => collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => setting($k)])->all(),
            'social' => ['en' => (array) setting('social_en', []), 'so' => (array) setting('social_so', [])],
            'logos' => collect(self::LOGOS)->flatMap(fn ($v) => [$v => setting("logo_{$v}"), "{$v}_en" => setting("logo_{$v}_en"), "{$v}_so" => setting("logo_{$v}_so")])->all(),
            'features' => (array) setting('features', ['live_tv' => true, 'newsletter' => true, 'tips' => true, 'pwa' => true, 'dark_mode' => true]),
        ]);
    }

    public function save(Request $request)
    {
        $rules = collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => 'nullable|string|max:1000'])->all();
        $rules += [
            'contact_email' => 'nullable|email|max:190', 'editorial_email' => 'nullable|email|max:190',
            'advertising_email' => 'nullable|email|max:190', 'corrections_email' => 'nullable|email|max:190',
            'mail_from_address' => 'nullable|email|max:190', 'brand_accent' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'social' => 'nullable|array', 'social.*' => 'nullable|array', 'social.*.*' => 'nullable|url|max:255',
            'logo' => 'nullable|array', 'logo.*' => 'nullable|exists:media_assets,id',
            'features' => 'nullable|array',
        ];
        $data = $request->validate($rules);

        foreach (self::KEYS as $k) {
            if ($request->has($k)) {
                Setting::put($k, $data[$k] ?? null);
            }
        }
        if ($request->has('social')) {
            foreach (array_keys(editions()) as $l) {
                // Keep the editor's ordering: first listed = shown first on that edition.
                $links = collect($data['social'][$l] ?? [])->only(self::SOCIAL)->filter()->all();
                Setting::put("social_{$l}", $links);
            }
        }
        foreach ((array) ($data['logo'] ?? []) as $slot => $mediaId) {
            if (preg_match('/^(light|dark|mark)(_(en|so))?$/', $slot)) {
                Setting::put("logo_{$slot}", $mediaId ? MediaAsset::find($mediaId)?->url() : null);
            }
        }
        if ($request->has('features_present')) {
            Setting::put('features', collect(['live_tv', 'newsletter', 'tips', 'pwa', 'dark_mode'])->mapWithKeys(fn ($f) => [$f => $request->boolean("features.{$f}")])->all());
        }
        foreach (array_keys(editions()) as $l) {
            app(\App\Services\HomepageService::class)->flush($l);
        }
        AuditLog::record('settings.updated', 'Updated site settings');

        return back()->with('status', __('Settings saved.'));
    }
}
