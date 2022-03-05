<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Helpers\APIHelpers;
use JD\Cloudder\Facades\Cloudder;
use App\ContactUs;
use App\ContactImage;


class ContactUsController extends Controller
{
    public function SendMessage(Request $request){
            $validator = Validator::make($request->all(), [
                'phone' => 'required',
                'message' => 'required'
            ]);

            if ($validator->fails()) {
                $response = APIHelpers::createApiResponse(true , 406 ,  'بعض الحقول مفقودة', 'بعض الحقول مفقودة' , null, $request->lang );
                return response()->json($response , 406);
            }

        
        $contactUs = ContactUs::create(['phone' => $request->phone, 'message' => $request->message]);
       
        if ($request->images && count($request->images) > 0) {
            foreach ($request->images as $image) {
               
                Cloudder::upload("data:image/jpeg;base64," . $image, null);
                $imagereturned = Cloudder::getResult();
                $image_id = $imagereturned['public_id'];
                $image_format = $imagereturned['format'];
                $image_new_name = $image_id . '.' . $image_format;
                $data_image['contact_id'] = $contactUs->id;
                $data_image['image'] = $image_new_name;
                ContactImage::create($data_image);
            }
        }
        $response = APIHelpers::createApiResponse(false , 200 ,  '', '' , $contactUs, $request->lang );
        return response()->json($response , 200);
    }
}
