<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Sets the current plugins directory to be the one used by tric.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} here [reset]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} here</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} here reset</light_cyan>\n" );
	return;
}

$sub_args    = args( [ 'reset' ], $args( '...' ), 0 );
$reset       = $sub_args( 'reset', false );

$wp_dir      = './_wordpress';
$plugins_dir = './_plugins';
$themes_dir  = './_wordpress/wp-content/themes';

if ( empty( $reset ) ) {
	$here_dir = getcwd();
	if ( false === $here_dir ) {
		echo magenta( "Cannot get the current working directory with 'getcwd'; please make sure it's accessible." );
		exit( 1 );
	}
} else {
	$here_dir = $plugins_dir;
}

$has_wp_config = dir_has_wp_config( $here_dir );
$env_values    = [];

if ( $has_wp_config ) {
	if ( file_exists( "{$here_dir}/wp-content" ) ) {
		$wp_content_dir = "{$here_dir}/wp-content";
	} elseif ( file_exists( "{$here_dir}/content" ) ) {
		$wp_content_dir = "{$here_dir}/content";
	} else {
		echo magenta( "Cannot locate the wp-content directory. If you have a custom wp-content location, you will need to set the TRIC_WP_DIR, TRIC_PLUGINS_DIR, and TRIC_THEMES_DIR manually in tric's .env.tric.run file." );
		exit( 1 );
	}

	$env_values['TRIC_HERE_DIR'] = $here_dir;

	if ( file_exists( "{$here_dir}/wp" ) ) {
		$here_dir .= '/wp';
	}

	$env_values['TRIC_WP_DIR']       = $here_dir;
	$env_values['TRIC_PLUGINS_DIR']  = "{$wp_content_dir}/plugins";
	$env_values['TRIC_THEMES_DIR']   = "{$wp_content_dir}/themes";
} else {
	$env_values['TRIC_HERE_DIR']     = $here_dir;
	$env_values['TRIC_WP_DIR']       = $wp_dir;
	$env_values['TRIC_PLUGINS_DIR']  = $here_dir;
	$env_values['TRIC_THEMES_DIR']   = $themes_dir;
}

// When changing the here target, clear the currently selected project.
$env_values['TRIC_CURRENT_PROJECT']               = '';
$env_values['TRIC_CURRENT_PROJECT_RELATIVE_PATH'] = '';

write_env_file( $run_settings_file, $env_values, true );

teardown_stack();

echo colorize( "\n<light_cyan>Tric plugin path set to</light_cyan> {$here_dir}.\n\n" );
echo colorize( "If this is the first time setting this plugin path, be sure to <light_cyan>tric init <plugin></light_cyan>." );
