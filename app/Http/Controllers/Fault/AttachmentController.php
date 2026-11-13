<?php

namespace App\Http\Controllers\Fault;

use App\Http\Controllers\Controller;
use App\Models\FaultAttachment;
use App\Models\FaultProject;
use App\Models\Organization;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Always served as a download with a neutral content type, never inline.
     */
    public function download(Organization $organization, FaultProject $project, FaultAttachment $attachment): StreamedResponse
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($attachment->fault_project_id === $project->id, 404);
        abort_unless(Storage::exists($attachment->path), 404);

        return Storage::download($attachment->path, $attachment->filename, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
