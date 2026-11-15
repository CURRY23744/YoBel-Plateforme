<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_login_returns_a_token(): void
    {
        $user = $this->makeUser('member', null, ['email' => 'jean@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'jean@example.com', 'password' => 'Password123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'must_change_password', 'user' => ['id', 'email', 'role']])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->makeUser('member', null, ['email' => 'jean@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'jean@example.com', 'password' => 'mauvais'])
            ->assertStatus(401);

        $this->postJson('/api/auth/login', ['email' => 'inconnu@example.com', 'password' => 'x'])
            ->assertStatus(401);
    }

    public function test_account_is_locked_after_too_many_failures(): void
    {
        $this->makeUser('member', null, ['email' => 'jean@example.com']);

        for ($i = 0; $i < config('platform.max_login_attempts'); $i++) {
            $this->postJson('/api/auth/login', ['email' => 'jean@example.com', 'password' => 'mauvais'])->assertStatus(401);
        }

        $this->postJson('/api/auth/login', ['email' => 'jean@example.com', 'password' => 'Password123'])
            ->assertStatus(423)
            ->assertJsonPath('code', 'account_locked');
    }

    public function test_inactive_account_cannot_login(): void
    {
        $this->makeUser('member', null, ['email' => 'jean@example.com', 'is_active' => false]);

        $this->postJson('/api/auth/login', ['email' => 'jean@example.com', 'password' => 'Password123'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'account_disabled');
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/projects')->assertUnauthorized();
    }

    public function test_me_returns_current_user(): void
    {
        $user = $this->actingAsUser($this->makeUser('admin'));

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_temporary_password_must_be_changed_first(): void
    {
        $this->actingAsUser($this->makeUser('member', null, ['must_change_password' => true]));

        $this->getJson('/api/projects')->assertStatus(403)->assertJsonPath('code', 'password_change_required');

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'Password123',
            'password' => 'NouveauPass456',
            'password_confirmation' => 'NouveauPass456',
        ])->assertOk();

        $this->getJson('/api/projects')->assertOk();
    }

    public function test_change_password_requires_correct_current_password_and_strength(): void
    {
        $this->actingAsUser($this->makeUser());

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'faux', 'password' => 'NouveauPass456', 'password_confirmation' => 'NouveauPass456',
        ])->assertStatus(422);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'Password123', 'password' => 'faible', 'password_confirmation' => 'faible',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_logout_deletes_the_token(): void
    {
        $user = $this->makeUser('member', null, ['email' => 'jean@example.com']);
        $token = $this->postJson('/api/auth/login', ['email' => 'jean@example.com', 'password' => 'Password123'])->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_can_update_profile(): void
    {
        $user = $this->actingAsUser($this->makeUser());

        $this->putJson('/api/auth/profile', ['phone' => '+33600000000', 'job_title' => 'Développeuse'])
            ->assertOk()->assertJsonPath('job_title', 'Développeuse');

        $this->assertSame('+33600000000', User::find($user->id)->phone);
    }

    public function test_password_reset_link_is_sent_without_revealing_accounts(): void
    {
        Notification::fake();
        $user = $this->makeUser('member', null, ['email' => 'jean@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'jean@example.com'])->assertOk();
        $this->postJson('/api/auth/forgot-password', ['email' => 'inconnu@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }
}
