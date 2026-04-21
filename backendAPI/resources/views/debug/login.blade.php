<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Debug Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 520px">
    <div class="card">
        <div class="card-body">
            <h1 class="h4 mb-3">Debug UI Login</h1>
            <div class="alert alert-info small">
                Token will be stored in <code>localStorage</code> key <code>auth_token</code>.
            </div>

            <div id="alert"></div>

            <form id="loginForm">
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" type="email" id="email" required autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="token_name">Token name (optional)</label>
                    <input class="form-control" id="token_name" value="debug-ui">
                </div>
                <button class="btn btn-primary w-100" type="submit">Login → POST /api/auth/token</button>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const TOKEN_KEY = 'auth_token';

    const existing = localStorage.getItem(TOKEN_KEY);
    if (existing) {
        window.location.href = '{{ route('debug.dashboard') }}';
        return;
    }

    function escapeHtml(text) {
        return String(text)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showAlert(type, message, details) {
        const el = document.getElementById('alert');
        const detailsHtml = details ? `<pre class="mt-2 mb-0 small">${escapeHtml(details)}</pre>` : '';
        el.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <div>${escapeHtml(message)}</div>
                ${detailsHtml}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
    }

    async function submitLogin(e) {
        e.preventDefault();

        const payload = {
            email: document.getElementById('email').value,
            password: document.getElementById('password').value,
            token_name: document.getElementById('token_name').value || 'debug-ui',
        };

        const res = await fetch('/api/auth/token', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const json = await res.json().catch(() => null);

        if (!res.ok || !json?.success) {
            const message = json?.message || `Login failed (${res.status})`;
            const errors = json?.errors ? JSON.stringify(json.errors, null, 2) : null;
            showAlert('danger', message, errors);
            return;
        }

        const token = json?.data?.token;
        if (!token) {
            showAlert('danger', 'Login failed: token not found in response');
            return;
        }

        localStorage.setItem(TOKEN_KEY, token);
        window.location.href = '{{ route('debug.dashboard') }}';
    }

    document.getElementById('loginForm').addEventListener('submit', (e) => {
        submitLogin(e).catch((err) => showAlert('danger', 'Login error', err?.message || String(err)));
    });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

