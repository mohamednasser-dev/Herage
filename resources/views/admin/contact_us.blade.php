@extends('admin.app')

@section('title' , __('messages.show_contact_us'))

@section('content')
    <div id="tableSimple" class="col-lg-12 col-12 layout-spacing">
        <div class="statbox widget box box-shadow">
            <div class="widget-header">
                <div class="row">

                    <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                        <h4>{{ __('messages.show_contact_us') }}</h4>

                    </div>
                </div>
            </div>
            <div class="widget-content widget-content-area">
                <div class="table-responsive">
                    <table id="html5-extension" class="table table-hover non-hover" style="width:100%">
                        <thead>
                        <tr>
                            <th>Id</th>
                            <th>{{ __('messages.phone') }}</th>
                            <th>{{ __('messages.date') }}</th>
                            <th class="text-center">{{ __('messages.details') }}</th>
                            <th class="text-center">{{ __('messages.seen?') }}</th>
                            @if(Auth::user()->delete_data)
                                <th class="text-center">{{ __('messages.delete') }}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; ?>
                        @foreach ($data['contact_us'] as $contact_us)
                            <tr class="{{$contact_us->seen == 0 ? 'unread' : '' }}">
                                <td><?=$i;?></td>
                                <td>{{ $contact_us->phone }}</td>
                                <td>{{ $contact_us->created_at }}</td>
                                <td class="text-center blue-color"><a
                                        href="/admin-panel/contact_us/details/{{ $contact_us->id }}"><i
                                            class="far fa-eye"></i></a></td>
                                <td style="font-weight : bold" class="text-center blue-color">
                                    {{ $contact_us->seen == 0 ?  __('messages.unseen')  :  __('messages.seen')  }}
                                </td>
                                @if(Auth::user()->delete_data)
                                    <td class="text-center blue-color"><a
                                            onclick="return confirm('Are you sure you want to delete this item?');"
                                            href="/admin-panel/contact_us/delete/{{ $contact_us->id }}"><i
                                                class="far fa-trash-alt"></i></a></td>
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
