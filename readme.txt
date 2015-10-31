=== Rewrite Rule Testing ===
Contributors: mboynes, johnbillion, alleyinteractive
Tags: permalinks, rewrite rules, tests, testing
Requires at least: 3.9
Tested up to: 4.3
Stable tag: 1.0.0

Unit test your rewrite rules from the WordPress Admin or Debug Bar.

== Description ==

This plugin provides a simple interface for testing your custom rewrite rules.

The purpose of this plugin is to be able to test your *own* rewrite rules, so
you're probably most interested in knowing how to do that, right? The plugin
provides a filter, `rewrite_testing_tests` to add your own tests. That filter
passes an associative array of name => tests. The tests array is an associative
array of URI => unit test. In the outer array, the "name" is arbitrary and for
your own reference. In the inner array, the "URI" is the path you want to test,
and the unit test is what WordPress should find as a rewrite match and/or an
array of resulting query vars.

= Basic Testing =

Enough chit-chat, here's an example:

    function my_rewrite_tests( $tests ) {
      return array(
        'Events' => array(
          '/event/super-bowl/' => 'index.php?event=$matches[1]',
          '/event/super-bowl/page/2/' => 'index.php?event=$matches[1]&paged=$matches[2]',
        ),
      );
    }
    add_filter( 'rewrite_testing_tests', 'my_rewrite_tests' );

You can see the `Rewrite_Testing::test_cases()` method for a full suite of tests
for the "Day and Name" permalink structure. It's not necessary to leave these in
(in fact, the above demo would wipe them out), unless you want to make sure that
your custom rewrites aren't affecting core rewrites. If you aren't using "Day
and Name" permalinks, you'll need to adjust the tests to fit your permalink
structure.

= Enhanced Testing =

Sometimes, the above level of testing isn't thorough enough. Perhaps you need to
verify that a URL doesn't just match, but also parses correctly. For this
scenario, there is an enhanced level of testing where you test against query
vars and/or rewrite matches. Here are a couple examples of this:

    function my_rewrite_tests( $tests ) {
      return array(
        'Events' => array(
          '/event/super-bowl/' => array(
            'match' => 'index.php?event=$matches[1]',
            'query' => array( 'name' => 'super-bowl', 'post_type' => 'event' ),
          ),
          '/event/super-bowl/print/' => array(
            'query' => array( 'name' => 'super-bowl', 'post_type' => 'event', 'print_view' => '1' ),
          ),
        ),
      );
    }
    add_filter( 'rewrite_testing_tests', 'my_rewrite_tests' );

The query vars are parsed just as WordPress would parse them, up to and
including the `"request"` filter.

= Test-Driven Development =

I've found this plugin most useful when I write my tests before I start on my
rewrite rules. This allows me to sit back and think of the "big picture" of what
my urls should look like before I start coding them. Once I have a full suite of
failing tests, the rewrite rules practically write themselves.

= Debug Bar Add-on =

If you also have the [Debug Bar plugin](https://wordpress.org/plugins/debug-bar/)
active, this plugin will use that to report failing rules to you as soon as
they happen. The tests will only run when rules are generated (that is, when
your rules get flushed), and if they fail, the debug bar button will turn red.


== Contributing ==

This plugin is [maintained in GitHub](https://github.com/alleyinteractive/rewrite-testing).
If you find a bug, it's best to file an issue there than via the WordPress.org
support forums.


== Installation ==

1. Upload to the /wp-content/plugins/ directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Tools &rarr; Rewrite Testing


== Frequently Asked Questions ==

= I installed this and it says the tests failed, what gives? =

Out-of-the-box, this plugin is setup to test core rewrites for the "Day and
Name" permalink structure. The purpose of this plugin is not to test core
rewrites; these are just to serve as a demonstration.


== Screenshots ==

1. Sample output of passing rules
2. Sample output of failing rules


== Changelog ==

= 1.0.0 =

* Adds WP-CLI command
* Adds test coverage report
* Several bug fixes
* Improved compliance with WordPress Coding Standards

= 0.2.1 =

* Don't require query-based matches to be in a specific order
* Minor bug fix
* Improved escaping
* Improved compliance with WordPress Coding Standards

= 0.2 =

* Adds debug bar panel/status for CI(ish)
* Adds "enhanced" query-based rewrite tests
* Adds filters for providing custom test routines.
* Adds support for customized category/tag base (props johnbillion)

= 0.1.1 =

* Cosmetic updates

= 0.1 =

* Initial release. Enjoy!
