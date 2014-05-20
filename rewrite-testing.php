<?php

/*
	Plugin Name: Rewrite Rule Testing
	Plugin URI: https://github.com/alleyinteractive/rewrite-testing
	Description: Unit test your rewrite rules
	Version: 0.1.1
	Author: Matthew Boynes
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

if ( !class_exists( 'Rewrite_Testing' ) ) :

class Rewrite_Testing {

	private static $instance;

	private $errors = 0;

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() { wp_die( "Please don't __clone Rewrite_Testing" ); }

	public function __wakeup() { wp_die( "Please don't __wakeup Rewrite_Testing" ); }

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
		if ( isset( $_GET['page'], $_GET['action'] ) && 'rewrite-testing' == $_GET['page'] && 'flush-rules' == $_GET['action'] )
			add_action( 'admin_init', array( $this, 'flush_rules' ) );
		elseif ( isset( $_GET['page'], $_GET['message'] ) && 'rewrite-testing' == $_GET['page'] && 'flush-success' == $_GET['message'] )
			add_action( 'admin_notices', array( $this, 'action_admin_notices' ) );
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
		echo '<div class="message updated"><p>' . __( 'Rewrite rules flushed.', 'rewrite-testing' ) . '</p></div>';
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
			#rt_test_results tr.error {
				background-color: #f7a8a9;
			}
			#rt_test_results tr.error td {
				border-top-color: #FECFD0;
				border-bottom-color: #f99b9d;
			}
		</style>
		<div class="wrap">
			<h2><?php esc_html_e( 'Test & Flush Rewrite Rules', 'rewrite-testing' ); ?></h2>

			<p><a title="<?php esc_attr_e( 'Flush your rewrite rules to regenerate them', 'rewrite-testing' ); ?>" class="button-secondary" href="<?php echo esc_url( $flush_url ); ?>"><?php esc_html_e( 'Flush Rewrite Rules', 'rewrite-testing' ); ?></a></p>

			<?php
			$results = $this->test();
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
							<?php foreach( (array) $rules as $rule => $rewrite ) : ?>
							<tr>
								<td><?php echo esc_html( $rule ) ?></td>
								<td><?php echo esc_html( $rewrite['rewrite'] ) ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php else : ?>
				<h3>Test Results</h3>
				<?php if ( $this->errors ) : ?>
					<div class="message error">
						<p><?php printf( _n( '1 test failed!', '%d tests failed!', $this->errors, 'rewrite-testing' ), $this->errors ); ?></p>
					</div>
				<?php else : ?>
					<div class="message updated">
						<p><?php esc_html_e( 'All tests are passing', 'rewrite-testing' ) ?></p>
					</div>
				<?php endif ?>

				<table class="wp-list-table widefat">
					<thead>
						<tr>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Group', 'rewrite-testing' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Status', 'rewrite-testing' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Test Path', 'rewrite-testing' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Expected Results', 'rewrite-testing' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Actual Results', 'rewrite-testing' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Rewrite Rule Matched', 'rewrite-testing' ); ?></th>
						</tr>
					</thead>
					<tbody id="rt_test_results">
						<?php foreach ( $results as $row ) : ?>
							<tr class="<?php echo esc_attr( $row['status'] ) ?>">
								<td><?php echo esc_html( $row['group'] ) ?></td>
								<?php if ( 'error' == $row['status'] ) : ?>
									<td><?php esc_html_e( 'Failed!', 'rewrite-testing' ) ?></td>
								<?php else : ?>
									<td><?php esc_html_e( 'Passed', 'rewrite-testing' ) ?></td>
								<?php endif ?>
								<td><?php echo esc_html( $row['path'] ) ?></td>
								<td><?php echo esc_html( $row['test'] ) ?></td>
								<td><?php echo esc_html( $row['target'] ) ?></td>
								<td><?php echo esc_html( $row['rule'] ) ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
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
		if ( !current_user_can( 'manage_options' ) )
			wp_die( __( 'You do not have permissions to perform this action.' ) );

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
		// Array of arrays of path => should match
		return apply_filters( 'rewrite_testing_tests', array(
			'Categories' => array(
				'/category/uncategorized/feed/atom/' => 'index.php?category_name=$matches[1]&feed=$matches[2]',
				'/category/parent/child/feed/rss'    => 'index.php?category_name=$matches[1]&feed=$matches[2]',
				'/category/uncategorized/atom/'      => 'index.php?category_name=$matches[1]&feed=$matches[2]',
				'/category/parent/child/feed'        => 'index.php?category_name=$matches[1]&feed=$matches[2]',
				'/category/uncategorized/page/345'   => 'index.php?category_name=$matches[1]&paged=$matches[2]',
				'/category/parent/child/page2'       => 'index.php?category_name=$matches[1]&paged=$matches[2]',
				'/category/uncategorized/'           => 'index.php?category_name=$matches[1]',
				'/category/parent/child'             => 'index.php?category_name=$matches[1]',
			),

			'Tags' => array(
				'/tag/hello/feed/atom/' => 'index.php?tag=$matches[1]&feed=$matches[2]',
				'/tag/hello/feed/'      => 'index.php?tag=$matches[1]&feed=$matches[2]',
				'/tag/hello/page/123'   => 'index.php?tag=$matches[1]&paged=$matches[2]',
				'/tag/hello/'           => 'index.php?tag=$matches[1]',
			),

			'Post Type' => array(
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
				'/search/hello/feed/atom/'  => 'index.php?s=$matches[1]&feed=$matches[2]',
				'/search/hello/world/feed/' => 'index.php?s=$matches[1]&feed=$matches[2]',
				'/search/hello/page/123'    => 'index.php?s=$matches[1]&paged=$matches[2]',
				'/search/hello/'            => 'index.php?s=$matches[1]',
			),

			'Authors' => array(
				'/author/hello/feed/atom/' => 'index.php?author_name=$matches[1]&feed=$matches[2]',
				'/author/hello/feed/'      => 'index.php?author_name=$matches[1]&feed=$matches[2]',
				'/author/hello/page/123'   => 'index.php?author_name=$matches[1]&paged=$matches[2]',
				'/author/hello/'           => 'index.php?author_name=$matches[1]',
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
			)
		) );
	}


	/**
	 * Test our test cases against the rewrite rules
	 *
	 * @return array|object If successful, returns an array of the results. Otherwise, returns a WP_Error object
	 */
	function test() {
		$tests = $this->test_cases();
		$rewrite_rules_array = $this->get_rules();
		if ( is_wp_error( $rewrite_rules_array ) ) {
			return $rewrite_rules_array;
		} elseif ( empty( $rewrite_rules_array) ) {
			return new WP_Error( 'rt_empty_rules', 'The rewrite rules look to be missing. Try flushing or check your permalink settings.' );
		}
		$results = array();

		foreach ( $tests as $group => $test_cases ) {
			foreach ( $test_cases as $path => $match ) {
				$result = array(
					'group'  => $group,
					'path'   => $path,
					'test'   => $match,
					'status' => '',
					'rule'   => '',
					'target' => '',
				);
				// Setup our "match path" to run against the regex
				$match_path = parse_url( esc_url( $path ), PHP_URL_PATH );
				$wordpress_subdir_for_site = parse_url( home_url(), PHP_URL_PATH );
				if ( ! empty( $wordpress_subdir_for_site ) ) {
					$match_path = str_replace( $wordpress_subdir_for_site, '', $match_path );
				}
				$match_path = ltrim( $match_path, '/' );

				$target = false;
				// Loop through all the rewrite rules until we find a match
				foreach( $rewrite_rules_array as $rule => $maybe_target ) {
					if ( preg_match( "!^$rule!", $match_path, $matches ) ) {
						$target = $maybe_target['rewrite'];
						break;
					}
				}
				$result['rule'] = $rule;
				$result['target'] = $target;

				if ( $match !== $target ) {
					$this->errors++;
					$result['status'] = 'error';
				}
				$results[] = $result;
			}
		}

		return $results;
	}


	/**
	 * Generate a list of rewrite rules and check to ensure everything is present
	 *
	 * @return array|object If everything is successful, returns an array of rewrite rules. Otherwise returns a WP_Error object
	 */
	public function get_rules() {
		global $wp_rewrite;

		$rewrite_rules_array = array();
		$rewrite_rules = get_option( 'rewrite_rules' );
		if ( !$rewrite_rules )
			$rewrite_rules = array();
		// Track down which rewrite rules are associated with which methods by breaking it down
		$rewrite_rules_by_source = array();
		$rewrite_rules_by_source['post'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->permalink_structure, EP_PERMALINK );
		$rewrite_rules_by_source['date'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->get_date_permastruct(), EP_DATE );
		$rewrite_rules_by_source['root'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->root . '/', EP_ROOT );
		$rewrite_rules_by_source['comments'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->root . $wp_rewrite->comments_base, EP_COMMENTS, true, true, true, false );
		$rewrite_rules_by_source['search'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->get_search_permastruct(), EP_SEARCH );
		$rewrite_rules_by_source['author'] = $wp_rewrite->generate_rewrite_rules($wp_rewrite->get_author_permastruct(), EP_AUTHORS );
		$rewrite_rules_by_source['page'] = $wp_rewrite->page_rewrite_rules();

		// Extra permastructs including tags, categories, etc.
		foreach ( $wp_rewrite->extra_permastructs as $permastructname => $permastruct ) {
			if ( is_array( $permastruct ) ) {
				// Pre 3.4 compat
				if ( count( $permastruct ) == 2 )
					$rewrite_rules_by_source[$permastructname] = $wp_rewrite->generate_rewrite_rules( $permastruct[0], $permastruct[1] );
				else
					$rewrite_rules_by_source[$permastructname] = $wp_rewrite->generate_rewrite_rules( $permastruct['struct'], $permastruct['ep_mask'], $permastruct['paged'], $permastruct['feed'], $permastruct['forcomments'], $permastruct['walk_dirs'], $permastruct['endpoints'] );
			} else {
				$rewrite_rules_by_source[$permastructname] = $wp_rewrite->generate_rewrite_rules( $permastruct, EP_NONE );
			}
		}

		// Apply the filters used in core just in case
		foreach( $rewrite_rules_by_source as $source => $rules ) {
			$rewrite_rules_by_source[$source] = apply_filters( $source . '_rewrite_rules', $rules );
			if ( 'post_tag' == $source )
				$rewrite_rules_by_source[$source] = apply_filters( 'tag_rewrite_rules', $rules );
		}

		foreach( $rewrite_rules as $rule => $rewrite ) {
			$rewrite_rules_array[$rule]['rewrite'] = $rewrite;
			foreach( $rewrite_rules_by_source as $source => $rules ) {
				if ( array_key_exists( $rule, $rules ) ) {
					$rewrite_rules_array[$rule]['source'] = $source;
				}
			}
			if ( !isset( $rewrite_rules_array[$rule]['source'] ) )
				$rewrite_rules_array[$rule]['source'] = apply_filters( 'rewrite_rules_inspector_source', 'other', $rule, $rewrite );
		}

		// Find any rewrite rules that should've been generated but weren't
		$maybe_missing = $wp_rewrite->rewrite_rules();
		$missing_rules = array();
		foreach( $maybe_missing as $rule => $rewrite ) {
			if ( !array_key_exists( $rule, $rewrite_rules_array ) ) {
				$missing_rules[ $rule ] = array(
					'rewrite' => $rewrite
				);
			}
		}
		if ( !empty( $missing_rules ) ) {
			// We have missing rules! Abort the tests with an instance of WP_Error
			return new WP_Error( 'rt_missing_rules', __( "The site's rewrite rules need to be flushed", 'rewrite-testing' ), $missing_rules );
		}

		// Set the sources used in our filtering
		$sources = array( 'all' );
		foreach( $rewrite_rules_array as $rule => $data ) {
			$sources[] = $data['source'];
		}
		$this->sources = array_unique( $sources );

		// Return our array of rewrite rules to be used
		return $rewrite_rules_array;
	}

}

function Rewrite_Testing() {
	return Rewrite_Testing::instance();
}
add_action( 'after_setup_theme', 'Rewrite_Testing' );

endif;