@extends('layouts.app')

@section('title', 'Journal d’activité')
@section('nav', 'activity')
@section('page-roles', 'admin')

@section('content')
    <div class="page-head">
        <div>
            <h1>Journal d’activité</h1>
            <div class="sub">Historique des actions enregistrées dans votre organisation.</div>
        </div>
        <button class="btn btn-outline-primary" type="button" id="exportActivity"><i class="bi bi-download me-1"></i>Exporter en CSV</button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-lg-2"><label class="form-label" for="activityFrom">Du</label><input class="form-control" id="activityFrom" type="date"></div>
                <div class="col-6 col-lg-2"><label class="form-label" for="activityTo">Au</label><input class="form-control" id="activityTo" type="date"></div>
                <div class="col-12 col-lg-3"><label class="form-label" for="activityUser">Utilisateur</label><select class="form-select" id="activityUser"><option value="">Tous les utilisateurs</option></select></div>
                <div class="col-12 col-lg-3"><label class="form-label" for="activityProject">Projet</label><select class="form-select" id="activityProject"><option value="">Tous les projets</option></select></div>
                <div class="col-12 col-lg-2"><label class="form-label" for="activityAction">Action</label><input class="form-control" id="activityAction" placeholder="ex. updated"></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Date</th><th>Utilisateur</th><th>Projet</th><th>Action</th><th>Élément</th><th>Description</th></tr></thead>
                <tbody id="activityRows"><tr><td colspan="6" class="text-center py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement du journal…</td></tr></tbody>
            </table>
        </div>
        <div class="card-body pt-0" id="activityPager"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/activity.js') }}"></script>
@endpush