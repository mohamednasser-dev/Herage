<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Account_type extends Model
{
    protected $fillable = ['name_ar','name_en','type','deleted'];
}
