<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecretKey extends Model
{
    protected $table = 'secret_keys';
    protected $fillable = ['user_id', 'key', 'device_name'];

    public function virtualPin()
    {
        return $this->hasMany(VirtualPin::class, 'device_id', 'id');
    }
}
