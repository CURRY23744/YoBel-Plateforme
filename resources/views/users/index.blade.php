@extends('layouts.app')

@section('title', 'Utilisateurs')
@section('nav', 'users')
@section('page-roles', 'admin,project_manager')

@section('content')
    <div class="page-head">
        <div>
            <h1>Utilisateurs</h1>
            <div class="sub">Comptes de votre organisation et gestion des accès.</div>
        </div>
        <div id="userActions"></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="userSearch">Rechercher</label>
                    <input class="form-control" id="userSearch" type="search" placeholder="Nom ou adresse e-mail">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="userRoleFilter">Rôle</label>
                    <select class="form-select" id="userRoleFilter">
                        <option value="">Tous les rôles</option>
                        <option value="admin">Administrateur</option>
                        <option value="project_manager">Chef de projet</option>
                        <option value="member">Membre</option>
                        <option value="guest">Invité</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="userActiveFilter">État du compte</label>
                    <select class="form-select" id="userActiveFilter">
                        <option value="">Tous</option>
                        <option value="1">Actif</option>
                        <option value="0">Désactivé</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Utilisateur</th><th>Fonction</th><th>Rôle</th><th>État</th><th>Mot de passe</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody id="userRows">
                    <tr><td colspan="6" class="text-center py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des utilisateurs…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body pt-0" id="userPager"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/users.js') }}"></script>
@endpush