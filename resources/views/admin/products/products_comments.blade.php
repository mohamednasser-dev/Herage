@extends('admin.app')
@section('title' , __('messages.comments'))
@section('content')
    <div id="tableSimple" class="col-lg-12 col-12 layout-spacing">
        <div class="statbox widget box box-shadow">
            <div class="widget-header">
                <div class="row">
                    <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                        <h4>{{ __('messages.comments') }}</h4>
                    </div>
                </div>
            </div>
            <div class="widget-content widget-content-area">
                <div class="table-responsive">
                    <table id="html5-extension" class="table table-hover non-hover" style="width:100%">
                        <thead>
                        <tr>
                            <th class="text-center">Id</th>
                            <th class="text-center">{{ __('messages.user_name') }}</th>
                            <th class="text-center">{{ __('messages.ad_name') }}</th>
                            <th class="text-center">{{ __('messages.comment') }}</th>
                            <th class="text-center">{{ __('messages.ad_details') }}</th>
                            {{-- <th class="text-center">{{ __('messages.approval') }}</th> --}}
                        </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; ?>
                        @foreach ($data as $row)
                            <tr>
                                <td class="text-center"><?=$i;?></td>
                                <td class="text-center">{{ $row->User->name }}</td>
                                <td class="text-center">{{ $row->Product->title }}</td>
                                <td class="text-center">{{ $row->comment }}</td>
                                <td class="text-center blue-color">
                                    <a href="{{ route('products.details', $row->Product->id) }}"><i
                                            class="far fa-eye"></i></a>
                                </td>
                                {{-- <td class="text-center blue-color">
                                    @if($row->status == 'new')
                                        <a href="{{route('comment.approve',['type'=>'accepted','id'=>$row->id])}}"
                                           class="btn btn-success mb-2 mr-2 rounded-circle bs-tooltip"
                                           title="{{ __('messages.accept') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="feather feather-check">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                        </a>
                                        <a href="{{route('comment.approve',['type'=>'rejected','id'=>$row->id])}}"
                                           class="btn btn-danger mb-2 mr-2 rounded-circle bs-tooltip"
                                           title="{{ __('messages.reject') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="feather feather-x">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </a>
                                    @elseif($row->status == 'accepted')
                                        <a href="{{route('comment.approve',['type'=>'rejected','id'=>$row->id])}}"
                                           class="btn btn-danger mb-2 mr-2 rounded-circle bs-tooltip"
                                           title="{{ __('messages.reject') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="feather feather-x">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </a>
                                    @else
                                        <a href="{{route('comment.approve',['type'=>'accepted','id'=>$row->id])}}"
                                           class="btn btn-success mb-2 mr-2 rounded-circle bs-tooltip"
                                           title="{{ __('messages.accept') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 stroke-linecap="round" stroke-linejoin="round"
                                                 class="feather feather-check">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                        </a>
                                    @endif
                                </td> --}}
                                <?php $i++; ?>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

