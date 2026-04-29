<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationAction;
use App\Models\AutomationCondition;
use App\Models\SecretKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutomationController extends Controller
{
    public function index(Request $request)
    {
        $automations = Automation::with(['conditions', 'actions'])
            ->where('user_id', $request->id)
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $automations
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'device_id' => 'required|integer',
            'name' => 'required|string',

            'conditions' => 'required|array|min:1',
            'actions' => 'required|array|min:1',
        ]);

        DB::beginTransaction();

        try {

            // CREATE AUTOMATION
            $automation = Automation::create([
                'user_id' => $request->id,
                'device_id' => $request->device_id,
                'name' => $request->name,
                'is_active' => true,
            ]);

            // =========================
            // CONDITIONS (IF)
            // =========================
            foreach ($request->conditions as $condition) {

                AutomationCondition::create([
                    'automation_id' => $automation->id,
                    'type' => $condition['type'], // sensor / time

                    'sensor_tag' => $condition['sensor_tag'] ?? null,
                    'operator' => $condition['operator'] ?? null,
                    'value' => $condition['value'] ?? null,
                    'time' => $condition['time'] ?? null,
                ]);
            }

            // =========================
            // ACTIONS (THEN)
            // =========================
            foreach ($request->actions as $action) {

                AutomationAction::create([
                    'automation_id' => $automation->id,
                    'target_pin' => $action['target_pin'],
                    'value' => $action['value'],
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Automation created successfully',
                'data' => $automation->load(['conditions', 'actions'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // =========================
    // SHOW SINGLE AUTOMATION
    // =========================
    public function show($id, Request $request)
    {
        $automation = Automation::with(['conditions', 'actions'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $automation
        ]);
    }

    // =========================
    // UPDATE AUTOMATION
    // =========================
    public function update(Request $request, $id)
    {
        $automation = Automation::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $request->validate([
            'name' => 'required|string',
            'is_active' => 'boolean'
        ]);

        $automation->update([
            'name' => $request->name,
            'is_active' => $request->is_active ?? $automation->is_active
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Automation updated'
        ]);
    }

    // =========================
    // DELETE AUTOMATION
    // =========================
    public function destroy($id, Request $request)
    {
        $automation = Automation::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $automation->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Automation deleted'
        ]);
    }

    public function populateDeviceInfo(Request $request)
    {
        $user_id = $request->user_id;

        try {
            $devices = SecretKey::with(['virtualPin.dataStreams'])->where('user_id', $user_id)->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Device information populated successfully.',
                'data' => $devices,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while populating device information.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
