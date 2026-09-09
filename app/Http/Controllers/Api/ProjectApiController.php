<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaultProjectResource;
use App\Models\Organization;
use Illuminate\Http\Request;

class ProjectApiController extends Controller
{
    public function index(Request $request, Organization $organization)
    {
        $this->authorizeMembership($request, $organization);

        return FaultProjectResource::collection($organization->projects()->orderBy('name')->get());
    }

    protected function authorizeMembership(Request $request, Organization $organization): void
    {
        abort_unless($organization->roleFor($request->user()) !== null, 403);
    }
}
