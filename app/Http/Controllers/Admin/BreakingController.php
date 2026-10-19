<?php

namespace App\Http\Controllers\Admin;

use App\Events\BreakingNewsChanged;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BreakingAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BreakingController extends Controller
{
    public function index()
    {
        return view('admin.breaking', [
            'current' => BreakingAlert::with('creator', 'article.translations')->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->orderBy('priority')->latest()->get(),
            'history' => BreakingAlert::with('creator')->where(fn ($q) => $q->where('is_active', false)->orWhere('expires_at', '<=', now()))
                ->latest()->limit(30)->get(),
        ]);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'locale' => 'required|in:en,so',
            'headline' => 'required|string|max:200',
            'url' => 'nullable|string|max:255',
            'article_id' => 'nullable|exists:articles,id',
            'priority' => 'required|integer|between:1,3',
            'is_urgent_banner' => 'nullable|boolean',
            'duration' => 'nullable|integer|min:0|max:10080',
            'starts_at' => 'nullable|date',
        ]);
        if (! empty($data['url']) && ! preg_match('~^(https?://|/)~', $data['url'])) {
            abort(422, 'Link must be a full URL or start with /');
        }
        $data['is_urgent_banner'] = (bool) ($data['is_urgent_banner'] ?? false);
        $data['starts_at'] = ! empty($data['starts_at']) ? Carbon::parse($data['starts_at'], config('doogsan.timezone'))->utc() : null;
        $data['expires_at'] = ! empty($data['duration']) ? ($data['starts_at'] ?? now())->copy()->addMinutes((int) $data['duration']) : null;
        unset($data['duration']);

        return $data;
    }

    protected function notify(string $locale): void
    {
        Cache::forget("pulse.{$locale}");
        Cache::forget("home.{$locale}");
        safe_broadcast(new BreakingNewsChanged($locale));
    }

    public function store(Request $request)
    {
        $alert = BreakingAlert::create($this->validated($request) + ['created_by' => $request->user()->id, 'is_active' => true]);
        AuditLog::record('breaking.published', "Breaking ({$alert->locale}): {$alert->headline}", $alert);
        $this->notify($alert->locale);

        return back()->with('status', __('Breaking alert is live on the :l edition.', ['l' => editions()[$alert->locale]['name']]));
    }

    public function update(Request $request, BreakingAlert $alert)
    {
        $old = $alert->locale;
        $alert->update($this->validated($request));
        AuditLog::record('breaking.updated', "Updated breaking alert: {$alert->headline}", $alert);
        $this->notify($alert->locale);
        if ($old !== $alert->locale) {
            $this->notify($old);
        }

        return back()->with('status', __('Alert updated.'));
    }

    public function expire(BreakingAlert $alert)
    {
        $alert->update(['is_active' => false, 'expires_at' => $alert->expires_at && $alert->expires_at->isPast() ? $alert->expires_at : now()]);
        AuditLog::record('breaking.expired', "Removed breaking alert: {$alert->headline}", $alert);
        $this->notify($alert->locale);

        return back()->with('status', __('Alert removed from the site.'));
    }

    public function destroy(BreakingAlert $alert)
    {
        $locale = $alert->locale;
        AuditLog::record('breaking.deleted', "Deleted breaking alert: {$alert->headline}", $alert);
        $alert->delete();
        $this->notify($locale);

        return back()->with('status', __('Alert deleted.'));
    }
}
