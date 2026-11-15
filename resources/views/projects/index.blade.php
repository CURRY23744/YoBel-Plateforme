@extends('layouts.app')

@section('title', 'Projets')
@section('nav', 'projects')

@section('content')
    <div class="page-head">
        <div>
            <h1>Projets</h1>
            <div class="sub">Consultez les projets auxquels vous avez accès.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary d-none" id="createProject">
                <i class="bi bi-plus-lg me-1"></i>Nouveau projet
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3" id="projectStats" aria-live="polite">
        <div class="col-6 col-xl-3"><div class="card"><div class="card-body"><div class="placeholder-glow"><span class="placeholder col-8"></span><br><span class="placeholder col-4 mt-2"></span></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card"><div class="card-body"><div class="placeholder-glow"><span class="placeholder col-8"></span><br><span class="placeholder col-4 mt-2"></span></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card"><div class="card-body"><div class="placeholder-glow"><span class="placeholder col-8"></span><br><span class="placeholder col-4 mt-2"></span></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card"><div class="card-body"><div class="placeholder-glow"><span class="placeholder col-8"></span><br><span class="placeholder col-4 mt-2"></span></div></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form id="projectFilters" class="row g-2 align-items-end">
                <div class="col-12 col-lg-3">
                    <label class="form-label" for="projectSearch">Recherche</label>
                    <input class="form-control" id="projectSearch" name="q" type="search" minlength="2" placeholder="Nom, référence, description">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="projectType">Catégorie</label>
                    <select class="form-select" id="projectType" name="project_type_id">
                        <option value="">Toutes</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="projectStatus">Statut</label>
                    <select class="form-select" id="projectStatus" name="status">
                        <option value="">Tous</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="projectSort">Trier par</label>
                    <select class="form-select" id="projectSort" name="sort">
                        <option value="created_at">Date de création</option>
                        <option value="name">Nom</option>
                        <option value="reference">Référence</option>
                        <option value="status">Statut</option>
                        <option value="priority">Priorité</option>
                        <option value="planned_end_date">Échéance</option>
                    </select>
                </div>
                <div class="col-6 col-lg-1">
                    <label class="form-label" for="projectDirection">Ordre</label>
                    <select class="form-select" id="projectDirection" name="direction">
                        <option value="desc">↓</option>
                        <option value="asc">↑</option>
                    </select>
                </div>
                <div class="col-12 col-lg-1">
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="includeArchived" name="include_archived" type="checkbox" value="1">
                        <label class="form-check-label small" for="includeArchived">Archivés</label>
                    </div>
                </div>
                <div class="col-12 col-lg-1">
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="overdueOnly" name="overdue" type="checkbox" value="1">
                        <label class="form-check-label small" for="overdueOnly">En retard</label>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th scope="col">Projet</th>
                        <th scope="col">Catégorie</th>
                        <th scope="col">Responsable</th>
                        <th scope="col">Statut</th>
                        <th scope="col">Progression</th>
                        <th scope="col">Priorité</th>
                        <th scope="col">Échéance</th>
                        <th scope="col"><span class="visually-hidden">Ouvrir</span></th>
                    </tr>
                </thead>
                <tbody id="projectRows">
                    <tr><td colspan="8" class="text-center py-5 text-muted">Chargement des projets…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body border-top" id="projectPager"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/projects.js') }}"></script>
@endpush
