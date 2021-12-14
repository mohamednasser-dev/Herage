<?php

namespace App\Http\Controllers;

use App\Participant;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Helpers\APIHelpers;
use App\Favorite;
use App\Product;
use App\Setting;


class FavoriteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api' , ['except' => []]);
    }

    public function addtofavorites(Request $request){
        $user = auth()->user();
        if($user->active == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم حظر حسابك', 'تم حظر حسابك' , null, $request->lang );
            return response()->json($response , 406);
        }
        $validator = Validator::make($request->all() , [
            'product_id' => 'required',
        ]);
        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'بعض الحقول مفقودة', 'بعض الحقول مفقودة' , null, $request->lang );
            return response()->json($response , 406);
        }
        $favorite = Favorite::where('product_id' , $request->product_id)->where('user_id' , $user->id)->first();
        if($favorite){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم إضافه هذا المنتج للمفضله من قبل', 'تم إضافه هذا المنتج للمفضله من قبل' , null, $request->lang );
            return response()->json($response , 406);
        }else{
            $favorite = new Favorite();
            $favorite->user_id = $user->id;
            $favorite->product_id = $request->product_id;
            $favorite->save();
            $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $favorite, $request->lang);
            return response()->json($response , 200);
        }
    }

    public function removefromfavorites(Request $request){
        $user = auth()->user();
        if($user->active == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم حظر حسابك', 'تم حظر حسابك' , (object)[], $request->lang );
            return response()->json($response , 406);
        }

        $validator = Validator::make($request->all() , [
            'product_id' => 'required',
        ]);

        if($validator->fails()) {
            $response = APIHelpers::createApiResponse(true , 406 ,  'بعض الحقول مفقودة', 'بعض الحقول مفقودة' , null, $request->lang );
            return response()->json($response , 406);
        }

        $favorite = Favorite::where('product_id' , $request->product_id)->where('user_id',$user->id)->first();
        if($favorite){
            $favorite->delete();
            $response = APIHelpers::createApiResponse(false , 200 ,  'Deteted ', 'تم الحذف' , (object)[], $request->lang);
            return response()->json($response , 200);
        }else{
            $response = APIHelpers::createApiResponse(true , 406 ,  'هذا المنتج غير موجود بالمفضله', 'هذا المنتج غير موجود بالمفضله' , null, $request->lang );
            return response()->json($response , 406);
        }
    }

    public function getfavorites(Request $request){
        $user = auth()->user();
        $lang = $request->lang ;
        Session::put('api_lang', $lang);
        if($user->active == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم حظر حسابك', 'تم حظر حسابك' , null, $request->lang );
            return response()->json($response , 406);
        }else {
            $products = Favorite::select('id','product_id','user_id')
                                 ->with('Product')
                                 ->where('user_id', $user->id)
                                 ->orderBy('id','desc')
                ->simplePaginate(12);

//            $inc = 0;
//            $data = null;
//            foreach ($favorites as $key => $row){
//                $product = Product::where('id',$row->product_id)->first();
//                if($product != null){
//                    if($product->status == 1 && $product->deleted == 0 && $product->publish == 'Y'){
//                        $data[$inc]['id'] = $product->id;
//                        $data[$inc]['title'] = $product->title;
//                        $data[$inc]['image'] = $product->main_image;
//                        $data[$inc]['price'] = $product->price;
//                        $data[$inc]['description'] = $product->description;
//                        $inc =$inc + 1;
//                    }
//                }
//            }
            for ($i = 0; $i < count($products); $i++) {
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
                $products[$i]['Product']->time = $products[$i]['Product']->created_at->diffForHumans();
            }
            $show_views = Setting::where('id', 1)->select('show_views')->first()['show_views'];
            $response = APIHelpers::createApiResponse(false, 200, '', '', ['products' => $products, 'show_views' => $show_views], $request->lang);
            return response()->json($response, 200);
        }
    }
}
