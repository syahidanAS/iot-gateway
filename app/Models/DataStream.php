<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataStream extends Model
{
    protected $fillable = [
        'virtual_pin_id',
        'name',
        'data_type',
        'min_value',
        'max_value',
        'description',
        'tag',
    ];

    public function virtualPin()
    {
        return $this->belongsTo(VirtualPin::class);
    }

    public function deviceData()
    {
        return $this->hasOne(DeviceData::class);
    }
}
