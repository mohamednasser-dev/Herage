<?php

namespace App\Http\Controllers\Admin;

use JD\Cloudder\Facades\Cloudder;
use Illuminate\Http\Request;
use App\Account_type;
use App\Mndob;

class AccountTypesController extends AdminController
{

    public function index()
    {
        $data = Account_type::where('type','commercial')->where('deleted','0')->OrderBy('id','desc')->get();
        return view('admin.account_types.index',compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.account_types.create');
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
        Account_type::create($data);
        session()->flash('success', trans('messages.added_s'));
        return redirect( route('account_types.index'));
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
        $data = Account_type::findOrFail($id);
        return view('admin.account_types.edit',compact('data'));
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
        $mndob = Account_type::find($id);
        $mndob->name_ar = $request->name_ar;
        $mndob->name_en = $request->name_en;
        $mndob->save();
        return redirect(route('account_types.index'));
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
        Account_type::where('id',$id)->update($data);
        session()->flash('success', trans('messages.deleted_s'));
        return back();
    }
}
