@extends('layouts.app')

@section('title', 'Paramètres')
@section('nav', 'settings')

@section('content')
    <div class="page-head">
        <div>
            <h1>Paramètres de notifications</h1>
            <div class="sub">Choisissez les catégories de notifications que vous souhaitez mettre en sourdine.</div>
        </div>
        <a class="btn btn-light-soft" href="/profile"><i class="bi bi-person me-1"></i>Mon profil</a>
    </div>

    <div class="card">
        <div class="card-header">Notifications dans l’application</div>
        <div class="card-body">
            <p class="text-muted">Les notifications désactivées ne seront pas créées pour votre compte. Cette préférence ne modifie pas les notifications déjà reçues.</p>
            <form id="notificationSettingsForm">
                <div id="notificationPreferences">
                    <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des préférences…</div>
                </div>
                <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary" type="submit">Enregistrer les préférences</button></div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/settings.js') }}"></script>
@endpush