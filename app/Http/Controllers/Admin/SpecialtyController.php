<?php

namespace App\Http\Controllers\Admin;

use App\specialty;
use JD\Cloudder\Facades\Cloudder;
use Illuminate\Http\Request;
use App\Account_type;
use App\Mndob;

class SpecialtyController extends AdminController
{

    public function index()
    {
        $data = specialty::where('deleted','0')->OrderBy('id','desc')->get();
        return view('admin.specialty.index',compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.specialty.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $this->validate(\request(),
            [
                'name_ar' => 'required',
                'name_en' => 'required',
            ]);
        specialty::create($data);
        session()->flash('success', trans('messages.added_s'));
        return redirect( route('specialty.index'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $data = specialty::findOrFail($id);
        return view('admin.specialty.edit',compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $mndob  = specialty::find($id);
        $mndob->name_ar = $request->name_ar;
        $mndob->name_en = $request->name_en;
        $mndob->save();
        return redirect(route('specialty.index'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $data['deleted'] = "1";
        specialty::where('id',$id)->update($data);
        session()->flash('success', trans('messages.deleted_s'));
        return back();
    }
}
