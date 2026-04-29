<?php

namespace App\Console\Commands;

use App\Models\SecretKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttSubscribe extends Command
{
    protected $signature = 'mqtt:subscribe';
    protected $description = 'MQTT Subscriber for IoT Gateway';

    public function handle()
    {
        while (true) {
            try {
                $mqtt = new MqttClient(
                    env('MQTT_HOST'),
                    (int) env('MQTT_PORT', 1883),
                    'mqtt-subscriber-' . uniqid()
                );

                $settings = (new ConnectionSettings)
                    ->setUsername(env('MQTT_USERNAME'))
                    ->setPassword(env('MQTT_PASSWORD'))
                    ->setKeepAliveInterval(60);

                $mqtt->connect($settings, true);

                echo "MQTT Connected...\n";

                $mqtt->subscribe('device/#', function ($topic, $message) {

                    $parts = explode('/', $topic);
                    $device_id = $parts[1] ?? null;
                    $type      = $parts[2] ?? null;

                    if (!$device_id || !$type) {
                        echo "INVALID TOPIC: $topic\n";
                        return;
                    }

                    // Normalise type — tolak 'undefined' dari client
                    if (strtolower($type) === 'undefined') {
                        // echo "SKIP: type=undefined for device $device_id\n";
                        return;
                    }

                    // echo "Device: $device_id | Type: $type | Message: $message\n";

                    try {
                        $device = SecretKey::where('device_name', "device/alkha-device-013/V0")->get();
                        if (!$device) {
                            echo "DEVICE NOT FOUND: $device_id\n";
                            return;
                        }

                        echo "Device found: id={$device->id} name={$device->device_name}\n";

                        DB::connection('timescale_remote')->reconnect();

                        DB::connection('timescale_remote')->table('data_points')->insert([
                            'device_id'   => $device->id,
                            'virtual_pin' => $type,
                            'value'       => (string) $message,
                            'data_type'   => is_numeric($message) ? 'integer' : 'string',
                            'tag'         => 'mqtt',
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);

                        echo "DB INSERT OK — device={$device_id} type={$type} value={$message}\n";
                    } catch (\Throwable $e) {
                       Log::error($e->getMessage());
                        echo "  → File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
                    }
                }, 0);

                $mqtt->loop(true);
            } catch (\Throwable $e) {
                Log::error($e->getMessage());
                echo "MQTT ERROR: " . $e->getMessage() . "\n";
                echo "Reconnecting in 5 seconds...\n";
                sleep(5);
            }
        }
    }
}
