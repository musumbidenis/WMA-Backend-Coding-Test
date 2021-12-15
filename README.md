## Implementation

I used Laravel web application framework to implement the coding-test. Flutterwave payment integration
was used. I hosted the test project on heroku platform [here](https://wma-test.herokuapp.com).The code 
specific implementation files are:

- Implementation Logic:  ``` /app/Http/Controllers/PaymentController.php ```
- API endpoints: ``` /routes/api.php ```

## Assumptions

I created a user in the database for testing the API endpoints. The default user details are as follows:
```
username: musumbidenis
email: musumbidenis@gmail.com
token: null
biling date: null
renewal date: null
user status: not_premium
payment status: inactive
```

On visiting the ``` /api/user ``` endpoint, the user details are persisted in session to be used for testing of 
the other endpoints.

The following are the deliverables for coding test:

### View user details
To view the user details use the ``` /api/user ``` endpoint.

### Activate premium

To activate premium subscription for the user use the ``` /api/subscribe ``` endpoint.

### Toggle user status

To toggle the user status for the user use the ``` /api/change_status ``` endpoint. User status values used are: 
'is_premium', and 'not_premium'.

### Other features

I implemented recurring payments using Flutterwave's token feature in payments. The ```/api/subscribe ``` endpoint
checks if the user has an active premium subscription or needs to renew their monthly subscription. When the
subscription is due, the ``` renew() ``` function is called and subscription renewal is done using the users token.
