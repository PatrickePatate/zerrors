<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request, Organization $organization): View
    {
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);

        $logs = $organization->auditLogs()->with('user')->latest()->paginate(30);

        return view('fault.organizations.audit', [
            'organization' => $organization,
            'logs' => $logs,
        ]);
    }
}
