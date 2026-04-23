@extends('debug.layout')

@section('title', 'Debug Dashboard')

@section('content')
    <h1 class="h4 mb-3">Dashboard</h1>

    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Journal</div>
                    <div class="fs-4" id="totalJournal">-</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Posted Journal</div>
                    <div class="fs-4" id="totalPostedJournal">-</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Sales Invoice</div>
                    <div class="fs-4" id="totalInvoice">-</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Sales Payment</div>
                    <div class="fs-4" id="totalPayment">-</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Purchase Invoice</div>
                    <div class="fs-4" id="totalPurchaseInvoice">-</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Purchase Payment</div>
                    <div class="fs-4" id="totalPurchasePayment">-</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
        <h2 class="h6 m-0 text-muted">Master Data</h2>
    </div>

    <div class="row g-3">
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.accounts') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Accounts</div>
                        <div class="text-muted small">Chart of accounts</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
        <h2 class="h6 m-0 text-muted">Transactions</h2>
    </div>

    <div class="row g-3">
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.invoices.index') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Sales Invoice</div>
                        <div class="text-muted small">AR vs Revenue + (COGS vs Inventory on post)</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.payments.index') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Sales Payment</div>
                        <div class="text-muted small">Cash/Bank vs AR</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.purchase-invoices.index') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Purchase Invoice</div>
                        <div class="text-muted small">Inventory vs AP + stock increases on post</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.purchase-payments.index') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Purchase Payment</div>
                        <div class="text-muted small">AP vs Cash/Bank</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
        <h2 class="h6 m-0 text-muted">Reports</h2>
    </div>

    <div class="row g-3">
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.trial-balance') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Trial Balance</div>
                        <div class="text-muted small">Neraca saldo (posted only)</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.general-ledger') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">General Ledger</div>
                        <div class="text-muted small">Buku besar per akun</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.profit-loss') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Profit &amp; Loss</div>
                        <div class="text-muted small">Laba rugi (revenue/expense)</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.balance-sheet') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Balance Sheet</div>
                        <div class="text-muted small">Neraca (asset/liability/equity)</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.cash-flow') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Cash Flow</div>
                        <div class="text-muted small">Arus kas (kas/bank)</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
        <h2 class="h6 m-0 text-muted">Inventory</h2>
    </div>

    <div class="row g-3">
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.inventory.items') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Item Master</div>
                        <div class="text-muted small">/api/items</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.inventory.warehouses') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Warehouses</div>
                        <div class="text-muted small">/api/warehouses</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.inventory.stock-card') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Stock Card</div>
                        <div class="text-muted small">/api/reports/stock-card</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.inventory.adjustment') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Stock Adjustment</div>
                        <div class="text-muted small">/api/stocks/adjustment</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <a class="text-decoration-none" href="{{ route('debug.inventory.transfer') }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold">Stock Transfer</div>
                        <div class="text-muted small">/api/stocks/transfer</div>
                    </div>
                </div>
            </a>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (async () => {
            const res = await window.DebugApi.apiJson('{{ route('debug.api.dashboard-stats') }}');
            if (!res?.success) return;
            document.getElementById('totalJournal').textContent = res.data.total_journal;
            document.getElementById('totalPostedJournal').textContent = res.data.total_posted_journal;
            document.getElementById('totalInvoice').textContent = res.data.total_invoice;
            document.getElementById('totalPayment').textContent = res.data.total_payment;
            document.getElementById('totalPurchaseInvoice').textContent = res.data.total_purchase_invoice;
            document.getElementById('totalPurchasePayment').textContent = res.data.total_purchase_payment;
        })().catch(() => {});
    </script>
@endpush
