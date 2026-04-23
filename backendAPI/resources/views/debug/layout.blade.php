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

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#debugNavbar"
                aria-controls="debugNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="debugNavbar">
            <div class="navbar-nav flex-wrap">
                <a class="nav-link" href="{{ route('debug.dashboard') }}">Dashboard</a>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Penjualan
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('debug.invoices.index') }}">Faktur Penjualan</a></li>
                        <li><a class="dropdown-item" href="{{ route('debug.payments.index') }}">Pembayaran Penjualan</a></li>
                        <li><a class="dropdown-item" href="{{ route('debug.sales-returns.index') }}">Retur Penjualan</a></li>
                    </ul>
                </div>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Pembelian
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('debug.purchase-invoices.index') }}">Faktur Pembelian</a></li>
                        <li>
                            <a class="dropdown-item" href="{{ route('debug.purchase-payments.index') }}">Pembayaran Pembelian</a>
                        </li>
                        <li><a class="dropdown-item" href="{{ route('debug.purchase-returns.index') }}">Retur Pembelian</a></li>
                    </ul>
                </div>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Inventory
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('debug.inventory.items') }}">Items</a></li>
                        <li><a class="dropdown-item" href="{{ route('debug.inventory.warehouses') }}">Warehouse</a></li>
                        <li><a class="dropdown-item" href="{{ route('debug.inventory.adjustment') }}">Stock Adjustment</a></li>
                        <li><a class="dropdown-item" href="{{ route('debug.inventory.transfer') }}">Stock Transfer</a></li>
                    </ul>
                </div>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        General Ledger
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('debug.accounts') }}">Accounts</a></li>
                        <li><a class="dropdown-item" href="{{ route('debug.journals.index') }}">Jurnal</a></li>
                        <li><a class="dropdown-item" href="{{ route('debug.general-ledger') }}">General Ledger</a></li>
                    </ul>
                </div>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Report
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('debug.trial-balance') }}">Trial Balance</a></li>
                        <li><a class="dropdown-item" href="{{ route('debug.inventory.stock-card') }}">Stock Card</a></li>
                        <li><a class="dropdown-item" href="{{ route('debug.profit-loss') }}">Profit &amp; Loss</a></li>
                        <li><a class="dropdown-item" href="{{ route('debug.balance-sheet') }}">Balance Sheet</a></li>
                        <li><a class="dropdown-item" href="{{ route('debug.cash-flow') }}">Cash Flow</a></li>
                    </ul>
                </div>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Setting
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('debug.settings.journals') }}">Settings</a></li>
                    </ul>
                </div>
            </div>

            <div class="d-flex ms-lg-auto gap-2 align-items-center mt-2 mt-lg-0">
                <input id="tokenPreview" class="form-control form-control-sm d-none d-lg-block" style="width: 360px"
                       readonly type="password" placeholder="Bearer token">
                <button id="logoutBtn" class="btn btn-sm btn-outline-light w-100 w-lg-auto">Logout</button>
            </div>
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
