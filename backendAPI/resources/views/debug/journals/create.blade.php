@extends('debug.layout')

@section('title', 'Create Journal')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Create Journal</h1>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.journals.index') }}">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="journalForm">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="date">Date</label>
                        <input class="form-control form-control-sm" type="date" id="date" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="period">Accounting Period ID</label>
                        <input class="form-control form-control-sm" type="number" id="period" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="description">Description</label>
                        <input class="form-control form-control-sm" type="text" id="description">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="reason">Reason (optional)</label>
                        <input class="form-control form-control-sm" type="text" id="reason">
                    </div>
                </div>

                <hr class="my-3">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 m-0">Lines</h2>
                    <button type="button" id="addLine" class="btn btn-sm btn-outline-primary">Add line</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead>
                            <tr>
                                <th style="width: 320px">Account</th>
                                <th style="width: 160px">Debit</th>
                                <th style="width: 160px">Credit</th>
                                <th style="width: 80px"></th>
                            </tr>
                        </thead>
                        <tbody id="linesTbody"></tbody>
                    </table>
                </div>

                <button class="btn btn-primary btn-sm" type="submit">Submit → POST /api/journals</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const tbody = document.getElementById('linesTbody');
            let accounts = [];

            function escapeHtml(text) {
                return String(text)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function accountOptionsHtml(selectedId) {
                const selected = Number(selectedId || 0);
                const hasSelected = selected && accounts.some((a) => a.id === selected);
                const options = [
                    `<option value="" ${selected ? '' : 'selected'} disabled>— Select account —</option>`,
                    ...(hasSelected ? [] : (selected ? [`<option value="${selected}" selected>(ID ${selected})</option>`] : [])),
                    ...accounts.map((a) => {
                        const label = `${a.code} - ${a.name}`;
                        return `<option value="${a.id}" ${a.id === selected ? 'selected' : ''}>${escapeHtml(label)}</option>`;
                    }),
                ];
                return options.join('');
            }

            function addLine(line = { account_id: '', debit: '0', credit: '0' }) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <select class="form-select form-select-sm" data-field="account_id" required>
                            ${accountOptionsHtml(line.account_id)}
                        </select>
                    </td>
                    <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" data-field="debit" required value="${line.debit ?? '0'}"></td>
                    <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" data-field="credit" required value="${line.credit ?? '0'}"></td>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove>×</button></td>
                `;
                row.querySelector('[data-remove]').addEventListener('click', () => {
                    row.remove();
                });
                tbody.appendChild(row);
            }

            document.getElementById('addLine').addEventListener('click', () => addLine());

            async function loadAccounts() {
                const res = await window.DebugApi.apiJson('/api/accounts');
                accounts = Array.isArray(res?.data) ? res.data : [];
            }

            document.getElementById('journalForm').addEventListener('submit', async (e) => {
                e.preventDefault();

                const lines = Array.from(tbody.querySelectorAll('tr')).map((tr) => {
                    const account_id = tr.querySelector('[data-field="account_id"]').value;
                    const debit = tr.querySelector('[data-field="debit"]').value;
                    const credit = tr.querySelector('[data-field="credit"]').value;
                    return {
                        account_id: Number(account_id),
                        debit: Number(debit),
                        credit: Number(credit),
                    };
                });

                const payload = {
                    date: document.getElementById('date').value,
                    description: document.getElementById('description').value || null,
                    reason: document.getElementById('reason').value || null,
                    accounting_period_id: Number(document.getElementById('period').value),
                    lines,
                };

                const res = await window.DebugApi.apiJson('/api/journals', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });

                if (res?.success) {
                    window.DebugApi.showAlert('success', 'Journal created');
                    window.location.href = '{{ route('debug.journals.index') }}';
                }
            });

            (async () => {
                try {
                    await loadAccounts();
                } catch (e) {}
                addLine();
                addLine();
            })();
        })();
    </script>
@endpush
