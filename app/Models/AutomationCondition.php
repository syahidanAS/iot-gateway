<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationCondition extends Model
{
    protected $table = 'automation_conditions';

    protected $fillable = [
        'automation_id',
        'type',
        'sensor_tag',
        'operator',
        'value',
        'time'
    ];
}
