<?php
/**
 * Created by PhpStorm.
 * User: aschuurman
 * Date: 04/08/2017
 * Time: 14:21
 */

namespace drsdre\radtools\helpers;

use common\helpers\ArrayHelper;

class Url extends \yii\helpers\Url {

	/**
	 * Merges query parameters into an existing URL overwriting existing query keys
	 *
	 * @param string $url
	 * @param array $query_params
	 *
	 * @return string|bool
	 */
	public static function urlQueryMerge( string $url, array $query_params ) {
		// $url = 'http://www.google.com.au?q=apple&type=keyword';
		// $query = '?q=banana';

		// Stop if the url is empty
		if ( empty($url) ) {
			return false;
		}

		// If empty query, return url as is
		if ( ! $query_params ) {
			return $url;
		}
		// split the url into it's components
		$url_components = parse_url( $url );

		// if we have the query string but no query on the original url
		// just return the URL + query string
		if ( empty( $url_components['query'] ) ) {
			return $url . '?' . http_build_query( $query_params );
		}

		// Turn the url's query string into an array
		parse_str( $url_components['query'], $original_query_string );

		// Find the original query string in the URL and replace it with the new merged
		return str_replace(
			$url_components['query'],
			http_build_query(
				ArrayHelper::merge(
					$original_query_string,
					$query_params
				)
			),
			$url
		);
	}
}