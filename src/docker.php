<?php
/**
 * docker-compose wrapper functions.
 */

namespace Tribe\Test;

require_once __DIR__ . '/utils.php';

/**
 * Returns the current Operating System family.
 *
 * @return string The human-readable name of the OS PHP is running on. One of `Linux`, `macOS`, `Windows`, `Solaris`,
 *                `BSD` or `Unknown`.
 */
function os() {
	$map = [
		'win' => 'Windows',
		'dar' => 'macOS',
		'lin' => 'Linux',
		'bsd' => 'BSD',
		'sol' => 'Solaris',
	];

	$key = strtolower( substr( PHP_OS, 0, 3 ) );

	return isset( $map[ $key ] ) ? $map[ $key ] : 'Unknown';
}

/**
 * Curried docker-compose wrapper.
 *
 * @param array<string> $options A list of options to initialize the wrapper.
 *
 * @return \Closure A closure to actually call docker-compose with more arguments.
 */
function docker_compose( array $options = [] ) {
	setup_id();

	$is_ci = is_ci();

	$host_ip = false;
	if ( ! $is_ci && 'Linux' === os() ) {
		$linux_overrides = stack( '-linux-override' );
		if ( file_exists( $linux_overrides ) ) {
			$options = array_merge( [ '-f', $linux_overrides ], $options );
		}
		// If we're running on Linux, then try to fetch the host machine IP using a command.
		$host_ip = host_ip( 'Linux' );
	}

	return static function ( array $command = [] ) use ( $options, $host_ip, $is_ci ) {
		$command = 'docker-compose ' . implode( ' ', $options ) . ' ' . implode( ' ', $command );

		if ( ! empty( $host_ip ) ) {
			// Set the host IP address on Linux machines.
			$xdebug_remote_host = (string) getenv( 'XDH' ) ?: host_ip();
			$command            = 'XDH=' . $xdebug_remote_host . ' ' . $command;
		}

		if ( ! empty( $is_ci ) ) {
			// Disable XDebug in CI context to speed up the builds.
			$command = 'XDE=0 XDEBUG_DISABLE=1 ' . $command;
		}

		return process( $command );
	};
}

/**
 * Returns the file path of the WordPress root directory in the WordPress container.
 *
 * @param string $path The path to append to the WordPress root directory path.
 *
 * @return string The absolute path to a directory or file in the WordPress container.
 */
function wordpress_container_root_dir( $path = '/' ) {
	return '/var/www/html/' . ltrim( $path, '\\/' );
}

/**
 * Sets up and returns a wp-cli pre-process, ready to run wp-cli commands in the stack.
 *
 * @return \Closure The wp-cli pre-process, ready to accept an array of commands to run, the `wp` command is not
 *                 required.
 */
function cli() {
	$service     = is_ci() ? 'cli' : 'cli_debug';
	$stack_array = tric_stack_array();

	return docker_compose( array_merge( $stack_array, [ 'run', $service, '--allow-root' ] ) );
}

/**
 * Returns the URL at which the `wordpress` service will be reachable on localhost.
 *
 * Depending on whether the current context is a CI one or not, the URL will vary.
 *
 * @return string The URL at which the `wordpress` service can be reached.
 */
function wordpress_url() {
	if ( is_ci() ) {
		return 'http://tribe.test';
	}

	$config = check_status_or_exit( docker_compose( tric_stack_array() )( [ 'config' ] ) )( 'string_output' );

	preg_match( '/wordpress_debug:.*?ports:.*?(?<port>\\d+):80\\/tcp/us', $config, $m );

	if ( ! isset( $m['port'] ) ) {
		echo "\n<red>Could not read the 'wordpress_debug' service localhost port from the stack " .
		     "configuration:\n" . $config;
		exit( 1 );
	}

	return 'http://localhost:' . (int) $m['port'];
}

/**
 * Returns the stack to run depending on the current run context.
 *
 * @param string $postfix      A postfix to use for the stack file, it will be inserted between the file base name and
 *                             the `.yml` file extension.
 *
 * @return string The path to the docker-compose stack file to run, depending on the run context.
 */
function stack( $postfix = '' ) {
	$root_dir     = dirname( __DIR__ );
	$test_dir    = $root_dir . '/test';
	$run_context = run_context();
	switch ( $run_context ) {
		case 'tric';
			$stack = $root_dir . '/tric-stack' . $postfix . '.yml';
			break;
		default:
		case 'default':
		case 'ci':
			$stack = $test_dir . '/activation-stack' . $postfix . '.yml';
			break;
	}

	return $stack;
}

/**
 * Builds a collection of docker-compose yaml files for spinning up a stack.
 *
 * Typically, this would be tric-stack.yml for plugin-only setups, but if running in site mode, it adds tric-stack.site.yml.
 *
 * @return string[] Array of docker-compose arguments indicating the files that should be used to initialize the stack.
 */
function tric_stack_array() {
	$base_stack  = stack();
	$stack_array = [ '-f', '"' . $base_stack . '"' ];

	if ( tric_here_is_site() ) {
		$stack_array[] = '-f';
		$stack_array[] = '"' . stack( '.site' ) . '"';
	}

	return $stack_array;
}

/**
 * Executes a docker-compose command in real time, printing the output as produced by the command.
 *
 * @param array<string> $options A list of options to initialize the wrapper.
 * @param bool $is_realtime Whether the command should be run in real time (true) or passively (false).
 *
 * @return \Closure A closure that will run the process in real time and return the process exit status.
 */
function docker_compose_process( array $options = [], $is_realtime = true ) {
	setup_id();

	$is_ci = is_ci();

	$host_ip = false;
	if ( ! $is_ci && 'Linux' === os() ) {
		$linux_override = stack( '-linux-override' );
		if ( file_exists( $linux_override ) ) {
			$options = array_merge( [ '-f', $linux_override ], $options );
		}
		// If we're running on Linux, then try to fetch the host machine IP using a command.
		$host_ip = host_ip( 'Linux' );
	}

	return static function ( array $command = [], $prefix = null ) use ( $options, $host_ip, $is_ci, $is_realtime ) {
		$command = 'docker-compose ' . implode( ' ', $options ) . ' ' . implode( ' ', $command );

		if ( ! empty( $host_ip ) ) {
			// Set the host IP address on Linux machines.
			$xdebug_remote_host = (string) getenv( 'XDH' ) ?: host_ip();
			$command            = 'XDH=' . $xdebug_remote_host . ' ' . $command;
		}

		if ( ! empty( $is_ci ) ) {
			// Disable XDebug in CI context to speed up the builds.
			$command = 'XDE=0 ' . $command;
		}

		return $is_realtime ? process_realtime( $command ) : process_passive( $command, $prefix );
	};
}

/**
 * Executes a docker-compose command in passive mode, printing the output as produced by the command.
 *
 * This approach is used for commands that can be run in a parallel or forked process without interactivity.
 *
 * @param array<string> $options A list of options to initialize the wrapper.
 *
 * @return \Closure A closure that will run the process in real time and return the process exit status.
 */
function docker_compose_passive( array $options = [] ) {
	return docker_compose_process( $options, false );
}

/**
 * Executes a docker-compose command in real time, printing the output as produced by the command.
 *
 * @param array<string> $options A list of options to initialize the wrapper.
 *
 * @return \Closure A closure that will run the process in real time and return the process exit status.
 */
function docker_compose_realtime( array $options = [] ) {
	return docker_compose_process( $options, true );
}
