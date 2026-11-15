@extends('layouts.app')

@section('title', 'Mon profil')
@section('nav', 'profile')

@section('content')
    <div class="page-head">
        <div>
            <h1>Mon profil</h1>
            <div class="sub">Mettez à jour vos coordonnées et les informations de votre compte.</div>
        </div>
        <a class="btn btn-outline-primary" href="/change-password"><i class="bi bi-key me-1"></i>Changer le mot de passe</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body text-center py-4">
                    <div class="avatar profile-avatar mx-auto mb-3" id="profileAvatar">··</div>
                    <h2 class="h5 mb-1" id="profileName">Chargement…</h2>
                    <div class="text-muted" id="profileJobTitle"></div>
                    <div class="small text-muted mt-2" id="profileOrganization"></div>
                    <div class="small mt-3"><span class="text-muted">Adresse e-mail</span><div id="profileEmail"></div></div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">Informations personnelles</div>
                <div class="card-body">
                    <form id="profileForm" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="firstName">Prénom</label><input class="form-control" id="firstName" name="first_name" required maxlength="100"></div>
                            <div class="col-md-6"><label class="form-label" for="lastName">Nom</label><input class="form-control" id="lastName" name="last_name" required maxlength="100"></div>
                            <div class="col-md-6"><label class="form-label" for="phone">Téléphone</label><input class="form-control" id="phone" name="phone" type="tel" maxlength="30"></div>
                            <div class="col-md-6"><label class="form-label" for="jobTitle">Fonction</label><input class="form-control" id="jobTitle" name="job_title" maxlength="150"></div>
                            <div class="col-md-6"><label class="form-label" for="locale">Langue</label><select class="form-select" id="locale" name="locale"><option value="fr">Français</option><option value="en">English</option></select></div>
                            <div class="col-md-6"><label class="form-label" for="timezone">Fuseau horaire</label><input class="form-control" id="timezone" name="timezone" placeholder="Europe/Paris"></div>
                            <div class="col-12"><label class="form-label" for="bio">À propos</label><textarea class="form-control" id="bio" name="bio" rows="4" maxlength="2000"></textarea></div>
                            <div class="col-12"><label class="form-label" for="avatar">Photo de profil</label><input class="form-control" id="avatar" name="avatar" type="file" accept="image/*"><div class="form-text">Image de 2 Mo maximum.</div></div>
                        </div>
                        <div class="d-flex justify-content-end mt-4"><button class="btn btn-primary" type="submit">Enregistrer le profil</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/profile.js') }}"></script>
@endpush