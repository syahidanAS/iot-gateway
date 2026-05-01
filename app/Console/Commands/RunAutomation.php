<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Automation;
use App\Models\SecretKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class RunAutomation extends Command
{
    protected $signature = 'automation:run';
    protected $description = 'Run automation rules';

    public function handle()
    {
        $this->info("=== RUN AUTOMATION ===");

        try {
            $automations = Automation::with(['conditions', 'actions'])
                ->where('is_active', true)
                ->get();

            foreach ($automations as $automation) {

                $this->info("Checking: {$automation->name}");

                $isTriggered = true;

                foreach ($automation->conditions as $condition) {

                    $result = false;

                    // =====================
                    // SENSOR CONDITION
                    // =====================
                    if ($condition->type === 'sensor') {

                        $latest = DB::connection('timescale_remote')
                            ->table('data_points')
                            ->where('device_id', $automation->device_id)
                            ->where('virtual_pin', $condition->sensor_tag)
                            ->orderByDesc('created_at')
                            ->first();

                        if (!$latest) {
                            $this->warn("No data for sensor {$condition->sensor_tag}");
                            $isTriggered = false;
                            break;
                        }

                        $value = (float) $latest->value;
                        $target = (float) $condition->value;

                        switch ($condition->operator) {
                            case '>':
                                $result = $value > $target;
                                break;
                            case '<':
                                $result = $value < $target;
                                break;
                            case '=':
                                $result = $value == $target;
                                break;
                        }

                        $this->info("Sensor check: {$value} {$condition->operator} {$target} => " . ($result ? 'TRUE' : 'FALSE'));
                    }

                    // =====================
                    // TIME CONDITION
                    // =====================
                    if ($condition->type === 'time') {

                        $now = Carbon::now();
                        $target = Carbon::parse($condition->time);
                        $result = $now->format('H:i') === $target->format('H:i');

                        $this->info("Time check: now={$now->format('H:i')} target={$condition->time} => " . ($result ? 'TRUE' : 'FALSE'));
                    }

                    if (!$result) {
                        $isTriggered = false;
                        break;
                    }
                }

                // =====================
                // EXECUTE ACTION
                // =====================
                if ($isTriggered) {

                    $this->info(">>> TRIGGERED: {$automation->name}");

                    $device = SecretKey::where('id', $automation->device_id)->first();

                    if (!$device) {
                        $this->error("Device not found: {$automation->device_id}");
                        continue;
                    }

                    foreach ($automation->actions as $action) {

                        $this->publishMqtt(
                            $device->device_name,
                            $action->target_pin,
                            $action->value
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            $this->error("ERROR: " . $e->getMessage());
        }

        Log::info('Automation executed', [
            'time' => now()->toDateTimeString()
        ]);

        $this->info("=== DONE ===");
    }

    // =====================
    // MQTT PUBLISH
    // =====================
    private function publishMqtt($deviceName, $pin, $value)
    {
        try {
            $mqtt = new MqttClient(
                env('MQTT_HOST'),
                (int) env('MQTT_PORT', 1883),
                'mqtt-publisher-' . uniqid()
            );

            $settings = (new ConnectionSettings)
                ->setUsername(env('MQTT_USERNAME'))
                ->setPassword(env('MQTT_PASSWORD'))
                ->setKeepAliveInterval(60);

            $mqtt->connect($settings, true);

            $topic = "device/{$deviceName}/{$pin}";

            $mqtt->publish($topic, (string) $value, 0);

            $mqtt->disconnect();

            $this->info("Publish: {$topic} => {$value}");
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            $this->error("MQTT ERROR: " . $e->getMessage());
        }
    }
}
