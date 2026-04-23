@extends('debug.layout')

@section('title', 'Edit Purchase Payment')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Edit Purchase Payment #{{ $id }}</h1>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.purchase-payments.index') }}">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-info small">
                Recommended for posted transactions: <span class="fw-semibold">VOID/REVERSAL</span>.
                Editing/deleting posted purchase payment is <span class="fw-semibold">dangerous</span> (admin setting + "I understand the risk").
            </div>

            <form id="form">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="payment_no">Payment No</label>
                        <input class="form-control form-control-sm" id="payment_no" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="payment_date">Payment Date</label>
                        <input class="form-control form-control-sm" type="date" id="payment_date" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="amount">Amount</label>
                        <input class="form-control form-control-sm" type="number" step="0.01" min="0" id="amount" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="purchase_invoice_label">Purchase Invoice</label>
                        <input class="form-control form-control-sm" id="purchase_invoice_label" readonly>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="credit_account_id">Pay From (Cash/Bank)</label>
                        <select class="form-select form-select-sm" id="credit_account_id" required></select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description (optional)</label>
                        <input class="form-control form-control-sm" id="description">
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-primary btn-sm" type="submit">Save</button>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('debug.purchase-payments.index') }}">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const paymentId = {{ (int) $id }};
            const accountSelect = document.getElementById('credit_account_id');

            function escapeHtml(text) {
                return String(text ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            async function loadAccounts() {
                const res = await window.DebugApi.apiFetch('/api/accounts');
                const payload = await res.json().catch(() => null);
                const accounts = payload?.data || [];
                accountSelect.innerHTML = '<option value="">Select account</option>';
                for (const a of accounts) {
                    accountSelect.insertAdjacentHTML('beforeend', `<option value="${a.id}">${escapeHtml(a.code)} - ${escapeHtml(a.name)}</option>`);
                }
            }

            async function loadPayment() {
                const res = await window.DebugApi.apiFetch(`/api/purchase-payments/${paymentId}`);
                const payload = await res.json().catch(() => null);
                const payment = payload?.data;

                if (!res.ok || !payment) {
                    const msg = payload?.message || `Request failed (${res.status})`;
                    const errors = payload?.errors ? JSON.stringify(payload.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return null;
                }

                document.getElementById('payment_no').value = payment.payment_no || '';
                document.getElementById('payment_date').value = (payment.payment_date || '').slice(0, 10);
                document.getElementById('amount').value = payment.amount ?? '';
                document.getElementById('description').value = payment.description || '';

                const invNo = payment.purchase_invoice?.invoice_no || ('#' + (payment.purchase_invoice_id || '-'));
                document.getElementById('purchase_invoice_label').value = invNo;

                const journalLines = payment.journal_entry?.journal_lines || [];
                const creditLine = journalLines.find((l) => Number(l.credit || 0) > 0) || null;
                if (creditLine?.account_id) {
                    accountSelect.value = String(creditLine.account_id);
                }

                return payment;
            }

            document.getElementById('form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const payload = {
                    payment_no: document.getElementById('payment_no').value,
                    payment_date: document.getElementById('payment_date').value,
                    amount: Number(document.getElementById('amount').value || 0),
                    credit_account_id: Number(accountSelect.value || 0),
                    description: document.getElementById('description').value || null,
                };

                const res = await window.DebugApi.apiFetch(`/api/purchase-payments/${paymentId}`, {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });
                const body = await res.json().catch(() => null);
                if (!res.ok || !body?.data) {
                    const msg = body?.message || `Request failed (${res.status})`;
                    const errors = body?.errors ? JSON.stringify(body.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Purchase payment updated');
                window.location.href = '{{ route('debug.purchase-payments.index') }}';
            });

            loadAccounts()
                .then(() => loadPayment())
                .catch(() => {});
        })();
    </script>
@endpush
