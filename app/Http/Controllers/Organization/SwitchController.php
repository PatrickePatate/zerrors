<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SwitchController extends Controller
{
    /**
     * Landing page after login: pick an organization, or jump straight in if there's only one.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $organizations = $request->user()->organizations()->orderBy('name')->get();

        if ($organizations->count() === 1) {
            return redirect()->route('organizations.overview', $organizations->first());
        }

        return view('fault.organizations.picker', ['organizations' => $organizations]);
    }

    /**
     * Always shows the organization picker — used by explicit "manage organizations"
     * links, unlike index() which shortcuts straight through when there's only one.
     */
    public function list(Request $request): View
    {
        return view('fault.organizations.picker', [
            'organizations' => $request->user()->organizations()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $organization = Organization::create($data);
        $organization->users()->attach($request->user()->id, ['role' => 'owner']);

        return redirect()->route('organizations.overview', $organization);
    }
}
