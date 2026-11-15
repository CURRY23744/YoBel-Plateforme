<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends ApiController
{
    public function index(Request $request)
    {
        $users = User::where('organization_id', $this->me()->organization_id)
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.$request->query('search').'%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $s)->orWhere('last_name', 'like', $s)->orWhere('email', 'like', $s));
            })
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate($this->perPage($request));

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        $this->ensureSameOrganization($user);

        $user->load(['memberships' => fn ($q) => $q->with('project:id,reference,name,status,project_type_id')->latest()]);

        return response()->json($user);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => ['required', Rule::enum(Role::class)],
            'phone' => 'nullable|string|max:30',
            'job_title' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'employee_number' => 'nullable|string|max:50',
        ]);

        $temporary = Str::password(12, symbols: false);

        $user = User::create($data + [
            'organization_id' => $this->me()->organization_id,
            'password' => $temporary,
            'must_change_password' => true,
            'created_by' => $this->me()->id,
        ]);

        $this->sendCredentials($user, $temporary);

        return response()->json(['user' => $user, 'temporary_password' => $temporary], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureSameOrganization($user);

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', Rule::enum(Role::class)],
            'phone' => 'nullable|string|max:30',
            'job_title' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'employee_number' => 'nullable|string|max:50',
        ]);

        if ($user->id === $this->me()->id && isset($data['role']) && $data['role'] !== $user->role->value) {
            return response()->json(['message' => 'Vous ne pouvez pas modifier votre propre rôle.'], 422);
        }

        $user->update($data);

        return response()->json($user->fresh());
    }

    public function destroy(User $user): JsonResponse
    {
        $this->ensureSameOrganization($user);

        if ($user->id === $this->me()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(null, 204);
    }

    public function toggleActive(User $user): JsonResponse
    {
        $this->ensureSameOrganization($user);

        if ($user->id === $this->me()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas désactiver votre propre compte.'], 422);
        }

        $active = ! $user->is_active;
        $user->update(['is_active' => $active, 'deactivated_at' => $active ? null : now()]);

        if (! $active) {
            $user->tokens()->delete();
        }

        return response()->json($user->fresh());
    }

    public function resetPassword(User $user): JsonResponse
    {
        $this->ensureSameOrganization($user);

        $temporary = Str::password(12, symbols: false);
        $user->update(['password' => $temporary, 'must_change_password' => true, 'failed_login_attempts' => 0, 'locked_until' => null]);
        $user->tokens()->delete();

        $this->sendCredentials($user, $temporary);

        return response()->json(['temporary_password' => $temporary]);
    }

    private function sendCredentials(User $user, string $temporary): void
    {
        try {
            Mail::raw(
                "Bonjour {$user->first_name},\n\nUn accès à la plateforme a été créé pour vous.\n\n"
                ."Adresse e-mail : {$user->email}\nMot de passe temporaire : {$temporary}\n\n"
                .'Connectez-vous : '.config('platform.frontend_url')."\nVous devrez changer ce mot de passe à votre première connexion.",
                fn ($message) => $message->to($user->email)->subject('Votre accès à la plateforme')
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
