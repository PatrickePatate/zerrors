<?php

namespace App\Http\Controllers;

use App\Models\StatusPage;
use App\Support\Status\StatusPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class StatusPageController extends Controller
{
    public function show(string $slug, StatusPageData $data): View
    {
        return view('status.show', ['status' => $data->forPage($this->publicPage($slug))]);
    }

    public function json(string $slug, StatusPageData $data): JsonResponse
    {
        return response()->json($data->forPage($this->publicPage($slug)));
    }

    protected function publicPage(string $slug): StatusPage
    {
        return StatusPage::where('slug', $slug)->where('is_public', true)->firstOrFail();
    }
}
