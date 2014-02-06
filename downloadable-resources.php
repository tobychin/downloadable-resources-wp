<?php
/*
Plugin Name: Downloadable Resources
Plugin URI: #
Description: Create resources for people to download. List Excel, Word, PowerPoint templates and documents, to name a few! 
Version: 1.0
Author: PayProMedia
Author URI: http://www.paypromedia.com/
License: GPLv2
*/

function create_resource() {
    register_post_type( 'resources',
        array(
            'labels' => array(
                'name' => 'Downloadable Resources',
                'singular_name' => 'Downloadable Resource',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Resource',
                'edit' => 'Edit',
                'edit_item' => 'Edit Resource',
                'new_item' => 'New Resource',
                'view' => 'View',
                'view_item' => 'View Resource',
                'search_items' => 'Search Downloadable Resources',
                'not_found' => 'No Downloadable Resources found',
                'not_found_in_trash' => 'No Downloadable Resources found in Trash',
                'parent' => 'Parent Resource'
            ),
 
            'public' => true,
            'menu_position' => 15,
            'supports' => array( 'title', 'editor', 'thumbnail' ),
            'taxonomies' => array( 'resource_categories', 'resource_tags' ),
            'menu_icon' => plugins_url( 'images/generic.png', __FILE__ ),
            'has_archive' => true
        )
    );
}

add_action( 'init', 'create_resource' );


// hook into the init action and call create_book_taxonomies when it fires
add_action( 'init', 'create_resource_taxonomies', 0 );

// create two taxonomies, genres and writers for the post type "book"
function create_resource_taxonomies() {
	// Add new taxonomy, make it hierarchical (like categories)
	$labels = array(
		'name'              => _x( 'Resource Categories', 'taxonomy general name' ),
		'singular_name'     => _x( 'Resource Category', 'taxonomy singular name' ),
		'search_items'      => __( 'Search Resource Categories' ),
		'all_items'         => __( 'All Resource Categories' ),
		'parent_item'       => __( 'Parent Resource Category' ),
		'parent_item_colon' => __( 'Parent Resource Category:' ),
		'edit_item'         => __( 'Edit Resource Category' ),
		'update_item'       => __( 'Update Resource Category' ),
		'add_new_item'      => __( 'Add New Resource Category' ),
		'new_item_name'     => __( 'New Genre Resource Category' ),
		'menu_name'         => __( 'Resource Categories' ),
	);

	$args = array(
		'hierarchical'      => true,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'resources' ),
	);
	register_taxonomy( 'resource_categories', array( 'resources' ), $args );
	
	// Add new taxonomy, NOT hierarchical (like tags)
	$labels = array(
		'name'                       => _x( 'Resource Tags', 'taxonomy general name' ),
		'singular_name'              => _x( 'Resource Tag', 'taxonomy singular name' ),
		'search_items'               => __( 'Search Resource Tags' ),
		'popular_items'              => __( 'Popular Resource Tags' ),
		'all_items'                  => __( 'All Resource Tags' ),
		'parent_item'                => null,
		'parent_item_colon'          => null,
		'edit_item'                  => __( 'Edit resource tag' ),
		'update_item'                => __( 'Update resource tag' ),
		'add_new_item'               => __( 'Add New Resource Tag' ),
		'new_item_name'              => __( 'New Writer Resource Tag' ),
		'separate_items_with_commas' => __( 'Separate tags with commas' ),
		'add_or_remove_items'        => __( 'Add or remove resource tags' ),
		'choose_from_most_used'      => __( 'Choose from the most used resource tags' ),
		'not_found'                  => __( 'No resource tags found.' ),
		'menu_name'                  => __( 'Resource Tags' ),
	);

	$args = array(
		'hierarchical'          => false,
		'labels'                => $labels,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'resource-tags' ),
	);

	register_taxonomy( 'resource_tags', 'resources', $args );
	}
	
// Let's add the file field to the bottom of these posts

function add_custom_meta_boxes() {

	// Define the custom attachment for posts
	add_meta_box(
		'wp_custom_attachment',
		'Attach a File',
		'wp_custom_attachment',
		'resources',
		'normal'
	);

} // end add_custom_meta_boxes
add_action('add_meta_boxes', 'add_custom_meta_boxes');

// That was fun!

// Now, let's actually display the field on the post editor page and get the file.

function wp_custom_attachment() {

	wp_nonce_field(plugin_basename(__FILE__), 'wp_custom_attachment_nonce');
	
	$html = '<p class="description">';
		$html .= 'Upload your file here.';
	$html .= '</p>';
	$html .= '<input id="wp_custom_attachment" name="wp_custom_attachment" value="" size="25" type="file">';
	
	echo $html;

} // end wp_custom_attachment

// Guess we should save it, too...

function save_custom_meta_data($id) {

	/* --- security verification --- */
	if(!wp_verify_nonce($_POST['wp_custom_attachment_nonce'], plugin_basename(__FILE__))) {
	  return $id;
	} // end if
	  
	if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
	  return $id;
	} // end if
	  
	if('page' == $_POST['post_type']) {
	  if(!current_user_can('edit_page', $id)) {
	    return $id;
	  } // end if
	} else {
   		if(!current_user_can('edit_page', $id)) {
	    	return $id;
	   	} // end if
	} // end if
	/* - end security verification - */
	
	// Make sure the file array isn't empty
	if(!empty($_FILES['wp_custom_attachment']['name'])) {
		
		// Setup the array of supported file types. In this case, it's PDF, txt, .doc, .xls, .ppt, png, jpg.
		$supported_types = array(
		'application/pdf',
		'text/plain',
		'application/msword',
		'application/vnd.ms-excel',
		'application/vnd.ms-powerpoint',
		'image/png',
		'image/jpg'
		);
		
		// Get the file type of the upload
		$arr_file_type = wp_check_filetype(basename($_FILES['wp_custom_attachment']['name']));
		$uploaded_type = $arr_file_type['type'];
		
		// Check if the type is supported. If not, throw an error.
		if(in_array($uploaded_type, $supported_types)) {

			// Use the WordPress API to upload the file
			$upload = wp_upload_bits($_FILES['wp_custom_attachment']['name'], null, file_get_contents($_FILES['wp_custom_attachment']['tmp_name']));
	
			if(isset($upload['error']) && $upload['error'] != 0) {
				wp_die('There was an error uploading your file. The error is: ' . $upload['error']);
			} else {
				add_post_meta($id, 'wp_custom_attachment', $upload);
				update_post_meta($id, 'wp_custom_attachment', $upload);		
			} // end if/else

		} else {
			wp_die("The file type that you've uploaded is not valid.");
		} // end if/else
		
	} // end if
	
} // end save_custom_meta_data
add_action('save_post', 'save_custom_meta_data');

function update_edit_form() {  
    echo ' enctype="multipart/form-data"';  
} // end update_edit_form  
add_action('post_edit_form_tag', 'update_edit_form');

?>