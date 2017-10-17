<?php

namespace drsdre\radtools\tests\unit;

use Codeception\Specify;
use drsdre\radtools\helpers\Url;

class UrlTest extends \Codeception\Test\Unit {

	use Specify;

	public function testMergeQuerystring() {
		$base_url = 'https://testurl.com:999/directory1/directory2/page';

		$existing_url = $base_url . '?a=123&b=456&c=abc#hyperlink';

		$additional_query = [ 'b' => 'new_value', 'd' => 'new_value' ];

		expect( 'Merged URL with overrule should match', Url::urlQueryMerge( $existing_url, $additional_query, true ) )
			->equals( $base_url . '?a=123&b=new_value&c=abc&d=new_value#hyperlink' );

		expect( 'Merged URL no overrule should match', Url::urlQueryMerge( $existing_url, $additional_query, false ) )
			->equals( $base_url . '?b=456&d=new_value&a=123&c=abc#hyperlink' );
	}
}
