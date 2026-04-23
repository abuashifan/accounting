@extends('debug.layout')

@section('title', 'Create Purchase Payment')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Create Purchase Payment</h1>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.purchase-payments.index') }}">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-info small">
                Pembayaran pembelian membuat jurnal <code>Utang(D) - Kas/Bank(C)</code>.
                Jika <code>journals.auto_post=true</code>, jurnal akan langsung posted.
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
                        <label class="form-label" for="purchase_invoice_id">Purchase Invoice</label>
                        <select class="form-select form-select-sm" id="purchase_invoice_id" required></select>
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

                <div class="mt-3">
                    <button class="btn btn-primary btn-sm" type="submit">Submit</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const invoiceSelect = document.getElementById('purchase_invoice_id');
            const accountSelect = document.getElementById('credit_account_id');

            function escapeHtml(text) {
                return String(text ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            async function loadPurchaseInvoices() {
                // Load first page; enough for debug.
                const res = await window.DebugApi.apiFetch('/api/purchase-invoices?per_page=50');
                const payload = await res.json().catch(() => null);
                const rows = payload?.data?.data || [];
                invoiceSelect.innerHTML = '<option value="">Select purchase invoice</option>';
                for (const inv of rows) {
                    const label = `${inv.invoice_no} (${inv.status}) - outstanding ${(Number(inv.amount) - Number(inv.paid_amount || 0)).toFixed(2)}`;
                    invoiceSelect.insertAdjacentHTML('beforeend', `<option value="${inv.id}">${escapeHtml(label)}</option>`);
                }
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

            document.getElementById('form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const payload = {
                    payment_no: document.getElementById('payment_no').value,
                    purchase_invoice_id: Number(invoiceSelect.value || 0),
                    payment_date: document.getElementById('payment_date').value,
                    amount: Number(document.getElementById('amount').value || 0),
                    credit_account_id: Number(accountSelect.value || 0),
                    description: document.getElementById('description').value || null,
                };

                const res = await window.DebugApi.apiFetch('/api/purchase-payments', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                const body = await res.json().catch(() => null);
                if (!res.ok || !body?.data) {
                    const msg = body?.message || `Request failed (${res.status})`;
                    const errors = body?.errors ? JSON.stringify(body.errors, null, 2) : null;
                    window.DebugApi.showAlert('danger', msg, errors);
                    return;
                }

                window.DebugApi.showAlert('success', 'Purchase payment recorded');
                window.location.href = '{{ route('debug.purchase-payments.index') }}';
            });

            Promise.all([loadPurchaseInvoices(), loadAccounts()]).catch(() => {});
        })();
    </script>
@endpush

