<?php

namespace App\Http\Controllers;

use App\Models\SecretKey as ModelsSecretKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class SecretKey extends Controller
{
    public function index(Request $request)
    {
        try {
            $user_id = $request->user_id;

            $secret_keys = ModelsSecretKey::where('user_id', $user_id)->get();

            $data = $secret_keys->map(function ($item) {
                return [
                    'id' => $item->id, // ❌ hash_id dihapus
                    'device_name' => $item->device_name,
                    'key' => $item->key,
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
                'message' => 'Failed to retrieve secret keys: ' . $error->getMessage()
            ], 500);
        }
    }

    public function generate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_name' => [
                    'required',
                    'string',
                    'max:20',
                    'unique:secret_keys,device_name',
                    'regex:/^\S+$/'
                ],
            ], [
                'device_name.required' => 'Device name is required',
                'device_name.string' => 'Device name must be a string',
                'device_name.max' => 'Device name must not exceed 20 characters',
                'device_name.unique' => 'Device name already been taken',
                'device_name.regex' => 'Device name must not contain spaces'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($request->user_id);
            $secretKey = bin2hex(random_bytes(16));

            $emqxResponse = Http::withHeaders([
                'Authorization' => 'Basic ' . env('EMQX_API_KEY_BASE64'),
                'Content-Type' => 'application/json'
            ])->post(env('EMQX_URL') . '/api/v5/authentication/password_based:built_in_database/users', [
                'user_id' => $request->device_name,
                'password' => $secretKey
            ]);

            if (!$emqxResponse->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Device created, but failed to register to EMQX',
                    'emqx_error' => $emqxResponse->json()
                ], 500);
            }

            ModelsSecretKey::create([
                'user_id' => $user->id,
                'key' => $secretKey,
                'device_name' => $request->device_name
            ]);

            return response()->json([
                'status' => 'success',
                'device_name' => $request->device_name,
                'key' => $secretKey,
                'message' => 'Device & MQTT user created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate secret key: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // ❌ unhash_id dihapus, pakai ID asli
            $secretKey = ModelsSecretKey::findOrFail($id);

            $secretKey->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Secret key deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete secret key: ' . $e->getMessage()
            ], 500);
        }
    }

    public function findDeviceById($id)
    {
        try {
            $device = ModelsSecretKey::where('id', $id)->first();

            if (!$device) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No devices found for this user'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $device
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve devices: ' . $e->getMessage()
            ], 500);
        }
    }
}
