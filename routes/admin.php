<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Newsroom administration — /admin
|--------------------------------------------------------------------------
| Every route below the auth group passes through `admin` (active account
| + optional permission) and `twofactor`. Fine-grained permission checks
| are declared per route with admin:<permission>.
*/
Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
    });

    Route::middleware('auth')->group(function () {
        Route::get('two-factor', [Admin\SecurityController::class, 'challenge'])->name('2fa.challenge');
        Route::post('two-factor', [Admin\SecurityController::class, 'verifyChallenge'])->middleware('throttle:login')->name('2fa.verify');
        Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');
    });

    Route::middleware(['auth', 'admin', 'twofactor'])->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/metrics', [Admin\DashboardController::class, 'metrics'])->name('dashboard.metrics');

        // Account security (2FA, password)
        Route::get('security', [Admin\SecurityController::class, 'show'])->name('security');
        Route::post('security/password', [Admin\SecurityController::class, 'password'])->name('security.password');
        Route::post('security/2fa/enable', [Admin\SecurityController::class, 'enable'])->name('security.2fa.enable');
        Route::post('security/2fa/confirm', [Admin\SecurityController::class, 'confirm'])->name('security.2fa.confirm');
        Route::post('security/2fa/disable', [Admin\SecurityController::class, 'disable'])->name('security.2fa.disable');
        Route::post('security/2fa/recovery', [Admin\SecurityController::class, 'regenerate'])->name('security.2fa.recovery');

        // ── Editorial: two independent newsrooms ─────────────────────
        Route::middleware('admin:articles.view')->group(function () {
            Route::get('articles', [Admin\ArticleController::class, 'all'])->name('articles.all');
            Route::get('articles/translations', [Admin\ArticleController::class, 'translations'])->name('articles.translations');
            Route::get('articles/lookup', [Admin\ArticleController::class, 'lookup'])->name('articles.lookup');

            Route::prefix('newsroom/{edition}')->whereIn('edition', ['en', 'so'])->group(function () {
                Route::get('/', [Admin\NewsroomController::class, 'show'])->name('newsroom.show');
                Route::get('articles', [Admin\ArticleController::class, 'index'])->name('articles.index');
                Route::get('articles/create', [Admin\ArticleController::class, 'create'])->middleware('admin:articles.create')->name('articles.create');
                Route::post('articles', [Admin\ArticleController::class, 'store'])->middleware('admin:articles.create')->name('articles.store');
                Route::get('articles/{article}/edit', [Admin\ArticleController::class, 'edit'])->name('articles.edit');
                Route::put('articles/{article}', [Admin\ArticleController::class, 'update'])->name('articles.update');
            });

            Route::post('articles/{article}/autosave', [Admin\ArticleController::class, 'autosave'])->name('articles.autosave');
            Route::post('articles/{article}/lock/{locale}', [Admin\ArticleController::class, 'lock'])->name('articles.lock');
            Route::delete('articles/{article}/lock/{locale}', [Admin\ArticleController::class, 'unlock'])->name('articles.unlock');
            Route::post('articles/{article}/transition/{locale}', [Admin\ArticleController::class, 'transition'])->name('articles.transition');
            Route::post('articles/{article}/duplicate', [Admin\ArticleController::class, 'duplicate'])->middleware('admin:articles.create')->name('articles.duplicate');
            Route::post('articles/{article}/move-edition', [Admin\ArticleController::class, 'moveEdition'])->middleware('admin:articles.edit_any')->name('articles.move-edition');
            Route::delete('articles/{article}', [Admin\ArticleController::class, 'destroy'])->middleware('admin:articles.delete')->name('articles.destroy');
            Route::get('articles/{article}/preview/{locale}', [Admin\ArticleController::class, 'preview'])->name('articles.preview');
            Route::get('articles/{article}/history/{locale}', [Admin\ArticleController::class, 'history'])->name('articles.history');
            Route::post('articles/{article}/rollback/{revision}', [Admin\ArticleController::class, 'rollback'])->middleware('admin:corrections.manage')->name('articles.rollback');
            Route::post('articles/{article}/correction/{locale}', [Admin\ArticleController::class, 'correction'])->middleware('admin:corrections.manage')->name('articles.correction');
            Route::post('articles/{article}/approve-translation/{locale}', [Admin\ArticleController::class, 'approveTranslation'])->middleware('admin:translations.manage')->name('articles.approve-translation');
            Route::post('articles/{article}/comments', [Admin\ArticleController::class, 'comment'])->name('articles.comment');
            Route::patch('comments/{comment}', [Admin\ArticleController::class, 'resolveComment'])->name('comments.resolve');
        });

        Route::middleware('admin:breaking.manage')->group(function () {
            Route::get('breaking', [Admin\BreakingController::class, 'index'])->name('breaking.index');
            Route::post('breaking', [Admin\BreakingController::class, 'store'])->name('breaking.store');
            Route::put('breaking/{alert}', [Admin\BreakingController::class, 'update'])->name('breaking.update');
            Route::post('breaking/{alert}/expire', [Admin\BreakingController::class, 'expire'])->name('breaking.expire');
            Route::delete('breaking/{alert}', [Admin\BreakingController::class, 'destroy'])->name('breaking.destroy');
        });

        Route::middleware('admin:live.manage')->group(function () {
            Route::get('live', [Admin\LiveEventController::class, 'index'])->name('live.index');
            Route::post('live', [Admin\LiveEventController::class, 'store'])->name('live.store');
            Route::get('live/{event}', [Admin\LiveEventController::class, 'desk'])->name('live.desk');
            Route::put('live/{event}', [Admin\LiveEventController::class, 'update'])->name('live.update');
            Route::post('live/{event}/status', [Admin\LiveEventController::class, 'status'])->name('live.status');
            Route::post('live/{event}/updates', [Admin\LiveEventController::class, 'post'])->name('live.post');
            Route::put('live-updates/{update}', [Admin\LiveEventController::class, 'edit'])->name('live.edit');
            Route::post('live-updates/{update}/pin', [Admin\LiveEventController::class, 'pin'])->name('live.pin');
            Route::delete('live-updates/{update}', [Admin\LiveEventController::class, 'remove'])->name('live.remove');
        });

        Route::middleware('admin:homepage.manage')->group(function () {
            Route::get('homepage/{locale?}', [Admin\HomepageController::class, 'edit'])->whereIn('locale', ['en', 'so'])->name('homepage.edit');
            Route::post('homepage/{locale}', [Admin\HomepageController::class, 'save'])->whereIn('locale', ['en', 'so'])->name('homepage.save');
            Route::delete('homepage/version/{layout}', [Admin\HomepageController::class, 'destroy'])->name('homepage.destroy');
        });

        Route::middleware('admin:media.manage')->group(function () {
            Route::get('media', [Admin\MediaController::class, 'index'])->name('media.index');
            Route::get('media/picker', [Admin\MediaController::class, 'picker'])->name('media.picker');
            Route::post('media', [Admin\MediaController::class, 'store'])->name('media.store');
            Route::put('media/{media}', [Admin\MediaController::class, 'update'])->name('media.update');
            Route::delete('media/{media}', [Admin\MediaController::class, 'destroy'])->name('media.destroy');
        });

        Route::resource('authors', Admin\AuthorController::class)->except(['show'])->middleware('admin:authors.manage');

        Route::middleware('admin:taxonomy.manage')->group(function () {
            Route::resource('categories', Admin\CategoryController::class)->except(['show']);
            Route::get('tags', [Admin\CategoryController::class, 'tags'])->name('tags.index');
            Route::delete('tags/{tag}', [Admin\CategoryController::class, 'destroyTag'])->name('tags.destroy');
        });

        Route::middleware('admin:video.manage')->group(function () {
            Route::get('broadcast', [Admin\BroadcastController::class, 'index'])->name('broadcast.index');
            Route::post('broadcast/channels', [Admin\BroadcastController::class, 'storeChannel'])->name('broadcast.channels.store');
            Route::put('broadcast/channels/{channel}', [Admin\BroadcastController::class, 'updateChannel'])->name('broadcast.channels.update');
            Route::post('broadcast/channels/{channel}/live', [Admin\BroadcastController::class, 'toggleLive'])->name('broadcast.channels.live');
            Route::post('broadcast/channels/{channel}/check', [Admin\BroadcastController::class, 'check'])->name('broadcast.channels.check');
            Route::delete('broadcast/channels/{channel}', [Admin\BroadcastController::class, 'destroyChannel'])->name('broadcast.channels.destroy');
            Route::post('broadcast/programs', [Admin\BroadcastController::class, 'storeProgram'])->name('broadcast.programs.store');
            Route::delete('broadcast/programs/{program}', [Admin\BroadcastController::class, 'destroyProgram'])->name('broadcast.programs.destroy');
            Route::resource('videos', Admin\VideoController::class)->except(['show']);
        });

        Route::middleware('admin:seo.manage')->group(function () {
            Route::get('seo', [Admin\SeoController::class, 'index'])->name('seo.index');
            Route::post('seo/settings', [Admin\SeoController::class, 'settings'])->name('seo.settings');
            Route::post('seo/redirects', [Admin\SeoController::class, 'storeRedirect'])->name('seo.redirects.store');
            Route::delete('seo/redirects/{redirect}', [Admin\SeoController::class, 'destroyRedirect'])->name('seo.redirects.destroy');
        });

        Route::resource('ads', Admin\AdController::class)->except(['show'])->middleware('admin:ads.manage');

        Route::get('analytics', [Admin\AnalyticsController::class, 'index'])->middleware('admin:analytics.view')->name('analytics');

        Route::middleware('admin:inbox.manage')->group(function () {
            Route::get('inbox', [Admin\InboxController::class, 'index'])->name('inbox.index');
            Route::get('inbox/messages/{message}', [Admin\InboxController::class, 'message'])->name('inbox.message');
            Route::patch('inbox/messages/{message}', [Admin\InboxController::class, 'updateMessage'])->name('inbox.message.update');
            Route::get('inbox/tips/{tip}', [Admin\InboxController::class, 'tip'])->name('inbox.tip');
            Route::patch('inbox/tips/{tip}', [Admin\InboxController::class, 'updateTip'])->name('inbox.tip.update');
        });

        Route::middleware('admin:newsletter.manage')->group(function () {
            Route::get('newsletter', [Admin\NewsletterController::class, 'index'])->name('newsletter.index');
            Route::get('newsletter/export', [Admin\NewsletterController::class, 'export'])->name('newsletter.export');
            Route::post('newsletter/campaigns', [Admin\NewsletterController::class, 'store'])->name('newsletter.store');
            Route::post('newsletter/campaigns/{campaign}/send', [Admin\NewsletterController::class, 'send'])->name('newsletter.send');
            Route::delete('newsletter/subscribers/{subscriber}', [Admin\NewsletterController::class, 'destroySubscriber'])->name('newsletter.subscribers.destroy');
        });

        Route::middleware('admin:users.manage')->group(function () {
            Route::resource('users', Admin\UserController::class)->except(['show']);
            Route::get('roles', [Admin\RoleController::class, 'index'])->name('roles.index');
            Route::put('roles', [Admin\RoleController::class, 'update'])->name('roles.update');
            Route::post('roles', [Admin\RoleController::class, 'store'])->name('roles.store');
        });

        Route::middleware('admin:settings.manage')->group(function () {
            Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings');
            Route::post('settings', [Admin\SettingsController::class, 'save'])->name('settings.save');
        });

        Route::middleware('admin:menus.manage')->group(function () {
            Route::get('menus/{locale?}', [Admin\MenuController::class, 'edit'])->whereIn('locale', ['en', 'so'])->name('menus.edit');
            Route::post('menus/{locale}', [Admin\MenuController::class, 'save'])->whereIn('locale', ['en', 'so'])->name('menus.save');
        });

        Route::middleware('admin:pages.manage')->group(function () {
            Route::get('pages', [Admin\PageController::class, 'index'])->name('pages.index');
            Route::get('pages/{page}/edit', [Admin\PageController::class, 'edit'])->name('pages.edit');
            Route::put('pages/{page}', [Admin\PageController::class, 'update'])->name('pages.update');
        });

        Route::get('audit', [Admin\SystemController::class, 'audit'])->middleware('admin:audit.view')->name('audit');
        Route::get('system', [Admin\SystemController::class, 'health'])->middleware('admin:system.view')->name('system');
        Route::middleware('admin:backups.manage')->group(function () {
            Route::get('backups', [Admin\SystemController::class, 'backups'])->name('backups');
            Route::post('backups', [Admin\SystemController::class, 'runBackup'])->name('backups.run');
            Route::post('backups/settings', [Admin\SystemController::class, 'backupSettings'])->name('backups.settings');
            Route::get('backups/{file}', [Admin\SystemController::class, 'download'])->where('file', '[A-Za-z0-9._-]+')->name('backups.download');
        });
    });
});
