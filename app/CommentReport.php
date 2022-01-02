<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CommentReport extends Model
{
    protected $fillable = ['comment_id', 'product_id', 'report', 'user_id'];

    public function user() {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function comment() {
        return $this->belongsTo('App\Product_comment', 'comment_id');
    }

    public function product() {
        return $this->belongsTo('App\Product', 'product_id');
    }
}
