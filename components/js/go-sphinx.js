
// add a label to the search results so we know how the query was executed
//
var top_divs = document.querySelectorAll(".context-top");
var top_div = null;
if ( 0 < top_divs.length ) {
	top_div = top_divs[0];
}

if ( null !== top_div ) {
	var results_text = top_div.innerHTML;

	if ( 'undefined' === typeof sphinx_results ) {
		results_text = results_text.replace( 'results', '(wp_query) results' );
	} else {
		results_text = results_text.replace( 'results', '(sphinx) results' );
		results_text = results_text.replace( 'terms:', 'terms in ' + sphinx_results['elapsed_time'] + ' seconds:' );

		var sphinx_equality_div = document.createElement('p');
		if ( sphinx_results['posts_equality'] ) {
			var sphinx_equality = document.createTextNode( 'Sphinx and MySQL results exaclty match' );
		} else {
			var sphinx_equality = document.createTextNode( 'Sphinx and MySQL DO NOT MATCH' );
		}
		sphinx_equality_div.appendChild( sphinx_equality );

		// insert the original wp_query before top_div
		var wp_query_div = document.createElement( 'p' );

		if ( 'undefined' !== typeof sphinx_results['wp_request'] ) {
			var query_comment = document.createTextNode( 'original query: ' + sphinx_results['wp_request'] );
			wp_query_div.appendChild( query_comment );
		}//end if

		var searcheditor = document.getElementById( 'scrib_searcheditor-3' );
		if ( searcheditor ) {
			var br = document.createElement( 'br' );

			searcheditor.parentNode.insertBefore( wp_query_div, searcheditor );
			searcheditor.parentNode.insertBefore( br, searcheditor );

			searcheditor.parentNode.insertBefore( sphinx_equality_div, searcheditor );
			searcheditor.parentNode.insertBefore( br, searcheditor );
		}
		console.log( sphinx_results );
	}//end else

	top_div.innerHTML = results_text;
}//end if
