<?php
/**
 * Handles a request to execute a command in a tric service container.
 *
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var string   $cli_name The current name of the CLI.
 * @var \Closure $args     The argument map closure, as produced by the `args` function.
 */

namespace Tribe\Test;

if ( $is_help ) {
	echo "Executes a command in a service container.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} exec [...<commands>]</light_cyan>\n" );
	echo colorize( "signature: <light_cyan>{$cli_name} exec wordpress ls /var/www/html</light_cyan>\n" );

	return;
}

setup_id();
$sub_args = args( [ 'service', '...' ], $args( '...' ), 0 );
$service  = $sub_args( 'service', false );

if ( empty( $service ) ) {
	echo magenta( "You have to specify a service; e.g. 'wordpress'.\n" );
	exit( 1 );
}

$command = $sub_args( '...', [] );

if ( empty( $command ) ) {
	echo magenta( "You have to specify a command for the service.\n" );
	exit( 1 );
}

$process = tric_process()( array_merge( [ 'exec', $service ], $command ) );
echo $process( 'string_output' );

exit( $process( 'status' ) );
