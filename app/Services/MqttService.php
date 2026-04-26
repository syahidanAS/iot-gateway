<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttService
{
    public function connect($username, $password)
    {
        $server   = env('MQTT_HOST');
        $port     = env('MQTT_PORT');
        $clientId = 'laravel-client-' . uniqid();

        $mqtt = new MqttClient($server, $port, $clientId);

        $settings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password);

        $mqtt->connect($settings, true);

        return $mqtt;
    }

    public function publish($username, $password, $topic, $message)
    {
        $mqtt = $this->connect($username, $password);

        $mqtt->publish($topic, $message, 0);

        $mqtt->disconnect();
    }
}
