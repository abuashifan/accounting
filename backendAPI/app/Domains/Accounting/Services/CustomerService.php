<?php
// app/Domains/Accounting/Services/CustomerService.php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Customer;
use App\Domains\Accounting\DTOs\CustomerData;

class CustomerService
{
    public function create(CustomerData $data): Customer
    {
        /** @var Customer $customer */
        $customer = Customer::query()->create([
            'code' => $data->code,
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'address' => $data->address,
            'city' => $data->city,
            'province' => $data->province,
            'postal_code' => $data->postal_code,
            'tax_id' => $data->tax_id,
            'credit_limit' => $data->credit_limit,
            'notes' => $data->notes,
            'is_active' => $data->is_active,
        ]);

        return $customer;
    }

    public function update(int $id, CustomerData $data): Customer
    {
        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($id);

        $customer->fill([
            'code' => $data->code,
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'address' => $data->address,
            'city' => $data->city,
            'province' => $data->province,
            'postal_code' => $data->postal_code,
            'tax_id' => $data->tax_id,
            'credit_limit' => $data->credit_limit,
            'notes' => $data->notes,
            'is_active' => $data->is_active,
        ])->save();

        return $customer->fresh();
    }

    public function find(int $id): Customer
    {
        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($id);

        return $customer;
    }

    public function delete(int $id): void
    {
        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($id);
        $customer->delete();
    }
}