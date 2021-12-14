<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /** Intiate the subscription payment */
    public function pay()
    {
        /* Fetch a user details from the database
        *
        */



        /* Prepare our rave request */
        $data = [
            'tx_ref' => time(),
            'amount' => 20,
            'currency' => 'USD',
            "interval" => "monthly",
            "duration" => 48,
            'payment_options' => 'Card',
            'redirect_url' => 'http://localhost:8000/api/process',
            'customer' => [
                'email' => 'test@mail.com',
                'name' => 'Test1234'
            ],
            'meta' => [
                'price' => 20
            ],
            'customizations' => [
                'title' => 'Monthly Subscription',
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
            echo 'We can not process your payment at this time';
        }

    }



    /** Process the subscription payment */
    public function process()
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
                    $amountPaid = $res->data->charged_amount;
                    $amountBilled = $res->data->meta->price;

                    /* Cross-check the amount paid with amount billed */
                    if($amountPaid >= $amountBilled)
                    {
                        echo 'Your subscription payment was successful';

                    }
                    else
                    {
                        echo 'Please pay the full subscription amount required to activate your premium product';
                    }
                }
                else
                {
                    echo 'Cannot process payment now. Try again later';
                }
            }
        }
    }

}
