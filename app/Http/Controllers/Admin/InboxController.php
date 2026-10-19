<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\NewsTip;
use App\Models\User;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'messages');

        return view('admin.inbox', [
            'tab' => $tab,
            'messages' => $tab === 'messages' ? ContactMessage::when($request->input('status'), fn ($q, $s) => $q->where('status', $s))->latest()->paginate(25)->withQueryString() : null,
            'tips' => $tab === 'tips' ? NewsTip::with('assignee')->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))->latest()->paginate(25)->withQueryString() : null,
            'counts' => ['messages' => ContactMessage::where('status', 'new')->count(), 'tips' => NewsTip::where('status', 'new')->count()],
        ]);
    }

    public function message(ContactMessage $message)
    {
        if ($message->status === 'new') {
            $message->update(['status' => 'read']);
        }

        return view('admin.inbox-message', ['message' => $message]);
    }

    public function updateMessage(Request $request, ContactMessage $message)
    {
        $message->update($request->validate(['status' => 'required|in:new,read,replied,archived']));

        return back()->with('status', __('Updated.'));
    }

    /** Tip contents are decrypted only on this screen and every view is audited. */
    public function tip(NewsTip $tip)
    {
        AuditLog::record('tip.viewed', "Viewed news tip #{$tip->id}", $tip);
        if ($tip->status === 'new') {
            $tip->update(['status' => 'reviewing']);
        }

        return view('admin.inbox-tip', ['tip' => $tip->load('media', 'assignee'), 'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function updateTip(Request $request, NewsTip $tip)
    {
        $tip->update($request->validate(['status' => 'required|in:new,reviewing,assigned,closed', 'assigned_to' => 'nullable|exists:users,id']));
        AuditLog::record('tip.updated', "News tip #{$tip->id} → {$tip->status}", $tip);

        return back()->with('status', __('Tip updated.'));
    }
}
