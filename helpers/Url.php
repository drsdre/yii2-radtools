<?php
/**
 * Created by PhpStorm.
 * User: aschuurman
 * Date: 04/08/2017
 * Time: 14:21
 */

namespace drsdre\radtools\helpers;

use yii\helpers\ArrayHelper;

class Url extends \yii\helpers\Url {

	/**
	 * Merges query parameters into an existing URL overwriting existing query keys
	 *
	 * @param string $base_url
	 * @param array $new_query_params
	 * @param bool $new_overrules query parameters used as base overwritten by url params
	 *
	 * @return string|bool
	 */
	public static function urlQueryMerge( string $base_url, array $new_query_params, bool $new_overrules = true ) {
		// $url = 'http://www.google.com.au?q=apple&type=keyword';
		// $query = '?q=banana';

		// Stop if the url is empty
		if ( empty( $base_url ) ) {
			return false;
		}

		// If empty query, return url as is
		if ( ! $new_query_params ) {
			return $base_url;
		}

		$base_url_components = parse_url( $base_url );

		// if we have the query string but no query on the original url
		// just return the URL + query string
		if ( empty( $base_url_components['query'] ) ) {
			return $base_url . '?' . http_build_query( $new_query_params );
		}

		// Parse query string into array
		parse_str( $base_url_components['query'], $base_query_params );

		// Find the original query string in the URL and replace it with the new merged
		return str_replace(
			$base_url_components['query'],
			http_build_query(
				// merge order
				$new_overrules ?
				ArrayHelper::merge(
					$base_query_params,
					$new_query_params
				) :
				ArrayHelper::merge(
					$new_query_params,
					$base_query_params
				)
			),
			$base_url
		);
	}
}