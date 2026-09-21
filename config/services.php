<?php

use Illuminate\Support\Env;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
        'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
    ],
],
'clickpesa' => [
    'client_id' => env('IDPincuIywUDHLlCE1Kqq1NL4X4nuRYO'),
    'api_key' => env('SKAx5dl4ZUB88noZLQaOqLTS0jwi6cGNotwusJ2QzL'),
],

'beem'=>[
    'api_key'=>
    env('BEEM_API_KEY'),
    'secret_key'=>
    env('BEEM_SECRET_KEY'),
    'sender_id'=>
    env('BEEM_SENDER_ID', 'OrderMe'),
],


];
