<?php

namespace App\Http\Controllers;

use Mail;
use Image;
use App\Helper;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Messages;
use App\Models\Withdrawals;
use Illuminate\Http\Request;
use App\Models\AdminSettings;
use App\Models\Conversations;
use App\Models\Subscriptions;
use App\Models\PaymentGateways;
use Illuminate\Support\Facades\Auth;
use Fahim\PaypalIPN\PaypalIPNListener;
use Illuminate\Support\Facades\Validator;


class SubscriptionsController extends Controller
{

  public function __construct(Request $request, AdminSettings $settings) {
    $this->request = $request;
    $this->settings = $settings::first();
  }

  /**
	 * Buy subscription
	 *
	 * @return Response
	 */
  public function buy()
  {
    
    // Check if subscription exists
    $checkSubscription = Auth::user()->mySubscriptions()->where('user_id', $this->request->id)->whereDate('ends_at', '>=', Carbon::today())->count();

    if ($checkSubscription != 0) {
      return response()->json([
          'success' => false,
          'errors' => ['error' => trans('general.subscription_exists')],
      ]);
    }

    // Find the User
    $user = User::whereVerifiedId('yes')->whereId($this->request->id)->where('id', '<>', Auth::user()->id)->firstOrFail();

    // Validate Payment Gateway
    Validator::extend('check_payment_gateway', function($attribute, $value, $parameters) {
      return PaymentGateways::find($value);
    });

    $messages = array (
    'payment_gateway.check_payment_gateway' => trans('general.payments_error'),
  );

  //<---- Validation
  $validator = Validator::make($this->request->all(), [
      'payment_gateway' => 'required|check_payment_gateway',
      'agree_terms' => 'required',
      ], $messages);

    if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->getMessageBag()->toArray(),
            ]);
        }
// dd($this->request);
        // Get name of Payment Gateway
        $payment = PaymentGateways::find($this->request->payment_gateway);
// dd($this->request->except(['_token']));
        // Send data to the payment processor
        return redirect()->route(str_slug($payment->name), $this->request->except(['_token']));

  }// End Method Send

  public function buy_free(){
    // Check if subscription exists
    $checkSubscription = Auth::user()->mySubscriptions()->where('user_id', $this->request->id)->whereDate('ends_at', '>=', Carbon::today())->count();

    if ($checkSubscription != 0) {
      return response()->json([
          'success' => false,
          'errors' => ['error' => trans('general.subscription_exists')],
      ]);
    }

    // Find the User
    $user = User::whereVerifiedId('yes')->whereId($this->request->id)->where('id', '<>', Auth::user()->id)->firstOrFail();

    $validator = Validator::make($this->request->all(), [
      // 'payment_gateway' => 'required|check_payment_gateway',
      'agree_terms' => 'required',
      ]);

    if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->getMessageBag()->toArray(),
            ]);
        }

    $sql          = new Subscriptions();
    $sql->user_id = $this->request->id;
    $sql->ends_at = Carbon::now()->add(1, 'month');
    // $sql->payment_gateway = '';
    $sql->stripe_plan = \Auth::user()->plan;
    // dd(\Auth::user()->name);
    if($sql->save()){

      $temp_user = \App\Models\User::find($this->request->id);
      $time = Carbon::now();
      if($temp_user->welcome_msg){

        $conversation = Conversations::where('user_1', Auth::user()->id)
  				->where('user_2', $this->request->id)
  				->orWhere('user_1', $this->request->id)
          ->where('user_2', Auth::user()->id)->first();
          
          if (! isset($conversation)) {
            $newConversation = new Conversations;
            $newConversation->user_1 = $this->request->id;
            $newConversation->user_2 = Auth::user()->id;
            $newConversation->updated_at = $time;
            $newConversation->save();
            $conversationID = $newConversation->id;
  
          } else {
            $conversation->updated_at = $time;
            $conversation->save();
  
            $conversationID = $conversation->id;
          }
        

            $message = new Messages;
            $message->conversations_id = $conversationID;
            $message->from_user_id    = $this->request->id;
            $message->to_user_id      = \Auth::id();
            $message->message         = trim(Helper::checkTextDb($temp_user->welcome_msg));
            $message->updated_at      = $time;
            $message->save();
      }



      $sql->sendEmailAndNotify(\Auth::user()->id,\Auth::user()->name, $this->request->id);
      $sql->sendEmailAndNotifytoCurrentUser(\Auth::user()->id, \Auth::user()->name, $this->request->id);

      session()->put('subscription_success', trans('general.subscription_success'));
      // return redirect()->route('profile-d',$user->username);
      return json_encode([
        'url' => route('profile-d',$user->username),
        'success' => true
      ]);
    }    

  }
}
