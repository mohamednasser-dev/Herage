<?php

namespace App\Http\Controllers;

use App\Participant;
use App\Product_view;
use App\SubCategory;
use App\SubTwoCategory;
use Illuminate\Http\Request;
use App\Helpers\APIHelpers;
use App\Balance_package;
use App\ProductImage;
use App\Plan_details;
use Carbon\Carbon;
use App\Favorite;
use App\Category;
use App\Product;
use App\Main_ad;
use App\Setting;
use App\User;
use App\Ad;
use Illuminate\Support\Facades\Session;


class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['balance_packages', 'gethome', 'getHomeAds', 'check_ad', 'main_ad']]);
    }

    public function gethome(Request $request)
    {
//        --------------------------------------------- begin scheduled functions --------------------------------------------------------

        $mytime = Carbon::now();
        $today = Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');

        $pin_ad = Setting::where('id', 1)->whereDate('free_loop_date', '<', Carbon::now())->first();
        if ($pin_ad != null) {
            if ($pin_ad->is_loop_free_balance == 'y') {
                $all_users = User::where('active', 1)->get();
                foreach ($all_users as $row) {
                    $user = User::find($row->id);
                    $user->my_wallet = $user->my_wallet + $pin_ad->free_loop_balance;
                    $user->free_balance = $user->free_balance + $pin_ad->free_loop_balance;
                    $user->save();
                }
                $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                $final_free_loop_date = $final_pin_date->addDays($pin_ad->free_loop_period);
                $pin_ad->free_loop_date = $final_free_loop_date;
                $pin_ad->save();
            }
        }
//        --------------------------------------------- end scheduled functions --------------------------------------------------------

        $data['slider'] = Ad::select('id', 'image', 'type', 'content')->where('place', 1)->get();
        $data['ads'] = Ad::select('id', 'image', 'type', 'content')->where('place', 2)->get();
        $data['categories'] = Category::select('id', 'image', 'title_ar as title')->where('deleted', 0)->get();
        $data['offers'] = Product::where('offer', 1)->where('status', 1)->where('deleted', 0)->where('publish', 'Y')->select('id', 'title', 'price', 'type')->get();
        for ($i = 0; $i < count($data['offers']); $i++) {
            $data['offers'][$i]['image'] = ProductImage::where('product_id', $data['offers'][$i]['id'])->select('image')->first()['image'];
            $user = auth()->user();
            if ($user) {
                $favorite = Favorite::where('user_id', $user->id)->where('product_id', $data['offers'][$i]['id'])->first();
                if ($favorite) {
                    $data['offers'][$i]['favorite'] = true;
                } else {
                    $data['offers'][$i]['favorite'] = false;
                }
            } else {
                $data['offers'][$i]['favorite'] = false;
            }
            // $data['offers'][$i]['favorite'] = false;

        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function getHomeAds(Request $request){

        $one = Ad::select('id', 'image', 'type', 'content')->where('place', 1)->get();


        $lang = $request->lang;
        Session::put('api_lang', $lang);
        $user = auth()->user();
        $cat_ids =[];
        $categories = Category::where(function ($q) {
            $q->has('SubCategories', '>', 0)->orWhere(function ($qq) {
                $qq->has('Products', '>', 0);
            });
        })->where('deleted', 0)->select('id', 'title_' . $lang . ' as title', 'image')->orderBy('sort', 'asc')->get();

        for ($i = 0; $i < count($categories); $i++) {
            $cat_ids[$i] = $categories[$i]['id'];
            $categories[$i]['products_count'] = Product::where('category_id', $categories[$i]['id'])->where('status', 1)->where('publish', 'Y')->where('deleted', 0)->count();
            //text next level
            $subTwoCats = SubCategory::where('category_id', $categories[$i]['id'])->where('deleted', 0)->select('id')->first();
            $categories[$i]['next_level'] = false;
            if (isset($subTwoCats['id'])) {
                $categories[$i]['next_level'] = true;
            }
            if ($categories[$i]['next_level'] == true) {
                // check after this level layers
                $data_ids = SubCategory::where('deleted', '0')->where('category_id', $categories[$i]['id'])->select('id')->get()->toArray();
                $subFiveCats = SubTwoCategory::whereIn('sub_category_id', $data_ids)->where('deleted', 0)->select('id', 'deleted')->get();
                if (count($subFiveCats) == 0) {
                    $have_next_level = false;
                } else {
                    $have_next_level = true;
                }
                if ($have_next_level == false) {
                    $categories[$i]['next_level'] = false;
                } else {
                    $categories[$i]['next_level'] = true;
                }
                //End check
            }
        }

        $data['categories'] = $categories;

        $products = Product::where('status', 1)->with('Publisher')
            ->where('publish', 'Y')
            ->where('deleted', 0)
            ->where('status', 1)
            ->whereIn('category_id', $cat_ids)
            ->select('id', 'title', 'main_image as image', 'created_at', 'user_id','city_id','area_id')
            ->orderBy('created_at', 'desc')
            ->simplePaginate(12);
        for ($i = 0; $i < count($products); $i++) {
                if($lang == 'ar'){
                    $products[$i]['address'] = $products[$i]['City']->title_ar .' , '.$products[$i]['Area']->title_ar;
                }else{
                    $products[$i]['address'] = $products[$i]['City']->title_en .' , '.$products[$i]['Area']->title_en;
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

        $data['products'] = $products;
        $data['ads'] = $one;

        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

//nasser code
    // main ad page
    public function main_ad(Request $request)
    {
        $data = Main_ad::select('image')->where('deleted', '0')->inRandomOrder()->take(1)->get();
        if (count($data) == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'no ads available',
                'لا يوجد اعلانات', null, $request->lang);
            return response()->json($response, 406);
        }
        foreach ($data as $image) {
            $image['image'] = $image->image;
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $image, $request->lang);
        return response()->json($response, 200);
    }

    public function check_ad(Request $request)
    {
        $ads = Main_ad::select('image')->where('deleted', '0')->get();
        if (count($ads) > 0) {
            $data['show_ad'] = true;
        } else {
            $data['show_ad'] = false;
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function balance_packages(Request $request)
    {
        if ($request->lang == 'en') {
            $data['packages'] = Balance_package::where('status', 'show')->select('id', 'name_en as title', 'price', 'amount', 'desc_en as desc')->orderBy('title', 'desc')->get();
        } else {
            $data['packages'] = Balance_package::where('status', 'show')->select('id', 'name_ar as title', 'price', 'amount', 'desc_ar as desc')->orderBy('title', 'desc')->get();
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }
}
