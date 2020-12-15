<?php
/**
 * Handles the `dc` command to execute a docker-compose command in the tric stack.
 *
 * @var string   $cli_name The current name of the CLI binary.
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var \Closure $args     The argument map closure, as produced by the `args` function.
 */

use function Tribe\Test\colorize;
use function Tribe\Test\tric_realtime;

if ( $is_help ) {
	echo "Activates and deactivates object cache support, returns the current object cache status.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} dc [...<command|arg>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} dc logs</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} dc ps</light_cyan>\n" );

	return;
}

$status = tric_realtime()( $args( '...' ) );

exit( $status );
