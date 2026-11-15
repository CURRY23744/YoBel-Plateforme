<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discussion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function me(): User
    {
        return request()->user();
    }

    /**
     * Vérifie un droit sur un projet.
     * Sans droit de lecture, le projet "n'existe pas" (404), sinon 403 pour les autres droits.
     */
    protected function authorizeProject(?Project $project, string $ability = 'view'): void
    {
        abort_unless($project, 404, 'Projet introuvable.');

        $user = $this->me();

        abort_unless($user->canOnProject($project, 'view'), 404, 'Projet introuvable.');
        abort_unless(
            $ability === 'view' || $user->canOnProject($project, $ability),
            403,
            "Vous n'avez pas le droit « {$ability} » sur ce projet."
        );
    }

    /** L'espace général est fermé aux invités. */
    protected function authorizeGeneral(): void
    {
        abort_if($this->me()->isGuest(), 403, "L'espace général n'est pas accessible aux invités.");
    }

    protected function authorizeDiscussion(?Discussion $discussion, string $ability = 'view'): void
    {
        abort_unless($discussion, 404);

        if ($discussion->project_id) {
            abort_unless($discussion->project, 404);
            $this->authorizeProject($discussion->project, $ability);
        } else {
            $this->authorizeGeneral();
        }
    }

    protected function ensureSameOrganization(User $user): void
    {
        abort_unless($user->organization_id === $this->me()->organization_id, 404);
    }

    protected function perPage(Request $request): int
    {
        return max(1, min((int) $request->query('per_page', 20), 100));
    }

    /** Droit de gestion d'un élément rattaché à un projet (ou à l'espace général). */
    protected function canManageScope(?Project $project, ?int $ownerId = null): bool
    {
        $me = $this->me();

        if ($ownerId !== null && $ownerId === $me->id) {
            return true;
        }

        return $project ? $me->canOnProject($project, 'manage') : $me->isStaff();
    }
}
