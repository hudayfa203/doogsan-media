<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterIssue;
use App\Models\AuditLog;
use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    public function index(Request $request)
    {
        $growth = Subscriber::whereNotNull('confirmed_at')->where('confirmed_at', '>=', now()->subDays(29)->startOfDay())->get(['confirmed_at'])
            ->groupBy(fn ($s) => local_time($s->confirmed_at)->toDateString())->map->count();

        return view('admin.newsletter', [
            'subscribers' => Subscriber::when($request->input('q'), fn ($q, $s) => $q->where('email', 'like', "%{$s}%"))
                ->latest()->paginate(30)->withQueryString(),
            'stats' => [
                'active' => Subscriber::active()->count(),
                'pending' => Subscriber::whereNull('confirmed_at')->count(),
                'unsubscribed' => Subscriber::whereNotNull('unsubscribed_at')->count(),
                'by_locale' => Subscriber::active()->select('locale', DB::raw('COUNT(*) as c'))->groupBy('locale')->pluck('c', 'locale'),
            ],
            'growth' => collect(range(29, 0))->mapWithKeys(fn ($d) => [$k = now()->subDays($d)->setTimezone(config('doogsan.timezone'))->toDateString() => $growth[$k] ?? 0]),
            'campaigns' => NewsletterCampaign::latest()->limit(20)->get(),
        ]);
    }

    public function export()
    {
        AuditLog::record('newsletter.export', 'Exported subscriber list');

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'edition', 'confirmed_at', 'source']);
            Subscriber::active()->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $s) {
                    fputcsv($out, [$s->email, $s->locale, $s->confirmed_at?->toIso8601String(), $s->source]);
                }
            });
            fclose($out);
        }, 'doogsan-subscribers-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'locale' => 'required|in:en,so',
            'subject' => 'required|string|max:190',
            'intro' => 'nullable|string|max:2000',
            'article_ids' => 'required|array|min:1|max:12',
            'article_ids.*' => 'integer|exists:articles,id',
        ]);
        NewsletterCampaign::create($data + ['created_by' => $request->user()->id]);

        return back()->with('status', __('Campaign drafted.'));
    }

    public function send(NewsletterCampaign $campaign)
    {
        abort_if($campaign->status !== 'draft', 422, 'Already sent');
        $campaign->update(['status' => 'sending']);
        $count = 0;
        Subscriber::active()->where('locale', $campaign->locale)->chunk(200, function ($subs) use ($campaign, &$count) {
            foreach ($subs as $s) {
                Mail::to($s->email)->queue(new NewsletterIssue($campaign, $s));
                $count++;
            }
        });
        $campaign->update(['status' => 'sent', 'sent_at' => now(), 'recipients_count' => $count]);
        AuditLog::record('newsletter.sent', "Sent “{$campaign->subject}” to {$count} subscribers", $campaign);

        return back()->with('status', __('Queued for :n subscribers.', ['n' => $count]));
    }

    public function destroySubscriber(Subscriber $subscriber)
    {
        $subscriber->delete();
        AuditLog::record('newsletter.subscriber_deleted', 'Deleted a subscriber record');

        return back()->with('status', __('Subscriber removed.'));
    }
}
