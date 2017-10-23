<?php
/**
 * DO NOT EDIT THIS FILE
 * 
 * Define the path to your wp-tests-config.php using the WP_PHPUNIT__TESTS_CONFIG environment variable.
 */

/**
 * Load the real wp-tests-config.php, wherever it may be.
 */
if ( file_exists( getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) ) {
    require getenv( 'WP_PHPUNIT__TESTS_CONFIG' );
}

/**
 * This must be defined as a local variable here as expected by the library's bootstrap.php.
 * @var string
 */
if ( ! isset( $table_prefix ) || getenv( 'WP_PHPUNIT__TABLE_PREFIX' ) ) {
    $table_prefix = getenv( 'WP_PHPUNIT__TABLE_PREFIX' ) ? getenv( 'WP_PHPUNIT__TABLE_PREFIX' ) : 'wptests_';
}
