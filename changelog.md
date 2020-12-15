# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.20] - 2020-12-15
### Changed
- Add the `dc` command to run `docker-compose` commands in the stack with properly set up `tric` environment variables.
- Updated the `chrome` service image to `selenium/standalone-chrome:4.0.0`.

## [0.5.19] - 2020-12-11
### Changed
- Fix an issue where commands that required a ready and available WordPress installation in the `tric` WordPress 
  directory would not take care to scaffold and install it; e.g. `cli` or `site-cli`.

## [0.5.18] - 2020-12-07
### Changed
- Fix an issue where the `USING_CONTAINERS` environment variable would be duplicated in environment files set up by 
  `tric` init command.  
- Fix an issue where a negative answer to build targets with sub-directories, e.g. TEC and ET, would result in the 
  target being built anyway.

## [0.5.17] - 2020-12-04
### Changed

- Fix the XDebug version and `wordpress` service `Dockerfile` to keep using version `2` and not update to version `3` on a rebuild (e.g. one triggered by `tric update` or `tric build-stack` commands).
- Add the `tric ssh` command to allow opening a shell in a **running** stack service; differently from the `shell` command, the `ssh` command will **not** start the service if it's not already running.

## [0.5.16] - 2020-11-26
### Changed

- Add support for the `TRIC_HOST` environment variable. This will override the default host machine IP address lookup `tric` would perform on Linux or the hard-wired `host.docker.internal` hostname `tric` would use on Windows and Mac host to set the default `xdebug.remote_host` value.
- Default to the host machin IP address to set `xdebug.remote_host` only if the host has not been set  by means of a call to `tric xdebug host <host>` or by setting the `XDH` environment variable explicitly.
- Fix an issue that would reset run settings stored in the `.env.tric.run` file when using the `tric xdebug <key> <value>` command.
- Correctly handle Docker network removal in parallel tasks to avoid "error while removing network" errors.
- Add support for `tric run` to run all the avaiable Codeception test suites from the target one after another.

## [0.5.15] - 2020-11-24
### Changed

- Removed the 3s wait at start of the `codeception` service.
- Fixed (in the context of the [lucatume/dockerfiles](https://github.com/lucatume/dockerfiles) repository) an issue that would cause Codeception tests to exit `0` on failure and not `1` as expected.

## [0.5.14] - 2020-11-24
### Changed

- Fixed volumes setup to make sure the volume, and the host file structure, created by WordPress container is owned by the current user and not `root`.
- Fixed and issue that would change, on Linux, the file modes of all the used plugins, to `a+rwx` when using the `run` command; fixes #36

## [0.5.13] - 2020-11-23
### Changed

- Executing `tric use` without a target now attempts to set the current working directory as the target.
- `tric info` now outputs all valid targets, which is what `tric use` without a valid target used to do.
- The Composer prompt from `tric init` no longer appears if `composer.json` is not found, likewise for the NPM prompt if `package.json` is not found.
- Fixed an issue that would prevent the `npm` and `npm_lts` services from correctly returning their exit status.
- Add support for the `--pretty` flag to the `npm` and `npm_lts` commands to print a more human-readable output.

## [0.5.12] - 2020-11-11
### Changed

- Fixed two issues in the `target` command where the command would fail if no previous target had been set.
- Set the start of the random network subnet pool for parallel processes used by the `target` command to a higher number to reduce the chance of running into overlapping pool issues.

## [0.5.11] - 2020-11-03
### Changed

- Activate all debug options in the `wordpress` service.
- Use a custom WordPress image for the stack, based on the default `wordpress` one, but modified to support and use XDebug.

## [0.5.10] - 2020-10-14
### Changed

- Fixed a smaller issue in the `target` command.

## [0.5.9] - 2020-10-07
### Changed

- Add the `site-cli` command to start a wp-cli container on a running and ready WordPress stack.

## [0.5.8] - 2020-10-06
### Changed

- Add a check to ensure a target is set for commands that require it.

## [0.5.7] - 2020-08-28
### Changed

- Add the `npm_lts` service and service to the stack to run `npm` commands on the current LTS version of node.

## [0.5.6] - 2020-08-27
### Changed

- Fixed an issue where the terminal columns and lines detection would cause issue in CI context.
- Fixed an issue where the `npm` image would not build correctly in CI context.

## [0.5.5] - 2020-08-19
### Changed

- Add support for the `bash` sub-command to the `tric cli` command to allow opening a `bash` shell into the `cli` container to manage the WordPress installation currently being served by `tric serve`.


## [0.5.4] - 2020-08-18
### Changed

- Add support for multiple commands in the `target` command to allow running a set of commands on a set of targets.

## [0.5.3] - 2020-08-13
### Changed

- Update the WordPress version used in the stack to `5.5`.
- Fix handling of default answers in prompts.

## [0.5.2] - 2020-08-10
### Changed

- Fix an issue where `docker-compose` would display an error due to missing default value for the `TRIC_CURRENT_PROJECT_SUBDIR` env var.

## [0.5.1] - 2020-08-04
### Changed

- Fix an issue where the `function-mocker-cache` volume would be mounted with `root` ownership on Linux systems causing Function Mocker to fail while trying to set up cache in plugins that use it.

## [0.5.0] - 2020-07-30
### Added

- Added the `tric target` command to support running the same command against multiple targets.

## [0.4.9] - 2020-07-07
### Changed

- Fix an issue where the `tric init` command would not correctly pick up the existing environment files if not running from the plugin directory.

### Added

- Added the `TRIC_INTERACTIVE`, `TRIC_BUILD_PROMPT` and `TRIC_BUILD_SUBDIR` env var to the `tric info` report.

## [0.4.8] - 2020-07-02
### Added

- Added `tric build-subdir` which allows you to control whether sub-directories (e.g. `common` in The Events Calendar) should be built during composer/npm commands or not.

### Changed

- Fix an issue where the `build-prompt` status was reported incorrectly.

## [0.4.7] - 2020-07-01
### Changed

- Fix an issue with file modes on the WordPress container that would make its `wp-content`  directory `root` owned on Linux hosts.

## [0.4.6] - 2020-06-30
### Changed

- Fix parallel processing closure argument requirements.

## [0.4.5] - 2020-06-30
### Changed

- Allow for additional git upstreams other than GitHub.

## [0.4.4] - 2020-06-29
### Changed

- Fix and re-enable the parallel processing functionality.

## [0.4.3] - 2020-06-26
### Changed

- Temporarily disabled the parallel processing functionality.

## [0.4.2] - 2020-06-26
### Changed

- When running parallel processes, use random subnets to avoid container collision.

## [0.4.1] - 2020-06-25

### Changed

- Add support for `DB_` prefixed database credentials and name in the original `.env.*` files.

## [0.4.0] - 2020-06-25
### Added

- Added `tric upgrade` which allows you to upgrade `tric` to the latest tagged release.

## [0.3.0] - 2020-06-25
### Added

- Prompt to `tric update` when container build version are out of sync from the tric version.
- Output npm error log when one is generated.

### Changed

- Adjust pathing of subdirectories within the tric stack so that npm can find a `.git` directory when performing `npm install`.
- Suppress the `fixuid` command output in the npm `docker-entrypoint.sh`.
- Separated out poolable (passive) command functions from realtime command functions to prevent issues with interactivity.

## [0.2.0] - 2020-06-24
### Added

- Added phpcs and phpcbf commands.
- Added parallel processing of commands.

### Changed

- Changed `tric build` to `tric build-stack`.

## [0.1.1] - 2020-05-26
### Changed

- Ensure `.htaccess` file is present in `_wordpress`.

## [0.1.0] - 2020-05-25
### Added

- Initial version
