<?php
/*
Plugin Name:       Authors Dashboard - Twitter
Plugin URI:        https://tipit.net/
Description:       Display Twitter data.
Version:           1.0
Requires at least: 5.2
Requires PHP:      7.2
Author:            Hugo Moran
Author URI:        https://tipit.net
License:           GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Authors Dashboard is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Authors Dashboard is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Authors Dashboard. If not, see https://www.gnu.org/licenses/gpl-2.0.html
*/


// TODO LIST:
// - Add the Twitter API. [DONE]
// - Get data. [DONE]
// - Display the data in a comprehensible way.

// Loading the Twitter API wrapper.
require_once('TwitterAPIExchange.php');

$settings = array(
	'oauth_access_token' => "1166074328800251906-1TfBHgEq3qrvSaEeSFSnL8vWFVjje0",
	'oauth_access_token_secret' => "tx4utCFkiByfIvrj7JIcgfrpPIjx9PN7hRsRVKAEyoUaQ",
	'consumer_key' => "pHRdhG7rYPWGZ3TTkHecozPfa",
	'consumer_secret' => "xU0AwSLGGcUrRIIp6zd46ovq3YtPCC8hcaj1gk1L36u74iJ5Jk"
);


$url           = 'https://api.twitter.com/1.1/search/tweets.json';
$getfield      = '?q=from%3Atwitterdev&result_type=mixed&count=2';
$requestMethod = 'GET';
$twitter       = new TwitterAPIExchange($settings);
$jsonraw       =  $twitter->setGetfield($getfield)
						  ->buildOauth($url, $requestMethod)
					 	  ->performRequest();

$json = json_decode( $jsonraw );

echo '<pre>';
print_r($json);
echo '</pre>';
