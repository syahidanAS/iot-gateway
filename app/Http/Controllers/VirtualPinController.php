<?php

namespace App\Http\Controllers;

use App\Models\SecretKey;
use App\Models\VirtualPin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VirtualPinController extends Controller
{
    public function index($device_id)
    {
        try {
            $virtual_pins = VirtualPin::where('device_id', $device_id)->get();

            $data = $virtual_pins->map(function ($item) {
                return [
                    'id' => $item->id, // ❌ hash_id dihapus
                    'device_id' => $item->device_id, // ❌ hash_id dihapus
                    'pin_name' => $item->pin_name,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $data
            ], 200);
        } catch (\Exception $error) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve virtual pins: ' . $error->getMessage()
            ], 500);
        }
    }

    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => ['required'],
                'pin_name' => [
                    'required',
                    'string',
                    'max:3',
                    'unique:virtual_pins,pin_name,NULL,id,device_id,' . $request->device_id
                ]
            ], [
                'device_id.required' => 'Device ID is required',
                'pin_name.required' => 'Pin name is required',
                'pin_name.string' => 'Pin name must be a string',
                'pin_name.max' => 'Pin name must not exceed 3 characters',
                'pin_name.unique' => 'Pin name already exists for the specified device'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $device_id = $request->device_id;

            if (!SecretKey::where('id', $device_id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Device not found'
                ], 404);
            }

            $virtualPin = VirtualPin::create([
                'device_id' => $device_id,
                'pin_name' => $request->pin_name,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $virtualPin
            ], 201);
        } catch (\Exception $error) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create virtual pin: ' . $error->getMessage()
            ], 500);
        }
    }

    public function find($device_id)
    {
        try {
            $virtual_pins = SecretKey::findOrFail($device_id)
                ->virtualPin()
                ->get();

            if ($virtual_pins->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Virtual pins not found for the specified device'
                ], 404);
            }

            $data = $virtual_pins->map(function ($item) {
                return [
                    'id' => $item->id, // ❌ hash_id dihapus
                    'device_id' => $item->device_id, // ❌ hash_id dihapus
                    'pin_name' => $item->pin_name,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $data
            ], 200);
        } catch (\Exception $error) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve virtual pins: ' . $error->getMessage()
            ], 500);
        }
    }
}
