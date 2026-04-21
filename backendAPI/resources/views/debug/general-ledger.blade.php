@extends('debug.layout')

@section('title', 'Debug General Ledger')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">General Ledger</h1>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('debug.general-ledger') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="account_id">Account</label>
                    <select class="form-select form-select-sm" name="account_id" id="account_id">
                        <option value="">Select account</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account['id'] }}" @selected((string) $filters['account_id'] === (string) $account['id'])>
                                {{ $account['code'] }} - {{ $account['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="start_date">Start Date</label>
                    <input class="form-control form-control-sm" type="date" id="start_date" name="start_date" value="{{ $filters['start_date'] }}">
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label" for="end_date">End Date</label>
                    <input class="form-control form-control-sm" type="date" id="end_date" name="end_date" value="{{ $filters['end_date'] }}">
                </div>

                <div class="col-12 col-md-1">
                    <button class="btn btn-sm btn-outline-primary w-100" type="submit">Load</button>
                </div>
            </form>
        </div>
    </div>

    @if ($ledger !== null)
        <div class="card mb-3">
            <div class="card-body">
                <div class="fw-semibold">{{ $ledger['account']['code'] }} - {{ $ledger['account']['name'] }}</div>
                <div class="text-muted small">Period: {{ $ledger['period']['start_date'] }} to {{ $ledger['period']['end_date'] }}</div>
                <div class="row g-3 mt-2">
                    <div class="col-12 col-md-3"><div class="text-muted small">Opening</div><div>{{ number_format($ledger['opening_balance'], 2) }}</div></div>
                    <div class="col-12 col-md-3"><div class="text-muted small">Total Debit</div><div>{{ number_format($ledger['total_debit'], 2) }}</div></div>
                    <div class="col-12 col-md-3"><div class="text-muted small">Total Credit</div><div>{{ number_format($ledger['total_credit'], 2) }}</div></div>
                    <div class="col-12 col-md-3"><div class="text-muted small">Closing</div><div>{{ number_format($ledger['closing_balance'], 2) }}</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Journal No</th>
                            <th>Description</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ledger['entries'] as $entry)
                            <tr>
                                <td>{{ $entry['date'] }}</td>
                                <td>{{ $entry['journal_no'] }}</td>
                                <td>{{ $entry['description'] }}</td>
                                <td>{{ number_format($entry['debit'], 2) }}</td>
                                <td>{{ number_format($entry['credit'], 2) }}</td>
                                <td>{{ number_format($entry['running_balance'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
