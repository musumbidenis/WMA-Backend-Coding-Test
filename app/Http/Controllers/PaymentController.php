<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use DateTime;
use DB;

class PaymentController extends Controller
{
    public function authenticate(Request $request)
    {

        /* Fetch user details from the database
        *  Testing Case - Picking the first record from DB
        *  Persist the user details in a session
        */
        $userDetails = User::get()->first();
        $request->session()->put('userDetails', $userDetails);

        return redirect('/api/subscribe');

    }


    /** Intiate the subscription payment */
    public function subscribe(Request $request)
    {
        /* Fetch user details */
        $email = $request->session()->get('userDetails')['email'];
        $username = $request->session()->get('userDetails')['username'];
        $userStatus = $request->session()->get('userDetails')['userStatus'];

        /* Check user status */
        if ($userStatus == 'is_premium'){
            $now = now();
            $renewalDate = $request->session()->get('userDetails')['renewalDate'];
            $token = $request->session()->get('userDetails')['token'];

            /* Check if user is due to renew subscription */
            if($now >= $renewalDate){

                $this->renew($token, $email);

            }else{

                echo "You already have an active subscription";

            }
            
        }else{
            /* Activate premium subscription for user
            *  Prepare our rave request
            */
            $data = [
                'tx_ref' => time(),
                'amount' => 20,
                'currency' => 'USD',
                'payment_options' => 'Card',
                'redirect_url' => 'http://localhost:8000/api/activate',
                'customer' => [
                    'email' => $email,
                    'name' => $username
                ],
                'meta' => [
                    'price' => 20
                ],
                'customizations' => [
                    'title' => 'WMA Monthly Subscription',
                    'description' => 'Test monthly subscription to WMA'
                ]
            ];

            /* Call flutterwave endpoint */
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.flutterwave.com/v3/payments',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer FLWSECK_TEST-cd0373388aa0bd7501258419f36d7450-X',
                'Content-Type: application/json'
            ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);

            $res = json_decode($response);

            /* Redirect to payment page if success*/
            if($res->status == 'success')
            {
                $link = $res->data->link;
                return redirect($link);
            }
            else
            {
                echo 'We cannot process your payment now. Try again later';
            }
        }

    }

    public function renew($token, $email)
    {
        /* Get the user token
        *  Process the user renewal payment
        */

        $data = [
            'token' => $token,
            'tx_ref' => time(),
            'amount' => 20,
            'country' => 'NG',
            'currency' => 'USD',
            'email' => $email
        ];

        /* Call flutterwave endpoint */
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.flutterwave.com/v3/tokenized-charges',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer FLWSECK_TEST-cd0373388aa0bd7501258419f36d7450-X',
            'Content-Type: application/json'
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $res = json_decode($response);

        /* Redirect to payment page if success*/
        if($res->status == 'success')
        {
            /* Update billing date and renewal date */
            echo "Monthly Subscription has been renewed";
        }
        else
        {
            echo 'We cannot process your payment now. Try again later';
        } 
            
    }


    /** Subscription activation payment */
    public function activate(Request $request)
    {
        /* Get the user from session */

        if(isset($_GET['status']))
        {
            /* Check the payment process status */
            if($_GET['status'] == 'cancelled')
            {
                echo 'You cancelled the payment';
            }
            elseif($_GET['status'] == 'successful')
            {
                /* Get the transaction id
                *  Process the user payment
                */
                $txid = $_GET['transaction_id'];

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/{$txid}/verify",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                    CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Authorization: Bearer FLWSECK_TEST-cd0373388aa0bd7501258419f36d7450-X"
                    ),
                ));
                
                $response = curl_exec($curl);
                
                curl_close($curl);
                
                $res = json_decode($response);
                if($res->status)
                {
                    $amountPaid = $res->data->charged_amount;
                    $amountBilled = $res->data->meta->price;

                    /* Cross-check the amount paid with amount billed */
                    if($amountPaid >= $amountBilled)
                    {
                        /* Update user details in DB
                        *  Assign token to user for recurring payments
                        */
                        $userId = $request->session()->get('userDetails')['userId'];
                        $token = $res->data->card->token;

                        DB::update(
                            'update users set token = ?, billingDate = ?, userStatus = ?, paymentStatus = ? where userId = ?', 
                            [$token, now(), 'is_premium', 'active', $userId]
                        );

                        echo 'Your subscription payment was successful';

                    }
                    else
                    {
                        echo 'Please pay the full subscription amount required to activate your premium product';
                    }
                }
                else
                {
                    echo 'We cannot process your payment now. Try again later';
                }
            }
        }
    }

}
