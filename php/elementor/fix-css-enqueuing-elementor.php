<?php

	/**
	* title: Force CSS enqueue of Elementor template and loop items
	* tags: elementor, dynamic, css styling, fix
	* description: When using the template widget, Dynamic.ooo Repeater, or the FIVE internal Repeater Add on, use this snippet to fix issues with widgets styling not being properly applied to elements due to CSS files not being printed on the markup. The snippet ensures that the style is only printed once per page
	*/
	
	// Global list to store the document ids that css have been printed
	$GLOBALS['five_el_printed_css'] = [];
	
	// Adds our action just before we print the element
	add_action('elementor/frontend/before_get_builder_content', function($document) {
		
		// Accesses our global list
		global $five_el_printed_css;
		
		// If we have not yet printed this documents (elementor template) CSS:
		if(!in_array($document->get_id(), $five_el_printed_css)) {
			
			// Adds the document ID to the global
			$five_el_printed_css[] = $document->get_id();
			
			// Adds our filter
			add_filter('elementor/frontend/builder_content/before_print_css', 'five_return_print_css', 20);
		}
		
	}, 10, 1);
	
	// Once we generate the document's markup, we remove the filter so that we check whether or not it has been printed before prior to rendering it
	add_action('elementor/frontend/get_builder_content', function($document) {
		
		remove_filter('elementor/frontend/builder_content/before_print_css', 'five_return_print_css', 20);
		
	}, 10, 1);
	
	// Return true
	function five_return_print_css() {
		return true;
	}

?>