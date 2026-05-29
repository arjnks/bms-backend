<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use Illuminate\Http\Request;

class LoginLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = LoginLog::orderBy('created_at', 'desc')->paginate(50);
        return response()->json($logs);
    }
}
