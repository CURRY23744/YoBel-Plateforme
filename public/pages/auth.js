
/* Pages d'authentification :
   connexion, mot de passe oublié,
   réinitialisation, changement obligatoire
*/

(function () {
    'use strict';

    const { api, setSession, token, go, onFormSubmit, qs, $, $$ } = App;

    const page = document.body.dataset.page;

    // ---------- Utilitaires communs ----------

    function alertBox(msg, type = 'danger') {
        const box = $('#formAlert');

        if (!box) return;

        box.className = `alert alert-${type}`;
        box.textContent = msg;
    }

    // ---------- Afficher / masquer le mot de passe ----------

    $$('[data-toggle-password]').forEach((btn) => {

        btn.addEventListener('click', () => {

            const input = btn.dataset.togglePassword
                ? $(btn.dataset.togglePassword)
                : btn.closest('.input-group')?.querySelector('input[type="password"], input[type="text"]');

            if (!input) return;

            const show = input.type === 'password';

            input.type = show ? 'text' : 'password';

            const icon = btn.querySelector('i');

            if (icon) {
                icon.classList.toggle('bi-eye', !show);
                icon.classList.toggle('bi-eye-slash', show);
            }

            btn.setAttribute(
                'aria-label',
                show ? 'Masquer le mot de passe' : 'Afficher le mot de passe'
            );

            btn.setAttribute('aria-pressed', String(show));

            input.focus();
        });

    });

    // ---------- Règles de robustesse ----------

    const RULES = [
        ['8 caractères minimum', (v) => v.length >= 8],
        [
            'Une majuscule et une minuscule',
            (v) => /[a-z]/.test(v) && /[A-Z]/.test(v)
        ],
        ['Un chiffre', (v) => /\d/.test(v)]
    ];

    function bindRules() {

        const input = $('#password');
        const list = $('#pwRules');

        if (!input || !list) return;

        const draw = () => {

            list.innerHTML = RULES.map(([label, test]) => {

                const ok = test(input.value);

                return `
                    <li class="${ok ? 'text-success' : 'text-muted'}">
                        <i class="bi ${ok ? 'bi-check-circle-fill' : 'bi-circle'} me-1"></i>
                        ${label}
                    </li>
                `;

            }).join('');

        };

        input.addEventListener('input', draw);

        draw();
    }

    const rulesOk = (v) =>
        RULES.every(([, test]) => test(v || ''));

    // ---------- Connexion ----------

    if (page === 'login') {

        // Déjà connecté ? On redirige.

        if (token()) {

            api('auth/me')
                .then((u) => {
                    go(
                        u.must_change_password
                            ? '/change-password'
                            : '/dashboard'
                    );
                })
                .catch(() => {});

        }

        onFormSubmit($('#loginForm'), async (d) => {

            try {

                const r = await api('auth/login', {
                    method: 'POST',
                    body: {
                        email: d.email,
                        password: d.password,
                        device: 'web'
                    }
                });

                setSession(r.token, r.user);

                go(
                    r.must_change_password
                        ? '/change-password'
                        : '/dashboard'
                );

                await new Promise(() => {});

            } catch (e) {

                if (e.status === 423) {

                    alertBox(
                        'Compte temporairement bloqué après plusieurs échecs. Réessayez dans quelques minutes.',
                        'warning'
                    );

                } else if (e.status === 403) {

                    alertBox(
                        'Ce compte est désactivé. Contactez un administrateur.',
                        'warning'
                    );

                } else if (e.status === 429) {

                    alertBox(
                        'Trop de tentatives. Patientez une minute.',
                        'warning'
                    );

                } else {

                    alertBox(
                        e.status === 401
                            ? 'Adresse e-mail ou mot de passe incorrect.'
                            : (e.message || 'Connexion impossible.')
                    );

                }

                throw e;
            }

        });
    }

    // ---------- Mot de passe oublié ----------

    if (page === 'forgot') {

        onFormSubmit($('#forgotForm'), async (d) => {

            const r = await api('auth/forgot-password', {
                method: 'POST',
                body: {
                    email: d.email
                }
            });

            $('#forgotForm').classList.add('d-none');

            alertBox(
                (r && r.message) ||
                "Si ce compte existe, un lien de réinitialisation vient d'être envoyé.",
                'success'
            );

        });
    }

    // ---------- Réinitialisation ----------

    if (page === 'reset') {

        bindRules();

        const tokenParam = qs('token');
        const email = qs('email');

        if (!tokenParam || !email) {

            alertBox(
                'Lien invalide. Refaites une demande de réinitialisation.'
            );

            const form = $('#resetForm');

            if (form) {
                form.classList.add('d-none');
            }

        }

        onFormSubmit($('#resetForm'), async (d) => {

            if (!rulesOk(d.password)) {

                alertBox(
                    'Le mot de passe ne respecte pas les règles indiquées.'
                );

                return;
            }

            if (d.password !== d.password_confirmation) {

                alertBox(
                    'Les deux mots de passe ne correspondent pas.'
                );

                return;
            }

            try {

                await api('auth/reset-password', {
                    method: 'POST',
                    body: {
                        token: tokenParam,
                        email,
                        password: d.password,
                        password_confirmation: d.password_confirmation
                    }
                });

                $('#resetForm').classList.add('d-none');

                alertBox(
                    'Mot de passe mis à jour. Vous pouvez maintenant vous connecter.',
                    'success'
                );

                setTimeout(() => go('/login'), 2000);

            } catch (e) {

                alertBox(
                    e.message || 'Réinitialisation impossible.'
                );

                throw e;
            }

        });
    }

    // ---------- Changement obligatoire ----------

    if (page === 'change') {

        if (!token()) {

            go('/login');

            return;
        }

        bindRules();

        const currentPasswordGroup = $('#currentPasswordGroup');
        const currentPasswordInput = $('#current_password');
        const submitButton = $('#changeForm [type="submit"]');
        submitButton.disabled = true;

        api('auth/me').then((user) => {
            const mustChangePassword = Boolean(user.must_change_password);
            currentPasswordGroup.hidden = mustChangePassword;
            currentPasswordInput.disabled = mustChangePassword;
            currentPasswordInput.required = !mustChangePassword;
            $('#changePasswordIntro').textContent = mustChangePassword
                ? 'Choisissez un mot de passe personnel pour sécuriser votre compte.'
                : 'Saisissez votre mot de passe actuel, puis choisissez-en un nouveau.';
            submitButton.disabled = false;
        }).catch((error) => {
            alertBox(error.message || 'Impossible de vérifier votre compte.');
        });

        const logoutLink = $('#logoutLink');

        if (logoutLink) {

            logoutLink.addEventListener('click', (e) => {

                e.preventDefault();

                App.logout();

            });

        }

        onFormSubmit($('#changeForm'), async (d) => {

            if (!rulesOk(d.password)) {

                alertBox(
                    'Le nouveau mot de passe ne respecte pas les règles indiquées.'
                );

                return;
            }

            if (d.password !== d.password_confirmation) {

                alertBox(
                    'Les deux mots de passe ne correspondent pas.'
                );

                return;
            }

            try {

                await api('auth/change-password', {
                    method: 'POST',
                    body: d
                });

                const me = await api('auth/me');

                setSession(null, me);

                alertBox(
                    'Mot de passe mis à jour. Redirection…',
                    'success'
                );

                go('/dashboard');

                await new Promise(() => {});

            } catch (e) {

                alertBox(
                    e.message || 'Changement impossible.'
                );

                throw e;
            }

        });
    }

})();
