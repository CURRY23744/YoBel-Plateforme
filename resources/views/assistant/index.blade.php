@extends('layouts.app')

@section('title', 'Assistant Yobel')
@section('nav', 'assistant')
@section('page-roles', 'admin,project_manager,member,guest')

@section('content')
    <div id="assistantPage">
        <div class="page-head">
            <div>
                <h1><i class="bi bi-stars text-primary me-2"></i>Assistant Yobel</h1>
                <div class="sub">Posez vos questions générales ou consultez les données des projets auxquels vous avez accès.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0" for="assistantProject">Contexte</label>
                <select class="form-select" id="assistantProject" aria-label="Choisir le contexte de projet" style="min-width:220px">
                    <option value="">Question générale (aucun projet)</option>
                </select>
            </div>
        </div>

        <div class="alert alert-info" role="note">
            <i class="bi bi-shield-lock me-1"></i>
            Yobel utilise Google Gemini pour répondre à vos questions. Votre question, les derniers échanges de ce contexte et, si vous choisissez un projet, les données accessibles de ce projet sont transmis à Google. Consultez les conditions et règles de conservation de Google avant utilisation. Yobel ne modifie aucune donnée.
        </div>

        <div class="card">
            <div class="card-body">
                <div id="assistantMessages" class="d-flex flex-column gap-3 mb-4" aria-live="polite">
                    <div class="text-muted text-center py-5" id="assistantWelcome">
                        Bonjour, je suis Yobel. Posez-moi une question, partagez une idée ou demandez un conseil. Vous pouvez choisir un projet si vous souhaitez que je tienne compte de ses données.
                    </div>
                </div>
                <div class="alert alert-danger d-none" id="assistantError" role="alert"></div>
                <form id="assistantForm">
                    <label class="form-label" for="assistantPrompt">Votre question</label>
                    <div class="input-group">
                        <textarea class="form-control" id="assistantPrompt" rows="2" maxlength="4000" required placeholder="Ex. : J’ai une idée de projet : … Qu’en penses-tu ?"></textarea>
                        <button class="btn btn-primary px-4" type="submit" id="assistantSend">
                            <i class="bi bi-send me-1"></i>Envoyer
                        </button>
                    </div>
                    <div class="form-text">Yobel peut discuter de sujets variés et vous aider à réfléchir. Le projet est facultatif; choisissez-en un uniquement si vous souhaitez inclure ses données autorisées dans la conversation.</div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/assistant.js') }}"></script>
@endpush
