
@extends('layouts.guest')

@section('title', 'Connexion')
@section('page', 'login')

@section('content')
    <div class="login-eyebrow">
        <i class="bi bi-shield-lock" aria-hidden="true"></i>
        Espace de travail sécurisé
    </div>

    <h1 class="h4 mb-2">Bienvenue dans votre espace</h1>

    <p class="text-muted login-description">
        Connectez-vous à votre espace de travail.
    </p>

    <div id="formAlert" class="alert d-none" role="alert"></div>

    <form id="loginForm" novalidate>
        {{-- Adresse e-mail --}}
        <div class="mb-3">
            <label class="form-label" for="email">
                Adresse e-mail
            </label>

            <div class="input-group login-input">
                <span class="input-group-text" aria-hidden="true">
                    <i class="bi bi-envelope"></i>
                </span>

                <input
                    type="email"
                    class="form-control"
                    id="email"
                    name="email"
                    placeholder="vous@entreprise.com"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>
        </div>

        {{-- Mot de passe --}}
        <div class="mb-2">
            <label class="form-label" for="password">
                Mot de passe
            </label>

            <div class="input-group password-group login-input">
                <span class="input-group-text" aria-hidden="true">
                    <i class="bi bi-lock"></i>
                </span>

                <input
                    type="password"
                    class="form-control"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

                <button
                    class="btn password-toggle"
                    type="button"
                    data-toggle-password="#password"="#password"
                    aria-label="Afficher le mot de passe"
                    aria-pressed="false"
                >
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        {{-- Mot de passe oublié --}}
        <div class="text-end mb-4">
            <a href="/forgot-password" class="small">
                Mot de passe oublié ?
            </a>
        </div>

        {{-- Connexion --}}
        <button
            type="submit"
            class="btn btn-primary w-100 py-2"
        >
            Se connecter
            <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
        </button>
    </form>

    <div class="login-note">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <p class="mb-0">Accès réservé aux membres de l’entreprise. Votre compte est créé par un administrateur.</p>
    </div>
@endsection
