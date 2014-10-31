# === Gigaom New Relic ===

Contributors: wluo, okredo, misterbisson

Tags: wpquery, keyword search, sphinx, full text searching, fulltext, taxonomy queries, performance

Requires at least: 3.7

Tested up to: 4.0

Stable tag: trunk

Improve WP Query performance using Sphinx.

## == Description ==

<a href="http://sphinxsearch.com/">Sphinx</a> is a blazing fast index of content. This plugin makes it easy get WordPress posts into Sphinx, then query them using <a href="http://codex.wordpress.org/Class_Reference/WP_Query">standard WP Query</a>. This approach improves the performance of other plugins and core WordPress without rewriting queries.

See it in use at <a href="http://search.gigaom.com/">search.gigaom.com</a>. Each search result is a post in WordPress, Sphinx does the work to find the matching results. The filters in the sidebar are powered by <a href="https://github.com/misterbisson/scriblio">Scriblio</a> based on taxonomy data on each post.

The plugin accellerates most WP Queries, including search, tag/taxonomy, author, and date, among others. One of the only class of queries _not_ supported are queries against metadata. The plugin can automatically detect if other plugins have modified the SQL outside WP Query and steps out of the way if so.

### = In the WordPress.org plugin repo =

Eventually: https://wordpress.org/plugins/go-sphinx/

### = Fork me! =

This plugin is on Github: https://github.com/gigaOM/go-sphinx

## == Installation ==

1. Install and activate the plugin.
