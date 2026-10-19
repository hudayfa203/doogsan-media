<?php

namespace App\Services;

use App\Models\BackupRun;
use App\Models\BroadcastChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthService
{
    public function checks(): array
    {
        $checks = [];

        $checks['database'] = $this->attempt(function () {
            $t = microtime(true);
            DB::select('select 1');

            return ['ok', DB::connection()->getDriverName().' · '.round((microtime(true) - $t) * 1000, 1).' ms'];
        });

        $checks['cache'] = $this->attempt(function () {
            Cache::put('health.ping', $v = (string) microtime(true), 10);

            return [Cache::get('health.ping') === $v ? 'ok' : 'fail', config('cache.default')];
        });

        $checks['queue'] = $this->attempt(function () {
            $driver = config('queue.default');
            if ($driver === 'sync') {
                return ['warn', 'sync driver — jobs run inline (configure a worker in production)'];
            }
            $pending = $driver === 'database' ? DB::table('jobs')->count() : null;
            $failed = DB::table('failed_jobs')->count();
            $beat = Cache::get('health.scheduler_beat');

            return [$failed > 0 ? 'warn' : 'ok', $driver.($pending !== null ? " · {$pending} pending" : '')." · {$failed} failed"];
        });

        $checks['scheduler'] = $this->attempt(function () {
            $beat = Cache::get('health.scheduler_beat');
            if (! $beat) {
                return ['fail', 'No heartbeat — add the cron entry: * * * * * php artisan schedule:run'];
            }
            $age = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($beat), true);

            return [$age < 180 ? 'ok' : 'fail', 'Last run '.(int) $age.'s ago'];
        });

        $checks['broadcasting'] = $this->attempt(function () {
            $d = config('broadcasting.default');
            if (in_array($d, ['null', 'log'], true)) {
                return ['warn', "{$d} — real-time disabled; site uses polling fallback"];
            }
            $host = config('broadcasting.connections.reverb.options.host');
            $port = config('broadcasting.connections.reverb.options.port');
            $fp = @fsockopen(config('reverb.servers.reverb.host') === '0.0.0.0' ? '127.0.0.1' : $host, (int) config('reverb.servers.reverb.port', $port), $e, $s, 1.5);
            if ($fp) {
                fclose($fp);

                return ['ok', "Reverb reachable ({$host}:{$port})"];
            }

            return ['fail', "Reverb not reachable — start: php artisan reverb:start"];
        });

        $checks['storage'] = $this->attempt(function () {
            $path = Storage::disk('public')->path('');
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
            $linked = file_exists(public_path('storage'));
            $pct = $total ? round(($total - $free) / $total * 100) : null;

            return [! $linked ? 'warn' : ($pct !== null && $pct > 90 ? 'warn' : 'ok'),
                ($linked ? 'public link OK' : 'run: php artisan storage:link').($pct !== null ? " · {$pct}% used · ".round($free / 1073741824, 1).' GB free' : '')];
        });

        $checks['streams'] = $this->attempt(function () {
            $channels = BroadcastChannel::where('is_enabled', true)->get();
            if ($channels->isEmpty()) {
                return ['warn', 'No broadcast channels configured'];
            }
            $down = $channels->whereIn('health_status', ['down'])->count();

            return [$down ? 'warn' : 'ok', $channels->count().' channel(s) · '.$down.' down'];
        });

        $checks['backups'] = $this->attempt(function () {
            $last = BackupRun::latest('created_at')->first();
            if (! $last) {
                return ['warn', 'No backup recorded yet'];
            }

            return [$last->status === 'success' && $last->created_at->gt(now()->subDays(2)) ? 'ok' : 'warn',
                'Last: '.$last->status.' · '.$last->created_at->diffForHumans()];
        });

        $checks['php'] = ['status' => version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : 'fail', 'detail' => 'PHP '.PHP_VERSION.' · Laravel '.app()->version()];

        return $checks;
    }

    protected function attempt(callable $fn): array
    {
        try {
            [$status, $detail] = $fn();

            return ['status' => $status, 'detail' => $detail];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => mb_substr($e->getMessage(), 0, 180)];
        }
    }
}
