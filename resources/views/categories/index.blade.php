@extends('layouts.app')

@section('title', 'Catégories et référentiels')
@section('nav', 'categories')
@section('page-roles', 'admin,project_manager,member')

@section('content')
    <div class="page-head">
        <div>
            <h1>Catégories et référentiels</h1>
            <div class="sub">Gérez les types de projets, les clients et les étiquettes de votre organisation.</div>
        </div>
        <div id="categoryActions"></div>
    </div>

    <ul class="nav nav-tabs nav-tabs-soft mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" type="button" data-category-tab="types">Types de projet</button></li>
        <li class="nav-item"><button class="nav-link" type="button" data-category-tab="clients">Clients</button></li>
        <li class="nav-item"><button class="nav-link" type="button" data-category-tab="tags">Étiquettes</button></li>
    </ul>

    <div class="card">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-end gap-3">
            <div class="flex-grow-1" id="categorySearchWrap">
                <label class="form-label" for="categorySearch">Rechercher</label>
                <input class="form-control" id="categorySearch" type="search" placeholder="Nom, entreprise ou adresse e-mail">
            </div>
            <div class="form-check form-switch" id="inactiveTypesWrap">
                <input class="form-check-input" id="includeInactiveTypes" type="checkbox">
                <label class="form-check-label" for="includeInactiveTypes">Inclure les types inactifs</label>
            </div>
        </div>
        <div id="categoryContent">
            <div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement…</div>
        </div>
        <div class="card-body" id="categoryPager"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/categories.js') }}"></script>
@endpush