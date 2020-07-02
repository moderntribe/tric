<?php
/**
 * Handles the `run` command to `codecept run` a command in the target.
 *
 * Codeception allows specifying an additional configuration file to load with the `-c`, or `--config` option.
 * Since the `codeception.dist.yml` and `codeception.yml` files will always be loaded if they exist, here we set up
 * the command to load the tric configuration file (`coeception.tric.yml`) if it exists.
 * Note: we cannot use `-o "params: .env.testing.tric"` to load tric env file as the `params` key is not overrideable
 * if not using a file; so we scaffold a tric-specific Codeception configuration file to get around it in the `init`
 * command.
 * See the `init` command for more details.
 *
 * @var bool     $is_help Whether we're handling an `help` request on this command or not.
 * @var \Closure $args    The argument map closure, as produced by the `args` function.
 */

namespace Tribe\Test;

if ( $is_help ) {
	echo colorize( "Runs a Codeception test in the stack, the equivalent of <light_cyan>'codecept run ...'</light_cyan>.\n" );
	echo PHP_EOL;
	echo colorize( "This command requires a  set using the <light_cyan>use</light_cyan> command.\n" );
	echo colorize( "usage: <light_cyan>{$cli_name} run [...<commands>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} run wpunit</light_cyan>" );

	return;
}

$using = tric_target();
echo light_cyan( "Using {$using}\n" );

setup_id();

maybe_generate_htaccess();

// Run the command in the Codeception container.
$root = tric_plugins_dir( tric_target( true ) );

// Object-cache is disruptive in the context of tests; remove the object cache drop-in before running the tests.
$object_cache_dropin = tric_wp_dir( 'wp-content/object-cache.php' );
if ( file_exists( $object_cache_dropin ) ) {
	echo "Removing the object cache drop-in file before tests...\n";
	if ( ! unlink( $object_cache_dropin ) ) {
		echo magenta( "Failed to remove the {$object_cache_dropin} file.\n" );
		exit( 1 );
	}
	echo "Object cache drop-in file removed.\n";
}

/*
 * Check what configuration files we've got available.
 * Depending on the what we have apply them in this order: dist, local, tric.
 * Codeception will apply them in cascading style.
 */
$available_configs_mask = array_sum( [
	file_exists( $root . '/codeception.dist.yml' ),
	2 * file_exists( $root . '/codeception.yml' ),
	4 * file_exists( $root . '/codeception.tric.yml' ),
] );

$config_files = [];

switch ( $available_configs_mask ) {
	case 0:
		// There is no configuration file; needs configuration.
		echo magenta( "No Codeception configuration file found: run the 'init' subcommand to initialize the project.\n" );
		exit( 1 );
	case 1:
		// Dist config file only; this will work if `tric` is the dist tool used locally and in CI, CC will pick it up.
	case 2:
		// Local config file only; this will work, CC will pick it up.
	case 3:
		// Dist and local config file only; this will work, CC will pick it up.
		break;
	case 4:
		// Only `tric` configuration file, CC needs to know it should use it.
	case 5:
		// Dist and `tric` configuration file, CC needs to know it should add the tric one at the end.
	case 6:
		// Local and `tric` configuration file, CC needs to know it should add the tric one at the end.
	case 7:
		// Dist, local and `tric` configuration file, CC needs to know it should add the tric one at the end.
		$config_files = [ '-c codeception.tric.yml' ];
		break;
}
// Add tric configuration file, if existing.
$run_configuration = array_merge( [ 'run', '--rm', 'codeception', 'run' ], $config_files );

fix_container_dir_file_modes( 'wordpress', '/var/www/html/wp-content', 'a+rwx' );

// Finally run the command.
$status     = tric_realtime()( array_merge( $run_configuration, $args( '...' ) ) );
$has_failed = file_exists( $root . '/tests/_output/failed' );

if ( $status || $has_failed ) {
	exit( 1 );
}

exit( $status );
