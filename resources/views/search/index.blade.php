@extends('layouts.app')

@section('title', 'Recherche')
@section('nav', 'search')

@section('content')
    <div class="page-head">
        <div>
            <h1>Recherche globale</h1>
            <div class="sub">Les résultats respectent vos accès aux projets, fichiers et discussions.</div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label" for="globalSearch">Rechercher dans l’espace de travail</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input class="form-control" id="globalSearch" type="search" minlength="2" maxlength="100" placeholder="Saisissez au moins deux caractères">
                <button class="btn btn-primary" type="button" id="runSearch">Rechercher</button>
            </div>
        </div>
    </div>

    <div id="searchResults">
        <div class="empty"><i class="bi bi-search"></i>Entrez un terme pour rechercher dans les éléments auxquels vous avez accès.</div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/search.js') }}"></script>
@endpush