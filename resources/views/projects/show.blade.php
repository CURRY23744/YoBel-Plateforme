@extends('layouts.app')

@section('title', 'Projet')
@section('nav', 'projects')

@push('styles')
<style>
    .project-chat-list {
        display: flex;
        flex-direction: column;
        gap: .85rem;
        max-height: 55vh;
        min-height: 12rem;
        overflow-y: auto;
        padding: 1rem;
        border-radius: .9rem;
        background: #f1f4f9;
        overscroll-behavior: contain;
        scroll-behavior: smooth;
    }

    .project-chat-row {
        display: flex;
        align-items: flex-end;
        gap: .55rem;
        max-width: 88%;
    }

    .project-chat-row.tone-0 .project-chat-bubble { background: #f0efff; border-color: #dedbf7; }
    .project-chat-row.tone-1 .project-chat-bubble { background: #e8f6f1; border-color: #d3ebe2; }
    .project-chat-row.tone-2 .project-chat-bubble { background: #fff3e7; border-color: #f3e2d0; }
    .project-chat-row.tone-3 .project-chat-bubble { background: #f9edf4; border-color: #eed9e5; }
    .project-chat-row.tone-4 .project-chat-bubble { background: #eaf3fb; border-color: #d7e5f2; }

    .project-chat-row.is-own {
        align-self: flex-end;
        flex-direction: row-reverse;
    }

    .project-chat-avatar {
        display: grid;
        flex: 0 0 2rem;
        width: 2rem;
        height: 2rem;
        place-items: center;
        border-radius: 50%;
        color: #fff;
        font-size: .75rem;
        font-weight: 700;
    }

    .project-chat-avatar.tone-0 { background: #6875c5; }
    .project-chat-avatar.tone-1 { background: #258a83; }
    .project-chat-avatar.tone-2 { background: #c1763b; }
    .project-chat-avatar.tone-3 { background: #a14d79; }
    .project-chat-avatar.tone-4 { background: #52749d; }

    .project-chat-bubble {
        min-width: 5rem;
        padding: .65rem .85rem;
        border: 1px solid #e1e6ef;
        border-radius: 1rem 1rem 1rem .25rem;
        background: #fff;
        box-shadow: 0 1px 2px rgb(16 24 40 / 5%);
        overflow-wrap: anywhere;
    }

    .project-chat-row.is-own .project-chat-bubble {
        border-color: #c8d8f0;
        border-radius: 1rem 1rem .25rem 1rem;
        background: #e3edfc;
    }

    .project-chat-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: .2rem .55rem;
        margin-bottom: .28rem;
        font-size: .76rem;
    }

    .project-chat-meta time,
    .project-chat-edited {
        color: #778196;
        font-size: .68rem;
    }

    .project-chat-body > :last-child { margin-bottom: 0; }

    @media (max-width: 575.98px) {
        .project-chat-list { padding: .7rem; }
        .project-chat-row { max-width: 94%; }
    }
</style>
@endpush

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
