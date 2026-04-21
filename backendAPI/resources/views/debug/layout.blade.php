<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Debug UI')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('debug.dashboard') }}">Debug UI</a>
        <div class="navbar-nav">
            <a class="nav-link" href="{{ route('debug.dashboard') }}">Dashboard</a>
            <a class="nav-link" href="{{ route('debug.journals.index') }}">Journal</a>
            <a class="nav-link" href="{{ route('debug.invoices.index') }}">Invoice</a>
            <a class="nav-link" href="{{ route('debug.payments.index') }}">Payment</a>
            <a class="nav-link" href="{{ route('debug.trial-balance') }}">Trial Balance</a>
            <a class="nav-link" href="{{ route('debug.general-ledger') }}">General Ledger</a>
        </div>
        <div class="d-flex ms-auto gap-2 align-items-center">
            <input id="tokenPreview" class="form-control form-control-sm" style="width: 360px" readonly type="password" placeholder="Bearer token">
            <button id="logoutBtn" class="btn btn-sm btn-outline-light">Logout</button>
        </div>
    </div>
</nav>

<div class="container py-3">
    <div id="debugAlert"></div>
    @yield('content')
</div>

<script>
(() => {
    const tokenKey = 'auth_token';
    const loginPath = '{{ route('debug.login') }}';
    const dashboardPath = '{{ route('debug.dashboard') }}';

    function escapeHtml(text) {
        return String(text)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showAlert(type, message, details) {
        const el = document.getElementById('debugAlert');
        const detailsHtml = details ? `<pre class="mt-2 mb-0 small">${escapeHtml(details)}</pre>` : '';
        el.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <div>${escapeHtml(message)}</div>
                ${detailsHtml}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
    }

    function getToken() {
        return localStorage.getItem(tokenKey) || '';
    }

    function setToken(token) {
        localStorage.setItem(tokenKey, token || '');
    }

    function apiFetch(url, options = {}) {
        const token = localStorage.getItem(tokenKey);
        options.headers = {
            ...(options.headers || {}),
            'Authorization': 'Bearer ' + token,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };

        return fetch(url, options);
    }

    async function apiJson(url, options = {}) {
        const response = await apiFetch(url, options);
        const contentType = response.headers.get('content-type') || '';
        const payload = contentType.includes('application/json')
            ? await response.json().catch(() => null)
            : null;

        if (!response.ok || !payload?.success) {
            const message = payload?.message || `Request failed (${response.status})`;
            const errors = payload?.errors ? JSON.stringify(payload.errors, null, 2) : null;
            showAlert('danger', message, errors);
            throw new Error(message);
        }

        return payload;
    }

    window.DebugApi = {
        showAlert,
        getToken,
        setToken,
        apiFetch,
        apiJson,
    };

    const token = getToken();
    const tokenPreview = document.getElementById('tokenPreview');
    tokenPreview.value = token;

    if (!token) {
        window.location.href = loginPath;
        return;
    }

    if (window.location.pathname === new URL(loginPath, window.location.origin).pathname) {
        window.location.href = dashboardPath;
        return;
    }

    document.getElementById('logoutBtn').addEventListener('click', async () => {
        try {
            await apiFetch('/api/auth/logout', { method: 'POST' });
        } catch (e) {}
        localStorage.removeItem(tokenKey);
        window.location.href = loginPath;
    });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
