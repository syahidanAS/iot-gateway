<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VirtualPin extends Model
{
    protected $table = 'virtual_pins';
    protected $fillable = [
        'device_id',
        'pin_name',
    ];

    public function dataStreams()
    {
        return $this->hasOne(DataStream::class);
    }
}
