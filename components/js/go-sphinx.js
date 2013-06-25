
// add a label to the search results so we know how the query was executed
//
var top_divs = document.querySelectorAll(".context-top");
var top_div = null;
if (0 < top_divs.length){
	top_div = top_divs[0];
}
if (null != top_div){
	var results_text = top_div.innerHTML;

	if ('undefined' == typeof sphinx_results){
		results_text = results_text.replace('results', '(wp_query) results');
	}
	else {
		results_text = results_text.replace('results', '(sphinx) results');
		results_text = results_text.replace('terms:', 'terms in ' + sphinx_results['elapsed_time'] + ' seconds:');

		// insert the original wp_query before top_div
		var wp_query_div = document.createElement('div');
		var query_comment = document.createTextNode( 'original query: ' + sphinx_results['wp_request'] );
		wp_query_div.appendChild(query_comment);

		var searcheditor = document.getElementById('scrib_searcheditor-3');
		if ( searcheditor ) {
			searcheditor.parentNode.insertBefore( wp_query_div, searcheditor );

			var br = document.createElement('br');
			searcheditor.parentNode.insertBefore( br, searcheditor );			
		}
	}
	top_div.innerHTML = results_text;
}
