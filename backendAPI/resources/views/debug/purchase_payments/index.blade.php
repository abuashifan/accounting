@extends('debug.layout')

@section('title', 'Debug Purchase Payments')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Purchase Payments</h1>
        <a class="btn btn-sm btn-primary" href="{{ route('debug.purchase-payments.create') }}">Create Purchase Payment</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Payment No</th>
                        <th>Date</th>
                        <th>Purchase Invoice</th>
                        <th>Amount</th>
                        <th>Journal</th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="6" class="text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex justify-content-between align-items-center">
            <button id="prevPage" class="btn btn-sm btn-outline-secondary">Prev</button>
            <div class="small text-muted" id="pageInfo">-</div>
            <button id="nextPage" class="btn btn-sm btn-outline-secondary">Next</button>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const tbody = document.getElementById('tbody');
            const pageInfo = document.getElementById('pageInfo');
            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');

            function qs() { return new URLSearchParams(window.location.search); }
            function setQs(params) {
                const url = new URL(window.location.href);
                url.search = params.toString();
                window.location.href = url.toString();
            }

            async function load() {
                const params = qs();
                const page = params.get('page') || '1';
                const url = new URL('/api/purchase-payments', window.location.origin);
                url.searchParams.set('page', page);

                const res = await window.DebugApi.apiFetch(url.toString());
                const payload = await res.json().catch(() => null);
                const paginator = payload?.data;
                const rows = paginator?.data || [];

                tbody.innerHTML = '';
                if (!rows.length) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-muted">No data</td></tr>`;
                    return;
                }

                for (const p of rows) {
                    const journalStatus = p.journal_entry?.status || '-';
                    const invNo = p.purchase_invoice?.invoice_no || ('#' + p.purchase_invoice_id);
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${p.id}</td>
                            <td>${p.payment_no}</td>
                            <td>${p.payment_date || '-'}</td>
                            <td>${invNo}</td>
                            <td>${p.amount}</td>
                            <td><span class="badge ${String(journalStatus).toLowerCase() === 'posted' ? 'text-bg-success' : 'text-bg-warning'}">${journalStatus}</span></td>
                        </tr>
                    `);
                }

                pageInfo.textContent = `Page ${paginator.current_page} / ${paginator.last_page} (Total ${paginator.total})`;
                prevBtn.disabled = !paginator.prev_page_url;
                nextBtn.disabled = !paginator.next_page_url;
            }

            prevBtn.addEventListener('click', () => {
                const params = qs();
                const page = Math.max(1, parseInt(params.get('page') || '1', 10) - 1);
                params.set('page', String(page));
                setQs(params);
            });
            nextBtn.addEventListener('click', () => {
                const params = qs();
                const page = Math.max(1, parseInt(params.get('page') || '1', 10) + 1);
                params.set('page', String(page));
                setQs(params);
            });

            load().catch(() => {});
        })();
    </script>
@endpush

