<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'project_type_id' => fn (array $attributes) => ProjectType::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'reference' => 'PRJ-'.now()->year.'-'.Str::upper(Str::random(6)),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => 'in_progress',
            'priority' => 'medium',
            'start_date' => now()->subMonth()->toDateString(),
            'planned_end_date' => now()->addMonths(2)->toDateString(),
        ];
    }
}
