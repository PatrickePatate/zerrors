<?php

namespace Tests\Feature\Fault;

use App\Models\FaultAttachment;
use App\Models\FaultEvent;
use App\Models\FaultFeedback;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class FeedbackAndAttachmentsTest extends IngestExtrasTestCase
{
    private function eventItem(string $id): array
    {
        return [['type' => 'event'], json_encode($this->errorPayload($id))];
    }

    public function test_user_report_after_the_event_links_to_the_issue(): void
    {
        $project = FaultProject::factory()->create();
        $id = str_repeat('1', 32);
        $this->sendEnvelope($project, [$this->eventItem($id)]);
        $this->sendEnvelope($project, [[['type' => 'user_report'], json_encode(['event_id' => $id, 'name' => 'Ann', 'email' => 'ann@x.test', 'comments' => '<b>it broke</b>'])]]);

        $feedback = FaultFeedback::firstOrFail();
        $this->assertSame(FaultIssue::firstOrFail()->id, $feedback->fault_issue_id);
        $this->assertSame(FaultEvent::firstOrFail()->id, $feedback->fault_event_id);

        $user = User::factory()->create();
        $project->organization->users()->attach($user->id, ['role' => 'member']);
        $this->actingAs($user)
            ->get(route('organizations.issues.show', [$project->organization, $project, $feedback->issue]))
            ->assertOk()->assertSee('&lt;b&gt;it broke&lt;/b&gt;', false);
    }

    public function test_feedback_arriving_before_its_event_is_linked_when_the_event_is_processed(): void
    {
        $project = FaultProject::factory()->create();
        $id = str_repeat('2', 32);
        $this->sendEnvelope($project, [[['type' => 'feedback'], json_encode(['contexts' => ['feedback' => [
            'message' => 'late', 'contact_email' => 'a@x.test', 'name' => 'Al', 'associated_event_id' => $id,
        ]]])]]);

        $this->assertNull(FaultFeedback::firstOrFail()->fault_issue_id);
        $this->assertSame('a@x.test', FaultFeedback::first()->email);

        $this->sendEnvelope($project, [$this->eventItem($id)]);

        $this->assertSame(FaultIssue::firstOrFail()->id, FaultFeedback::first()->fault_issue_id);
    }

    public function test_attachments_are_stored_and_limits_enforced(): void
    {
        Storage::fake();
        config(['fault.max_attachment_bytes' => 12, 'fault.max_attachments_per_event' => 2]);
        $project = FaultProject::factory()->create();
        $id = str_repeat('3', 32);
        $attachment = fn (string $name, string $content) => [
            ['type' => 'attachment', 'filename' => $name, 'content_type' => 'text/html', 'attachment_type' => 'event.attachment', 'length' => strlen($content)],
            $content,
        ];

        $this->sendEnvelope($project, [
            $this->eventItem($id),
            $attachment('a.txt', "line\nbinary"),
            $attachment('big.txt', str_repeat('x', 13)),
            $attachment('b.txt', 'bbb'),
            $attachment('c.txt', 'ccc'),
        ], $id)->assertOk();

        $this->assertSame(['a.txt', 'b.txt'], FaultAttachment::orderBy('id')->pluck('filename')->all());
        $stored = FaultAttachment::first();
        $this->assertSame(FaultEvent::firstOrFail()->id, $stored->fault_event_id);
        $this->assertSame("line\nbinary", Storage::get($stored->path));
    }

    public function test_download_requires_org_membership_and_forces_download(): void
    {
        Storage::fake();
        $project = FaultProject::factory()->create();
        $other = Organization::factory()->create();
        $path = "fault-attachments/{$project->id}/x/file";
        Storage::put($path, '<script>alert(1)</script>');
        $attachment = FaultAttachment::factory()->create([
            'fault_project_id' => $project->id, 'filename' => 'e.html', 'content_type' => 'text/html', 'path' => $path,
        ]);

        $member = User::factory()->create();
        $project->organization->users()->attach($member->id, ['role' => 'member']);
        $outsider = User::factory()->create();
        $other->users()->attach($outsider->id, ['role' => 'member']);

        $ok = $this->actingAs($member)->get(route('organizations.attachments.download', [$project->organization, $project, $attachment]));
        $ok->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringContainsString('attachment', $ok->headers->get('Content-Disposition'));

        $this->actingAs($outsider)->get(route('organizations.attachments.download', [$project->organization, $project, $attachment]))->assertForbidden();
        $this->actingAs($outsider)->get(route('organizations.attachments.download', [$other, $project, $attachment]))->assertNotFound();
        $this->actingAs($member)->get(route('organizations.attachments.download', [$project->organization, $project, FaultAttachment::factory()->create()]))->assertNotFound();
    }

    public function test_pruning_events_deletes_their_attachments(): void
    {
        Storage::fake();
        $project = FaultProject::factory()->create(['retention_days' => 7]);
        $event = FaultEvent::factory()->create(['fault_project_id' => $project->id, 'occurred_at' => now()->subDays(30)]);
        Storage::put('fault-attachments/old', 'x');
        $attachment = FaultAttachment::factory()->create([
            'fault_project_id' => $project->id, 'fault_event_id' => $event->id, 'path' => 'fault-attachments/old',
        ]);

        $this->artisan('zerrors:prune-events')->assertSuccessful();

        Storage::assertMissing('fault-attachments/old');
        $this->assertDatabaseMissing('fault_attachments', ['id' => $attachment->id]);
    }
}
