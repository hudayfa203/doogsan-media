<?php

use App\Models\BreakingAlert;
use App\Services\BackupService;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('newsroom:publish-scheduled', function (WorkflowService $workflow) {
    $r = $workflow->runSchedule();
    $this->info("Published {$r['published']}, unpublished {$r['unpublished']}.");
})->purpose('Publish due scheduled stories and unpublish expired ones');

Artisan::command('newsroom:expire-breaking', function () {
    $expired = BreakingAlert::where('is_active', true)->whereNotNull('expires_at')->where('expires_at', '<=', now())->get();
    foreach ($expired->groupBy('locale') as $locale => $alerts) {
        BreakingAlert::whereIn('id', $alerts->pluck('id'))->update(['is_active' => false]);
        safe_broadcast(new \App\Events\BreakingNewsChanged($locale));
        Cache::forget("pulse.{$locale}");
    }
    $this->info("Expired {$expired->count()} alert(s).");
})->purpose('Deactivate expired breaking-news alerts and notify browsers');

Artisan::command('newsroom:backup', function (BackupService $backups) {
    $run = $backups->run();
    $this->line("Backup {$run->status}: {$run->path} {$run->message}");
})->purpose('Back up the database to storage/app/backups');

Artisan::command('newsroom:check-streams', function () {
    foreach (\App\Models\BroadcastChannel::where('is_enabled', true)->get() as $c) {
        $status = 'unknown';
        $msg = null;
        try {
            if ($c->source_type === 'hls' && $c->source_url) {
                $res = \Illuminate\Support\Facades\Http::timeout(6)->get($c->source_url);
                $ok = $res->successful() && str_contains($res->body(), '#EXTM3U');
                $status = $ok ? 'healthy' : 'down';
                $msg = $ok ? 'Manifest OK' : 'HTTP '.$res->status();
            } elseif ($c->source_type === 'youtube') {
                $status = $c->youtube_video_id ? 'healthy' : 'down';
                $msg = $c->youtube_video_id ? 'Embed configured' : 'No video ID';
            } else {
                $status = $c->source_url ? 'healthy' : 'down';
                $msg = $c->source_url ? 'Source configured' : 'No source';
            }
        } catch (\Throwable $e) {
            $status = 'down';
            $msg = mb_substr($e->getMessage(), 0, 180);
        }
        $c->update(['health_status' => $status, 'health_message' => $msg, 'health_checked_at' => now()]);
        $this->line("{$c->name}: {$status}");
    }
})->purpose('Check configured live streams');

Schedule::call(fn () => Cache::put('health.scheduler_beat', now()->toIso8601String(), 3600))->everyMinute()->name('heartbeat');
Schedule::command('newsroom:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('newsroom:expire-breaking')->everyMinute()->withoutOverlapping();
Schedule::command('newsroom:check-streams')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('newsroom:backup')->dailyAt('01:30');
Schedule::command('queue:prune-failed --hours=168')->daily();

Artisan::command('doogsan:sample {--remove : Delete all sample content} {--password= : Password for the sample newsroom accounts}', function (\App\Services\SampleContent $samples) {
    if ($this->option('remove')) {
        $counts = $samples->remove();
        $this->info('Sample content removed: '.json_encode($counts));

        return;
    }
    $password = $this->option('password') ?: \Illuminate\Support\Str::password(16, symbols: false);
    $counts = $samples->install($password);
    $this->info('Sample content installed: '.json_encode($counts));
    $this->line("Sample newsroom accounts (e.g. ayan@sample.doogsanmedia.com) use password: {$password}");
    $this->warn('Everything is labelled “Sample”. Remove before launch:  php artisan doogsan:sample --remove');
})->purpose('Install or remove clearly-labelled sample content for design review');

Artisan::command('doogsan:import-wordpress {url : WordPress site, e.g. https://doogsanmedia.com}
    {--dry-run : Show what would be imported without changing anything}
    {--update : Refresh posts that were already imported}
    {--limit=0 : Stop after this many posts}
    {--since= : Only posts published after this date (YYYY-MM-DD)}
    {--status=published : published (mirror the live site) or draft (editors review first)}
    {--edition= : Force every post into one newsroom (en|so) instead of detecting the language}
    {--no-images : Do not download images}', function (\App\Services\WordPressImporter $importer) {
    $edition = $this->option('edition');
    if ($edition && ! in_array($edition, ['en', 'so'], true)) {
        $this->error('--edition must be en or so');

        return 1;
    }
    $this->info(($this->option('dry-run') ? '[DRY RUN] ' : '').'Importing from '.$this->argument('url'));
    $result = $importer->run($this->argument('url'), [
        'dry_run' => $this->option('dry-run'),
        'update' => $this->option('update'),
        'limit' => (int) $this->option('limit'),
        'since' => $this->option('since'),
        'status' => $this->option('status'),
        'force_edition' => $edition,
        'no_images' => $this->option('no-images'),
    ], fn ($r) => $this->line(sprintf('  %-7s %-3s %4s  #%-6s %s  <fg=gray>%s</>', $r['action'], strtoupper($r['edition']), isset($r['confidence']) ? round($r['confidence'] * 100).'%' : '', $r['id'], $r['title'], $r['note'])));

    $this->newLine();
    $this->table(array_keys($result['counts']), [array_values($result['counts'])]);
    $low = collect($result['rows'])->filter(fn ($r) => isset($r['confidence']) && $r['confidence'] < 0.65)->count();
    if ($low) {
        $this->warn("{$low} post(s) had low language confidence — check them in the newsrooms and move any mis-filed story.");
    }
})->purpose('Import posts from the existing WordPress site into the Somali and English newsrooms');
