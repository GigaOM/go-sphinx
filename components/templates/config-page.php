<div class="wrap">
	<?php screen_icon( 'tools' ); ?>
	<h2>GigaOM Sphinx Integration</h2>

	<h3>Sample config file with connection parameters</h3>
	<textarea style="width:100%; height:200px;font-family:monospace;"><?php require __DIR__ . '/config-file.php'; ?></textarea>

	<h3>Command-line helpers</h3>
	<ol>
		<li>
			<p>Do the initial index</p>
			<p><code>sudo -u sphinx indexer <?php echo $c->name; ?></code></p>
			<p>(Note that there's no <code>--rotate</code> flag.)</p>
		</li>

		<li>
			<p>Update an existing index</p>
			<p><code>sudo -u sphinx indexer --rotate <?php echo $c->name; ?></code></p>
			<p><a href="http://sphinxsearch.com/docs/current.html#ref-indexer">Here are the complete docs on <code>indexer</code></a></p>
		</li>

		<li>
			<p>Do a search</p>
			<p><code>sudo -u sphinx search -i <?php echo $c->name; ?> -q SEARCHTERM</code></p>
			<p><a href="http://sphinxsearch.com/docs/current.html#ref-search">Here are the complete docs on <code>search</code></a></p>
		</li>
	</ol>

</div>