<?php

namespace App\Http\Controllers;

use App\Category;
use App\Notification;
use App\UserNotification;
use App\Category_option;
use App\Models\Student;
use App\Participant;
use App\Product_comment;
use App\Product_report;
use App\SubCategory;
use App\SubFiveCategory;
use App\SubFourCategory;
use App\SubThreeCategory;
use App\SubTwoCategory;
use App\User_specialty;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use JD\Cloudder\Facades\Cloudder;
use App\Category_option_value;
use Illuminate\Http\Request;
use App\Helpers\APIHelpers;
use App\Product_feature;
use App\Plan_details;
use App\Product_view;
use App\ProductImage;
use Carbon\Carbon;
use App\Favorite;
use App\Setting;
use App\Product;
use App\User;
use App\Plan;
use App\City;
use App\Area;
use App\CommentReport;
use App\Visitor;
use Throwable;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['select_my_ads', 'all_comments', 'make_comment', 'make_report', 'ad_owner_info', 'current_ads', 'ended_ads', 'max_min_price', 'filter', 'offer_ads', 'republish_ad',
            'areas', 'cities', 'third_step_excute_pay', 'save_third_step_with_money', 'update_ad', 'select_ad_data', 'delete_my_ad',
            'save_third_step', 'save_second_step', 'save_first_step', 'getdetails', 'last_seen', 'getoffers', 'getproducts','map_ads', 'getsearch', 'getFeatureOffers', 'map_ads_sub_cat_two', 'map_ads_last_level']]);
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

    public function create(Request $request)
    {
        $user = auth()->user();
        if ($user->active == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'تم حظر حسابك', null);
            return response()->json($response, 406);
        }

        if ($user->free_ads_count == 0 && $user->paid_ads_count == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'ليس لديك رصيد إعلانات لإضافه إعلان جديد يرجي شراء باقه إعلانات', null);
            return response()->json($response, 406);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'required',
            "type" => "required",
            "title" => "required",
            "description" => "required",
            "price" => "required",
            "image" => "required"
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, $validator->errors()->first(), $validator->errors()->first());
            return response()->json($response, 406);
        }

        if ($user->free_ads_count > 0) {
            $count = $user->free_ads_count;
            $user->free_ads_count = $count - 1;
        } else {
            $count = $user->paid_ads_count;
            $user->paid_ads_count = $count - 1;
        }

        $user->save();

        $ad_period = Setting::find(1)['ad_period'];

        $product = new Product();
        $product->title = $request->title;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->category_id = $request->category_id;
        $product->type = $request->type;
        $product->user_id = $user->id;
        $product->publication_date = date("Y-m-d H:i:s");
        $product->expiry_date = date('Y-m-d H:i:s', strtotime('+' . $ad_period . ' days'));

        $product->save();

        $image = $request->image;
        Cloudder::upload("data:image/jpeg;base64," . $image, null);
        $imagereturned = Cloudder::getResult();
        $image_id = $imagereturned['public_id'];
        $image_format = $imagereturned['format'];
        $image_new_name = $image_id . '.' . $image_format;
        $product_image = new ProductImage();
        $product_image->image = $image_new_name;
        $product_image->product_id = $product->id;
        $product_image->save();

        $product->image = $image_new_name;

        $response = APIHelpers::createApiResponse(false, 200, '', $product);
        return response()->json($response, 200);
    }

    public function uploadimages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'image' => 'required'
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, $validator->errors()->first(), $validator->errors()->first());
            return response()->json($response, 406);
        }

        $image = $request->image;
        Cloudder::upload("data:image/jpeg;base64," . $image, null);
        $imagereturned = Cloudder::getResult();
        $image_id = $imagereturned['public_id'];
        $image_format = $imagereturned['format'];
        $image_new_name = $image_id . '.' . $image_format;
        $product_image = new ProductImage();
        $product_image->image = $image_new_name;
        $product_image->product_id = $request->product_id;
        $product_image->save();
        $response = APIHelpers::createApiResponse(false, 200, '', $product_image);
        return response()->json($response, 200);
    }

    public function all_comments(Request $request, $id)
    {
        $comments = Product_comment::with('User')
            ->select('id', 'user_id', 'comment')
            ->where('product_id', $id)
            ->where('status', '!=', 'rejected')
            ->orderBy('created_at', 'desc')
            ->get();
        $response = APIHelpers::createApiResponse(false, 200, '', '', $comments, $request->lang);
        return response()->json($response, 200);
    }

    public function getdetails(Request $request){
        $user = auth()->user();
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        Session::put('lang', $lang);
        $data = Product::with('Product_user')->with('Area_name')
            ->select('id', 'title', 'main_image', 'description', 'price', 'type', 'publication_date as date', 'user_id', 'category_id', 'latitude', 'longitude', 'share_location', 'area_id', 'views', 'prevent_comments', 'retweet_date', 'retweet','created_at')
            ->find($request->id);

        $retweetDate = new Carbon($data->retweet_date);
        if ((!$retweetDate->isToday()) && ($retweetDate->addDay() >= $data['retweet_date'])) {
            $data->retweet = 1;
        }else {
            $data->retweet = 0;
        }
        $data['show_price'] = true;
        if ($data['price'] == 0) {
            $data['show_price'] = false;
        }
        $data->price = number_format((float)($data->price), 3);
        $data->comments_count = Product_comment::where('product_id', $request->id)
        ->where('status', '!=', 'rejected')
        ->orderBy('created_at', 'desc')
        ->get()
        ->count();

        if ($data->share_location == 0) {
            $data->share_location = '0';
        } else {
            $data->share_location = '1';
        }
        $user_ip_address = $request->ip();
        if ($user == null) {
            $prod_view = Product_view::where('ip', $user_ip_address)->where('product_id', $data->id)->first();
            if ($prod_view == null) {
                $data_view['ip'] = $user_ip_address;
                $data_view['product_id'] = $data->id;
                Product_view::create($data_view);
            }
        } else {
            $prod_view = Product_view::where('user_id', $user->id)->where('product_id', $data->id)->first();
            if ($prod_view == null) {
                $data_view['user_id'] = $user->id;
                $data_view['ip'] = $user_ip_address;
                $data_view['product_id'] = $data->id;
                Product_view::create($data_view);
            } else {
                $prod_view->user_id = $user->id;
                $prod_view->save();
            }
        }
        $product = Product::where('id', $request->id)->select('id', 'views')->first();
        $product->views = $product->views + 1;
        $product->save();
        $features = Product_feature::where('product_id', $request->id)
            ->select('id', 'type', 'product_id', 'target_id', 'option_id')
            ->get();
        $feature_data = [];
        foreach ($features as $key => $feature) {
            $feature_data[$key]['image'] = $feature->Option->image;
            if ($feature->type == 'manual') {
                $feature_data[$key]['title'] = $feature->Option->title;
                $feature_data[$key]['value'] = $feature->target_id;
            } else if ($feature->type == 'option') {
                $feature_data[$key]['title'] = $feature->Option->title;
                $feature_data[$key]['value'] = $feature->Option_value->value;
            }
        }
        if ($user) {
            $favorite = Favorite::where('user_id', $user->id)->where('product_id', $data->id)->first();
            if ($favorite) {
                $data->favorite = true;
            } else {
                $data->favorite = false;
            }
            $conversation = Participant::where('ad_product_id', $data->id)->where('user_id', $user->id)->first();
            if ($conversation == null) {
                $data->conversation_id = 0;
            } else {
                $data->conversation_id = $conversation->conversation_id;
            }

        } else {
            $data->favorite = false;
            $data->conversation_id = 0;
        }
        $date = date_create($data->date);
        $data->date = date_format($date, 'd M Y');
//        $data->time = date_format($date, 'g:i a');
        $data['time'] = $data['created_at']->diffForHumans(['long' => true, 'parts' => 2, 'join' => ' و ']);

        //to get ad images in array
        $images = ProductImage::where('product_id', $data->id)->pluck('image')->toArray();
        $images[count($images)] = $data->main_image;
        $data->images = $images;

        $comments = Product_comment::with('User')->select('id', 'user_id', 'comment')->where('status', '!=', 'rejected')->where('product_id', $data->id)->orderBy('created_at', 'desc')->limit(3)->get();


        $related = Product::where('category_id', $data->category_id)
            ->where('id', '!=', $data->id)
            ->where('status', 1)
            ->where('publish', 'Y')
            ->where('deleted', 0)
            ->with('Publisher')
            ->select('id','title','main_image','user_id','price','description','created_at','city_id','area_id')
            ->limit(3)
            ->get()
            ->map(function ($ads) use ($lang, $user) {
                $ads['show_price'] = true;
                if ($ads->price == 0) {
                    $ads['show_price'] = false;
                }
                $ads->price = number_format((float)($ads->price), 3);
                if ($lang == 'ar') {
                    $ads->address = $ads->City->title_ar . ' , ' . $ads->Area->title_ar;
                } else {
                    $ads->address = $ads->City->title_en . ' , ' . $ads->Area->title_en;
                }
                if ($user) {
                    $favorite = Favorite::where('user_id', $user->id)->where('product_id', $ads->id)->first();
                    if ($favorite) {
                        $ads->favorite = true;
                    } else {
                        $ads->favorite = false;
                    }
                    $conversation = Participant::where('ad_product_id', $ads->id)->where('user_id', $user->id)->first();
                    if ($conversation == null) {
                        $ads->conversation_id = 0;
                    } else {
                        $ads->conversation_id = $conversation->conversation_id;
                    }
                } else {
                    $ads->favorite = false;
                    $ads->conversation_id = 0;
                }
                $ads->time = $ads->created_at->diffForHumans();
                return $ads;
            });
            $show_views = Setting::where('id', 1)->select('show_views')->first()['show_views'];
        $response = APIHelpers::createApiResponse(false, 200, '', '', array('product' => $data,
            'features' => $feature_data, 'comments' => $comments, 'related' => $related, 'show_views' => $show_views), $request->lang);
        return response()->json($response, 200);
    }

    public function getoffers(Request $request)
    {
        $products = Product::where('offer', 1)->select('id', 'title', 'price', 'type', 'publication_date as date', 'views')->orderBy('publication_date', 'DESC')->where('status', 1)->where('deleted', 0)->where('reviewed', 1)->where('publish', 'Y')->simplePaginate(12);
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['price'] = number_format((float)($products[$i]['price']), 3);
            $date = date_create($products[$i]['date']);
            $products[$i]['date'] = date_format($date, 'd M Y');
            $products[$i]['image'] = ProductImage::where('product_id', $products[$i]['id'])->select('image')->first()['image'];
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
        $response = APIHelpers::createApiResponse(false, 200, '', '', $products, $request->lang);
        return response()->json($response, 200);
    }

    public function getproducts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required'
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, $validator->errors()->first(), $validator->errors()->first(), null, $request->lang);
            return response()->json($response, 406);
        }

        if ($request->type) {
            $type = [$request->type];
        } else {
            $type = [1, 2];
        }

        $products = Product::where('status', 1)->where('deleted', 0)->where('publish', 'Y')->whereIn('type', $type)->where('reviewed', 1)->where('category_id', $request->category_id)->select('id', 'title', 'price', 'type', 'publication_date as date')->orderBy('publication_date', 'DESC')->simplePaginate(12);

        for ($i = 0; $i < count($products); $i++) {
            $date = date_create($products[$i]['date']);
            $products[$i]['date'] = date_format($date, 'd M Y');
            $products[$i]['image'] = ProductImage::where('product_id', $products[$i]['id'])->first()['image'];
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
        $response = APIHelpers::createApiResponse(false, 200, '', '', $products, $request->lang);
        return response()->json($response, 200);

    }

    public function ad_owner_info(Request $request, $id)
    {
        $user = auth()->user();
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        $basic_info = User::with('Account_type')->select('id', 'name', 'cover', 'email', 'image','account_type', 'phone', 'city_id', 'area_id', 'about_user', 'created_at as join_date', 'updated_at')->where('id', $id)->first()->makeHidden(['city_id', 'area_id', 'City', 'Area']);
        $basic_info->last_seen = $basic_info->updated_at->diffForHumans();
        if ($basic_info->city_id != null && $basic_info->area_id != null) {
            $basic_info->address = $basic_info->City->title_ar . ' , ' . $basic_info->Area->title_ar;
        } else {
            $basic_info->address = "";
        }
        $spec = "";
        if($basic_info->Account_type->type == 'commercial'){
            $user_specialties = User_specialty::with('Specialty')
                ->select('special_id')
                ->where('user_id', $id)->get();

            foreach ($user_specialties as $row) {
                if ($lang == 'ar') {
                    $spec = $spec . ',' . $row->Specialty->name;
                } else {
                    $spec = $spec . ',' . $row->Specialty->name;
                }
            }
            $basic_info->specialties = $spec;
        }else{
            $basic_info->specialties = "";
        }
        $basic_info->current_ads_count = Product::where('user_id', $id)
            ->where('status', 1)
            ->where('publish', 'Y')
            ->where('deleted', 0)
            ->get()->count();

        $products = Product::select('id', 'title', 'main_image as image', 'created_at', 'user_id','city_id','area_id', 'views')
            ->with('Publisher')
            ->where('user_id', $id)
            ->where('status', 1)
            ->where('publish', 'Y')
            // ->where('reviewed', 1)
            ->where('deleted', 0)
            ->orderBy('created_at', 'desc')
            ->simplePaginate(12);
//        ->makeHidden(['city_id','area_id','City','Area'])
        for ($i = 0; $i < count($products); $i++) {
           if($products[$i]['City'] != null &&$products[$i]['Area'] != null  ){
               if ($lang == 'ar') {
                   $products[$i]['address'] = $products[$i]['City']->title_ar . ' , ' . $products[$i]['Area']->title_ar;
               } else {
                   $products[$i]['address'] = $products[$i]['City']->title_en . ' , ' . $products[$i]['Area']->title_en;
               }
           }else{
               $products[$i]['address'] = "";
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
            $products[$i]['price']  = number_format((float)(  $products[$i]['price']  ), 3);

            $products[$i]['time'] = APIHelpers::get_month_day( $products[$i]['created_at'] , $lang);
        }

        $response = APIHelpers::createApiResponse(false, 200, '', '', array('basic_info'=>$basic_info ,'ads'=>$products), $request->lang);
        return response()->json($response, 200);

    }

    public function getsearch(Request $request)
    {
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        $validator = Validator::make($request->all(), [
            'search' => 'required'
        ]);
        $search = $request->search;
        $products = Product::select('id','title','main_image','user_id','price','description','created_at','city_id','area_id', 'views')
            ->with('Publisher')
            ->where('publish', 'Y')
            ->where('deleted', 0)
            ->where('status', 1)
            ->where('reviewed', 1)
            ->Where(function ($query) use ($search) {
                $query->Where('title', 'like', '%' . $search . '%');
            })
            ->orderBy('pin', 'desc')
            ->orderBy('created_at', 'desc')
            ->simplePaginate(12);
        for ($i = 0; $i < count($products); $i++) {
            if($lang == 'ar'){
                $products[$i]['address'] = $products[$i]['City']->title_ar .' , '.$products[$i]['Area']->title_ar;
            }else{
                $products[$i]['address'] = $products[$i]['City']->title_en .' , '.$products[$i]['Area']->title_en;
            }
            $products[$i]['price']  = number_format((float)(  $products[$i]['price'] ), 3);
            $user = auth()->user();
            if ($user) {
                $favorite = Favorite::where('user_id', $user->id)->where('product_id', $products[$i]['id'])->first();
                if ($favorite) {
                    $products[$i]['favorite'] = true;
                } else {
                    $products[$i]['favorite'] = false;
                }

                $conversation = Participant::where('ad_product_id', $products[$i]['product_id'])->where('user_id', $user->id)->first();
                if ($conversation == null) {
                    $products[$i]['conversation_id'] = 0;
                } else {
                    $products[$i]['conversation_id'] = $conversation->conversation_id;
                }
            } else {
                $products[$i]['favorite'] = false;
                $products[$i]['conversation_id'] = 0;
            }
            $products[$i]['time'] = $products[$i]['created_at']->diffForHumans();
        }
        $show_views = Setting::where('id', 1)->select('show_views')->first()['show_views'];
        $response = APIHelpers::createApiResponse(false, 200, '', '', ['products' => $products, 'show_views' => $show_views], $request->lang);
        return response()->json($response, 200);
    }

    // get cats - sub cats with next level
    public function getCatsSubCats($model, $lang, $baseUrl, $cat_id=0, $all=false, $whereIn=[]) {
        $categories = $model::has('Products', '>', 0)
        ->where('deleted', 0);
        if ($model == '\App\SubCategory' && $cat_id != 0) {
            $categories = $categories->where('category_id', $cat_id);
        }elseif ($model != '\App\Category' && $cat_id != 0) {
            $categories = $categories->where('sub_category_id', $cat_id);
        }
        if (count($whereIn) > 0 && $model != '\App\SubCategory') {
            $categories = $categories->whereIn('sub_category_id', $whereIn);
        }

        if (count($whereIn) > 0 && $model == '\App\SubCategory') {
            $categories = $categories->whereIn('category_id', $whereIn);
        }

        $categories = $categories->select('id', 'title_' . $lang . ' as title', 'image')->orderBy('sort', 'asc')->get()->makeHidden(['ViewSubCategories', 'products'])
        ->map(function ($row) use ($baseUrl, $model, $lang) {
            if ($model == '\App\SubCategory') {
                $row->url = $baseUrl . '/api/map/ads/level2/' . $row->id . '/' . $lang . '/v1';
            }else {
                $row->url = $baseUrl . '/api/map/ads/last_level/' . $row->id . '/' . $lang . '/v1';
            }
            $row->next_level = false;
            $subCategories = $row->ViewSubCategories;

            if ($subCategories && count($subCategories) > 0) {
                $hasProducts = false;
                for ($n = 0; $n < count($subCategories); $n++) {

                    if ($model != '\App\SubFiveCategory') {
                        if ($subCategories[$n]->products != null && count($subCategories[$n]->products) > 0) {
                            $hasProducts = true;
                        }
                    }
                }
                if ($hasProducts) {
                    $row->next_level = true;
                }
            }
            $row->selected = false;
            return $row;
        })->toArray();

        if ($all) {
            $url = '';
            if ($model == '\App\SubCategory') {
                $url = $baseUrl . '/api/map/ads/level2/0/' . $lang . '/v1';
            }else {
                $url = $baseUrl . '/api/map/ads/last_level/0/' . $lang . '/v1';
            }
            $title = 'All';
            if ($lang == 'ar') {
                $title = 'الكل';
            }
            $all = [
                'id' => 0,
                'image' => "",
                'title' => $title,
                'url' => $url,
                'next_level' => false,
                'selected' => false
            ];

            array_unshift($categories, $all);
        }

        return $categories;
    }

    //to get map ads establish
    public function map_ads(Request $request)
    {
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        $categories = $this->getCatsSubCats('\App\SubCategory', $lang, $request->root(), 7, true);

        $products = Product::where('publish', 'Y')->with('Publisher')->with('Sub_category')
            ->where('deleted', 0)
            ->where('status', 1)
            ->where('category_id',7);
        
        $products = $products->select('id', 'title', 'price','sub_category_id', 'sub_category_two_id', 'main_image as image', 'created_at', 'pin','city_id','area_id', 'user_id','latitude','longitude')
        ->orderBy('pin', 'desc')
        ->orderBy('created_at', 'desc')
        ->get()->makeHidden(['City','Area', 'Sub_two_category']);
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['show_price'] = true;
            if ($products[$i]['price'] == 0) {
                $products[$i]['show_price'] = false;
            }
            $products[$i]['price'] = number_format((float)($products[$i]['price']), 3);
            $products[$i]['color'] = '';
            if ($products[$i]->Sub_two_category) {
                $products[$i]['color'] = $products[$i]->Sub_two_category->color;
            }

            if($lang == 'ar'){
                $products[$i]['address'] = $products[$i]['City']->title_ar .' , '.$products[$i]['Area']->title_ar;
            }else{
                $products[$i]['address'] = $products[$i]['City']->title_en .' , '.$products[$i]['Area']->title_en;
            }
            $views = Product_view::where('product_id', $products[$i]['id'])->get()->count();
            $products[$i]['views'] = $views;
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
            $products[$i]['time'] = $products[$i]['created_at']->diffForHumans(['long' => true, 'parts' => 2, 'join' => ' و ']);
        }
        $show_views = Setting::where('id', 1)->select('show_views')->first()['show_views'];
        $response = APIHelpers::createApiResponse(false, 200, '', '',array('categories'=>$categories , 'products'=>$products, 'show_views' => $show_views ) , $request->lang);
        return response()->json($response, 200);

    }

    //to get map ads establish
    public function map_ads_sub_cat_two(Request $request)
    {
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        $subCats = [];
        if ($request->sub_category_id == 0) {
            $subCats = SubCategory::where('category_id', 7)->pluck('id')->toArray();
        }
        $categories = $this->getCatsSubCats('\App\SubTwoCategory', $lang, $request->root(), $request->sub_category_id, true, $subCats);

        $products = Product::where('publish', 'Y')->with('Publisher')->with('Sub_category')
            ->where('deleted', 0)
            ->where('status', 1)
            ->where('category_id',7);
            
            if ($request->sub_category_id && $request->sub_category_id) {
                $products = $products->where('sub_category_id', $request->sub_category_id);
            }
            $products = $products->select('id', 'title', 'price','sub_category_id', 'sub_category_two_id', 'main_image as image', 'created_at', 'pin','city_id','area_id', 'user_id','latitude','longitude')
            ->orderBy('pin', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()->makeHidden(['City','Area', 'Sub_two_category']);
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['show_price'] = true;
            if ($products[$i]['price'] == 0) {
                $products[$i]['show_price'] = false;
            }
            $products[$i]['price'] = number_format((float)($products[$i]['price']), 3);
            $products[$i]['color'] = '';
            if ($products[$i]->Sub_two_category) {
                $products[$i]['color'] = $products[$i]->Sub_two_category->color;
            }

            if($lang == 'ar'){
                $products[$i]['address'] = $products[$i]['City']->title_ar .' , '.$products[$i]['Area']->title_ar;
            }else{
                $products[$i]['address'] = $products[$i]['City']->title_en .' , '.$products[$i]['Area']->title_en;
            }
            $views = Product_view::where('product_id', $products[$i]['id'])->get()->count();
            $products[$i]['views'] = $views;
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
            $products[$i]['time'] = $products[$i]['created_at']->diffForHumans(['long' => true, 'parts' => 2, 'join' => ' و ']);
        }
        $show_views = Setting::where('id', 1)->select('show_views')->first()['show_views'];
        $response = APIHelpers::createApiResponse(false, 200, '', '',array('categories'=>$categories , 'products'=>$products, 'show_views' => $show_views ) , $request->lang);
        return response()->json($response, 200);
    }

    //to get map ads establish
    public function map_ads_last_level(Request $request)
    {
        $lang = $request->lang;
        Session::put('api_lang', $lang);

        $categories = [];

        $products = Product::where('publish', 'Y')->with('Publisher')->with('Sub_category')
            ->where('deleted', 0)
            ->where('status', 1)
            ->where('category_id',7);
            
            if ($request->sub_category_id && $request->sub_category_id) {
                $products = $products->where('sub_category_two_id', $request->sub_category_id);
            }
            $products = $products->select('id', 'title', 'price','sub_category_id', 'sub_category_two_id', 'main_image as image', 'created_at', 'pin','city_id','area_id', 'user_id','latitude','longitude')
            ->orderBy('pin', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()->makeHidden(['City','Area', 'Sub_two_category']);
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['show_price'] = true;
            if ($products[$i]['price'] == 0) {
                $products[$i]['show_price'] = false;
            }
            $products[$i]['price'] = number_format((float)$products[$i]['price'], 3, '.', '');
            $products[$i]['color'] = '';
            if ($products[$i]->Sub_two_category) {
                $products[$i]['color'] = $products[$i]->Sub_two_category->color;
            }

            if($lang == 'ar'){
                $products[$i]['address'] = $products[$i]['City']->title_ar .' , '.$products[$i]['Area']->title_ar;
            }else{
                $products[$i]['address'] = $products[$i]['City']->title_en .' , '.$products[$i]['Area']->title_en;
            }
            $views = Product_view::where('product_id', $products[$i]['id'])->get()->count();
            $products[$i]['views'] = $views;
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
            $products[$i]['time'] = $products[$i]['created_at']->diffForHumans(['long' => true, 'parts' => 2, 'join' => ' و ']);
        }
        $show_views = Setting::where('id', 1)->select('show_views')->first()['show_views'];
        $response = APIHelpers::createApiResponse(false, 200, '', '',array('categories'=>$categories , 'products'=>$products, 'show_views' => $show_views ) , $request->lang);
        return response()->json($response, 200);
    }

    public function filter(Request $request)
    {
        $lang = $request->lang;
        $result = Product::query();
        $result = $result->where('publish', 'Y')
            ->where('status', 1)
            ->where('deleted', 0)
            ->where('reviewed', 1);
        if ($request->from_price != null && $request->to_price != null) {
            $result = $result->whereRaw('price BETWEEN ' . $request->from_price . ' AND ' . $request->to_price . '');
        }
        if ($request->area_id != 0) {
            $result = $result->where('area_id', $request->area_id);
        }
        if ($request->category_id != null) {
            $result = $result->where('category_id', $request->category_id);
        }
        if ($request->sub_category_level1_id != null) {
            $result = $result->where('sub_category_id', $request->sub_category_level1_id);
        }
        if ($request->sub_category_level2_id != null) {
            $result = $result->where('sub_category_two_id', $request->sub_category_level2_id);
        }
        if ($request->sub_category_level3_id != null) {
            $result = $result->where('sub_category_three_id', $request->sub_category_level3_id);
        }
        if ($request->sub_category_level4_id != null) {
            $result = $result->where('sub_category_four_id', $request->sub_category_level4_id);
        }
        if ($request->sub_category_level5_id != null) {
            $result = $result->where('sub_category_five_id', $request->sub_category_level5_id);
        }
        if ($request->options != null) {
            $product_ids[] = null;
            foreach ($request->options as $key => $row) {
                $product_ids = Product_feature::where('option_id', $row['option_id'])->where('target_id', $row['option_value'])->pluck('product_id')->toArray();
            }
            $result = $result->whereIn('id', $product_ids);
        }
        $products = $result->select('id', 'title', 'price', 'main_image as image', 'pin', 'created_at', 'views')
            ->orderBy('pin', 'desc')
            ->orderBy('created_at', 'desc')
            ->simplePaginate(12);

        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['show_price'] = true;
            if ($products[$i]['price'] == 0) {
                $products[$i]['show_price'] = false;
            }
            $products[$i]['price'] = number_format((float)$products[$i]['price'], 3, '.', '');
            $views = Product_view::where('product_id', $products[$i]['id'])->get()->count();
            $products[$i]['views'] = $views;
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
            $products[$i]['time'] = $products[$i]['created_at']->diffForHumans();
        }
        $data['products'] = $products;
        $data['show_views'] = Setting::where('id', 1)->select('show_views')->first()['show_views'];
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function max_min_price(Request $request)
    {
        $result = Product::query();
        $result = $result->where('publish', 'Y')
            ->where('status', 1)
            ->where('deleted', 0)
            ->where('price', '!=', null)
            ->where('category_id', $request->category_id)
            ->orderBy('price', 'asc');

        if ($request->category_id != null) {
            $result = $result->where('category_id', $request->category_id);
        }


        if ($request->sub_category_level1_id != null) {
            $result = $result->where('sub_category_id', $request->sub_category_level1_id);
        }
        if ($request->sub_category_level2_id != null) {
            $result = $result->where('sub_category_two_id', $request->sub_category_level2_id);
        }
        if ($request->sub_category_level3_id != null) {
            $result = $result->where('sub_category_three_id', $request->sub_category_level3_id);
        }
        if ($request->sub_category_level4_id != null) {
            $result = $result->where('sub_category_four_id', $request->sub_category_level4_id);
        }
        if ($request->sub_category_level5_id != null) {
            $result = $result->where('sub_category_five_id', $request->sub_category_level5_id);
        }
        $result = $result->get();


        $data['max'] = $result->last()->price;
        $data['min'] = $result->first()->price;

//        $null_price = Product::where('publish', 'Y')->where('status', 1)->where('deleted', 0)->where('price', null)->get();
//        if (count($null_price) > 0) {
//            $data['min'] = "0";
//        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function getFeatureOffers(Request $request)
    {
        $products = Product::where('feature', 1)
            ->select('id', 'title', 'price')
            ->orderBy('publication_date', 'DESC')
            ->where('status', 1)
            ->where('deleted', 0)
            ->where('publish', 'Y')
            ->simplePaginate(12);
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['image'] = ProductImage::where('product_id', $products[$i]['id'])->select('image')->first()['image'];
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $products, $request->lang);
        return response()->json($response, 200);
    }
    //nasser code
    //to create ad you need 3 steps
    public function save_first_step(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'category_id' => 'required',
            'sub_category_id' => '',
            'title' => 'required',
            // 'main_image' => 'required',
            'images' => '',
            'city_id' => 'required|exists:cities,id',
            'area_id' => 'required|exists:areas,id',
            'latitude' => '',
            'longitude' => '',
            'options' => ''
        ]);
        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, $validator->messages()->first(), $validator->messages()->first(), null, $request->lang);
            return response()->json($response, 406);
        } else {
            $user = auth()->user();
            if ($user != null) {
                $input['user_id'] = $user->id;

                //to get the expire_date of ad
                $mytime = Carbon::now();
                $today = Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');
                $input['publish'] = 'Y';
                $input['publication_date'] = $today;
                if ($request->main_image) {
                    //save second step of creation ...
                    $image = $request->main_image;
                    Cloudder::upload("data:image/jpeg;base64," . $image, null);
                    $imagereturned = Cloudder::getResult();
                    $image_id = $imagereturned['public_id'];
                    $image_format = $imagereturned['format'];
                    $image_new_name = $image_id . '.' . $image_format;
                    $input['main_image'] = $image_new_name;
                }

                //create final
                if ($input['price'] == null) {
                    $input['price'] = '0';
                }

                //create expier day
                $settings = Setting::find(1);
                $mytime = Carbon::now();
                $today = Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');
                $final_retweet_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                $final_expire_pin_date = $final_pin_date->addDays($settings->expier_days);
                $input['expiry_date'] = $final_expire_pin_date;

                //to make retweet date
                $final_retweet_date_date = $final_retweet_date->addDays(1);
                // dd($today);
                $input['retweet_date'] = $today;
                $preventComments = 0;
                if ($request->prevent_comments) {
                    $preventComments = $request->prevent_comments;
                }
                $input['prevent_comments'] = $preventComments;
                $ad_data = Product::create($input);

                //save product feature ...
                if ($request->options != null) {
                    foreach ($request->options as $key => $option) {
                        if ($option['option_value'] != null) {
                            if (is_numeric($option['option_value'])) {
                                $option_values = Category_option_value::where('id', $option['option_value'])->first();
                                if ($option_values != null) {
                                    $feature_data['type'] = 'option';
                                } else {
                                    $feature_data['type'] = 'manual';
                                }
                            } else {
                                $feature_data['type'] = 'manual';
                            }
                            $feature_data['product_id'] = $ad_data->id;
                            $feature_data['target_id'] = $option['option_value'];
                            $feature_data['option_id'] = $option['option_id'];
                            Product_feature::create($feature_data);
                        }
                    }
                }
                if ($request->images != null) {
                    foreach ($request->images as $image) {
                        Cloudder::upload("data:image/jpeg;base64," . $image, null);
                        $imagereturned = Cloudder::getResult();
                        $image_id = $imagereturned['public_id'];
                        $image_format = $imagereturned['format'];
                        $image_name = $image_id . '.' . $image_format;

                        $data['product_id'] = $ad_data->id;
                        $data['image'] = $image_name;
                        ProductImage::create($data);
                    }
                }
                $final_data['status'] = true;
                $response = APIHelpers::createApiResponse(false, 200, 'your ad added successfully', 'تم أنشاء الاعلان بنجاح', $final_data, $request->lang);
                return response()->json($response, 200);
            } else {
                $response = APIHelpers::createApiResponse(true, 406, '', 'يجب تسجيل الدخول اولا', null, $request->lang);
                return response()->json($response, 406);
            }
        }
    }

    public function save_second_step(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'ad_id' => 'required|exists:products,id',
            'main_image' => 'required',
            'images' => 'required',
        ]);
        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, $validator->messages()->first(), $validator->messages()->first(), $validator->messages()->first(), $request->lang);
            return response()->json($response, 406);
        } else {
            if (auth()->user() != null) {
                $image = $request->main_image;
                Cloudder::upload("data:image/jpeg;base64," . $image, null);
                $imagereturned = Cloudder::getResult();
                $image_id = $imagereturned['public_id'];
                $image_format = $imagereturned['format'];
                $image_new_name = $image_id . '.' . $image_format;
                $product = Product::where('id', $request->ad_id)->first();
                $product->main_image = $image_new_name;
                $product->save();

                foreach ($request->images as $image) {
                    Cloudder::upload("data:image/jpeg;base64," . $image, null);
                    $imagereturned = Cloudder::getResult();
                    $image_id = $imagereturned['public_id'];
                    $image_format = $imagereturned['format'];
                    $image_name = $image_id . '.' . $image_format;

                    $data['product_id'] = $request->ad_id;
                    $data['image'] = $image_name;
                    ProductImage::create($data);
                }
                $response = APIHelpers::createApiResponse(false, 200, 'image saved successfully', 'تم حفظ الصور بنجاح', null, $request->lang);
                return response()->json($response, 200);
            } else {
                $response = APIHelpers::createApiResponse(true, 406, '', 'يجب تسجيل الدخول اولا', null, $request->lang);
                return response()->json($response, 406);
            }
        }
    }

    public function save_third_step(Request $request, $ad_id, $plan_id)
    {
        //when user have enghe money in wallet
        if (auth()->user() != null) {
            $user = User::where('id', auth()->user()->id)->first();
            $selected_plan = Plan::where('id', $plan_id)->first();
            $plan_ads_number = $selected_plan->ads_count;
            $plan_price = $selected_plan->price;
            if ($plan_price <= $user->my_wallet) {
                //to select expire days of selected plane
                $plan_detail = Plan_details::where('plan_id', $plan_id)->where('type', 'expier_num')->first();
                $expire_days = $plan_detail->expire_days;

                $user->my_wallet = $user->my_wallet - $plan_price;
                if ($user->free_balance >= $plan_price) {
                    $user->free_balance = $user->free_balance - $plan_price;
                } else if ($user->payed_balance >= $plan_price) {
                    $user->payed_balance = $user->payed_balance - $plan_price;
                } else {
                    $free_balance = $user->free_balance;  //70
                    $payed_balance = $user->payed_balance;  //30
                    $price = $plan_price; //100
                    $after_min_free = $price - $free_balance;  // 100 - 70 = 30
                    if ($after_min_free <= $payed_balance && $after_min_free > 0) {
                        // 30 <= 30 && 30 < 0
                        $user->free_balance = 0;
                        $user->payed_balance = $user->payed_balance - $after_min_free;
                    } else if ($after_min_free > $payed_balance && $after_min_free > 0) {
                        $after_min_payed = $price - $payed_balance;
                        $user->free_balance = $user->free_balance - $after_min_payed;
                        $user->payed_balance = 0;
                    }
                }
                $user->save();

                //to get the expire_date of ad
                $mytime = Carbon::now();
                $today = Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');
                $ad_data = null;
                $pin = Plan_details::where('plan_id', $plan_id)->where('type', 'pin')->first();
                if ($pin != null) {
                    $expire_pin_date = $pin->expire_days;
                    $ad_data['pin'] = 1;
                    //to create expire pin date
                    $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                    $final_expire_pin_date = $final_pin_date->addDays($expire_pin_date);
                    $ad_data['expire_pin_date'] = $final_expire_pin_date;
                }
                $re_post = Plan_details::where('plan_id', $plan_id)->where('type', 're_post')->first();
                if ($re_post != null) {
                    $expire_re_post_date = $re_post->expire_days;
                    $ad_data['re_post'] = '1';
                    //to create expire pin date
                    $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                    $final_expire_re_post_date = $final_pin_date->addDays($expire_re_post_date);
                    $ad_data['re_post_date'] = $final_expire_re_post_date;
                }
                $special = Plan_details::where('plan_id', $plan_id)->where('type', 'special')->first();
                if ($special != null) {
                    $expire_special_date = $special->expire_days;
                    $ad_data['is_special'] = '1';
                    //to create expire pin date
                    $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                    $final_expire_special_date = $final_pin_date->addDays($expire_special_date);
                    $ad_data['expire_special_date'] = $final_expire_special_date;
                }

                $final_today = Carbon::createFromFormat('Y-m-d H:i', $today);
                $expire_date = $final_today->addDays($expire_days);

                $ad_data['publish'] = 'Y';
                $ad_data['plan_id'] = $plan_id;
                $ad_data['publication_date'] = $today;
                $ad_data['expiry_date'] = $expire_date;
                Product::where('id', $ad_id)->update($ad_data);

                $response = APIHelpers::createApiResponse(false, 200, 'your ad added successfully', 'تم أنشاء الاعلان بنجاح', null, $request->lang);
                return response()->json($response, 200);
            } else {
                $response = APIHelpers::createApiResponse(true, 406, 'Your wallet does not contain enough amount to create an ad',
                    'محفظتك لا تحتوى على المبلغ الكافى لانشاء الاعلانا', null, $request->lang);
                return response()->json($response, 406);
            }
        } else {
            $response = APIHelpers::createApiResponse(true, 406, '', 'يجب تسجيل الدخول اولا', null, $request->lang);
            return response()->json($response, 406);
        }
    }

    // add balance to wallet
    public function save_third_step_with_money(Request $request)
    {
        $plan = Plan::where('id', $request->plan_id)->first();
        if ($plan == null) {
            $response = APIHelpers::createApiResponse(true, 406, 'يجب اختيار خطة صحيحة', 'plan not found ', null, $request->lang);
            return response()->json($response, 406);
        }

        $products = Product::where('id', $request->ad_id)->first();

        if ($products == null) {
            $response = APIHelpers::createApiResponse(true, 406, 'يجب اختيار اعلان صحيحة', 'Ad not found ', null, $request->lang);
            return response()->json($response, 406);
        }
        $user = auth()->user();
        $root_url = $request->root();
        $path = 'https://apitest.myfatoorah.com/v2/SendPayment';
        $token = "bearer rLtt6JWvbUHDDhsZnfpAhpYk4dxYDQkbcPTyGaKp2TYqQgG7FGZ5Th_WD53Oq8Ebz6A53njUoo1w3pjU1D4vs_ZMqFiz_j0urb_BH9Oq9VZoKFoJEDAbRZepGcQanImyYrry7Kt6MnMdgfG5jn4HngWoRdKduNNyP4kzcp3mRv7x00ahkm9LAK7ZRieg7k1PDAnBIOG3EyVSJ5kK4WLMvYr7sCwHbHcu4A5WwelxYK0GMJy37bNAarSJDFQsJ2ZvJjvMDmfWwDVFEVe_5tOomfVNt6bOg9mexbGjMrnHBnKnZR1vQbBtQieDlQepzTZMuQrSuKn-t5XZM7V6fCW7oP-uXGX-sMOajeX65JOf6XVpk29DP6ro8WTAflCDANC193yof8-f5_EYY-3hXhJj7RBXmizDpneEQDSaSz5sFk0sV5qPcARJ9zGG73vuGFyenjPPmtDtXtpx35A-BVcOSBYVIWe9kndG3nclfefjKEuZ3m4jL9Gg1h2JBvmXSMYiZtp9MR5I6pvbvylU_PP5xJFSjVTIz7IQSjcVGO41npnwIxRXNRxFOdIUHn0tjQ-7LwvEcTXyPsHXcMD8WtgBh-wxR8aKX7WPSsT1O8d8reb2aR7K3rkV3K82K_0OgawImEpwSvp9MNKynEAJQS6ZHe_J_l77652xwPNxMRTMASk1ZsJL";
        $headers = array(
            'Authorization:' . $token,
            'Content-Type:application/json'
        );
        $call_back_url = $root_url . "/api/ad/save_third_step/excute_pay?user_id=" . $user->id . "&plan_id=" . $request->plan_id . "&ad_id=" . $request->ad_id;
        $error_url = $root_url . "/api/pay/error";
        $fields = array(
            "CustomerName" => $user->name,
            "NotificationOption" => "LNK",
            "InvoiceValue" => $plan->price,
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
    public function third_step_excute_pay(Request $request)
    {
        //after customer pay the price of plan ..
        $plan_id = $request->plan_id;
        if (auth()->user() != null) {
            $user = User::where('id', $request->user_id)->first();
            $selected_plan = Plan::where('id', $plan_id)->first();
            $plan_ads_number = $selected_plan->ads_count;
            $plan_price = $selected_plan->price;
            //to select expire days of selected plane
            $plan_detail = Plan_details::where('plan_id', $plan_id)->where('type', 'expier_num')->first();
            $expire_days = $plan_detail->expire_days;
            //to get the expire_date of ad
            $mytime = Carbon::now();
            $today = Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');
            $ad_data = null;
            $pin = Plan_details::where('plan_id', $plan_id)->where('type', 'pin')->first();
            if ($pin != null) {
                $expire_pin_date = $pin->expire_days;
                $ad_data['pin'] = 1;
                //to create expire pin date
                $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                $final_expire_pin_date = $final_pin_date->addDays($expire_pin_date);
                $ad_data['expire_pin_date'] = $final_expire_pin_date;
            }
            $re_post = Plan_details::where('plan_id', $plan_id)->where('type', 're_post')->first();
            if ($re_post != null) {
                $expire_re_post_date = $re_post->expire_days;
                $ad_data['re_post'] = '1';
                //to create expire pin date
                $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                $final_expire_re_post_date = $final_pin_date->addDays($expire_re_post_date);
                $ad_data['re_post_date'] = $final_expire_re_post_date;
            }
            $special = Plan_details::where('plan_id', $plan_id)->where('type', 'special')->first();
            if ($special != null) {
                $expire_special_date = $special->expire_days;
                $ad_data['is_special'] = '1';
                //to create expire pin date
                $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                $final_expire_special_date = $final_pin_date->addDays($expire_special_date);
                $ad_data['expire_special_date'] = $final_expire_special_date;
            }

            $final_today = Carbon::createFromFormat('Y-m-d H:i', $today);
            $expire_date = $final_today->addDays($expire_days);

            $ad_data['publish'] = 'Y';
            $ad_data['plan_id'] = $plan_id;
            $ad_data['publication_date'] = $today;
            $ad_data['expiry_date'] = $expire_date;
            Product::where('id', $request->ad_id)->update($ad_data);

            return redirect('api/pay/success');
        } else {
            return redirect('api/pay/error');
        }
    }

    public function republish_ad(Request $request)
    {
        $user = auth()->user();
        if ($user == null) {
            $response = APIHelpers::createApiResponse(true, 406, 'you should login first', 'يجب تسجيل الدخول اولا', null, $request->lang);
            return response()->json($response, 406);
        }
        if ($user->active == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'your account un actived', 'تم حظر حسابك', null, $request->lang);
            return response()->json($response, 406);
        }
        $plan = Plan::where('id', $request->plan_id)->first();
        if ($user->my_wallet < $plan->price) {
            $response = APIHelpers::createApiResponse(true, 406, 'you don`t have enough balance to republish ad , please buy ads package', 'ليس لديك رصيد إعلانات لتجديد الإعلان يرجي شراء باقه إعلانات', null, $request->lang);
            return response()->json($response, 406);
        }
        $product = Product::where('id', $request->product_id)->where('user_id', $user->id)->first();
        if ($product->status == 1) {
            $response = APIHelpers::createApiResponse(true, 406, 'this ad not ended yet', 'هذا الاعلان لم ينتهى بعد', null, $request->lang);
            return response()->json($response, 406);
        }
        if ($product->deleted == 1) {
            $response = APIHelpers::createApiResponse(true, 406, 'this ad deleted before', 'هذا الاعلان تم حذفة', null, $request->lang);
            return response()->json($response, 406);
        }
        if ($product) {
            $plan_price = $plan->price;
            //to select expire days of selected plane
            $plan_detail = Plan_details::where('plan_id', $request->plan_id)->where('type', 'expier_num')->first();
            $expire_days = $plan_detail->expire_days;

            $user->my_wallet = $user->my_wallet - $plan_price;
            if ($user->free_balance >= $plan_price) {
                $user->free_balance = $user->free_balance - $plan_price;
            } else if ($user->payed_balance >= $plan_price) {
                $user->payed_balance = $user->payed_balance - $plan_price;
            } else {
                $free_balance = $user->free_balance;  //70
                $payed_balance = $user->payed_balance;  //30
                $price = $plan_price; //100
                $after_min_free = $price - $free_balance;  // 100 - 70 = 30
                if ($after_min_free <= $payed_balance && $after_min_free > 0) {
                    // 30 <= 30 && 30 < 0
                    $user->free_balance = 0;
                    $user->payed_balance = $user->payed_balance - $after_min_free;
                } else if ($after_min_free > $payed_balance && $after_min_free > 0) {
                    $after_min_payed = $price - $payed_balance;
                    $user->free_balance = $user->free_balance - $after_min_payed;
                    $user->payed_balance = 0;
                }
            }
            $user->save();
            //to get the expire_date of ad
            $mytime = Carbon::now();
            $today = Carbon::parse($mytime->toDateTimeString())->format('Y-m-d H:i');
            $pin = Plan_details::where('plan_id', $request->plan_id)->where('type', 'pin')->first();
            if ($pin != null) {
                $expire_pin_date = $pin->expire_days;
                $product->pin = 1;
                //to create expire pin date
                $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                $final_expire_pin_date = $final_pin_date->addDays($expire_pin_date);
                $product->expire_pin_date = $final_expire_pin_date;
            }
            $re_post = Plan_details::where('plan_id', $request->plan_id)->where('type', 're_post')->first();
            if ($re_post != null) {
                $expire_re_post_date = $re_post->expire_days;
                $product->re_post = '1';
                //to create expire pin date
                $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                $final_expire_re_post_date = $final_pin_date->addDays($expire_re_post_date);
                $product->re_post_date = $final_expire_re_post_date;
            }
            $special = Plan_details::where('plan_id', $request->plan_id)->where('type', 'special')->first();
            if ($special != null) {
                $expire_special_date = $special->expire_days;
                $product->is_special = '1';
                //to create expire pin date
                $final_pin_date = Carbon::createFromFormat('Y-m-d H:i', $today);
                $final_expire_special_date = $final_pin_date->addDays($expire_special_date);
                $product->expire_special_date = $final_expire_special_date;
            }

            $final_today = Carbon::createFromFormat('Y-m-d H:i', $today);
            $expire_date = $final_today->addDays($expire_days);
            $product->plan_id = $request->plan_id;
            $product->expiry_date = $expire_date;
            $product->status = 1;
            $product->publish = 'Y';
            $product->save();
            $response = APIHelpers::createApiResponse(false, 200, 'republish done', 'تم اعادة النشر بنجاح', null, $request->lang);
            return response()->json($response, 200);

        } else {
            $response = APIHelpers::createApiResponse(true, 406, 'ليس لديك الصلاحيه لتجديد هذا الاعلان', '', null, $request->lang);
            return response()->json($response, 406);
        }
    }

    public function select_my_ads(Request $request)
    {
        $ads['ended_ads'] = Product::where('status', 2)
            ->where('deleted', 0)
            ->where('user_id', auth()->user()->id)
            ->select('id', 'title', 'price', 'main_image')
            ->orderBy('created_at', 'desc')
            ->get();
        $ads['current_ads'] = Product::where('status', 1)
            ->where('publish', 'Y')
            ->where('deleted', 0)
            ->where('user_id', auth()->user()->id)
            ->select('id', 'title', 'price', 'main_image')
            ->orderBy('created_at', 'desc')
            ->get();
        if (count($ads) == 0) {
            $response = APIHelpers::createApiResponse(false, 200, 'no ads yet !', ' !لا يوجد اعلانات حتى الان', null, $request->lang);
            return response()->json($response, 200);
        } else {
            $response = APIHelpers::createApiResponse(false, 200, '', '', $ads, $request->lang);
            return response()->json($response, 200);
        }
    }

    public function ended_ads(Request $request)
    {
        $data = Product::where('status', 2)
            ->where('deleted', 0)
            ->where('user_id', auth()->user()->id)
            ->select('id', 'title', 'price', 'main_image')
            ->orderBy('created_at', 'desc')
            ->get();
        if (count($data) == 0) {
            $response = APIHelpers::createApiResponse(false, 200, 'no ads yet !', ' !لا يوجد اعلانات حتى الان', null, $request->lang);
            return response()->json($response, 200);
        } else {
            $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
            return response()->json($response, 200);
        }
    }

    public function current_ads(Request $request)
    {
        $data = Product::where('status', 1)
            ->where('publish', 'Y')
            ->where('deleted', 0)
            ->where('user_id', auth()->user()->id)
            ->select('id', 'title', 'price', 'main_image')
            ->orderBy('created_at', 'desc')
            ->get();
        if (count($data) == 0) {
            $response = APIHelpers::createApiResponse(false, 200, 'no ads yet !', ' !لا يوجد اعلانات حتى الان', null, $request->lang);
            return response()->json($response, 200);
        } else {
            $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
            return response()->json($response, 200);
        }
    }

    public function last_seen(Request $request)
    {
        $lang = $request->lang ;
        Session::put('api_lang', $lang);
        $user = auth()->user();
        if ($user == null) {
            $response = APIHelpers::createApiResponse(true, 406, 'you should login first', 'يجب تسجيل الدخول اولا', null, $request->lang);
            return response()->json($response, 406);
        }

        $products = Product_view::has('product', '>', 0)->where('user_id', auth()->user()->id)
            ->select('product_id', 'user_id')
            ->orderBy('created_at', 'desc')
            ->simplePaginate(12);
//        $products = $products
//            ->simplePaginate(12);

        if (count($products) > 0) {
            for ($i = 0; $i < count($products); $i++) {
                $products[$i]['show_price'] = true;
                if ($products[$i]['Product']->price == 0) {
                    $products[$i]['show_price'] = false;
                }
                if($lang == 'ar'){
                    $products[$i]['Product']->address = $products[$i]['Product']->City->title_ar .' , '.$products[$i]['Product']->Area->title_ar;
                }else{
                    $products[$i]['Product']->address = $products[$i]['Product']->City->title_en .' , '.$products[$i]['Product']->Area->title_en;
                }
                $products[$i]['Product']->price  = number_format((float)(  $products[$i]['Product']->price ), 3);
                if ($user) {
                    $favorite = Favorite::where('user_id', $user->id)->where('product_id', $products[$i]['product_id'])->first();
                    if ($favorite) {
                        $products[$i]['Product']->favorite  = true;
                    } else {
                        $products[$i]['Product']->favorite = false;
                    }

                    $conversation = Participant::where('ad_product_id', $products[$i]['product_id'])->where('user_id', $user->id)->first();
                    if ($conversation == null) {
                        $products[$i]['Product']->conversation_id = 0;
                    } else {
                        $products[$i]['Product']->conversation_id = $conversation->conversation_id;
                    }
                } else {
                    $products[$i]['Product']->favorite = false;
                    $products[$i]['Product']->conversation_id = 0;
                }

                $products[$i]['Product']->time = APIHelpers::get_month_day( $products[$i]['Product']->created_at , $lang);
            }
        }
        
        $response = APIHelpers::createApiResponse(false, 200, '', '', $products, $request->lang);
        return response()->json($response, 200);
    }

    public function offer_ads(Request $request)
    {
        $user = auth()->user();
        $lang = $request->lang;
        if ($user == null) {
            $response = APIHelpers::createApiResponse(true, 406, 'you should login first', 'يجب تسجيل الدخول اولا', null, $request->lang);
            return response()->json($response, 406);
        }
        if ($lang == 'ar') {
            $offer_image = Setting::where('id', 1)->first()->offer_image;
        } else {
            $offer_image = Setting::where('id', 1)->first()->offer_image_en;
        }
        $ads = Product::where('offer', 1)
            ->where('status', 1)
            ->where('deleted', 0)
            ->where('publish', 'Y')
            ->orderBy('created_at', 'desc')
            ->get();
        $inc = 0;
        foreach ($ads as $key => $row) {
            $data[$inc]['id'] = $row->id;
            $data[$inc]['title'] = $row->title;
            $data[$inc]['image'] = $row->main_image;
            $data[$inc]['price'] = $row->price;
            $data[$inc]['description'] = $row->description;
            $favorite = Favorite::where('user_id', $user->id)->where('product_id', $row->id)->first();
            if ($favorite) {
                $data[$inc]['favorite'] = true;
            } else {
                $data[$inc]['favorite'] = false;
            }
            $inc = $inc + 1;
        }
        if (count($data) == 0) {
            $response = APIHelpers::createApiResponse(false, 200, 'no ads yet !', ' !لا يوجد اعلانات حتى الان', null, $request->lang);
            return response()->json($response, 200);
        } else {
            $response = APIHelpers::createApiResponse(false, 200, '', '', array('offer_image' => $offer_image, 'data' => $data), $request->lang);
            return response()->json($response, 200);
        }
    }

    public function select_current_ads(Request $request)
    {
        $ads = Product::where('status', 1)
            ->where('publish', 'Y')
            ->where('deleted', 0)
            ->where('user_id', auth()->user()->id)
            ->select('id', 'title', 'price', 'main_image', 'views', 'pin', 'publication_date')
            ->orderBy('pin', 'desc')
            ->orderBy('created_at', 'desc')
            ->simplePaginate(12)
            ->map(function ($ads) {
                $ads->views = Product_view::where('product_id', $ads->id)->count();
                return $ads;
            });
        if (count($ads) == 0) {
            $response = APIHelpers::createApiResponse(false, 200, 'no ended ads yet !', ' !لا يوجد اعلانات منتهيه حتى الان', null, $request->lang);
            return response()->json($response, 200);
        } else {
            $response = APIHelpers::createApiResponse(false, 200, '', '', $ads, $request->lang);
            return response()->json($response, 200);
        }
    }

    public function select_all_ads(Request $request)
    {
        $ads = Product::where('status', 1)
            ->where('publish', 'Y')
            ->where('deleted', 0)
            ->where('user_id', auth()->user()->id)
            ->select('id', 'title', 'price', 'main_image', 'pin', 'user_id')
            ->orderBy('pin', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        if (count($ads) == 0) {
            $response = APIHelpers::createApiResponse(false, 200, 'no  ads until now !', ' !لا يوجد اعلانات حتى الان', null, $request->lang);
            return response()->json($response, 200);
        } else {
            $response = APIHelpers::createApiResponse(false, 200, '', '', $ads, $request->lang);
            return response()->json($response, 200);
        }
    }

    public function delete_my_ad(Request $request, $id)
    {
        $user = auth()->user();
        if ($user != null) {
            $product = Product::where('id', $id)->first();
            if ($product != null) {
                if ($product->user_id == $user->id) {
                    $product->deleted = 1;
                    $product->save();
                    $response = APIHelpers::createApiResponse(false, 200, 'deleted', 'تم الحذف بنجاح', null, $request->lang);
                    return response()->json($response, 200);
                } else {
                    $response = APIHelpers::createApiResponse(true, 406, 'this not your ad', 'لا تمتلك هذا الاعلان !!', null, $request->lang);
                    return response()->json($response, 406);
                }
            } else {
                $response = APIHelpers::createApiResponse(true, 406, 'no ad of this id', 'لا يوجد اعلان بهذا ال id', null, $request->lang);
                return response()->json($response, 406);
            }
        } else {
            $response = APIHelpers::createApiResponse(true, 406, 'you should login first ', 'يجب تسجيل الدخول أولا !!', null, $request->lang);
            return response()->json($response, 406);
        }
    }

    public function select_ad_data(Request $request, $id)
    {
        $lang = $request->lang ;
        Session::put('local_api', $lang);
        $data['ad'] = Product::where('id', $id)
            ->with('City_api')
            ->with('Area_api')
            ->select('id', 'category_id', 'sub_category_id', 'sub_category_two_id', 'sub_category_three_id', 'sub_category_four_id', 'sub_category_five_id', 'title', 'price', 'description', 'main_image', 'city_id', 'area_id', 'share_location', 'latitude', 'longitude', 'prevent_comments')
            ->first();
        if ($data['ad']['sub_category_id'] == null) {
            $data['ad']['sub_category_id'] = 0;
        }
        if ($data['ad']['sub_category_two_id'] == null) {
            $data['ad']['sub_category_two_id'] = 0;
        }
        if ($data['ad']['sub_category_three_id'] == null) {
            $data['ad']['sub_category_three_id'] = 0;
        }
        if ($data['ad']['sub_category_four_id'] == null) {
            $data['ad']['sub_category_four_id'] = 0;
        }
        if ($data['ad']['sub_category_five_id'] == null) {
            $data['ad']['sub_category_five_id'] = 0;
        }
        if ($data['ad']->share_location == '1') {
            $data['ad']->share_location = true;
        } else {
            $data['ad']->share_location = false;
        }
        $data['ad_images'] = ProductImage::where('product_id', $id)->select('id', 'image', 'product_id')->get();
        if ($request->lang == 'ar') {
            if ($data['ad']->city_id != null) {
                $cat_data_city = City::find($data['ad']->city_id);
                $data['area_names'] = $cat_data_city->title_ar;
            }
            if ($data['ad']->area_id != null) {
                $cat_data_area = Area::find($data['ad']->area_id);
                $data['area_names'] = $data['area_names'] . '/' . $cat_data_area->title_ar;
            }
            if ($data['ad']->category_id != null) {
                $cat_data = Category::find($data['ad']->category_id);
                $data['category_names'] = $cat_data->title_ar;
            }
            if ($data['ad']->sub_category_id != null) {
                $scat_data = SubCategory::find($data['ad']->sub_category_id);
                $data['category_names'] = $data['category_names'] . '/' . $scat_data->title_ar;
            }
            if ($data['ad']->sub_category_two_id != null) {
                $sscat_data = SubTwoCategory::find($data['ad']->sub_category_two_id);
                $data['category_names'] = $data['category_names'] . '/' . $sscat_data->title_ar;
            }
            if ($data['ad']->sub_category_three_id != null) {
                $ssscat_data = SubThreeCategory::find($data['ad']->sub_category_three_id);
                $data['category_names'] = $data['category_names'] . '/' . $ssscat_data->title_ar;
            }
            if ($data['ad']->sub_category_four_id != null) {
                $sssscat_data = SubFourCategory::find($data['ad']->sub_category_four_id);
                $data['category_names'] = $data['category_names'] . '/' . $sssscat_data->title_ar;
            }
            if ($data['ad']->sub_category_five_id != null) {
                $ssssscat_data = SubFiveCategory::find($data['ad']->sub_category_five_id);
                $data['category_names'] = $data['category_names'] . '/' . $ssssscat_data->title_ar;
            }
        } else {
            if ($data['ad']->city_id != null) {
                $cat_data_city = City::find($data['ad']->city_id);
                $data['area_names'] = $cat_data_city->title_en;
            }
            if ($data['ad']->area_id != null) {
                $cat_data_area = Area::find($data['ad']->area_id);
                $data['area_names'] = $data['area_names'] . '/' . $cat_data_area->title_en;
            }
            if ($data['ad']->category_id != null) {
                $cat_data = Category::find($data['ad']->category_id);
                $data['category_names'] = $cat_data->title_en;
            }
            if ($data['ad']->sub_category_id != null) {
                $scat_data = SubCategory::find($data['ad']->sub_category_id);
                $data['category_names'] = $data['category_names'] . '/' . $scat_data->title_en;
            }
            if ($data['ad']->sub_category_two_id != null) {
                $sscat_data = SubTwoCategory::find($data['ad']->sub_category_two_id);
                $data['category_names'] = $data['category_names'] . '/' . $sscat_data->title_en;
            }
            if ($data['ad']->sub_category_three_id != null) {
                $ssscat_data = SubThreeCategory::find($data['ad']->sub_category_three_id);
                $data['category_names'] = $data['category_names'] . '/' . $ssscat_data->title_en;
            }
            if ($data['ad']->sub_category_four_id != null) {
                $sssscat_data = SubFourCategory::find($data['ad']->sub_category_four_id);
                $data['category_names'] = $data['category_names'] . '/' . $sssscat_data->title_en;
            }
            if ($data['ad']->sub_category_five_id != null) {
                $ssssscat_data = SubFiveCategory::find($data['ad']->sub_category_five_id);
                $data['category_names'] = $data['category_names'] . '/' . $ssssscat_data->title_en;
            }
        }

        $features = Product_feature::where('product_id', $id)
            ->select('id', 'type', 'product_id', 'target_id', 'option_id')
            ->orderBy('option_id', 'asc')
            ->get();

        foreach ($features as $key => $feature) {
            if ($feature->type == 'manual') {
                $features[$key]['type'] = 'input';
                $features[$key]['value'] = $feature->target_id;
            } else if ($feature->type == 'option') {
                $features[$key]['type'] = 'select';
                $target_data = Category_option_value::where('id', $feature->target_id)->first();
                if ($request->lang == 'ar')
                    $features[$key]['value'] = $target_data->value_ar;
                else {
                    $features[$key]['value'] = $target_data->value_en;
                }
            }
        }

        $data['options'] = Category_option::where('cat_id', $data['ad']->category_id)->where('cat_type', 'category')->where('deleted', '0')->select('id as option_id', 'title_' . $lang . ' as title', 'is_required')->get();

        if (count($data['options']) > 0) {
            for ($i = 0; $i < count($data['options']); $i++) {
                $option_id = $data['options'][$i]['option_id'];
                $data['options'][$i]['type'] = 'input';
                $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])
                    ->where('deleted', '0')->select('id as value_id', 'value_' . $lang . ' as value')
                    ->get()->map(function ($data) use ($id, $option_id) {
                        $data->selected = false;
//                        dd($data->option_id);
                        $inserted_in_db = Product_feature::where('option_id', $option_id)->where('product_id', $id)->first();
                        if ($inserted_in_db) {
                            if ($data->value_id == $inserted_in_db->target_id) {
                                $data->selected = true;
                            }
                        }

                        return $data;
                    });
                if (count($optionValues) > 0) {
                    $data['options'][$i]['type'] = 'select';
                    $data['options'][$i]['values'] = $optionValues;
                } else {
                    $inserted_in_db = Product_feature::where('option_id', $option_id)->where('product_id', $id)->first();
                    if ($inserted_in_db) {
                        $data['options'][$i]['value'] = $inserted_in_db->target_id;
                    } else {
                        $data['options'][$i]['value'] = "";
                    }
                }
            }
        }
        $response = APIHelpers::createApiResponse(false, 200, 'data shown', 'تم أظهار البيانات', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function remove_main_image(Request $request, $id)
    {
        $data['main_image'] = null;
        $final_data = Product::where('id', $id)->update($data);

        if ($final_data == 1) {
            $data_f['status'] = true;
            $response = APIHelpers::createApiResponse(false, 200, 'data updated', 'تم تعديل البيانات', $data_f, $request->lang);
            return response()->json($response, 200);
        } else {
            $data_f['status'] = false;
            $response = APIHelpers::createApiResponse(true, 406, 'not updated', 'لم يتم التعديل', $data_f, $request->lang);
            return response()->json($response, 406);
        }
    }

    public function remove_product_image(Request $request, $image_id)
    {

        $final_data = ProductImage::where('id', $image_id)->delete();
        if ($final_data == 1) {
            $data_f['status'] = true;
            $response = APIHelpers::createApiResponse(false, 200, 'data deleted', 'تم الحذف البيانات', $data_f, $request->lang);
            return response()->json($response, 200);
        } else {
            $data_f['status'] = false;
            $response = APIHelpers::createApiResponse(true, 406, 'not deleted', 'لم يتم الحذف', $data_f, $request->lang);
            return response()->json($response, 406);
        }
    }

    public function update_ad(Request $request, $id)
    {
        $input = $request->all();
        $product = Product::where('id', $id)->first();
        if ($product == null) {
            $response = APIHelpers::createApiResponse(true, 406, 'ad not exists', 'لا يوجد اعلان بهذا ال id', null, $request->lang);
            return response()->json($response, 406);
        }
        $validator = Validator::make($input, [
            'category_id' => 'required',
            'sub_category_id' => '',
            'sub_category_two_id' => '',
            'sub_category_three_id' => '',
            'sub_category_four_id' => '',
            'sub_category_five_id' => '',
            'title' => 'required',
            'city_id' => 'required',
            'area_id' => 'required',
            'share_location' => 'required',
            'latitude' => '',
            'longitude' => '',
            'options' => '',
            'price' => 'required|numeric',
            'description' => '',
            'main_image' => '',
            'images' => ''
        ]);
        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, $validator->messages()->first(), $validator->messages()->first(), null, $request->lang);
            return response()->json($response, 406);
        } else {
            $user = auth()->user();
            if ($user != null) {
                $input['user_id'] = $user->id;
            } else {
                $response = APIHelpers::createApiResponse(true, 406, 'you should login first', 'يجب تسجيل الدخول اولا', null, $request->lang);
                return response()->json($response, 406);
            }
            if ($request->main_image != null) {
                $image = $request->main_image;
                Cloudder::upload("data:image/jpeg;base64," . $image, null);
                $imagereturned = Cloudder::getResult();
                $image_id = $imagereturned['public_id'];
                $image_format = $imagereturned['format'];
                $image_new_name = $image_id . '.' . $image_format;
                $input['main_image'] = $image_new_name;
            }
            if ($request->images != null) {
                if($request->ios == 'ios'){
                    ProductImage::where('product_id',$id)->delete();
                }
                foreach ($request->images as $image) {
                    Cloudder::upload("data:image/jpeg;base64," . $image, null);
                    $imagereturned = Cloudder::getResult();
                    $image_id = $imagereturned['public_id'];
                    $image_format = $imagereturned['format'];
                    $image_name = $image_id . '.' . $image_format;
                    $data['product_id'] = $id;
                    $data['image'] = $image_name;
                    ProductImage::create($data);
                }
            }
            unset($input['images']);
            unset($input['options']);
            unset($input['ios']);
            $preventComments = 0;
            if ($request->prevent_comments) {
                $preventComments = $request->prevent_comments;
            }
            $input['prevent_comments'] = $preventComments;
            if ($request->share_location == 0) {
                $input['share_location'] = '0';
            } else {
                $input['share_location'] = '1';
            }
            $updated = Product::where('id', $id)->update($input);
            if ($request->options != null) {
                Product_feature::where('product_id', $id)->delete();
                foreach ($request->options as $key => $option) {
                    if ($option['option_value'] != null) {
                        if (is_numeric($option['option_value'])) {
                            $option_values = Category_option_value::where('id', $option['option_value'])->first();
                            if ($option_values != null) {
                                $feature_data['type'] = 'option';
                            } else {
                                $feature_data['type'] = 'manual';
                            }
                        } else {
                            $feature_data['type'] = 'manual';
                        }
                        $feature_data['product_id'] = $id;
                        $feature_data['target_id'] = $option['option_value'];
                        $feature_data['option_id'] = $option['option_id'];
                        Product_feature::create($feature_data);
                    }
                }
            }
            if ($updated == 1) {
                $final_data['status'] = true;
                $response = APIHelpers::createApiResponse(false, 200, 'updated successfuly', 'تم التعديل بنجاح', $final_data, $request->lang);
                return response()->json($response, 200);
            } else {
                $data_f['status'] = false;
                $response = APIHelpers::createApiResponse(true, 406, 'not updated', 'لم يتم التعديل', $data_f, $request->lang);
                return response()->json($response, 406);
            }
        }
    }

    public function cities(Request $request)
    {
        Session::put('api_lang', $request->lang);
        if ($request->lang == 'en') {
            $cities = City::with('Areas')
                ->where('deleted', '0')
                ->select('id', 'title_en as title')
                ->get();
        } else {
            $cities = City::with('Areas')
                ->where('deleted', '0')
                ->select('id', 'title_ar as title')
                ->get();
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', array('cities' => $cities), $request->lang);
        return response()->json($response, 200);
    }

    public function areas( Request $request , $city_id )
    {
        Session::put('api_lang', $request->lang);

        $areas = [];
        if ($request->lang == 'en') {
            $areas = Area::where('city_id',$city_id)->where('deleted', '0')
                ->select('id', 'title_en as title')
                ->get();
        } else {
            $areas = Area::where('city_id',$city_id)->where('deleted', '0')
                ->select('id', 'title_ar as title')
                ->get();
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $areas, $request->lang);
        return response()->json($response, 200);
    }


    public function make_report(Request $request)
    {
        $user = auth()->user();
        if ($user->active == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'تم حظر حسابك', 'تم حظر حسابك', null, $request->lang);
            return response()->json($response, 406);
        }
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'report_content' => 'required',
        ]);
        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, $validator->errors()->first(), $validator->errors()->first(), null, $request->lang);
            return response()->json($response, 406);
        }
        $reported = Product_report::where('product_id', $request->product_id)->where('user_id', $user->id)->first();
        if ($reported) {
            $response = APIHelpers::createApiResponse(true, 406, 'this ad reported before', 'تم الابلاغ عن هذا الاعلان من قبل', null, $request->lang);
            return response()->json($response, 406);
        } else {
            $favorite = new Product_report();
            $favorite->user_id = $user->id;
            $favorite->product_id = $request->product_id;
            $favorite->report_content = $request->report_content;
            $favorite->save();
            $response = APIHelpers::createApiResponse(false, 200, '', '', $favorite, $request->lang);
            return response()->json($response, 200);
        }
    }

    public function make_comment(Request $request)
    {
        $user = auth()->user();
        if ($user->active == 0) {
            $response = APIHelpers::createApiResponse(true, 406, 'your account blocked', 'تم حظر حسابك', null, $request->lang);
            return response()->json($response, 406);
        }
        $validator = Validator::make($request->all(), [
            'comment' => 'required',
            'product_id' => 'required|exists:products,id',
        ]);
        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, $validator->errors()->first(), $validator->errors()->first(), null, $request->lang);
            return response()->json($response, 406);
        }

        $comment = new Product_comment();
        $comment->comment = $request->comment;
        $comment->user_id = $user->id;
        $comment->product_id = $request->product_id;
        $comment->save();
        $product = Product::where('id', $request->product_id)->select('id', 'user_id', 'title')->first();
        $lastToken = Visitor::where('user_id', $product->user_id)->where('fcm_token' ,'!=' , null)->latest('updated_at')->select('id', 'fcm_token')->first();

        $title = "الإعلان";
        $body = $user->name . " has added a new comment on your ad " . $product->title;
        if ($request->lang == 'ar') {
            $body = $user->name . " قام بإضافة تعليق جديد على إعلانك " . $product->title;
        }
        if ($lastToken) {
            $notification = Notification::create(['title' => $title, 'body' => $body, 'ad_id' => $request->product_id]);
            UserNotification::create([
                'user_id' => $user->id,
                'notification_id' => $notification->id,
                'visitor_id' => $lastToken->id
                ]);
            $notificationss = APIHelpers::send_notification($title , $body , "", (object)['ad_id' => $request->product_id] , [$lastToken->fcm_token]);
        }


        $response = APIHelpers::createApiResponse(false, 200, 'commented successfully', 'تم التعليق بنجاح', $comment, $request->lang);
        return response()->json($response, 200);
    }

    // craete report
    public function createCommentReport(Request $request) {
        $user_id = auth()->user()->id;
        $post = $request->all();
        $post['user_id'] = $user_id;
        $commentIdRequired = "comment id is required field";
        $productIdRequired = "Product id is required field";
        $reportRequired = "Report is required field";
        if ($request->lang == 'ar') {
            $reportRequired = "البلاغ حقل مطلوب";
        }
        $messages = [
            "comment_id.required" => $commentIdRequired,
            "report.required" => $reportRequired,
            "product_id.required" => $productIdRequired
        ];
        $validator = Validator::make($request->all(), [
            'comment_id' => 'required',
            'product_id' => 'required',
            "report" => 'required'
        ], $messages);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 , $validator->messages()->first() , $validator->messages()->first()  , null , $request->lang);
            return response()->json($response , 406);
        }

        CommentReport::create($post);

        $response = APIHelpers::createApiResponse(false, 200, 'reported successfully', 'تم إرسال البلاغ بنجاح', (object)[], $request->lang);
        return response()->json($response, 200);
    }

}
