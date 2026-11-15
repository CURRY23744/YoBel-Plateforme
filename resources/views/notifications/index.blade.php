@extends('layouts.app')

@section('title', 'Notifications')
@section('nav', 'notifications')

@section('content')
    <div class="page-head">
        <div>
            <h1>Notifications</h1>
            <div class="sub" id="notificationSummary">Chargement…</div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="form-check form-switch">
                <input class="form-check-input" id="unreadOnly" type="checkbox">
                <label class="form-check-label" for="unreadOnly">Non lues uniquement</label>
            </div>
            <button type="button" class="btn btn-outline-primary" id="markAllRead"><i class="bi bi-check2-all me-1"></i>Tout marquer comme lu</button>
        </div>
    </div>

    <div class="card">
        <div class="list-group list-group-flush" id="notificationItems">
            <div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des notifications…</div>
        </div>
        <div class="card-body" id="notificationPager"></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/notifications.js') }}"></script>
@endpush