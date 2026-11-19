@extends('layouts.app')

@section('title', 'Évaluation des projets')
@section('nav', 'evaluation')
@section('page-roles', 'admin,project_manager,guest')

@section('content')
    <div class="page-head">
        <div>
            <h1>Évaluation des projets</h1>
            <div class="sub">Classement selon les évaluations pondérées et gestion des critères.</div>
        </div>
        <div id="criterionActions"></div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>Classement des projets</span>
            <select class="form-select form-select-sm" id="rankingStatus" aria-label="Filtrer par statut">
                <option value="">Tous les statuts</option>
                <option value="idea">Idée</option>
                <option value="feasibility">Étude de faisabilité</option>
                <option value="validated">Validé</option>
                <option value="in_progress">En cours</option>
                <option value="on_hold">En pause</option>
                <option value="completed">Terminé</option>
                <option value="abandoned">Abandonné</option>
            </select>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Rang</th><th>Projet</th><th>Catégorie</th><th>Statut</th><th>Priorité</th><th>Score</th></tr></thead>
                <tbody id="rankingRows"><tr><td colspan="6" class="text-center py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement du classement…</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Critères d’évaluation</span>
            <div class="form-check form-switch" id="inactiveCriteriaWrap">
                <input class="form-check-input" id="includeInactiveCriteria" type="checkbox">
                <label class="form-check-label" for="includeInactiveCriteria">Inclure les critères inactifs</label>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Critère</th><th>Description</th><th>Pondération</th><th>État</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody id="criteriaRows"><tr><td colspan="5" class="text-center py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des critères…</td></tr></tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/evaluation.js') }}"></script>
@endpush