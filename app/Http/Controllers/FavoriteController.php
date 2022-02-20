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
use App\Visitor;
use App\Notification;
use App\UserNotification;

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
            $product = Product::where("id", $request->product_id)->select('id', 'user_id', 'title')->first();
            $lastToken = Visitor::where('user_id', $product->user_id)->where('fcm_token' ,'!=' , null)->latest('updated_at')->select('id', 'fcm_token')->first();
            
            $title = "الإعلان";
            $body = $user->name . " has added your ad " . $product->title . " to favorite list";
            if ($request->lang == 'ar') {
                $body = $user->name . " قام بإضافة إعلانك " . $product->title . " إلى المفضلة";
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
            $products = Favorite::has('product', '>', 0)->select('id','product_id','user_id')
                                 ->with('Product')
                                 ->where('user_id', $user->id)
                                 ->orderBy('id','desc')
                ->simplePaginate(12);

            if (count($products) > 0) {
                for ($i = 0; $i < count($products); $i++) {
                    $products[$i]['show_price'] = true;
                    if ($products[$i]['price'] == 0) {
                        $products[$i]['show_price'] = false;
                    } 
                    if($lang == 'ar'){
                        $products[$i]['Product']->address = $products[$i]->Product->City->title_ar .' , '.$products[$i]['Product']->Area->title_ar;
                    }else{
                        
                        $products[$i]['Product']->address = $products[$i]->Product->City->title_en .' , '.$products[$i]['Product']->Area->title_en;
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
            }
            
            $show_views = Setting::where('id', 1)->select('show_views')->first()['show_views'];
            $response = APIHelpers::createApiResponse(false, 200, '', '', ['products' => $products, 'show_views' => $show_views], $request->lang);
            return response()->json($response, 200);
        }
    }

    public function getIosfavorites(Request $request){
        $user = auth()->user();
        $lang = $request->lang ;
        Session::put('api_lang', $lang);
        if($user->active == 0){
            $response = APIHelpers::createApiResponse(true , 406 ,  'تم حظر حسابك', 'تم حظر حسابك' , null, $request->lang );
            return response()->json($response , 406);
        }else {
            $products = Favorite::has('product', '>', 0)->select('id','product_id','user_id')
                                 ->with('Product')
                                 ->where('user_id', $user->id)
                                 ->orderBy('id','desc')
                ->simplePaginate(12);

            if (count($products) > 0) {
                for ($i = 0; $i < count($products); $i++) {
                    $products[$i]['show_price'] = true;
                    if ($products[$i]['Product']->price == 0) {
                        $products[$i]['show_price'] = false;
                    } 
                    if($lang == 'ar'){
                        $products[$i]['Product']->address = $products[$i]->Product->City->title_ar .' , '.$products[$i]['Product']->Area->title_ar;
                    }else{
                        
                        $products[$i]['Product']->address = $products[$i]->Product->City->title_en .' , '.$products[$i]['Product']->Area->title_en;
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
            }
            
            
            $response = APIHelpers::createApiResponse(false, 200, '', '', $products, $request->lang);
            return response()->json($response, 200);
        }
    }
}
