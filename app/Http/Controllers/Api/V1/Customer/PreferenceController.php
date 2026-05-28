<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    /**
     * Get the authenticated customer's current preferences.
     */
    public function show(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['message' => 'No customer profile found.'], 404);
        }

        return response()->json([
            'preferred_bill_format' => $customer->preferred_bill_format,
        ]);
    }

    /**
     * Update the authenticated customer's bill format preference.
     */
    public function update(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['message' => 'No customer profile found.'], 404);
        }

        $validated = $request->validate([
            'preferred_bill_format' => ['required', Rule::in(['csv', 'excel', 'pdf'])],
        ]);

        $customer->update([
            'preferred_bill_format' => $validated['preferred_bill_format'],
        ]);

        return response()->json([
            'message' => 'Preference updated successfully.',
            'preferred_bill_format' => $customer->preferred_bill_format,
        ]);
    }
}
