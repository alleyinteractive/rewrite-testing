<?php

/**
 * View and run rewrite rule tests.
 *
 */
class Rewrite_Rule_Test_Command extends WP_CLI_Command {

	protected static $colors = array(
		'ok'      => '%g',
		'error'   => '%r',
		'tested'  => '%g',
		'missed'  => '%r',
		'Passing' => '%g',
		'Failing' => '%r',
	);

	/**
	 * List the available rewrite rule tests.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields.
	 *
	 * [--groups=<groups>]
	 * : Limit the output to specific test groups.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, csv. Default: table.
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each test:
	 * * group
	 * * path
	 * * match
	 * * query
	 *
	 * ## EXAMPLES
	 *
	 *     wp rewrite test list
	 *
	 *     wp rewrite test list --groups=Categories,Tags
	 *
	 *     wp rewrite test list --fields=path,match --format=csv
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$assoc_args = array_merge( array(
			'groups' => null,
		), $assoc_args );

		$tests = self::get_tests( $assoc_args );

		if ( is_wp_error( $tests ) ) {
			WP_CLI::error( $tests );
		}

		$formatter = $this->get_formatter( $assoc_args, array(
			'group',
			'path',
			'match',
			'query',
		) );

		$formatter->display_items( $tests );

	}

	/**
	 * Get a summary of the rewrite test results.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rewrite test summary
	 *
	 *     wp rewrite test summary --format=json
	 *
	 */
	public function summary( $args, $assoc_args ) {
		$summary = self::get_summary( $assoc_args );

		$formatter = $this->get_formatter( $assoc_args, array(
			'status',
			'errors',
			'tested',
			'missed',
			'coverage',
		) );

		$formatter->display_item( $summary );
	}

	/**
	 * Get the status of the rewrite tests.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rewrite test status
	 *
	 */
	public function status( $args, $assoc_args ) {
		$summary = self::get_summary( $assoc_args );

		WP_CLI::line( $summary['status'] );

	}

	/**
	 * Are the rewrite tests passing?
	 *
	 * ## EXAMPLES
	 *
	 *     wp rewrite test passing
	 *
	 */
	public function passing( $args, $assoc_args ) {
		$summary = self::get_summary( $assoc_args );

		if ( $summary['passing'] ) {
			WP_CLI::success( $summary['status'] );
		} else {
			WP_CLI::error( $summary['status'] );
		}
	}

	/**
	 * Run the rewrite tests.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, csv. Default: table.
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each test result:
	 * * status
	 * * group
	 * * path
	 * * matched
	 * * rewrite_test
	 * * rewrite_result
	 *
	 * These fields are optionally available:
	 * * query_test
	 * * query_result
	 *
	 * ## EXAMPLES
	 *
	 *     wp rewrite test run
	 *
	 *     wp rewrite test run --fields=status,path,rewrite_test,rewrite_result,query_test,query_result --format=csv
	 *
	 */
	public function run( $args, $assoc_args ) {
		$results = self::run_tests( $assoc_args );

		if ( is_wp_error( $results ) ) {
			WP_CLI::error( $results );
		}

		$formatter = $this->get_formatter( $assoc_args, array(
			'status',
			'group',
			'path',
			'matched',
			'rewrite_test',
			'rewrite_result',
		) );

		$formatter->display_items( $results );

	}

	/**
	 * Get a test coverage report for the rewrite rules on the site.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Limit the output to a specific status. Accepted values: missed, tested. Default: "missed,tested".
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rewrite test coverage
	 *
	 *     wp rewrite test coverage --status=missed --format=json
	 *
	 */
	public function coverage( $args, $assoc_args ) {
		$assoc_args = array_merge( array(
			'status' => 'missed,tested',
		), $assoc_args );

		$coverage = self::get_coverage( $assoc_args );

		$formatter = $this->get_formatter( $assoc_args, array(
			'status',
			'match',
			'query',
			'source',
		) );

		$formatter->display_items( $coverage );
	}

	protected static function get_coverage( array $assoc_args ) {
		$rrt     = Rewrite_Testing::instance();
		$summary = $rrt->get_summary();
		$coverage = array();

		if ( ! is_array( $assoc_args['status'] ) ) {
			$assoc_args['status'] = explode( ',', $assoc_args['status'] );
		}

		foreach ( $assoc_args['status'] as $status ) {
			if ( ! isset( $summary[ "{$status}_rules" ] ) ) {
				continue;
			}
			foreach ( $summary[ "{$status}_rules" ] as $rule => $target ) {
				$coverage[] = array(
					'status' => self::colorize( $status, self::$colors[ $status ], $assoc_args ),
					'match'  => $rule,
					'query'  => $target['rewrite'],
					'source' => $target['source'],
				);
			}
		}

		return $coverage;
	}

	protected static function run_tests( $assoc_args ) {
		$rrt     = Rewrite_Testing::instance();
		$results = $rrt->test();
		$list    = array();

		if ( empty( $results ) ) {
			return new WP_Error( 'no_tests', 'No tests found' );
		}

		if ( is_wp_error( $results ) ) {
			return $results;
		}

		$list = self::process_test_results( $results, $assoc_args );

		return $list;

	}

	protected static function process_test_results( array $results, array $assoc_args ) {

		$list = array();

		foreach ( $results as $result ) {
			if ( empty( $result['status'] ) ) {
				$result['status'] = self::colorize( 'ok', self::$colors['ok'], $assoc_args );
			} else {
				$result['status'] = self::colorize( $result['status'], self::$colors[ $result['status'] ], $assoc_args );
			}
			if ( isset( $result['test']['match'] ) ) {
				$result['rewrite_test'] = $result['test']['match'];
			} else {
				$result['rewrite_test'] = '';
			}
			$result['rewrite_result'] = $result['target'];
			if ( isset( $result['test']['query'] ) ) {
				$result['query_test'] = $result['test']['query'];
			} else {
				$result['query_test'] = '';
			}
			$result['query_result'] = $result['query'];
			$result['matched']      = $result['rule'];
			$list[] = $result;
		}

		return $list;

	}

	protected static function get_tests( array $args ) {
		$rrt   = Rewrite_Testing::instance();
		$tests = $rrt->test_cases();
		$list  = array();

		if ( empty( $tests ) ) {
			return new WP_Error( 'no_tests', 'No tests found' );
		}

		if ( $args['groups'] ) {
			if ( ! is_array( $args['groups'] ) ) {
				$args['groups'] = explode( ',', $args['groups'] );
			}

			$tests = array_intersect_key( $tests, array_flip( $args['groups'] ) );

			if ( empty( $tests ) ) {
				return new WP_Error( 'no_matching_tests', 'No matching tests found' );
			}
		}

		foreach ( $tests as $group => $test_cases ) {
			$list = array_merge( $list, self::process_test_cases( $test_cases, $group ) );
		}

		return $list;
	}

	protected static function process_test_cases( array $test_cases, $group ) {

		$list = array();

		foreach ( $test_cases as $path => $test ) {
			if ( ! is_array( $test ) ) {
				$test = array(
					'match' => $test,
				);
			}
			if ( ! isset( $test['query'] ) ) {
				$test['query'] = null;
			}
			if ( ! isset( $test['match'] ) ) {
				$test['match'] = null;
			}
			$test['group'] = $group;
			$test['path']  = $path;
			$list[] = $test;
		}

		return $list;

	}

	protected static function get_summary( $assoc_args ) {

		$rrt     = Rewrite_Testing::instance();
		$summary = $rrt->get_summary();
		$list    = array(
			'status'   => self::colorize( $summary['status'], self::$colors[ $summary['status'] ], $assoc_args ),
			'passing'  => $summary['passing'],
			'errors'   => ! empty( $summary['error_count'] ) ? $summary['error_count'] : 0,
			'total'    => $summary['total'],
			'tested'   => $summary['tested'],
			'missed'   => $summary['missed'],
			'coverage' => sprintf( '%d%%', $summary['coverage'] ),
		);

		return $list;

	}

	protected static function colorize( $text, $color, $assoc_args ) {
		if ( ! empty( $assoc_args['format'] ) && ( 'table' !== $assoc_args['format'] ) ) {
			return $text;
		}
		return WP_CLI::colorize( $color . $text . '%n' );
	}

	private function get_formatter( &$assoc_args, $fields ) {
		return new \WP_CLI\Formatter( $assoc_args, $fields );
	}

}

WP_CLI::add_command( 'rewrite test', 'Rewrite_Rule_Test_Command' );
