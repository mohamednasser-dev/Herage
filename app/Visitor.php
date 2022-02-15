<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $fillable = ['unique_id', 'fcm_token','type','user_id', 'city_id', 'area_id'];

    public function user() {
        return $this->belongsTo('App\User', 'user_id');
    }
}
