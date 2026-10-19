<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdPlacement;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AdController extends Controller
{
    public function index()
    {
        return view('admin.ads.index', [
            'ads' => AdPlacement::with('media')->orderBy('slot')->latest()->get(),
            'inventory' => collect(AdPlacement::SLOTS)->map(fn ($label, $slot) => [
                'label' => $label,
                'active' => AdPlacement::where('slot', $slot)->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->count(),
            ]),
        ]);
    }

    public function create()
    {
        return view('admin.ads.form', ['ad' => new AdPlacement(['is_active' => true, 'weight' => 1])]);
    }

    public function edit(AdPlacement $ad)
    {
        return view('admin.ads.form', ['ad' => $ad]);
    }

    protected function data(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'slot' => ['required', Rule::in(array_keys(AdPlacement::SLOTS))],
            'locale' => 'nullable|in:en,so',
            'advertiser' => 'nullable|string|max:120',
            'media_id' => 'required|exists:media_assets,id',
            'target_url' => 'nullable|url|max:255',
            'alt_text' => 'nullable|string|max:190',
            'weight' => 'required|integer|between:1,10',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]);
        $tz = config('doogsan.timezone');
        foreach (['starts_at', 'ends_at'] as $k) {
            $data[$k] = ! empty($data[$k]) ? Carbon::parse($data[$k], $tz)->utc() : null;
        }
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    public function store(Request $request)
    {
        $ad = AdPlacement::create($this->data($request));
        AuditLog::record('ads.created', "Created ad {$ad->name}", $ad);

        return redirect()->route('admin.ads.index')->with('status', __('Campaign saved.'));
    }

    public function update(Request $request, AdPlacement $ad)
    {
        $ad->update($this->data($request));
        AuditLog::record('ads.updated', "Updated ad {$ad->name}", $ad);

        return redirect()->route('admin.ads.index')->with('status', __('Campaign saved.'));
    }

    public function destroy(AdPlacement $ad)
    {
        $ad->delete();

        return back()->with('status', __('Campaign deleted.'));
    }
}
