<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key');
        
        return response()->json([
            'popup_on' => filter_var($settings['popup_on'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'threshold' => $settings['threshold'] ?? 1000,
            'freq' => $settings['freq'] ?? 'every_login',
            'send_time' => $settings['send_time'] ?? '09:00',
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'popup_on' => 'boolean',
            'threshold' => 'numeric',
            'freq' => 'string',
            'send_time' => 'string',
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }
}
