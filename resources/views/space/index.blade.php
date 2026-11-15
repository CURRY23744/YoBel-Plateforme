@extends('layouts.app')

@section('title', 'Espace général')
@section('nav', 'space')
@section('page-roles', 'admin,project_manager,member')

@section('content')
    <div class="page-head">
        <div>
            <h1>Espace général</h1>
            <div class="sub">Annonces de l’organisation et discussions de collaboration.</div>
        </div>
        <div id="spaceActions" class="d-flex gap-2"></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Annonces</span>
                    <div class="d-flex align-items-center gap-2">
                        <div class="form-check form-switch small" id="allAnnouncementsWrap">
                            <input class="form-check-input" id="allAnnouncements" type="checkbox">
                            <label class="form-check-label" for="allAnnouncements">Toutes</label>
                        </div>
                        <div id="announcementAction"></div>
                    </div>
                </div>
                <div class="list-group list-group-flush" id="announcementList">
                    <div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement…</div>
                </div>
                <div class="card-body" id="announcementPager"></div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>Discussions générales</span>
                    <div class="d-flex gap-2">
                        <input class="form-control form-control-sm" id="discussionSearch" type="search" placeholder="Rechercher" aria-label="Rechercher une discussion">
                        <button class="btn btn-sm btn-primary text-nowrap" id="createDiscussion" type="button"><i class="bi bi-plus-lg me-1"></i>Nouvelle</button>
                    </div>
                </div>
                <div class="list-group list-group-flush" id="discussionList">
                    <div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement…</div>
                </div>
                <div class="card-body" id="discussionPager"></div>
            </div>
        </div>
    </div>

    <div class="card mt-3 d-none" id="discussionDetail">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-light-soft" type="button" id="backToDiscussions" aria-label="Retour aux discussions"><i class="bi bi-arrow-left"></i></button>
                <h2 class="h6 mb-0" id="discussionTitle"></h2>
            </div>
            <div class="d-flex gap-1" id="discussionActions"></div>
        </div>
        <div class="card-body">
            <div id="discussionMessages"></div>
            <div id="messagePager" class="mt-3"></div>
            <form id="messageForm" class="mt-3 d-none">
                <label class="form-label" for="messageBody">Votre message</label>
                <textarea class="form-control" id="messageBody" name="body" rows="3" maxlength="10000" required></textarea>
                <div class="d-flex justify-content-end mt-2"><button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Envoyer</button></div>
            </form>
            <div id="lockedMessage" class="alert alert-warning mt-3 d-none mb-0">Cette discussion est verrouillée.</div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/space.js') }}"></script>
@endpush