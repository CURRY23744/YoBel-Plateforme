@extends('layouts.guest')

@section('title', 'Changer le mot de passe')
@section('page', 'change')

@section('content')
    <h1 class="h4 text-center mb-1">Sécurisez votre compte</h1>
    <p class="text-muted text-center mb-4">Pour continuer, remplacez le mot de passe temporaire reçu par un mot de passe personnel.</p>

    <div id="formAlert" class="alert d-none" role="alert"></div>

    <form id="changeForm" novalidate>
        <div class="mb-3">
            <label class="form-label" for="current_password">Mot de passe actuel (temporaire)</label>
            <div class="input-group password-group">
                <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required autofocus>
                <button class="btn btn-outline-secondary" type="button" data-toggle-password="#current_password" aria-label="Afficher le mot de passe"><i class="bi bi-eye"></i></button>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">Nouveau mot de passe</label>
            <div class="input-group password-group">
                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" minlength="8" required>
                <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Afficher le mot de passe"><i class="bi bi-eye"></i></button>
            </div>
            <ul class="list-unstyled small mt-2 mb-0" id="pwRules"></ul>
        </div>

        <div class="mb-4">
            <label class="form-label" for="password_confirmation">Confirmer le nouveau mot de passe</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">Valider et continuer</button>
    </form>

    <p class="text-center mt-4 mb-0"><a href="#" id="logoutLink" class="small text-muted"><i class="bi bi-box-arrow-left me-1"></i>Se déconnecter</a></p>
@endsection
