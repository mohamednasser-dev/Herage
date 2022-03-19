<?php
namespace App\Http\Controllers\Admin;

use JD\Cloudder\Facades\Cloudder;
use Illuminate\Http\Request;
use App\Helpers\APIHelpers;
use Illuminate\Support\Facades\Session;
use App\UserNotification;
use App\Notification;
use App\Visitor;

class NotificationController extends AdminController{

    // get all notifications
    public function show(){
        $data['notifications'] = Notification::orderBy('id' , 'desc')->get();
        return view('admin.notifications.index' , ['data' => $data]);
    }

    // get notification details
    public function details(Request $request){
        $data['notification'] = Notification::find($request->id);
        return view('admin.notifications.show' , ['data' => $data]);
    }

    // delete notification
    public function delete(Request $request){
        $notification = Notification::find($request->id);
        if($notification){
            $notification->delete();
        }
        return redirect('admin-panel/notifications/show');
    }

    // type : get - get send notification page
    public function getsend(){
        return view('admin.notifications.create');
    }

    // send notification and insert it in database
    public function send(Request $request){
        Session::put('api_lang', 'en');
        $users = Visitor::select('id','fcm_token','user_id');
        if ($request->type != 'all') {
            $users = $users->whereHas('user', function($q) use ($request) {
                $q->whereHas('Account_type', function($query) use ($request) {
                    $query->where('type', $request->type);
                });
            });
        }
        $users = $users->where('fcm_token' ,'!=' , null)->get();
        if (count($users) == 0) {
            session()->flash('danger', trans('messages.no_users_to_send'));
            return redirect()->back();
        }
        $notification = new Notification();
        if($request->file('image')){
            $image_name = $request->file('image')->getRealPath();
            Cloudder::upload($image_name, null);
            $imagereturned = Cloudder::getResult();
            $image_id = $imagereturned['public_id'];
            $image_format = $imagereturned['format'];
            $image_new_name = $image_id.'.'.$image_format;
            $notification->image = $image_new_name;
        }else{
            $notification->image = null;
        }
        $notification->title = $request->title;
        $notification->body = $request->body;
        $notification->account_type = $request->type;
        $notification->save();
        
        
        for($i =0; $i < count($users); $i++){
            $fcm_tokens[$i] = $users[$i]['fcm_token'];
            $user_notification = new UserNotification();
            $user_notification->user_id = $users[$i]['user_id'];
            $user_notification->notification_id = $notification->id;
            $user_notification->visitor_id = $users[$i]['id'];
            $user_notification->save();
        }
		$the_image = "https://res.cloudinary.com/duwmvqjpo/image/upload/w_100,q_100/v1581928924/".$notification->image;
        $notificationss = APIHelpers::send_notification($notification->title , $notification->body , $the_image , (object)[] , $fcm_tokens);
		session()->flash('success', trans('messages.sent_s'));
        return redirect('admin-panel/notifications/show');
    }



    // resend notifications
    public function resend(Request $request){
        $notification_id = $request->id;
        $notification = Notification::find($notification_id);
        $users = Visitor::select('id','fcm_token', 'user_id')->where('fcm_token' ,'!=' , null)->get();
        for($i =0; $i < count($users); $i++){
            $fcm_tokens[$i] = $users[$i]['fcm_token'];
            $user_notification = new UserNotification();
            $user_notification->visitor_id = $users[$i]['id'];
            $user_notification->user_id = $users[$i]['user_id'];
            $user_notification->notification_id = $notification->id;
            $user_notification->save();            
        }
        $array_values = Visitor::orderBy('id', 'desc');
        if ($notification->account_type != 'all') {
            $array_values = $array_values->whereHas('user', function($q) use ($notification) {
                $q->whereHas('Account_type', function($query) use ($notification) {
                    $query->where('type', $notification->account_type);
                });
            });
        }
        
        $array_values = $array_values->pluck('fcm_token')->toArray();
        
		
        $nots = APIHelpers::send_notification($notification->title , $notification->body , $notification->image , (object)[] , $array_values);
        
		session()->flash('success', trans('messages.sent_s'));
        return redirect()->back();
    }

}
