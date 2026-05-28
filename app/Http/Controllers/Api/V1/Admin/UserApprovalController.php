<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class UserApprovalController extends Controller
{
    protected WhatsAppService $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    public function pending(Request $request)
    {
        $users = User::where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->select('id', 'name', 'username', 'email', 'phone', 'role', 'created_at')
            ->get();
            
        return response()->json($users);
    }

    public function approve(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        if ($user->status !== 'pending') {
            return response()->json(['message' => 'User is not pending approval'], 400);
        }

        $user->update(['status' => 'active']);

        if ($user->role === 'customer') {
            \App\Models\Customer::create([
                'user_id' => $user->id,
                'customer_code' => $user->customer_code ?: 'CUST-' . strtoupper(substr(uniqid(), -5)),
            ]);
        }

        if ($user->phone) {
            $this->whatsapp->send($user->phone, "Hi {$user->name}, your Leo Group portal account has been approved. Login here: " . url('/login'));
        }

        return response()->json(['message' => 'User approved successfully', 'user' => $user]);
    }

    public function reject(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->status !== 'pending') {
            return response()->json(['message' => 'User is not pending approval'], 400);
        }

        $user->update(['status' => 'rejected']);

        if ($user->phone) {
            $this->whatsapp->send($user->phone, "Hi {$user->name}, your Leo Group portal registration was not approved. Contact admin for details.");
        }

        return response()->json(['message' => 'User rejected successfully', 'user' => $user]);
    }
}
