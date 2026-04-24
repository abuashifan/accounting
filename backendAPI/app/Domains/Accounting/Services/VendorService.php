<?php
// app/Domains/Accounting/Services/VendorService.php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Vendor;
use App\Domains\Accounting\DTOs\VendorData;

class VendorService
{
    public function create(VendorData $data): Vendor
    {
        /** @var Vendor $vendor */
        $vendor = Vendor::query()->create([
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

        return $vendor;
    }

    public function update(int $id, VendorData $data): Vendor
    {
        /** @var Vendor $vendor */
        $vendor = Vendor::query()->findOrFail($id);

        $vendor->fill([
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

        return $vendor->fresh();
    }

    public function find(int $id): Vendor
    {
        /** @var Vendor $vendor */
        $vendor = Vendor::query()->findOrFail($id);

        return $vendor;
    }

    public function delete(int $id): void
    {
        /** @var Vendor $vendor */
        $vendor = Vendor::query()->findOrFail($id);
        $vendor->delete();
    }
}