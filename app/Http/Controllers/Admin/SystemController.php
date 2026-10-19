<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BackupRun;
use App\Models\Setting;
use App\Services\BackupService;
use App\Services\HealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    public function audit(Request $request)
    {
        return view('admin.audit', [
            'logs' => AuditLog::with('user')
                ->when($request->input('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                ->when($request->input('user'), fn ($q, $u) => $q->where('user_id', $u))
                ->latest('created_at')->paginate(50)->withQueryString(),
            'actions' => AuditLog::distinct()->pluck('action')->map(fn ($a) => explode('.', $a)[0])->unique()->sort()->values(),
            'users' => \App\Models\User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function health(HealthService $health)
    {
        return view('admin.system', [
            'checks' => $health->checks(),
            'failed' => DB::table('failed_jobs')->latest('failed_at')->limit(10)->get(),
            'env' => [
                'Environment' => app()->environment(),
                'Debug mode' => config('app.debug') ? 'ON (disable in production)' : 'off',
                'App URL' => config('app.url'),
                'Timezone (storage / display)' => config('app.timezone').' / '.config('doogsan.timezone'),
                'Cache / Queue / Session' => config('cache.default').' / '.config('queue.default').' / '.config('session.driver'),
                'Broadcasting' => config('broadcasting.default'),
                'Mail' => config('mail.default'),
                '2FA enforced for privileged roles' => config('doogsan.enforce_2fa') ? 'yes' : 'no',
            ],
        ]);
    }

    public function backups(BackupService $backups)
    {
        return view('admin.backups', [
            'runs' => BackupRun::latest('created_at')->limit(20)->get(),
            'files' => $backups->files(),
            'retention' => (int) setting('backup_retention_days', 14),
        ]);
    }

    public function runBackup(BackupService $backups)
    {
        $run = $backups->run();

        return back()->with($run->status === 'success' ? 'status' : 'error', $run->status === 'success' ? __('Backup completed.') : __('Backup failed: :m', ['m' => $run->message]));
    }

    public function backupSettings(Request $request)
    {
        $data = $request->validate(['retention' => 'required|integer|between:1,365']);
        Setting::put('backup_retention_days', $data['retention']);

        return back()->with('status', __('Retention updated.'));
    }

    public function download(string $file, BackupService $backups)
    {
        $path = $backups->dir().'/'.basename($file);
        abort_unless(is_file($path), 404);
        AuditLog::record('backup.downloaded', "Downloaded backup {$file}");

        return response()->download($path);
    }
}
