<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\LoginLog;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'username' => 'required|string|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'customer_code' => 'nullable|string|max:255',
            'password' => 'required|string|min:8',
            'role' => 'in:admin,customer,marketing_company',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'customer_code' => $validated['customer_code'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'customer',
            'status' => 'pending', // Requires admin approval based on schema rules
        ]);

        return response()->json([
            'message' => 'Registration successful. Waiting for admin approval.',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->login)
                    ->orWhere('username', $request->login)
                    ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            LoginLog::create([
                'email' => $request->login,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
            ]);
            return response()->json(['message' => 'Invalid username or password'], 401);
        }

        if ($user->status !== 'active') {
            LoginLog::create([
                'email' => $request->login,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
            ]);
            return response()->json(['message' => 'Your account is awaiting admin approval'], 403);
        }

        LoginLog::create([
            'email' => $request->login,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'success',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token'        => $token,
            'token_type'   => 'Bearer',
            'user'         => $user->load('customer'),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('customer'));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}
