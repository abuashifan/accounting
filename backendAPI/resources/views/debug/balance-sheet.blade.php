@extends('debug.layout')

@section('title', 'Debug Balance Sheet')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Balance Sheet</h1>
        <button id="loadBtn" class="btn btn-sm btn-outline-primary">Load</button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="date_from">Date From</label>
                    <input class="form-control form-control-sm" type="date" id="date_from" value="{{ $filters['date_from'] }}">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="date_to">Date To</label>
                    <input class="form-control form-control-sm" type="date" id="date_to" value="{{ $filters['date_to'] }}">
                </div>
                <div class="col-12 col-md-6 d-flex align-items-end">
                    <div class="text-muted small">
                        Data source: <code>GET /api/reports/balance-sheet</code>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Assets</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Code</th><th>Name</th><th class="text-end">Balance</th></tr></thead>
                            <tbody id="assetsBody">
                                <tr><td colspan="3" class="text-muted">Fill dates then click Load</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Liabilities</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Code</th><th>Name</th><th class="text-end">Balance</th></tr></thead>
                            <tbody id="liabilitiesBody">
                                <tr><td colspan="3" class="text-muted">Fill dates then click Load</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Equity</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Code</th><th>Name</th><th class="text-end">Balance</th></tr></thead>
                            <tbody id="equityBody">
                                <tr><td colspan="3" class="text-muted">Fill dates then click Load</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            function renderRows(tbody, rows) {
                tbody.innerHTML = '';
                for (const row of rows || []) {
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${row.account_code}</td>
                            <td>${row.account_name}</td>
                            <td class="text-end">${row.balance}</td>
                        </tr>
                    `);
                }
                if (!rows || rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-muted">No data</td></tr>';
                }
            }

            async function load() {
                const dateFrom = document.getElementById('date_from').value;
                const dateTo = document.getElementById('date_to').value;
                if (!dateFrom || !dateTo) {
                    window.DebugApi.showAlert('warning', 'date_from and date_to are required');
                    return;
                }

                const url = new URL('/api/reports/balance-sheet', window.location.origin);
                url.searchParams.set('date_from', dateFrom);
                url.searchParams.set('date_to', dateTo);

                const response = await window.DebugApi.apiFetch(url.toString());
                const payload = await response.json().catch(() => null);
                if (!response.ok || !payload?.data) {
                    const message = payload?.message || `Request failed (${response.status})`;
                    const errors = payload?.errors ? JSON.stringify(payload.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', message, errors);
                    return;
                }

                renderRows(document.getElementById('assetsBody'), payload.data.assets);
                renderRows(document.getElementById('liabilitiesBody'), payload.data.liabilities);
                renderRows(document.getElementById('equityBody'), payload.data.equity);
            }

            document.getElementById('loadBtn').addEventListener('click', () => load().catch(() => {}));

            @if (!empty($filters['date_from']) && !empty($filters['date_to']))
                load().catch(() => {});
            @endif
        })();
    </script>
@endpush

