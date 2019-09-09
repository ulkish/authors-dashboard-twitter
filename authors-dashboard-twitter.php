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
// - Display the data in a comprehensible way. [DONE]
// - Start thinking about this plugin's architecture and its integration
// with the Authors Dashboard plugin.
// - Fix Twitter query since at the time it only shows tweets from (apparently)
// the last 24hs or so.
// - Improve Twitter query efficiency, it takes 5~7 segs to complete at current
// speed.
// - Not all tweets matching the search criteria are being returned by the API.
// - Fix error regarding short urls not expanding.

// Load private credentials.
require_once('app-credentials.php');
// Load the Twitter API wrapper.
require_once('TwitterAPIExchange.php');

// $results = create_twitter_request( 'sapiens.org', $app_credentials );
// $url_mentions = find_url_mentions( $results );
$permalinks = get_all_permalinks();
echo '<pre>';
print_r($permalinks);
echo '</pre>';

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


// Returns an array of tweets, each containing a list of found
// URLs and the full text.
function find_url_mentions( $results ) {
	// TODO: Make this whole "check if url" thing a function!
	$url_regex = "/(?i)\b((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\".,<>?«»“”‘’]))/";
	$tweets = array();
	foreach ( $results['statuses'] as $result ) {
		$tweet = array();
		// If it's an original tweet.
		if ( !isset( $result['retweeted_status'] ) ) {
			// If it contains URLs (is this necessary?).
			if ( preg_match_all( $url_regex, $result['full_text'], $matches ) ) {
				$url_targets = array();
				foreach( $matches[0] as $short_url ) {
					if ( $expanded_url = expand_url($short_url) ) {
						array_push($url_targets, $expanded_url);
					}
				}
				if ( !empty($url_targets) ) { // Unnecessary check?
					$found_urls = $url_targets;
				}
				$tweet = array( 'full_text'   => $result['full_text'],
								'id'          => $result['id'],
								'urls'        => $matches[0],
								'url_targets' => $found_urls,
								'created_at'  => $result['created_at'],
								'user'        => $result['user']['screen_name'],
				);
				array_push( $tweets, $tweet ); // Return filtered tweets.
			}
			// array_push( $tweets, $result ); // Return original tweets.
		}
		// array_push( $tweets, $result ); // Return all tweets.
	}
	return $tweets;
}

function expand_url( $short_url ) {
	$short_url_headers = get_headers($short_url , true);
	if(isset($short_url_headers['Location'])) { // Is there a shorter way?
		return $short_url_headers['Location'];
	} else{
		return;
	}
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
		array_push( $all_permalinks, $permalink = array(
            'url' => get_permalink( $post_id ),
            'post_id'   => $post_id,
        ));
	}
	wp_reset_postdata();// Restore original Post Data.
	return $all_permalinks;
}
// add_action('init', 'get_all_permalinks');

// For each url mention, search through permalinks
// for a match, if found, store it in the post meta.
function store_url_mentions( $url_mentions ) {
    $all_permalinks = get_all_permalinks();
    foreach( $url_mentions['url_targets'][0] as $permalink ) {
        if( in_array( $permalink, $all_permalinks ) ) {
            update_post_meta( $permalink['post_id'], 'twitter_data', $twitter_data );
        }
    }
}


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


