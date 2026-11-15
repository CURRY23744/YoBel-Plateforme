@extends('layouts.guest')

@section('title', 'Mot de passe oublié')
@section('page', 'forgot')

@section('content')
    <h1 class="h4 text-center mb-1">Mot de passe oublié</h1>
    <p class="text-muted text-center mb-4">Saisissez votre adresse e-mail : nous vous enverrons un lien pour choisir un nouveau mot de passe.</p>

    <div id="formAlert" class="alert d-none" role="alert"></div>

    <form id="forgotForm" novalidate>
        <div class="mb-4">
            <label class="form-label" for="email">Adresse e-mail</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email" placeholder="vous@entreprise.com" required autofocus>
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2">Envoyer le lien</button>
    </form>

    <p class="text-center mt-4 mb-0"><a href="/login" class="small"><i class="bi bi-arrow-left me-1"></i>Retour à la connexion</a></p>
@endsection
