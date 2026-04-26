<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceData extends Model
{
    protected $fillable = [
        'device_id',
        'data_stream_id',
        'value',
        'timestamp',
    ];

    public function device()
    {
        return $this->belongsTo(SecretKey::class);
    }

    public function dataStream()
    {
        return $this->belongsTo(DataStream::class);
    }
}
