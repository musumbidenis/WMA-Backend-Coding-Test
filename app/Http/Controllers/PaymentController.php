<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use DateTime;
use DB;

class PaymentController extends Controller
{
    /* Authenticate users */
    public function user(Request $request)
    {

        /* Fetch user details from the database
        *  Testing Case - Picking the first record from DB
        *  Persist the user details in a session
        */
        $userDetails = User::get()->first();
        $request->session()->put('userDetails', $userDetails);

        echo json_encode($userDetails, JSON_PRETTY_PRINT);

    }


    /* Toggle user status */
    public function changeStatus(Request $request)
    {
        /* Fetch user details from session */
        $userId = $request->session()->get('userDetails')['userId'];
        $userStatus = $request->session()->get('userDetails')['userStatus'];

        if($userStatus == 'is_premium'){

            DB::update("update users set userStatus = 'not_premium', token = null, billingDate = null, renewalDate = null, paymentStatus = 'inactive' where userId = ?", [$userId]);
            $userDetails = User::get()->first();
            $request->session()->put('userDetails', $userDetails);
    
            echo "Deactivation of premium status was succesfull";

        }else{

            /* Initiate premium subscription */
            return redirect('api/subscribe');

        }

        
    }


    /** Intiate the subscription payment */
    public function subscribe(Request $request)
    {
        /* Fetch user details from session */
        $userId = $request->session()->get('userDetails')['userId'];
        $username = $request->session()->get('userDetails')['username'];
        $email = $request->session()->get('userDetails')['email'];
        $userStatus = $request->session()->get('userDetails')['userStatus'];
        $token = $request->session()->get('userDetails')['token'];
        $renewalDate = $request->session()->get('userDetails')['renewalDate'];

        /* Check user status */
        if ($userStatus == 'is_premium'){
            /* Check if user is due to renew subscription 
            *  Use the user token to renew their subscription
            */
            if(now() >= $renewalDate){

                $this->renew($userId, $email, $token);

            }else{

                echo "You already have an active subscription";

            }
            
        }else{
            /* Initiate premium subscription activation for user
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


    /* Subscription renewal */
    public function renew($userId, $email, $token)
    {
        /* Get the user token and email
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
            $billingDate = now();
            $renewalDate = now()->addDays(30);

            DB::update(
                'update users set billingDate = ?, renewalDate = ?, userStatus = ?, paymentStatus = ? where userId = ?', 
                [$billingDate, $renewalDate, 'is_premium', 'active', $userId]
            );

            echo "Monthly Subscription has been renewed";
        }
        else
        {
            echo 'We cannot process your payment now. Try again later';
        } 
            
    }


    /** Subscription activation */
    public function activate(Request $request)
    {
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
                    /* Update user details in DB
                    *  Assign token to user for recurring payments
                    */
                    $userId = $userId = $request->session()->get('userDetails')['userId'];
                    $token = $res->data->card->token;
                    $billingDate = now();
                    $renewalDate = now()->addDays(30);//Monthly subscription of 30 days

                    DB::update(
                        'update users set token = ?, billingDate = ?, renewalDate = ?, userStatus = ?, paymentStatus = ? where userId = ?', 
                        [$token, $billingDate, $renewalDate, 'is_premium', 'active', $userId]
                    );

                    echo 'Your subscription activation payment was successful';


                }else{

                    echo 'We cannot process your payment now. Try again later';

                }
            }
        }
    }

}
