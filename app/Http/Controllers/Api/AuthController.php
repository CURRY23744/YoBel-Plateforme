<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device' => 'nullable|string|max:100',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Identifiants incorrects.'], 401);
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            return response()->json([
                'message' => 'Compte temporairement bloqué suite à plusieurs échecs. Réessayez plus tard.',
                'code' => 'account_locked',
                'locked_until' => $user->locked_until,
            ], 423);
        }

        if (! Hash::check($data['password'], $user->password)) {
            $attempts = $user->failed_login_attempts + 1;
            $user->forceFill(['failed_login_attempts' => $attempts]);

            if ($attempts >= config('platform.max_login_attempts')) {
                $user->locked_until = now()->addMinutes(config('platform.lockout_minutes'));
                $user->failed_login_attempts = 0;
            }

            $user->saveQuietly();

            return response()->json(['message' => 'Identifiants incorrects.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Compte désactivé. Contactez un administrateur.', 'code' => 'account_disabled'], 403);
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        return response()->json([
            'token' => $user->createToken($data['device'] ?? 'api')->plainTextToken,
            'must_change_password' => $user->must_change_password,
            'user' => $user->load('organization:id,name,slug'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function currentUser(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('organization:id,name,slug,timezone,locale,currency'));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:30',
            'job_title' => 'nullable|string|max:150',
            'bio' => 'nullable|string|max:2000',
            'locale' => 'nullable|in:fr,en',
            'timezone' => 'nullable|timezone',
            'notification_preferences' => 'nullable|array',
            'notification_preferences.muted' => 'nullable|array',
            'notification_preferences.muted.*' => 'string|max:50',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars/'.$request->user()->id, 'public');
        }
        unset($data['avatar']);

        $request->user()->update($data);

        return response()->json($request->user()->fresh());
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect.', 'errors' => ['current_password' => ['Mot de passe actuel incorrect.']]], 422);
        }

        if (Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Le nouveau mot de passe doit être différent de l\'ancien.', 'errors' => ['password' => ['Choisissez un mot de passe différent.']]], 422);
        }

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        // Déconnecte les autres appareils
        $current = $user->currentAccessToken();
        $user->tokens()->when($current && isset($current->id), fn ($q) => $q->where('id', '!=', $current->id))->delete();

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        // Réponse volontairement identique, que le compte existe ou non.
        return response()->json(['message' => 'Si ce compte existe, un lien de réinitialisation a été envoyé.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ])->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Lien invalide ou expiré.'], 422);
        }

        return response()->json(['message' => 'Mot de passe réinitialisé.']);
    }
}
