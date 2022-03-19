<?php

namespace App\Http\Controllers;

use App\Participant;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Category_option_value;
use Illuminate\Http\Request;
use App\Helpers\APIHelpers;
use App\SubThreeCategory;
use App\SubFiveCategory;
use App\Category_option;
use App\SubFourCategory;
use App\SubTwoCategory;
use App\Product_view;
use App\SubCategory;
use App\Favorite;
use App\Category;
use App\Product;
use App\Setting;
use App\Visitor;


class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['getSubTwoCategoryOptions', 'getSubCategoryOptions', 'show_six_cat', 'getCategoryOptions', 'show_five_cat', 'show_four_cat', 'show_third_cat', 'show_second_cat', 'show_first_cat', 'getcategories', 'getAdSubCategories', 'get_sub_categories_level2', 'get_sub_categories_level3', 'get_sub_categories_level4', 'get_sub_categories_level5', 'getproducts']]);
    }

    

    public function getCatsSubCats($model, $lang, $show=true, $cat_id=0, $city_id=0, $all=false, $whereIn=[]) {
        $categories = $model::where('deleted', 0);
        if ($city_id != 0) {
            $categories = $categories->whereHas('Products', function($q) use ($city_id) {
                $q->where('city_id', $city_id);
            });
        }
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
        ->map(function ($row) use ($show, $model) {
            if ($show) {
                $row->products_count = count($row->products);
            }
            $row->next_level = false;
            $subCategories = $row->ViewSubCategories;
            
            if ($subCategories && count($subCategories) > 0) {
                // $hasProducts = false;
                // for ($n = 0; $n < count($subCategories); $n++) {
                    
                //     if ($model != '\App\SubFiveCategory') {
                //         if ($subCategories[$n]->products != null && count($subCategories[$n]->products) > 0) {
                //             $hasProducts = true;
                //         }
                //     }
                // }
                // if ($hasProducts) {
                    $row->next_level = true;
                // }
            }
            $row->selected = false;
            return $row;
        })->toArray();

        if ($all) {
            $title = 'All';
            if ($lang == 'ar') {
                $title = 'الكل';
            }
            $all = [
                'id' => 0,
                'image' => "",
                'title' => $title,
                'next_level' => false,
                'selected' => false
            ];
            
            array_unshift($categories, $all);
        }

        return $categories;
    }

    public function getcategories(Request $request)
    {
        $lang = $request->lang;
        $categories = Category::where(function ($q) {
            $q->has('SubCategories', '>', 0)->orWhere(function ($qq) {
                $qq->has('Products', '>', 0);
            });
        })->where('deleted', 0)->select('id', 'title_' . $lang . ' as title', 'image')->orderBy('sort', 'asc')->get();

        for ($i = 0; $i < count($categories); $i++) {
            //text next level
            $subTwoCats = SubCategory::where('category_id', $categories[$i]['id'])->where('deleted', 0)->select('id')->first();
            $categories[$i]['next_level'] = false;
            if (isset($subTwoCats['id'])) {
                $categories[$i]['next_level'] = true;
            }

/*
            if ($categories[$i]['next_level'] == true) {
                // check after this level layers
                $data_ids = SubCategory::where('deleted', '0')->where('category_id', $categories[$i]['id'])->select('id')->get()->toArray();
                $subFiveCats = SubTwoCategory::whereIn('sub_category_id', $data_ids)->where('deleted', '0')->select('id', 'deleted')->get();
                if (count($subFiveCats) == 0) {
                    $have_next_level = false;
                } else {
                    $have_next_level = true;
                }
                if ($have_next_level == false) {
                    $categories[$i]['next_level'] = false;
                } else {
                    $categories[$i]['next_level'] = true;
                    break;
                }
                //End check
            }
            */
        }

        // $data = Categories_ad::select('image','ad_type','content as link')->where('type','category')->inRandomOrder()->take(1)->get();
        $response = APIHelpers::createApiResponse(false, 200, '', '', array('categories' => $categories), $request->lang);
        return response()->json($response, 200);
    }

    // get ad subcategories
    public function getAdSubCategories(Request $request)
    {
        if (!$request->header('uniqueid')) {
            $response = APIHelpers::createApiResponse(true , 406 , 'unique id required header' , 'unique id required header'  , null , $request->lang);
            return response()->json($response , 406);
        }
        $visitor = Visitor::where('unique_id', $request->header('uniqueid'))->select('city_id', 'area_id', 'unique_id')->first();
        if (!$visitor) {
            $response = APIHelpers::createApiResponse(true , 406 , 'there is no such visitor' , 'there is no such visitor'  , null , $request->lang);
            return response()->json($response , 406);
        }
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        if ($request->category_id != 0) {
            $data['sub_categories'] = $this->getCatsSubCats('\App\SubCategory', $lang, false, $request->category_id, $visitor->city_id,  true);
            $data['category'] = Category::select('id', 'title_en as title')->find($request->category_id);
        }else {
            $categories = $this->getCatsSubCats('\App\Category', $lang, true, 0, $visitor->city_id, false);
            $plukCats = [];
            for ($i = 0; $i < count($categories); $i ++) {
                array_push($plukCats, $categories[$i]['id']);
            }
            $main = 'Main';
            if ($request->lang == 'ar') {
                $main = 'الرئيسية';
            }
            $data['sub_categories'] = $this->getCatsSubCats('\App\SubCategory', $lang, false, 0, $visitor->city_id, true, $plukCats);
            $data['category'] = (object)[
                'id' => 0,
                'title' => $main
            ];
       
        }

        $lang = $request->lang;
        
        $products = Product::where('status', 1)->where('publish', 'Y')->where('deleted', 0)->where('reviewed', 1)->with('Publisher');
        if ($request->category_id != 0) {
            $products = $products->where('category_id', $request->category_id);
        }
        if ($visitor->city_id != 0) {
            $products = $products->where('city_id', $visitor->city_id);
        }
        if ($visitor->area_id != 0) {
            $products = $products->where('area_id', $visitor->area_id);
        }
        $products = $products->select('id', 'title', 'main_image as image', 'created_at', 'user_id','city_id','area_id', 'price', 'views')
        ->orderBy('created_at', 'desc')->simplePaginate(12);
        $data['show_views'] = Setting::where('id', 1)->select('show_views')->first()['show_views'];
            
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['show_price'] = true;
            if ($products[$i]['price'] == 0) {
                $products[$i]['show_price'] = false;
            } 
            if($lang == 'ar'){
                $products[$i]['address'] = $products[$i]['City']->title_ar;
            }else{
                $products[$i]['address'] = $products[$i]['City']->title_en;
            }
            
            $products[$i]['price'] = number_format((float)$products[$i]['price'], 3, '.', '');
            
            $user = auth()->user();
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
            $products[$i]['time'] = $products[$i]['created_at']->diffForHumans(['long' => true, 'parts' => 2, 'join' => ' و ']);
        }

        $data['products'] = $products;
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function get_sub_categories_level2(Request $request)
    {
        if (!$request->header('uniqueid')) {
            $response = APIHelpers::createApiResponse(true , 406 , 'unique id required header' , 'unique id required header'  , null , $request->lang);
            return response()->json($response , 406);
        }
        $validator = Validator::make($request->all(), [
            'category_id' => 'required'
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, 'بعض الحقول مفقودة', 'بعض الحقول مفقودة', null, $request->lang);
            return response()->json($response, 406);
        }
        $visitor = Visitor::where('unique_id', $request->header('uniqueid'))->select('city_id', 'area_id', 'unique_id')->first();
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        if (!$visitor) {
            $response = APIHelpers::createApiResponse(true , 406 , 'there is no such visitor' , 'there is no such visitor'  , null , $request->lang);
            return response()->json($response , 406);
        }
        if ($request->sub_category_id != 0) {
            $data['sub_categories'] = $this->getCatsSubCats('\App\SubTwoCategory', $lang, false, $request->sub_category_id, $visitor->city_id, true);
            $data['sub_category_level1'] = SubCategory::where('id', $request->sub_category_id)->select('id', 'title_' . $lang . ' as title', 'category_id')->first();
            
            $data['category'] = Category::where('id', $data['sub_category_level1']['category_id'])->select('id', 'title_' . $lang . ' as title')->first();
        } else {
            $pluckSubCats = SubCategory::where('category_id', $request->category_id)->where('deleted', 0)->pluck('id')->toArray();
            $data['sub_categories'] = $this->getCatsSubCats('\App\SubTwoCategory', $lang, false, 0, $visitor->city_id, true, $pluckSubCats);
            $data['sub_category_level1'] = (object)[
                "id" => 0,
                "title" => "All",
                "category_id" => (int)$request->category_id
            ];
            
            $data['category'] = Category::where('id', $request->category_id)->select('id', 'title_' . $lang . ' as title')->first();
        }

        
        $lang = $request->lang;
        
        $products = Product::where('status', 1)->where('publish', 'Y')->where('deleted', 0)->where('reviewed', 1);
        
        if ($visitor->city_id != 0) {
            $products = $products->where('city_id', $visitor->city_id);
        }

        if ($request->sub_category_id != 0) {
            $products = $products->where('sub_category_id', $request->sub_category_id);
        }

        if ($visitor->area_id != 0) {
            $products = $products->where('area_id', $visitor->area_id);
        }
        
        $products = $products->with('Publisher')
                ->select('id', 'title', 'main_image as image', 'created_at', 'user_id','city_id','area_id', 'price', 'views')
                ->orderBy('created_at', 'desc')->simplePaginate(12);
       
       
        $data['show_views'] = Setting::where('id', 1)->select('show_views')->first()['show_views'];
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['show_price'] = true;
            if ($products[$i]['price'] == 0) {
                $products[$i]['show_price'] = false;
            } 
            if($lang == 'ar'){
                $products[$i]['address'] = $products[$i]['City']->title_ar;
            }else{
                $products[$i]['address'] = $products[$i]['City']->title_en;
            }
            $products[$i]['price'] = number_format((float)$products[$i]['price'], 3, '.', '');
            
            $user = auth()->user();
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
            $products[$i]['time'] = $products[$i]['created_at']->diffForHumans(['long' => true, 'parts' => 2, 'join' => ' و ']);
        }

        $data['products'] = $products;
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function get_sub_categories_level3(Request $request)
    {
        if (!$request->header('uniqueid')) {
            $response = APIHelpers::createApiResponse(true , 406 , 'unique id required header' , 'unique id required header'  , null , $request->lang);
            return response()->json($response , 406);
        }
        $validator = Validator::make($request->all(), [
            'category_id' => 'required'
        ]);

        if ($validator->fails() && !isset($request->sub_category_level1_id)) {
            $response = APIHelpers::createApiResponse(true, 406, 'بعض الحقول مفقودة', 'بعض الحقول مفقودة', null, $request->lang);
            return response()->json($response, 406);
        }
        $visitor = Visitor::where('unique_id', $request->header('uniqueid'))->select('city_id', 'area_id', 'unique_id')->first();
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        if (!$visitor) {
            $response = APIHelpers::createApiResponse(true , 406 , 'there is no such visitor' , 'there is no such visitor'  , null , $request->lang);
            return response()->json($response , 406);
        }

        $subCategories = SubCategory::where('category_id', $request->category_id)->pluck('id')->toArray();
        $subCategoriesTwo = SubTwoCategory::where('deleted', 0)->whereIn('sub_category_id', $subCategories)->pluck('id')->toArray();

        if ($request->sub_category_id != 0) {
            $data['sub_categories'] = $this->getCatsSubCats('\App\SubThreeCategory', $lang, false, $request->sub_category_id, $visitor->city_id, true);

            $data['sub_category_level2'] = SubTwoCategory::where('id', $request->sub_category_id)->select('id', 'title_' . $lang . ' as title', 'sub_category_id')->first();
            
            $data['category'] = Category::where('id', $request->category_id)->select('id', 'title_' . $lang . ' as title')->first();
        } else {

            $data['sub_category_level2'] = (object)[
                "id" => 0,
                "title" => "All",
                "sub_category_id" => (int)$request->sub_category_level1_id
            ];
            
            $secondIds = SubTwoCategory::where('sub_category_id', $request->sub_category_level1_id)->where('deleted', 0)->pluck('id')->toArray();
            
            $data['sub_categories'] = $this->getCatsSubCats('\App\SubThreeCategory', $lang, false, 0, $visitor->city_id, true, $secondIds);

            $data['category'] = Category::where('id', $request->category_id)->select('id', 'title_' . $lang . ' as title')->first();


        }


        for ($i = 0; $i < count($data['sub_categories']); $i++) {
            $cat_ids[$i] = $data['sub_categories'][$i]['id'];
        }
        
        //end all button
        $products = Product::where('status', 1)->where('deleted', 0)->where('reviewed', 1)->where('publish', 'Y')->with('Publisher')
            ->where('category_id', $request->category_id)->select('id', 'title', 'main_image as image', 'created_at', 'user_id','city_id','area_id', 'price', 'views');
        if ($request->sub_category_id != 0) {
            $products = $products->where('sub_category_two_id', $request->sub_category_id);
        }

        if ($visitor->city_id != 0) {
            $products = $products->where('city_id', $visitor->city_id);
        }

        if ($visitor->area_id != 0) {
            $products = $products->where('area_id', $visitor->area_id);
        }

        if ($request->sub_category_level1_id != 0) {
            $products = $products->where('sub_category_id', $request->sub_category_level1_id);
        }

        $products = $products->orderBy('created_at', 'desc')->simplePaginate(12);
        $data['show_views'] = Setting::where('id', 1)->select('show_views')->first()['show_views'];
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['show_price'] = true;
            if ($products[$i]['price'] == 0) {
                $products[$i]['show_price'] = false;
            } 
            if ($lang == 'ar') {
                $products[$i]['address'] = $products[$i]['City']->title_ar;
            } else {
                $products[$i]['address'] = $products[$i]['City']->title_en;
            }
            $products[$i]['price'] = number_format((float)$products[$i]['price'], 3, '.', '');
            
            $user = auth()->user();
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
            $products[$i]['time'] = $products[$i]['created_at']->diffForHumans(['long' => true, 'parts' => 2, 'join' => ' و ']);
        }
        $data['products'] = $products;


        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function get_sub_categories_level4(Request $request)
    {
        if (!$request->header('uniqueid')) {
            $response = APIHelpers::createApiResponse(true , 406 , 'unique id required header' , 'unique id required header'  , null , $request->lang);
            return response()->json($response , 406);
        }
        $validator = Validator::make($request->all(), [
            'category_id' => 'required'
        ]);

        if ($validator->fails() && !isset($request->sub_category_level2_id) && !isset($request->sub_category_level1_id)) {
            $response = APIHelpers::createApiResponse(true, 406, 'بعض الحقول مفقودة', 'بعض الحقول مفقودة', null, $request->lang);
            return response()->json($response, 406);
        }
        $visitor = Visitor::where('unique_id', $request->header('uniqueid'))->select('city_id', 'area_id', 'unique_id')->first();
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        if (!$visitor) {
            $response = APIHelpers::createApiResponse(true , 406 , 'there is no such visitor' , 'there is no such visitor'  , null , $request->lang);
            return response()->json($response , 406);
        }


        if ($request->sub_category_level1_id == 0) {
            $subCategories = SubCategory::where('deleted', 0)->where('category_id', $request->category_id)->pluck('id')->toArray();
            $subCategoriesTwo = SubTwoCategory::where('deleted', 0)->whereIn('sub_category_id', $subCategories)->pluck('id')->toArray();
        } else {
            $subCategoriesTwo = SubTwoCategory::where('deleted', 0)->where('sub_category_id', $request->sub_category_level1_id)->pluck('id')->toArray();
        }

        if ($request->sub_category_level2_id == 0) {
            $subCategoriesThree = SubThreeCategory::where('deleted', 0)->whereIn('sub_category_id', $subCategoriesTwo)->pluck('id')->toArray();
        } else {
            $subCategoriesThree = SubThreeCategory::where('deleted', 0)->where('sub_category_id', $request->sub_category_level2_id)->pluck('id')->toArray();
        }

        if ($request->sub_category_id != 0) {
            $data['sub_categories'] = $this->getCatsSubCats('\App\SubFourCategory', $lang, false, $request->sub_category_id, $visitor->city_id, true);
            $data['sub_category_level3'] = SubThreeCategory::where('id', $request->sub_category_id)->select('id', 'title_' . $lang . ' as title', 'sub_category_id')->first();

            $data['category'] = Category::where('id', $request->category_id)->select('id', 'title_' . $lang . ' as title')->first();
        } else {
            $data['sub_categories'] = $this->getCatsSubCats('\App\SubFourCategory', $lang, false, 0, $visitor->city_id, false, $subCategoriesThree);
            $data['sub_category_level3'] = (object)[
                "id" => 0,
                "title" => "All",
                "sub_category_id" => (int)$request->sub_category_level2_id
            ];

            $data['category'] = Category::where('id', $request->category_id)->select('id', 'title_' . $lang . ' as title')->first();
        }

        $products = Product::where('status', 1)->where('deleted', 0)->where('reviewed', 1)->where('publish', 'Y')->with('Publisher');
        if ($request->sub_category_id != 0) {
            $products = $products->where('sub_category_three_id', $request->sub_category_id);
        }

        if ($visitor->city_id != 0) {
            $products = $products->where('city_id', $visitor->city_id);
        }

        if ($visitor->area_id != 0) {
            $products = $products->where('area_id', $visitor->area_id);
        }

        if ($request->sub_category_level2_id != 0) {
            $products = $products->where('sub_category_two_id', $request->sub_category_level2_id);
        }

        if ($request->sub_category_level1_id != 0) {
            $products = $products->where('sub_category_id', $request->sub_category_level1_id);
        }

        $products = $products->select('id', 'title', 'main_image as image', 'created_at', 'user_id','city_id','area_id', 'price', 'views')
            ->orderBy('created_at', 'desc')->simplePaginate(12);
        $data['show_views'] = Setting::where('id', 1)->select('show_views')->first()['show_views'];
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['show_price'] = true;
            if ($products[$i]['price'] == 0) {
                $products[$i]['show_price'] = false;
            } 
            if ($lang == 'ar') {
                $products[$i]['address'] = $products[$i]['City']->title_ar;
            } else {
                $products[$i]['address'] = $products[$i]['City']->title_en;
            }
            $products[$i]['price'] = number_format((float)$products[$i]['price'], 3, '.', '');
           
            $user = auth()->user();
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
            $products[$i]['time'] = $products[$i]['created_at']->diffForHumans(['long' => true, 'parts' => 2, 'join' => ' و ']);
        }
        $data['products'] = $products;


        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function get_sub_categories_level5(Request $request)
    {
        if (!$request->header('uniqueid')) {
            $response = APIHelpers::createApiResponse(true , 406 , 'unique id required header' , 'unique id required header'  , null , $request->lang);
            return response()->json($response , 406);
        }
        $validator = Validator::make($request->all(), [
            'category_id' => 'required'
        ]);
        if ($validator->fails() && !isset($request->sub_category_level2_id) && !isset($request->sub_category_level1_id)) {
            $response = APIHelpers::createApiResponse(true, 406, 'بعض الحقول مفقودة', 'بعض الحقول مفقودة', null, $request->lang);
            return response()->json($response, 406);
        }
        $visitor = Visitor::where('unique_id', $request->header('uniqueid'))->select('city_id', 'area_id', 'unique_id')->first();
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        if (!$visitor) {
            $response = APIHelpers::createApiResponse(true , 406 , 'there is no such visitor' , 'there is no such visitor'  , null , $request->lang);
            return response()->json($response , 406);
        }
        
        if ($request->sub_category_level1_id == 0) {
            $subCategories = SubCategory::where('deleted', 0)->where('category_id', $request->category_id)->pluck('id')->toArray();
            $subCategoriesTwo = SubTwoCategory::where('deleted', 0)->whereIn('sub_category_id', $subCategories)->pluck('id')->toArray();
        } else {
            $subCategoriesTwo = SubTwoCategory::where('deleted', 0)->where('sub_category_id', $request->sub_category_level1_id)->pluck('id')->toArray();
        }
        if ($request->sub_category_level2_id == 0) {
            $subCategoriesThree = SubThreeCategory::where('deleted', 0)->whereIn('sub_category_id', $subCategoriesTwo)->pluck('id')->toArray();
        } else {
            $subCategoriesThree = SubThreeCategory::where('deleted', 0)->where('sub_category_id', $request->sub_category_level2_id)->pluck('id')->toArray();
        }
        if ($request->sub_category_level3_id == 0) {
            $subCategoriesFour = SubFourCategory::where('deleted', 0)->whereIn('sub_category_id', $subCategoriesThree)->pluck('id')->toArray();
        } else {
            $subCategoriesFour = SubFourCategory::where('deleted', 0)->where('sub_category_id', $request->sub_category_level3_id)->pluck('id')->toArray();
        }
        if ($request->sub_category_id != 0) {
            $data['sub_categories'] = $this->getCatsSubCats('\App\SubFiveCategory', $lang, false, $request->sub_category_id, $visitor->city_id, true);
        
            $data['sub_category_level4'] = SubFourCategory::where('deleted', 0)->where('sub_category_id', $request->sub_category_id)->select('id', 'image', 'title_' . $lang . ' as title')->first();
            
            $data['category'] = Category::where('id', $request->category_id)->select('id', 'title_' . $lang . ' as title')->first();
        } else {
            $data['sub_categories'] = $this->getCatsSubCats('\App\SubFiveCategory', $lang, false, 0, $visitor->city_id, false, $subCategoriesFour);
            $data['sub_category_level3'] = (object)[
                "id" => 0,
                "title" => "All",
                "sub_category_id" => (int)$request->sub_category_level2_id
            ];
            
            $data['category'] = Category::where('id', $request->category_id)->select('id', 'title_' . $lang . ' as title')->first();
        }
        
        

        $products = Product::where('status', 1)->where('publish', 'Y')->where('deleted', 0)->where('reviewed', 1)->with('Publisher');
        if ($request->sub_category_id != 0) {
            $products = $products->where('sub_category_four_id', $request->sub_category_id);
        }
        if ($visitor->city_id != 0) {
            $products = $products->where('city_id', $visitor->city_id);
        }
        if ($visitor->area_id != 0) {
            $products = $products->where('area_id', $visitor->area_id);
        }
        if ($request->sub_category_level3_id != 0) {
            $products = $products->where('sub_category_three_id', $request->sub_category_level3_id);
        }
        if ($request->sub_category_level2_id != 0) {
            $products = $products->where('sub_category_two_id', $request->sub_category_level2_id);
        }
        if ($request->sub_category_level1_id != 0) {
            $products = $products->where('sub_category_id', $request->sub_category_level1_id);
        }
        $products = $products->select('id', 'title', 'main_image as image', 'created_at', 'user_id','city_id','area_id', 'price', 'views')
            ->orderBy('created_at', 'desc')->simplePaginate(12);
        $data['show_views'] = Setting::where('id', 1)->select('show_views')->first()['show_views'];
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['show_price'] = true;
            if ($products[$i]['price'] == 0) {
                $products[$i]['show_price'] = false;
            } 
            if ($lang == 'ar') {
                $products[$i]['address'] = $products[$i]['City']->title_ar;
            } else {
                $products[$i]['address'] = $products[$i]['City']->title_en;
            }
            $products[$i]['price'] = number_format((float)$products[$i]['price'], 3, '.', '');
            
            $user = auth()->user();
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
            $products[$i]['time'] = $products[$i]['created_at']->diffForHumans(['long' => true, 'parts' => 2, 'join' => ' و ']);
        }
        
        $data['products'] = $products;


        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function getproducts(Request $request)
    {
        if (!$request->header('uniqueid')) {
            $response = APIHelpers::createApiResponse(true , 406 , 'unique id required header' , 'unique id required header'  , null , $request->lang);
            return response()->json($response , 406);
        }
        $validator = Validator::make($request->all(), [
            'sub_category_level1_id' => 'required',
            'sub_category_level2_id' => 'required',
            'sub_category_level3_id' => 'required',
            'sub_category_level4_id' => 'required',
            'category_id' => 'required'
        ]);

        if ($validator->fails()) {
            $response = APIHelpers::createApiResponse(true, 406, 'بعض الحقول مفقودة', 'بعض الحقول مفقودة', null, $request->lang);
            return response()->json($response, 406);
        }
        $visitor = Visitor::where('unique_id', $request->header('uniqueid'))->select('city_id', 'area_id', 'unique_id')->first();
        $lang = $request->lang;
        Session::put('api_lang', $lang);
        if (!$visitor) {
            $response = APIHelpers::createApiResponse(true , 406 , 'there is no such visitor' , 'there is no such visitor'  , null , $request->lang);
            return response()->json($response , 406);
        }
        

        if ($request->sub_category_level1_id == 0) {
            $subCategories = SubCategory::where('deleted', 0)->where('category_id', $request->category_id)->pluck('id')->toArray();
            $subCategoriesTwo = SubTwoCategory::where('deleted', 0)->whereIn('sub_category_id', $subCategories)->pluck('id')->toArray();
        } else {
            $subCategoriesTwo = SubTwoCategory::where('deleted', 0)->where('sub_category_id', $request->sub_category_level1_id)->pluck('id')->toArray();
        }

        if ($request->sub_category_level2_id == 0) {
            $subCategoriesThree = SubThreeCategory::where('deleted', 0)->whereIn('sub_category_id', $subCategoriesTwo)->pluck('id')->toArray();
        } else {
            $subCategoriesThree = SubThreeCategory::where('deleted', 0)->where('sub_category_id', $request->sub_category_level2_id)->pluck('id')->toArray();
        }
        if ($request->sub_category_level3_id == 0) {
            $subCategoriesFour = SubFourCategory::where('deleted', 0)->whereIn('sub_category_id', $subCategoriesThree)->pluck('id')->toArray();
        } else {
            $subCategoriesFour = SubFourCategory::where('deleted', 0)->where('sub_category_id', $request->sub_category_level3_id)->pluck('id')->toArray();
        }

        if ($request->sub_category_level4_id != 0) {
            $data['sub_categories'] = SubFiveCategory::where('deleted', '0')->where('sub_category_id', $request->sub_category_level4_id)->select('id', 'image', 'title_' . $lang . ' as title')->orderBy('sort', 'asc')->get()->toArray();
        } else {
            $data['sub_categories'] = SubFiveCategory::where('deleted', '0')->whereIn('sub_category_id', $subCategoriesFour)->select('id', 'image', 'title_' . $lang . ' as title')->orderBy('sort', 'asc')->get()->toArray();
        }
        if ($request->sub_category_level3_id != 0) {
            $data['sub_category_array'] = SubFiveCategory::where('deleted', '0')->where('sub_category_id', $request->sub_category_level4_id)->select('id', 'image', 'title_' . $lang . ' as title')->orderBy('sort', 'asc')->get()->toArray();
        } else {
            $data['sub_category_array'] = SubFiveCategory::where('deleted', '0')->whereIn('sub_category_id', $subCategoriesFour)->select('id', 'image', 'title_' . $lang . ' as title')->orderBy('sort', 'asc')->get()->toArray();
        }
        if (count($data['sub_category_array']) == 0) {
            $data['sub_category_array'] = SubFiveCategory::where('deleted', '0')->where('sub_category_id', $request->sub_category_level4_id)->select('id', 'image', 'title_' . $lang . ' as title')->orderBy('sort', 'asc')->get()->toArray();
        }

        $products = Product::where('status', 1)->where('publish', 'Y')->where('deleted', 0)->where('reviewed', 1)->with('Publisher');
        if ($request->sub_category_level1_id != 0) {
            $products = $products->where('sub_category_id', $request->sub_category_level1_id);
        }
        if ($visitor && !empty($visitor->city_id)) {
            $products = $products->where('city_id', $visitor->city_id);
        }
        if ($visitor->area_id != 0) {
            $products = $products->where('area_id', $visitor->area_id);
        }
        if ($request->sub_category_level2_id != 0) {
            $products = $products->where('sub_category_two_id', $request->sub_category_level2_id);
        }
        if ($request->sub_category_level3_id != 0) {
            $products = $products->where('sub_category_three_id', $request->sub_category_level3_id);
        }
        if ($request->sub_category_level4_id != 0) {
            $products = $products->where('sub_category_four_id', $request->sub_category_level4_id);
        }
        $products = $products->where('sub_category_five_id', $request->sub_category_id)
            ->select('id', 'title', 'main_image as image', 'created_at', 'user_id','city_id','area_id', 'views')
            ->where('publish', 'Y')->orderBy('created_at', 'desc')->simplePaginate(12);
        $data['show_views'] = Setting::where('id', 1)->select('show_views')->first()['show_views'];
        for ($i = 0; $i < count($products); $i++) {
            $products[$i]['show_price'] = true;
            if ($products[$i]['price'] == 0) {
                $products[$i]['show_price'] = false;
            } 
            if ($lang == 'ar') {
                $products[$i]['address'] = $products[$i]['City']->title_ar . ' , ' . $products[$i]['Area']->title_ar;
            } else {
                $products[$i]['address'] = $products[$i]['City']->title_en . ' , ' . $products[$i]['Area']->title_en;
            }
            $products[$i]['price'] = number_format((float)$products[$i]['price'], 3, '.', '');
            
            $user = auth()->user();
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
            $month = $products[$i]['created_at']->format('F');
            $products[$i]['time'] = $products[$i]['created_at']->diffForHumans(['long' => true, 'parts' => 2, 'join' => ' و ']);
        }
        $data['products'] = $products;
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }
    //nasser code
    // get ad categories for create ads
    public function show_first_cat(Request $request)
    {
        if ($request->lang == 'en') {
            $data['categories'] = Category::where('deleted', 0)->select('id', 'title_en as title', 'image')->orderBy('sort', 'asc')->get();
        } else {
            $data['categories'] = Category::where('deleted', 0)->select('id', 'title_ar as title', 'image')->orderBy('sort', 'asc')->get();
        }
        if (count($data['categories']) > 0) {
            for ($i = 0; $i < count($data['categories']); $i++) {
                $subThreeCats = SubCategory::where('category_id', $data['categories'][$i]['id'])->where('deleted', 0)->select('id')->first();
                $data['categories'][$i]['next_level'] = false;
                if (isset($subThreeCats['id'])) {
                    $data['categories'][$i]['next_level'] = true;
                }
            }
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function show_second_cat(Request $request, $cat_id)
    {
        if ($request->lang == 'en') {
            $data['categories'] = SubCategory::where('category_id', $cat_id)->where('deleted', 0)->select('id', 'title_en as title', 'image')->orderBy('sort', 'asc')->get();
        } else {
            $data['categories'] = SubCategory::where('category_id', $cat_id)->where('deleted', 0)->select('id', 'title_ar as title', 'image')->orderBy('sort', 'asc')->get();
        }
        if (count($data['categories']) > 0) {
            for ($i = 0; $i < count($data['categories']); $i++) {
                $subThreeCats = SubTwoCategory::where('sub_category_id', $data['categories'][$i]['id'])->where('deleted', 0)->where('deleted', 0)->select('id')->first();
                $data['categories'][$i]['next_level'] = false;
                if (isset($subThreeCats['id'])) {
                    $data['categories'][$i]['next_level'] = true;
                }
            }
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function show_third_cat(Request $request, $sub_cat_id)
    {
        if ($request->lang == 'en') {
            $data['categories'] = SubTwoCategory::where('sub_category_id', $sub_cat_id)->where('deleted', 0)->select('id', 'title_en as title', 'image')->orderBy('sort', 'asc')->get();
        } else {
            $data['categories'] = SubTwoCategory::where('sub_category_id', $sub_cat_id)->where('deleted', 0)->select('id', 'title_ar as title', 'image')->orderBy('sort', 'asc')->get();
        }
        if (count($data['categories']) > 0) {
            for ($i = 0; $i < count($data['categories']); $i++) {
                $subThreeCats = SubThreeCategory::where('sub_category_id', $data['categories'][$i]['id'])->where('deleted', 0)->select('id')->first();
                $data['categories'][$i]['next_level'] = false;
                if (isset($subThreeCats['id'])) {
                    $data['categories'][$i]['next_level'] = true;
                }
            }
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function show_four_cat(Request $request, $sub_sub_cat_id)
    {
        if ($request->lang == 'en') {
            $data['categories'] = SubThreeCategory::where('sub_category_id', $sub_sub_cat_id)->where('deleted', 0)->select('id', 'title_en as title', 'image')->orderBy('sort', 'asc')->get();
        } else {
            $data['categories'] = SubThreeCategory::where('sub_category_id', $sub_sub_cat_id)->where('deleted', 0)->select('id', 'title_ar as title', 'image')->orderBy('sort', 'asc')->get();
        }
        if (count($data['categories']) > 0) {
            for ($i = 0; $i < count($data['categories']); $i++) {
                $subThreeCats = SubFourCategory::where('sub_category_id', $data['categories'][$i]['id'])->where('deleted', 0)->select('id')->first();
                $data['categories'][$i]['next_level'] = false;
                if (isset($subThreeCats['id'])) {
                    $data['categories'][$i]['next_level'] = true;
                }
            }
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function show_five_cat(Request $request, $sub_sub_cat_id)
    {
        if ($request->lang == 'en') {
            $data['categories'] = SubFourCategory::where('sub_category_id', $sub_sub_cat_id)->where('deleted', 0)->select('id', 'title_en as title', 'image')->orderBy('sort', 'asc')->get();
        } else {
            $data['categories'] = SubFourCategory::where('sub_category_id', $sub_sub_cat_id)->where('deleted', 0)->select('id', 'title_ar as title', 'image')->orderBy('sort', 'asc')->get();
        }
        if (count($data['categories']) > 0) {
            for ($i = 0; $i < count($data['categories']); $i++) {
                $subThreeCats = SubFiveCategory::where('sub_category_id', $data['categories'][$i]['id'])->where('deleted', '0')->select('id')->first();
                $data['categories'][$i]['next_level'] = false;
                if (isset($subThreeCats['id'])) {
                    $data['categories'][$i]['next_level'] = true;
                }
            }
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function show_six_cat(Request $request, $sub_sub_cat_id)
    {
        if ($request->lang == 'en') {
            $data['categories'] = SubFiveCategory::where('sub_category_id', $sub_sub_cat_id)->where('deleted', '0')->select('id', 'title_en as title', 'image')->orderBy('sort', 'asc')->get();
        } else {
            $data['categories'] = SubFiveCategory::where('sub_category_id', $sub_sub_cat_id)->where('deleted', '0')->select('id', 'title_ar as title', 'image')->orderBy('sort', 'asc')->get();
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }


    // get category options
    public function getCategoryOptions(Request $request)
    {
        $lang = $request->lang;
        if ($request->category_id != 0 && $request->sub_category_id == 0 && $request->sub_two_category_id == 0) {
            $data['options'] = Category_option::where('cat_id', $request->category_id)->where('cat_type', 'category')->where('deleted', '0')->select('id as option_id', 'title_' . $lang . ' as title', 'is_required')->get();
            if (count($data['options']) > 0) {
                for ($i = 0; $i < count($data['options']); $i++) {
                    $data['options'][$i]['type'] = 'input';
                    $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_' . $lang . ' as value')->get();
                    if (count($optionValues) > 0) {

                        $data['options'][$i]['type'] = 'select';
                        $data['options'][$i]['values'] = $optionValues;
                    }
                }
            }
        } else if ($request->category_id != 0 && $request->sub_category_id != 0 && $request->sub_two_category_id == 0) {
            $data['options'] = Category_option::where('cat_id', $request->sub_category_id)->where('cat_type', 'subcategory')->where('deleted', '0')->select('id as option_id', 'title_' . $lang . ' as title', 'is_required')->get();
            if (count($data['options']) > 0) {
                for ($i = 0; $i < count($data['options']); $i++) {
                    $data['options'][$i]['type'] = 'input';
                    $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_' . $lang . ' as value')->get();
                    if (count($optionValues) > 0) {

                        $data['options'][$i]['type'] = 'select';
                        $data['options'][$i]['values'] = $optionValues;
                    }
                }
            }
            if (count($data['options']) == 0) {
                    $data['options'] = Category_option::where('cat_id', $request->category_id)->where('cat_type', 'category')->where('deleted', '0')->select('id as option_id', 'title_en as title', 'is_required')->get();
                    if (count($data['options']) > 0) {
                        for ($i = 0; $i < count($data['options']); $i++) {
                            $data['options'][$i]['type'] = 'input';
                            $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_en as value')->get();
                            if (count($optionValues) > 0) {
                                $data['options'][$i]['type'] = 'select';
                                $data['options'][$i]['values'] = $optionValues;
                            }
                        }
                    }

            }
        } else if ($request->category_id != 0 && $request->sub_category_id != 0 && $request->sub_two_category_id != 0) {
            $lang = $request->lang;
            $data['options'] = [];
            if ($request->sub_two_category_id != 0) {
                $data['options'] = Category_option::where('cat_id', $request->sub_two_category_id)->where('cat_type', 'subTwoCategory')->where('deleted', '0')->select('id as option_id', 'title_' . $lang . ' as title', 'is_required')->get();
                if (count($data['options']) > 0) {
                    for ($i = 0; $i < count($data['options']); $i++) {
                        $data['options'][$i]['type'] = 'input';
                        $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_' . $lang . ' as value')->get();
                        if (count($optionValues) > 0) {
                            $data['options'][$i]['type'] = 'select';
                            $data['options'][$i]['values'] = $optionValues;
                        }
                    }
                }
            }

            if ($request->sub_category_id != 0) {
                if (count($data['options']) == 0) {
                    $data['options'] = Category_option::where('cat_id', $request->sub_category_id)->where('cat_type', 'subcategory')->where('deleted', '0')->select('id as option_id', 'title_' . $lang . ' as title', 'is_required')->get();
                    if (count($data['options']) > 0) {
                        for ($i = 0; $i < count($data['options']); $i++) {
                            $data['options'][$i]['type'] = 'input';
                            $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_' . $lang . ' as value')->get();
                            if (count($optionValues) > 0) {
                                $data['options'][$i]['type'] = 'select';
                                $data['options'][$i]['values'] = $optionValues;
                            }
                        }
                    }
                }
            }


            if ($request->category_id != 0) {
                if (count($data['options']) == 0) {
                    $data['options'] = Category_option::where('cat_id', $request->category_id)->where('cat_type', 'category')->where('deleted', '0')->select('id as option_id', 'title_' . $lang . ' as title', 'is_required')->get();
                    if (count($data['options']) > 0) {
                        for ($i = 0; $i < count($data['options']); $i++) {
                            $data['options'][$i]['type'] = 'input';
                            $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_' . $lang . ' as value')->get();
                            if (count($optionValues) > 0) {
                                $data['options'][$i]['type'] = 'select';
                                $data['options'][$i]['values'] = $optionValues;
                            }
                        }
                    }
                }
            }
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    // get sub category options
    public function getSubCategoryOptions(Request $request, Category $category, SubCategory $sub_category)
    {
        if ($request->lang == 'en') {
            $data['options'] = Category_option::where('cat_id', $sub_category['id'])->where('cat_type', 'subcategory')->where('deleted', '0')->select('id as option_id', 'title_en as title', 'is_required')->get();
            if (count($data['options']) > 0) {
                for ($i = 0; $i < count($data['options']); $i++) {
                    $data['options'][$i]['type'] = 'input';
                    $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_en as value')->get();
                    if (count($optionValues) > 0) {

                        $data['options'][$i]['type'] = 'select';
                        $data['options'][$i]['values'] = $optionValues;
                    }
                }
            }
        } else {
            $data['options'] = Category_option::where('cat_id', $sub_category['id'])->where('cat_type', 'subcategory')->where('deleted', '0')->select('id as option_id', 'title_ar as title', 'is_required')->get();
            if (count($data['options']) > 0) {
                for ($i = 0; $i < count($data['options']); $i++) {
                    $data['options'][$i]['type'] = 'input';
                    $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_ar as value')->get();
                    if (count($optionValues) > 0) {
                        $data['options'][$i]['type'] = 'select';
                        $data['options'][$i]['values'] = $optionValues;
                    }
                }
            }
        }

        if (count($data['options']) == 0) {
            if ($request->lang == 'en') {
                $data['options'] = Category_option::where('cat_id', $category['id'])->where('cat_type', 'category')->where('deleted', '0')->select('id as option_id', 'title_en as title', 'is_required')->get();

                if (count($data['options']) > 0) {
                    for ($i = 0; $i < count($data['options']); $i++) {
                        $data['options'][$i]['type'] = 'input';
                        $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_en as value')->get();
                        if (count($optionValues) > 0) {

                            $data['options'][$i]['type'] = 'select';
                            $data['options'][$i]['values'] = $optionValues;
                        }
                    }
                }
            } else {
                $data['options'] = Category_option::where('cat_id', $category['id'])->where('cat_type', 'category')->where('deleted', '0')->select('id as option_id', 'title_ar as title', 'is_required')->get();
                if (count($data['options']) > 0) {
                    for ($i = 0; $i < count($data['options']); $i++) {
                        $data['options'][$i]['type'] = 'input';
                        $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_ar as value')->get();
                        if (count($optionValues) > 0) {
                            $data['options'][$i]['type'] = 'select';
                            $data['options'][$i]['values'] = $optionValues;
                        }
                    }
                }
            }
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }

    public function getSubTwoCategoryOptions(Request $request, $category, $sub_category, $sub_two_category)
    {
        $lang = $request->lang;
        $data['options'] = [];
        if ($sub_two_category != 0) {
            $data['options'] = Category_option::where('cat_id', $sub_two_category)->where('cat_type', 'subTwoCategory')->where('deleted', '0')->select('id as option_id', 'title_' . $lang . ' as title', 'is_required')->get();
            if (count($data['options']) > 0) {
                for ($i = 0; $i < count($data['options']); $i++) {
                    $data['options'][$i]['type'] = 'input';
                    $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_' . $lang . ' as value')->get();
                    if (count($optionValues) > 0) {
                        $data['options'][$i]['type'] = 'select';
                        $data['options'][$i]['values'] = $optionValues;
                    }
                }
            }
        }

        if ($sub_category != 0) {
            if (count($data['options']) == 0) {
                $data['options'] = Category_option::where('cat_id', $sub_category)->where('cat_type', 'subcategory')->where('deleted', '0')->select('id as option_id', 'title_' . $lang . ' as title', 'is_required')->get();
                if (count($data['options']) > 0) {
                    for ($i = 0; $i < count($data['options']); $i++) {
                        $data['options'][$i]['type'] = 'input';
                        $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_' . $lang . ' as value')->get();
                        if (count($optionValues) > 0) {
                            $data['options'][$i]['type'] = 'select';
                            $data['options'][$i]['values'] = $optionValues;
                        }
                    }
                }
            }
        }


        if ($category != 0) {
            if (count($data['options']) == 0) {
                $data['options'] = Category_option::where('cat_id', $category)->where('cat_type', 'category')->where('deleted', '0')->select('id as option_id', 'title_' . $lang . ' as title', 'is_required')->get();
                if (count($data['options']) > 0) {
                    for ($i = 0; $i < count($data['options']); $i++) {
                        $data['options'][$i]['type'] = 'input';
                        $optionValues = Category_option_value::where('option_id', $data['options'][$i]['option_id'])->where('deleted', '0')->select('id as value_id', 'value_' . $lang . ' as value')->get();
                        if (count($optionValues) > 0) {
                            $data['options'][$i]['type'] = 'select';
                            $data['options'][$i]['values'] = $optionValues;
                        }
                    }
                }
            }
        }
        $response = APIHelpers::createApiResponse(false, 200, '', '', $data, $request->lang);
        return response()->json($response, 200);
    }


}
