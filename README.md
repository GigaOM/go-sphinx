# === Gigaom Sphinx ===

Contributors: wluo, okredo, misterbisson

Tags: wpquery, keyword search, sphinx, full text searching, fulltext, taxonomy queries, performance

Requires at least: 3.7

Tested up to: 4.0

Stable tag: trunk

Improve WP Query performance using Sphinx.

## == Description ==

<a href="http://sphinxsearch.com/">Sphinx</a> is a blazing fast index of content. This plugin makes it easy get WordPress posts into Sphinx, then query them using <a href="http://codex.wordpress.org/Class_Reference/WP_Query">standard WP Query</a>. This approach improves the performance of queries in core WordPress and even in plugins without having to implement a new API or rewrite the queries.

See it in use at <a href="http://search.gigaom.com/">search.gigaom.com</a>. Each search result is a post in WordPress, Sphinx does the work to find the matching results. The filters in the sidebar are powered by <a href="https://github.com/misterbisson/scriblio">Scriblio</a> based on taxonomy data on each post.

### = What it accellerates =

The plugin accellerates most WP Queries, including search, tag/taxonomy, author, and date, among others. The full list of supported query vars is:

1. author
1. author_name
1. authors
1. category__and
1. category__in
1. category__not_in
1. exclude
1. feed
1. fields
1. ignore_sticky_posts
1. include
1. no_found_rows
1. numberposts
1. numberposts
1. offset
1. order
1. orderby
1. output
1. paged
1. post__in
1. post__not_in
1. post_parent
1. post_status
1. post_type
1. posts_per_page
1. s
1. suppress_filters
1. tag
1. tag__and
1. tag__in
1. tag__not_in
1. tag_id
1. tag_slug__and
1. tag_slug__in
1. tax_query
1. wijax

See those <a href="https://github.com/GigaOM/go-sphinx/blob/master/components/class-go-sphinx.php#L45">in the code</a>.

### = What it doesn't =

One of the only class of queries _not_ supported are queries against metadata. Those are ignored by this plugin and WP Query handles those as usual.

go-sphinx automatically detects if other plugins have modified the SQL outside WP Query and steps out of the way if so. That allows the query to execute against the MySQL database as usual, but without the performance benefits of Sphinx.

### = In the WordPress.org plugin repo =

Eventually: https://wordpress.org/plugins/go-sphinx/

### = Fork me! =

This plugin is on Github: https://github.com/gigaOM/go-sphinx

## == Installation ==

1. Install <a href="http://sphinxsearch.com/docs/current/">Sphinx</a>.
1. Install and activate this plugin.
1. Go to your WordPress dashboard -> Settings -> Sphinx where you'll find a sample Sphinx config file with the paramters to index the blog content.
1. Use the configuration template to start indexing in Sphinx.
1. Enjoy the performance boost.

Note: the plugin expects the Sphinx server IP:port to be `127.0.0.1:9306`. For now, changing that requires <a href="https://github.com/GigaOM/go-sphinx/blob/master/components/class-go-sphinx.php#L148">filtering `go_config`</a> to replace <a href="https://github.com/GigaOM/go-sphinx/blob/master/components/class-go-sphinx.php#L158">the defaults</a>.
