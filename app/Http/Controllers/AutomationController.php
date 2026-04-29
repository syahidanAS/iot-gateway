<?php

namespace App\Http\Controllers;

use App\Models\SecretKey;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
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
