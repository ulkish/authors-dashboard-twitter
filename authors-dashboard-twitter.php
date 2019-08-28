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

// Load the Twitter API wrapper.
require_once('TwitterAPIExchange.php');

$settings = array(
	'oauth_access_token'        => "1166074328800251906-1TfBHgEq3qrvSaEeSFSnL8vWFVjje0",
	'oauth_access_token_secret' => "tx4utCFkiByfIvrj7JIcgfrpPIjx9PN7hRsRVKAEyoUaQ",
	'consumer_key'              => "pHRdhG7rYPWGZ3TTkHecozPfa",
	'consumer_secret'           => "xU0AwSLGGcUrRIIp6zd46ovq3YtPCC8hcaj1gk1L36u74iJ5Jk"
);

// Creating a request.
$url            = 'https://api.twitter.com/1.1/search/tweets.json';
$get_field      = '?q=http%3A%2F%2Flocalhost%2Ftestinginstall%2Ftest-post-3%2F&src=typed_query';
$request_method = 'GET';
// $twitter       = new TwitterAPIExchange($settings);
// $jsonraw       =  $twitter->setGetfield($get_field)
// 						  ->buildOauth($url, $request_method)
// 					 	  ->performRequest();
// $json = json_decode( $jsonraw, true );

// echo '<pre>';
// print_r($json['statuses']);
// echo '</pre>';


function get_all_permalinks() {
	$args = array(
		'posts_per_page' => -1,
		'post_type'		 => 'any',
	);
	$all_posts_query = new WP_Query( $args );
	$all_permalinks = array();
	// Get all the permalinks and store them.
	while( $all_posts_query->have_posts() ) {
		$all_posts_query->the_post();
		$post_id = $all_posts_query->post->ID;
		array_push($all_permalinks, get_permalink( $post_id ));
	}
	wp_reset_postdata();// Restore original Post Data.
	print_r($all_permalinks);
}
// add_action('init', 'get_all_permalinks');

// Adding rewrites. The code below only takes effect after flushing the
// rewrite rules.
add_action( 'init', 'test_rewrite_add_rewrites' );
function test_rewrite_add_rewrites() {
	add_rewrite_endpoint( 'stats', EP_PERMALINK );
}

add_action( 'template_redirect', 'test_rewrite_catch_stats' );
function test_rewrite_catch_stats() {
	if( is_singular() && get_query_var( 'stats' ) ) {
	   $post = get_queried_object();
		$out = array(
			'title'     => $post->post_title,
			'content'   => $post->post_content
		);


		add_filter( 'template_include', function() {
            return plugin_dir_path( __FILE__ ) . '/stats-page.php';
        });
	}
}

add_filter( 'request', 'test_rewrite_filter_request' );
function test_rewrite_filter_request( $vars ) {
	if( isset( $vars['stats'] ) ) $vars['stats'] = true;
	return $vars;
}
