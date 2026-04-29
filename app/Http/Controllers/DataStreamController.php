<?php

namespace App\Http\Controllers;

use App\Models\DataStream;
use App\Models\SecretKey;
use App\Models\VirtualPin;
use App\Services\InfluxDB2Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class DataStreamController extends Controller
{

    // =========================
    // INDEX (metadata PostgreSQL)
    // =========================
    public function index(Request $request)
    {
        try {
            $virtual_pins = SecretKey::where('id', $request->device_id)
                ->with('virtualPin.dataStreams')
                ->firstOrFail();

            return response()->json([
                'status' => 'success',
                'data' => $virtual_pins
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // =========================
    // CREATE DATA STREAM (POSTGRES ONLY)
    // =========================
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:secret_keys,id',
            'virtual_pin_id' => 'required|exists:virtual_pins,id',
            'name' => 'required|string|max:255',
            'data_type' => 'required|in:string,integer,float,double',
            'min_value' => 'nullable|numeric',
            'max_value' => 'nullable|numeric',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $pin = VirtualPin::where('id', $request->virtual_pin_id)
                ->where('device_id', $request->device_id)
                ->first();

            if (!$pin) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Virtual pin tidak valid'
                ], 403);
            }

            $dataStream = DataStream::create([
                'virtual_pin_id' => $request->virtual_pin_id,
                'name' => $request->name,
                'data_type' => $request->data_type,
                'min_value' => $request->min_value,
                'max_value' => $request->max_value,
                'description' => $request->description,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => $dataStream
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // =========================
    // WRITE STATE → INFLUXDB
    // =========================
    public function mutateState(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_name' => 'required',
            'secret_key' => 'required',
            'virtual_pin' => 'required',
            'value' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $device = SecretKey::where('key', $request->secret_key)
                ->where('device_name', $request->device_name)
                ->first();

            if (!$device) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid device'
                ], 401);
            }

            $virtualPin = VirtualPin::where('device_id', $device->id)
                ->where('pin_name', $request->virtual_pin)
                ->first();

            if (!$virtualPin) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Virtual pin not found'
                ], 404);
            }

            $dataStream = DataStream::where('virtual_pin_id', $virtualPin->id)->first();

            if (!$dataStream) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data stream not found'
                ], 404);
            }

            DB::connection('timescale_remote')->table('data_points')->insert([
                'device_id' => $device->id,
                'virtual_pin' => $virtualPin->pin_name,
                'value' => $request->value,
                'data_type' => $dataStream->data_type,
                'tag' => $dataStream->tag,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {

                $mqtt = new MqttClient(
                    env('MQTT_HOST'),
                    env('MQTT_PORT'),
                    'laravel-publisher-' . uniqid()
                );

                $settings = (new ConnectionSettings)
                    ->setUsername($device->device_name) // basic auth username
                    ->setPassword($device->key)         // secret key
                    ->setConnectTimeout(3);

                $mqtt->connect($settings, true);

                $topic = "device/{$device->device_name}/{$virtualPin->pin_name}";

                $payload = $request->value;

                $mqtt->publish($topic, $payload, 0);

                $mqtt->disconnect();
            } catch (\Throwable $e) {
                logger()->error("MQTT publish error: " . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'State changed successfully',
                'topic' => $topic,
                'payload' => $payload
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getDeviceStates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            // =========================
            // 1. GET DEVICE + PIN
            // =========================
            $device = SecretKey::where('id', $request->device_id)
                ->with('virtualPin.dataStreams')
                ->first();

            if (!$device) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Device not found'
                ], 404);
            }

            // =========================
            // 2. LATEST STATE FROM TIMESCALE
            // =========================
            $latest = DB::connection('timescale_remote')
                ->select("
                SELECT DISTINCT ON (virtual_pin)
                    virtual_pin,
                    value,
                    data_type,
                    created_at
                FROM data_points
                WHERE device_id = ?
                ORDER BY virtual_pin, created_at DESC
            ", [$request->device_id]);

            $latestMap = collect($latest)->keyBy('virtual_pin');

            // =========================
            // 3. MERGE RESPONSE
            // =========================
            $result = collect($device->virtualPin)->map(function ($pin) use ($latestMap) {

                $state = $latestMap[$pin->pin_name] ?? null;

                $value = $state->value ?? null;
                $dataType = $state->data_type ?? null;

                return [
                    'pin_name' => $pin->pin_name,
                    'data_streams' => $pin->dataStreams,
                    'state' => $this->formatState($value, $dataType),
                    'raw_value' => $value,
                    'updated_at' => $state->created_at ?? null,
                ];
            });

            // =========================
            // 4. RESPONSE CLEAN
            // =========================
            return response()->json([
                'status' => 'success',
                'data' => [
                    'device_id' => $device->id,
                    'device_name' => $device->device_name,
                    'key' => $device->key,
                    'virtual_pin' => $result
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    private function formatState($value, $type)
    {
        if ($type === 'boolean') {
            return ((int)$value === 1) ? 'ON' : 'OFF';
        }

        if ($type === 'integer' || $type === 'float') {
            return $value;
        }

        return $value;
    }
}
