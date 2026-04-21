@extends('debug.layout')

@section('title', 'Debug Invoices')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Invoices</h1>
        <a class="btn btn-sm btn-primary" href="{{ route('debug.invoices.create') }}">Create Invoice</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="invoiceTbody">
                    <tr>
                        <td colspan="6" class="text-muted">Loading...</td>
                    </tr>
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
            const tbody = document.getElementById('invoiceTbody');
            const pageInfo = document.getElementById('pageInfo');
            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');

            function qs() {
                return new URLSearchParams(window.location.search);
            }
            function setQs(params) {
                const url = new URL(window.location.href);
                url.search = params.toString();
                window.location.href = url.toString();
            }

            async function load() {
                const params = qs();
                const page = params.get('page') || '1';

                const url = new URL(`{{ route('debug.api.invoices.list') }}`, window.location.origin);
                url.searchParams.set('page', page);

                const res = await window.DebugApi.apiJson(url.toString());
                const paginator = res?.data;
                const rows = paginator?.data || [];

                tbody.innerHTML = '';
                if (rows.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-muted">No data</td></tr>`;
                    return;
                }

                for (const inv of rows) {
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${inv.id}</td>
                            <td>${inv.invoice_no}</td>
                            <td>${inv.invoice_date || '-'}</td>
                            <td>${inv.amount}</td>
                            <td>${inv.paid_amount ?? '-'}</td>
                            <td><span class="badge text-bg-secondary">${inv.status || '-'}</span></td>
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
