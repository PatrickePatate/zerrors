<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StatusPageAdminController extends Controller
{
    public function edit(Request $request, Organization $organization): View
    {
        Gate::authorize('manage', $organization);

        return view('status.admin', ['organization' => $organization]);
    }
}
