<?php
return [
 'google'=>['client_id'=>$_ENV['GOOGLE_CLIENT_ID']??'','client_secret'=>$_ENV['GOOGLE_CLIENT_SECRET']??'','redirect_uri'=>$_ENV['GOOGLE_REDIRECT_URI']??''],
 'contifico'=>['api_url'=>$_ENV['CONTIFICO_API_URL']??'','api_key'=>$_ENV['CONTIFICO_API_KEY']??''],
];
