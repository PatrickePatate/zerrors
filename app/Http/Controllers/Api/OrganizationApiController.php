<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use Illuminate\Http\Request;

class OrganizationApiController extends Controller
{
    public function index(Request $request)
    {
        return OrganizationResource::collection($request->user()->organizations()->orderBy('name')->get());
    }
}
