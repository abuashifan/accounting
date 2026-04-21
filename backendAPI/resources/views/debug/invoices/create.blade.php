@extends('debug.layout')

@section('title', 'Create Invoice')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Create Invoice</h1>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('debug.invoices.index') }}">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-warning small">
                Invoice posting uses configured accounts (see <code>config/accounting.php</code>).
            </div>

            <form id="invoiceForm">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="invoice_no">Invoice No</label>
                        <input class="form-control form-control-sm" id="invoice_no" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="invoice_date">Invoice Date</label>
                        <input class="form-control form-control-sm" type="date" id="invoice_date" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="amount">Amount</label>
                        <input class="form-control form-control-sm" type="number" step="0.01" min="0" id="amount" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description (optional)</label>
                        <input class="form-control form-control-sm" id="description">
                    </div>
                </div>

                <div class="mt-3">
                    <button class="btn btn-primary btn-sm" type="submit">Submit → InvoiceService</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            document.getElementById('invoiceForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const payload = {
                    invoice_no: document.getElementById('invoice_no').value,
                    invoice_date: document.getElementById('invoice_date').value,
                    amount: Number(document.getElementById('amount').value),
                    description: document.getElementById('description').value || null,
                };

                const res = await window.DebugApi.apiJson('{{ route('debug.api.invoices.store') }}', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });

                if (res?.success) {
                    window.DebugApi.showAlert('success', 'Invoice created');
                    window.location.href = '{{ route('debug.invoices.index') }}';
                }
            });
        })();
    </script>
@endpush
