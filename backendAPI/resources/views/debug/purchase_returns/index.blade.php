@extends('debug.layout')

@section('title', 'Debug Purchase Returns')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Purchase Returns</h1>
        <a class="btn btn-sm btn-primary" href="{{ route('debug.purchase-returns.create') }}">Create Purchase Return</a>
    </div>

    <div class="alert alert-warning small mb-3" id="dangerBox" style="display:none">
        <div class="fw-semibold">Dangerous mode (posted edit/delete)</div>
        <div class="mt-1">
            Recommended for posted transactions: <span class="fw-semibold">VOID/REVERSAL</span>.
            Editing/deleting posted transactions can break audit trail, GL, and stock balances.
        </div>
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="dangerAck">
            <label class="form-check-label" for="dangerAck">I understand the risk</label>
        </div>
        <div class="text-muted mt-1" id="dangerHint"></div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Return No</th>
                        <th>Date</th>
                        <th>Purchase Invoice</th>
                        <th>Amount</th>
                        <th>Journal</th>
                        <th>Posted At</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="8" class="text-muted">Loading...</td></tr>
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
            const dangerBox = document.getElementById('dangerBox');
            const dangerAck = document.getElementById('dangerAck');
            const dangerHint = document.getElementById('dangerHint');

            function qs() { return new URLSearchParams(window.location.search); }
            function setQs(params) {
                const url = new URL(window.location.href);
                url.search = params.toString();
                window.location.href = url.toString();
            }

            async function initDangerUi() {
                const settings = await window.DebugApi.getJournalSettings();
                dangerBox.style.display = 'block';
                dangerAck.checked = window.DebugApi.getDangerAck();
                dangerAck.disabled = !settings.allow_admin_edit_delete_posted;
                dangerHint.textContent = settings.allow_admin_edit_delete_posted
                    ? 'Posted Edit/Delete buttons require this checkbox.'
                    : 'Disabled by setting transactions.allow_admin_edit_delete_posted (admin-only).';

                dangerAck.addEventListener('change', () => {
                    if (dangerAck.checked) {
                        const ok = confirm('Enable dangerous Edit/Delete buttons for posted transactions on this page?');
                        if (!ok) {
                            dangerAck.checked = false;
                            window.DebugApi.setDangerAck(false);
                            load().catch(() => {});
                            return;
                        }
                    }
                    window.DebugApi.setDangerAck(dangerAck.checked);
                    load().catch(() => {});
                });
            }

            async function load() {
                const settings = await window.DebugApi.getJournalSettings();
                const allowPostedEditDelete = !!settings.allow_admin_edit_delete_posted && window.DebugApi.getDangerAck();

                const params = qs();
                const page = params.get('page') || '1';
                const url = new URL('/api/purchase-returns', window.location.origin);
                url.searchParams.set('page', page);

                const res = await window.DebugApi.apiFetch(url.toString());
                const payload = await res.json().catch(() => null);
                const paginator = payload?.data;
                const rows = paginator?.data || [];

                tbody.innerHTML = '';
                if (!rows.length) {
                    tbody.innerHTML = `<tr><td colspan="8" class="text-muted">No data</td></tr>`;
                    return;
                }

                for (const r of rows) {
                    const journalStatus = r.journal_entry?.status || '-';
                    const isDraft = String(journalStatus).toLowerCase() === 'draft';
                    const canPost = isDraft && !r.posted_at;
                    const invLabel = r.purchase_invoice?.invoice_no || ('#' + r.purchase_invoice_id);
                    const isPosted = !!r.posted_at;
                    const isVoided = !!r.voided_at;
                    const canDanger = !isPosted || allowPostedEditDelete;
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${r.id}</td>
                            <td>${r.return_no}</td>
                            <td>${r.return_date || '-'}</td>
                            <td>${invLabel}</td>
                            <td>${r.amount}</td>
                            <td><span class="badge ${isDraft ? 'text-bg-warning' : 'text-bg-success'}">${journalStatus}</span></td>
                            <td>${r.posted_at || '-'}</td>
                            <td class="text-end">
                                ${canPost ? `<button class="btn btn-sm btn-outline-primary" data-post="${r.id}">Post</button>` : ''}
                                ${isPosted && !isVoided ? `<button class="btn btn-sm btn-outline-warning" type="button" data-void="${r.id}">Void</button>` : ''}
                                <a class="btn btn-sm btn-outline-secondary ${canDanger ? '' : 'disabled'}" ${canDanger ? `href="{{ url('/debug/purchase-returns') }}/${r.id}/edit"` : 'href="#" aria-disabled="true" tabindex="-1"'}>Edit</a>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-delete="${r.id}" ${canDanger ? '' : 'disabled'}>Delete</button>
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

                const r = await window.DebugApi.apiFetch(`/api/purchase-returns/${id}/post`, { method: 'POST' });
                const b = await r.json().catch(() => null);
                if (!r.ok || !b?.data) {
                    const msg = b?.message || `Request failed (${r.status})`;
                    const errors = b?.errors ? JSON.stringify(b.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Purchase return posted');
                load().catch(() => {});
            });

            tbody.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-delete]');
                if (!btn) return;
                if (btn.disabled) return;
                const id = btn.getAttribute('data-delete');
                if (!id) return;
                if (!confirm(`Delete purchase return #${id}?`)) return;

                const r = await window.DebugApi.apiFetch(`/api/purchase-returns/${id}`, { method: 'DELETE' });
                const b = await r.json().catch(() => null);
                if (!r.ok) {
                    const msg = b?.message || `Request failed (${r.status})`;
                    const errors = b?.errors ? JSON.stringify(b.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Purchase return deleted');
                load().catch(() => {});
            });

            tbody.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-void]');
                if (!btn) return;
                const id = btn.getAttribute('data-void');
                if (!id) return;

                const reason = prompt('Void reason (optional):') || null;
                if (!confirm(`Void purchase return #${id}?`)) return;

                const r = await window.DebugApi.apiFetch(`/api/purchase-returns/${id}/void`, {
                    method: 'POST',
                    body: JSON.stringify({ void_reason: reason }),
                });
                const b = await r.json().catch(() => null);
                if (!r.ok || !b?.data) {
                    const msg = b?.message || `Request failed (${r.status})`;
                    const errors = b?.errors ? JSON.stringify(b.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Purchase return voided');
                load().catch(() => {});
            });

            initDangerUi()
                .then(() => load())
                .catch(() => load().catch(() => {}));
        })();
    </script>
@endpush
