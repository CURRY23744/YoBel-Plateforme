@extends('layouts.app')

@section('title', request()->routeIs('decisions') ? 'Décisions générales' : 'Comptes rendus généraux')
@section('nav', request()->routeIs('decisions') ? 'decisions' : 'meeting-minutes')
@section('page-roles', 'admin,project_manager,member')

@section('content')
    @php($isDecisionRegister = request()->routeIs('decisions'))
    <div id="registerPage" data-register-type="{{ $isDecisionRegister ? 'decisions' : 'meeting-minutes' }}">
        <div class="page-head">
            <div>
                <h1>{{ $isDecisionRegister ? 'Décisions générales' : 'Comptes rendus généraux' }}</h1>
                <div class="sub">{{ $isDecisionRegister ? 'Registre des décisions prises à l’échelle de l’organisation.' : 'Comptes rendus des réunions générales de l’organisation.' }}</div>
            </div>
            <div class="d-flex gap-2">
                <input class="form-control" id="registerSearch" type="search" placeholder="Rechercher" aria-label="Rechercher dans le registre">
                @if($isDecisionRegister)
                    <select class="form-select" id="registerStatus" aria-label="Filtrer par statut">
                        <option value="">Tous les statuts</option>
                        <option value="proposed">Proposée</option>
                        <option value="validated">Validée</option>
                        <option value="rejected">Rejetée</option>
                        <option value="superseded">Remplacée</option>
                    </select>
                @endif
                <div id="registerActions"></div>
            </div>
        </div>
        <div class="alert alert-danger d-none" id="registerError" role="alert"></div>
        <div class="card">
            <div class="card-body" id="registerList"><div class="text-center text-muted py-5">Chargement…</div></div>
            <div class="card-body pt-0" id="registerPager"></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/registers.js') }}"></script>
@endpush
