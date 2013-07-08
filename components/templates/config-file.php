source <?php echo $c->name; ?>

{
	type            = mysql

	sql_host        = <?php echo $c->host; ?>

	sql_user        = <?php echo $c->user; ?>

	sql_pass        = <?php echo $c->pass; ?>

	sql_db          = <?php echo $c->db; ?>

	sql_port        = 3306	# Actually, this isn't set dynamically yet, override this if you know better

	sql_query_range	= SELECT MIN( ID ), MAX( ID ) FROM <?php echo $c->posts_table; ?>

	sql_range_step = 1000
	sql_query = \
		SELECT \
			p.ID, \
			p.post_content, \
			s.content, \
			UNIX_TIMESTAMP( p.post_date_gmt ) AS post_date_gmt, \
			UNIX_TIMESTAMP( p.post_modified_gmt ) AS post_modified_gmt, \
			p.post_author, \
			p.post_status, \
			p.post_type, \
			p.comment_count, \
			p.post_parent, \
			GROUP_CONCAT( term_taxonomy_id ) AS tt_id \
		FROM <?php echo $c->posts_table; ?> p \
		LEFT JOIN <?php echo $c->search_table; ?> s ON p.ID = s.post_id \
		LEFT JOIN <?php echo $c->term_relationships_table; ?> tr ON p.ID = tr.object_id \
		WHERE ID >= $start AND \
			ID <= $end \
		GROUP BY p.ID

	sql_attr_timestamp  = post_date_gmt
	sql_attr_timestamp  = post_modified_gmt
	sql_attr_uint       = post_author
	sql_field_string    = post_status
	sql_field_string    = post_type
	sql_attr_uint       = comment_count
	sql_attr_uint       = post_parent
	sql_attr_multi      = uint tt_id from field; \

	sql_query_info  = SELECT * FROM <?php echo $c->posts_table; ?> WHERE ID = $id
}


index <?php echo $c->name; ?>

{
	source          = <?php echo $c->name; ?>

	path            = /var/lib/sphinx/<?php echo $c->name; ?>

	docinfo         = extern
	charset_type    = utf-8
}


source <?php echo $c->secondary_index; ?>

{
	type            = mysql

	sql_host        = <?php echo $c->host; ?>

	sql_user        = <?php echo $c->user; ?>

	sql_pass        = <?php echo $c->pass; ?>

	sql_db          = <?php echo $c->db; ?>

	sql_port        = 3306	# Actually, this isn't set dynamically yet, override this if you know better

	sql_query_range	= SELECT MIN( ID ), MAX( ID ) FROM <?php echo $c->posts_table; ?>

	sql_range_step = 1000
	sql_query = \
		SELECT \
			p.ID, \
			p.post_content, \
			s.content, \
			UNIX_TIMESTAMP( p.post_date_gmt ) AS post_date_gmt, \
			UNIX_TIMESTAMP( p.post_modified_gmt ) AS post_modified_gmt, \
			p.post_author, \
			p.post_status, \
			p.post_type, \
			p.comment_count, \
			p.post_parent, \
			GROUP_CONCAT( term_taxonomy_id ) AS tt_id \
		FROM <?php echo $c->posts_table; ?> p \
		LEFT JOIN <?php echo $c->search_table; ?> s ON p.ID = s.post_id \
		LEFT JOIN <?php echo $c->term_relationships_table; ?> tr ON p.ID = tr.object_id \
		WHERE ID >= $start \
			AND ID <= $end \
			AND DATE( p.post_modified ) = CURDATE() \
		GROUP BY p.ID

	sql_attr_timestamp  = post_date_gmt
	sql_attr_timestamp  = post_modified_gmt
	sql_attr_uint       = post_author
	sql_field_string    = post_status
	sql_field_string    = post_type
	sql_attr_uint       = comment_count
	sql_attr_uint       = post_parent
	sql_attr_multi      = uint tt_id from field; \

	sql_query_info  = SELECT * FROM <?php echo $c->posts_table; ?> WHERE ID = $id
}


index <?php echo $c->secondary_index; ?>

{
	source          = <?php echo $c->secondary_index; ?>

	path            = /var/lib/sphinx/<?php echo $c->secondary_index; ?>

	docinfo         = extern
	charset_type    = utf-8
}


indexer
{
	mem_limit       = 128M
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
