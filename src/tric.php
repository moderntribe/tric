<?php
/**
 * tric cli functions.
 */

namespace Tribe\Test;

/**
 * Returns whether or not the tric here command was done at the site level or not.
 *
 * @return bool
 */
function tric_here_is_site() {
	return TRIC_ROOT_DIR . '/_wordpress' !== getenv( 'TRIC_WP_DIR' )
		&& './_wordpress' !== getenv( 'TRIC_WP_DIR' );
}

/**
 * Get the current directory name without any slashes or path.
 *
 * @return string Name of the current working directory. Empty string if not a readable directory or other error.
 */
function get_cwd_dir_name() {
	$cwd = getcwd();

	if (
		is_string( $cwd )
		&& is_dir( $cwd )
	) {
		return basename( $cwd );
	}

	return '';
}

/**
 * Gets all valid targets.
 *
 * Valid targets are:
 *   - Anything in the plugins directory.
 *   - If tric here was done on the site level, "site" is also a valid target.
 *
 * @param bool $as_array Whether to output as an array. If falsy, will output as a formatted string, including
 *                       headings, line breaks, and indentation.
 *
 * @return array|string
 */
function get_valid_targets( $as_array = true ) {
	$targets_str = '';

	$plugins = array_keys( dev_plugins() );
	sort( $plugins, SORT_NATURAL );

	$themes = array_keys( dev_themes() );
	sort( $themes, SORT_NATURAL );

	$targets = $plugins;

	if ( tric_here_is_site() ) {
		$targets     = array_merge( [ 'site' ], $plugins, $themes );
		$targets_str .= PHP_EOL . '  Site:' . PHP_EOL;
		$targets_str .= '    - site';
	}

	$targets_str .= PHP_EOL . "  Plugins:" . PHP_EOL;
	$targets_str .= implode(
		PHP_EOL, array_map(
			static function ( $target ) {
				return "    - {$target}";
			}, $plugins
		)
	);

	if ( tric_here_is_site() && $themes ) {
		$targets_str .= PHP_EOL . "  Themes:" . PHP_EOL;
		$targets_str .= implode(
			PHP_EOL, array_map(
				static function ( $target ) {
					return "    - {$target}";
				}, $themes
			)
		);
	}

	if ( empty( $as_array ) ) {
		return $targets_str;
	}

	return $targets;
}

/**
 * Checks a specified target is supported as a target.
 *
 * Valid targets are:
 *   - Anything in the plugins directory.
 *   - If tric here was done on the site level, "site" is also a valid target.
 *
 * @param string $target The target to check in the valid list of targets.
 * @param bool   $exit   Whether to exit if the target is invalid, or to return `false`.
 *
 * @return string|false $target The validated target or `false` to indicate the target is not valid if the `$exit`
 *                              parameter is set to `false`.
 */
function ensure_valid_target( $target, $exit = true ) {
	$targets = get_valid_targets();

	$targets_str = get_valid_targets( false );

	if ( empty( $target ) ) {
		$target = get_cwd_dir_name();

		if ( ! in_array( $target, $targets, true ) ) {
			echo magenta( "Detecting the current directory of '{$target}' as the target was not valid.\nAvailable targets are:\n${targets_str}\n" );
			if ( $exit ) {
				exit( 1 );
			}

			return false;
		}
	}

	if ( ! in_array( $target, $targets, true ) ) {
		echo magenta( "'{$target}' is not a valid target; available targets are:\n${targets_str}\n" );
		if ( $exit ) {
			exit( 1 );
		}

		return false;
	}

	return $target;
}

/**
 * Get the container relative path to the provided target.
 *
 * @param string $target Target with which to build the relative path from.
 *
 * @return string
 */
function get_target_relative_path( $target ) {
	if ( 'site' === $target ) {
		return '';
	}

	$plugin_dir = getenv( 'TRIC_PLUGINS_DIR' );
	$theme_dir  = getenv( 'TRIC_THEMES_DIR' );

	if ( file_exists( "{$plugin_dir}/{$target}" ) ) {
		$parent_path = $plugin_dir;
	} elseif ( file_exists( "{$theme_dir}/{$target}" ) ) {
		$parent_path = $theme_dir;
	} else {
		echo magenta( "Unable to locate a path to the desired target ({$target}). Searched in: \n- {$plugin_dir}\n- {$theme_dir}" );
		exit( 1 );
	}

	$parent_path = str_replace( getenv( 'TRIC_HERE_DIR' ) . '/', '', $parent_path );

	return "{$parent_path}/{$target}";
}

/**
 * Sets up the environment from the cli tool.
 *
 * @param string $root_dir The cli tool root directory.
 */
function setup_tric_env( $root_dir ) {
	// Let's declare we're performing trics.
	putenv( 'TRIBE_TRIC=1' );

	$os = os();
	if ( $os === 'macOS' || $os === 'Windows' ) {
		// Do not fix file modes on hosts that implement user ID and group ID remapping at the Docker daemon level.
		putenv( 'FIXUID=0' );
	}

	// Load the distribution version configuration file, the version-controlled one.
	load_env_file( $root_dir . '/.env.tric' );

	// Load the local overrides, this file is not version controlled.
	if ( file_exists( $root_dir . '/.env.tric.local' ) ) {
		load_env_file( $root_dir . '/.env.tric.local' );
	}

	// Load the current session configuration file.
	if ( file_exists( $root_dir . '/.env.tric.run' ) ) {
		load_env_file( $root_dir . '/.env.tric.run' );
	}

	$wp_dir = getenv( 'TRIC_WP_DIR' );
	if ( empty( $wp_dir ) ) {
		$wp_dir = root( '_wordpress' );
	} elseif ( ! is_dir( $wp_dir ) ) {
		$wp_dir_path = trim( root( ltrim( $wp_dir, './' ) ) );

		if (
			is_dir( dirname( $wp_dir_path ) )
			&& ! is_dir( $wp_dir_path )
			&& ! mkdir( $wp_dir_path )
			&& ! is_dir( $wp_dir_path )
		) {
			// If the WordPress directory does not exist, then create it now.
			echo magenta( "Cannot create the {$wp_dir_path} directory" );
			exit( 1 );
		}

		$wp_dir = realpath( $wp_dir_path );
	}

	// Whatever the case, the WordPress directory should now exist.
	if ( ! is_dir( $wp_dir ) ) {
		echo magenta( "The WordPress directory ({$wp_dir}) does not exist." );
		exit( 1 );
	}

	maybe_generate_htaccess();

	$plugins_dir = getenv( 'TRIC_PLUGINS_DIR' );
	if ( empty( $plugins_dir ) ) {
		$plugins_dir = root( '_plugins' );
	} elseif ( ! is_dir( $plugins_dir ) ) {
		$plugin_dir_path = root( ltrim( $plugins_dir, './' ) );

		if (
			is_dir( basename( $plugin_dir_path ) )
			&& ! is_dir( $plugin_dir_path )
			&& ! mkdir( $plugin_dir_path )
			&& ! is_dir( $plugin_dir_path )
		) {
			echo magenta( "Cannot create the {$plugin_dir_path} directory." );
			exit( 1 );
		}
		$plugins_dir = realpath( $plugin_dir_path );
	}

	putenv( 'TRIC_WP_DIR=' . $wp_dir );
	putenv( 'TRIC_PLUGINS_DIR=' . $plugins_dir );

	// Most commands are nested shells that should not run with a time limit.
	remove_time_limit();
}

/**
 * Returns the current `use` target.
 *
 * @param bool $require Whether to require a target, and fail if not set, or not.
 *
 * @return string|string Either the current target or `false` if the target is not set. If `$require` is `true` then the
 *                       return value will always be a non empty string.
 */
function tric_target( $require = true ) {
	$using        = getenv( 'TRIC_CURRENT_PROJECT' );
	$using_subdir = getenv( 'TRIC_CURRENT_PROJECT_SUBDIR' );
	$using_full   = $using . ( $using_subdir ? '/' . $using_subdir : '' );

	if ( $require ) {
		return $using_full;
	}

	if ( empty( $using_full ) ) {
		echo magenta( "Use target not set; use the 'use' sub-command to set it.\n" );
		exit( 1 );
	}

	return trim( $using_full );
}

/**
 * Switches the current `use` target.
 *
 * @param string $target Target to switch to.
 */
function tric_switch_target( $target ) {
	$root                 = root();
	$run_settings_file    = "{$root}/.env.tric.run";
	$target_relative_path = '';
	$subdir               = '';

	if ( tric_here_is_site() ) {
		$target_relative_path = get_target_relative_path( $target );
	}

	if ( false !== strpos( $target, '/' ) ) {
		list( $target, $subdir ) = explode( '/', $target );
	}

	$env_values = [
		'TRIC_CURRENT_PROJECT'               => $target,
		'TRIC_CURRENT_PROJECT_RELATIVE_PATH' => $target_relative_path,
		'TRIC_CURRENT_PROJECT_SUBDIR'        => $subdir,
	];

	write_env_file( $run_settings_file, $env_values, true );

	setup_tric_env( $root );
}

/**
 * Returns a map of the stack PHP services that relates the service to its pretty name.
 *
 * @return array<string,string> A map of the stack PHP services relating each service to its pretty name.
 */
function php_services() {
	return [
		'wordpress'   => 'WordPress',
		'codeception' => 'Codeception',
	];
}

/**
 * Restart the stack PHP services.
 *
 * @param bool $hard Whether to restart the PHP services using the `docker-compose restart` command or by using a
 *                   tear-down and up again cycle.
 */
function restart_php_services( $hard = false ) {
	foreach ( php_services() as $service => $pretty_name ) {
		restart_service( $service, $pretty_name, $hard );
	}
}

/**
 * Restarts a stack services if it's running.
 *
 * @param string      $service     The name of the service to restart, e.g. `wordpress`.
 * @param string|null $pretty_name The pretty name to use for the service, or `null` to use the service name.
 * @param bool $hard Whether to restart the service using the `docker-compose restart` command or to use full tear-down
 *                   and up again cycle.
 */
function restart_service( $service, $pretty_name = null, $hard = false ) {
	$pretty_name   = $pretty_name ?: $service;
	$tric          = docker_compose( tric_stack_array() );
	$tric_realtime = docker_compose_realtime( tric_stack_array() );

	$service_running = $tric( [ 'ps', '-q', $service ] )( 'string_output' );
	if ( ! empty( $service_running ) ) {
		echo colorize( "Restarting {$pretty_name} service...\n" );
		if ( $hard ) {
			$tric_realtime( [ 'rm', '--stop', '--force', $service ] );
			$tric_realtime( [ 'up', '-d', $service ] );
		} else {
			$tric_realtime( [ 'restart', $service ] );
		}
		echo colorize( "<light_cyan>{$pretty_name} service restarted.</light_cyan>\n" );
	} else {
		echo colorize( "{$pretty_name} service was not running.\n" );
	}
}

/**
 * Returns the absolute path to the current plugins directory tric is using.
 *
 * @param string $path An optional path to append to the current tric plugin directory.
 *
 * @return string The absolute path to the current plugins directory tric is using.
 *
 */
function tric_plugins_dir( $path = '' ) {
	return tric_content_type_dir( 'plugins', $path );
}

/**
 * Returns the absolute path to the current plugins directory tric is using.
 *
 * @param string $path An optional path to append to the current tric plugin directory.
 *
 * @return string The absolute path to the current plugins directory tric is using.
 *
 */
function tric_themes_dir( $path = '' ) {
	return tric_content_type_dir( 'themes', $path );
}

/**
 * Returns the absolute path to the current content directory tric is using.
 *
 * @param string $path An optional path to append to the current tric content directory.
 *
 * @return string The absolute path to the current content directory tric is using.
 *
 */
function tric_content_type_dir( $content_type = 'plugins', $path = '' ) {
	$content_type_dir = getenv( 'TRIC_' . strtoupper( $content_type ) . '_DIR' );
	$root_dir         = root();

	if ( 'plugins' === $content_type ) {
		$default_path = '/_plugins';
	} elseif ( 'themes' === $content_type ) {
		$default_path = '/_wordpress/wp-content/themes';
	}

	if ( empty( $content_type_dir ) ) {
		// Use the default directory in tric repository.
		$dir = $root_dir . $default_path;
	} elseif ( is_dir( $content_type_dir ) ) {
		// Use the specified directory.
		$dir = $content_type_dir;
	} else {
		if ( 0 === strpos( $content_type_dir, '.' ) ) {
			// Resolve the './...' paths a relative to the root directory in tric repository.
			$dir = preg_replace( '/^\\./', $root_dir, $content_type_dir );
		} else {
			// Use a directory relative to the root directory in tric reopository.
			$dir = $root_dir . '/' . ltrim( $content_type_dir, '\\/' );
		}
	}

	return empty( $path ) ? $dir : $dir . '/' . ltrim( $path, '\\/' );
}

/**
 * Clones a company plugin in the current plugin root directory.
 *
 * @param string $plugin The plugin name, e.g. `the-events-calendar` or `event-tickets`.
 * @param string $branch The specific branch to clone. If not specified, then the default plugin repository branch
 *                       will be cloned.
 */
function clone_plugin( $plugin, $branch = null ) {
	$plugin_dir  = tric_plugins_dir();
	$plugin_path = tric_plugins_dir( $plugin );

	if ( ! file_exists( $plugin_dir ) ) {
		echo "Creating the plugins directory...\n";
		if ( ! mkdir( $plugin_dir ) && ! is_dir( $plugin_dir ) ) {
			echo magenta( "Could not create {$plugin_dir} directory; please check the parent directory is writeable." );
			exit( 1 );
		}
	}

	// If the plugin path already exists, don't bother cloning.
	if ( file_exists( $plugin_path ) ) {
		return;
	}

	echo "Cloning {$plugin}...\n";

	$repository = git_handle() . '/' . escapeshellcmd( $plugin );

	$clone_command = sprintf(
		'git clone %s --recursive git@%s:%s.git %s',
		null !== $branch ? '-b "' . $branch . '"' : '',
		git_domain(),
		$repository,
		escapeshellcmd( $plugin_path )
	);

	$clone_status = process_realtime( $clone_command );

	if ( 0 !== $clone_status ) {
		echo magenta( "Could not clone the {$repository} repository; please check your access rights to the repository." );
		exit( 1 );
	}
}

/**
 * Sets up the files required to run tests in the plugin using tric stack.
 *
 * @param string $plugin The plugin name, e.g. 'the-events-calendar` or `event-tickets`.
 */
function setup_plugin_tests( $plugin ) {
	$plugin_path    = tric_plugins_dir() . '/' . $plugin;
	$relative_paths = [ '' ];

	if ( file_exists( "{$plugin_path}/common" ) ) {
		$relative_paths[] = 'common';
	}

	foreach ( $relative_paths as $relative_path ) {
		$target_path   = "{$plugin_path}/{$relative_path}";
		$relative_path = empty( $relative_path ) ? '' : "{$relative_path}/";

		if ( write_tric_test_config( $target_path ) ) {
			echo colorize( "Created/updated <light_cyan>{$relative_path}test-config.tric.php</light_cyan> " .
			               "in {$plugin}.\n" );
		}

		write_tric_env_file( $target_path );
		echo colorize( "Created/updated <light_cyan>{$relative_path}.env.testing.tric</light_cyan> " .
		               "in <light_cyan>{$plugin}</light_cyan>.\n" );


		write_codeception_config( $target_path );
		echo colorize( "Created/updated <light_cyan>{$relative_path}codeception.tric.yml</light_cyan> in " .
		               "<light_cyan>{$plugin}</light_cyan>.\n" );
	}
}

/**
 * Returns the git domain from which to clone git plugins.
 *
 * Configured using the `TRIC_GIT_DOMAIN` env variable.
 * Examples: gitlab.com, bitbucket.org, git.example.com
 *
 * @return string The git domain from which to clone plugins.
 */
function git_domain() {
	$domain = getenv( 'TRIC_GIT_DOMAIN' );

	return ! empty( $domain ) ? trim( $domain ) : 'github.com';
}

/**
 * Returns the handle (username) of the company from which to clone git plugins.
 *
 * Configured using the `TRIC_GIT_HANDLE` env variable.
 *
 * @return string The git handle from which to clone plugins.
 */
function git_handle() {
	$handle = getenv( 'TRIC_GIT_HANDLE' );

	return ! empty( $handle ) ? trim( $handle ) : 'the-events-calendar';
}

/**
 * Runs a process in passive mode in tric stack and returns the exit status.
 *
 * This approach is used when running commands that can be done in parallel or forked processes.
 *
 * @return \Closure The process closure to start a real-time process using tric stack.
 */
function tric_passive() {
	return docker_compose_passive( tric_stack_array() );
}

/**
 * Runs a process in tric stack and returns the exit status.
 *
 * @return \Closure The process closure to start a real-time process using tric stack.
 */
function tric_realtime() {
	return docker_compose_realtime( tric_stack_array() );
}

/**
 * Returns the process Closure to start a real-time process using tric stack.
 *
 * @return \Closure The process closure to start a real-time process using tric stack.
 */
function tric_process() {
	return docker_compose( tric_stack_array() );
}

/**
 * Tears down tric stack.
 */
function teardown_stack() {
	tric_realtime()( [ 'down', '--volumes', '--remove-orphans' ] );
}

/**
 * Rebuilds the tric stack.
 */
function rebuild_stack() {
	echo "Building the stack images...\n\n";

	if ( is_ci() ) {
		// In CI context do NOT build the image with XDebug and waste time on unused features.
		putenv( 'TRIC_WORDPRESS_DOCKERFILE=Dockerfile.base' );
	}

	tric_realtime()( [ 'build' ] );
	write_build_version();
	echo light_cyan( "\nStack images built.\n\n" );
}

/**
 * Write the current CLI_VERSION to the build-version file
 */
function write_build_version() {
	file_put_contents( TRIC_ROOT_DIR . '/.build-version', CLI_VERSION );
}

/**
 * Prints information about tric tool.
 */
function tric_info() {
	$config_vars = [
		'TRIC_TEST_SUBNET',
		'CLI_VERBOSITY',
		'CI',
		'TRAVIS_CI',
		'COMPOSER_CACHE_DIR',
		'COMPOSER_CACHE_HOST_DIR',
		'CONTINUOUS_INTEGRATION',
		'GITHUB_ACTION',
		'TRIC_CURRENT_PROJECT',
		'TRIC_CURRENT_PROJECT_RELATIVE_PATH',
		'TRIC_CURRENT_PROJECT_SUBDIR',
		'TRIC_HOST',
		'TRIC_PLUGINS',
		'TRIC_THEMES',
		'TRIC_GIT_DOMAIN',
		'TRIC_GIT_HANDLE',
		'TRIC_HERE_DIR',
		'TRIC_PLUGINS_DIR',
		'TRIC_THEMES_DIR',
		'TRIC_WP_DIR',
		'TRIC_INTERACTIVE',
		'TRIC_BUILD_PROMPT',
		'TRIC_BUILD_SUBDIR',
		'TERM',
		'XDK',
		'XDE',
		'XDH',
		'XDP',
		'UID',
		'DOCKER_RUN_UID',
		'GID',
		'DOCKER_RUN_GID',
		'MYSQL_ROOT_PASSWORD',
		'WORDPRESS_HTTP_PORT',
		'SSH_AUTH_SOCK',
	];

	echo colorize( "<yellow>Configuration read from the following files:</yellow>\n" );
	$tric_root = root();
	echo implode( "\n", array_filter( [
			file_exists( $tric_root . '/.env.tric' ) ? "  - " . $tric_root . '/.env.tric' : null,
			file_exists( $tric_root . '/.env.tric.local' ) ? "  - " . $tric_root . '/.env.tric.local' : null,
			file_exists( $tric_root . '/.env.tric.run' ) ? "  - " . $tric_root . '/.env.tric.run' : null,
		] ) ) . "\n\n";

	echo colorize( "<yellow>Current configuration:</yellow>\n" );
	foreach ( $config_vars as $key ) {
		$value = print_r( getenv( $key ), true );

		if ( $key === 'TRIC_PLUGINS_DIR' && $value !== tric_plugins_dir() ) {
			// If the configuration is using a relative path, then expose the absolute path.
			$value .= ' => ' . tric_plugins_dir();
		}

		echo colorize( "  - <light_cyan>{$key}</light_cyan>: {$value}\n" );
	}

	echo "\n";
	echo colorize( "<yellow>Valid Targets:</yellow>" );
	echo get_valid_targets( false );
}

/**
 * Returns the absolute path to the WordPress Core directory currently used by tric.
 *
 * The function will not check for the directory existence as we might be using this function to get a path to create.
 *
 * @param string $path An optional, relative, path to append to the WordPress Core directory absolute path.
 *
 * @return string The absolute path to the WordPress Core directory currently used by tric.
 */
function tric_wp_dir( $path = '' ) {
	$default = root( '/_wordpress' );

	$wp_dir = getenv( 'TRIC_WP_DIR' );

	if ( ! empty( $wp_dir ) ) {
		if ( ! is_dir( $wp_dir ) ) {
			// Relative path, resolve from root.
			$wp_dir = root( ltrim( preg_replace( '^\\./', '', $wp_dir ), '\\/' ) );
		}
	} else {
		$wp_dir = $default;
	}

	return empty( $path ) ? $wp_dir : $wp_dir . '/' . ltrim( $path, '\\/' );
}

/**
 * Prints the current composer-cache status to screen.
 */
function composer_cache_status() {
	$host_dir = getenv( 'COMPOSER_CACHE_HOST_DIR' );

	echo 'Composer cache directory: ' . ( $host_dir ? light_cyan( $host_dir ) : magenta( 'not set' ) ) . PHP_EOL;
}

/**
 * Handles the composer-cache command request.
 *
 * @param callable $args The closure that will produce the current interactive request arguments.
 */
function tric_handle_composer_cache( callable $args ) {
	$run_settings_file = root( '/.env.tric.run' );
	$toggle            = $args( 'toggle', 'status' );

	if ( 'status' === $toggle ) {
		composer_cache_status();

		return;
	}

	$value = $args( 'value', null );
	$docker_composer_cache_dir = '/host-composer-cache';

	if ( 'unset' === $toggle ) {
		$value = '/tmp';
		$docker_composer_cache_dir = null;

		write_env_file( $run_settings_file, [ 'COMPOSER_CACHE_HOST_DIR' => $value ], true );
		write_env_file( $run_settings_file, [ 'COMPOSER_CACHE_DIR' => $docker_composer_cache_dir ], true );
	}

	echo 'Composer cache directory: ' . ( $value ? light_cyan( $value ) : magenta( 'not set' ) );

	if ( $value === getenv( 'COMPOSER_CACHE_HOST_DIR' ) ) {
		return;
	}

	write_env_file( $run_settings_file, [ 'COMPOSER_CACHE_HOST_DIR' => $value ], true );
	write_env_file( $run_settings_file, [ 'COMPOSER_CACHE_DIR' => $docker_composer_cache_dir ], true );

	echo "\n\n";

	$restart_services = ask(
		'Would you like to restart the WordPress (NOT the database) and Codeception services now?',
		'yes'
	);
	if ( $restart_services ) {
		putenv( "COMPOSER_CACHE_HOST_DIR={$value}" );
		putenv( "COMPOSER_CACHE_DIR={$value}" );

		// Call for a hard restart to make sure the web-server will restart its php-fpm connection.
		restart_php_services( true );
	} else {
		echo colorize(
			"\n\nTear down the stack with <light_cyan>down</light_cyan> and restart it to apply the new settings!\n"
		);
	}
}

/**
 * Prints the current build-prompt status to screen.
 */
function build_prompt_status() {
	$enabled = getenv( 'TRIC_BUILD_PROMPT' );

	echo 'Interactive status is: ' . ( $enabled ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
}

/**
 * Handles the build-prompt command request.
 *
 * @param callable $args The closure that will produce the current interactive request arguments.
 */
function tric_handle_build_prompt( callable $args ) {
	$run_settings_file = root( '/.env.tric.run' );
	$toggle            = $args( 'toggle', 'on' );

	if ( 'status' === $toggle ) {
		build_prompt_status();

		return;
	}

	$value = 'on' === $toggle ? 1 : 0;
	echo 'Build Prompt status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );

	if ( $value === (int) getenv( 'TRIC_BUILD_PROMPT' ) ) {
		return;
	}

	write_env_file( $run_settings_file, [ 'TRIC_BUILD_PROMPT' => $value ], true );
}

/**
 * Prints the current interactive status to screen.
 */
function interactive_status() {
	$enabled = getenv( 'TRIC_INTERACTIVE' );

	echo 'Interactive status is: ' . ( $enabled ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
}

/**
 * Handles the interactive command request.
 *
 * @param callable $args The closure that will produce the current interactive request arguments.
 */
function tric_handle_interactive( callable $args ) {
	$run_settings_file = root( '/.env.tric.run' );
	$toggle            = $args( 'toggle', 'on' );

	if ( 'status' === $toggle ) {
		interactive_status();

		return;
	}

	$value = 'on' === $toggle ? 1 : 0;
	echo 'Interactive status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );

	if ( $value === (int) getenv( 'TRIC_INTERACTIVE' ) ) {
		return;
	}

	write_env_file( $run_settings_file, [ 'TRIC_INTERACTIVE' => $value ], true );
}

/**
 * Prints the current XDebug status to screen.
 */
function xdebug_status() {
	$enabled = getenv( 'XDE' );
	$ide_key = getenv( 'XDK' );
	if ( empty( $ide_key ) ) {
		$ide_key = 'tric';
	}
	$localhost_port = getenv( 'WORDPRESS_HTTP_PORT' );
	if ( empty( $localhost_port ) ) {
		$localhost_port = '8888';
	}

	echo 'XDebug status is: ' . ( $enabled ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
	echo 'Remote host: ' . light_cyan( getenv( 'XDH' ) ) . PHP_EOL;
	echo 'Remote port: ' . light_cyan( getenv( 'XDP' ) ) . PHP_EOL;

	echo 'IDE Key: ' . light_cyan( $ide_key ) . PHP_EOL;
	echo colorize( PHP_EOL . "You can override these values in the <light_cyan>.env.tric.local" .
			"</light_cyan> file or by using the " .
			"<light_cyan>'xdebug (host|key|port) <value>'</light_cyan> command." ) . PHP_EOL;


	echo PHP_EOL . 'Set up, in your IDE, a server with the following parameters to debug PHP requests:' . PHP_EOL;
	echo 'IDE key, or server name: ' . light_cyan( $ide_key ) . PHP_EOL;
	echo 'Host: ' . light_cyan( 'http://localhost' . ( $localhost_port === '80' ? '' : ':' . $localhost_port ) ) . PHP_EOL;
	echo colorize( 'Path mapping (host => server): <light_cyan>'
			. tric_plugins_dir()
			. '</light_cyan> => <light_cyan>/var/www/html/wp-content/plugins</light_cyan>' ) . PHP_EOL;
	echo colorize( 'Path mapping (host => server): <light_cyan>'
		. tric_wp_dir()
		. '</light_cyan> => <light_cyan>/var/www/html</light_cyan>' );

	$default_mask = ( tric_wp_dir() === root( '/_wordpress' ) ) + 2 * ( tric_plugins_dir() === root( '/_plugins' ) );

	switch ( $default_mask ) {
		case 1:
			echo PHP_EOL . PHP_EOL;
			echo yellow( 'Note: tric is using the default WordPress directory and a different plugins directory: ' .
				'set path mappings correctly and keep that in mind.' );
			break;
		case 2:
			echo PHP_EOL . PHP_EOL;
			echo yellow( 'Note: tric is using the default plugins directory and a different WordPress directory: ' .
				'set path mappings correctly and keep that in mind.' );
			break;
		case 3;
		default:
			break;
	}
}

/**
 * Handles the XDebug command request.
 *
 * @param callable $args The closure that will produce the current XDebug request arguments.
 */
function tric_handle_xdebug( callable $args ) {
	$run_settings_file = root( '/.env.tric.run' );
	$toggle            = $args( 'toggle', 'on' );

	if ( 'status' === $toggle ) {
		xdebug_status();

		return;
	}

	$map = [
		'host' => 'XDH',
		'key'  => 'XDK',
		'port' => 'XDP',
	];
	if ( array_key_exists( $toggle, $map ) ) {
		$var = $args( 'value' );
		echo colorize( "Setting <light_cyan>{$map[$toggle]}={$var}</light_cyan>" ) . PHP_EOL . PHP_EOL;
		write_env_file( $run_settings_file, [ $map[ $toggle ] => $var ], true );
		echo PHP_EOL . PHP_EOL . colorize( "Tear down the stack with <light_cyan>down</light_cyan> and restar it to apply the new settings!\n" );

		return;
	}

	$value = 'on' === $toggle ? 1 : 0;
	echo 'XDebug status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );

	if ( $value === (int) getenv( 'XDE' ) ) {
		return;
	}

	$xdebug_env_vars = [ 'XDE' => $value, 'XDEBUG_DISABLE' => 1 === $value ? 0 : 1 ];
	write_env_file( $run_settings_file, $xdebug_env_vars, true );

	echo "\n\n";

	$restart_services = ask(
		'Would you like to restart the WordPress (NOT the database) and Codeception services now?',
		'yes'
	);
	if ( $restart_services ) {
		foreach ( $xdebug_env_vars as $key => $value ) {
			putenv( "{$key}={$value}" );
		}
		// Call for a hard restart to make sure the web-server will restart its php-fpm connection.
		restart_php_services( true );
	} else {
		echo colorize(
			"\n\nTear down the stack with <light_cyan>down</light_cyan> and restar it to apply the new settings!\n"
		);
	}
}

/**
 * Updates the stack images by pulling the latest version of each.
 */
function update_stack_images() {
	echo "Updating the stack images...\n\n";
	tric_realtime()( [ 'pull', '--include-deps' ] );
	echo light_cyan( "\n\nStack images updated.\n" );
}

/**
 * Check if a recognized command's required file exists in the specified directory.
 *
 * @param string $base_command Command name, such as 'composer' or 'npm'.
 * @param string $path         The directory path in which to look for relevantly-required files (e.g. 'package.json').
 *
 * @return bool True if the path is a directory and the command doesn't have a known file requirement or the expected
 *              file does exist. False if the path is not a directory or a recognized command didn't find the
 *              relevantly-required file.
 */
function dir_has_req_build_file( $base_command, $path ) {
	// Bail if doesn't exist or is not a directory.
	if ( ! is_dir( $path ) ) {
		return false;
	}

	if ( 'composer' === $base_command ) {
		$req_file = 'composer.json';
	} elseif ( 'npm' === $base_command ) {
		$req_file = 'package.json';
	}

	// We don't know if we should handle so assume we should.
	if ( empty( $req_file ) ) {
		return true;
	}

	return is_file( rtrim( $path, '\\/' ) . '/' . $req_file );
}

/**
 * Maybe run the install process (e.g. Composer, NPM) on a given target.
 *
 * @param string $base_command    Base command to run.
 * @param string $target          Target to potentially run composer install against.
 * @param array  $sub_directories Sub directories to prompt for additional execution.
 *
 * @return array Result of command execution.
 */
function maybe_build_install_command_pool( $base_command, $target, array $sub_directories = [] ) {
	// Only prompt if the target itself has has been identified as available to build. If any subs need to build, will auto-try.
	if ( dir_has_req_build_file( $base_command, tric_plugins_dir( $target ) ) ) {
		$run = ask(
			"\n" . yellow( $target . ':' ) . " Would you like to run the {$base_command} install processes for this plugin?",
			'yes'
		);
	}

	if ( empty( $run ) ) {
		// Do not run the command on sub-directories if not running on the target.
		return [];
	}

	$subdirs_to_build = array_reduce( $sub_directories, static function ( array $buffer, $sub_directory ) use (
		$base_command
	) {
		$subdir_path = target_absolute_path( $sub_directory );
		if ( dir_has_req_build_file( $base_command, $subdir_path ) ) {
			$buffer[] = $sub_directory;
		}

		return $buffer;
	}, [] );

	return count( $subdirs_to_build ) ? build_command_pool( $base_command, [ 'install' ], $sub_directories ) : [];
}

/**
 * Run a command using the appropriate service.
 *
 * If any subdirectories are provided and are available in the target, then the user will be prompted to run the same
 * command on those subdirectories.
 *
 * @param string        $base_command    The base service command to run, e.g. `npm`, `composer`, etc.
 * @param array<string> $command         The command to run, e.g. `['install','--save-dev']` in array format.
 * @param array<string> $sub_directories Sub directories to prompt for additional execution.
 * @param string        $using           An optional target to use in place of the specified one.
 *
 * @return array The built command pool.
 */
function build_command_pool( $base_command, array $command, array $sub_directories = [], $using = null ) {
	$using_alias = $using;
	$using       = $using ?: tric_target();
	$targets     = [];

	// If applicable, include target plugin before subdirectory plugins.
	if ( dir_has_req_build_file( $base_command, tric_plugins_dir( tric_target() ) ) ) {
		$targets[] = 'target';
	}

	// Prompt for execution within subdirectories, if enabled.
	if ( getenv( 'TRIC_BUILD_SUBDIR' ) ) {
		foreach ( $sub_directories as $dir ) {
			$sub_target = $using_alias ? "{$using_alias}/{$dir}" : "{$using}/{$dir}";

			$question = "\n" . yellow( $sub_target . ':' ) . " Would you like to run the {$base_command} command against {$sub_target}?";
			if (
				dir_has_req_build_file( $base_command, tric_plugins_dir( $sub_target ) )
				&& ask( $question, 'yes' )
			) {
				$targets[] = $dir;
			}
		}
	}

	// Build the command process.
	$command_process = static function( $target, $subnet = '' ) use ( $using, $using_alias, $base_command, $command, $sub_directories ) {
		$target_name = $using_alias ?: $target;
		$prefix      = "{$base_command}:" . light_cyan( $target_name );

		// Execute command as the parent.
		if ( 'target' !== $target ) {
			tric_switch_target( "{$using}/{$target}" );
			$sub_target_name = $using_alias ? "{$using_alias}/{$target}" : $target;
			$prefix          = "{$base_command}:" . yellow( $sub_target_name );
		}

		putenv( "TRIC_TEST_SUBNET={$subnet}" );

		$network_name = "tric{$subnet}";
		$status       = tric_passive()( array_merge( [ '-p', $network_name, 'run', '--rm', $base_command ], $command ), $prefix );

		if ( ! empty( $subnet ) ) {
			do {
				/*
				 * Some containers might take time to terminate after yielding control back to the Docker daemon (zombies).
				 * If we try to remote the network when zombie containers are attached to it, we'll get the following error:
				 * "error while removing network: network <network_name> id <id> has active endpoints".
				 * When this happens, the return status of the command will be a `1`.
				 * We iterate until the status is a `0`.
				 */
				$network_rm_status = (int) process( "docker network rm {$network_name}_tric {$network_name}_default" )( 'status' );
			} while ( $network_rm_status !== 0 );
		}

		if ( 'target' !== $target ) {
			tric_switch_target( $using );
		}

		exit( pcntl_exit( $status ) );
	};

	$pool = [];

	// Build the pool with a target/container/command-specific key.
	foreach ( $targets as $target ) {
		$clean_command = implode( ' ', $command );

		$pool["{$target}:{$base_command}:{$clean_command}"] = [
			'target'    => $target,
			'container' => $base_command,
			'command'   => $command,
			'process'   => $command_process,
		];
	}

	return $pool;
}

/**
 * Executes a pool of commands in parallel.
 *
 * @param array $pool Pool of processes to execute in parallel.
 *     $pool[] = [
 *       'target'    => (string) Tric target.
 *       'container' => (string) Container on which to execute the command.
 *       'command'   => (array) The command to run, e.g. `['install', '--save-dev']` in array format.
 *       'process'   => (closure) The function to execute for each Tric target.
 *     ]
 * @return int Result of combined command execution.
 */
function execute_command_pool( $pool ) {
	if ( ! $pool ) {
		return 0;
	}

	$using = tric_target();

	if ( count( $pool ) > 1 ) {
		$status = parallel_process( $pool );
		tric_switch_target( $using );
		return $status;
	}

	$pool_item = reset( $pool );

	return $pool_item['process']( $pool_item['target'] );
}

/**
 * Returns an array of arguments to correctly run a wp-cli command in the tric stack.
 *
 * @param array<string> $command The wp-cli command to run, anything after the `wp`; e.g. `[ 'plugin', 'list' ]`.
 * @param string        $service The wp-cli service to target; defaults to the `cli` one.
 *
 * @return array<string> The complete command arguments, ready to be used in the `tric` or `tric_realtime` functions.
 */
function cli_command( array $command = [], $service = 'cli' ) {
	$wp_command = array_merge( [ 'wp', '--allow-root' ], $command );
	// Put the full `wp` command in an env var to allow services that might use it, e.g. `site-cli`, to pick it up.
	putenv( 'TRIC_' . upper_snake_case( $service ) . '_COMMAND=' . implode( ' ', $wp_command ) );

	return array_merge( [ 'run', '--rm', $service ], $wp_command );
}

/**
 * Switches a plugin branch.
 *
 * The function will try to pull, and switch to, the branch from the plugin repository remotes if the branch is not
 * locally available.
 * If the branch is locally available, then the function will switch to the local version of th branch; this might not
 * be up-to-date with the remote: this is done by design as the sync of local and remote branches should be a developer
 * concern.
 *
 * @param string      $branch The name of the branch to switch to, e.g. `release/B20.03`.
 * @param string|null $plugin The slug of the plugin to switch branch for; if not specified, then the current tric
 *                            target will be used.
 */
function switch_plugin_branch( $branch, $plugin = null ) {
	$cwd = getcwd();

	if ( false === $cwd ) {
		echo magenta( "Cannot get current working directory; is it accessible?j\n" );
		exit( 1 );
	}

	$plugin     = null === $plugin ? tric_target() : $plugin;
	$plugin_dir = tric_plugins_dir( $plugin );

	echo light_cyan( "Temporarily using {$plugin}\n" );

	$changed    = chdir( $plugin_dir );

	if ( false === $changed ) {
		echo magenta( "Cannot change to directory {$plugin_dir}; is it accessible?\n" );
		exit( 1 );
	}

	$current_branch = check_status_or_exit( process( 'git branch --show-current' ) )( 'string_output' );

	if ( $current_branch === $branch ) {
		// Already on the correct branch.
		return;
	}

	$locally_available = check_status_or_exit( process( 'git branch' ) )( 'output' );

	// Clean up the branch names.
	$locally_available = array_map( static function ( $branch ) {
		return trim( preg_replace( '/^\*\\s+/', '', $branch ) );
	}, $locally_available );

	if ( ! in_array( $branch, $locally_available, true ) ) {
		echo "Branch {$branch} not found locally: checking it out from remotes...";
		$status  = 1;
		$remotes = check_status_or_exit( process( 'git remote' ) )( 'output' );
		foreach ( $remotes as $remote ) {
			// Try fetching from each available remote.
			$command = sprintf( 'git checkout -b %1$s --recurse-submodules %2$s/%1$s', $branch, $remote );
			$status  = process_realtime( $command );
			if ( 0 === $status ) {
				// We're done.
				break;
			}
		}

		if ( 0 !== $status ) {
			// If we could not fetch from any remote we failed.
			echo magenta( "Remote branch fetch failed.\n" );
			exit( 1 );
		}
	} else {
		echo "Branch {$branch} found locally: checking it out...";
		$command = 'git checkout --recurse-submodules ' . $branch;
		if ( 0 !== process_realtime( $command ) ) {
			echo magenta( "Branch switch failed.\n" );
			exit( 1 );
		}
	}

	// Restore the current working directory to the previous value.
	echo light_cyan( 'Using ' . tric_target() . " once again\n" );
	$restored = chdir( $cwd );

	if ( false === $restored ) {
		echo magenta( "Could not restore working directory {$cwd}\n" );
		exit( 1 );
	}
}

/**
 * If tric itself is out of date, prompt to update repo.
 */
function maybe_prompt_for_repo_update() {
	$remote_version = null;
	$check_date     = null;
	$cli_version    = CLI_VERSION;
	$today          = date( 'Y-m-d' );

	if ( file_exists( TRIC_ROOT_DIR . '/.remote-version' ) ) {
		list( $check_date, $remote_version ) = explode( ':', file_get_contents( TRIC_ROOT_DIR . '/.remote-version' ) );
	}

	if ( empty( $remote_version ) || empty( $check_date ) || $today > $check_date ) {
		$current_dir = getcwd();
		chdir( TRIC_ROOT_DIR );

		$tags = explode( "\n", shell_exec( 'git ls-remote --tags origin' ) );

		chdir( $current_dir );

		foreach ( $tags as &$tag ) {
			$tag_parts = explode( '/', $tag );
			$tag       = array_pop( $tag_parts );
		}

		natsort( $tags );

		$remote_version = array_pop( $tags );

		file_put_contents( TRIC_ROOT_DIR . '/.remote-version', "{$today}:{$remote_version}" );
	}

	// If the version of the CLI is the same as the most recently built version, bail.
	if ( version_compare( $remote_version, $cli_version, '<=' ) ) {
		return;
	}

	echo magenta( "\n****************************************************************\n\n" );
	echo colorize( "<magenta>Version</magenta> <yellow>{$remote_version}</yellow> <magenta>of tric is available! You are currently</magenta>\n" );
	echo magenta( "running version {$cli_version}. To update, execute the following:\n\n" );
	echo yellow( "                         tric upgrade\n\n" );
	echo magenta( "****************************************************************\n" );
}

/**
 * If tric stack is out of date, prompt for an execution of tric update.
 */
function maybe_prompt_for_stack_update() {
	$build_version = '0.0.1';
	$cli_version   = CLI_VERSION;

	if ( file_exists( TRIC_ROOT_DIR . '/.build-version' ) ) {
		$build_version = file_get_contents( TRIC_ROOT_DIR . '/.build-version' );
	}

	// If there isn't a .env.tric.run, this is likely a fresh install. Bail.
	if ( ! file_exists( TRIC_ROOT_DIR . '/.env.tric.run' ) ) {
		return;
	}

	// If the version of the CLI is the same as the most recently built version, bail.
	if ( version_compare( $build_version, $cli_version, '=' ) ) {
		return;
	}

	echo magenta( "\n****************************************************************\n\n" );
	echo yellow( "                  ____________    ____  __\n" );
	echo yellow( "                  |   ____\   \  /   / |  |\n" );
	echo yellow( "                  |  |__   \   \/   /  |  |\n" );
	echo yellow( "                  |   __|   \_    _/   |  |\n" );
	echo yellow( "                  |  |        |  |     |  |\n" );
	echo yellow( "                  |__|        |__|     |__|\n\n" );
	echo magenta( "Your tric containers are not up to date with the latest version.\n" );
	echo magenta( "                  To update, please run:\n\n" );
	echo yellow( "                         tric update\n\n" );
	echo magenta( "****************************************************************\n" );
}

/**
 * Handles the build-subdir command request.
 *
 * @param callable $args The closure that will produce the current subdirectories build arguments.
 */
function tric_handle_build_subdir( callable $args ) {
	$run_settings_file = root( '/.env.tric.run' );
	$toggle            = $args( 'toggle', 'on' );

	if ( 'status' === $toggle ) {
		build_subdir_status();

		return;
	}

	$value = 'on' === $toggle ? 1 : 0;
	echo 'Build Sub-directories status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );

	if ( $value === (int) getenv( 'TRIC_BUILD_SUBDIR' ) ) {
		return;
	}

	write_env_file( $run_settings_file, [ 'TRIC_BUILD_SUBDIR' => $value ], true );
}

/**
 * Prints the current build-subdir status to screen.
 */
function build_subdir_status() {
	$enabled = getenv( 'TRIC_BUILD_SUBDIR' );

	echo 'Sub-directories build status is: ' . ( $enabled ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
}

/**
 * Build a command pool, suitable to be run using the `execute_command_pool` function, for multiple targets.
 *
 * If any subdirectories are provided and are available in the target, then the user will be prompted to run the same
 * command on those subdirectories.
 *
 * @param array<string> $targets         An array of targets for the command pool; note the targets are NOT validated by
 *                                       this function and the validation should be done by the calling code.
 * @param string        $base_command    The base service command to run, e.g. `npm`, `composer`, etc.
 * @param array<string> $command         The command to run, e.g. `['install','--save-dev']` in array format.
 * @param array<string> $sub_directories Sub directories to prompt for additional execution.
 *
 * @return array The built command pool for all the targets.
 */
function build_targets_command_pool( array $targets, $base_command, array $command, array $sub_directories = [] ) {
	$raw_command_pool = array_combine(
		$targets,
		array_map( static function ( $target ) use ( $base_command, $command, $sub_directories ) {
			return build_command_pool( $base_command, $command, $sub_directories, $target );
		}, $targets )
	);

	// Set the keys correctly to have the command prefixes correctly built.
	$command_pool = [];
	foreach ( $raw_command_pool as $target => $target_pool ) {
		foreach ( $target_pool as $target_key => $process ) {
			$key                  = preg_replace(
				[
					// Main target.
					'/^target:/',
					// Sub-directories.
					'/^([\w\d]+):/'
				],
				[
					// Replace with `<target>:`.
					$target . ':',
					// Replace with `<target>/<subdir>:`.
					$target . '/$1:'
				],
				$target_key
			);
			$command_pool[ $key ] = $process;
		}
	}

	return $command_pool;
}

/**
 * Returns the current target or exits if no target is set.
 *
 * @param string|null $reason The colorized reason why the target should be set.
 *
 * @return string The current target, if set, else the function will exit.
 */
function tric_target_or_fail( $reason = null ) {
	$target = tric_target();

	if ( empty( $target ) ) {
		$reason = $reason
			?: magenta( 'This command requires a target set using the ' )
			   . light_cyan( 'use' )
			   . magenta( ' command.' );
		echo colorize( $reason . PHP_EOL );
		exit( 1 );
	}

	return $target;
}

/**
 * Returns the absolute path to the current target.
 *
 * @param null|string $append_path A relative path to append to the target absolute path.
 *
 * @return string The absolute path to the current target.
 */
function target_absolute_path( $append_path = null ) {
	$here_abs_path    = rtrim( getenv( 'TRIC_HERE_DIR' ), '\\/' );
	$target_rel_path  = '/' . trim( tric_target(), '\\/' );
	$full_target_path = $here_abs_path . $target_rel_path;
	if ( empty( $append_path ) ) {
		return $full_target_path;
	}

	return $full_target_path . '/' . ltrim( $append_path, '\\/' );
}

/**
 * Compiles a list of the current target Codeception suites. The available suites are inferred, as Codeception does,
 * from the available suite configuration files.
 *
 * @return array<string> A list of the available target suites.
 */
function collect_target_suites() {
	// If the command is just `run`, without arguments, then collect the available suites and run them separately.
	$dir_iterator = new \DirectoryIterator( target_absolute_path( 'tests' ) );
	$suitesFilter = new \CallbackFilterIterator( $dir_iterator, static function ( \SplFileInfo $file ) {
		return $file->isFile() && preg_match( '/^.*\\.suite(\\.dist)?\\.yml$/', $file->getBasename() );
	} );
	$suites       = [];
	foreach ( $suitesFilter as $f ) {
		$suites[] = preg_replace( '/^([\\w-]+)\\.suite(\\.dist)?\\.yml$/u', '$1', $f->getBasename() );
	}

	return $suites;
}
