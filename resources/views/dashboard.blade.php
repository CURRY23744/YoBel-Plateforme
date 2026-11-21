@extends('layouts.app')

@section('title', 'Tableau de bord')
@section('nav', 'dashboard')

@push('styles')
<style>
    /* ------------------------------------------------------------------
       Tokens : tout repose sur les variables Bootstrap, le thème clair/sombre
       et la couleur primaire de l'application restent donc respectés.
    ------------------------------------------------------------------ */
    .dashboard-root {
        --dash-border: var(--bs-border-color, rgba(0, 0, 0, 0.08));
        --dash-surface: var(--bs-card-bg, var(--bs-body-bg, #ffffff));
        --dash-muted: var(--bs-secondary-color, #6c757d);
        --dash-hover: var(--bs-tertiary-bg, rgba(0, 0, 0, 0.03));
        --dash-radius: 12px;
        --dash-radius-sm: 8px;
        --dash-gap: 1.25rem;
        width: 100%;
        max-width: 1600px;
        margin: 0 auto;
        font-variant-numeric: tabular-nums;
        /* Empêche tout débordement horizontal sur mobile */
        overflow-x: clip;
        /* Encoches / barres système (iPhone, Android gestuel) */
        padding-bottom: env(safe-area-inset-bottom, 0px);
    }

    /* Tous les enfants flex/grid peuvent rétrécir : évite les débordements de texte */
    .dashboard-root :is(.row > *, .dashboard-card, .dashboard-card-body, .card, .card-body) {
        min-width: 0;
    }

    .dashboard-root img,
    .dashboard-root canvas,
    .dashboard-root svg {
        max-width: 100%;
    }

    /* ------------------------------ En-tête ------------------------------ */
    .dashboard-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.75rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--dash-border);
    }

    .dashboard-header > div:first-child {
        min-width: 0;
        flex: 1 1 14rem;
    }

    .dashboard-header h1 {
        font-size: clamp(1.3rem, 1.05rem + 1.2vw, 1.85rem);
        font-weight: 700;
        letter-spacing: -0.025em;
        line-height: 1.15;
        margin: 0;
        overflow-wrap: anywhere;
    }

    .dashboard-header .sub {
        font-size: 0.875rem;
        color: var(--dash-muted);
        margin-top: 0.35rem;
    }

    .dashboard-header .sub::first-letter {
        text-transform: uppercase;
    }

    #headActions {
        flex-wrap: wrap;
    }

    /* ------------------------ Indicateurs (KPI) ------------------------- */
    #kpis .card {
        position: relative;
        overflow: hidden;
        background-color: var(--dash-surface);
        border: 1px solid var(--dash-border);
        border-radius: var(--dash-radius-sm);
        box-shadow: none;
        height: 100%;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    /* Filet d'accent : seul élément coloré de la rangée, il ancre les chiffres */
    #kpis .card::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 3px;
        background: var(--bs-primary);
        opacity: 0.85;
    }

    #kpis .card-body {
        padding: 1.1rem 1.25rem 1.1rem 1.4rem;
        overflow-wrap: anywhere;
    }

    /* Le chiffre prend le poids visuel, le libellé reste discret */
    #kpis .card-body :is(h1, h2, h3, h4, h5, .h1, .h2, .h3, .h4, .h5, .fs-1, .fs-2, .fs-3, .kpi-value) {
        font-weight: 700;
        letter-spacing: -0.03em;
        line-height: 1.1;
        /* Le chiffre s'adapte à la largeur de l'écran */
        font-size: clamp(1.35rem, 1rem + 1.6vw, 2rem);
    }

    #kpis .card-body :is(small, .small, .text-muted, .text-secondary, .kpi-label) {
        font-size: 0.8rem;
        letter-spacing: 0.01em;
    }

    #kpis .placeholder {
        background-color: var(--dash-border);
    }

    /* ----------------------------- Cartes ------------------------------- */
    .dashboard-card {
        background-color: var(--dash-surface);
        border: 1px solid var(--dash-border);
        border-radius: var(--dash-radius);
        box-shadow: none;
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
    }

    .dashboard-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.25rem 0.75rem;
        padding: 1rem 1.25rem;
        font-weight: 600;
        font-size: 0.95rem;
        letter-spacing: -0.005em;
        color: var(--bs-body-color);
        background: transparent;
        border-bottom: 1px solid var(--dash-border);
    }

    .dashboard-card-header > span {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .dashboard-card-header a {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        /* Zone tactile confortable (≈ 44px) */
        min-height: 36px;
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--bs-primary);
        text-decoration: none;
        padding: 0.2rem 0.55rem;
        margin: -0.4rem -0.55rem -0.4rem 0;
        border-radius: 6px;
        transition: background-color 0.15s ease;
    }

    .dashboard-card-body {
        padding: 0.75rem 1.25rem 1.25rem;
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
    }

    /* ---------------------------- Graphiques ---------------------------- */
    .chart-box {
        position: relative;
        width: 100%;
        flex-grow: 1;
        min-height: 240px;
    }

    .chart-box canvas {
        display: block;
        width: 100% !important;
    }

    /* ------------------------------ Listes ------------------------------ */
    /* Lignes séparées par un simple filet, sans cadre ni fond par défaut */
    :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) {
        padding-top: 0.25rem;
        padding-bottom: 0.5rem;
        max-height: 360px;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: var(--dash-border) transparent;
    }

    :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) > :is(div, a, li, article) {
        padding-top: 0.7rem;
        padding-bottom: 0.7rem;
        margin-left: -0.5rem;
        margin-right: -0.5rem;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        border-radius: 6px;
        transition: background-color 0.15s ease;
        /* Le texte long passe à la ligne au lieu de déborder */
        overflow-wrap: anywhere;
        min-width: 0;
    }

    :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) > :is(div, a, li, article):not(:last-child) {
        border-bottom: 1px solid var(--dash-border);
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
    }

    /* Éléments flex générés par le JS : le contenu peut rétrécir et passer à la ligne */
    :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) :is(.d-flex, .flex-row) {
        min-width: 0;
    }

    :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) .d-flex > :is(div, span, a):not(.badge):not(.avatar):not(.flex-shrink-0) {
        min-width: 0;
    }

    :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) .badge {
        flex-shrink: 0;
        white-space: nowrap;
    }

    :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) .list-group-item {
        background: transparent;
        border-color: var(--dash-border);
    }

    :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) :is(.text-muted, small, .small) {
        color: var(--dash-muted) !important;
    }

    /* Tableaux éventuels : défilement horizontal interne, jamais sur la page */
    :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) table {
        display: block;
        max-width: 100%;
        overflow-x: auto;
    }

    /* États vides : lisibles et centrés */
    :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) > :is(.text-muted, .empty, .empty-state, p):only-child {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 120px;
        text-align: center;
        font-size: 0.875rem;
        color: var(--dash-muted);
    }

    /* Badges : forme homogène sur toute la page */
    .dashboard-root .badge {
        font-weight: 600;
        letter-spacing: 0.01em;
        border-radius: 999px;
        padding: 0.35em 0.7em;
    }

    /* ----------------------- Section administrateur --------------------- */
    .admin-section-wrapper {
        position: relative;
        margin-top: 2.75rem;
        padding-top: 2.25rem;
        border-top: 1px solid var(--dash-border);
    }

    .admin-section-wrapper::before {
        content: "Administration";
        position: absolute;
        top: -0.7rem;
        left: 0.75rem;
        padding: 0 0.6rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--dash-muted);
        background: var(--bs-body-bg, #fff);
    }

    /* ---------------------------- Accessibilité ------------------------- */
    .dashboard-root :is(a, button):focus-visible {
        outline: 2px solid var(--bs-primary);
        outline-offset: 2px;
        border-radius: 6px;
    }

    /* Les effets de survol ne s'appliquent qu'aux appareils qui en ont un
       (évite les états "collés" après un tap sur mobile) */
    @media (hover: hover) {
        #kpis .card:hover {
            border-color: color-mix(in srgb, var(--bs-primary) 45%, var(--dash-border));
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
        }

        .dashboard-card-header a:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent);
        }

        :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) > :is(a, li, article):hover {
            background-color: var(--dash-hover);
        }
    }

    /* Retour tactile à la place du survol */
    @media (hover: none) {
        .dashboard-card-header a:active {
            background-color: color-mix(in srgb, var(--bs-primary) 12%, transparent);
        }

        :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) > :is(a, li, article):active {
            background-color: var(--dash-hover);
        }
    }

    /* ------------------------------ Tablette ---------------------------- */
    @media (max-width: 991.98px) {
        .chart-box {
            min-height: 260px;
        }

        .admin-section-wrapper {
            margin-top: 2.25rem;
        }
    }

    /* ------------------------------ Mobile ------------------------------ */
    @media (max-width: 575.98px) {
        .dashboard-header {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
        }

        /* Boutons d'action : pleine largeur, faciles à toucher */
        /* En colonne, le flex-basis de 14rem devenait une hauteur : on l'annule */
        .dashboard-header > div:first-child {
            flex: 0 0 auto;
        }

        #headActions {
            width: 100%;
        }

        #headActions > :is(a, button, .btn) {
            flex: 1 1 auto;
            min-height: 44px;
            justify-content: center;
            text-align: center;
        }

        #kpis {
            --bs-gutter-x: 0.75rem;
            --bs-gutter-y: 0.75rem;
        }

        #kpis .card-body {
            padding: 0.85rem 0.85rem 0.85rem 1.05rem;
        }

        .dashboard-card-header {
            padding: 0.85rem 1rem;
            font-size: 0.92rem;
        }

        .dashboard-card-body {
            padding: 0.5rem 1rem 1rem;
        }

        .chart-box {
            min-height: 220px;
        }

        :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) {
            max-height: 320px;
        }

        :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) > :is(div, a, li, article) {
            padding-top: 0.8rem;
            padding-bottom: 0.8rem;
        }

        .admin-section-wrapper {
            margin-top: 2rem;
            padding-top: 1.75rem;
        }

        .admin-section-wrapper::before {
            left: 0.25rem;
        }
    }

    /* Très petits écrans (iPhone SE, petits Android ≤ 360px) :
       les indicateurs passent en une colonne pour rester lisibles */
    @media (max-width: 399.98px) {
        /* Grille 2x2 : pictogramme au-dessus du chiffre, bien plus compact que 4 cartes empilées */
        #kpis .card .kpi {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .dashboard-card-header a {
            margin-right: 0;
        }
    }

    /* Paysage sur téléphone : on limite la hauteur des zones scrollables */
    @media (max-height: 480px) and (orientation: landscape) {
        :is(#myTasks, #events, #milestones, #overdue, #activity, #latestUsers) {
            max-height: 240px;
        }

        .chart-box {
            min-height: 180px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .dashboard-root * {
            transition: none !important;
        }
    }
</style>
@endpush

@section('content')
<div class="dashboard-root">
    <div class="page-head dashboard-header">
        <div>
            <h1 id="hello">Tableau de bord</h1>
            <div class="sub" id="today"></div>
        </div>
        <div id="headActions" class="d-flex gap-2"></div>
    </div>

    {{-- Indicateurs --}}
    <div class="row g-3 mb-4" id="kpis">
        @for ($i = 0; $i < 4; $i++)
            <div class="col-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="placeholder-glow">
                            <span class="placeholder col-8 rounded-1"></span><br>
                            <span class="placeholder col-4 mt-2 rounded-1"></span>
                        </div>
                    </div>
                </div>
            </div>
        @endfor
    </div>

    {{-- Graphiques --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-5">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Projets par statut</span>
                </div>
                <div class="dashboard-card-body">
                    <div class="chart-box" id="statusBox">
                        <canvas id="chartStatus"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Projets par catégorie</span>
                    <a href="/categories">Toutes les catégories</a>
                </div>
                <div class="dashboard-card-body">
                    <div class="chart-box" id="typeBox">
                        <canvas id="chartType"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Listes --}}
    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Mes tâches en cours</span>
                    <a href="/tasks">Tout voir</a>
                </div>
                <div class="dashboard-card-body py-2" id="myTasks"></div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Prochains événements</span>
                    <a href="/agenda">Agenda</a>
                </div>
                <div class="dashboard-card-body py-2" id="events"></div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Jalons des 30 prochains jours</span>
                </div>
                <div class="dashboard-card-body py-2" id="milestones"></div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Projets en retard</span>
                    <a href="/projects?overdue=1">Tout voir</a>
                </div>
                <div class="dashboard-card-body py-2" id="overdue"></div>
            </div>
        </div>
    </div>

    {{-- Réservé à l'administrateur --}}
    <div class="row g-3 d-none admin-section-wrapper" id="adminRow">
        <div class="col-12 col-lg-7">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Activité récente</span>
                    <a href="/activity">Journal complet</a>
                </div>
                <div class="dashboard-card-body py-2" id="activity"></div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Derniers comptes créés</span>
                    <a href="/users">Utilisateurs</a>
                </div>
                <div class="dashboard-card-body py-2" id="latestUsers"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/pages/dashboard.js') }}"></script>
@endpush