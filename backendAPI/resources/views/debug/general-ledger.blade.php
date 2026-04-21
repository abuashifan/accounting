<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug General Ledger</title>
</head>
<body>
    <nav>
        <a href="{{ route('debug.general-ledger') }}">General Ledger</a>
        <a href="{{ route('debug.trial-balance') }}">Trial Balance</a>
    </nav>

    <h1>Debug General Ledger</h1>

    <form method="GET" action="{{ route('debug.general-ledger') }}">
        <label for="account_id">Account</label>
        <select name="account_id" id="account_id">
            <option value="">Select account</option>
            @foreach ($accounts as $account)
                <option value="{{ $account['id'] }}" @selected((string) $filters['account_id'] === (string) $account['id'])>
                    {{ $account['code'] }} - {{ $account['name'] }}
                </option>
            @endforeach
        </select>

        <label for="start_date">Start Date</label>
        <input type="date" id="start_date" name="start_date" value="{{ $filters['start_date'] }}">

        <label for="end_date">End Date</label>
        <input type="date" id="end_date" name="end_date" value="{{ $filters['end_date'] }}">

        <button type="submit">Load</button>
    </form>

    @if ($ledger !== null)
        <h2>{{ $ledger['account']['code'] }} - {{ $ledger['account']['name'] }}</h2>
        <p>Period: {{ $ledger['period']['start_date'] }} to {{ $ledger['period']['end_date'] }}</p>
        <p>Opening Balance: {{ number_format($ledger['opening_balance'], 2) }}</p>
        <p>Total Debit: {{ number_format($ledger['total_debit'], 2) }}</p>
        <p>Total Credit: {{ number_format($ledger['total_credit'], 2) }}</p>
        <p>Closing Balance: {{ number_format($ledger['closing_balance'], 2) }}</p>

        <table border="1" cellpadding="6" cellspacing="0">
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
    @endif
</body>
</html>
