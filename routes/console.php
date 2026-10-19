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
