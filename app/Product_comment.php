<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product_comment extends Model
{
    protected $fillable = ['comment','user_id', 'product_id','status'];

    public function Product() {
        return $this->belongsTo('App\Product', 'product_id');
    }

    public function User() {
        return $this->belongsTo('App\User', 'user_id')->select('id','name');
    }

    public function reports() {
        return $this->hasMany('App\CommentReport', 'comment_id');
    }
}
