<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product_view extends Model
{
    protected $fillable = ['ip', 'product_id','user_id'];

    public function User() {
        return $this->belongsTo('App\User', 'user_id');
    }
    public function Product() {
        return $this->belongsTo('App\Product', 'product_id')->with('Publisher')->select('id','title','main_image','price','description','user_id','created_at','city_id','area_id');
    }
}
