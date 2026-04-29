<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationAction extends Model
{
    protected $table = 'automation_actions';

    protected $fillable = [
        'automation_id',
        'target_pin',
        'value',
    ];
}
