jQuery.noConflict()(function(){

	// Handle autocomplete for authors
	jQuery( '#contributors-box-id' ).each( function() {
	
		// This is the authors autocomplete input	
		var $author_input = jQuery( 'input#search_author_id' );
				
		//Autocomplete 
		if ( $author_input.size() > 0 ) {
			
			$author_input.autocomplete({
				delay: 0,
				minLength: 1,

				source: function( $request, $response ) {
					
					//Ajax call to get data source for autocomplete					
					jQuery.ajax({
						url: cp_js_vars.ajaxurl,
						type: 'POST',
						async: true,
						cache: false,
						dataType: 'json',

						data: {
							
							action: 'contributors_ajax_suggest',
							author_search_term: $request.term
		 
							},
	
						success: function( $data ) {

							$response(jQuery.map( $data, function( $item ) {

								return {

									user_id: $item.user_id,
									user_login: $item.user_login,
									display_name: $item.display_name,
									email: $item.email,
									term_id: $item.term_id,
									no_result: $item.no_result 

									}

								}));
							}
					});
				},
				
				response: function( $event, $ui ) {										
					
					// stop the loading spinner
					stop_loading_spinner();
				
					hide_no_user_msg()
				},
				
				search: function( $event, $ui ) {
					
					hide_no_user_msg()
					
				},
				
				select: function( $event, $ui ) {
				
					// stop the loading spinner
					stop_loading_spinner();
					
					//function to create checkbox on meta box when contributor is selected.					
					contributors_with_checkboxes($ui.item.display_name, $ui.item.term_id, $ui.item.user_id, $ui.item.email);

					},
				
				focus: function( $event, $ui ) {
					stop_loading_spinner();
				},

				close: function( $event, $ui ) {
					stop_loading_spinner();
				},

				change: function( $event, $ui ) {
					stop_loading_spinner();
					}
					
			//Ajax result display
			}).data( "ui-autocomplete" )._renderItem = function( $ul, $item ) {

				if(typeof $item.no_result === 'undefined'){

					return jQuery( '<li>' )
					.append( '<a>'+$item.display_name + '<strong> | </strong> E-mail: <em>' + $item.email + '</em></a>' )
					.appendTo( $ul )					

					}
					
					show_no_user_msg();
					
				};
	   	 }
		});
	});

	function show_no_user_msg(){

		jQuery('#nomatch').show()

		}


	function hide_no_user_msg(){

		jQuery('#nomatch').hide()

		}

	function contributors_with_checkboxes( $display_name, $term_id, $user_id, $email ) {
		
		//Global variables from php - wp_localize_post		
		$post_author = cp_js_vars.current_post_author;
		$post_author_text = cp_js_vars.post_author_text;
		$user_present_text = cp_js_vars.user_present_text;	
	
		if( $user_id == $post_author ) {
			alert( $display_name+' '+$post_author_text );
				
		} else {
			
			//Checkboxes data creation	
			if( terms_in_contributors_list( $term_id ) ) {				
																																																																																																																																	
				jQuery( '.toappend' )
			
				.append( '<div id="contributors-list">' )
				.append( '<p> <label>' )
				.append( '<input id="my_check" type="checkbox" checked="check" name="contributors[]" value="'+$term_id+'"/>'+ $display_name +' <i style="color:grey">'+$email+'</i>')
				.append( '</label></p></div>' );
			
			} else { 
	 
	  			alert($user_present_text);
	  	
		 		 }
		 		 
	   		}
	 		 	}
	 

	function terms_in_contributors_list($term_id){
	 	
	 	var num = jQuery('#contributors-list #my_check');
	 	
	 	for(i=0; i < num.length; i++){
	 			if(num[i].value == $term_id){
	 				return false;	
	 	 			}
	 			}
	 		return true;
		}
	
	function stop_loading_spinner() {
		
		jQuery( 'input#search_author_id' ).removeClass( 'ui-autocomplete-loading' );

		}