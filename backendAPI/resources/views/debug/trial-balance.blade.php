@extends('debug.layout')

@section('title', 'Debug Trial Balance')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Trial Balance</h1>
        <button id="loadBtn" class="btn btn-sm btn-outline-primary">Load</button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
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
                        Data source: <code>GET /api/reports/trial-balance</code>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Total Debit</div>
                    <div class="fs-5" id="totalDebit">-</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Total Credit</div>
                    <div class="fs-5" id="totalCredit">-</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Balanced</div>
                    <div class="fs-5" id="isBalanced">-</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="5" class="text-muted">Fill dates then click Load</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const tbody = document.getElementById('tbody');

            async function load() {
                const dateFrom = document.getElementById('date_from').value;
                const dateTo = document.getElementById('date_to').value;
                if (!dateFrom || !dateTo) {
                    window.DebugApi.showAlert('warning', 'date_from and date_to are required');
                    return;
                }

                const url = new URL('/api/reports/trial-balance', window.location.origin);
                url.searchParams.set('date_from', dateFrom);
                url.searchParams.set('date_to', dateTo);

                const response = await window.DebugApi.apiFetch(url.toString());
                const reportPayload = await response.json().catch(() => null);
                if (!response.ok || !reportPayload?.data) {
                    const message = reportPayload?.message || `Request failed (${response.status})`;
                    const errors = reportPayload?.errors ? JSON.stringify(reportPayload.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', message, errors);
                    return;
                }

                const report = reportPayload.data;
                document.getElementById('totalDebit').textContent = report.total_debit;
                document.getElementById('totalCredit').textContent = report.total_credit;
                document.getElementById('isBalanced').textContent = report.total_debit === report.total_credit ? 'Yes' : 'No';

                tbody.innerHTML = '';
                for (const account of report.accounts || []) {
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${account.account_code}</td>
                            <td>${account.account_name}</td>
                            <td>${account.total_debit}</td>
                            <td>${account.total_credit}</td>
                            <td>${account.balance}</td>
                        </tr>
                    `);
                }
            }

            document.getElementById('loadBtn').addEventListener('click', () => load().catch(() => {}));

            @if (!empty($filters['date_from']) && !empty($filters['date_to']))
                load().catch(() => {});
            @endif
        })();
    </script>
@endpush
