<?php

namespace App\Http\Controllers\Fault;

use App\Http\Controllers\Controller;
use App\Models\FaultProject;
use App\Models\Organization;
use Illuminate\View\View;

class CronMonitorController extends Controller
{
    public function index(Organization $organization, FaultProject $project): View
    {
        abort_unless($project->organization_id === $organization->id, 404);

        $monitors = $project->cronMonitors()
            ->with(['checkIns' => fn ($query) => $query->latest('id')->limit(20)])
            ->orderBy('name')
            ->get();

        return view('fault.crons.index', compact('organization', 'project', 'monitors'));
    }
}
