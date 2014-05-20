=== Rewrite Rule Testing ===
Contributors: mboynes, alleyinteractive
Donate link: url
Tags: permalinks, rewrite rules, tests, testing
Requires at least: 3.9
Tested up to: 3.9
Stable tag: 0.1.1

Unit test your rewrite rules from the WordPress Admin.

== Description ==

This plugin provides a simple interface for testing your custom rewrite rules.

The purpose of this plugin is to be able to test your *own* rewrite rules, so
you're probably most interested in knowing how to do that, right? The plugin
provides a filter, `rewrite_testing_tests` to add your own tests. That filter
passes an associative array of name => tests. The tests array is an associative
array of URI => expected match. In the outer array, the "name" is arbitrary and
for your own reference. In the inner array, the "URI" is the path you want to
test, and the "expected match" is what WordPress should find as a rewrite
match.

Enough chit-chat, here's an example:

    function my_rewrite_tests( $tests ) {
      return array(
        'Events' => array(
          '/event/super-bowl/' => 'index.php?event=$matches[1]',
          '/event/super-bowl/page/2/' => 'index.php?event=$matches[1]&paged=$matches[2]'
        )
      );
    }
    add_filter( 'rewrite_testing_tests', 'my_rewrite_tests' );

You can see the `test_cases()` method for a full suite of tests for the "Day
and Name" permalink structure. It's not necessary to leave these in (in fact,
the above demo would wipe them out), unless you want to make sure that your
custom rewrites aren't affecting core rewrites. If you aren't using "Day and
Name" permalinks, you'll need to adjust the tests to fit your permalink
structure.


== Todo ==

* Add a debug bar extension which reads a transient; the transient would be
  updated whenever rewrite rules are flushed. The debug bar extension would
  show pass/fail status and link directly to the settings page.
* Add tests for other permalink structures?
* Add a way to run this as part of phpunit


== Installation ==

1. Upload to the /wp-content/plugins/ directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Tools &rarr; Rewrite Testing

== Frequently Asked Questions ==

= I installed this and my tests failed, what gives? =

Out-of-the-box, this plugin is setup to test core rewrites for the "Day and
Name" permalink structure. The purpose of this plugin is not to test core
rewrites; these are just to serve as a demonstration.

== Screenshots ==

1. Sample output of passing rules
2. Sample output of failing rules

== Changelog ==

= 0.1.1 =
Cosmetic updates

= 0.1 =
Initial release. Enjoy!