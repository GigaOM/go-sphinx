source wp
{
	type            = mysql

	sql_host        = localhost
	sql_user        = wp
	sql_pass        =
	sql_db          = wp
	sql_port        = 3306	# optional, default is 3306

	sql_query_range	= SELECT MIN( ID ), MAX( ID ) FROM wp_1_posts
	sql_range_step = 1000
	sql_query = \
		SELECT p.ID, p.post_content, s.content, UNIX_TIMESTAMP( p.post_date_gmt ) AS post_date_gmt, UNIX_TIMESTAMP( p.post_modified_gmt ) AS post_modified_gmt, p.post_author, p.post_status, p.post_type, p.comment_count, p.post_parent, GROUP_CONCAT( term_taxonomy_id ) AS tt_id \
		FROM wp_1_posts p \
		LEFT JOIN wp_1_bcms_search s ON p.ID = s.post_id \
		LEFT JOIN wp_1_term_relationships tr ON p.ID = tr.object_id \
		WHERE ID >= $start AND ID <= $end \
		GROUP BY p.ID

	sql_attr_timestamp  = post_date_gmt
	sql_attr_timestamp  = post_modified_gmt
	sql_attr_uint       = post_author
	sql_attr_string     = post_status
	sql_attr_string     = post_type
	sql_attr_uint       = comment_count
	sql_attr_uint       = post_parent
	sql_attr_multi      = uint tt_id from field; \

	sql_query_info  = SELECT * FROM wp_1_posts WHERE ID=$id
}


index wp
{
	source          = wp
	path            = /var/lib/sphinx/wp
	docinfo         = extern
	charset_type    = utf-8
}


indexer
{
	mem_limit		= 128M
}


searchd
{
	listen          = 9312
	listen          = 9306:mysql41
	log             = /var/log/sphinx/searchd.log
	query_log       = /var/log/sphinx/query.log
	read_timeout    = 5
	max_children    = 30
	pid_file        = /var/run/sphinx/searchd.pid
	max_matches     = 1000
	seamless_rotate = 1
	preopen_indexes = 1
	unlink_old      = 1
	workers         = threads # for RT to work
	binlog_path     = /var/lib/sphinx/
}
