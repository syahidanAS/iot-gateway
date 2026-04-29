<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
            env('MQTT_CLIENT_ID', 'mqtt-subscriber')
        );

        $settings = (new ConnectionSettings)
            ->setUsername(env('MQTT_USERNAME'))
            ->setPassword(env('MQTT_PASSWORD'));

        $mqtt->connect($settings, true);

        $mqtt->subscribe('device/#', function ($topic, $message) {

            $parts = explode('/', $topic);

            $device_id = $parts[1] ?? null;
            $type = $parts[2] ?? null;


            DB::connection('timescale_remote')->table('data_points')->insert([
                'device_id' => $device_id,
                'virtual_pin' => $type,
                'value' => $message,
                'data_type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 0);

        $mqtt->loop(true);
    }
}
