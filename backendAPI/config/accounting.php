<?php

return [
    'auto_journal' => [
        'accounts' => [
            'cash' => env('ACCOUNTING_CASH_ACCOUNT_CODE', '1000'),
            'accounts_receivable' => env('ACCOUNTING_AR_ACCOUNT_CODE', '1200'),
            'accounts_payable' => env('ACCOUNTING_AP_ACCOUNT_CODE', '2100'),
            'revenue' => env('ACCOUNTING_REVENUE_ACCOUNT_CODE', '4000'),
        ],
    ],
];
