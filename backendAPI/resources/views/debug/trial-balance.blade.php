<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Trial Balance</title>
</head>
<body>
    <nav>
        <a href="{{ route('debug.general-ledger') }}">General Ledger</a>
        <a href="{{ route('debug.trial-balance') }}">Trial Balance</a>
    </nav>

    <h1>Debug Trial Balance</h1>

    <form method="GET" action="{{ route('debug.trial-balance') }}">
        <label for="start_date">Start Date</label>
        <input type="date" id="start_date" name="start_date" value="{{ $filters['start_date'] }}">

        <label for="end_date">End Date</label>
        <input type="date" id="end_date" name="end_date" value="{{ $filters['end_date'] }}">

        <button type="submit">Load</button>
    </form>

    @if ($trialBalance !== null)
        <p>Period: {{ $trialBalance['period']['start_date'] }} to {{ $trialBalance['period']['end_date'] }}</p>
        <p>Total Debit: {{ number_format($trialBalance['total_debit'], 2) }}</p>
        <p>Total Credit: {{ number_format($trialBalance['total_credit'], 2) }}</p>
        <p>Balanced: {{ $trialBalance['is_balanced'] ? 'Yes' : 'No' }}</p>

        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($trialBalance['accounts'] as $account)
                    <tr>
                        <td>{{ $account['account_code'] }}</td>
                        <td>{{ $account['account_name'] }}</td>
                        <td>{{ $account['account_type'] }}</td>
                        <td>{{ number_format($account['total_debit'], 2) }}</td>
                        <td>{{ number_format($account['total_credit'], 2) }}</td>
                        <td>{{ number_format($account['balance'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
