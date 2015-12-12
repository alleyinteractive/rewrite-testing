<?php

/*
	Plugin Name: Rewrite Rule Testing
	Plugin URI: https://github.com/alleyinteractive/rewrite-testing
	Description: Unit test your rewrite rules
	Version: 1.0.0
	Author: Matthew Boynes, John Blackbourn
	Author URI: http://www.alleyinteractive.com/
*/
/*  This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( ! class_exists( 'Rewrite_Testing' ) ) :

	class Rewrite_Testing {

		private static $instance;

		protected $transient_key = 'rewrite-testing-results';

		protected $errors = 0;

		protected $summary = array();

		private function __construct() {
			/* Don't do anything, needs to be initialized via instance() method */
		}

		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new Rewrite_Testing;
				self::$instance->setup();
			}
			return self::$instance;
		}


		/**
		 * Add in hooks and filters
		 *
		 * @return void
		 */
		public function setup() {
			add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );

			add_filter( 'debug_bar_panels', array( $this, 'debug_bar_panel' ) );
			add_filter( 'debug_bar_classes', array( $this, 'debug_bar_classes' ) );
			add_filter( 'debug_bar_statuses', array( $this, 'debug_bar_statuses' ) );

			add_action( 'generate_rewrite_rules', array( $this, 'clear_cache' ) );

			if ( isset( $_GET['page'], $_GET['action'] ) && 'rewrite-testing' == $_GET['page'] && 'flush-rules' == $_GET['action'] ) {
				add_action( 'admin_init', array( $this, 'flush_rules' ) );
			} elseif ( isset( $_GET['page'], $_GET['message'] ) && 'rewrite-testing' == $_GET['page'] && 'flush-success' == $_GET['message'] ) {
				add_action( 'admin_notices', array( $this, 'action_admin_notices' ) );
			}
		}


		/**
		 * Add our rewrite testing page to admin menu
		 *
		 * @return void
		 */
		function action_admin_menu() {
			add_submenu_page( 'tools.php', __( 'Reload & Test Rewrite Rules', 'rewrite-testing' ), __( 'Rewrite Testing', 'rewrite-testing' ), 'manage_options', 'rewrite-testing', array( $this, 'page_test_rewrites' ) );
		}


		/**
		 * Show a message when you've successfully flushed your rewrite rules
		 *
		 * @return void
		 */
		function action_admin_notices() {
			echo '<div class="message updated"><p>' . esc_html__( 'Rewrite rules flushed.', 'rewrite-testing' ) . '</p></div>';
		}


		/**
		 * Rewrite testing page in admin
		 *
		 * @return void
		 */
		function page_test_rewrites() {
			global $plugin_page;
			$flush_url = add_query_arg(
				array(
					'action' => 'flush-rules',
					'_wpnonce' => wp_create_nonce( 'flush-rules' ),
				),
				menu_page_url( $plugin_page, false )
			);
			?>
			<style type="text/css">
				#rewrite_testing_untested tr:nth-child(even),
				#rt_test_results tr:nth-child(even) {
					background-color: #f9f9f9;
				}
				#rt_test_results tr.error {
					background-color: #f7a8a9;
				}
				#rt_test_results tr.error:nth-child(even) {
					background-color: #f2a5a6;
				}
				#rt_test_results tr.error td {
					border-top-color: #FECFD0;
					border-bottom-color: #f99b9d;
				}
				#rt_test_results td strong {
					width: 8em;
					display: inline-block;
				}
				#rewrite_testing_untested {
					display: none;
					margin-bottom: 20px;
				}
			</style>
			<script type="text/javascript">
				jQuery(function($){
					$('a[href="#rewrite_testing_untested"]').on('click',function(e){
						e.preventDefault();
						$($(this).attr('href')).toggle();
					});
				});
			</script>
			<div class="wrap">
				<h2><?php esc_html_e( 'Test & Flush Rewrite Rules', 'rewrite-testing' ); ?></h2>

				<p><a title="<?php esc_attr_e( 'Flush your rewrite rules to regenerate them', 'rewrite-testing' ); ?>" class="button-secondary" href="<?php echo esc_url( $flush_url ); ?>"><?php esc_html_e( 'Flush Rewrite Rules', 'rewrite-testing' ); ?></a></p>

				<?php
				$results = $this->test();
				$summary = $this->get_summary();
				if ( is_wp_error( $results ) ) : ?>
					<div class="message error">
						<p><?php echo esc_html( $results->get_error_message() ) ?></p>
					</div>
					<?php if ( 'rt_missing_rules' == $results->get_error_code() && ( $rules = $results->get_error_data() ) ) : ?>
						<h3><?php esc_html_e( 'Missing Rewrite Rules', 'rewrite-testing' ); ?></h3>
						<table class="widefat">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Rule', 'rewrite-testing' ); ?></th>
									<th><?php esc_html_e( 'Rewrite', 'rewrite-testing' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( (array) $rules as $rule => $rewrite ) : ?>
								<tr>
									<td><?php echo esc_html( $rule ) ?></td>
									<td><?php echo esc_html( $rewrite['rewrite'] ) ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				<?php else : ?>
					<h3><?php esc_html_e( 'Test Results', 'rewrite-testing' ); ?></h3>
					<?php if ( $this->errors ) : ?>
						<div class="message error">
							<p><?php echo esc_html( sprintf( _n( '1 test failed!', '%d tests failed!', $this->errors, 'rewrite-testing' ), $this->errors ) ); ?></p>
						</div>
					<?php else : ?>
						<div class="message updated">
							<p><?php esc_html_e( 'All tests are passing', 'rewrite-testing' ) ?></p>
						</div>
					<?php endif ?>

					<div class="message notice notice-info">
						<p><?php
							printf(
								esc_html__( '%1$s/%2$s rewrite rules covered (%3$s%%).', 'rewrite-testing' ),
								esc_html( number_format_i18n( $summary['tested'] ) ),
								esc_html( number_format_i18n( $summary['total'] ) ),
								esc_html( number_format_i18n( $summary['coverage'] ) )
							);
							if ( ! empty( $summary['missed_rules'] ) ) {
								printf(
								    ' <a href="#rewrite_testing_untested">%1$s</a>',
								    esc_html__( 'Show untested rules', 'rewrite-rules' )
								);
							}
						?></p>
					</div>

					<?php if ( ! empty( $summary['missed_rules'] ) ) { ?>
						<table class="wp-list-table widefat" id="rewrite_testing_untested">
							<?php $this->coverage_table_head(); ?>
							<tbody>
								<?php array_walk( $summary['missed_rules'], array( $this, 'untested_row' ) ) ?>
							</tbody>
						</table>
					<?php } ?>

					<table class="wp-list-table widefat">
						<?php $this->results_table_head() ?>
						<tbody id="rt_test_results">
							<?php array_walk( $results, array( $this, 'results_row' ) ) ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Output the head for the rewrite test results table.
		 *
		 * @return void
		 */
		public function results_table_head() {
			?>
			<thead>
				<tr>
					<th scope="col" class="manage-column"><?php esc_html_e( 'Group', 'rewrite-testing' ); ?></th>
					<th scope="col" class="manage-column"><?php esc_html_e( 'Status', 'rewrite-testing' ); ?></th>
					<th scope="col" class="manage-column"><?php esc_html_e( 'Test Path', 'rewrite-testing' ); ?></th>
					<th scope="col" class="manage-column"><?php esc_html_e( 'Results', 'rewrite-testing' ); ?></th>
				</tr>
			</thead>
			<?php
		}

		/**
		 * Output the head for the rewrite test coverage table.
		 *
		 * @return void
		 */
		public function coverage_table_head() {
			?>
			<thead>
				<tr>
					<th scope="col" class="manage-column"><?php esc_html_e( 'Rule', 'rewrite-testing' ); ?></th>
					<th scope="col" class="manage-column"><?php esc_html_e( 'Rewrite', 'rewrite-testing' ); ?></th>
					<th scope="col" class="manage-column"><?php esc_html_e( 'Source', 'rewrite-testing' ); ?></th>
				</tr>
			</thead>
			<?php
		}

		/**
		 * Output a row of rewrite test results forthe results table.
		 *
		 * @param  array $row {
		 * 		Row of data for the results table.
		 * 		@type type $key Description. Default <value>. Accepts <value>, <value>.
		 * 		@type  string $status The status string.
		 * 		@type  string $group The rewrite permastruct.
		 * 		@type  string $path The test path.
		 * 		@type  string $test The test -- what we expect to see.
		 * 		@type  string $target The target matched -- what we did see.
		 * 		@type  string $rule The rule that actually matched.
		 * }
		 * @return void
		 */
		public function results_row( $row ) {
			?>
			<tr class="<?php echo esc_attr( $row['status'] ) ?>">
				<td><?php echo esc_html( $row['group'] ) ?></td>
				<?php if ( 'error' == $row['status'] ) : ?>
					<td><?php esc_html_e( 'Failed!', 'rewrite-testing' ) ?></td>
				<?php else : ?>
					<td><?php esc_html_e( 'Passed', 'rewrite-testing' ) ?></td>
				<?php endif ?>
				<td><?php echo esc_html( $row['path'] ) ?></td>
				<td>
					<?php if ( ! empty( $row['test']['match'] ) ) : ?>
						<strong><?php esc_html_e( 'Rewrite Test:', 'rewrite-testing' ); ?></strong> <?php echo esc_html( $row['test']['match'] ) ?><br />
						<strong><?php esc_html_e( 'Rewrite Result:', 'rewrite-testing' ); ?></strong> <?php echo esc_html( $row['target'] ) ?><br />
					<?php endif; ?>
					<?php if ( ! empty( $row['test']['query'] ) ) : ?>
						<strong><?php esc_html_e( 'Query Test:', 'rewrite-testing' ); ?></strong> <?php echo esc_html( http_build_query( $row['test']['query'] ) ) ?><br />
						<strong><?php esc_html_e( 'Query Result:', 'rewrite-testing' ); ?></strong> <?php echo esc_html( http_build_query( $row['query'] ) ) ?><br />
					<?php endif; ?>
					<strong><?php esc_html_e( 'Matched:', 'rewrite-testing' ); ?></strong> <?php echo esc_html( $row['rule'] ) ?>
					<?php do_action( 'rewrite_testing_unit_results', $row ) ?>
				</td>
			</tr>
			<?php
		}

		/**
		 * Output a row representing a rewrite rule that was not covered during a test.
		 *
		 * @param  array $target {
		 *     Row of data for the rewrite rule.
		 *
		 *     @type string $rewrite The rewrite query.
		 *     @type string $source  The source of the rewrite rule.
		 * }
		 * @param  string $rule The rewrite rule.
		 * @return void
		 */
		public function untested_row( $target, $rule ) {
			?>
			<tr>
				<td><strong><?php echo esc_html( $rule ); ?></strong></td>
				<td><?php echo esc_html( $target['rewrite'] ); ?></td>
				<td><?php echo esc_html( $target['source'] ); ?></td>
			</tr>
			<?php
		}

		/**
		 * Allow a user to flush rewrite rules for their site
		 *
		 * @return void
		 */
		function flush_rules() {
			global $plugin_page;

			// Check nonce and permissions
			check_admin_referer( 'flush-rules' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permissions to perform this action.' ) );
			}

			flush_rewrite_rules( false );

			// Woo hoo!
			wp_safe_redirect( add_query_arg( array( 'message' => 'flush-success' ), menu_page_url( $plugin_page, false ) ) );
			exit;
		}


		/**
		 * Rewrite rule test units. Returns an array of arrays, where the inner array is organized as path => test:
		 * 		(string) path = The URL you want to test, e.g. /parent-page/child-page/
		 * 		(string) test = The rewrite match we expect to see in return.
		 *
		 * @return array
		 */
		public function test_cases() {
			global $wp_rewrite;

			$tag_base      = get_option( 'tag_base' ) ? get_option( 'tag_base' ) : 'tag';
			$category_base = get_option( 'category_base' ) ? get_option( 'category_base' ) : 'category';
			$search_base   = $wp_rewrite->search_base;
			$author_base   = $wp_rewrite->author_base;

			// Array of arrays of path => should match
			$tests = array(
				'Query Test' => array(
					'/query-test/' => array( 'query' => array( 'page' => '', 'pagename' => 'query-test' ) ),
				),
				'Categories' => array(
					"/{$category_base}/uncategorized/feed/atom/" => 'index.php?category_name=$matches[1]&feed=$matches[2]',
					"/{$category_base}/parent/child/feed/rss"    => 'index.php?category_name=$matches[1]&feed=$matches[2]',
					"/{$category_base}/uncategorized/atom/"      => 'index.php?category_name=$matches[1]&feed=$matches[2]',
					"/{$category_base}/parent/child/feed"        => 'index.php?category_name=$matches[1]&feed=$matches[2]',
					"/{$category_base}/uncategorized/page/345"   => 'index.php?category_name=$matches[1]&paged=$matches[2]',
					"/{$category_base}/parent/child/page2"       => 'index.php?category_name=$matches[1]&paged=$matches[2]',
					"/{$category_base}/uncategorized/"           => 'index.php?category_name=$matches[1]',
					"/{$category_base}/parent/child"             => 'index.php?category_name=$matches[1]',
				),
				'Tags' => array(
					"/{$tag_base}/hello/feed/atom/" => 'index.php?tag=$matches[1]&feed=$matches[2]',
					"/{$tag_base}/hello/feed/"      => 'index.php?tag=$matches[1]&feed=$matches[2]',
					"/{$tag_base}/hello/page/123"   => 'index.php?tag=$matches[1]&paged=$matches[2]',
					"/{$tag_base}/hello/"           => 'index.php?tag=$matches[1]',
				),
				'Post Format' => array(
					'/type/hello/feed/atom/' => 'index.php?post_format=$matches[1]&feed=$matches[2]',
					'/type/hello/feed/'      => 'index.php?post_format=$matches[1]&feed=$matches[2]',
					'/type/hello/page/123'   => 'index.php?post_format=$matches[1]&paged=$matches[2]',
					'/type/hello/'           => 'index.php?post_format=$matches[1]',
				),
				'Misc' => array(
					'/robots.txt'        => 'index.php?robots=1',
					'/wp-rss.php'        => 'index.php?feed=old',
					'/hello/wp-atom.php' => 'index.php?feed=old',
					'/wp-app.php/hello'  => 'index.php?error=403',
					'/wp-register.php'   => 'index.php?register=true',
				),
				'Homepage' => array(
					'/feed/atom/'         => 'index.php?&feed=$matches[1]',
					'/feed'               => 'index.php?&feed=$matches[1]',
					'/page/2/'            => 'index.php?&paged=$matches[1]',
					'/comments/feed/rss/' => 'index.php?&feed=$matches[1]&withcomments=1',
					'/comments/atom/'     => 'index.php?&feed=$matches[1]&withcomments=1',
				),
				'Search' => array(
					"/{$search_base}/hello/feed/atom/"  => 'index.php?s=$matches[1]&feed=$matches[2]',
					"/{$search_base}/hello/world/feed/" => 'index.php?s=$matches[1]&feed=$matches[2]',
					"/{$search_base}/hello/page/123"    => 'index.php?s=$matches[1]&paged=$matches[2]',
					"/{$search_base}/hello/"            => 'index.php?s=$matches[1]',
				),
				'Authors' => array(
					"/{$author_base}/hello/feed/atom/" => 'index.php?author_name=$matches[1]&feed=$matches[2]',
					"/{$author_base}/hello/feed/"      => 'index.php?author_name=$matches[1]&feed=$matches[2]',
					"/{$author_base}/hello/page/123"   => 'index.php?author_name=$matches[1]&paged=$matches[2]',
					"/{$author_base}/hello/"           => 'index.php?author_name=$matches[1]',
				),
				'Dates' => array(
					'/2014/1/1/feed/rss/' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]',
					'/2014/2/10/rss/'     => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]',
					'/2014/3/20/page/2/'  => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&paged=$matches[4]',
					'/2014/4/30/'         => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]',
					'/2014/5/feed/rss/'   => 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]',
					'/2014/10/rss/'       => 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]',
					'/2014/11/page/123/'  => 'index.php?year=$matches[1]&monthnum=$matches[2]&paged=$matches[3]',
					'/2014/12/'           => 'index.php?year=$matches[1]&monthnum=$matches[2]',
					'/2014/feed/rss/'     => 'index.php?year=$matches[1]&feed=$matches[2]',
					'/2014/rss/'          => 'index.php?year=$matches[1]&feed=$matches[2]',
					'/2014/page/4567/'    => 'index.php?year=$matches[1]&paged=$matches[2]',
					'/2014/'              => 'index.php?year=$matches[1]',
				),
				'Posts' => array(
					'/2014/1/1/hello/attachment/world/'                 => 'index.php?attachment=$matches[1]',
					'/2014/2/10/hello/attachment/world/trackback/'      => 'index.php?attachment=$matches[1]&tb=1',
					'/2014/3/2/hello/attachment/world/feed/rss/'        => 'index.php?attachment=$matches[1]&feed=$matches[2]',
					'/2014/4/20/hello/attachment/world/rss/'            => 'index.php?attachment=$matches[1]&feed=$matches[2]',
					'/2014/5/30/hello/attachment/world/comment-page-2/' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
					'/2014/1/31/hello/trackback/'                       => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&tb=1',
					'/2014/2/10/hello/feed/rss/'                        => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&feed=$matches[5]',
					'/2014/3/20/hello/rss/'                             => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&feed=$matches[5]',
					'/2014/4/30/hello/page/2/'                          => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&paged=$matches[5]',
					'/2014/5/31/hello/comment-page-2/'                  => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&cpage=$matches[5]',
					'/2014/10/5/hello/'                                 => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&page=$matches[5]',
					'/2014/10/5/hello/2'                                => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&page=$matches[5]',
					'/2014/6/2/hello/world/'                            => 'index.php?attachment=$matches[1]',
					'/2014/7/10/hello/world/trackback/'                 => 'index.php?attachment=$matches[1]&tb=1',
					'/2014/10/20/hello/world/feed/rss/'                 => 'index.php?attachment=$matches[1]&feed=$matches[2]',
					'/2014/11/30/hello/world/rss/'                      => 'index.php?attachment=$matches[1]&feed=$matches[2]',
					'/2014/12/31/hello/world/comment-page-2/'           => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
					// This one actually doesn't work. See Trac ticket #28156
					// '/2014/11/31/comment-page-123/'                  => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&cpage=$matches[4]',
					'/2014/12/comment-page-123/'                        => 'index.php?year=$matches[1]&monthnum=$matches[2]&cpage=$matches[3]',
					'/2014/comment-page-123/'                           => 'index.php?year=$matches[1]&cpage=$matches[2]',
				),
				'Pages' => array(
					'/hello/attachment/world/'                       => 'index.php?attachment=$matches[1]',
					'/parent/child/attachment/world/'                => 'index.php?attachment=$matches[1]',
					'/hello/attachment/world/trackback/'             => 'index.php?attachment=$matches[1]&tb=1',
					'/parent/child/attachment/world/trackback/'      => 'index.php?attachment=$matches[1]&tb=1',
					'/hello/attachment/world/feed/rss/'              => 'index.php?attachment=$matches[1]&feed=$matches[2]',
					'/parent/child/attachment/world/feed/rss/'       => 'index.php?attachment=$matches[1]&feed=$matches[2]',
					'/hello/attachment/world/feed/'                  => 'index.php?attachment=$matches[1]&feed=$matches[2]',
					'/parent/child/attachment/world/feed/'           => 'index.php?attachment=$matches[1]&feed=$matches[2]',
					'/hello/attachment/world/comment-page-2/'        => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
					'/parent/child/attachment/world/comment-page-2/' => 'index.php?attachment=$matches[1]&cpage=$matches[2]',
					'/hello/trackback/'                              => 'index.php?pagename=$matches[1]&tb=1',
					'/parent/child/trackback/'                       => 'index.php?pagename=$matches[1]&tb=1',
					'/hello/feed/rss2/'                              => 'index.php?pagename=$matches[1]&feed=$matches[2]',
					'/parent/child/feed/rss2/'                       => 'index.php?pagename=$matches[1]&feed=$matches[2]',
					'/hello/feed/'                                   => 'index.php?pagename=$matches[1]&feed=$matches[2]',
					'/parent/child/feed/'                            => 'index.php?pagename=$matches[1]&feed=$matches[2]',
					'/hello/page/2/'                                 => 'index.php?pagename=$matches[1]&paged=$matches[2]',
					'/parent/child/page/2/'                          => 'index.php?pagename=$matches[1]&paged=$matches[2]',
					'/hello/comment-page-2/'                         => 'index.php?pagename=$matches[1]&cpage=$matches[2]',
					'/parent/child/comment-page-2/'                  => 'index.php?pagename=$matches[1]&cpage=$matches[2]',
					'/hello/'                                        => 'index.php?pagename=$matches[1]&page=$matches[2]',
					'/hello/2'                                       => 'index.php?pagename=$matches[1]&page=$matches[2]',
					'/parent/child/'                                 => 'index.php?pagename=$matches[1]&page=$matches[2]',
					'/parent/child/2'                                => 'index.php?pagename=$matches[1]&page=$matches[2]',
				),
			);

			if ( has_action( 'init', 'rest_api_init' ) ) {
				$tests['REST API'] = array(
					'/' . rest_get_url_prefix() . '/'            => 'index.php?rest_route=/',
					'/' . rest_get_url_prefix() . '/v2/foo/bar/' => 'index.php?rest_route=/$matches[1]',
				);
			}

			if ( has_action( 'rest_api_init', 'wp_oembed_register_route' ) ) {
				$tests['Embeds'] = array(
					'/2014/10/5/hello/embed/'                  => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&embed=true',
					'/2014/10/5/hello/attachment/world/embed/' => 'index.php?attachment=$matches[1]&embed=true',
					'/parent/embed/'                           => 'index.php?pagename=$matches[1]&embed=true',
					'/parent/attachment/world/embed/'          => 'index.php?attachment=$matches[1]&embed=true',
					'/parent/child/embed/'                     => 'index.php?pagename=$matches[1]&embed=true',
				);
			}

			return apply_filters( 'rewrite_testing_tests', $tests );
		}


		/**
		 * Test our test cases against the rewrite rules
		 *
		 * @return array|object If successful, returns an array of the results. Otherwise, returns a WP_Error object
		 */
		function test() {
			require_once( dirname( __FILE__ ) . '/php/class-rewrite-testing-tests.php' );

			$this->summary = array();
			$tests = $this->test_cases();
			$results = array();
			$this->errors = 0;
			$wordpress_subdir_for_site = parse_url( home_url(), PHP_URL_PATH );

			foreach ( $tests as $group => $test_cases ) {
				foreach ( $test_cases as $path => $test ) {
					if ( ! is_array( $test ) ) {
						$test = array( 'match' => $test );
					}

					$result = array(
						'group'  => $group,
						'path'   => $path,
						'test'   => $test,
						'status' => '',
						'rule'   => '',
						'target' => '',
						'query'  => '',
					);

					// Setup our "match path" to run against the regex
					$match_path = parse_url( esc_url( $path ), PHP_URL_PATH );
					if ( ! empty( $wordpress_subdir_for_site ) ) {
						$match_path = str_replace( $wordpress_subdir_for_site, '', $match_path );
					}
					$match_path = ltrim( $match_path, '/' );

					// We're optimistic, so we'll assume success.
					$basic_unit_result = $extended_unit_result = true;

					// Run a basic match test if supplied
					if ( ! empty( $test['match'] ) ) {
						$basic_results = Rewrite_Testing_Tests()->basic_test( $match_path );
						if ( is_wp_error( $basic_results ) ) {
							return $basic_results;
						}
						list( $result['rule'], $result['target'] ) = $basic_results;
						$basic_unit_result = $test['match'] === $result['target'];
					}

					// Run an extended query test if supplied
					if ( ! empty( $test['query'] ) ) {
						$extended_results = Rewrite_Testing_Tests()->extended_test( $match_path );
						if ( is_wp_error( $extended_results ) ) {
							return $extended_results;
						}
						list( $result['rule'], $result['query'] ) = $extended_results;
						ksort( $result['query'] );
						ksort( $test['query'] );
						$result['test'] = $test;
						$extended_unit_result = $test['query'] === $result['query'];
					}

					if ( ! apply_filters( 'rewrite_testing_unit_test', ( $basic_unit_result && $extended_unit_result ), $test, $result ) ) {
						$this->errors++;
						$result['status'] = 'error';
						$this->summary['details'][] = $result;
					}
					$results[] = $result;
				}
			}

			if ( $this->errors ) {
				$this->summary['status'] = __( 'Failing', 'rewrite-testing' );
				$this->summary['passing'] = false;
				$this->summary['error_count'] = $this->errors;
			} else {
				$this->summary['status'] = __( 'Passing', 'rewrite-testing' );
				$this->summary['passing'] = true;
			}

			$this->summary['tested_rules'] = Rewrite_Testing_Tests()->get_tested();
			$this->summary['missed_rules'] = array_diff_key( (array) Rewrite_Testing_Tests()->get_rewrite_rules(), (array) Rewrite_Testing_Tests()->get_tested() );
			$this->summary['tested']       = count( $this->summary['tested_rules'] );
			$this->summary['missed']       = count( $this->summary['missed_rules'] );
			$this->summary['total']        = $this->summary['tested'] + $this->summary['missed'];
			$this->summary['coverage']     = floor( ( 100 / $this->summary['total'] ) * $this->summary['tested'] );

			set_transient( $this->transient_key, $this->summary );

			return $results;
		}


		/**
		 * Get the summary of the tests, preferably from the transient.
		 *
		 * @return array The test results summary.
		 */
		public function get_summary() {
			if ( false === ( $this->summary = get_transient( $this->transient_key ) ) ) {
				$this->test();
			}
			return $this->summary;
		}

		/**
		 * Clear the summary cache.
		 *
		 * @return void
		 */
		public function clear_cache() {
			delete_transient( $this->transient_key );
		}

		/**
		 * Register the debug bar component to this plugin.
		 *
		 * @param  array $panels Debug Bar panels.
		 * @return array
		 */
		public function debug_bar_panel( $panels ) {
			require_once( dirname( __FILE__ ) . '/php/class-debug-bar-rewrite-testing-panel.php' );
			$panels[] = new Debug_Bar_Rewrite_Testing_Panel();
			return $panels;
		}

		/**
		 * Set the classes for the debug bar. We want to make it a warning if out tests are failing.
		 *
		 * @param  array $classes
		 * @return array
		 */
		public function debug_bar_classes( $classes ) {
			$this->get_summary();
			if ( ! empty( $this->summary['error_count'] ) ) {
				$classes[] = 'debug-bar-php-warning-summary';
			}
			return $classes;
		}

		/**
		 * Add our test status to the debug bar statuses.
		 *
		 * @param  array $statuses
		 * @return array
		 */
		public function debug_bar_statuses( $statuses ) {
			$this->get_summary();
			$statuses[] = array( 'rewrite-testing', __( 'Rewrite Tests', 'rewrite-testing' ), $this->summary['status'] );
			return $statuses;
		}

	}

	function Rewrite_Testing() {
		return Rewrite_Testing::instance();
	}
	add_action( 'after_setup_theme', 'Rewrite_Testing' );

endif;

if ( defined( 'WP_CLI' ) && WP_CLI && is_readable( $wp_cli = dirname( __FILE__ ) . '/class-wp-cli.php' ) ) {
	include_once $wp_cli;
}
