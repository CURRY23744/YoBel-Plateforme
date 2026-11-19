<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PlanningApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_guest_can_manage_only_their_own_planning(): void
    {
        $project = $this->makeProject();
        $guest = $this->makeUser('guest');
        $this->addMember($project, $guest, 'contributor');
        $other = $this->makeUser('guest');
        $this->actingAsUser($guest);

        $weekStart = now()->startOfWeek()->toDateString();
        $this->getJson('/api/planning?week_start='.$weekStart)
            ->assertOk()
            ->assertJsonPath('user.id', $guest->id)
            ->assertJsonMissingPath('team');

        $weekly = collect(range(1, 7))->map(fn (int $weekday) => [
            'weekday' => $weekday,
            'is_available' => $weekday === 1,
            'start_time' => $weekday === 1 ? '09:00' : null,
            'end_time' => $weekday === 1 ? '17:00' : null,
        ])->all();

        $this->putJson('/api/planning', [
            'week_start' => $weekStart,
            'weekly' => $weekly,
            'exceptions' => [],
        ])->assertOk()
            ->assertJsonPath('days.0.is_available', true);

        $this->assertDatabaseHas('user_weekly_availabilities', [
            'user_id' => $guest->id,
            'weekday' => 1,
            'is_available' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
        $this->getJson('/api/planning?user_id='.$other->id)->assertForbidden();
    }
}
