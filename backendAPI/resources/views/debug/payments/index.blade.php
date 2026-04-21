@extends('debug.layout')

@section('title', 'Debug Payments')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Payments</h1>
        <a class="btn btn-sm btn-primary" href="{{ route('debug.payments.create') }}">Create Payment</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Payment No</th>
                        <th>Invoice ID</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody id="paymentTbody">
                    <tr>
                        <td colspan="5" class="text-muted">Loading...</td>
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
            const tbody = document.getElementById('paymentTbody');
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

                const url = new URL(`{{ route('debug.api.payments.list') }}`, window.location.origin);
                url.searchParams.set('page', page);

                const res = await window.DebugApi.apiJson(url.toString());
                const paginator = res?.data;
                const rows = paginator?.data || [];

                tbody.innerHTML = '';
                if (rows.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-muted">No data</td></tr>`;
                    return;
                }

                for (const pay of rows) {
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${pay.id}</td>
                            <td>${pay.payment_no}</td>
                            <td>${pay.invoice_id}</td>
                            <td>${pay.amount}</td>
                            <td>${pay.payment_date || '-'}</td>
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
