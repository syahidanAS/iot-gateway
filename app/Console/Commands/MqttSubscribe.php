<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttSubscribe extends Command
{
    protected $signature = 'mqtt:subscribe';

    protected $description = 'Command description';

    public function handle()
    {
        $mqtt = new MqttClient(
            env('MQTT_HOST'),
            env('MQTT_PORT'),
            'laravel-subscriber'
        );

        $settings = (new ConnectionSettings)
            ->setUsername(env('MQTT_USERNAME'))
            ->setPassword(env('MQTT_PASSWORD'));

        $mqtt->connect($settings, true);

        $mqtt->subscribe('device/#', function ($topic, $message) {

            $parts = explode('/', $topic);

            $device_id = $parts[1] ?? null;
            $type = $parts[2] ?? null;

            echo "Device: $device_id | Type: $type | Message: $message\n";

            // contoh simpan ke DB
            // ModelsData::create([
            //     'device_id' => $device_id,
            //     'type' => $type,
            //     'message' => $message
            // ]);

        }, 0);

        $mqtt->loop(true);
    }
}
