@extends('layouts.app')

@section('title'){{trans('general.messages')}} -@endsection
@section('css')
<link rel="stylesheet" href="{{asset('public/plugins/select2/select2.min.css')}}">
@endsection

@section('content')
<section class="section section-sm">
    <div class="container">
      <div class="row justify-content-center text-center mb-sm">
        <div class="col-lg-8 py-5">
          <h2 class="mb-0 font-montserrat"><i class="far fa-comment-dots mr-2"></i> {{trans('general.messages')}}</h2>
          <p class="lead text-muted mt-0">{{trans('general.messages_subtitle')}}</p>
          @if ($messages->count() != 0)
          <button class="btn btn-primary btn-sm w-small-100" data-toggle="modal" data-target="#newMessageForm">
            <i class="fa fa-plus"></i> {{trans('general.new_message')}}
          </button>
        @endif
        @if(count(\Auth::user()->userSubscriptions) > 0)
        <button class="btn btn-primary btn-sm w-small-100" data-toggle="modal" data-target="#bulkMessage">
          <i class="fa fa-plus"></i> Create Bulk Message
        </button>
        @endif
        </div>
      </div>
      <div class="row">

        @include('includes.cards-settings')

      <div class="col-md-6 col-lg-9 mb-5 mb-lg-0" id="messagesContainer">

    @if ($messages->count() != 0)

      @include('includes.messages-inbox')

    @else

      <div class="my-5 text-center no-updates">
        <span class="btn-block mb-3">
          <i class="fa fa-comment-slash ico-no-result"></i>
        </span>
      <h4 class="font-weight-light">{{trans('general.no_messages')}}</h4>

      <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#newMessageForm">
        <i class="fa fa-plus"></i> {{trans('general.new_message')}}
      </button>

      </div>
    @endif
    </div><!-- end col-md-6 -->

      </div>
    </div>
  </section>

  <div class="modal fade" id="newMessageForm" tabindex="-1" role="dialog" aria-labelledby="modal-form" aria-hidden="true">
    <div class="modal-dialog modal- modal-dialog-centered modal-dialog-scrollable modal-sm" role="document">
      <div class="modal-content">
        <div class="modal-body p-0">
          <div class="card bg-white shadow border-0">

            <div class="card-body px-lg-5 py-lg-5">

              <div class="mb-2">
                <h5 class="position-relative">{{trans('general.new_message')}}
                  <small data-dismiss="modal" class="btn-cancel-msg">{{ trans('admin.cancel') }}</small>
                </h5>

              </div>

              <div class="position-relative">
                <span class="my-sm-0 btn-new-msg">
                  <i class="fa fa-search"></i>
                </span>

                <input class="form-control input-new-msg rounded mb-2" id="searchCreator" type="text" name="q" autocomplete="off" placeholder="{{ trans('general.find_user') }}" aria-label="Search">
              </div>

              <div class="w-100 text-center mt-3 display-none" id="spinner">
                <span class="spinner-border align-middle text-primary"></span>
              </div>

              <div id="containerUsers" class="text-center"></div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </div><!-- End Modal new Message -->



  <!--- bulk message option---> 
  <div class="modal fade" id="bulkMessage" tabindex="-1" role="dialog" aria-labelledby="modal-form" aria-hidden="true">
    <div class="modal-dialog modal- modal-dialog-centered modal-dialog-scrollable modal" role="document">
      <div class="modal-content">
        <div class="modal-body p-0">
          <div class="card bg-white shadow border-0">

            <div class="card-body ">

              <div class="mb-2">
                <h5 class="position-relative">Create New Message
                  <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
                  {{-- <small data-dismiss="modal" class="btn-cancel-msg">{{ trans('admin.cancel') }}</small> --}}
                </h5>

              </div>

              <div class="position-relative">
                <div class="row">
                  <div class="col-md-12">
                    <div class="alert alert-success grid-col-span m-0 text-center" id='alert-success' style="display:none;"
                      role="alert">
                    </div>
                    <div class="alert alert-danger grid-col-span m-0 text-center" id='alert-error' style="display:none;"
                      role="alert">
                    </div>
                  </div>
                </div>

              <form id="editform" action="{{route('send-bulk-message')}}" method="POST">
                @csrf
                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group">
                        <label><i class="fa fa-bullhorn text-muted"></i>Write a Message</label>
                        <textarea name="message" id="message" required rows="5" cols="40" class="form-control textareaAutoSize"></textarea>
                        <small class="text-danger" id="message_error"></small>
                      {{-- <div id="the-count" class="float-right my-2">
                        <span id="current1"></span>
                        <span id="maximum">/ {{$settings->story_length}}</span>
                      </div> --}}
                      </div>
                    </div>
                  </div>

                  <div class="row mb-3">
                    <div class="col-md-6">
                      <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" class="custom-control-input send_to_check" id="customRadio" checked name="send_to" value="all">
                        <label class="custom-control-label" for="customRadio">Send to All</label>
                      </div>
                      
                    </div>
                    <div class="col-md-6">  
                      <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" class="custom-control-input send_to_check" id="customRadio2" name="send_to" value="selectes">
                        <label class="custom-control-label" for="customRadio2">Select Subscribers</label>
                      </div>
                    </div>
                    
                  </div>
                  {{-- {{dd(\Auth::id() .' '. \Auth::user()->userSubscriptions)}} --}}
                  <div class="row d-none" id="subs_ids">
                    <div class="col-md-12">
                      <div class="form-group">
                        <label><i class="fa fa-users text-muted"></i>Select Subscribers</label>
                        <select name="subscriber_ids[]" id="subscriber_ids" multiple  class="form-control textareaAutoSize">
                          <option value="" disabled>Select Subscribers</option>
                          @foreach (\Auth::user()->userSubscriptions as $item)
                            <option value="{{$item->subscribed()->id}}">{{ucfirst($item->subscribed()->name)}}</option>
                          @endforeach
                        </select>
                        <small class="text-danger" id="subscriber_ids_error"> </small>
                      {{-- <div id="the-count" class="float-right my-2">
                        <span id="current1"></span>
                        <span id="maximum">/ {{$settings->story_length}}</span>
                      </div> --}}
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="submit" class="btn editfrontRto btn-primary">Save changes</button>
                    <button  disabled
                    class="btn-pro btn btn-primary btn-lg align-items-center d-none justify-content-center"><span
                      class="spinner-border spinner-border-sm mr-1" role="status"
                      aria-hidden="true"></span>Processing</button>
                  </div>
                </form>
              </div>

              {{-- <div class="w-100 text-center mt-3 display-none" id="spinner">
                <span class="spinner-border align-middle text-primary"></span>
              </div>

              <div id="containerUsers" class="text-center"></div> --}}

            </div>
            
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('javascript')
<script src="{{ asset('public/js/paginator-messages.js') }}"></script>
<script src="{{ asset('public/plugins/select2/select2.min.js') }}"></script>
<script src="{{ asset('public/js/malsap.form.min.js') }}"></script>

<script>

  $(function(){
    $('#subscriber_ids').select2({
      width:'100%',
    });

    var options = {
			dataType: 'json',
			beforeSubmit: function () {
				$('.editfrontRto').addClass('d-none');
				$('.btn-pro').removeClass('d-none').addClass('d-flex');
				// $('#btn-pro').removeClass('d-none');
			},
			success: function (data) {
				$('html, body').animate({scrollTop:0}, 'slow');
				// response=$.parseJSON(data);
				response = data;
				// console.log(response);
				$('.editfrontRto').removeClass('d-none');
				$('.btn-pro').addClass('d-none').removeClass('d-flex');
				if (response.feedback === "false") {
					$.each(response.errors, function (key, value) {
						$('#' + key + '_error').html(value);
					});
				}
				else if (response.feedback === "other_error") {
					$('#' + response.id).html(response.custom_msg);
				}
				else {
					$('#alert-error').hide();
					$('#alert-success').html(response.msg);
					$('#alert-success').show();
					setTimeout(() => {
						window.location.reload();
					}, 1000);
				}
			},
			error: function (jqXHR, exception) {
				$('html, body').animate({scrollTop:0}, 'slow');
				// form.find('button[type=submit]').html('<i aria-hidden="true" class="fa fa-check"></i> {{ __('Save') }}');
				$('.editfrontRto').removeClass('d-none');
				$('.btn-pro').addClass('d-none').removeClass('d-flex');
				var msg = '';
				if (jqXHR.status === 0) {
					msg = 'Not Connected.\n Verify Network.';
				} else if (jqXHR.status == 404) {
					msg = 'Requested page not found. [404]';
				} else if (jqXHR.status == 500) {
					msg = 'Internal Server Error [500].';
				} else if (exception === 'parsererror') {
					msg = 'Requested JSON parse failed.';
				} else if (exception === 'timeout') {
					msg = 'Time out error.';
				} else if (exception === 'abort') {
					msg = 'Ajax request aborted.';
				} else {
					msg = 'Uncaught Error, Please try again later';
				}
				$('#alert-error').html(msg);
				$('#alert-error').show();
			},
		};
		$('#editform').ajaxForm(options);
	
  });

  $(document).on('change', '.send_to_check', function(){
    if($('#customRadio2').is(':checked')){
            $('#subs_ids').removeClass('d-none');
        }
        else{
            $('#subs_ids').addClass('d-none');
        }
  });
</script>
@endsection
