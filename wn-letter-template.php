<?php
/*
Plugin Name: Letter Template
Plugin URI: http://www.opzegbrief-autoverzekering.nl/
Description: Shortcode and Widget for display user input form and prepare and prefill predefined downloadable letter content with user input data.
Version: 1.0.0
Author: Wouter van Nierop
Author URI: http://www.opzegbrief-autoverzekering.nl/
*/

class LetterTemplate {

	private $options;

	static $instance = 0;

	function __construct() {

		load_plugin_textdomain( 'wnlt', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		$plugin = plugin_basename(__FILE__);
		add_filter( 'plugin_action_links_'.$plugin, array( $this, 'settings_link' ) );

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_screen' ) );

		add_shortcode('wn-letter-template', array($this, 'shortcode'));

		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		add_action( 'init', array( $this, 'register_cpt_wnlt_business' ) );
		add_action( 'init', array( $this, 'download' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post', array( $this, 'save_metabox_data' ) );

		$this->load_options();

	}

	/**
	 * Preload options
	 */
	function settings_link( $links ) {

		$settings_link = '<a href="options-general.php?page=wnlt-setting">Settings</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Preload options
	 */
	function load_options() {

		$this->options = get_option( 'wnlt_options' );

	}

	/**
	 * Register Back-end settings screen.
	 */
	public function register_settings_screen() {

		add_submenu_page(
			'options-general.php', // Parent slug
			__('Letter Template Settings', 'wnlt'), // Page title
			__('Letter Template', 'wnlt'), // Menu title
			'manage_options', // Capability
			'wnlt-setting', // Menu slug
			array( $this, 'settings_screen' ) // [Callback]
		);

	}

	/**
	 * Register settings.
	 */
	function register_settings() {

		register_setting(
			'wnlt_option_group', // Option group
			'wnlt_options', // Option name
			array( $this, 'sanitize' ) // [Sanitize Callback]
		);

		add_settings_section(
			'wnlt_section_general', // ID
			'General settings', // Title
			false, //array( $this, 'wnlt_section_general_info' ), // Callback
			'wnlt-setting-page' // Page
		);

		$description = '';
		$description .= '<br />'.PHP_EOL;
		$description .= '<span class="description">'.__('Use these template tags to match the Business template display:','wnlt').'</span><br />'.PHP_EOL;
		$description .= '<pre id="business-tags" class="wrap-tags">'.PHP_EOL;
		$description .= '<code class="tag">{{name}}</code>'.PHP_EOL;
		$fields = $this->cpt_business_meta_fields();
		foreach ( $fields as $id => $label ) {
			$description .= '<code class="tag">{{'.$id.'}}</code>'.PHP_EOL;
		}
		$description .= '</pre>'.PHP_EOL;
		add_settings_field(
			'wnlt-business', // ID
			__('Business Template','wnlt') . $description, // Title
			array( $this, 'field_business' ), // Callback
			'wnlt-setting-page', // Page
			'wnlt_section_general' // Section ID
			// [Args]
		);

		$description = '';
		$description .= '
<br />
<span class="description">'.__('You can add any of these template tags anywhere in the template text and they will be prefilled with the user input:','wnlt').'</span><br />
<pre id="template-tags" class="wrap-tags">
</pre>
		';
		add_settings_field(
			'wnlt-template', // ID
			__('Letter Template','wnlt') . $description, // Title
			array( $this, 'field_template' ), // Callback
			'wnlt-setting-page', // Page
			'wnlt_section_general' // Section ID
			// [Args]
		);

		$description = '';
		$description .= '
<br />
<span class="description">'.__('Leave blank to use the build-in (letter-%business_name%) file name.','wnlt').'</span><br />
		';
		add_settings_field(
			'wnlt-filename', // ID
			__('Download File Name','wnlt') . $description, // Title
			array( $this, 'field_filename' ), // Callback
			'wnlt-setting-page', // Page
			'wnlt_section_general' // Section ID
			// [Args]
		);

		add_settings_section(
			'wnlt_section_fields', // ID
			'Letter Template Details', // Title
			false, //array( $this, 'wnlt_section_fields_info' ), // Callback
			'wnlt-setting-page' // Page
		);

	}

	/**
	 * Build Form fields Types
	 */
	function _form_field_types() {

		$fields = apply_filters( 'wnlt_form_field_types', array(
			'text' => __('Short Text','wnlt'),
			'textarea' => __('Multi-line Text Area','wnlt'),
			'predefined' => __('Predefined Text','wnlt'),
			'date' => __('Date','wnlt'),

			'cpt_business' => __('Business Select','wnlt'),
		) );

		return $fields;
	}

	/**
	 * Back-end settings screen.
	 */
	public function settings_screen() {
?>
		<div class="wrap">
			<h2><?php _e('Letter Template Settings', 'wnlt'); ?></h2>
			<div id="a2z_bizhrs-setting">
				<form method="post" action="options.php">
<?php
		settings_fields('wnlt_option_group');
?>
				<div id="wrap-options">
<?php
		do_settings_sections('wnlt-setting-page');
?>
				</div>
				<div id="wrap-fields">
					<table class="form-table">
						<tr>
							<th scope="row"><?php _e('Form Fields', 'wnlt'); ?></th>
							<td>
	<!--<span><?php _e('Please select which fields will be included in the form.'); ?></span><br />-->
<?php
	$fields = (array) $this->options['fields'];
	if ( empty($fields) ) {
		$fields[] = array(
			'name' => '',
			'type' => '',
			'description' => '',
			'extra' => '',
		);
	}
	$types = $this->_form_field_types();
	$i = 0;
	foreach ( $fields as $field ) {
?>
		<div class="wnlt-repeating wnlt-clearfix">
			<div class="actions">
				<a href="#" class="button button-secondary wnlt-up"><?php _e('Up', 'wnlt'); ?></a>
				<a href="#" class="button button-secondary wnlt-down"><?php _e('Down', 'wnlt'); ?></a>
				<a href="#" class="button button-secondary wnlt-remove"><?php _e('Remove', 'wnlt'); ?></a>
			</div>
			<div class="contents">
				<label for="wnlt_options[fields][<?php echo $i; ?>][name]"><?php _e('Field Name', 'wnlt'); ?>:</label>
				<input type="text" id="wnlt_options[fields][<?php echo $i; ?>][name]" class="regular-text wnlt-fields-name" size="30" name="wnlt_options[fields][<?php echo $i; ?>][name]" value="<?php esc_attr_e($field['name']); ?>" />
				<label for="wnlt_options[fields][<?php echo $i; ?>][type]"><?php _e('Field Type', 'wnlt'); ?>:</label>
				<select id="wnlt_options[fields][<?php echo $i; ?>][type]" class="wnlt-fields-type" name='wnlt_options[fields][<?php echo $i; ?>][type]'>
<?php
					foreach ( $types as $id => $label ) {
?>
					<option value='<?php echo $id; ?>' <?php selected($id, $field['type']); ?>><?php echo $label; ?></option>
<?php
					}
?>
				</select>
				<br />
				<label for="wnlt_options[fields][<?php echo $i; ?>][description]"><?php _e('Description', 'wnlt'); ?>:</label>
				<textarea id="wnlt_options[fields][<?php echo $i; ?>][description]" class="wnlt-fields-description" cols="50" rows="4" style="width:85%" name="wnlt_options[fields][<?php echo $i; ?>][description]"><?php esc_attr_e($field['description']); ?></textarea>
				<div class="wrap-wnlt-fields-extra">
					<div class="wnlt-fields-extra-predefined">
						<label for="wnlt_options[fields][<?php echo $i; ?>][extra]"><?php _e('Predefined Text', 'wnlt'); ?>:</label>
						<textarea id="wnlt_options[fields][<?php echo $i; ?>][extra]" cols="50" rows="3" style="width:80%" class="wnlt-fields-extra" size="71" name="wnlt_options[fields][<?php echo $i; ?>][extra]"><?php esc_attr_e($field['extra']); ?></textarea>
					</div>
				</div>
			</div>
		</div>
<?php
		$i++;
	}
?>
		<p class="wnlt-fields-action wnlt-clear">
			<a href="#" class="button button-secondary wnlt-repeat"><?php _e('Add Another', 'wnlt'); ?></a>
		</p>
							</td>
						</tr>
					</table>
				</div>
<?php
		submit_button();
?>
				</form>
			</div>
		</div>
<?php
	}

	public function field_business() {
?>
		<textarea cols="50" rows="4" style="width:97%" id="wnlt-business" name="wnlt_options[business]" class="large-text"><?php esc_html_e($this->options['business']); ?></textarea>
<?php
	}

	public function field_template() {
?>
		<textarea cols="60" rows="10" style="width:97%" id="wnlt-template" name="wnlt_options[template]" class="large-text"><?php esc_html_e($this->options['template']); ?></textarea>
<?php
	}

	public function field_filename() {
?>
		<input type="text" id="wnlt-filename" name="wnlt_options[filename]" class="large-text" value="<?php esc_html_e($this->options['filename']); ?>" />
<?php
	}

	/**
	 * Output Back-end Scripts and Styles
	 */
	public function admin_scripts() {

		wp_register_script( 'wnlt-admin', plugins_url('js/wn-letter-template-admin.js',__FILE__), array('jquery') );

		wp_localize_script( 'wnlt-admin', 'wnlt', array(
		) );

		wp_enqueue_script('wnlt-admin');

		wp_enqueue_style( 'wnlt-admin', plugins_url('css/wn-letter-template-admin.css',__FILE__), false, '1.0.0' );

	}

	/**
	 * Output functionality Scripts and Styles
	 */
	public function scripts() {

		wp_register_script( 'wnlt', plugins_url('js/wn-letter-template.js',__FILE__), array('jquery') );

		$args = array(
			'post_type' => 'wnlt_business',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		);
		$posts = get_posts($args);
		$cpt_business = array();
		$fields = $this->cpt_business_meta_fields();
		foreach ( $posts as $id => $post ) {
			$cpt_business[$post->ID] = new stdClass();
			$cpt_business[$post->ID]->name = $post->post_title;
			$meta = get_post_meta( $post->ID, '_wnlt_data', true );
			foreach ( $fields as $id => $label ) {
				$cpt_business[$post->ID]->{$id} = isset($meta[$id]) ? $meta[$id] : '';
			}
		}

		wp_localize_script( 'wnlt', 'wnlt', array(
			'str' => array(
				'unknown' => __('Unknown','wnlt'),
			),
			'cpt_business' => array(
				'template' => str_replace( "\n", '<br />'."\n", $this->options['business'] ),
				'items' => $cpt_business,
			),
		) );

		// wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_script( 'jquery-datetimepicker', plugins_url('vendor/jquery.datetimepicker.full.min.js',__FILE__), array('jquery'), '2.4.5' );
		wp_enqueue_style( 'jquery-datetimepicker', plugins_url('vendor/jquery.datetimepicker.css',__FILE__), false, '2.4.5' );

		wp_enqueue_script( 'markup', plugins_url('vendor/markup.min.js',__FILE__), array(), '1.5.21' );

		wp_enqueue_script( 'jquery-tooltipster', plugins_url('vendor/jquery.tooltipster.min.js',__FILE__), array(), '3.3.0' );
		wp_enqueue_style( 'jquery-tooltipster', plugins_url('vendor/tooltipster.css',__FILE__), array(), '3.3.0' );
		wp_enqueue_style( 'jquery-tooltipster-light', plugins_url('vendor/tooltipster-light.css',__FILE__), array('jquery-tooltipster'), '3.3.0' );

		wp_enqueue_script('wnlt');

		wp_enqueue_style( 'wnlt', plugins_url('css/wn-letter-template.css',__FILE__), false, '1.0.0' );

	}

	/**
	 * Shortcode
	 */
	public function shortcode($args) {

		$this->instance++;

		$args = shortcode_atts(array(
			'form_title' => 'Your data',
			'template_title' => 'Sample letter',
		), $args);

		ob_start();
?>
	<div class="wnlt-wrapper">
		<form class="wnlt-form" method="post" action="">
			<input type="hidden" name="wnlt_action" value="download" />
		<div class="wnlt-form-wrapper">
<?php
		if ( $this->options['fields'] ) :
?>
				<table>
<?php
			foreach ( $this->options['fields'] as $field ) :
?>
					<tr>
						<th>
				<label for="wnlt-input-<?php echo sanitize_title($field['name']); ?>-<?php echo $this->instance; ?>"><?php echo $field['name']; ?></label>
						</th>
						<td>
<?php
				if ( method_exists( $this, 'form_field_'.$field['type'] ) ) {
					call_user_func( array( $this, 'form_field_'.$field['type'] ), $field['name'], $field );
?>
				<input type="hidden" name="wnlt_type[<?php esc_attr_e($field['name']); ?>]" value="<?php esc_attr_e($field['type']); ?>" />
<?php
				}
				do_action( 'wnlt_shortcode_field_'.$field['type'], $field['name'], $field );
?>
						</td>
					</tr>
<?php
			endforeach;
?>
				</table>
<?php
		endif;
?>
		</div>
<?php
		$template = $this->options['template'];
		$template = str_replace( '%current_date%', date_i18n( get_option( 'date_format' ) ), $template );
		$template = preg_replace( '/\[([^\]]*)\]/', '<span class="wnlt-tag wnlt-tag-$1 placeholder" data-wnlt-tag="$1" data-wnlt-placeholder="[$1]">[$1]</span>', $template );
		$template = preg_replace_callback( '/wnlt-tag-([^"]*) placeholder/s', function($matches){return 'wnlt-tag-'.strtolower($matches[1]).' placeholder';}, $template);
		$template = preg_replace_callback( '/data-wnlt-tag="([^"]*)"/s', function($matches){return 'data-wnlt-tag="'.strtolower($matches[1]).'"';}, $template);
		$template = wpautop($template);
?>
		<div class="wnlt-template-wrapper">
<?php echo $template; ?>
		</div>
<?php
		if ( $this->options['fields'] ) {
?>
			<input type="submit" name="go" value="Download" />
<?php
		}
?>
		</form>
	</div>
<?php
		$output = ob_get_clean();

		return $output;
	}

	/**
	 * Output "text" Form field
	 */
	function form_field_text($name, $field) {
?>
			<input type="text" class="wnlt-field wnlt-input wnlt-text" id="wnlt-input-<?php echo sanitize_title($name); ?>-<?php echo $this->instance; ?>" name="wnlt_field[<?php esc_attr_e($name); ?>]" value="" data-wnlt-tag="<?php echo sanitize_title($name); ?>" title="<?php echo $field['description'] ? esc_html($field['description']) : ''; ?>" />
<?php
	}

	/**
	 * Output "textarea" Form field
	 */
	function form_field_textarea($name, $field) {
?>
			<textarea class="wnlt-field wnlt-input wnlt-textarea" id="wnlt-input-<?php echo sanitize_title($name); ?>-<?php echo $this->instance; ?>" name="wnlt_field[<?php esc_attr_e($name); ?>]" data-wnlt-tag="<?php echo sanitize_title($name); ?>" title="<?php echo $field['description'] ? esc_html($field['description']) : ''; ?>"></textarea>
<?php
	}

	/**
	 * Output "predefined" Form field
	 */
	function form_field_predefined($name, $field) {
?>
			<label><input type="checkbox" class="wnlt-field wnlt-checkbox wnlt-predefined" id="wnlt-input-<?php echo sanitize_title($name); ?>-<?php echo $this->instance; ?>" name="wnlt_field[<?php esc_attr_e($name); ?>]" value="1" data-wnlt-tag="<?php echo sanitize_title($name); ?>" data-wnlt-predefined="<?php esc_attr_e($field['extra']); ?>" title="<?php echo $field['description'] ? esc_html($field['description']) : ''; ?>" /> <?php _e('Yes','wnlt'); ?></label>
<?php
	}

	/**
	 * Output "date" Form field
	 */
	function form_field_date($name, $field) {
?>
			<input type="text" class="wnlt-field wnlt-input wnlt-date" id="wnlt-input-<?php echo sanitize_title($name); ?>-<?php echo $this->instance; ?>" name="wnlt_field[<?php esc_attr_e($name); ?>]" value="" data-wnlt-tag="<?php echo sanitize_title($name); ?>" title="<?php echo $field['description'] ? esc_html($field['description']) : ''; ?>" />
<?php
	}

	/**
	 * Output "cpt_business" Form field
	 */
	function form_field_cpt_business($name, $field) {
?>
			<select class="wnlt-field wnlt-select wnlt-cpt_business" id="wnlt-input-<?php echo sanitize_title($name); ?>-<?php echo $this->instance; ?>" name="wnlt_field[<?php esc_attr_e($name); ?>]" data-wnlt-tag="<?php echo sanitize_title($name); ?>" title="<?php echo $field['description'] ? esc_html($field['description']) : ''; ?>" />
				<option value="">-- <?php _e('please select','wnlt'); ?> --</option>
				<option value="-1">- <?php _e('Unknown','wnlt'); ?> -</option>
<?php
			$args = array(
				'post_type' => 'wnlt_business',
				'posts_per_page' => -1,
				'orderby' => 'title',
				'order' => 'ASC',
			);
			$cpt_business = get_posts($args);
			foreach ( $cpt_business as $business ) :
				// $meta = get_post_meta ( $business->ID, '_wnlt_data', true );
?>
				<option value="<?php echo $business->ID; ?>"><?php echo $business->post_title; ?></option>
<?php
			endforeach;
?>
			</select>
<?php
	}

	/**
	 * Download prefilled letter template
	 */
	function download() {

		if ( isset($_REQUEST['wnlt_action']) && $_REQUEST['wnlt_action'] == 'download' ) {

			$post_fields = $_REQUEST['wnlt_field'];
			$tags = array();
			$fields = $this->options['fields'];

			$fields_search = array_map(function($a){return $a['name'];},$fields);
			foreach ( $post_fields as $name => $value ) {
				$id = array_search(trim($name),$fields_search);
				if ( $id < 0 ) {
					continue;
				}
				$tagName = sanitize_title($name);
				$tags[$tagName] = new stdClass();
				$tags[$tagName]->id = $id;
				$tags[$tagName]->name = $tagName;
				$tags[$tagName]->value = sanitize_text_field($value);
				$tags[$tagName]->type = $fields[$id]['type'];
				$tags[$tagName]->extra = $fields[$id]['extra'];
			}

			$template = $this->options['template'];

			// system tags
			$template = str_replace( '%current_date%', date_i18n( get_option( 'date_format' ) ), $template );

			// special user fields' tags
			$business_name = '';
			foreach ( $tags as $tag ) {
				$tagName = sanitize_title($tag->name);
				switch ($tag->type) {
					case 'cpt_business':
					{
						if ( ! $tag->value ) {
							$template = str_ireplace( '['.$tagName.']', '', $template );
							continue;
						}

						$business_template = $this->options['business'];
						// $business_template = str_replace( "\n", '<br />', $business_template );
						$post = get_post( $tag->value );
						if ( ! $post ) {
							$business_name = '- '.__('Unknown','wnlt').' -';
							$template = str_ireplace( '['.$tagName.']', $business_name, $template );
							continue;
						}
						$business_name = $post->post_title;
						$meta = get_post_meta( $post->ID, '_wnlt_data', true );

						$business_template = str_ireplace( '{{name}}', $business_name, $business_template );

						$fields = $this->cpt_business_meta_fields();
						foreach ( $fields as $id => $label ) {
							if ( $meta[$id] ) {
								$business_template = str_ireplace( '{{'.$id.'}}', $meta[$id], $business_template );
							}
						}

						$template = str_ireplace( '['.$tagName.']', $business_template, $template );
					}
					break;
					case 'predefined':
					{
						if ( $tag->value ) {
							$template = str_ireplace( '['.$tagName.']', $tags[$tagName]->extra, $template );
						} else {
							$template = str_ireplace( '['.$tagName.']', '', $template );
						}
					}
					break;
				}
			}

			// standard user fields' tags
			foreach ( $this->options['fields'] as $field ) {
				$tagName = sanitize_title($field['name']);
				$template = str_ireplace( '['.$tagName.']', $tags[$tagName]->value, $template );
			}

			$template = apply_filters( 'wnlt_template_parse_tags', $template );

			// cleanup leftover tags
			$template = preg_replace( '/%[^%]*%/', '', $template ); // system tags
			$template = preg_replace( '/{{[^}]*}}/', '', $template ); // special user fields' tags
			$template = preg_replace( '/\[[^}]*\]/', '', $template ); // standard user fields' tags

			$template = wpautop($template);

			$output = <<<___HTML___
<html
    xmlns:o='urn:schemas-microsoft-com:office:office'
    xmlns:w='urn:schemas-microsoft-com:office:word'
    xmlns='http://www.w3.org/TR/REC-html40'>
    <head><title>Time</title>
    <xml>
        <w:worddocument xmlns:w="#unknown">
            <w:view>Print</w:view>
            <w:zoom>90</w:zoom>
            <w:donotoptimizeforbrowser />
        </w:worddocument>
    </xml>
    <style>
        @page Section1
        {size:8.5in 11.0in;
         margin:1.0in 1.25in 1.0in 1.25in ;
         mso-header-margin:.5in;
         mso-footer-margin:.5in; mso-paper-source:0;}
        div.Section1
        {page:Section1;}
    </style>
</head>
<body lang=EN-US style='tab-interval:.5in'>

$template

</body>
</html>
___HTML___;

			$filename = apply_filters( 'wnlt_filename', $this->options['filename'] );
			if ( ! $filename ) {
				$filename = 'letter' . ( $business_name ? '_' . $business_name : '' ) . '.doc';
			} else
			{
				$filename = preg_replace( '/\.docx?$/', '', $filename ) . '.doc';
			}
			$filename = str_replace( '%business_name%', $business_name, $filename );
			$filename = preg_replace( '/%[^%*]%/', '', $filename );
			$filename = sanitize_file_name( $filename );
			header('Content-type: application/vnd.ms-word; charset='.get_option('blog_charset'));
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			echo pack("CCC",0xef,0xbb,0xbf);
			echo $output;
			die;
		}

	}

	/**
	 * Register Custom Post Type for Letter Template Businesses
	 */
	function register_cpt_wnlt_business() {

		$this->load_options();

		$supports = array( 'title' );

		$labels = array(
			'name'                => _x( 'Letter Template Businesses', 'Post Type General Name', 'wnlt' ),
			'singular_name'       => _x( 'Business', 'Post Type Singular Name', 'wnlt' ),
			'menu_name'           => __( 'LT-Businesses', 'wnlt' ),
			'parent_item_colon'   => __( 'Parent Business:', 'wnlt' ),
			'all_items'           => __( 'All Businesses', 'wnlt' ),
			'view_item'           => __( 'View Business', 'wnlt' ),
			'add_new_item'        => __( 'Add New Business', 'wnlt' ),
			'add_new'             => __( 'Add New', 'wnlt' ),
			'edit_item'           => __( 'Edit Business', 'wnlt' ),
			'update_item'         => __( 'Update Business', 'wnlt' ),
			'search_items'        => __( 'Search Businesses', 'wnlt' ),
			'not_found'           => __( 'Not found', 'wnlt' ),
			'not_found_in_trash'  => __( 'Not found in Trash', 'wnlt' ),
		);
		$args = array(
			'label'               => __( 'business', 'wnlt' ),
			'description'         => __( 'Letter Template Businesses and Organizations', 'wnlt' ),
			'labels'              => $labels,
			'supports'            => $supports,
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-id',
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'page',
			'rewrite'             => false
		);
		register_post_type( 'wnlt_business', $args );

		add_filter( 'manage_wnlt_business_posts_columns', array( $this, 'cpt_business_columns' ), 10 );
		add_action( 'manage_wnlt_business_posts_custom_column', array( $this, 'cpt_business_custom_column' ), 10, 2 );

	}

	/**
	 * Build Details Metabox fields for our Custom Post Type
	 */
	function cpt_business_meta_fields() {

		$fields = apply_filters( 'wnlt_cpt_business_meta_fields', array(
			'address' => __('Address','wnlt'),
			'postcode' => __('Postcode','wnlt'),
			'city' => __('City','wnlt'),
		) );

		return $fields;
	}

	function cpt_business_columns($columns ) {

		$fields = $this->cpt_business_meta_fields();

		foreach ( $fields as $id => $label ) {
			$columns[$id] = $label;
		}

		return $columns;
	}

	function cpt_business_custom_column($column, $post_id) {

		$meta = get_post_meta( $post_id, '_wnlt_data', true );

		echo isset($meta[$column]) ? esc_attr_e($meta[$column]) : '';

	}

	/**
	 * Add Metabox for our Custom Post Type
	 */
	function add_metabox() {

		add_meta_box( 'wnlt_business_details', __( 'Details', 'wnlt' ), array( $this, 'metabox_business_details' ), 'wnlt_business', 'normal', 'default' );

	}

	/**
	 * Display Details Metabox for our Custom Post Type
	 */
	function metabox_business_details($post) {

		$fields = $this->cpt_business_meta_fields();

		$meta = get_post_meta( $post->ID, '_wnlt_data', true );
		wp_nonce_field( 'wnlt_business', 'wnlt_business_nonce' );

		do_action( 'wnlt_metabox_business_details_before', $post );

		foreach ( $fields as $id => $label ) :
?>
			<label for="wnlt-biz-<?php echo $id; ?>">
				<?php echo $label; ?>
			</label>
			<input type="text" id="wnlt-biz-<?php echo $id; ?>" class="wnlt-biz" name="wnlt-biz[<?php echo $id; ?>]" value="<?php isset($meta[$id]) ? esc_attr_e($meta[$id]) : ''; ?>" />
<?php
		endforeach;

		do_action( 'wnlt_metabox_business_details_after', $post );

	}

	/**
	 * Save extra data from Metabox
	 */
	function save_metabox_data( $post_id ) {

		if ( ! isset( $_POST['wnlt_business_nonce'] ) || ! wp_verify_nonce( $_POST['wnlt_business_nonce'], 'wnlt_business' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['wnlt-biz'] ) ) {
			return;
		}

		$meta = $this->sanitize( $_POST['wnlt-biz'] );
		update_post_meta( $post_id, '_wnlt_data', $meta );

	}

	/**
	 * Sanitize user input
	 */
	function sanitize( $input ) {

		$textareas = array('business','template','description');

		if ( ! $input || !is_array($input) || empty($input) ) {
			return;
		}

		foreach ( $input as $id => $data ) {
			if ( is_array($data) ) {
				$input[$id] = $this->sanitize( $data );
			} else {
				if ( in_array( $id, $textareas ) ) {
					// $input[$id] = implode( "\n", array_map( 'sanitize_text_field', explode( "\n", data ) ) );
					$input[$id] = esc_textarea( $data );
				} else {
					$input[$id] = sanitize_text_field( $data );
				}
			}
		}

		return $input;

	}

}

require_once( plugin_dir_path( __FILE__ ) . 'wn-letter-template-widget.php' );

$wnlt_letter_template = new LetterTemplate();

