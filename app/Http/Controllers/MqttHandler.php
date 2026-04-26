<?php

namespace App\Http\Controllers;

use App\Models\SecretKey;
use App\Services\MqttService;
use Illuminate\Http\Request;

class MqttHandler extends Controller
{
    public function publish(MqttService $mqttService, Request $request)
    {
        $device_id = $request->device_id;
        $virtual_pin = $request->virtual_pin;
        $message = $request->message;

        $device = SecretKey::where('device_name', $device_id)->firstOrFail();

        $mqttService->publish(
            $device->device_name,
            $device->key,
            'device/' . $device_id . '/' . $virtual_pin,
            $message
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Message sent',
            'topic' => 'device/' . $device_id . '/' . $virtual_pin
        ]);
    }
}
