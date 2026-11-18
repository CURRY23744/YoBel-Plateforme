@extends('layouts.app')

@section('title', 'Projets')
@section('nav', 'projects')

@push('styles')
<style>
    /* ------------------------------------------------------------------
       Tokens : basés sur les variables Bootstrap (thème clair/sombre et
       couleur primaire de l'application respectés).
    ------------------------------------------------------------------ */
    .projects-root {
        --prj-border: var(--bs-border-color, rgba(0, 0, 0, 0.1));
        --prj-surface: var(--bs-card-bg, var(--bs-body-bg, #ffffff));
        --prj-muted: var(--bs-secondary-color, #6c757d);
        --prj-hover: var(--bs-tertiary-bg, rgba(0, 0, 0, 0.03));
        --prj-radius: 12px;
        --prj-radius-sm: 8px;
        max-width: 1600px;
        margin: 0 auto;
        font-variant-numeric: tabular-nums;
    }

    /* Écrans larges (QHD / 4K) : plus d'air, texte légèrement agrandi */
    @media (min-width: 1920px) {
        .projects-root {
            max-width: 1840px;
            font-size: 1.0625rem;
        }
    }

    @media (min-width: 2560px) {
        .projects-root {
            max-width: 2200px;
            font-size: 1.125rem;
        }
    }

    /* ------------------------------ En-tête ------------------------------ */
    .projects-root .page-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--prj-border);
    }

    .projects-root .page-head h1 {
        font-size: clamp(1.4rem, 1.1rem + 1vw, 1.85rem);
        font-weight: 700;
        letter-spacing: -0.025em;
        line-height: 1.15;
        margin: 0;
    }

    .projects-root .page-head .sub {
        max-width: 62ch;
        font-size: 0.9rem;
        line-height: 1.5;
        color: var(--prj-muted);
        margin-top: 0.4rem;
    }

    .projects-root #createProject {
        font-weight: 600;
        padding: 0.55rem 1.1rem;
        border-radius: var(--prj-radius-sm);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
        transition: box-shadow 0.15s ease, filter 0.15s ease;
    }

    .projects-root #createProject:hover {
        box-shadow: 0 3px 10px color-mix(in srgb, var(--bs-primary) 30%, transparent);
    }

    /* ------------------------- Statistiques (KPI) ----------------------- */
    .projects-root #projectStats .card {
        position: relative;
        overflow: hidden;
        height: 100%;
        background-color: var(--prj-surface);
        border: 1px solid var(--prj-border);
        border-radius: var(--prj-radius-sm);
        box-shadow: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    /* Filet d'accent à gauche : il ancre les chiffres */
    .projects-root #projectStats .card::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 3px;
        background: var(--bs-primary);
        opacity: 0.85;
    }

    .projects-root #projectStats .card:hover {
        border-color: color-mix(in srgb, var(--bs-primary) 45%, var(--prj-border));
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
    }

    .projects-root #projectStats .card-body {
        padding: 1.1rem 1.25rem 1.1rem 1.4rem;
    }

    .projects-root #projectStats .card-body :is(h1, h2, h3, h4, h5, .h1, .h2, .h3, .h4, .h5, .fs-1, .fs-2, .fs-3, .kpi-value) {
        font-weight: 700;
        letter-spacing: -0.03em;
        line-height: 1.1;
    }

    .projects-root #projectStats .card-body :is(small, .small, .text-muted, .text-secondary, .kpi-label) {
        font-size: 0.8rem;
        letter-spacing: 0.01em;
    }

    .projects-root #projectStats .placeholder {
        background-color: var(--prj-border);
        border-radius: 4px;
    }

    /* ------------------------------ Filtres ----------------------------- */
    .projects-root > .card {
        background-color: var(--prj-surface);
        border: 1px solid var(--prj-border);
        border-radius: var(--prj-radius);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        overflow: hidden;
    }

    .projects-root #projectFilters {
        row-gap: 0.85rem !important;
    }

    .projects-root .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--prj-muted);
        margin-bottom: 0.35rem;
    }

    .projects-root :is(.form-control, .form-select) {
        min-height: 2.6rem;
        border-radius: var(--prj-radius-sm);
        border-color: var(--prj-border);
        background-color: var(--prj-surface);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .projects-root :is(.form-control, .form-select):focus,
    .projects-root .form-check-input:focus {
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--bs-primary) 18%, transparent);
    }

    .projects-root .form-check {
        min-height: 2.6rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding-left: 0;
        margin-bottom: 0 !important;
    }

    .projects-root .form-check .form-check-input {
        float: none;
        margin: 0;
        cursor: pointer;
    }

    .projects-root .form-check-label {
        cursor: pointer;
        font-size: 0.85rem !important;
        white-space: nowrap;
    }

    /* --------------------------------- Tableau -------------------------- */
    .projects-root .table-responsive {
        scrollbar-width: thin;
        scrollbar-color: var(--prj-border) transparent;
    }

    .projects-root .table {
        --bs-table-bg: transparent;
        margin-bottom: 0;
        vertical-align: middle;
    }

    .projects-root .table > thead > tr > th {
        padding: 0.8rem 1.25rem;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--prj-muted);
        background: color-mix(in srgb, var(--prj-hover) 55%, transparent);
        border-bottom: 1px solid var(--prj-border);
        white-space: nowrap;
    }

    .projects-root .table > tbody > tr > td {
        padding: 0.9rem 1.25rem;
        border-bottom: 1px solid var(--prj-border);
    }

    .projects-root .table > tbody > tr:last-child > td {
        border-bottom: 0;
    }

    .projects-root .table > tbody > tr {
        transition: background-color 0.15s ease;
    }

    .projects-root .table-hover > tbody > tr:hover > * {
        --bs-table-hover-bg: var(--prj-hover);
        background-color: var(--prj-hover);
    }

    /* Nom du projet : lien lisible, sans soulignement permanent */
    .projects-root .table > tbody > tr > td:first-child a {
        font-weight: 600;
        color: var(--bs-body-color);
        text-decoration: none;
    }

    .projects-root .table > tbody > tr > td:first-child a:hover {
        color: var(--bs-primary);
    }

    /* Barres de progression : fines et arrondies */
    .projects-root .progress {
        height: 6px;
        min-width: 90px;
        border-radius: 999px;
        background-color: var(--prj-hover);
        box-shadow: inset 0 0 0 1px var(--prj-border);
    }

    .projects-root .progress-bar {
        border-radius: 999px;
    }

    .projects-root .badge {
        font-weight: 600;
        letter-spacing: 0.01em;
        border-radius: 999px;
        padding: 0.35em 0.7em;
    }

    .projects-root .table .btn {
        --bs-btn-padding-y: 0.25rem;
        --bs-btn-padding-x: 0.6rem;
        --bs-btn-font-size: 0.8rem;
        border-radius: 6px;
    }

    /* ------------------------------ Pagination -------------------------- */
    .projects-root #projectPager {
        padding: 0.9rem 1.25rem;
        border-color: var(--prj-border) !important;
    }

    .projects-root #projectPager:empty {
        display: none;
    }

    .projects-root #projectPager .pagination {
        margin: 0;
        gap: 0.2rem;
    }

    .projects-root #projectPager .page-link {
        min-width: 2.15rem;
        text-align: center;
        font-size: 0.85rem;
        color: var(--bs-body-color);
        border: 1px solid transparent;
        border-radius: 6px !important;
        background: transparent;
    }

    .projects-root #projectPager .page-link:hover {
        background-color: var(--prj-hover);
    }

    .projects-root #projectPager .page-item.active .page-link {
        color: #fff;
        background-color: var(--bs-primary);
        border-color: var(--bs-primary);
    }

    .projects-root #projectPager .page-item.disabled .page-link {
        opacity: 0.45;
    }

    /* ---------------------------- Accessibilité ------------------------- */
    .projects-root :is(a, button):focus-visible {
        outline: 2px solid var(--bs-primary);
        outline-offset: 2px;
    }

    /* -------------------------------- Mobile ---------------------------- */
    @media (max-width: 575.98px) {
        .projects-root .page-head {
            margin-bottom: 1.1rem;
            padding-bottom: 1rem;
        }

        .projects-root #projectStats .card-body {
            padding: 0.9rem 1rem 0.9rem 1.2rem;
        }

        .projects-root .table > thead > tr > th,
        .projects-root .table > tbody > tr > td {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .projects-root #projectPager {
            padding: 0.75rem 1rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .projects-root * {
            transition: none !important;
        }
    }
</style>
@endpush

@section('content')
<div class="projects-root">
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
                    <label class="form-label" for="projectDateFrom">Créé du</label>
                    <input class="form-control" id="projectDateFrom" name="from" type="date">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="projectDateTo">Créé au</label>
                    <input class="form-control" id="projectDateTo" name="to" type="date">
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
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/projects.js') }}"></script>
@endpush