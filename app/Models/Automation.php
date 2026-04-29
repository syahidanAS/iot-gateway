<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Automation extends Model
{
    protected $table = "automations";

    protected $fillable = [
        'user_id',
        'device_id',
        'name',
        'is_active',
    ];

    public function conditions()
    {
        return $this->hasMany(AutomationCondition::class);
    }

    public function actions()
    {
        return $this->hasMany(AutomationAction::class);
    }
}
