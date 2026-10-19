<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Corporate & policy pages. Text is a professional starting draft that the
 * editorial board must review and adapt (Admin → Pages) before launch.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('doogsan.contact_email');
        $pages = [
            'about' => [
                ['About Doogsan Media', 'about', 'Independent journalism from Puntland, for Somalia and the world.',
                    '<p>Doogsan Media is a bilingual news organisation publishing in English and Somali. We report on Puntland, Somalia, the Horn of Africa and the wider world, with a commitment to accuracy, fairness and independence.</p><h2>What we do</h2><p>Our newsroom produces breaking news, live coverage, analysis, investigations, video and podcasts. Every story is edited before publication, and both language editions are produced by journalists — not published from unreviewed machine translation.</p><h2>Our standards</h2><p>We separate news from opinion, attribute our sources, correct our mistakes openly and give people the right to reply. Read our <a href="/en/editorial-policy">editorial policy</a>.</p><h2>Contact</h2><p>Email <a href="mailto:'.$email.'">'.$email.'</a>.</p>'],
                ['Ku saabsan Doogsan Media', 'ku-saabsan', 'Saxaafad madax-bannaan oo ka timaadda Puntland, loogana talagalay Soomaaliya iyo adduunka.',
                    '<p>Doogsan Media waa hay’ad war oo ku daabacda labo luqadood: Ingiriisi iyo Soomaali. Waxaan ka warramnaa Puntland, Soomaaliya, Geeska Afrika iyo adduunka intiisa kale, annagoo ka go’an saxnaanta, caddaaladda iyo madax-bannaanida.</p><h2>Waxa aan qabanno</h2><p>Tifaftirkayagu wuxuu soo saaraa warar deg-deg ah, tebin toos ah, falanqayn, baaritaanno, muuqaallo iyo podkaasyo. Sheeko kasta waa la tafatiraa ka hor inta aan la daabicin, labada daabacaadoodna waxaa soo saara weriyeyaal — lagama daabaco turjumaad mashiin oo aan dib loo eegin.</p><h2>Heerarkayaga</h2><p>Waxaan kala saarnaa warka iyo aragtida, waxaan sheegnaa ilaha xogta, si furan ayaan u saxnaa khaladaadkayaga, waxaana dadka siinnaa xaqa jawaabta.</p><h2>Nala soo xiriir</h2><p>Iimayl: <a href="mailto:'.$email.'">'.$email.'</a>.</p>'],
            ],
            'editorial-policy' => [
                ['Corrections & Editorial Policy', 'editorial-policy', 'How we report, how we correct, and how to reach our editors.',
                    '<h2>Accuracy</h2><p>We verify information before we publish it. When facts are disputed we say so, and we name our sources wherever it is safe to do so.</p><h2>Corrections</h2><p>When we get something wrong we correct it promptly and add a visible note at the end of the story explaining what changed and when. Significant changes are marked “Updated” with the time.</p><h2>Independence</h2><p>Advertising and sponsorship never influence editorial decisions. Sponsored content, when published, is clearly labelled.</p><h2>Opinion</h2><p>Opinion pieces represent the views of their authors and are clearly labelled.</p><h2>Translation</h2><p>English and Somali editions are produced and approved by editors. Machine translation may assist our journalists but is never published without editorial approval.</p><h2>Report an error</h2><p>Contact our corrections desk at <a href="mailto:'.$email.'">'.$email.'</a> or use the <a href="/en/contact?department=corrections">contact form</a>.</p>'],
                ['Siyaasadda Tifaftirka & Sixitaannada', 'siyaasadda-tifaftirka', 'Sida aan wax uga warramno, sida aan u saxno, iyo sida loola xiriiro tifaftirayaashayada.',
                    '<h2>Saxnaanta</h2><p>Waxaan xaqiijinaa xogta ka hor inta aanan daabicin. Marka xaqiiqooyinka lagu muransan yahay waan sheegnaa, waxaana magacaabnaa ilaheenna meel kasta oo ay ammaan tahay.</p><h2>Sixitaannada</h2><p>Marka aan khalad samayno si degdeg ah ayaan u saxnaa, waxaana sheekada dhammaadkeeda ku darnaa qoraal muuqda oo sharxaya waxa isbeddelay iyo goorta.</p><h2>Madax-bannaanida</h2><p>Xayeysiisku waligiis ma saameeyo go’aannada tifaftirka.</p><h2>Turjumaadda</h2><p>Daabacaadaha Ingiriisiga iyo Soomaaliga waxaa diyaariya oo ansixiya tifaftirayaal. Turjumaad mashiin lama daabaco iyadoo aan tifaftire ansixin.</p><h2>Ka warbixi khalad</h2><p>La xiriir: <a href="mailto:'.$email.'">'.$email.'</a>.</p>'],
            ],
            'privacy' => [
                ['Privacy Policy', 'privacy', 'What we collect, why, and your choices.',
                    '<p>We collect only what we need to run Doogsan Media.</p><h2>Reading the site</h2><p>We count page views with first-party analytics that do not set cookies and do not store your IP address; visits are counted using a daily-rotating anonymous identifier. If an editor enables Google Analytics, Google’s own privacy policy applies to that data.</p><h2>Newsletter</h2><p>If you subscribe we store your email address and edition preference. Every email contains an unsubscribe link.</p><h2>Contact forms and tips</h2><p>Messages you send are stored so our staff can reply. News tips are encrypted in our database and access is logged. You can submit tips anonymously.</p><h2>Your rights</h2><p>To access or delete your data, email <a href="mailto:'.$email.'">'.$email.'</a>.</p>'],
                ['Siyaasadda Asturnaanta', 'asturnaanta', 'Waxa aan uruurinno, sababta, iyo doorashooyinkaaga.',
                    '<p>Waxaan uruurinnaa oo keliya waxa aan u baahannahay si aan u maamulno Doogsan Media.</p><h2>Akhrinta bogga</h2><p>Waxaan tirinnaa booqashooyinka annagoo adeegsanayna falanqayn aan kuki dhigin oo aan kaydin cinwaanka IP-gaaga.</p><h2>Warsidaha</h2><p>Haddii aad isdiiwaangeliso waxaan kaydinnaa iimaylkaaga. Iimayl kasta waxaa ku jira xiriir lagu joojiyo.</p><h2>Foomamka iyo xogta</h2><p>Xogta aad noo soo dirto waa la qarsoodiyaa, gelitaankeedana waa la diiwaangeliyaa. Waxaad xog soo gudbin kartaa adigoo qarsoon.</p><h2>Xuquuqdaada</h2><p>La xiriir <a href="mailto:'.$email.'">'.$email.'</a>.</p>'],
            ],
            'terms' => [
                ['Terms of Use', 'terms', 'The rules for using Doogsan Media.',
                    '<p>All content on Doogsan Media is protected by copyright. You may share links to our stories freely. Republishing full articles, photographs or video requires written permission — contact <a href="mailto:'.$email.'">'.$email.'</a>.</p><p>We work hard to keep information accurate but cannot accept liability for decisions made on the basis of our reporting. Links to other websites are provided for convenience.</p>'],
                ['Shuruudaha Isticmaalka', 'shuruudaha', 'Xeerarka isticmaalka Doogsan Media.',
                    '<p>Dhammaan waxyaabaha ku jira Doogsan Media waxaa ilaaliya xuquuqda daabacaadda. Waxaad si xor ah u wadaagi kartaa xiriirrada sheekooyinkayaga. Dib-u-daabicidda maqaallo dhammaystiran, sawirro ama muuqaallo waxay u baahan tahay ogolaansho qoran.</p>'],
            ],
            'advertise' => [
                ['Advertise With Us', 'advertise', 'Reach readers across Puntland, Somalia and the diaspora in English and Somali.',
                    '<p>Doogsan Media offers display advertising on our homepage, section pages and articles in both language editions, as well as sponsorship of newsletters, video series and special coverage.</p><h2>Formats</h2><ul><li>Homepage leaderboard and billboard</li><li>Sidebar and in-article placements</li><li>Newsletter sponsorship</li><li>Video pre-roll and programme sponsorship</li></ul><p>Advertising is always clearly labelled and never influences our journalism. For rates and audience information email <a href="mailto:'.$email.'">'.$email.'</a>.</p>'],
                ['Nagala Xayeysii', 'xayeysiis', 'Gaadh akhristayaasha Puntland, Soomaaliya iyo qurbaha, Ingiriisi iyo Soomaali.',
                    '<p>Doogsan Media waxay bixisaa xayeysiis muuqaal ah oo ku yaal bogga hore, qaybaha iyo maqaallada labada daabacaadoodba, iyo sidoo kale kafaala-qaadka warsidaha, taxanaha muuqaallada iyo tebinta gaarka ah.</p><p>Xayeysiisku had iyo jeer si cad ayuu u calaamadaysan yahay mana saameeyo saxaafaddayada. Qiimaha iyo xogta akhristayaasha, la xiriir <a href="mailto:'.$email.'">'.$email.'</a>.</p>'],
            ],
        ];

        foreach ($pages as $key => [$en, $so]) {
            $page = Page::updateOrCreate(['key' => $key], ['show_in_footer' => $key !== 'about']);
            foreach (['en' => $en, 'so' => $so] as $locale => [$title, $slug, $summary, $html]) {
                $page->translations()->updateOrCreate(['locale' => $locale], [
                    'title' => $title, 'slug' => $slug, 'summary' => $summary, 'body_html' => $html,
                    'seo_description' => $summary, 'is_published' => true,
                ]);
            }
        }
    }
}
