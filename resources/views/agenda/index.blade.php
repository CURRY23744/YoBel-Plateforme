@extends('layouts.app')

@section('title', 'Agenda')
@section('nav', 'agenda')

@section('content')
    <div class="page-head">
        <div>
            <h1>Agenda</h1>
            <div class="sub">Événements personnels, invitations et rendez-vous visibles dans vos projets.</div>
        </div>
        <div id="agendaActions"></div>
    </div>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-light-soft btn-icon" type="button" id="previousMonth" aria-label="Mois précédent"><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-light-soft" type="button" id="todayMonth">Aujourd’hui</button>
                <button class="btn btn-light-soft btn-icon" type="button" id="nextMonth" aria-label="Mois suivant"><i class="bi bi-chevron-right"></i></button>
                <h2 class="h5 mb-0 ms-2" id="monthTitle"></h2>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <select class="form-select form-select-sm" id="eventTypeFilter" aria-label="Filtrer par type">
                    <option value="">Tous les types</option>
                    <option value="meeting">Réunion</option>
                    <option value="delivery">Livraison</option>
                    <option value="client_meeting">Rendez-vous client</option>
                    <option value="reminder">Rappel</option>
                    <option value="deadline">Échéance</option>
                    <option value="other">Autre</option>
                </select>
                <div class="form-check form-switch">
                    <input class="form-check-input" id="myEventsOnly" type="checkbox">
                    <label class="form-check-label" for="myEventsOnly">Mes événements</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-2 p-md-3">
            <div class="calendar-weekdays" aria-hidden="true">
                <div>Lun</div><div>Mar</div><div>Mer</div><div>Jeu</div><div>Ven</div><div>Sam</div><div>Dim</div>
            </div>
            <div class="calendar-grid" id="agendaCalendar" aria-label="Calendrier mensuel"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span id="selectedDateTitle">Événements</span>
            <span class="small text-muted" id="selectedDateCount"></span>
        </div>
        <div class="card-body" id="selectedDateEvents">
            <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement de l’agenda…</div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/agenda.js') }}"></script>
@endpush