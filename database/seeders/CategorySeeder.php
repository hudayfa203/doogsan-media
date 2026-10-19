<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public const SECTIONS = [
        // key => [en name, en slug, so name, so slug, color, in nav]
        'puntland' => ['Puntland', 'puntland', 'Puntland', 'puntland', '#0c5c4c', true],
        'somalia' => ['Somalia', 'somalia', 'Soomaaliya', 'soomaaliya', '#13755f', true],
        'africa' => ['Africa', 'africa', 'Afrika', 'afrika', '#a86b1f', true],
        'world' => ['World', 'world', 'Caalamka', 'caalamka', '#2d5a8a', true],
        'politics' => ['Politics', 'politics', 'Siyaasadda', 'siyaasadda', '#7a2b2b', true],
        'business' => ['Economy & Business', 'business', 'Dhaqaalaha & Ganacsiga', 'dhaqaalaha', '#8a6d1f', true],
        'technology' => ['Technology', 'technology', 'Tiknoolajiyadda', 'tiknoolajiyadda', '#2f6b5f', true],
        'health' => ['Health', 'health', 'Caafimaadka', 'caafimaadka', '#1e7a6d', true],
        'education' => ['Education', 'education', 'Waxbarashada', 'waxbarashada', '#5b4b8a', false],
        'environment' => ['Environment & Climate', 'environment', 'Deegaanka & Cimilada', 'deegaanka', '#3f7f12', false],
        'security' => ['Security', 'security', 'Amniga', 'amniga', '#3a3f47', true],
        'sports' => ['Sports', 'sports', 'Ciyaaraha', 'ciyaaraha', '#b0451c', true],
    ];

    public function run(): void
    {
        $i = 0;
        foreach (self::SECTIONS as $key => [$en, $enSlug, $so, $soSlug, $color, $nav]) {
            $cat = Category::updateOrCreate(['key' => $key], ['color' => $color, 'position' => ($i++) * 10, 'is_active' => true, 'in_navigation' => $nav]);
            $cat->translations()->updateOrCreate(['locale' => 'en'], ['name' => $en, 'slug' => $enSlug]);
            $cat->translations()->updateOrCreate(['locale' => 'so'], ['name' => $so, 'slug' => $soSlug]);
        }
    }
}
