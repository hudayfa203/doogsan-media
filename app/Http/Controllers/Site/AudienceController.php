<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\ConfirmSubscription;
use App\Models\AdPlacement;
use App\Models\ContactMessage;
use App\Models\NewsTip;
use App\Models\Subscriber;
use App\Services\MediaService;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AudienceController extends Controller
{
    public function contact(Seo $seo)
    {
        $seo->set(['title' => __('Contact Us'), 'canonical' => lroute('contact'),
            'alternates' => collect(editions())->mapWithKeys(fn ($e, $l) => [$l => lroute('contact', [], $l)])->all()]);

        return view('site.contact');
    }

    public function sendContact(Request $request)
    {
        if ($request->filled('website')) { // honeypot
            return back()->with('status', __('Thank you. Your message has been received.'));
        }
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:190',
            'department' => 'required|in:general,editorial,advertising,corrections,technical',
            'subject' => 'nullable|string|max:190',
            'message' => 'required|string|min:10|max:5000',
        ]);

        ContactMessage::create($data + ['locale' => edition(), 'ip_hash' => hash('sha256', $request->ip().config('app.key'))]);

        return back()->with('status', __('Thank you. Your message has been received and our team will reply by email.'));
    }

    public function tip(Seo $seo)
    {
        $seo->set(['title' => __('Submit a News Tip'), 'canonical' => lroute('tip'), 'noindex' => false]);

        return view('site.tip');
    }

    public function sendTip(Request $request, MediaService $media)
    {
        if ($request->filled('website')) {
            return back()->with('status', __('Thank you. Your tip has been received securely.'));
        }
        $data = $request->validate([
            'name' => 'nullable|string|max:120',
            'contact' => 'nullable|string|max:190',
            'wants_anonymity' => 'nullable|boolean',
            'location' => 'nullable|string|max:120',
            'details' => 'required|string|min:20|max:10000',
            'attachment' => 'nullable|file|max:20480|mimes:jpg,jpeg,png,webp,pdf,mp4,mp3,m4a',
            'consent' => 'accepted',
        ]);

        $asset = $request->hasFile('attachment')
            ? $media->store($request->file('attachment'), null, ['folder' => 'tips'])
            : null;

        NewsTip::create([
            'locale' => edition(),
            'name' => $data['name'] ?? null,
            'contact' => $data['contact'] ?? null,
            'wants_anonymity' => (bool) ($data['wants_anonymity'] ?? true),
            'location' => $data['location'] ?? null,
            'details' => $data['details'],
            'media_id' => $asset?->id,
        ]);

        return back()->with('status', __('Thank you. Your tip has been received securely. An editor will review it.'));
    }

    public function newsletter(Seo $seo)
    {
        $seo->set(['title' => __('Newsletter'), 'canonical' => lroute('newsletter')]);

        return view('site.newsletter');
    }

    public function subscribe(Request $request)
    {
        if ($request->filled('website')) {
            return back()->with('status', __('Almost done — check your inbox to confirm your subscription.'));
        }
        $data = $request->validate(['email' => 'required|email:rfc|max:190']);
        $email = Str::lower($data['email']);

        $sub = Subscriber::firstOrNew(['email' => $email]);
        if ($sub->exists && $sub->confirmed_at && ! $sub->unsubscribed_at) {
            return back()->with('status', __('You are already subscribed. Thank you!'));
        }
        $sub->fill(['locale' => edition(), 'token' => Str::random(48), 'unsubscribed_at' => null, 'source' => $request->input('source', 'site')])->save();

        try {
            Mail::to($email)->send(new ConfirmSubscription($sub));
        } catch (\Throwable $e) {
            Log::error('Subscription email failed: '.$e->getMessage());
        }

        return back()->with('status', __('Almost done — check your inbox to confirm your subscription.'));
    }

    public function confirm(string $token)
    {
        $sub = Subscriber::where('token', $token)->firstOrFail();
        $sub->update(['confirmed_at' => $sub->confirmed_at ?? now(), 'unsubscribed_at' => null]);

        return view('site.message', ['title' => __('Subscription confirmed'), 'message' => __('You will now receive the Doogsan Media newsletter at :email.', ['email' => $sub->email])]);
    }

    public function unsubscribe(string $token)
    {
        $sub = Subscriber::where('token', $token)->firstOrFail();
        $sub->update(['unsubscribed_at' => now()]);

        return view('site.message', ['title' => __('Unsubscribed'), 'message' => __('You will no longer receive our newsletter.')]);
    }

    public function adClick(AdPlacement $ad)
    {
        abort_unless($ad->target_url && str_starts_with($ad->target_url, 'http'), 404);
        $ad->increment('clicks');

        return redirect()->away($ad->target_url);
    }
}
