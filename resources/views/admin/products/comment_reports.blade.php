@extends('admin.app')
@section('title' , __('messages.reports'))
@section('content')
    <div id="tableSimple" class="col-lg-12 col-12 layout-spacing">
        <div class="statbox widget box box-shadow">
            <div class="widget-header">
                <div class="row">
                    <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                        <h4>{{ __('messages.reports') }}</h4>
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
                            <th class="text-center">{{ __('messages.report') }}</th>
                            @if(Auth::user()->delete_data)
                            <th class="text-center">{{ __('messages.published_unpublish') }}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; ?>
                        @foreach ($data->reports as $row)
                            <tr>
                                <td class="text-center"><?=$i;?></td>
                                <td class="text-center">{{ $row->User->name }}</td>
                                <td class="text-center">{{ $row->Product->title }}</td>
                                <td class="text-center">{{ $row->report }}</td>
                                @if(Auth::user()->delete_data)
                                <td class="text-center blue-color">
                                    <label class="switch s-icons s-outline  s-outline-primary  mb-4 mr-2">
                                        <input onchange="update_active(this)" value="{{ $row->comment_id }}" type="checkbox" <?php if($row->comment->status == 'rejected') echo "checked";?>>
                                        <span style="margin-top: 10px;" class="slider round"></span>
                                    </label>
                                </td>
                                @endif
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

@section('scripts')
    <script type="text/javascript">
        function update_active(el){
            if(el.checked){
                var status = 'rejected';
            }
            else{
                var status = 'new';
            }
            $.post('{{ route('products.comments.reject') }}', {_token:'{{ csrf_token() }}', id:el.value, status:status}, function(data){
                if(data == 1){
                    toastr.error("{{ __('messages.comment_deleted') }}");
                }
                else{
                    toastr.success("{{ __('messages.comment_published') }}");
                }
            });
        }
    </script>
@endsection