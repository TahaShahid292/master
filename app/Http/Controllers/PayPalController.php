<?php

namespace App\Http\Controllers;

use Mail;
use App\Helper;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Messages;
use App\Models\Transactions;
use Illuminate\Http\Request;
use App\Models\AdminSettings;
use App\Models\Conversations;
use App\Models\Notifications;
use App\Models\Subscriptions;
use App\Models\PaymentGateways;
use Illuminate\Support\Facades\Auth;
use Fahim\PaypalIPN\PaypalIPNListener;
use Illuminate\Support\Facades\Validator;

class PayPalController extends Controller
{
  public function __construct(AdminSettings $settings, Request $request) {
		$this->settings = $settings::first();
		$this->request = $request;
	}

  /**
   * Show/Send form PayPal
   *
   * @return response
   */
    public function show()
    {

    if (!$this->request->expectsJson()) {
        abort(404);
    }

    // Find the User
    $user = User::whereVerifiedId('yes')->whereId($this->request->id)->where('id', '<>', Auth::user()->id)->firstOrFail();

    if($user->promotion_price && Helper::check_promotion_end_date($user->promotion_end_date)){
      $price = $user->promotion_price;
    }
    else{
      $price = $user->price;
    }
      // Get Payment Gateway
      $payment = PaymentGateways::findOrFail($this->request->payment_gateway);

      // Verify environment Sandbox or Live
      if ($payment->sandbox == 'true') {
				$action = "https://www.sandbox.paypal.com/cgi-bin/webscr";
				} else {
				$action = "https://www.paypal.com/cgi-bin/webscr";
				}

        $urlSuccess = url('buy/subscription/success', $user->username);
  			$urlCancel   = url('buy/subscription/cancel', $user->username);
  			$urlPaypalIPN = url('paypal/ipn');

  			return response()->json([
  					        'success' => true,
  					        'insertBody' => '<form id="form_pp" name="_xclick" action="'.$action.'" method="post"  style="display:none">
  					        <input type="hidden" name="cmd" value="_xclick">
  					        <input type="hidden" name="return" value="'.$urlSuccess.'">
  					        <input type="hidden" name="cancel_return"   value="'.$urlCancel.'">
  					        <input type="hidden" name="notify_url" value="'.$urlPaypalIPN.'">
  					        <input type="hidden" name="currency_code" value="'.$this->settings->currency_code.'">
                    <input type="hidden" name="amount" id="amount" value="'.$price.'">
                    
  					        <input type="hidden" name="custom" value="id='.$this->request->id.'&amount='.$price.'&subscriber='.Auth::user()->id.'&name='.Auth::user()->name.'">
  					        <input type="hidden" name="item_name" value="'.trans('general.subscription_desc_buy').' @'.$user->username.'">
  					        <input type="hidden" name="business" value="'.$payment->email.'">
  					        <input type="submit">
  					        </form> <script type="text/javascript">document._xclick.submit();</script>',
  					    ]);
    }

    /**
     * PayPal IPN
     *
     * @return void
     */
    public function paypalIpn() {
// dd('hello');
      $ipn = new PaypalIPNListener();

			$ipn->use_curl = false;

      $payment = PaymentGateways::find(1);

			if ($payment->sandbox == 'true') {
				// SandBox
				$ipn->use_sandbox = true;
				} else {
				// Real environment
				$ipn->use_sandbox = false;
				}

	    $verified = $ipn->processIpn();

			$custom  = $_POST['custom'];
			parse_str($custom, $data);

			$payment_status = $_POST['payment_status'];
			$txn_id         = $_POST['txn_id'];

      //========== Processor Fees
      $processorFees = $data['amount'] - (  $data['amount'] * $payment->fee/100 ) - $payment->fee_cents;

      // Earnings Net User
      $earningNetUser = number_format($processorFees - (  $processorFees * $this->settings->fee_commission/100  ), 2);

      // Earnings Net Admin
      $earningNetAdmin = number_format($processorFees - $earningNetUser, 2);
        // dd('heloo');
	    if ($verified) {
				if ($payment_status == 'Completed') {

	          // Check outh POST variable and insert in DB
						$verifiedTxnId = Transactions::where('txn_id', $txn_id)->first();

			if (! isset($verifiedTxnId)) {
        $temp=User::where('id',$data['subscriber'])->first();
        // Insert DB
        $sql          = new Subscriptions;
        $sql->user_id = $data['id'];
        $sql->ends_at = Carbon::now()->add(1, 'month');
        // $sql->payment_gateway = 'PayPal';
        $sql->stripe_plan = $temp->plan;
        $sql->save();

        // Insert Transaction
        $txn = new Transactions;
        $txn->txn_id  = $txn_id;
        $txn->user_id = $data['subscriber'];
        $txn->subscriptions_id = $sql->id;
        $txn->subscribed = $data['id'];
        $txn->amount   = $data['amount'];
        $txn->earning_net_user  =  $earningNetUser;
        $txn->earning_net_admin = $earningNetAdmin;
        $txn->payment_gateway = 'PayPal';
        $txn->save();

        $temp_user = \App\Models\User::find($data['id']);
      $time = Carbon::now();
      if($temp_user->welcome_msg){

        $conversation = Conversations::where('user_1', $data['id'])
  				->where('user_2', $data['subscriber'])
  				->orWhere('user_1', $data['subscriber'])
          ->where('user_2', $data['id'])->first();
          
          if (! isset($conversation)) {
            $newConversation = new Conversations;
            $newConversation->user_1 = $data['id'];
            $newConversation->user_2 = $data['subscriber'];
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
            $message->from_user_id    = $data['id'];
            $message->to_user_id      = $data['subscriber'];
            $message->message         = trim(Helper::checkTextDb($temp_user->welcome_msg));
            $message->updated_at      = $time;
            $message->save();
      }


        $sql->sendEmailAndNotify($temp->id, $temp->name, $data['id']);
        $sql->sendEmailAndNotifytoCurrentUser($temp->id, $temp->name, $data['id']);

        //Add Earnings to User
        User::find($data['id'])->increment('balance', $earningNetUser);

        // Send Notification to User --- destination, author, type, target
        Notifications::send($data['id'], $data['subscriber'], '1', $data['id']);

			}// <--- Verified Txn ID

	      } // <-- Payment status
	    } else {
	    	//Some thing went wrong in the payment !
	    }

    }//<----- End Method paypalIpn()
}
