<?php
/**
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

@package Authors Dashboard Twitter
 */

// TODO LIST:
// - Add the Twitter API. [DONE]
// - Get data. [DONE]
// - Display the data in a comprehensible way. [DONE]
// - Fix error regarding short urls not expanding. [DONE]
// - Check rate limit for Twitter data requests.
// - Start thinking about this plugin's architecture and its integration
// with the Authors Dashboard plugin.
// - Fix Twitter query since at the time it only shows tweets from (apparently)
// the last 5hs or so.
// - Improve Twitter query efficiency, it takes 5~7 segs to complete at current
// speed.
// Test if adding ' -RT' at the end of the search URL excludes retweets.

// Load private credentials.
require_once 'twitter-app-credentials.php';
// Load the Twitter API wrapper.
require_once 'TwitterAPIExchange.php';

$results      = create_twitter_request( 'www.sapiens.org', $app_credentials );
$url_mentions = find_url_mentions( $results );
print_r( $url_mentions );

// store_url_mentions( $url_mentions );


/**
 * Searches Twitter for all Tweets containing a specific
 * string.
 *
 * @param  string $search Target.
 * @param  array  $app_credentials Access tokens, keys, secret.
 * @return array  $results All Tweets found.
 */
function create_twitter_request( $search, $app_credentials ) {
	$url            = 'https://api.twitter.com/1.1/search/tweets.json';
	$get_field      = '?q=' . $search . '&tweet_mode=extended';
	$request_method = 'GET';
	$twitter        = new TwitterAPIExchange( $app_credentials );
	$json_raw       = $twitter->setGetfield( $get_field )
								->buildOauth( $url, $request_method )
								->performRequest();
	$results        = json_decode( $json_raw, true );
	return $results;
}


/**
 * Searches through Twitter data for a specific URL, stores
 * Tweets containing it.
 *
 * @param array $results All Tweets found.
 * @return array $tweets Formatted Tweets.
 */
function find_url_mentions( $results ) {
	$url_regex   = "/(?i)\b((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\".,<>?«»“”‘’]))/";
	$tweets      = array();
	$shared_urls = array();
	foreach ( $results['statuses'] as $result ) {
		$tweet = array();
		// If it's an original tweet.
		if ( ! isset( $result['retweeted_status'] ) ) {
			// If it contains URLs (is this necessary?).
			if ( preg_match_all( $url_regex, $result['full_text'], $matches ) ) {
				$url_targets = array();
				foreach ( $matches[0] as $short_url ) {
					$expanded_url = expand_url( $short_url );
					if ( ! empty( $expanded_url ) ) {
						array_push( $url_targets, $expanded_url );
					}
				}
				if ( ! empty( $url_targets ) ) { // Unnecessary check?
					$found_urls = $url_targets;
				}
				$tweet = array(
					'full_text'   => $result['full_text'],
					'id'          => $result['id'],
					'urls'        => $matches[0],
					'url_targets' => $found_urls,
					'created_at'  => $result['created_at'],
					'user'        => $result['user']['screen_name'],
					'url_count'   => 0,
				);
				array_push( $tweets, $tweet ); // Return filtered tweets.
			}
		}
	}
	// Counting total times a URL was shared.
	foreach ( $tweets as $tweet ) {
		// If it contains more than 1 URL, get them all.
		if ( is_array( $tweet['url_targets'] ) ) {
			foreach ( $tweet['url_targets'] as $url ) {
				array_push( $shared_urls, $url );
			}
		} else {
			array_push( $shared_urls, $tweet['url_targets'] );
		}
	}
	$shared_urls_count = array_count_values( $shared_urls );
	// Adding a url value to each Tweet.
	foreach ( $tweets as $tweet => $value ) {
		// If an URL exists as as key in the $shared_url_counts array, change its
		// 'url_count' value to the one found in it.
		if ( array_key_exists( $value['url_targets'][0], $shared_urls_count ) ) {
			$tweets[ $tweet ]['url_count'] = $shared_urls_count[ $value['url_targets'][0] ];
		}
	}
	return $tweets;
}


/**
 * Expands tiny URLs found in Tweets, makes sure its related to
 * the site (and not a Twitter link) then returns it.
 *
 * @param string $short_url Tiny URL.
 * @return string $url Expanded URL.
 */
function expand_url( $short_url ) {
	$short_url_headers = get_headers( $short_url, true );
	$site_url          = 'https://www.sapiens.org'; // $site_url = get_site_url();
	if ( isset( $short_url_headers['Location'] ) ) {
		$location = $short_url_headers['Location'];
	} elseif ( isset( $short_url_headers['location'] ) ) {
		$location = $short_url_headers['location'];
	} else {
		return $short_url;
	}

	if ( is_array( $location ) ) {
		foreach ( $location as $location ) {
			return expand_url( $url );
		}
	} elseif ( is_string( $location ) ) {
		if ( strpos( $location, $site_url ) !== false ) {
			// Removes anchor tag.
			$url = strtok( $location, '#' );
			return $url;
		} else {
			return null;
		}
	} else {
		return null;
	}
}


/**
 * TODO: Copy the store_page_views() functionality.
 * For each URL mention search through permalinks for
 * a match, if found store it in the post meta.
 *
 * @param array $url_mentions Mentions found.
 * @return void
 */
function store_url_mentions( $url_mentions ) {
	$args            = array(
		'posts_per_page' => -1,
		'post_type'      => 'any',
	);
	$all_posts_query = new WP_Query( $args );

	while ( $all_posts_query->have_posts() ) {
		$all_posts_query->the_post();
		$post_id   = $all_posts_query->post->ID;
		$permalink = get_permalink( $post_id );

		foreach ( $url_mentions as $url_mention ) {
			if ( $url_mention['url_targets'][0] === $permalink ) {
				//echo 'Link stored: ' . $permalink . '<br>';
				update_post_meta( $post_id, 'twitter_data', $url_mention );
			}
		}
	}
	wp_reset_postdata();// Restore original Post Data.
}
