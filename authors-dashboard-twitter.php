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


require_once('app-credentials.php');
// Load the Twitter API wrapper.
require_once('TwitterAPIExchange.php');


// Creating a request.
function create_twitter_request( $search, $app_credentials ) {
	$url            = 'https://api.twitter.com/1.1/search/tweets.json';
	$get_field      = '?q=' . $search . '&tweet_mode=extended';
	$request_method = 'GET';
	$twitter        = new TwitterAPIExchange($app_credentials);
	$json_raw       =  $twitter->setGetfield($get_field)
							   ->buildOauth($url, $request_method)
						 	   ->performRequest();
	$results = json_decode( $json_raw, true );
	return $results;
}

$results = create_twitter_request( 'sapiens.org', $app_credentials );
$url_mentions = find_url_mentions( $results );
echo '<pre>';
print_r($url_mentions);
echo '</pre>';

// TODO: This function should return an array of tweets, each containing
// the found URL and the full text.
function find_url_mentions( $results ) {
	$url_regex = '@((https?://)?([-\\w]+\\.[-\\w\\.]+)+\\w(:\\d+)?(/([-\\w/_\\.]*(\\?\\S+)?)?)*)@';
	$tweets = array();
	foreach ($results['statuses'] as $result) {
		// print_r($result['full_text']);
		$tweet = array();
		$tweet['full_text'] = $result['full_text'];
		if (preg_match($url_regex, $result['full_text'], $matches)) {
			// $real_url = get_real_url($matches[0]);
			$tweet['url'] = $matches[0];
		}
		array_push($tweets, $tweet);
	}
	return $tweets;
}

function get_real_url( $short_url ) {
	$short_url_headers = get_headers($short_url , true);
	return $short_url_headers['Location'];
}

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
	return $all_permalinks;
}
// add_action('init', 'get_all_permalinks');

// Adding rewrites. The code below only takes effect after flushing the
// rewrite rules.
add_action( 'init', 'stats_endpoint_init' );
add_action( 'template_include', 'stats_endpoint_template_include' );
/**
 * Add our new stats endpoint
 */
function stats_endpoint_init(){
	add_rewrite_endpoint( 'stats', EP_PERMALINK | EP_PAGES );
}
/**
 * Respond to our new endpoint
 *
 * @param $template
 *
 * @return mixed
 */
function stats_endpoint_template_include( $template ){
	global $wp_query;
	// since the "stats" query variable does not require a value, we need to
	// check for its existence
	if ( is_singular() && isset( $wp_query->query_vars['stats'] ) ) {
		$post = get_post();
		print_r($post);
		plugin_dir_path( __FILE__ ) . '/stats-page.php';
	}
	return $template;
}


