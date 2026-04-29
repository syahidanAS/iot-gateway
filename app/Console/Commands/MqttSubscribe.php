<?php

namespace App\Console\Commands;

use App\Models\SecretKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
                    env('MQTT_PORT'),
                    env('MQTT_CLIENT_ID', 'mqtt-subscriber-' . uniqid())
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
                    $type = $parts[2] ?? null;

                    if (!$device_id || !$type) {
                        echo "INVALID TOPIC: $topic\n";
                        return;
                    }

                    echo "Device: $device_id | Type: $type | Message: $message\n";

                    try {

                        $device = SecretKey::where('device_name', $device_id)->first();

                        if (!$device) {
                            echo "DEVICE NOT FOUND: $device_id\n";
                            return;
                        }

                        DB::connection('timescale_remote')->table('data_points')->insert([
                            'device_id'   => $device->id,
                            'virtual_pin' => $type,
                            'value'       => is_numeric($message) ? $message : json_encode($message),
                            'data_type'   => is_numeric($message) ? 'integer' : 'string',
                            'tag'         => 'mqtt', // optional
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);

                        echo "DB INSERT OK\n";
                    } catch (\Throwable $e) {
                        echo "DB ERROR: " . $e->getMessage() . "\n";
                    }
                }, 0);

                $mqtt->loop(true);
            } catch (\Throwable $e) {
                echo "MQTT ERROR: " . $e->getMessage() . "\n";
                echo "Reconnecting in 5 seconds...\n";
                sleep(5);
            }
        }
    }
}
