@extends('layouts.app')

@section('title', 'Tableau de bord')
@section('nav', 'dashboard')

@push('styles')
<style>
    .dashboard-root {
        --dash-border: var(--bs-border-color, rgba(0, 0, 0, 0.08));
        --dash-surface: var(--bs-card-bg, #ffffff);
        max-width: 1600px;
        margin: 0 auto;
    }

    .dashboard-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--dash-border);
    }

    .dashboard-header h1 {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        margin: 0;
    }

    .dashboard-header .sub {
        font-size: 0.85rem;
        color: var(--bs-secondary-color, #6c757d);
        margin-top: 0.2rem;
    }

    #kpis .card {
        background-color: var(--dash-surface);
        border: 1px solid var(--dash-border);
        border-radius: 8px;
        box-shadow: none;
        transition: transform 0.2s ease, border-color 0.2s ease;
    }

    #kpis .card:hover {
        border-color: var(--bs-primary);
        transform: translateY(-2px);
    }

    #kpis .card-body {
        padding: 1rem 1.15rem;
    }

    .dashboard-card {
        background-color: var(--dash-surface);
        border: 1px solid var(--dash-border);
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
    }

    .dashboard-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.15rem 1.25rem 0.75rem 1.25rem;
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--bs-body-color);
        background: transparent;
        border-bottom: none;
    }

    .dashboard-card-header a {
        font-size: 0.775rem;
        font-weight: 500;
        color: var(--bs-primary);
        text-decoration: none;
        transition: opacity 0.15s ease;
    }

    .dashboard-card-header a:hover {
        opacity: 0.8;
        text-decoration: underline;
    }

    .dashboard-card-body {
        padding: 0.5rem 1.25rem 1.25rem 1.25rem;
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
    }

    .chart-box {
        position: relative;
        width: 100%;
        flex-grow: 1;
        min-height: 220px;
    }

    .admin-section-wrapper {
        margin-top: 2.5rem;
        padding-top: 1.5rem;
        border-top: 1px dashed var(--dash-border);
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
        <div class="col-lg-5">
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
        <div class="col-lg-7">
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
        <div class="col-lg-6">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Mes tâches en cours</span>
                    <a href="/tasks">Tout voir</a>
                </div>
                <div class="dashboard-card-body py-2" id="myTasks"></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Prochains événements</span>
                    <a href="/agenda">Agenda</a>
                </div>
                <div class="dashboard-card-body py-2" id="events"></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Jalons des 30 prochains jours</span>
                </div>
                <div class="dashboard-card-body py-2" id="milestones"></div>
            </div>
        </div>
        <div class="col-lg-6">
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
        <div class="col-lg-7">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <span>Activité récente</span>
                    <a href="/activity">Journal complet</a>
                </div>
                <div class="dashboard-card-body py-2" id="activity"></div>
            </div>
        </div>
        <div class="col-lg-5">
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