
// add a label to the search results so we know how the query was executed
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
	}
	top_div.innerHTML = results_text;
}
