<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReminderRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReminderRuleController extends Controller
{
    public function index(Request $request)
    {
        $rules = ReminderRule::orderBy('trigger_type', 'asc')->get();
        return response()->json($rules);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'trigger_type' => ['required', Rule::in(['before_due', 'on_due', 'after_due', 'weekly_overdue'])],
            'offset_days' => 'required|integer|min:0',
            'send_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'channel' => ['required', Rule::in(['whatsapp', 'popup'])],
            'message_template' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $rule = ReminderRule::create($validated);

        return response()->json(['message' => 'Rule created successfully', 'rule' => $rule], 201);
    }

    public function update(Request $request, $id)
    {
        $rule = ReminderRule::findOrFail($id);

        $validated = $request->validate([
            'trigger_type' => ['sometimes', Rule::in(['before_due', 'on_due', 'after_due', 'weekly_overdue'])],
            'offset_days' => 'sometimes|integer|min:0',
            'send_time' => ['sometimes', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'channel' => ['sometimes', Rule::in(['whatsapp', 'popup'])],
            'message_template' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $rule->update($validated);

        return response()->json(['message' => 'Rule updated successfully', 'rule' => $rule]);
    }

    public function destroy($id)
    {
        $rule = ReminderRule::findOrFail($id);
        $rule->delete();

        return response()->json(['message' => 'Rule deleted successfully']);
    }
}
