<?php
// app/Http/Controllers/Accounting/CustomerController.php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\DTOs\CustomerData;
use App\Domains\Accounting\Models\Customer;
use App\Domains\Accounting\Services\CustomerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreCustomerRequest;
use App\Http\Requests\Accounting\UpdateCustomerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->when(
                ! $request->boolean('include_inactive', false),
                fn ($q) => $q->where('is_active', true),
            )
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $customers,
            'message' => 'OK',
        ]);
    }

    public function store(StoreCustomerRequest $request, CustomerService $service): JsonResponse
    {
        $customer = $service->create(new CustomerData(
            code: (string) $request->string('code'),
            name: (string) $request->string('name'),
            email: $request->input('email'),
            phone: $request->input('phone'),
            address: $request->input('address'),
            city: $request->input('city'),
            province: $request->input('province'),
            postal_code: $request->input('postal_code'),
            tax_id: $request->input('tax_id'),
            credit_limit: (float) ($request->input('credit_limit') ?? 0),
            notes: $request->input('notes'),
            is_active: (bool) ($request->input('is_active', true)),
        ));

        return response()->json([
            'success' => true,
            'data' => $customer,
            'message' => 'Customer created',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $customer,
            'message' => 'OK',
        ]);
    }

    public function update(int $id, UpdateCustomerRequest $request, CustomerService $service): JsonResponse
    {
        $customer = $service->update($id, new CustomerData(
            code: (string) $request->string('code'),
            name: (string) $request->string('name'),
            email: $request->input('email'),
            phone: $request->input('phone'),
            address: $request->input('address'),
            city: $request->input('city'),
            province: $request->input('province'),
            postal_code: $request->input('postal_code'),
            tax_id: $request->input('tax_id'),
            credit_limit: (float) ($request->input('credit_limit') ?? 0),
            notes: $request->input('notes'),
            is_active: (bool) ($request->input('is_active', true)),
        ));

        return response()->json([
            'success' => true,
            'data' => $customer,
            'message' => 'Customer updated',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            /** @var Customer $customer */
            $customer = Customer::query()->findOrFail($id);
            $customer->delete();

            return response()->json([
                'success' => true,
                'data' => ['id' => $id],
                'message' => 'Customer deleted',
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete customer (still referenced by other records).',
            ], 422);
        }
    }
}