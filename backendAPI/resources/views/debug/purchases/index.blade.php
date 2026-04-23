@extends('debug.layout')

@section('title', 'Debug Purchase Invoices')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Purchase Invoices</h1>
        <a class="btn btn-sm btn-primary" href="{{ route('debug.purchases.create') }}">Create Purchase Invoice</a>
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
                        <th>Journal</th>
                        <th>Posted At</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="9" class="text-muted">Loading...</td></tr>
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
                const url = new URL('/api/purchase-invoices', window.location.origin);
                url.searchParams.set('page', page);

                const res = await window.DebugApi.apiFetch(url.toString());
                const payload = await res.json().catch(() => null);
                const paginator = payload?.data;
                const rows = paginator?.data || [];

                tbody.innerHTML = '';
                if (!rows.length) {
                    tbody.innerHTML = `<tr><td colspan="9" class="text-muted">No data</td></tr>`;
                    return;
                }

                for (const inv of rows) {
                    const journalStatus = inv.journal_entry?.status || '-';
                    const isDraft = String(journalStatus).toLowerCase() === 'draft';
                    const canPost = isDraft && !inv.posted_at;
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${inv.id}</td>
                            <td>${inv.invoice_no}</td>
                            <td>${inv.invoice_date || '-'}</td>
                            <td>${inv.amount}</td>
                            <td>${inv.paid_amount ?? '-'}</td>
                            <td><span class="badge text-bg-secondary">${inv.status || '-'}</span></td>
                            <td><span class="badge ${isDraft ? 'text-bg-warning' : 'text-bg-success'}">${journalStatus}</span></td>
                            <td>${inv.posted_at || '-'}</td>
                            <td class="text-end">
                                ${canPost ? `<button class="btn btn-sm btn-outline-primary" data-post="${inv.id}">Post</button>` : ''}
                                <a class="btn btn-sm btn-outline-success ${inv.status === 'paid' ? 'disabled' : ''}" href="{{ url('/debug/purchases') }}/${inv.id}/pay">Pay</a>
                            </td>
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

            tbody.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-post]');
                if (!btn) return;
                const id = btn.getAttribute('data-post');
                if (!id) return;

                await window.DebugApi.apiFetch(`/api/purchase-invoices/${id}/post`, { method: 'POST' })
                    .then(r => r.json().catch(() => null))
                    .then(payload => {
                        if (!payload?.data) throw new Error(payload?.message || 'Post failed');
                    })
                    .catch(err => {
                        window.DebugApi.showAlert('danger', err.message || 'Post failed');
                        throw err;
                    });

                window.DebugApi.showAlert('success', 'Purchase invoice posted');
                load().catch(() => {});
            });

            load().catch(() => {});
        })();
    </script>
@endpush

