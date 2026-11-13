<?php

namespace Tests\Feature\Api;

use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\SourceMap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Unit\Support\SourceMapDecoderTest;

class SourceMapApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private FaultProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->organization = Organization::factory()->create();
        $this->project = FaultProject::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function url(string $suffix = '', ?FaultProject $project = null, ?Organization $organization = null): string
    {
        $organization ??= $this->organization;
        $project ??= $this->project;

        return "/api/v1/organizations/{$organization->slug}/projects/{$project->slug}/releases/1.0.0/sourcemaps{$suffix}";
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        $this->organization->users()->attach($user->id, ['role' => $role]);

        return $user;
    }

    private function upload(string $name = '~/assets/app.js', ?string $contents = null): array
    {
        return [
            'name' => $name,
            'file' => UploadedFile::fake()->createWithContent('app.js.map', $contents ?? SourceMapDecoderTest::sampleMap()),
        ];
    }

    public function test_admin_can_upload_list_and_delete_source_maps(): void
    {
        Sanctum::actingAs($this->member('admin'), ['read', 'write']);

        $this->post($this->url(), $this->upload(), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.name', '~/assets/app.js');

        $map = SourceMap::sole();
        Storage::assertExists($map->path);

        $this->post($this->url(), $this->upload(), ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(1, SourceMap::count());

        $this->getJson($this->url())->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson($this->url("/{$map->id}"))->assertNoContent();
        Storage::assertMissing($map->path);
        $this->assertSame(0, SourceMap::count());
    }

    public function test_members_can_list_but_not_upload_or_delete(): void
    {
        $map = SourceMap::factory()->create(['fault_project_id' => $this->project->id, 'release' => '1.0.0']);
        Sanctum::actingAs($this->member('member'), ['read', 'write']);

        $this->getJson($this->url())->assertOk()->assertJsonCount(1, 'data');
        $this->post($this->url(), $this->upload(), ['Accept' => 'application/json'])->assertForbidden();
        $this->deleteJson($this->url("/{$map->id}"))->assertForbidden();
    }

    public function test_write_ability_is_required_to_upload(): void
    {
        Sanctum::actingAs($this->member('owner'), ['read']);

        $this->post($this->url(), $this->upload(), ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_other_organizations_cannot_access_the_project(): void
    {
        $outsider = User::factory()->create();
        $other = Organization::factory()->create();
        $other->users()->attach($outsider->id, ['role' => 'owner']);
        Sanctum::actingAs($outsider, ['read', 'write']);

        $this->getJson($this->url())->assertForbidden();
        $this->post($this->url(), $this->upload(), ['Accept' => 'application/json'])->assertForbidden();

        $this->getJson($this->url('', $this->project, $other))->assertNotFound();
    }

    public function test_deleting_requires_the_map_to_belong_to_the_project_and_release(): void
    {
        $otherProject = FaultProject::factory()->create(['organization_id' => $this->organization->id]);
        $map = SourceMap::factory()->create(['fault_project_id' => $otherProject->id, 'release' => '1.0.0']);
        Sanctum::actingAs($this->member('owner'), ['read', 'write']);

        $this->deleteJson($this->url("/{$map->id}"))->assertNotFound();
        $this->assertSame(1, SourceMap::count());
    }

    public function test_invalid_and_oversized_files_are_rejected(): void
    {
        Sanctum::actingAs($this->member('owner'), ['read', 'write']);

        $this->post($this->url(), $this->upload(contents: 'not a map'), ['Accept' => 'application/json'])->assertUnprocessable();

        config(['fault.max_source_map_bytes' => 1024]);
        $this->post($this->url(), $this->upload(contents: str_repeat('x', 4096)), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_the_per_release_limit_applies_to_new_artifacts_only(): void
    {
        config(['fault.max_source_maps_per_release' => 1]);
        Sanctum::actingAs($this->member('owner'), ['read', 'write']);

        $this->post($this->url(), $this->upload('~/a.js'), ['Accept' => 'application/json'])->assertCreated();
        $this->post($this->url(), $this->upload('~/b.js'), ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($this->url(), $this->upload('~/a.js'), ['Accept' => 'application/json'])->assertOk();
    }
}
