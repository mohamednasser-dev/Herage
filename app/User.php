<?php

namespace App;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'phone_verified_at',
        'password',
        'fcm_token',
        'verified',
        'remember_token',
        'active',
        'seen',
        'free_ads_count',
        'paid_ads_count',
        'my_wallet',
        'free_balance',
        'payed_balance',
        'watsapp',
        'cover',
        'about_user',
        'city_id',
        'area_id',
        'account_type',
        'image'
      ];
    use Notifiable;

    protected $casts = [
        'created_at' => 'datetime:Y-m-d',
    ];

    // Rest omitted for brevity

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function products() {
        return $this->hasMany('App\Product', 'user_id');
    }

    public function City() {
        return $this->belongsTo('App\City', 'city_id');
    }
    public function Area() {
        return $this->belongsTo('App\Area', 'area_id');
    }

    public function Account_type() {
        return $this->belongsTo('App\Account_type', 'account_type')->select('id','name_'.session('api_lang').' as name','type');
    }
}
