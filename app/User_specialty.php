<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class User_specialty extends Model
{
    protected $fillable = ['user_id','special_id'];


    public function Specialty() {
        $api_lang = session('api_lang');
        return $this->belongsTo('App\specialty', 'special_id')->select('id','name_'.$api_lang.' as name');
    }
}
