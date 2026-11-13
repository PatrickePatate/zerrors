<?php

namespace Database\Factories;

use App\Models\FaultAttachment;
use App\Models\FaultProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FaultAttachment>
 */
class FaultAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fault_project_id' => FaultProject::factory(),
            'event_id' => (string) Str::uuid(),
            'filename' => 'log.txt',
            'content_type' => 'text/plain',
            'attachment_type' => 'event.attachment',
            'size' => 10,
            'path' => 'fault-attachments/test/'.Str::uuid(),
        ];
    }
}
