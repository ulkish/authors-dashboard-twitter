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
// - Check rate limit for Twitter data requests.
// - Start thinking about this plugin's architecture and its integration
// with the Authors Dashboard plugin.

// Load private credentials.
require_once __DIR__ . '/twitter-app-credentials.php';
// Load the Twitter API wrapper.
require_once __DIR__ . '/TwitterAPIExchange.php';

// $results      = create_twitter_request( 'sapiens.org', $app_credentials );
// $url_mentions = find_url_mentions( $results );
// print_r( $url_mentions );
// store_url_mentions( $url_mentions );

print_r(get_post_meta(9));

/**
 * Combines the main functions of the plugin into one for
 * easier hooking into WP.
 *
 * @return void
 */
function get_and_store_twitter_data() {
	$app_credentials = array(
		'oauth_access_token'        => '1166074328800251906-1TfBHgEq3qrvSaEeSFSnL8vWFVjje0',
		'oauth_access_token_secret' => 'tx4utCFkiByfIvrj7JIcgfrpPIjx9PN7hRsRVKAEyoUaQ',
		'consumer_key'              => 'pHRdhG7rYPWGZ3TTkHecozPfa',
		'consumer_secret'           => 'xU0AwSLGGcUrRIIp6zd46ovq3YtPCC8hcaj1gk1L36u74iJ5Jk',
	);
	$results         = create_twitter_request( 'sapiens.org', $app_credentials );
	$url_mentions    = find_url_mentions( $results );
	store_url_mentions( $url_mentions );
}
// add_action( 'init', 'get_and_store_twitter_data' ); // Uncomment this if you need to do some testing.

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
	$get_field      = '?q=' . $search . '&tweet_mode=extended&src=typed_query&f=live';
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
					'created_at'  => strtotime( $result['created_at'] ),
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
		foreach ( $value['url_targets'] as $url_target ) {
			if ( array_key_exists( $url_target, $shared_urls_count ) ) {
				$tweets[ $tweet ]['url_count'] = $shared_urls_count[ $url_target ];
			}
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
	if ( ! isset( $short_url ) ) {
		return;
	}
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
			if ( ! isset( $url ) ) {
				return;
			}
			return expand_url( $url );
		}
	} elseif ( is_string( $location ) ) {
		if ( strpos( $location, $site_url ) !== false ) {
			// Removes anchor tag.
			$anchorless_url    = strtok( $location, '#' );
			$parameterless_url = strtok( $anchorless_url, '?' );
			return $parameterless_url;
		} else {
			return null;
		}
	} else {
		return null;
	}
}

/**
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
	$all_posts_query = get_posts( $args );
	foreach ( $all_posts_query as $post ) {
		$post_id   = $post->ID;
		$permalink = get_permalink( $post_id );
		// If sapiens.org is not found within the post permalink, add it.
		if ( strpos( $permalink, 'https://www.sapiens.org' ) === false ) {
			$permalink = str_replace(
				get_site_url(),
				'https://www.sapiens.org',
				$permalink
			);
		}
		foreach ( $url_mentions as $url_mention ) {
			foreach ( $url_mention['url_targets'] as $url_target ) {
				if ( $url_target === $permalink ) {
					$twitter_data       = get_post_meta( $post_id, 'twitter_data' );
					$url_count          = get_post_meta( $post_id, 'tweet_count' );
					$tweet_date_created = get_post_meta( $post_id, 'tweet_date_created' );
					$tweets_ids         = get_post_meta( $post_id, 'tweets_ids' );
					// If all Twitter data is not already set, add it. Else update it.
					if ( empty( $twitter_data ) ) {
						update_post_meta( $post_id, 'twitter_data', $url_mention );
					}
					if ( empty( $url_count ) ) {
						update_post_meta( $post_id, 'tweet_count', $url_mention['url_count'] );
					}
					if ( empty( $tweet_date_created ) ) {
						update_post_meta( $post_id, 'tweet_date_created', $url_mention['created_at'] );
					}
					if ( empty( $tweets_ids ) ) {
						$tweets_array = array();
						array_push( $tweets_array, $url_mention['id'] );
						update_post_meta( $post_id, 'tweets_ids', $tweets_array );
					}
					// If the Tweet we're getting is new, add 1 to the saved Tweet count,
					// update the last_modified date and add the Tweet ID to the tweets array.
					if ( ! empty( $tweet_date_created ) ) {
						if ( $url_mention['created_at'] > $tweet_date_created[0] ) {
							update_post_meta( $post_id, 'tweet_date_created', $url_mention['created_at'] );
							update_post_meta( $post_id, 'tweet_count', $url_count[0] + 1 );
							$tweets_array = get_post_meta( $post_id, 'tweets_ids' );
							array_push( $tweets_array, $url_mention['id'] );
							update_post_meta( $post_id, 'tweets_ids', $tweets_array );
						}
					}
				}
			}
		}
	}
}
