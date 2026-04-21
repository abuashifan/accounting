@extends('debug.layout')

@section('title', 'Debug Journals')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Journals</h1>
        <a class="btn btn-sm btn-primary" href="{{ route('debug.journals.create') }}">Create Journal</a>
    </div>

    <div class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-3">
            <label class="form-label" for="statusFilter">Status</label>
            <select id="statusFilter" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="draft">draft</option>
                <option value="posted">posted</option>
                <option value="void">void</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <button id="applyFilter" class="btn btn-sm btn-outline-secondary">Apply</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Journal No</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th style="width: 180px">Action</th>
                    </tr>
                </thead>
                <tbody id="journalTbody">
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
            const tbody = document.getElementById('journalTbody');
            const statusFilter = document.getElementById('statusFilter');
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
                const status = params.get('status') || '';
                statusFilter.value = status;

                const url = new URL('/api/journals', window.location.origin);
                url.searchParams.set('page', page);
                if (status) url.searchParams.set('status', status);

                const res = await window.DebugApi.apiJson(url.toString());
                const paginator = res?.data;
                const rows = paginator?.data || [];

                tbody.innerHTML = '';
                if (rows.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-muted">No data</td></tr>`;
                    return;
                }

                for (const journal of rows) {
                    const editUrl = `{{ url('/debug/journals') }}/${journal.id}/edit`;
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${journal.journal_no || '-'}</td>
                            <td>${journal.date || '-'}</td>
                            <td>${journal.description || ''}</td>
                            <td><span class="badge text-bg-secondary">${journal.status || '-'}</span></td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="${editUrl}">Edit</a>
                                <button class="btn btn-sm btn-outline-danger" data-void-id="${journal.id}">Void</button>
                            </td>
                        </tr>
                    `);
                }

                pageInfo.textContent = `Page ${paginator.current_page} / ${paginator.last_page} (Total ${paginator.total})`;
                prevBtn.disabled = !paginator.prev_page_url;
                nextBtn.disabled = !paginator.next_page_url;

                tbody.querySelectorAll('[data-void-id]').forEach((btn) => {
                    btn.addEventListener('click', async () => {
                        const id = btn.getAttribute('data-void-id');
                        const reason = window.prompt('Reason (optional):') || null;
                        const voidUrl = new URL(`/api/journals/${id}/void`, window.location.origin);
                        const payload = reason ? { reason } : {};
                        await window.DebugApi.apiJson(voidUrl.toString(), {
                            method: 'POST',
                            body: JSON.stringify(payload),
                        });
                        window.DebugApi.showAlert('success', 'Journal voided');
                        await load();
                    });
                });
            }

            document.getElementById('applyFilter').addEventListener('click', () => {
                const params = qs();
                const status = statusFilter.value;
                params.set('page', '1');
                if (status) params.set('status', status); else params.delete('status');
                setQs(params);
            });

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
