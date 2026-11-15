
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Connexion') · YOBEL Workspace</title>

    <link
        rel="icon"
        type="image/png"
        href="{{ asset('images/favicon.png') }}"
    >

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    {{-- Bootstrap --}}
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    {{-- Bootstrap Icons --}}
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    {{-- CSS principal --}}
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    @stack('styles')
</head>

<body
    class="guest"
    data-guest="1"
    data-page="@yield('page')"
>

    <div class="guest-wrap">

        <div class="card auth-card">

            <div class="card-body p-4 p-sm-5">

                <img
                    class="logo"
                    src="{{ asset('images/logo.png') }}"
                    alt="YOBEL – Tech & Engineering Studio"
                >

                @yield('content')

            </div>

        </div>

    </div>

    {{-- Notifications --}}
    <div
        class="toast-container position-fixed top-0 end-0 p-3"
        id="toasts"
    ></div>

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Application JS --}}
    <script src="{{ asset('js/app.js') }}"></script>

    {{-- Authentication JS --}}
    <script src="{{ asset('pages/auth.js') }}"></script>

    @stack('scripts')

</body>
</html>
