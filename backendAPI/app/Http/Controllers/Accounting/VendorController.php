<?php
// app/Http/Controllers/Accounting/VendorController.php

namespace App\Http\Controllers\Accounting;

use App\Domains\Accounting\DTOs\VendorData;
use App\Domains\Accounting\Models\Vendor;
use App\Domains\Accounting\Services\VendorService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreVendorRequest;
use App\Http\Requests\Accounting\UpdateVendorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class VendorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendors = Vendor::query()
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
            'data' => $vendors,
            'message' => 'OK',
        ]);
    }

    public function store(StoreVendorRequest $request, VendorService $service): JsonResponse
    {
        $vendor = $service->create(new VendorData(
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
            'data' => $vendor,
            'message' => 'Vendor created',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        /** @var Vendor $vendor */
        $vendor = Vendor::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $vendor,
            'message' => 'OK',
        ]);
    }

    public function update(int $id, UpdateVendorRequest $request, VendorService $service): JsonResponse
    {
        $vendor = $service->update($id, new VendorData(
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
            'data' => $vendor,
            'message' => 'Vendor updated',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            /** @var Vendor $vendor */
            $vendor = Vendor::query()->findOrFail($id);
            $vendor->delete();

            return response()->json([
                'success' => true,
                'data' => ['id' => $id],
                'message' => 'Vendor deleted',
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete vendor (still referenced by other records).',
            ], 422);
        }
    }
}