@extends('layouts.app')

@section('title', 'Assistant Yobel')
@section('nav', 'assistant')
@section('page-roles', 'admin,project_manager,member')

@section('content')
    <div id="assistantPage">
        <div class="page-head">
            <div>
                <h1><i class="bi bi-stars text-primary me-2"></i>Assistant Yobel</h1>
                <div class="sub">Un assistant local pour vous aider à comprendre et organiser vos projets.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0" for="assistantProject">Contexte</label>
                <select class="form-select" id="assistantProject" aria-label="Choisir le contexte de projet" style="min-width:220px">
                    <option value="">Question générale</option>
                </select>
            </div>
        </div>

        <div class="alert alert-info" role="note">
            <i class="bi bi-shield-lock me-1"></i>
            Les réponses sont générées par le modèle Ollama local configuré par l’application. Yobel ne modifie aucune donnée et ne lance aucune action.
        </div>

        <div class="card">
            <div class="card-body">
                <div id="assistantMessages" class="d-flex flex-column gap-3 mb-4" aria-live="polite">
                    <div class="text-muted text-center py-5" id="assistantWelcome">
                        Bonjour, je suis Yobel. Posez une question sur votre travail ou choisissez un projet pour ajouter son contexte.
                    </div>
                </div>
                <div class="alert alert-danger d-none" id="assistantError" role="alert"></div>
                <form id="assistantForm">
                    <label class="form-label" for="assistantPrompt">Votre question</label>
                    <div class="input-group">
                        <textarea class="form-control" id="assistantPrompt" rows="2" maxlength="4000" required placeholder="Ex. : Quelles tâches risquent de retarder ce projet ?"></textarea>
                        <button class="btn btn-primary px-4" type="submit" id="assistantSend">
                            <i class="bi bi-send me-1"></i>Envoyer
                        </button>
                    </div>
                    <div class="form-text">Les réponses peuvent être inexactes : vérifiez les informations importantes.</div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/assistant.js') }}"></script>
@endpush
