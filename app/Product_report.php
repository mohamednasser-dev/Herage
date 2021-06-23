<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product_report extends Model
{
    protected $fillable = ['user_id', 'product_id','status'];

    public function Product() {
        return $this->belongsTo('App\Product', 'product_id');
    }

    public function User() {
        return $this->belongsTo('App\User', 'user_id');
    }
}
