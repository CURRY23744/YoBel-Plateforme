@extends('layouts.app')

@section('title', 'Planning')
@section('nav', 'planning')
@section('page-roles', 'admin,project_manager,member,guest')

@section('content')
<div class="planning-root">
    <div class="page-head">
        <div>
            <h1>Planning hebdomadaire</h1>
            <div class="sub">Définissez vos horaires habituels et signalez vos absences ou disponibilités exceptionnelles.</div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-light-soft" id="planningPrevious" aria-label="Semaine précédente"><i class="bi bi-chevron-left"></i></button>
            <button type="button" class="btn btn-outline-primary" id="planningToday">Cette semaine</button>
            <button type="button" class="btn btn-light-soft" id="planningNext" aria-label="Semaine suivante"><i class="bi bi-chevron-right"></i></button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 mb-1" id="planningWeekTitle">Chargement…</h2>
                <div class="small text-muted">Les disponibilités aident à l’attribution des tâches, sans empêcher une affectation.</div>
            </div>
            <button type="submit" class="btn btn-primary" form="planningForm"><i class="bi bi-check2 me-1"></i>Enregistrer mon planning</button>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form id="planningForm">
                <h2 class="h5 mb-3">Mes horaires habituels</h2>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Jour</th><th>Disponible</th><th>De</th><th>À</th></tr></thead>
                        <tbody id="planningWeeklyRows"><tr><td colspan="4" class="text-muted">Chargement des disponibilités…</td></tr></tbody>
                    </table>
                </div>

                <hr class="my-4">
                <h2 class="h5 mb-1">Exceptions pour cette semaine</h2>
                <p class="small text-muted">Modifiez ponctuellement un jour, par exemple pour signaler une absence. « Habituelle » reprend vos horaires récurrents.</p>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Date</th><th>Exception</th><th>De</th><th>À</th><th>Motif</th></tr></thead>
                        <tbody id="planningExceptionRows"><tr><td colspan="5" class="text-muted">Chargement des exceptions…</td></tr></tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <section id="planningTeamSection" class="d-none">
        <div class="page-head">
            <div><h2 class="h4">Disponibilités de l’équipe</h2><div class="sub">Vue en lecture seule pour vous aider à attribuer les tâches.</div></div>
        </div>
        <div class="row g-3" id="planningTeam"></div>
    </section>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/planning.js') }}"></script>
@endpush
