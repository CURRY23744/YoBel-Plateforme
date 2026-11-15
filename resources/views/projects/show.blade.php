@extends('layouts.app')

@section('title', 'Projet')
@section('nav', 'projects')

@section('content')
    <div id="projectPage" data-project-id="{{ request()->route('id') }}">
        <div class="page-head">
            <div>
                <a class="small text-muted" href="/projects"><i class="bi bi-arrow-left me-1"></i>Retour aux projets</a>
                <h1 class="mt-2" id="projectTitle">Chargement du projet…</h1>
                <div class="sub" id="projectSubtitle"></div>
            </div>
            <div class="d-flex flex-wrap gap-2" id="projectActions"></div>
        </div>

        <div class="alert alert-danger d-none" id="projectError" role="alert"></div>

        <ul class="nav nav-tabs mb-3 flex-nowrap overflow-x-auto" id="projectTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" type="button" data-project-tab="overview">Aperçu</button></li>
            <li class="nav-item"><button class="nav-link" type="button" data-project-tab="team">Équipe</button></li>
            <li class="nav-item"><button class="nav-link" type="button" data-project-tab="milestones">Jalons</button></li>
            <li class="nav-item"><button class="nav-link" type="button" data-project-tab="tasks">Tâches</button></li>
            <li class="nav-item"><button class="nav-link" type="button" data-project-tab="files">Fichiers</button></li>
            <li class="nav-item"><button class="nav-link" type="button" data-project-tab="discussions">Discussions</button></li>
            <li class="nav-item"><button class="nav-link" type="button" data-project-tab="decisions">Décisions</button></li>
            <li class="nav-item"><button class="nav-link" type="button" data-project-tab="minutes">Comptes rendus</button></li>
            <li class="nav-item"><button class="nav-link" type="button" data-project-tab="evaluation">Évaluation</button></li>
            <li class="nav-item"><button class="nav-link" type="button" data-project-tab="activity">Historique</button></li>
        </ul>

        <section data-project-panel="overview">
            <div id="projectOverview" class="row g-3">
                <div class="col-12"><div class="card"><div class="card-body text-muted">Chargement des informations…</div></div></div>
            </div>
        </section>
        <section data-project-panel="team" class="d-none">
            <div class="card"><div class="card-body" id="projectTeam"><div class="text-muted">Chargement de l’équipe…</div></div></div>
        </section>
        <section data-project-panel="milestones" class="d-none">
            <div class="card"><div class="card-body" id="projectMilestones"><div class="text-muted">Chargement des jalons…</div></div></div>
        </section>
        <section data-project-panel="tasks" class="d-none">
            <div class="card"><div class="card-body" id="projectTasks"><div class="text-muted">Chargement des tâches…</div></div></div>
        </section>
        <section data-project-panel="files" class="d-none">
            <div class="card"><div class="card-body" id="projectFiles"><div class="text-muted">Chargement des fichiers…</div></div></div>
        </section>
        <section data-project-panel="discussions" class="d-none">
            <div class="card"><div class="card-body" id="projectDiscussions"><div class="text-muted">Chargement des discussions…</div></div></div>
        </section>
        <section data-project-panel="decisions" class="d-none">
            <div class="card"><div class="card-body" id="projectDecisions"><div class="text-muted">Chargement des décisions…</div></div></div>
        </section>
        <section data-project-panel="minutes" class="d-none">
            <div class="card"><div class="card-body" id="projectMinutes"><div class="text-muted">Chargement des comptes rendus…</div></div></div>
        </section>
        <section data-project-panel="evaluation" class="d-none">
            <div class="card"><div class="card-body" id="projectEvaluation"><div class="text-muted">Chargement de l’évaluation…</div></div></div>
        </section>
        <section data-project-panel="activity" class="d-none">
            <div class="card"><div class="card-body" id="projectActivity"><div class="text-muted">Chargement de l’historique…</div></div></div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/project-show.js') }}"></script>
@endpush
