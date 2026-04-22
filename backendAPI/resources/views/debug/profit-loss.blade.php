@extends('debug.layout')

@section('title', 'Debug Profit & Loss')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Profit &amp; Loss</h1>
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
                        Data source: <code>GET /api/reports/profit-loss</code>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Total Revenue</div>
                    <div class="fs-5" id="totalRevenue">-</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Total Expense</div>
                    <div class="fs-5" id="totalExpense">-</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Net Profit</div>
                    <div class="fs-5" id="netProfit">-</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            async function load() {
                const dateFrom = document.getElementById('date_from').value;
                const dateTo = document.getElementById('date_to').value;
                if (!dateFrom || !dateTo) {
                    window.DebugApi.showAlert('warning', 'date_from and date_to are required');
                    return;
                }

                const url = new URL('/api/reports/profit-loss', window.location.origin);
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

                const report = payload.data;
                document.getElementById('totalRevenue').textContent = report.total_revenue;
                document.getElementById('totalExpense').textContent = report.total_expense;
                document.getElementById('netProfit').textContent = report.net_profit;
            }

            document.getElementById('loadBtn').addEventListener('click', () => load().catch(() => {}));

            @if (!empty($filters['date_from']) && !empty($filters['date_to']))
                load().catch(() => {});
            @endif
        })();
    </script>
@endpush

