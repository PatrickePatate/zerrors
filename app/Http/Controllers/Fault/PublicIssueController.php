<?php

namespace App\Http\Controllers\Fault;

use App\Http\Controllers\Controller;
use App\Models\FaultIssueShareLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicIssueController extends Controller
{
    public function show(string $token): View
    {
        $link = FaultIssueShareLink::where('token', $token)->first();

        if (! $link || $link->isRevoked()) {
            return view('fault.share.invalid');
        }

        if ($link->isExpired()) {
            return view('fault.share.expired');
        }

        if ($link->isPasswordProtected() && ! session()->get("share_link_unlocked.{$link->token}")) {
            return view('fault.share.password', ['link' => $link]);
        }

        $issue = $link->issue()->with('project')->firstOrFail();
        $currentEvent = $issue->events()->orderByDesc('occurred_at')->orderByDesc('id')->first();

        return view('fault.share.show', [
            'link' => $link,
            'issue' => $issue,
            'project' => $issue->project,
            'currentEvent' => $currentEvent,
            'events' => $issue->events()->latest('occurred_at')->paginate(20),
        ]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $link = FaultIssueShareLink::where('token', $token)->firstOrFail();

        abort_unless($link->isPasswordProtected(), 404);

        if ($link->isRevoked() || $link->isExpired()) {
            return redirect()->route('share.show', $token);
        }

        $data = $request->validate(['password' => ['required', 'string']]);

        if (! $link->checkPassword($data['password'])) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        session()->put("share_link_unlocked.{$link->token}", true);

        return redirect()->route('share.show', $token);
    }
}
