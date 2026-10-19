<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'site_name_en' => 'Doogsan Media',
            'site_name_so' => 'Doogsan Media',
            'tagline_en' => 'Independent news from Puntland, Somalia and the world',
            'tagline_so' => 'Warar madax-bannaan oo ka imanaya Puntland, Soomaaliya iyo adduunka',
            'site_description_en' => 'Doogsan Media brings you trusted, independent news from Puntland, Somalia, Africa and the world — in English and Somali.',
            'site_description_so' => 'Doogsan Media waxay idiin keentaa warar la isku halleyn karo oo madax-bannaan oo ka imanaya Puntland, Soomaaliya, Afrika iyo adduunka — Ingiriisi iyo Soomaali.',
            'contact_email' => config('doogsan.contact_email'),
            'embed_hosts' => 'datawrapper.dwcdn.net,flo.uri.sh,www.google.com',
            'backup_retention_days' => 14,
            'features' => ['live_tv' => true, 'newsletter' => true, 'tips' => true, 'pwa' => true, 'dark_mode' => true],
            // Official accounts. Order = display priority per edition. Editable in Admin → Settings.
            'social_en' => [
                'tiktok' => 'https://www.tiktok.com/@doogsanenglish',
                'x' => 'https://x.com/doogsan_en',
                'facebook' => 'https://www.facebook.com/DoogsanMedia',
            ],
            'social_so' => [
                'tiktok' => 'https://www.tiktok.com/@doogsanmedia',
                'facebook' => 'https://www.facebook.com/DoogsanMedia',
            ],
        ];
        foreach ($defaults as $k => $v) {
            if (! Setting::find($k)) {
                Setting::put($k, $v);
            }
        }
    }
}
