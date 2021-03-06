<?php

/**
 * Plugin Name:       SWS ManageItems
 * Plugin URI:        https://ccharacter.com/custom-plugins/sws-manage-items/
 * Description:       Manage and display uploaded files, links, or videos
 * Version:           1.52
 * Requires at least: 5.7
 * Requires PHP:      5.5
 * Author:            Sharon Stromberg
 * Author URI:        https://ccharacter.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sws-manage-items
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once plugin_dir_path(__FILE__).'inc/plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://raw.githubusercontent.com/ccharacter/sws-manage-items/main/plugin.json',
	__FILE__,
	'sws_manage_items'
);

require_once plugin_dir_path(__FILE__).'func_fields.php';
require_once plugin_dir_path(__FILE__).'shortcodes.php';
require_once plugin_dir_path(__FILE__).'acf_field_group.php';

//require_once plugin_dir_path(__FILE__).'/inc/import_acf_field_group.php';
//require_once plugin_dir_path(__FILE__).'duplicate_pages.php';


// add stylesheets
function sws_manage_items_enqueue_script() {   
 	//wp_enqueue_style( 'swsTweakStyles', plugin_dir_url(__FILE__).'inc/sws_tweaks_style.css');
}
add_action('wp_enqueue_scripts', 'sws_manage_items_enqueue_script');

//$optVals = get_option( 'sws_wp_tweaks_options' );

/* NOTES TO SELF
--add categories
--add ACF Group
--add templates
--add options page with shortcode descriptions
*/


/**
 * Checks if Gravity Forms is active
 */
function sws_manage_items_activate() {
  if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
    include_once( ABSPATH . '/wp-admin/includes/plugin.php' );
  }
  
  if ( current_user_can( 'activate_plugins' ) && ! class_exists( 'GFCommon' ) ) {
    // Deactivate the plugin.
    deactivate_plugins( plugin_basename( __FILE__ ) );
    // Throw an error in the WordPress admin console.
    $error_message = '<p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen-Sans,Ubuntu,Cantarell,\'Helvetica Neue\',sans-serif;font-size: 13px;line-height: 1.5;color:#444;">' . esc_html__( 'This plugin requires ', 'gravityforms' ) . '<a href="' . esc_url( 'https://gravityforms.com/' ) . '">Gravity Forms</a>' . esc_html__( ' plugin to be active, as well as the <strong>Advanced Post Creation</strong> add-on.', 'gravityforms' ) . '</p>';
    die( $error_message ); // WPCS: XSS ok.
  }
}
register_activation_hook( __FILE__, 'sws_manage_items_activate' );



// CPT for FILES

add_action( 'init', 'sws_manage_items_cpt_init' );
function sws_manage_items_cpt_init() {
	$labelsCL = array(
 		'name' => 'Items',
    	'singular_name' => 'Item',
    	'add_new' => 'Add New Item',
    	'add_new_item' => 'Add New Item',
    	'edit_item' => 'Edit Item',
    	'new_item' => 'New Item',
    	'all_items' => 'All Items',
    	'view_item' => 'View Items',
    	'search_items' => 'Search Items',
    	'not_found' =>  'No Item',
    	'not_found_in_trash' => 'No Items found in Trash', 
    	'parent_item_colon' => '',
    	'menu_name' => 'ManageItems'
    );
    //register post type
	register_post_type( 'item', array(
		'labels' => $labelsCL,
		'hierarchical' => false,
		'has_archive' => true,
 		'public' => true,
		'publicly_queryable' => true,
		'supports' => array( 'title', 'editor', 'excerpt', 'custom-fields', 'thumbnail','page-attributes' ),
		'taxonomies' => array('post_tag','category'),	
		'exclude_from_search' => false,
		'capability_type' => 'post',
		'menu_icon' => 'dashicons-media-document',
		'rewrite' => array( 'with_front' => false, 'slug' => 'items' ),
		)
	);


}


/**
 * Filter slugs
 * @since 1.1.0
 * @return void
 */
function sws_manage_filter_by() {
  global $typenow;
  global $wp_query;
    if ( $typenow == 'item' ) { // Your custom post type slug
      $values = array( 'document'=>"Documents", 'video'=>"Videos", 'link'=>"Links",'resource'=>"Resources" ); // Options for the filter select field
      $current_val = '';
      if( isset( $_GET['item_type'] ) ) {
        $current_val = $_GET['item_type']; // Check if option has been selected
      } ?>
      <select name="item_type" id="item_type">
        <option value="all" <?php selected( 'all', $current_val ); ?>><?php _e( 'All Types', 'sws-manage-items' ); ?></option>
        <?php foreach( $values as $key=>$value ) { ?>
          <option value="<?php echo $key; ?>" <?php selected( $key, $current_val ); ?>><?php echo esc_attr( $value ); ?></option>
        <?php } ?>
      </select>
  <?php }
}
add_action( 'restrict_manage_posts', 'sws_manage_filter_by' );

/**
 * Update query
 * @since 1.1.0
 * @return void
 */
function sws_manage_items_sort_by_type( $query ) {
  global $pagenow;
  // Get the post type
  $post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : '';
  if ( is_admin() && $pagenow=='edit.php' && $post_type == 'item' && isset( $_GET['item_type'] ) && $_GET['item_type'] !='all' ) {
    $query->query_vars['meta_key'] = 'mgr_type';
    $query->query_vars['meta_value'] = $_GET['item_type'];
    $query->query_vars['meta_compare'] = '=';
  }
}
add_filter( 'parse_query', 'sws_manage_items_sort_by_type' );


// Update the columns shown on the custom post type edit.php view - so we also have custom columns
add_filter('manage_item_posts_columns', function($columns) {
	unset($columns['tags']);
	$rem=$columns['date'];
	unset($columns['date']);
	$columns['item_type'] = __('Item Type', 'sws_manage_items');
	$columns['date'] = $rem;
	return $columns;
}
);

// this fills in the columns that were created with each individual post's value
add_action('manage_item_posts_custom_column', function($column_key, $post_id) {
	if ($column_key == 'item_type') {
		$item_type = get_post_meta($post_id, 'mgr_type', true);
		if ($item_type) {
			switch($item_type) {
				case("document"): echo "Document"; break;
				case("link"): echo "Link"; break;
				case("video"): echo "Video"; break;
				case("resource"): echo "Resource"; break;
				default: break;
			}
		}
	}
}, 10, 2);

// make it sortable
add_filter('manage_edit-item_sortable_columns', function($columns) {
	$columns['item_type'] = 'item_type';
	return $columns;
});

function sws_fileman_rewrite_flush() {
    sws_manage_items_cpt_init();
    flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'sws_fileman_rewrite_flush' );


function sws_list_categories_for_post_type($post_type, $args) {
    if (isset($args['exclude'])) { $exclude=$args['exclude'];} else { $exclude="";}

	error_log($exclude,0);
	error_log(print_r($args,true),0);
    // Check ALL categories for posts of given post type
    foreach (get_categories() as $category) {
        $posts = get_posts(array('post_type' => $post_type, 'category' => $category->cat_ID));

        // If no posts found, ...
        if (empty($posts))
            // ...add category to exclude list
            if (strlen($exclude)>0) { $exclude.=","; }
			$exclude.= $category->cat_ID;
    }

    // Set up args
    if (strlen($exclude)>0) {
        //$args .= ('' === $args) ? '' : '&';
        //$args .= 'exclude='.implode(',', $exclude);
		$args['exclude']=$exclude;
    }

    // List categories
    return wp_list_categories($args);
}



?>
