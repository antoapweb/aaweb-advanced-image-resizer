<?php
/**
 * Plugin Name: AAWEB Advanced Image Resizer
 * Plugin URI: https://antoapweb.gr/aaweb-advanced-image-resizer/
 * Description: Resize WordPress Media Library images with padding, social presets, bulk actions, create-new mode and safer replace mode with optional backups.
 * Version: 1.2.1
 * Requires at least: 6.7
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Author: AAWEB - Apostolou Antonios
 * Author URI: https://antoapweb.gr/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: aaweb-advanced-image-resizer
 * Domain Path: /languages
 *
 * @package AAWEB_Advanced_Image_Resizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AAWEB_AIR_VERSION', '1.2.1' );
define( 'AAWEB_AIR_FILE', __FILE__ );
define( 'AAWEB_AIR_DIR', plugin_dir_path( __FILE__ ) );
define( 'AAWEB_AIR_URL', plugin_dir_url( __FILE__ ) );

final class AAWEB_Advanced_Image_Resizer {
	const OPTION_KEY = 'aaweb_air_settings';
	const NONCE_AJAX = 'aaweb_air_ajax';
	const NONCE_BULK = 'aaweb_air_bulk';
	const BACKUP_META = '_aaweb_air_replace_backups';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AAWEB_AIR_FILE ), array( $this, 'plugin_action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		add_filter( 'media_row_actions', array( $this, 'media_row_actions' ), 10, 2 );
		add_action( 'wp_ajax_aaweb_air_resize_image', array( $this, 'ajax_resize_image' ) );
		add_action( 'wp_ajax_aaweb_air_get_backups', array( $this, 'ajax_get_backups' ) );
		add_action( 'wp_ajax_aaweb_air_restore_backup', array( $this, 'ajax_restore_backup' ) );
		add_action( 'wp_ajax_aaweb_air_delete_backup', array( $this, 'ajax_delete_backup' ) );

		add_filter( 'bulk_actions-upload', array( $this, 'register_bulk_action' ) );
		add_action( 'restrict_manage_posts', array( $this, 'bulk_controls' ), 10, 2 );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	public function admin_menu() {
		add_media_page(
			__( 'AAWEB Image Resizer', 'aaweb-advanced-image-resizer' ),
			__( 'AAWEB Image Resizer', 'aaweb-advanced-image-resizer' ),
			'upload_files',
			'aaweb-image-resizer',
			array( $this, 'settings_page' )
		);
	}

	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'upload.php?page=aaweb-image-resizer' ) ) . '">' . esc_html__( 'Settings', 'aaweb-advanced-image-resizer' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function defaults() {
		return array(
			'default_mode'       => 'replace',
			'jpg_quality'        => 92,
			'webp_quality'       => 90,
			'output_format'      => 'jpg',
			'background_color'   => '#ffffff',
			'enable_backups'     => 1,
			'enable_row_action'  => 1,
			'enable_bulk_action' => 1,
			'enable_modal_tool'  => 1,
			'max_backups'        => 10,
			'custom_presets'     => '',
		);
	}

	public function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $this->defaults() );
	}

	public function register_settings() {
		register_setting(
			'aaweb_air_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->defaults(),
			)
		);
	}

	public function sanitize_settings( $input ) {
		$defaults = $this->defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = $defaults;

		$mode = isset( $input['default_mode'] ) ? sanitize_key( wp_unslash( $input['default_mode'] ) ) : $defaults['default_mode'];
		$output['default_mode'] = in_array( $mode, array( 'replace', 'create' ), true ) ? $mode : 'replace';

		$quality = isset( $input['jpg_quality'] ) ? absint( wp_unslash( $input['jpg_quality'] ) ) : $defaults['jpg_quality'];
		$output['jpg_quality'] = min( 100, max( 50, $quality ) );

		$webp_quality = isset( $input['webp_quality'] ) ? absint( wp_unslash( $input['webp_quality'] ) ) : $defaults['webp_quality'];
		$output['webp_quality'] = min( 100, max( 50, $webp_quality ) );

		$output_format = isset( $input['output_format'] ) ? sanitize_key( wp_unslash( $input['output_format'] ) ) : $defaults['output_format'];
		$output['output_format'] = in_array( $output_format, array( 'jpg', 'png', 'webp' ), true ) ? $output_format : 'jpg';

		$color = isset( $input['background_color'] ) ? sanitize_hex_color( wp_unslash( $input['background_color'] ) ) : $defaults['background_color'];
		$output['background_color'] = $color ? $color : '#ffffff';

		$output['enable_backups']     = empty( $input['enable_backups'] ) ? 0 : 1;
		$output['enable_row_action']  = empty( $input['enable_row_action'] ) ? 0 : 1;
		$output['enable_bulk_action'] = empty( $input['enable_bulk_action'] ) ? 0 : 1;
		$output['enable_modal_tool']  = empty( $input['enable_modal_tool'] ) ? 0 : 1;

		$max_backups = isset( $input['max_backups'] ) ? absint( wp_unslash( $input['max_backups'] ) ) : $defaults['max_backups'];
		$output['max_backups'] = min( 50, max( 0, $max_backups ) );

		$output['custom_presets'] = isset( $input['custom_presets'] ) ? $this->sanitize_custom_presets_text( wp_unslash( $input['custom_presets'] ) ) : '';

		return $output;
	}

	private function sanitize_custom_presets_text( $value ) {
		$value = is_string( $value ) ? $value : '';
		$lines = preg_split( '/\r\n|\r|\n/', $value );
		$clean = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			if ( preg_match( '/^(.+?)\s*\|\s*(\d{2,5})\s*x\s*(\d{2,5})$/i', $line, $m ) ) {
				$label = sanitize_text_field( $m[1] );
				$w     = absint( $m[2] );
				$h     = absint( $m[3] );

				if ( $label && $w >= 10 && $h >= 10 && $w <= 20000 && $h <= 20000 ) {
					$clean[] = $label . '|' . $w . 'x' . $h;
				}
			}
		}

		return implode( "\n", array_slice( $clean, 0, 40 ) );
	}

	public function presets() {
		$presets = array(
			'carousel_square'    => array( 'label' => __( 'Carousel Square', 'aaweb-advanced-image-resizer' ), 'w' => 1080, 'h' => 1080 ),
			'carousel_landscape' => array( 'label' => __( 'Carousel Landscape', 'aaweb-advanced-image-resizer' ), 'w' => 1080, 'h' => 566 ),
			'carousel_portrait'  => array( 'label' => __( 'Carousel Portrait', 'aaweb-advanced-image-resizer' ), 'w' => 1080, 'h' => 1350 ),
			'shared_link'        => array( 'label' => __( 'Shared Link', 'aaweb-advanced-image-resizer' ), 'w' => 1200, 'h' => 630 ),
			'facebook_square'    => array( 'label' => __( 'Facebook Square', 'aaweb-advanced-image-resizer' ), 'w' => 1200, 'h' => 1200 ),
			'facebook_ad'        => array( 'label' => __( 'Facebook Ad', 'aaweb-advanced-image-resizer' ), 'w' => 1200, 'h' => 628 ),
			'wp_banner'          => array( 'label' => __( 'WordPress Banner', 'aaweb-advanced-image-resizer' ), 'w' => 1544, 'h' => 500 ),
			'wp_banner_small'    => array( 'label' => __( 'WordPress Banner Small', 'aaweb-advanced-image-resizer' ), 'w' => 772, 'h' => 250 ),
			'wp_icon_256'        => array( 'label' => __( 'WordPress Icon 256', 'aaweb-advanced-image-resizer' ), 'w' => 256, 'h' => 256 ),
			'wp_icon_128'        => array( 'label' => __( 'WordPress Icon 128', 'aaweb-advanced-image-resizer' ), 'w' => 128, 'h' => 128 ),
		);

		$settings = $this->get_settings();
		$custom   = isset( $settings['custom_presets'] ) ? $settings['custom_presets'] : '';
		$lines    = preg_split( '/\r\n|\r|\n/', $custom );

		foreach ( $lines as $index => $line ) {
			$line = trim( $line );
			if ( preg_match( '/^(.+?)\|([0-9]+)x([0-9]+)$/', $line, $m ) ) {
				$key = 'custom_' . $index . '_' . sanitize_key( $m[1] );
				$presets[ $key ] = array(
					'label' => sanitize_text_field( $m[1] ),
					'w'     => absint( $m[2] ),
					'h'     => absint( $m[3] ),
				);
			}
		}

		return apply_filters( 'aaweb_air_presets', $presets );
	}

	private function preset_options_html( $selected = '' ) {
		$html = '<option value="">' . esc_html__( 'Select size', 'aaweb-advanced-image-resizer' ) . '</option>';
		foreach ( $this->presets() as $key => $preset ) {
			$label = sprintf( '%s (%dx%d)', $preset['label'], $preset['w'], $preset['h'] );
			$html .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $selected, $key, false ), esc_html( $label ) );
		}
		return $html;
	}

	public function enqueue_admin_assets( $hook ) {
		$allowed = array( 'upload.php', 'media_page_aaweb-image-resizer' );
		if ( ! in_array( $hook, $allowed, true ) ) {
			return;
		}

		$settings = $this->get_settings();
		$presets  = array();
		$formats  = array();
		foreach ( $this->output_formats() as $key => $format ) {
			$formats[ $key ] = $format['label'];
		}
		foreach ( $this->presets() as $key => $preset ) {
			$presets[ $key ] = array(
				'w'     => (int) $preset['w'],
				'h'     => (int) $preset['h'],
				'label' => sprintf( '%s (%dx%d)', $preset['label'], $preset['w'], $preset['h'] ),
			);
		}

		wp_enqueue_style( 'aaweb-air-admin', AAWEB_AIR_URL . 'assets/admin.css', array(), AAWEB_AIR_VERSION );
		wp_enqueue_script( 'aaweb-air-admin', AAWEB_AIR_URL . 'assets/admin.js', array( 'jquery' ), AAWEB_AIR_VERSION, true );

		wp_localize_script(
			'aaweb-air-admin',
			'AAWEBAIR',
			array(
				'ajaxurl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( self::NONCE_AJAX ),
				'bulkNonce'     => wp_create_nonce( self::NONCE_BULK ),
				'presets'       => $presets,
				'defaultMode'   => $settings['default_mode'],
				'outputFormat'  => $settings['output_format'],
				'formats'       => $formats,
				'enableModal'   => (bool) $settings['enable_modal_tool'],
				'enableBulk'    => (bool) $settings['enable_bulk_action'],
				'confirmReplace'=> (bool) $settings['enable_backups'],
				'i18n'          => array(
					'selectSize' => __( 'Select size', 'aaweb-advanced-image-resizer' ),
					'sizeBulk'   => __( '— Size for Bulk —', 'aaweb-advanced-image-resizer' ),
					'mode'       => __( '— Mode —', 'aaweb-advanced-image-resizer' ),
					'format'     => __( '— Format —', 'aaweb-advanced-image-resizer' ),
					'outputFormat' => __( 'Output format', 'aaweb-advanced-image-resizer' ),
					'replace'    => __( 'Replace', 'aaweb-advanced-image-resizer' ),
					'create'     => __( 'Create New', 'aaweb-advanced-image-resizer' ),
					'button'     => __( 'Resize', 'aaweb-advanced-image-resizer' ),
					'modalButton'=> __( 'Resize with AAWEB', 'aaweb-advanced-image-resizer' ),
					'missing'    => __( 'Please select both size and mode.', 'aaweb-advanced-image-resizer' ),
					'working'    => __( 'Resizing...', 'aaweb-advanced-image-resizer' ),
					'done'       => __( 'Done', 'aaweb-advanced-image-resizer' ),
					'error'      => __( 'Error', 'aaweb-advanced-image-resizer' ),
					'confirm'    => __( 'Replace will overwrite the current attachment file. Continue?', 'aaweb-advanced-image-resizer' ),
					'formatMissing' => __( 'Please select an output format.', 'aaweb-advanced-image-resizer' ),
					'backups'    => __( 'Backups', 'aaweb-advanced-image-resizer' ),
					'loadBackups'=> __( 'Load backups', 'aaweb-advanced-image-resizer' ),
					'noBackups'  => __( 'No backups found for this image.', 'aaweb-advanced-image-resizer' ),
					'restore'    => __( 'Restore', 'aaweb-advanced-image-resizer' ),
					'delete'     => __( 'Delete', 'aaweb-advanced-image-resizer' ),
					'restoreConfirm' => __( 'Restore this backup? The current attachment file will be replaced.', 'aaweb-advanced-image-resizer' ),
					'deleteConfirm'  => __( 'Delete this backup file permanently?', 'aaweb-advanced-image-resizer' ),
					'backupRestored' => __( 'Backup restored successfully.', 'aaweb-advanced-image-resizer' ),
					'backupDeleted'  => __( 'Backup deleted successfully.', 'aaweb-advanced-image-resizer' ),
				),
			)
		);
	}

	public function settings_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aaweb-advanced-image-resizer' ) );
		}

		$settings = $this->get_settings();
		?>
		<div class="wrap aaweb-air-wrap">
			<div class="aaweb-air-hero">
				<div>
					<p class="aaweb-air-kicker">AAWEB</p>
					<h1><?php esc_html_e( 'Advanced Image Resizer', 'aaweb-advanced-image-resizer' ); ?></h1>
					<p><?php esc_html_e( 'Resize Media Library images into social, ads and WordPress.org dimensions with padding, bulk tools and safer replace mode.', 'aaweb-advanced-image-resizer' ); ?></p>
				</div>
				<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php esc_html_e( 'Open Media Library', 'aaweb-advanced-image-resizer' ); ?></a>
			</div>

			<div class="aaweb-air-grid">
				<form method="post" action="options.php" class="aaweb-air-card aaweb-air-card-main">
					<?php settings_fields( 'aaweb_air_settings_group' ); ?>
					<h2><?php esc_html_e( 'Settings', 'aaweb-advanced-image-resizer' ); ?></h2>

					<div class="aaweb-air-field">
						<label for="aaweb_air_default_mode"><?php esc_html_e( 'Default mode', 'aaweb-advanced-image-resizer' ); ?></label>
						<select id="aaweb_air_default_mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_mode]">
							<option value="replace" <?php selected( $settings['default_mode'], 'replace' ); ?>><?php esc_html_e( 'Replace', 'aaweb-advanced-image-resizer' ); ?></option>
							<option value="create" <?php selected( $settings['default_mode'], 'create' ); ?>><?php esc_html_e( 'Create New', 'aaweb-advanced-image-resizer' ); ?></option>
						</select>
						<p><?php esc_html_e( 'Replace keeps the same attachment ID. Create New creates a new attachment using the selected output format.', 'aaweb-advanced-image-resizer' ); ?></p>
					</div>

					<div class="aaweb-air-field">
						<label for="aaweb_air_output_format"><?php esc_html_e( 'Default output format', 'aaweb-advanced-image-resizer' ); ?></label>
						<select id="aaweb_air_output_format" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[output_format]">
							<?php echo wp_kses( $this->output_format_options_html( $settings['output_format'] ), array( 'option' => array( 'value' => true, 'selected' => true ) ) ); ?>
						</select>
						<p><?php esc_html_e( 'Choose the default export format for resized images. WebP requires server GD WebP support.', 'aaweb-advanced-image-resizer' ); ?></p>
					</div>

					<div class="aaweb-air-field">
						<label for="aaweb_air_jpg_quality"><?php esc_html_e( 'JPG quality', 'aaweb-advanced-image-resizer' ); ?></label>
						<input id="aaweb_air_jpg_quality" type="number" min="50" max="100" step="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[jpg_quality]" value="<?php echo esc_attr( $settings['jpg_quality'] ); ?>">
						<p><?php esc_html_e( 'Recommended: 90–94. Use 100 only when file size is not important.', 'aaweb-advanced-image-resizer' ); ?></p>
					</div>

					<div class="aaweb-air-field">
						<label for="aaweb_air_webp_quality"><?php esc_html_e( 'WebP quality', 'aaweb-advanced-image-resizer' ); ?></label>
						<input id="aaweb_air_webp_quality" type="number" min="50" max="100" step="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[webp_quality]" value="<?php echo esc_attr( $settings['webp_quality'] ); ?>">
						<p><?php esc_html_e( 'Used only when the output format is WebP.', 'aaweb-advanced-image-resizer' ); ?></p>
					</div>

					<div class="aaweb-air-field">
						<label for="aaweb_air_background_color"><?php esc_html_e( 'Padding background', 'aaweb-advanced-image-resizer' ); ?></label>
						<input id="aaweb_air_background_color" type="color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[background_color]" value="<?php echo esc_attr( $settings['background_color'] ); ?>">
					</div>

					<div class="aaweb-air-field">
						<label for="aaweb_air_max_backups"><?php esc_html_e( 'Maximum backups per image', 'aaweb-advanced-image-resizer' ); ?></label>
						<input id="aaweb_air_max_backups" type="number" min="0" max="50" step="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_backups]" value="<?php echo esc_attr( $settings['max_backups'] ); ?>">
						<p><?php esc_html_e( 'Set 0 to keep unlimited backups. Older backups are deleted automatically when a new backup is created.', 'aaweb-advanced-image-resizer' ); ?></p>
					</div>

					<div class="aaweb-air-toggles">
						<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_backups]" value="1" <?php checked( $settings['enable_backups'], 1 ); ?>> <?php esc_html_e( 'Create backup before Replace', 'aaweb-advanced-image-resizer' ); ?></label>
						<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_row_action]" value="1" <?php checked( $settings['enable_row_action'], 1 ); ?>> <?php esc_html_e( 'Enable row action in Media Library', 'aaweb-advanced-image-resizer' ); ?></label>
						<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_bulk_action]" value="1" <?php checked( $settings['enable_bulk_action'], 1 ); ?>> <?php esc_html_e( 'Enable bulk resize action', 'aaweb-advanced-image-resizer' ); ?></label>
						<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_modal_tool]" value="1" <?php checked( $settings['enable_modal_tool'], 1 ); ?>> <?php esc_html_e( 'Enable tool inside image editor modal', 'aaweb-advanced-image-resizer' ); ?></label>
					</div>

					<div class="aaweb-air-field">
						<label for="aaweb_air_custom_presets"><?php esc_html_e( 'Custom presets', 'aaweb-advanced-image-resizer' ); ?></label>
						<textarea id="aaweb_air_custom_presets" rows="8" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_presets]" placeholder="Pinterest Pin|1000x1500&#10;Custom Banner|1600x900"><?php echo esc_textarea( $settings['custom_presets'] ); ?></textarea>
						<p><?php esc_html_e( 'One preset per line. Format: Label|WIDTHxHEIGHT', 'aaweb-advanced-image-resizer' ); ?></p>
					</div>

					<?php submit_button( __( 'Save Settings', 'aaweb-advanced-image-resizer' ) ); ?>
				</form>

				<div class="aaweb-air-card">
					<h2><?php esc_html_e( 'Available presets', 'aaweb-advanced-image-resizer' ); ?></h2>
					<div class="aaweb-air-presets">
						<?php foreach ( $this->presets() as $preset ) : ?>
							<span><?php echo esc_html( sprintf( '%s %dx%d', $preset['label'], $preset['w'], $preset['h'] ) ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function media_row_actions( $actions, $post ) {
		$settings = $this->get_settings();
		if ( empty( $settings['enable_row_action'] ) || ! $post || 'attachment' !== $post->post_type || 0 !== strpos( (string) $post->post_mime_type, 'image/' ) ) {
			return $actions;
		}

		$allowed_options = array(
			'option' => array(
				'value'    => true,
				'selected' => true,
			),
		);
		$preset_options = wp_kses( $this->preset_options_html(), $allowed_options );
		$format_options = wp_kses( $this->output_format_options_html( $settings['output_format'] ), $allowed_options );

		$actions['aaweb_air_resize'] = sprintf(
			'<div class="aaweb-air-row-action"><select class="aaweb-air-dimension">%s</select><select class="aaweb-air-mode"><option value="replace" %s>%s</option><option value="create" %s>%s</option></select><select class="aaweb-air-output-format">%s</select><button type="button" class="button button-small aaweb-air-resize" data-id="%d">%s</button></div>',
			$preset_options,
			selected( $settings['default_mode'], 'replace', false ),
			esc_html__( 'Replace', 'aaweb-advanced-image-resizer' ),
			selected( $settings['default_mode'], 'create', false ),
			esc_html__( 'Create New', 'aaweb-advanced-image-resizer' ),
			$format_options,
			absint( $post->ID ),
			esc_html__( 'Resize', 'aaweb-advanced-image-resizer' )
		);

		return $actions;
	}

	private function get_hex_rgb( $hex ) {
		$hex = sanitize_hex_color( $hex );
		if ( ! $hex ) {
			$hex = '#ffffff';
		}
		$hex = ltrim( $hex, '#' );
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	private function unique_file_path( $dir, $filename ) {
		$filename = wp_unique_filename( $dir, sanitize_file_name( $filename ) );
		return trailingslashit( $dir ) . $filename;
	}

	private function output_formats() {
		$formats = array(
			'jpg'  => array(
				'label' => __( 'JPG', 'aaweb-advanced-image-resizer' ),
				'ext'   => 'jpg',
				'mime'  => 'image/jpeg',
			),
			'png'  => array(
				'label' => __( 'PNG', 'aaweb-advanced-image-resizer' ),
				'ext'   => 'png',
				'mime'  => 'image/png',
			),
			'webp' => array(
				'label' => __( 'WebP', 'aaweb-advanced-image-resizer' ),
				'ext'   => 'webp',
				'mime'  => 'image/webp',
			),
		);

		if ( ! function_exists( 'imagewebp' ) ) {
			unset( $formats['webp'] );
		}

		return $formats;
	}

	private function output_format_options_html( $selected = '' ) {
		$formats = $this->output_formats();
		if ( empty( $formats[ $selected ] ) ) {
			$selected = 'jpg';
		}

		$html = '';
		foreach ( $formats as $key => $format ) {
			$html .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $selected, $key, false ), esc_html( $format['label'] ) );
		}

		return $html;
	}

	private function save_canvas( $canvas, $file_path, $format ) {
		$settings = $this->get_settings();
		$format   = sanitize_key( $format );

		if ( 'png' === $format ) {
			return imagepng( $canvas, $file_path, 6 );
		}

		if ( 'webp' === $format ) {
			if ( ! function_exists( 'imagewebp' ) ) {
				return false;
			}

			$quality = min( 100, max( 50, absint( $settings['webp_quality'] ) ) );
			return imagewebp( $canvas, $file_path, $quality );
		}

		$quality = min( 100, max( 50, absint( $settings['jpg_quality'] ) ) );
		return imagejpeg( $canvas, $file_path, $quality );
	}

	private function create_backup( $attachment_id, $file_path ) {
		$settings  = $this->get_settings();
		$file_path = wp_normalize_path( (string) $file_path );

		if ( empty( $settings['enable_backups'] ) || ! file_exists( $file_path ) || ! $this->is_upload_file( $file_path ) ) {
			return '';
		}

		$dir = $this->get_backup_dir();
		if ( empty( $dir ) || ! wp_mkdir_p( $dir ) || ! $this->is_upload_file( $dir ) ) {
			return '';
		}

		$info   = pathinfo( $file_path );
		$ext    = isset( $info['extension'] ) ? '.' . sanitize_file_name( $info['extension'] ) : '';
		$base   = isset( $info['filename'] ) ? sanitize_file_name( $info['filename'] ) : 'image';
		$backup = $this->unique_file_path( $dir, $attachment_id . '-' . $base . '-aaweb-air-backup-' . gmdate( 'Ymd-His' ) . $ext );

		if ( ! $this->is_upload_file( $backup ) ) {
			return '';
		}

		$copied = copy( $file_path, $backup );

		if ( ! $copied ) {
			return '';
		}

		$backups   = get_post_meta( $attachment_id, self::BACKUP_META, true );
		$backups   = is_array( $backups ) ? $backups : array();
		$backups[] = array(
			'file'          => $backup,
			'original_file' => $file_path,
			'time'          => current_time( 'mysql' ),
		);

		$max_backups = isset( $settings['max_backups'] ) ? absint( $settings['max_backups'] ) : 10;
		if ( $max_backups > 0 && count( $backups ) > $max_backups ) {
			$remove_count = count( $backups ) - $max_backups;
			$old_backups  = array_splice( $backups, 0, $remove_count );
			foreach ( $old_backups as $old_backup ) {
				if ( ! empty( $old_backup['file'] ) && $this->is_upload_file( $old_backup['file'] ) && file_exists( $old_backup['file'] ) ) {
					wp_delete_file( $old_backup['file'] );
				}
			}
		}

		update_post_meta( $attachment_id, self::BACKUP_META, $backups );

		return $backup;
	}

	private function get_backup_dir() {
		$upload_dir = wp_get_upload_dir();

		if ( empty( $upload_dir['basedir'] ) ) {
			return '';
		}

		return trailingslashit( wp_normalize_path( $upload_dir['basedir'] ) ) . 'aaweb-advanced-image-resizer/backups/';
	}

	private function is_upload_file( $file ) {
		$upload_dir = wp_get_upload_dir();

		if ( empty( $upload_dir['basedir'] ) ) {
			return false;
		}

		$base_dir = trailingslashit( wp_normalize_path( $upload_dir['basedir'] ) );
		$file     = wp_normalize_path( (string) $file );

		return '' !== $file && 0 === strpos( trailingslashit( $file ), $base_dir );
	}

	private function get_backup_items( $attachment_id ) {
		$backups = get_post_meta( $attachment_id, self::BACKUP_META, true );
		$backups = is_array( $backups ) ? $backups : array();
		$items   = array();

		foreach ( $backups as $index => $backup ) {
			$file = isset( $backup['file'] ) ? wp_normalize_path( $backup['file'] ) : '';
			if ( '' === $file || ! $this->is_upload_file( $file ) ) {
				continue;
			}

			$exists = file_exists( $file );
			$items[] = array(
				'index'  => (int) $index,
				'file'   => $file,
				'name'   => basename( $file ),
				'time'   => isset( $backup['time'] ) ? sanitize_text_field( $backup['time'] ) : '',
				'size'   => $exists ? size_format( filesize( $file ) ) : '',
				'exists' => $exists,
			);
		}

		return $items;
	}

	private function get_backup_by_index( $attachment_id, $backup_index ) {
		$backups = get_post_meta( $attachment_id, self::BACKUP_META, true );
		$backups = is_array( $backups ) ? $backups : array();

		if ( ! isset( $backups[ $backup_index ] ) || empty( $backups[ $backup_index ]['file'] ) ) {
			return new WP_Error( 'backup_missing', __( 'Backup not found.', 'aaweb-advanced-image-resizer' ) );
		}

		$file = wp_normalize_path( $backups[ $backup_index ]['file'] );
		if ( ! $this->is_upload_file( $file ) || ! file_exists( $file ) ) {
			return new WP_Error( 'backup_file_missing', __( 'Backup file is missing.', 'aaweb-advanced-image-resizer' ) );
		}

		$backups[ $backup_index ]['file'] = $file;
		return $backups[ $backup_index ];
	}

	public function restore_backup( $attachment_id, $backup_index ) {
		$attachment_id = absint( $attachment_id );
		$backup_index  = absint( $backup_index );
		$current_file  = get_attached_file( $attachment_id );

		if ( ! $attachment_id || ! $current_file || ! file_exists( $current_file ) ) {
			return new WP_Error( 'file_missing', __( 'Current attachment file not found.', 'aaweb-advanced-image-resizer' ) );
		}

		$current_file = wp_normalize_path( $current_file );
		if ( ! $this->is_upload_file( $current_file ) ) {
			return new WP_Error( 'invalid_attachment_path', __( 'Current attachment file is outside the uploads directory.', 'aaweb-advanced-image-resizer' ) );
		}

		$backup = $this->get_backup_by_index( $attachment_id, $backup_index );
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}

		$backup_file = $backup['file'];
		$backup_info = wp_check_filetype( $backup_file );
		if ( empty( $backup_info['type'] ) || 0 !== strpos( $backup_info['type'], 'image/' ) ) {
			return new WP_Error( 'invalid_backup_type', __( 'Invalid backup image type.', 'aaweb-advanced-image-resizer' ) );
		}

		$current_ext  = strtolower( pathinfo( $current_file, PATHINFO_EXTENSION ) );
		$backup_ext   = strtolower( pathinfo( $backup_file, PATHINFO_EXTENSION ) );
		$target_file  = $current_file;

		if ( $current_ext !== $backup_ext ) {
			$target_file = trailingslashit( dirname( $current_file ) ) . sanitize_file_name( pathinfo( $current_file, PATHINFO_FILENAME ) . '.' . $backup_ext );
		}

		if ( ! $this->is_upload_file( $backup_file ) || ! $this->is_upload_file( $target_file ) ) {
			return new WP_Error( 'invalid_restore_path', __( 'Restore paths must be inside the uploads directory.', 'aaweb-advanced-image-resizer' ) );
		}

		$this->delete_attachment_thumbnails( $attachment_id, $current_file );

		if ( ! copy( $backup_file, $target_file ) ) {
			return new WP_Error( 'restore_failed', __( 'Failed to restore backup file.', 'aaweb-advanced-image-resizer' ) );
		}

		if ( wp_normalize_path( $target_file ) !== $current_file && file_exists( $current_file ) ) {
			wp_delete_file( $current_file );
		}

		update_attached_file( $attachment_id, $target_file );
		wp_update_post(
			array(
				'ID'             => $attachment_id,
				'post_mime_type' => $backup_info['type'],
			)
		);

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$meta = wp_generate_attachment_metadata( $attachment_id, $target_file );
		wp_update_attachment_metadata( $attachment_id, $meta );

		return array( 'message' => __( 'Backup restored successfully.', 'aaweb-advanced-image-resizer' ) );
	}

	public function delete_backup( $attachment_id, $backup_index ) {
		$attachment_id = absint( $attachment_id );
		$backup_index  = absint( $backup_index );
		$backups       = get_post_meta( $attachment_id, self::BACKUP_META, true );
		$backups       = is_array( $backups ) ? $backups : array();

		if ( ! isset( $backups[ $backup_index ] ) || empty( $backups[ $backup_index ]['file'] ) ) {
			return new WP_Error( 'backup_missing', __( 'Backup not found.', 'aaweb-advanced-image-resizer' ) );
		}

		$file = wp_normalize_path( $backups[ $backup_index ]['file'] );
		if ( $this->is_upload_file( $file ) && file_exists( $file ) ) {
			wp_delete_file( $file );
		}

		unset( $backups[ $backup_index ] );
		$backups = array_values( $backups );
		update_post_meta( $attachment_id, self::BACKUP_META, $backups );

		return array( 'message' => __( 'Backup deleted successfully.', 'aaweb-advanced-image-resizer' ) );
	}

	private function delete_attachment_thumbnails( $attachment_id, $original_file ) {
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) || empty( $meta['sizes'] ) || empty( $meta['file'] ) ) {
			return;
		}

		$upload_dir = wp_get_upload_dir();
		$base_dir   = trailingslashit( wp_normalize_path( $upload_dir['basedir'] ) );
		$subdir     = trailingslashit( dirname( wp_normalize_path( $meta['file'] ) ) );
		$original   = wp_normalize_path( $original_file );

		foreach ( $meta['sizes'] as $size ) {
			if ( empty( $size['file'] ) ) {
				continue;
			}

			$thumb = wp_normalize_path( $base_dir . $subdir . $size['file'] );
			if ( $thumb !== $original && file_exists( $thumb ) ) {
				wp_delete_file( $thumb );
			}
		}
	}

	public function resize_one_attachment( $attachment_id, $dimension_key, $mode = 'replace', $output_format = '' ) {
		$attachment_id = absint( $attachment_id );
		$dimension_key = sanitize_key( $dimension_key );
		$mode          = in_array( $mode, array( 'replace', 'create' ), true ) ? $mode : 'replace';
		$settings      = $this->get_settings();
		$output_format = $output_format ? sanitize_key( $output_format ) : sanitize_key( $settings['output_format'] );
		$formats       = $this->output_formats();

		if ( empty( $formats[ $output_format ] ) ) {
			$output_format = 'jpg';
		}

		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return new WP_Error( 'gd_missing', __( 'The GD image library is not available on this server.', 'aaweb-advanced-image-resizer' ) );
		}

		$output_ext  = $formats[ $output_format ]['ext'];
		$output_mime = $formats[ $output_format ]['mime'];

		$presets = $this->presets();
		if ( empty( $presets[ $dimension_key ] ) ) {
			return new WP_Error( 'invalid_dimension', __( 'Invalid dimension key.', 'aaweb-advanced-image-resizer' ) );
		}

		$new_width  = absint( $presets[ $dimension_key ]['w'] );
		$new_height = absint( $presets[ $dimension_key ]['h'] );
		$file_path  = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_missing', __( 'File not found.', 'aaweb-advanced-image-resizer' ) );
		}

		$file_path = wp_normalize_path( $file_path );
		if ( ! $this->is_upload_file( $file_path ) ) {
			return new WP_Error( 'invalid_attachment_path', __( 'Attachment file is outside the uploads directory.', 'aaweb-advanced-image-resizer' ) );
		}

		$image_info = wp_getimagesize( $file_path );
		if ( ! $image_info || empty( $image_info['mime'] ) ) {
			return new WP_Error( 'invalid_image', __( 'Unsupported or invalid image.', 'aaweb-advanced-image-resizer' ) );
		}

		$mime_type = $image_info['mime'];
		switch ( $mime_type ) {
			case 'image/jpeg':
				$image = imagecreatefromjpeg( $file_path );
				break;
			case 'image/png':
				$image = imagecreatefrompng( $file_path );
				break;
			case 'image/gif':
				$image = imagecreatefromgif( $file_path );
				break;
			case 'image/webp':
				if ( ! function_exists( 'imagecreatefromwebp' ) ) {
					return new WP_Error( 'webp_unsupported', __( 'WebP is not supported on this server.', 'aaweb-advanced-image-resizer' ) );
				}
				$image = imagecreatefromwebp( $file_path );
				break;
			default:
				return new WP_Error( 'mime_unsupported', __( 'Unsupported image type.', 'aaweb-advanced-image-resizer' ) );
		}

		if ( ! $image ) {
			return new WP_Error( 'open_failed', __( 'Failed to open image.', 'aaweb-advanced-image-resizer' ) );
		}

		$ow = imagesx( $image );
		$oh = imagesy( $image );
		if ( $ow < 1 || $oh < 1 ) {
			imagedestroy( $image );
			return new WP_Error( 'invalid_size', __( 'Invalid image size.', 'aaweb-advanced-image-resizer' ) );
		}

		if ( $ow / $oh > $new_width / $new_height ) {
			$scaled_w = $new_width;
			$scaled_h = (int) round( $new_width / ( $ow / $oh ) );
		} else {
			$scaled_h = $new_height;
			$scaled_w = (int) round( $new_height * ( $ow / $oh ) );
		}

		$resized = imagecreatetruecolor( $scaled_w, $scaled_h );
		imagecopyresampled( $resized, $image, 0, 0, 0, 0, $scaled_w, $scaled_h, $ow, $oh );

		$canvas = imagecreatetruecolor( $new_width, $new_height );
		list( $r, $g, $b ) = $this->get_hex_rgb( $this->get_settings()['background_color'] );
		$bg = imagecolorallocate( $canvas, $r, $g, $b );
		imagefill( $canvas, 0, 0, $bg );

		$dx = (int) floor( ( $new_width - $scaled_w ) / 2 );
		$dy = (int) floor( ( $new_height - $scaled_h ) / 2 );
		imagecopy( $canvas, $resized, $dx, $dy, 0, 0, $scaled_w, $scaled_h );

		$upload_dir = wp_upload_dir();

		if ( 'replace' === $mode ) {
			$this->create_backup( $attachment_id, $file_path );

			$new_file_name = pathinfo( $file_path, PATHINFO_FILENAME ) . '.' . $output_ext;
			$new_file_path = trailingslashit( dirname( $file_path ) ) . sanitize_file_name( $new_file_name );

			if ( ! $this->is_upload_file( $new_file_path ) ) {
				imagedestroy( $image );
				imagedestroy( $resized );
				imagedestroy( $canvas );

				return new WP_Error( 'invalid_output_path', __( 'Output file path is outside the uploads directory.', 'aaweb-advanced-image-resizer' ) );
			}

			$saved = $this->save_canvas( $canvas, $new_file_path, $output_format );

			imagedestroy( $image );
			imagedestroy( $resized );
			imagedestroy( $canvas );

			if ( ! $saved ) {
				return new WP_Error( 'save_failed', __( 'Failed to save resized image file.', 'aaweb-advanced-image-resizer' ) );
			}

			$this->delete_attachment_thumbnails( $attachment_id, $file_path );

			if ( wp_normalize_path( $file_path ) !== wp_normalize_path( $new_file_path ) && file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}

			update_attached_file( $attachment_id, $new_file_path );
			wp_update_post( array( 'ID' => $attachment_id, 'post_mime_type' => $output_mime ) );

			require_once ABSPATH . 'wp-admin/includes/image.php';
			$meta = wp_generate_attachment_metadata( $attachment_id, $new_file_path );
			wp_update_attachment_metadata( $attachment_id, $meta );

			return array( 'message' => __( 'Image replaced successfully.', 'aaweb-advanced-image-resizer' ) );
		}

		$original_name = sanitize_title( pathinfo( $file_path, PATHINFO_FILENAME ) );
		if ( '' === $original_name ) {
			$original_name = 'image';
		}

		$preset_slug = sanitize_title( str_replace( '_', '-', $dimension_key ) );
		if ( '' === $preset_slug ) {
			$preset_slug = 'resized';
		}

		$unique_name = sprintf(
			'%1$s-%2$s-%3$dx%4$d.%5$s',
			$original_name,
			$preset_slug,
			$new_width,
			$new_height,
			$output_ext
		);
		$new_file_path = $this->unique_file_path( $upload_dir['path'], $unique_name );

		if ( ! $this->is_upload_file( $new_file_path ) ) {
			imagedestroy( $image );
			imagedestroy( $resized );
			imagedestroy( $canvas );

			return new WP_Error( 'invalid_output_path', __( 'Output file path is outside the uploads directory.', 'aaweb-advanced-image-resizer' ) );
		}

		$saved = $this->save_canvas( $canvas, $new_file_path, $output_format );

		imagedestroy( $image );
		imagedestroy( $resized );
		imagedestroy( $canvas );

		if ( ! $saved ) {
			return new WP_Error( 'save_failed', __( 'Failed to save new resized image file.', 'aaweb-advanced-image-resizer' ) );
		}

		$attachment = array(
			'post_mime_type' => $output_mime,
			'post_title'     => sanitize_text_field( pathinfo( $new_file_path, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$new_attach_id = wp_insert_attachment( $attachment, $new_file_path );
		if ( is_wp_error( $new_attach_id ) || ! $new_attach_id ) {
			wp_delete_file( $new_file_path );
			return new WP_Error( 'insert_failed', __( 'Failed to create attachment.', 'aaweb-advanced-image-resizer' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$meta = wp_generate_attachment_metadata( $new_attach_id, $new_file_path );
		wp_update_attachment_metadata( $new_attach_id, $meta );

		return array(
			'message'       => __( 'New resized image created successfully.', 'aaweb-advanced-image-resizer' ),
			'attachment_id' => $new_attach_id,
		);
	}

	private function current_user_can_edit_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		return $attachment_id && current_user_can( 'upload_files' ) && current_user_can( 'edit_post', $attachment_id );
	}

	public function ajax_get_backups() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'aaweb-advanced-image-resizer' ) ) );
		}

		check_ajax_referer( self::NONCE_AJAX, 'nonce' );

		$id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Missing attachment ID.', 'aaweb-advanced-image-resizer' ) ) );
		}

		if ( ! $this->current_user_can_edit_attachment( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this image.', 'aaweb-advanced-image-resizer' ) ) );
		}

		wp_send_json_success( array( 'backups' => $this->get_backup_items( $id ) ) );
	}

	public function ajax_restore_backup() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'aaweb-advanced-image-resizer' ) ) );
		}

		check_ajax_referer( self::NONCE_AJAX, 'nonce' );

		$id    = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		$index = isset( $_POST['backup_index'] ) ? absint( wp_unslash( $_POST['backup_index'] ) ) : 0;

		if ( ! $this->current_user_can_edit_attachment( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this image.', 'aaweb-advanced-image-resizer' ) ) );
		}

		$res = $this->restore_backup( $id, $index );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		wp_send_json_success( $res );
	}

	public function ajax_delete_backup() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'aaweb-advanced-image-resizer' ) ) );
		}

		check_ajax_referer( self::NONCE_AJAX, 'nonce' );

		$id    = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		$index = isset( $_POST['backup_index'] ) ? absint( wp_unslash( $_POST['backup_index'] ) ) : 0;

		if ( ! $this->current_user_can_edit_attachment( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this image.', 'aaweb-advanced-image-resizer' ) ) );
		}

		$res = $this->delete_backup( $id, $index );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		wp_send_json_success( $res );
	}

	public function ajax_resize_image() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'aaweb-advanced-image-resizer' ) ) );
		}

		check_ajax_referer( self::NONCE_AJAX, 'nonce' );

		$id   = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		$dim  = isset( $_POST['dimension'] ) ? sanitize_key( wp_unslash( $_POST['dimension'] ) ) : '';
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'replace';
		$output_format = isset( $_POST['output_format'] ) ? sanitize_key( wp_unslash( $_POST['output_format'] ) ) : '';

		if ( ! $id || ! $dim ) {
			wp_send_json_error( array( 'message' => __( 'Missing parameters.', 'aaweb-advanced-image-resizer' ) ) );
		}

		if ( ! $this->current_user_can_edit_attachment( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this image.', 'aaweb-advanced-image-resizer' ) ) );
		}

		$res = $this->resize_one_attachment( $id, $dim, $mode, $output_format );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		wp_send_json_success( $res );
	}

	public function register_bulk_action( $bulk_actions ) {
		$settings = $this->get_settings();
		if ( ! empty( $settings['enable_bulk_action'] ) ) {
			$bulk_actions['aaweb_air_bulk_resize'] = __( 'AAWEB Resize with Padding', 'aaweb-advanced-image-resizer' );
		}
		return $bulk_actions;
	}

	public function bulk_controls( $post_type, $which ) {
		$settings = $this->get_settings();
		if ( 'attachment' !== $post_type || 'top' !== $which || empty( $settings['enable_bulk_action'] ) ) {
			return;
		}

		echo '<select name="aaweb_air_bulk_dimension" id="aaweb_air_bulk_dimension" class="aaweb-air-bulk-control">' . wp_kses( $this->preset_options_html(), array( 'option' => array( 'value' => true, 'selected' => true ) ) ) . '</select>';
		echo '<select name="aaweb_air_bulk_mode" id="aaweb_air_bulk_mode" class="aaweb-air-bulk-control">';
		echo '<option value="">' . esc_html__( '— Mode —', 'aaweb-advanced-image-resizer' ) . '</option>';
		echo '<option value="replace" ' . selected( $settings['default_mode'], 'replace', false ) . '>' . esc_html__( 'Replace', 'aaweb-advanced-image-resizer' ) . '</option>';
		echo '<option value="create" ' . selected( $settings['default_mode'], 'create', false ) . '>' . esc_html__( 'Create New', 'aaweb-advanced-image-resizer' ) . '</option>';
		echo '</select>';
		echo '<select name="aaweb_air_bulk_output_format" id="aaweb_air_bulk_output_format" class="aaweb-air-bulk-control">' . wp_kses( $this->output_format_options_html( $settings['output_format'] ), array( 'option' => array( 'value' => true, 'selected' => true ) ) ) . '</select>';
		wp_nonce_field( self::NONCE_BULK, 'aaweb_air_bulk_nonce' );
	}

	public function handle_bulk_action( $redirect_url, $action, $ids ) {
		if ( 'aaweb_air_bulk_resize' !== $action ) {
			return $redirect_url;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return add_query_arg( 'aaweb_air_bulk_error', rawurlencode( __( 'Permission denied.', 'aaweb-advanced-image-resizer' ) ), $redirect_url );
		}

		if ( empty( $_REQUEST['aaweb_air_bulk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['aaweb_air_bulk_nonce'] ) ), self::NONCE_BULK ) ) {
			return add_query_arg( 'aaweb_air_bulk_error', rawurlencode( __( 'Invalid nonce.', 'aaweb-advanced-image-resizer' ) ), $redirect_url );
		}

		$dimension     = isset( $_REQUEST['aaweb_air_bulk_dimension'] ) ? sanitize_key( wp_unslash( $_REQUEST['aaweb_air_bulk_dimension'] ) ) : '';
		$mode          = isset( $_REQUEST['aaweb_air_bulk_mode'] ) ? sanitize_key( wp_unslash( $_REQUEST['aaweb_air_bulk_mode'] ) ) : 'replace';
		$output_format = isset( $_REQUEST['aaweb_air_bulk_output_format'] ) ? sanitize_key( wp_unslash( $_REQUEST['aaweb_air_bulk_output_format'] ) ) : '';
		$formats       = $this->output_formats();

		if ( ! $dimension ) {
			return add_query_arg( 'aaweb_air_bulk_error', rawurlencode( __( 'Please select a size before applying the bulk action.', 'aaweb-advanced-image-resizer' ) ), $redirect_url );
		}

		if ( ! in_array( $mode, array( 'replace', 'create' ), true ) ) {
			return add_query_arg( 'aaweb_air_bulk_error', rawurlencode( __( 'Please select a valid mode.', 'aaweb-advanced-image-resizer' ) ), $redirect_url );
		}

		if ( empty( $formats[ $output_format ] ) ) {
			return add_query_arg( 'aaweb_air_bulk_error', rawurlencode( __( 'Please select a valid output format.', 'aaweb-advanced-image-resizer' ) ), $redirect_url );
		}

		$success = 0;
		$failed  = 0;

		foreach ( (array) $ids as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			if ( ! $this->current_user_can_edit_attachment( $attachment_id ) ) {
				$failed++;
				continue;
			}

			$res = $this->resize_one_attachment( $attachment_id, $dimension, $mode, $output_format );
			if ( is_wp_error( $res ) ) {
				$failed++;
			} else {
				$success++;
			}
		}

		return add_query_arg(
			array(
				'aaweb_air_bulk_done'   => 1,
				'aaweb_air_bulk_ok'     => $success,
				'aaweb_air_bulk_failed' => $failed,
			),
			$redirect_url
		);
	}

	public function admin_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notice reads redirect query args generated by this plugin.
		if ( ! empty( $_GET['aaweb_air_bulk_error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notice reads redirect query args generated by this plugin.
			$error = sanitize_text_field( wp_unslash( $_GET['aaweb_air_bulk_error'] ) );
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $error ) );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notice reads redirect query args generated by this plugin.
		if ( empty( $_GET['aaweb_air_bulk_done'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notice reads redirect query args generated by this plugin.
		$ok  = isset( $_GET['aaweb_air_bulk_ok'] ) ? absint( $_GET['aaweb_air_bulk_ok'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notice reads redirect query args generated by this plugin.
		$bad = isset( $_GET['aaweb_air_bulk_failed'] ) ? absint( $_GET['aaweb_air_bulk_failed'] ) : 0;
		/* translators: 1: number of successfully resized images, 2: number of failed image resize attempts. */
		$msg = sprintf( __( 'AAWEB Image Resizer bulk complete: %1$d succeeded, %2$d failed.', 'aaweb-advanced-image-resizer' ), $ok, $bad );
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $msg ) );
	}
}

AAWEB_Advanced_Image_Resizer::instance();
