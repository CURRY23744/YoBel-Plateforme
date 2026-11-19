<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Espace de travail') · YOBEL Workspace</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="has-watermark" data-nav="@yield('nav')" data-page-roles="@yield('page-roles')">

<div class="app-shell">
    {{-- ================= Menu latéral ================= --}}
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
        <a href="/dashboard" class="brand"><img src="{{ asset('images/logo-white.png') }}" alt="YOBEL – Tech &amp; Engineering Studio"></a>
        <nav class="nav flex-column">
            <a class="nav-link" data-nav="dashboard" href="/dashboard"><i class="bi bi-speedometer2"></i> Tableau de bord</a>

            <div class="nav-section">Projets</div>
            <a class="nav-link" data-nav="categories" data-roles="admin,project_manager,member" href="/categories"><i class="bi bi-grid-1x2"></i> Catégories</a>
            <a class="nav-link" data-nav="projects" href="/projects"><i class="bi bi-kanban"></i> Tous les projets</a>
            <a class="nav-link" data-nav="tasks" href="/tasks"><i class="bi bi-check2-square"></i> Mes tâches</a>
            <a class="nav-link" data-nav="agenda" href="/agenda"><i class="bi bi-calendar3"></i> Agenda</a>
            <a class="nav-link" data-nav="planning" data-roles="admin,project_manager,member,guest" href="/planning"><i class="bi bi-calendar2-week"></i> Planning</a>

            <div class="nav-section" data-roles="admin,project_manager,member,guest">Assistant</div>
            <a class="nav-link" data-nav="assistant" data-roles="admin,project_manager,member,guest" href="/assistant"><i class="bi bi-stars"></i> Assistant Yobel</a>

            <div class="nav-section">Compte</div>
            <a class="nav-link" data-nav="profile" href="/profile"><i class="bi bi-person"></i> Mon profil</a>
            <a class="nav-link" data-nav="notifications" href="/notifications"><i class="bi bi-bell"></i> Notifications</a>
            <a class="nav-link" data-nav="settings" href="/settings"><i class="bi bi-bell"></i> Préférences</a>

            <div class="nav-section" data-roles="admin,project_manager,member,guest">Collaboration</div>
            <a class="nav-link" data-nav="space" data-roles="admin,project_manager,member" href="/space"><i class="bi bi-chat-square-text"></i> Espace général</a>
            <a class="nav-link" data-nav="decisions" data-roles="admin,project_manager,member,guest" href="/decisions"><i class="bi bi-journal-check"></i> Décisions</a>
            <a class="nav-link" data-nav="meeting-minutes" data-roles="admin,project_manager,member,guest" href="/meeting-minutes"><i class="bi bi-journal-text"></i> Comptes rendus</a>
            <a class="nav-link" data-nav="evaluation" data-roles="admin,project_manager,guest" href="/evaluation"><i class="bi bi-bar-chart-steps"></i> Évaluation</a>

            <div class="nav-section" data-roles="admin,project_manager">Administration</div>
            <a class="nav-link" data-nav="users" data-roles="admin,project_manager" href="/users"><i class="bi bi-people"></i> Utilisateurs</a>
            <a class="nav-link" data-nav="activity" data-roles="admin" href="/activity"><i class="bi bi-clock-history"></i> Journal d'activité</a>
        </nav>
        <div class="sidebar-footer">YOBEL Workspace · Tech &amp; Engineering Studio</div>
    </aside>

    <div class="main-wrap">
        {{-- ================= Barre supérieure ================= --}}
        <header class="topbar">
            <button class="btn btn-light-soft btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu"><i class="bi bi-list fs-5"></i></button>

            <form class="search position-relative" action="/search" method="get" role="search">
                <i class="bi bi-search"></i>
                <input class="form-control" type="search" name="q" minlength="2" placeholder="Rechercher un projet, un fichier, une tâche…" aria-label="Recherche">
            </form>

            <div class="ms-auto d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-light-soft btn-icon position-relative" id="notifBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                        <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width: 360px; max-width: 92vw;">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <strong>Notifications</strong>
                            <button class="btn btn-link btn-sm p-0" id="notifReadAll">Tout marquer comme lu</button>
                        </div>
                        <div id="notifList" style="max-height: 360px; overflow-y: auto;"><div class="empty py-4">Chargement…</div></div>
                        <a href="/notifications" class="d-block text-center py-2 border-top small">Voir toutes les notifications</a>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn p-0 d-flex align-items-center gap-2 border-0" data-bs-toggle="dropdown" id="userMenuBtn">
                        <span class="avatar" id="userAvatar">··</span>
                        <span class="text-start d-none d-md-block lh-sm">
                            <span class="d-block fw-semibold" id="userName">…</span>
                            <small class="text-muted" id="userRole"></small>
                        </span>
                        <i class="bi bi-chevron-down small text-muted d-none d-md-block"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/profile"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item text-danger" id="logoutBtn"><i class="bi bi-box-arrow-right me-2"></i>Se déconnecter</button></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="content" id="app">
            @yield('content')
        </main>
    </div>
</div>

{{-- Boîte de confirmation partagée --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle fs-1 text-warning"></i>
                <p class="mt-2 mb-4" id="confirmMsg">Confirmer cette action ?</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary" id="confirmOk">Confirmer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toasts"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
