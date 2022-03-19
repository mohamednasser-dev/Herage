<?php

namespace App\Http\Controllers;

use App\Account_type;
use App\Participant;
use App\specialty;
use App\User_specialty;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use JD\Cloudder\Facades\Cloudder;
use Illuminate\Http\Request;
use App\Helpers\APIHelpers;
use App\WalletTransaction;
use App\UserNotification;
use App\Balance_package;
use App\Notification;
use App\ProductImage;
use App\Category;
use App\Favorite;
use App\Setting;
use App\Product;
use App\User;
use App\Visitor;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['get_account_types', 'pay_sucess', 'pay_error', 'excute_pay', 'my_account', 'my_balance', 'resetforgettenpassword', 'checkphoneexistance', 'checkphoneexistanceandroid', 'getownerprofile']]);
        //        --------------------------------------------- begin scheduled functions --------------------------------------------------------
        $expired = Product::where('status', 1)->whereDate('expiry_date', '<', Carbon::now())->get();
        foreach ($expired as $row) {
            $product = Product::find($row->id);
            $product->status = 2;
            $product->re_post = '0';
            $product->save();
        }
        //        --------------------------------------------- end scheduled functions --------------------------------------------------------
    }

    public function select_my_data(Request $request)
    {
        $user = auth()->user();
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        $data = User::with('City')->with('Area')->with('Account_type')
            ->where('id', $user->id)
            ->select('id', 'name', 'email', 'about_user', 'image', 'cover', 'phone', 'watsapp', 'city_id', 'area_id', 'account_type', 'created_at')
            ->first();
        $spec = "";
        $user_specialties = User_specialty::with('Specialty')
            ->select('special_id')
            ->where('user_id', $user->id)->get();

        foreach ($user_specialties as $row) {
            if ($lang == 'ar') {
                $spec = $spec . ',' . $row->Specialty->name;
            } else {
                $spec = $spec . ',' . $row->Specialty->name;
            }
        }

        $data->specialties = $spec;
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $lang);
        return response()->json($response, 200);
    }

    public function get_account_types(Request $request)
    {
        $lang = $request->lang;
        $user = auth()->user();
        Session::put('api_lang', $lang);
        $data = Account_type::where('type', 'commercial')
            ->select('id', 'name_' . $lang . ' as name')
            ->get()->map(function ($data) use ($user) {
                if ($user) {
                    if ($user->account_type == $data->id) {
                        $data->is_selected = true;
                    } else {
                        $data->is_selected = false;
                    }
                } else {
                    $data->is_selected = false;
                }
                return $data;
            });
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $lang);
        return response()->json($response, 200);
    }

    public function retweet(Request $request, $ad_id)
    {

        $lang = $request->lang;
        Session::put('api_lang', $lang);

        $data = Product::find($ad_id);
        if ($data) {
            $retweetDate = new Carbon($data['retweet_date']);
            
            if ((!$retweetDate->isToday()) && ($retweetDate->addDay() >= $data['retweet_date'])) {
                //create expier day
                $settings = Setting::find(1);
                $mytime = Carbon::now();
                $today = Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');
                $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                $final_expire_pin_date = $final_pin_date->addDays($settings->expier_days);

                $data->expiry_date = $final_expire_pin_date;
                $data->created_at = $mytime;
                $data->status = 1;
                $data->retweet = 1;
                $data->retweet_date = Carbon::now();
                $data->save();

                $response = APIHelpers::createApiResponse(false, 200, 'retweet used successfully', 'تم استخدام الريتويت بنجاح', $data, $lang);
                return response()->json($response, 200);
            } else {
                $response = APIHelpers::createApiResponse(true, 406, 'It is not possible to retweet the advertisement before 24 hours have passed', 'لا يمكن عمل إعادة نشر للإعلان قبل مرور 24 ساعة', (object)[], $request->lang);
                return response()->json($response, 406);
            }

        } else {
            $response = APIHelpers::createApiResponse(true, 406, 'you should choose valid ad', 'يجب اختيار اعلان صحيح', (object)[], $request->lang);
            return response()->json($response, 406);
        }

    }

    public function get_specialties(Request $request)
    {
        $lang = $request->lang;
        $user = auth()->user();
        Session::put('api_lang', $lang);
        $data = specialty::select('id', 'name_' . $lang . ' as name')
            ->get()->map(function ($data) use ($user) {
                if ($user) {
                    $exists_specialty = User_specialty::where('special_id', $data->id)->where('user_id', $user->id)->first();
                    if ($exists_specialty) {
                        $data->is_selected = true;
                    } else {
                        $data->is_selected = false;
                    }
                } else {
                    $data->is_selected = false;
                }
                return $data;
            });
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $lang);
        return response()->json($response, 200);
    }

    public function checkphoneexistanceandroid(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required'
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, 'Missing Required Fields', 'حقل الهاتف اجباري', (object)[], $request->lang);
            return response()->json($response, 406);
        }

        $user = User::where('phone', $request->phone)->first();
        if ($user) {

            if ($request->email) {
                $user_email = User::where('email', $request->email)->first();
                if ($user_email) {
                    $response = APIHelpers::createApiResponse(false, 200, '', '', (object)[], $request->lang);
                    $response['phone'] = true;
                    $response['email'] = true;
                    return response()->json($response, 200);
                } else {
                    $response = APIHelpers::createApiResponse(false, 200, '', '', (object)[], $request->lang);
                    $response['phone'] = true;
                    $response['email'] = false;
                    return response()->json($response, 200);
                }

            }
            $response = APIHelpers::createApiResponse(false, 200, '', '', (object)[], $request->lang);
            return response()->json($response, 200);
        }
        if ($request->email) {
            $user_email = User::where('email', $request->email)->first();
            if ($user_email) {
                $response = APIHelpers::createApiResponse(false, 200, '', '', (object)[], $request->lang);
                $response['phone'] = false;
                $response['email'] = true;
                return response()->json($response, 200);
            }

        }

        $response = APIHelpers::createApiResponse(false, 200, 'Phone and Email Not Exists Before', 'الهاتف و البريد غير موجودين من قبل', (object)[], $request->lang);
        $response['phone'] = false;
        $response['email'] = false;

        return response()->json($response, 200);

    }

    public function update_profile(Request $request)
    {
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        $input = $request->all();
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'name' => '',
            'phone' => '',
            "email" => '',
            "image" => '',
            "city_id" => '',
            "area_id" => '',
            "cover" => '',
            "about_user" => '',
            "account_type" => '',
            "specialties" => '',
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, $validator->errors()->first(), $validator->errors()->first(), null, $request->lang);
            return response()->json($response, 406);
        }

        $currentuser = auth()->user();
        $user_by_phone = User::where('phone', '!=', $currentuser->phone)->where('phone', $request->phone)->first();
        if ($user_by_phone) {
            $response = APIHelpers::createApiResponse(true, 409, 'رقم الهاتف موجود من قبل', '', null, $request->lang);
            return response()->json($response, 409);
        }

        $user_by_email = User::where('email', '!=', $currentuser->email)->where('email', $request->email)->first();
        if ($user_by_email) {
            $response = APIHelpers::createApiResponse(true, 409, 'البريد الإلكتروني موجود من قبل', '', null, $request->lang);
            return response()->json($response, 409);
        }
        if ($request->image != null) {
            $image = $request->image;
            Cloudder::upload("data:image/jpeg;base64," . $image, null);
            $imagereturned = Cloudder::getResult();
            $image_id = $imagereturned['public_id'];
            $image_format = $imagereturned['format'];
            $image_new_name = $image_id . '.' . $image_format;
            $input['image'] = $image_new_name;
        } else {
            unset($input['image']);
        }

        if ($request->cover != null) {
            $cover = $request->cover;
            Cloudder::upload("data:image/jpeg;base64," . $cover, null);
            $imagereturned = Cloudder::getResult();
            $image_id = $imagereturned['public_id'];
            $image_format = $imagereturned['format'];
            $image_new_name = $image_id . '.' . $image_format;
            $input['cover'] = $image_new_name;
        } else {
            unset($input['cover']);
        }
        if ($request->specialties != null) {
            User_specialty::where('user_id', $user->id)->delete();
            $special_data['user_id'] = $user->id;
            foreach ($request->specialties as $row) {
                $special_data['special_id'] = $row;
                User_specialty::create($special_data);
            }
        }
        unset($input['specialties']);
        User::where('id', $currentuser->id)->update($input);

        $newuser = User::with('Account_type')->find($currentuser->id);
        $response = APIHelpers::createApiResponse(false, 200, '', '', $newuser, $request->lang);
        return response()->json($response, 200);
    }

    public function resetpassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required',
            "old_password" => 'required'
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, 'بعض الحقول مفقودة', '', null, $request->lang);
            return response()->json($response, 406);
        }

        $user = auth()->user();
        if (!Hash::check($request->old_password, $user->password)) {
            $response = APIHelpers::createApiResponse(true, 406, 'كلمه المرور السابقه خطأ', '', null, $request->lang);
            return response()->json($response, 406);
        }
        if ($request->old_password == $request->password) {
            $response = APIHelpers::createApiResponse(true, 406, 'لا يمكنك تعيين نفس كلمه المرور السابقه', '', null, $request->lang);
            return response()->json($response, 406);
        }
        User::where('id', $user->id)->update(['password' => Hash::make($request->password)]);
        $newuser = User::find($user->id);
        $response = APIHelpers::createApiResponse(false, 200, '', '', $newuser, $request->lang);
        return response()->json($response, 200);
    }

    public function resetforgettenpassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required',
            'phone' => 'required'
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, 'بعض الحقول مفقودة', '', null, $request->lang);
            return response()->json($response, 406);
        }

        $user = User::where('phone', $request->phone)->first();
        if (!$user) {
            $response = APIHelpers::createApiResponse(true, 403, 'رقم الهاتف غير موجود', '', null, $request->lang);
            return response()->json($response, 403);
        }

        User::where('phone', $user->phone)->update(['password' => Hash::make($request->password)]);
        $newuser = User::where('phone', $user->phone)->first();

        $token = auth()->login($newuser);
        $newuser->token = $this->respondWithToken($token);

        $response = APIHelpers::createApiResponse(false, 200, '', '', $newuser, $request->lang);
        return response()->json($response, 200);
    }

    // check if phone exists before or not
    public function checkphoneexistance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required'
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, 'حقل الهاتف اجباري', '', null, $request->lang);
            return response()->json($response, 406);
        }

        $user = User::where('phone', $request->phone)->first();
        if ($user) {
            $response = APIHelpers::createApiResponse(false, 200, '', '', $user, $request->lang);
            return response()->json($response, 200);
        }

        $response = APIHelpers::createApiResponse(true, 403, 'الهاتف غير موجود من قبل', '', null, $request->lang);
        return response()->json($response, 403);

    }


    // get notifications
    public function notifications(Request $request)
    {
        $user = auth()->user();
        if ($user->active == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'تم حظر حسابك من الادمن', '', null, $request->lang);
            return response()->json($response, 406);
        }
        if (!$request->header('uniqueid')) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'uniqueid is required header', 'uniqueid is required header' , null, $request->lang );
            return response()->json($response , 406);
        }
        $user_id = $user->id;
        $visitor = Visitor::where('unique_id', $request->header('uniqueid'))->where('user_id', $user_id)->select('id')->first();
        $notifications_ids = UserNotification::where('user_id', $user_id)->where('visitor_id', $visitor->id)->orderBy('id', 'desc')->select('notification_id')->get();
        $notifications = [];
        for ($i = 0; $i < count($notifications_ids); $i++) {
            $notifications[$i] = Notification::select('id', 'title', 'body', 'image', 'ad_id', 'created_at')->find($notifications_ids[$i]['notification_id']);
        }
        $data['notifications'] = $notifications;
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data['notifications'], $request->lang);
        return response()->json($response, 200);
    }

    // get ads count
    public function getadscount(Request $request)
    {
        $user = auth()->user();
        $returned_user['free_ads_count'] = $user->free_ads_count;
        $returned_user['paid_ads_count'] = $user->paid_ads_count;
        $response = APIHelpers::createApiResponse(false, 200, '', '', $returned_user, $request->lang);
        return response()->json($response, 200);
    }

    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 432000
        ];
    }

    // get current ads
    public function getcurrentads(Request $request)
    {
        $user = auth()->user();
        if ($user->active == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'تم حظر حسابك من الادمن', '', null, $request->lang);
            return response()->json($response, 406);
        }

        $user = auth()->user();

        $products = Product::where('user_id', $user->id)->where('status', 1)->orderBy('publication_date', 'DESC')->select('id', 'title', 'price', 'publication_date as date', 'type')->simplePaginate(12);
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['image'] = ProductImage::where('product_id', $products[$i]['id'])->select('image')->first()['image'];
            $favorite = Favorite::where('user_id', $user->id)->where('product_id', $products[$i]['id'])->first();
            if ($favorite) {
                $products[$i]['favorite'] = true;
            } else {
                $products[$i]['favorite'] = false;
            }
            $date = date_create($products[$i]['date']);
            $products[$i]['date'] = date_format($date, 'd M Y');
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $products, $request->lang);
        return response()->json($response, 200);
    }

    // get history date
    public function getexpiredads(Request $request)
    {
        $user = auth()->user();
        if ($user->active == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'تم حظر حسابك من الادمن', '', null, $request->lang);
            return response()->json($response, 406);
        }

        $user = auth()->user();

        $products = Product::where('user_id', $user->id)->where('status', 2)->orderBy('publication_date', 'DESC')->select('id', 'title', 'price', 'publication_date as date', 'type')->simplePaginate(12);
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['image'] = ProductImage::where('product_id', $products[$i]['id'])->select('image')->first()['image'];
            $favorite = Favorite::where('user_id', $user->id)->where('product_id', $products[$i]['id'])->first();
            if ($favorite) {
                $products[$i]['favorite'] = true;
            } else {
                $products[$i]['favorite'] = false;
            }
            $date = date_create($products[$i]['date']);
            $products[$i]['date'] = date_format($date, 'd M Y');
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $products, $request->lang);
        return response()->json($response, 200);
    }

    public function renewad(Request $request)
    {
        $user = auth()->user();
        if ($user->active == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'تم حظر حسابك', '', null, $request->lang);
            return response()->json($response, 406);
        }
        if ($user->free_ads_count == 0 && $user->paid_ads_count == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'ليس لديك رصيد إعلانات لتجديد الإعلان يرجي شراء باقه إعلانات', '', null, $request->lang);
            return response()->json($response, 406);
        }
        $product = Product::where('id', $request->product_id)->where('user_id', $user->id)->first();
        if ($product->status == 1) {
            $response = APIHelpers::createApiResponse(true, 406, 'هذا الاعلان لم ينتهى بعد', 'this ad not ended yet', null, $request->lang);
            return response()->json($response, 406);
        }
        if ($product->deleted == 1) {
            $response = APIHelpers::createApiResponse(true, 406, 'هذا الاعلان تم حذفة', 'this ad deleted before', null, $request->lang);
            return response()->json($response, 406);
        }
        if ($product) {
            if ($user->free_ads_count > 0) {
                $count = $user->free_ads_count;
                $user->free_ads_count = $count - 1;
            } else {
                $count = $user->paid_ads_count;
                $user->paid_ads_count = $count - 1;
            }
            $user->save();
            $settings = $settings = Setting::where('id', 1)->first();
            $product->publication_date = date("Y-m-d H:i:s");
            $mytime = Carbon::now();
            $today = Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');
            $final_date = Carbon::createFromFormat('Y-m-d H:i', $today);
            $final_expire_date = $final_date->addDays($settings->ad_period);
            $product->expiry_date = $final_expire_date;
            $product->status = 1;
            $product->publish = 'Y';
            $product->save();
            $response = APIHelpers::createApiResponse(false, 200, '', '', $product, $request->lang);
            return response()->json($response, 200);

        } else {
            $response = APIHelpers::createApiResponse(true, 406, 'ليس لديك الصلاحيه لتجديد هذا الاعلان', '', null, $request->lang);
            return response()->json($response, 406);

        }

    }

    public function deletead(Request $request)
    {
        $user = auth()->user();
        if ($user->active == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'تم حظر حسابك', '', null, $request->lang);
            return response()->json($response, 406);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, 'بعض الحقول مفقودة', '', null, $request->lang);
            return response()->json($response, 406);
        }

        $product = Product::where('id', $request->product_id)->where('user_id', $user->id)->first();

        if ($product) {
            $product->delete();
            $response = APIHelpers::createApiResponse(false, 200, '', '', null, $request->lang);
            return response()->json($response, 200);
        } else {
            $response = APIHelpers::createApiResponse(true, 406, 'ليس لديك الصلاحيه لحذف هذا الاعلان', '', null, $request->lang);
            return response()->json($response, 406);
        }

    }

    public function editad(Request $request)
    {
        $user = auth()->user();
        if ($user->active == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'تم حظر حسابك', '', null, $request->lang);
            return response()->json($response, 406);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, 'بعض الحقول مفقودة', '', null, $request->lang);
            return response()->json($response, 406);
        }

        $product = Product::where('id', $request->product_id)->where('user_id', $user->id)->first();
        if ($product) {
            if ($request->title) {
                $product->title = $request->title;
            }

            if ($request->description) {
                $product->description = $request->description;
            }

            if ($request->price) {
                $product->price = $request->price;
            }

            if ($request->category_id) {
                $product->category_id = $request->category_id;
            }

            if ($request->type) {
                $product->type = $request->type;
            }

            $product->save();

            if ($request->image) {
                $product_image = ProductImage::where('product_id', $request->product_id)->first();
                $image = $request->image;
                Cloudder::upload("data:image/jpeg;base64," . $image, null);
                $imagereturned = Cloudder::getResult();
                $image_id = $imagereturned['public_id'];
                $image_format = $imagereturned['format'];
                $image_new_name = $image_id . '.' . $image_format;
                $product_image->image = $image_new_name;
                $product_image->save();
            }

            $response = APIHelpers::createApiResponse(false, 200, '', '', $product, $request->lang);
            return response()->json($response, 200);
        } else {
            $response = APIHelpers::createApiResponse(true, 406, 'ليس لديك الصلاحيه لتعديل هذا الاعلان', '', null, $request->lang);
            return response()->json($response, 406);
        }

    }

    public function delteadimage(Request $request)
    {
        $user = auth()->user();
        if ($user->active == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'تم حظر حسابك', '', null, $request->lang);
            return response()->json($response, 406);
        }

        $validator = Validator::make($request->all(), [
            'image_id' => 'required',
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, 'بعض الحقول مفقودة', '', null, $request->lang);
            return response()->json($response, 406);
        }

        $image = ProductImage::find($request->image_id);
        if ($image) {
            $image->delete();
            $response = APIHelpers::createApiResponse(false, 200, '', '', null, $request->lang);
            return response()->json($response, 200);

        } else {
            $response = APIHelpers::createApiResponse(true, 406, 'Invalid Image Id', '', null, $request->lang);
            return response()->json($response, 406);
        }

    }

    public function getaddetails(Request $request)
    {
        $ad_id = $request->id;
        $ad = Product::select('id', 'title', 'description', 'price', 'type', 'category_id')->find($ad_id);
        $ad['category_name'] = Category::find($ad['category_id'])['title_ar'];
        $images = ProductImage::where('product_id', $ad_id)->select('id', 'image')->get()->toArray();

        $ad['image'] = array_shift($images)['image'];
        $ad['images'] = $images;
        $response = APIHelpers::createApiResponse(false, 200, '', '', $ad, $request->lang);
        return response()->json($response, 200);
    }

    public function getownerprofile(Request $request)
    {
        $user_id = $request->id;
        $data['user'] = User::select('id', 'name', 'phone', 'email')->find($user_id);
        $products = Product::where('status', 1)->where('user_id', $user_id)->orderBy('publication_date', 'DESC')->select('id', 'title', 'price', 'type', 'publication_date as date')->get();
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['image'] = ProductImage::where('product_id', $products[$i]['id'])->first()['image'];
            $date = date_create($products[$i]['date']);
            $products[$i]['date'] = date_format($date, 'd M Y');

            $user = auth()->user();
            if ($user) {
                $favorite = Favorite::where('user_id', $user->id)->where('product_id', $products[$i]['id'])->first();
                if ($favorite) {
                    $products[$i]['favorite'] = true;
                } else {
                    $products[$i]['favorite'] = false;
                }
            } else {
                $products[$i]['favorite'] = false;
            }

        }
        $data['products'] = $products;

        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    // nasser code
    public function my_account(Request $request)
    {
        
        $lang = $request->lang;
        $user = auth()->user();
        Session::put('api_lang', $lang);
        $data['personal_data'] = User::with('City')->with('Area')->with('Account_type')
        ->where('id', $user->id)
        ->select('id', 'name', 'email', 'about_user', 'image', 'cover', 'phone', 'watsapp', 'city_id', 'area_id', 'account_type', 'created_at', 'updated_at')
        ->first();
        $visitor = Visitor::where('unique_id', $request->header('uniqueid'))->select('city_id', 'unique_id')->first();
        
        $data['personal_data']->last_seen = $data['personal_data']->updated_at->diffForHumans();
        $user_specialties = User_specialty::with('Specialty')
            ->select('special_id')
            ->where('user_id', $user->id)->get();
        $spec = "";
        foreach ($user_specialties as $row) {
            if ($lang == 'ar') {
                $spec = $spec . ',' . $row->Specialty->name;
            } else {
                $spec = $spec . ',' . $row->Specialty->name;
            }
        }
        $data['personal_data']->specialties = $spec;
        $data['personal_data']->specialties_ids = $user_specialties;
        if ($data['personal_data']->city_id != null && $data['personal_data']->area_id != null) {
            $data['personal_data']->address = $data['personal_data']->City->title_ar . ' , ' . $data['personal_data']->Area->title_ar;
        } else {
            $data['personal_data']->address = "";
        }
        $products = Product::with('City')->with('Area')->with('Publisher')->where('status', 2)
            ->where('deleted', 0)
            ->where('user_id', auth()->user()->id)
            ->select('id', 'title', 'main_image as image', 'created_at', 'user_id', 'city_id', 'area_id', 'retweet', 'retweet_date', 'price')
            ->orderBy('created_at', 'desc')
            ->simplePaginate(12);
        for ($i = 0; $i < count($products); $i++) {
            
            $products[$i]['show_price'] = true;
            if ($products[$i]['price'] == 0) {
                $products[$i]['show_price'] = false;
            } 
            $products[$i]['price']  = number_format((float)(  $products[$i]['price'] ), 3);
            $retweetDate = new Carbon($products[$i]['retweet_date']);
            if ((!$retweetDate->isToday()) && ($retweetDate->addDay() >= $products[$i]['retweet_date'])) {
                $products[$i]['retweet'] = 1;
            }else {
                $products[$i]['retweet'] = 0;
            }
            if ($lang == 'ar') {
                $products[$i]['address'] = $products[$i]['City']->title_ar . ' , ' . $products[$i]['Area']->title_ar;
            } else {
                $products[$i]['address'] = $products[$i]['City']->title_en . ' , ' . $products[$i]['Area']->title_en;
            }

            if ($user) {
                $favorite = Favorite::where('user_id', $user->id)->where('product_id', $products[$i]['id'])->first();
                if ($favorite) {
                    $products[$i]['favorite'] = true;
                } else {
                    $products[$i]['favorite'] = false;
                }

                $conversation = Participant::where('ad_product_id', $products[$i]['id'])->where('user_id', $user->id)->first();
                if ($conversation == null) {
                    $products[$i]['conversation_id'] = 0;
                } else {
                    $products[$i]['conversation_id'] = $conversation->conversation_id;
                }
            } else {
                $products[$i]['favorite'] = false;
                $products[$i]['conversation_id'] = 0;
            }
            $products[$i]['time'] = APIHelpers::get_month_day($products[$i]['created_at'], $lang);
        }
        $current_products = Product::with('City')->with('Area')->with('Publisher')->where('status', 1)
            ->where('publish', 'Y')
            ->where('deleted', 0)
            ->where('user_id', auth()->user()->id)
            ->select('id', 'title', 'main_image as image', 'created_at', 'user_id', 'city_id', 'area_id', 'retweet', 'retweet_date', 'price')
            ->orderBy('created_at', 'desc')
            ->simplePaginate(12);
        for ($i = 0; $i < count($current_products); $i++) {
            $current_products[$i]['show_price'] = true;
            if ($current_products[$i]['price'] == 0) {
                $current_products[$i]['show_price'] = false;
            } 
            $current_products[$i]['price']  = number_format((float)(  $current_products[$i]['price'] ), 3);
            $retweetDate = new Carbon($current_products[$i]['retweet_date']);
            if ((!$retweetDate->isToday()) && ($retweetDate->addDay() >= $current_products[$i]['retweet_date'])) {
                $current_products[$i]['retweet'] = 1;
            }else {
                $current_products[$i]['retweet'] = 0;
            }

            if ($lang == 'ar') {
                $current_products[$i]['address'] = $current_products[$i]['City']->title_ar . ' , ' . $current_products[$i]['Area']->title_ar;
            } else {
                $current_products[$i]['address'] = $current_products[$i]['City']->title_en . ' , ' . $current_products[$i]['Area']->title_en;
            }

            if ($user) {
                $favorite = Favorite::where('user_id', $user->id)->where('product_id', $current_products[$i]['id'])->first();
                if ($favorite) {
                    $current_products[$i]['favorite'] = true;
                } else {
                    $current_products[$i]['favorite'] = false;
                }

                $conversation = Participant::where('ad_product_id', $current_products[$i]['id'])->where('user_id', $user->id)->first();
                if ($conversation == null) {
                    $current_products[$i]['conversation_id'] = 0;
                } else {
                    $current_products[$i]['conversation_id'] = $conversation->conversation_id;
                }
            } else {
                $current_products[$i]['favorite'] = false;
                $current_products[$i]['conversation_id'] = 0;
            }
//            $current_products[$i]['time'] = APIHelpers::get_month_day($current_products[$i]['created_at'], $lang);
        }
        $data['ended_ads'] = $products;
        $data['current_ads'] = $current_products;
        $data['personal_data']->current_ads_count = $data['current_ads']->count();
        $data['personal_data']->end_ads_count = $data['ended_ads']->count();

        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function payments_date(Request $request)
    {
        $user = auth()->user();
        $lang = $request->lang;

        $data = WalletTransaction::where('user_id', $user->id)
            ->where('type', 'payed')
            ->select('price', 'type', 'user_id', 'package_id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($wallet) use ($lang) {
                $package = Balance_package::where('id', $wallet->package_id)->first();
                if ($lang == 'ar') {
                    $wallet->pakage_name = $package->name_ar;
                } else {
                    $wallet->pakage_name = $package->name_en;
                }
                $wallet->day = $wallet->created_at->format('d');
                $wallet->month = $wallet->created_at->format('F');
                if ($lang == 'ar') {
                    if ($wallet->month == 'January') {
                        $wallet->month = 'يناير';
                    } else if ($wallet->month == 'February') {
                        $wallet->month = 'فبراير';
                    } else if ($wallet->month == 'March') {
                        $wallet->month = 'مارس';
                    } else if ($wallet->month == 'April') {
                        $wallet->month = 'ابريل';
                    } else if ($wallet->month == 'May') {
                        $wallet->month = 'مايو';
                    } else if ($wallet->month == 'June') {
                        $wallet->month = 'يونيو';
                    } else if ($wallet->month == 'July') {
                        $wallet->month = 'يوليو';
                    } else if ($wallet->month == 'August') {
                        $wallet->month = 'أغسطي';
                    } else if ($wallet->month == 'September') {
                        $wallet->month = 'سبتمبر';
                    } else if ($wallet->month == 'October') {
                        $wallet->month = 'أكتوبر';
                    } else if ($wallet->month == 'November') {
                        $wallet->month = 'نوفمبر';
                    } else if ($wallet->month == 'December') {
                        $wallet->month = 'ديسمبر';
                    }
                }
                $wallet->date = $wallet->created_at->format('d/m/Y');

                return $wallet;
            });

        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function my_balance(Request $request)
    {
        $data = User::where('id', auth()->user()->id)->select('id', 'free_balance', 'payed_balance')->first();
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

// add balance to wallet
    public function addBalance(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:balance_packages,id'
        ]);
        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, $validator->messages()->first(), $validator->messages()->first(), null, $request->lang);
            return response()->json($response, 406);
        }
        $package = Balance_package::find($request->package_id);
        $user = auth()->user();
        $root_url = $request->root();
        $path = 'https://apitest.myfatoorah.com/v2/SendPayment';
        $token = "bearer rLtt6JWvbUHDDhsZnfpAhpYk4dxYDQkbcPTyGaKp2TYqQgG7FGZ5Th_WD53Oq8Ebz6A53njUoo1w3pjU1D4vs_ZMqFiz_j0urb_BH9Oq9VZoKFoJEDAbRZepGcQanImyYrry7Kt6MnMdgfG5jn4HngWoRdKduNNyP4kzcp3mRv7x00ahkm9LAK7ZRieg7k1PDAnBIOG3EyVSJ5kK4WLMvYr7sCwHbHcu4A5WwelxYK0GMJy37bNAarSJDFQsJ2ZvJjvMDmfWwDVFEVe_5tOomfVNt6bOg9mexbGjMrnHBnKnZR1vQbBtQieDlQepzTZMuQrSuKn-t5XZM7V6fCW7oP-uXGX-sMOajeX65JOf6XVpk29DP6ro8WTAflCDANC193yof8-f5_EYY-3hXhJj7RBXmizDpneEQDSaSz5sFk0sV5qPcARJ9zGG73vuGFyenjPPmtDtXtpx35A-BVcOSBYVIWe9kndG3nclfefjKEuZ3m4jL9Gg1h2JBvmXSMYiZtp9MR5I6pvbvylU_PP5xJFSjVTIz7IQSjcVGO41npnwIxRXNRxFOdIUHn0tjQ-7LwvEcTXyPsHXcMD8WtgBh-wxR8aKX7WPSsT1O8d8reb2aR7K3rkV3K82K_0OgawImEpwSvp9MNKynEAJQS6ZHe_J_l77652xwPNxMRTMASk1ZsJL";
        $headers = array(
            'Authorization:' . $token,
            'Content-Type:application/json'
        );
        $call_back_url = $root_url . "/api/wallet/excute_pay?user_id=" . $user->id . "&balance=" . $request->package_id;
        $error_url = $root_url . "/api/pay/error";
//        dd($call_back_url);
        $fields = array(
            "CustomerName" => $user->name,
            "NotificationOption" => "LNK",
            "InvoiceValue" => $package->price,
            "CallBackUrl" => $call_back_url,
            "ErrorUrl" => $error_url,
            "Language" => "AR",
            "CustomerEmail" => $user->email
        );

        $payload = json_encode($fields);
        $curl_session = curl_init();
        curl_setopt($curl_session, CURLOPT_URL, $path);
        curl_setopt($curl_session, CURLOPT_POST, true);
        curl_setopt($curl_session, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_session, CURLOPT_IPRESOLVE, CURLOPT_IPRESOLVE);
        curl_setopt($curl_session, CURLOPT_POSTFIELDS, $payload);
        $result = curl_exec($curl_session);
        curl_close($curl_session);
        $result = json_decode($result);

        $data['url'] = $result->Data->InvoiceURL;
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    // excute pay
    public function excute_pay(Request $request)
    {
        $package = Balance_package::findOrFail($request->balance);
        if ($package != null) {
            $user = auth()->user();
            $selected_user = User::findOrFail($user->id);
            $selected_user->my_wallet = $selected_user->my_wallet + $package->amount;
            $selected_user->payed_balance = $selected_user->payed_balance + $package->amount;
            $selected_user->save();
            WalletTransaction::create([
                'price' => $package->price,
                'value' => $package->amount,
                'package_id' => $request->balance,
                'user_id' => $request->user_id
            ]);
            return redirect('api/pay/success');
        }
    }

    public function pay_error()
    {
        return "Please wait error ...";
    }

    public function pay_sucess()
    {
        return "Please wait success ...";
    }


}
