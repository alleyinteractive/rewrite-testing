<?php

/**
 * Rewrite test utilities
 */

if ( ! class_exists( 'Rewrite_Testing_Tests' ) ) :

	class Rewrite_Testing_Tests {

		private static $instance;

		protected $basic_rewrite_rules = false;

		protected $extended_rewrite_rules = false;

		private function __construct() {
			/* Don't do anything, needs to be initialized via instance() method */
		}

		public function __clone() { wp_die( "Please don't __clone Rewrite_Testing_Tests" ); }

		public function __wakeup() { wp_die( "Please don't __wakeup Rewrite_Testing_Tests" ); }

		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new Rewrite_Testing_Tests;
				self::$instance->setup();
			}
			return self::$instance;
		}

		public function setup() {
			# initialize anything for the singleton here
		}

		public function basic_test( $request ) {
			if ( false === $this->basic_rewrite_rules ) {
				$this->basic_rewrite_rules = $this->get_rules();
				$this->tested = array();
				if ( is_wp_error( $this->basic_rewrite_rules ) ) {
					return $this->basic_rewrite_rules;
				} elseif ( empty( $this->basic_rewrite_rules ) ) {
					return new WP_Error( 'rt_empty_rules', __( 'The rewrite rules look to be missing. Try flushing or check your permalink settings.', 'rewrite-testing' ) );
				}
			}

			$target = $rule = false;
			// Loop through all the rewrite rules until we find a match
			foreach ( $this->basic_rewrite_rules as $rule => $maybe_target ) {
				if ( preg_match( "!^$rule!", $request, $matches ) ) {
					$target = $maybe_target['rewrite'];
					$this->tested[ $rule ] = $maybe_target;
					break;
				}
			}

			return array( $rule, $target );
		}

		public function get_tested() {
			return $this->tested;
		}

		public function get_basic_rewrite_rules() {
			return $this->basic_rewrite_rules;
		}

		public function extended_test( $request ) {
			global $wp_rewrite, $wp;

			$query_vars = array();
			$post_type_query_vars = array();

			if ( false === $this->extended_rewrite_rules ) {
				// Fetch the rewrite rules.
				$this->extended_rewrite_rules = $wp_rewrite->wp_rewrite_rules();
				if ( empty( $this->extended_rewrite_rules ) ) {
					return new WP_Error( 'rt_empty_rules', __( 'The rewrite rules look to be missing. Try flushing or check your permalink settings.', 'rewrite-testing' ) );
				}
			}

			// If we match a rewrite rule, this will be cleared.
			$error = '404';
			$this_did_permalink = true;

			// Look for matches.
			if ( empty( $request ) ) {
				// An empty request could only match against ^$ regex
				if ( isset( $this->extended_rewrite_rules['$'] ) ) {
					$matched_rule = '$';
					$query = $this->extended_rewrite_rules['$'];
					$matches = array( '' );
				}
			} else {
				foreach ( (array) $this->extended_rewrite_rules as $match => $query ) {
					if ( preg_match( "#^$match#", $request, $matches ) ||
						preg_match( "#^$match#", urldecode( $request ), $matches ) ) {

						if ( $wp_rewrite->use_verbose_page_rules && preg_match( '/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch ) ) {
							// this is a verbose page match, lets check to be sure about it
							if ( ! get_page_by_path( $matches[ $varmatch[1] ] ) ) {
						 		continue;
							}
						}

						// Got a match.
						$matched_rule = $match;
						break;
					}
				}
			}

			if ( isset( $matched_rule ) ) {
				// Trim the query of everything up to the '?'.
				$query = preg_replace( '!^.+\?!', '', $query );

				// Substitute the substring matches into the query.
				$query = addslashes( WP_MatchesMapRegex::apply( $query, $matches ) );

				// @todo: this isn't used elsewhere
				$matched_query = $query;

				// Parse the query.
				parse_str( $query, $perma_query_vars );

				// If we're processing a 404 request, clear the error var since we found something.
				if ( '404' == $error ) {
					unset( $error, $_GET['error'] );
				}
			}

			/**
			 * Filter the query variables whitelist before processing.
			 *
			 * Allows (publicly allowed) query vars to be added, removed, or changed prior
			 * to executing the query. Needed to allow custom rewrite rules using your own arguments
			 * to work, or any other custom query variables you want to be publicly available.
			 *
			 * @param array $public_query_vars The array of whitelisted query variables.
			 */
			$public_query_vars = apply_filters( 'query_vars', $wp->public_query_vars );

			foreach ( get_post_types( array(), 'objects' ) as $post_type => $t ) {
				if ( $t->query_var ) {
					$post_type_query_vars[ $t->query_var ] = $post_type;
				}
			}

			foreach ( $public_query_vars as $wpvar ) {
				if ( isset( $this_extra_query_vars[ $wpvar ] ) ) {
					$query_vars[ $wpvar ] = $this_extra_query_vars[ $wpvar ];
				} elseif ( isset( $perma_query_vars[ $wpvar ] ) ) {
					$query_vars[ $wpvar ] = $perma_query_vars[ $wpvar ];
				}

				if ( ! empty( $query_vars[ $wpvar ] ) ) {
					if ( ! is_array( $query_vars[ $wpvar ] ) ) {
						$query_vars[ $wpvar ] = (string) $query_vars[ $wpvar ];
					} else {
						foreach ( $query_vars[ $wpvar ] as $vkey => $v ) {
							if ( ! is_object( $v ) ) {
								$query_vars[ $wpvar ][ $vkey ] = (string) $v;
							}
						}
					}

					if ( isset( $post_type_query_vars[ $wpvar ] ) ) {
						$query_vars['post_type'] = $post_type_query_vars[ $wpvar ];
						$query_vars['name'] = $query_vars[ $wpvar ];
					}
				}
			}

			// Convert urldecoded spaces back into +
			foreach ( get_taxonomies( array() , 'objects' ) as $taxonomy => $t ) {
				if ( $t->query_var && isset( $query_vars[ $t->query_var ] ) ) {
					$query_vars[ $t->query_var ] = str_replace( ' ', '+', $query_vars[ $t->query_var ] );
				}
			}

			// Limit publicly queried post_types to those that are publicly_queryable
			if ( isset( $query_vars['post_type'] ) ) {
				$queryable_post_types = get_post_types( array( 'publicly_queryable' => true ) );
				if ( ! is_array( $query_vars['post_type'] ) ) {
					if ( ! in_array( $query_vars['post_type'], $queryable_post_types ) ) {
						unset( $query_vars['post_type'] );
					}
				} else {
					$query_vars['post_type'] = array_intersect( $query_vars['post_type'], $queryable_post_types );
				}
			}

			foreach ( $wp->private_query_vars as $var ) {
				if ( isset( $this_extra_query_vars[ $var ] ) ) {
					$query_vars[ $var ] = $this_extra_query_vars[ $var ];
				}
			}

			if ( isset( $error ) ) {
				$query_vars['error'] = $error;
			}

			/**
			 * Filter the array of parsed query variables.
			 *
			 * @param array $query_vars The array of requested query variables.
			 */
			$query_vars = apply_filters( 'request', $query_vars );

			return array( $matched_rule, $query_vars );
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
			if ( ! $rewrite_rules ) {
				$rewrite_rules = array();
			}
			// Track down which rewrite rules are associated with which methods by breaking it down
			$rewrite_rules_by_source = array();
			$rewrite_rules_by_source['post'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->permalink_structure, EP_PERMALINK );
			$rewrite_rules_by_source['date'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->get_date_permastruct(), EP_DATE );
			$rewrite_rules_by_source['root'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->root . '/', EP_ROOT );
			$rewrite_rules_by_source['comments'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->root . $wp_rewrite->comments_base, EP_COMMENTS, true, true, true, false );
			$rewrite_rules_by_source['search'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->get_search_permastruct(), EP_SEARCH );
			$rewrite_rules_by_source['author'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->get_author_permastruct(), EP_AUTHORS );
			$rewrite_rules_by_source['page'] = $wp_rewrite->page_rewrite_rules();

			// Extra permastructs including tags, categories, etc.
			foreach ( $wp_rewrite->extra_permastructs as $permastructname => $permastruct ) {
				if ( is_array( $permastruct ) ) {
					// Pre 3.4 compat
					if ( 2 == count( $permastruct ) ) {
						$rewrite_rules_by_source[ $permastructname ] = $wp_rewrite->generate_rewrite_rules( $permastruct[0], $permastruct[1] );
					} else {
						$rewrite_rules_by_source[ $permastructname ] = $wp_rewrite->generate_rewrite_rules( $permastruct['struct'], $permastruct['ep_mask'], $permastruct['paged'], $permastruct['feed'], $permastruct['forcomments'], $permastruct['walk_dirs'], $permastruct['endpoints'] );
					}
				} else {
					$rewrite_rules_by_source[ $permastructname ] = $wp_rewrite->generate_rewrite_rules( $permastruct, EP_NONE );
				}
			}

			// Apply the filters used in core just in case
			foreach ( $rewrite_rules_by_source as $source => $rules ) {
				$rewrite_rules_by_source[ $source ] = apply_filters( $source . '_rewrite_rules', $rules );
				if ( 'post_tag' == $source ) {
					$rewrite_rules_by_source[ $source ] = apply_filters( 'tag_rewrite_rules', $rules );
				}
			}

			foreach ( $rewrite_rules as $rule => $rewrite ) {
				$rewrite_rules_array[ $rule ]['rewrite'] = $rewrite;
				foreach ( $rewrite_rules_by_source as $source => $rules ) {
					if ( array_key_exists( $rule, $rules ) ) {
						$rewrite_rules_array[ $rule ]['source'] = $source;
					}
				}
				if ( ! isset( $rewrite_rules_array[ $rule ]['source'] ) ) {
					$rewrite_rules_array[ $rule ]['source'] = apply_filters( 'rewrite_rules_inspector_source', 'other', $rule, $rewrite );
				}
			}

			// Find any rewrite rules that should've been generated but weren't
			$maybe_missing = $wp_rewrite->rewrite_rules();
			$missing_rules = array();
			foreach ( $maybe_missing as $rule => $rewrite ) {
				if ( ! array_key_exists( $rule, $rewrite_rules_array ) ) {
					$missing_rules[ $rule ] = array(
						'rewrite' => $rewrite,
					);
				}
			}
			if ( ! empty( $missing_rules ) ) {
				// We have missing rules! Abort the tests with an instance of WP_Error
				return new WP_Error( 'rt_missing_rules', __( "The site's rewrite rules need to be flushed", 'rewrite-testing' ), $missing_rules );
			}

			// Set the sources used in our filtering
			$sources = array( 'all' );
			foreach ( $rewrite_rules_array as $rule => $data ) {
				$sources[] = $data['source'];
			}
			$this->sources = array_unique( $sources );

			// Return our array of rewrite rules to be used
			return $rewrite_rules_array;
		}
	}

	function Rewrite_Testing_Tests() {
		return Rewrite_Testing_Tests::instance();
	}
	add_action( 'after_setup_theme', 'Rewrite_Testing_Tests' );

endif;