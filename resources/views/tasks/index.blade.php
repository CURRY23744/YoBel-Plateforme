@extends('layouts.app')

@section('title', 'Mes tâches')
@section('nav', 'tasks')

@section('content')
    <div class="page-head">
        <div>
            <h1>Mes tâches</h1>
            <div class="sub">Suivez vos tâches et celles des projets auxquels vous avez accès.</div>
        </div>
        <div id="taskActions"></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="taskSearch">Rechercher</label>
                    <input class="form-control" id="taskSearch" type="search" placeholder="Titre de la tâche">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="taskStatus">Statut</label>
                    <select class="form-select" id="taskStatus">
                        <option value="">Tous les statuts</option>
                        <option value="todo">À faire</option>
                        <option value="in_progress">En cours</option>
                        <option value="review">En revue</option>
                        <option value="done">Terminée</option>
                        <option value="cancelled">Annulée</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="taskPriority">Priorité</label>
                    <select class="form-select" id="taskPriority">
                        <option value="">Toutes les priorités</option>
                        <option value="low">Faible</option>
                        <option value="medium">Moyenne</option>
                        <option value="high">Haute</option>
                        <option value="critical">Critique</option>
                    </select>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label" for="taskProject">Projet</label>
                    <select class="form-select" id="taskProject">
                        <option value="">Tous les projets visibles</option>
                    </select>
                </div>
                <div class="col-12 col-lg-1">
                    <div class="form-check">
                        <input class="form-check-input" id="taskOverdue" type="checkbox">
                        <label class="form-check-label" for="taskOverdue">En retard</label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="taskShowAll" type="checkbox">
                        <label class="form-check-label" for="taskShowAll">Afficher toutes les tâches visibles plutôt que seulement les miennes</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tâche</th>
                        <th>Projet</th>
                        <th>Assignée à</th>
                        <th>Échéance</th>
                        <th>Priorité</th>
                        <th>Statut</th>
                        <th><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody id="taskRows">
                    <tr><td colspan="7" class="text-center py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des tâches…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body pt-0" id="taskPager"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/tasks.js') }}"></script>
@endpush