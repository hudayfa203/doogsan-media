<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            'primary' => [
                ['Latest', 'Wararkii ugu dambeeyay', 'route', 'latest'],
                ['Puntland', 'Puntland', 'category', 'puntland'],
                ['Somalia', 'Soomaaliya', 'category', 'somalia'],
                ['Africa', 'Afrika', 'category', 'africa'],
                ['World', 'Caalamka', 'category', 'world'],
                ['Politics', 'Siyaasadda', 'category', 'politics'],
                ['Economy', 'Dhaqaalaha', 'category', 'business'],
                ['Technology', 'Tiknoolajiyadda', 'category', 'technology'],
                ['Health', 'Caafimaadka', 'category', 'health'],
                ['Security', 'Amniga', 'category', 'security'],
                ['Sports', 'Ciyaaraha', 'category', 'sports'],
                ['Opinion', 'Aragti', 'format', 'opinion'],
                ['Investigations', 'Baaritaanno', 'format', 'investigation'],
                ['Video', 'Muuqaallo', 'route', 'videos'],
                ['Live TV', 'TV Toos ah', 'route', 'tv', true],
            ],
            'footer_news' => [
                ['Latest News', 'Wararkii ugu dambeeyay', 'route', 'latest'],
                ['Breaking News', 'War Deg-deg ah', 'route', 'breaking'],
                ['Puntland', 'Puntland', 'category', 'puntland'],
                ['Somalia', 'Soomaaliya', 'category', 'somalia'],
                ['Africa', 'Afrika', 'category', 'africa'],
                ['World', 'Caalamka', 'category', 'world'],
                ['Education', 'Waxbarashada', 'category', 'education'],
                ['Environment & Climate', 'Deegaanka & Cimilada', 'category', 'environment'],
            ],
            'footer_journalism' => [
                ['Analysis', 'Falanqayn', 'format', 'analysis'],
                ['Opinion', 'Aragti', 'format', 'opinion'],
                ['Investigations', 'Baaritaanno', 'format', 'investigation'],
                ['In-Depth', 'Qoto-dheer', 'format', 'in-depth'],
                ['Interviews', 'Waraysiyo', 'format', 'interview'],
                ['Fact Checks', 'Xaqiijin', 'format', 'fact-check'],
                ['Live Coverage', 'Tebin Toos ah', 'route', 'live'],
                ['Media Centre', 'Xarunta Warbaahinta', 'route', 'media'],
            ],
            'footer_company' => [
                ['About Us', 'Nagu saabsan', 'page', 'about'],
                ['Editorial Team', 'Tifaftirka', 'route', 'team'],
                ['Our Journalists', 'Weriyeyaasha', 'route', 'authors'],
                ['Contact Us', 'Nala soo xiriir', 'route', 'contact'],
                ['Advertise', 'Xayeysiis', 'page', 'advertise'],
                ['Submit a Tip', 'Soo gudbi xog', 'route', 'tip'],
                ['Newsletter', 'Warsidaha', 'route', 'newsletter'],
            ],
            'footer_legal' => [
                ['Editorial Policy', 'Siyaasadda Tifaftirka', 'page', 'editorial-policy'],
                ['Privacy Policy', 'Asturnaanta', 'page', 'privacy'],
                ['Terms of Use', 'Shuruudaha', 'page', 'terms'],
                ['Archive', 'Kaydka', 'route', 'archive'],
            ],
        ];

        foreach (['en' => 0, 'so' => 1] as $locale => $idx) {
            foreach ($menus as $location => $items) {
                $menu = Menu::updateOrCreate(['locale' => $locale, 'location' => $location], ['name' => ucfirst(str_replace('_', ' ', $location))]);
                $menu->allItems()->delete();
                foreach ($items as $i => $item) {
                    $menu->allItems()->create([
                        'label' => $item[$idx], 'link_type' => $item[2], 'link_value' => $item[3],
                        'position' => $i, 'highlight' => $item[4] ?? false,
                    ]);
                }
            }
        }
    }
}
