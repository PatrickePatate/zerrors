<?php

namespace Database\Factories;

use App\Models\FaultProject;
use App\Models\SourceMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourceMap>
 */
class SourceMapFactory extends Factory
{
    public function definition(): array
    {
        $artifactName = '~/assets/'.$this->faker->unique()->word().'.js';

        return [
            'fault_project_id' => FaultProject::factory(),
            'release' => '1.0.0',
            'artifact_name' => $artifactName,
            'artifact_hash' => sha1($artifactName),
            'path' => 'source-maps/test/'.sha1($artifactName).'.map',
            'size' => 1024,
            'sha1' => sha1($artifactName),
        ];
    }
}
