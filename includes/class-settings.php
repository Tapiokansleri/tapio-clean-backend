<?php
/**
 * Tools settings page and stored feature toggles.
 *
 * @package Tapio\CleanBackend
 */

namespace Tapio\CleanBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Tools > Tapio Clean Backend and reads the saved switches.
 */
class Settings {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION = 'tapio_clean_backend_settings';

	/**
	 * Settings page slug (Tools submenu).
	 *
	 * @var string
	 */
	const PAGE = 'tapio-clean-backend';

	/**
	 * Settings group used by the Settings API.
	 *
	 * @var string
	 */
	const GROUP = 'tapio_clean_backend';

	/**
	 * Default feature switches (both on).
	 *
	 * @return array{collapse_sidebar: bool, relocate_notices: bool}
	 */
	public static function defaults() {
		return array(
			'collapse_sidebar' => true,
			'relocate_notices' => true,
		);
	}

	/**
	 * Returns merged settings.
	 *
	 * @return array{collapse_sidebar: bool, relocate_notices: bool}
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = wp_parse_args( $saved, self::defaults() );

		return array(
			'collapse_sidebar' => ! empty( $settings['collapse_sidebar'] ),
			'relocate_notices' => ! empty( $settings['relocate_notices'] ),
		);
	}

	/**
	 * Whether the More sidebar accordion is enabled.
	 *
	 * @return bool
	 */
	public static function is_sidebar_enabled() {
		$settings = self::get();
		return $settings['collapse_sidebar'];
	}

	/**
	 * Whether notice relocation is enabled.
	 *
	 * @return bool
	 */
	public static function is_notices_enabled() {
		$settings = self::get();
		return $settings['relocate_notices'];
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TAPIO_CLEAN_BACKEND_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$url = admin_url( 'tools.php?page=' . self::PAGE );

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'tapio-clean-backend' )
			)
		);

		return $links;
	}

	/**
	 * Adds the Tools submenu page.
	 *
	 * @return void
	 */
	public function add_page() {
		add_management_page(
			__( 'Tapio Clean Backend', 'tapio-clean-backend' ),
			__( 'Tapio Clean Backend', 'tapio-clean-backend' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers the option and fields.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'tapio_clean_backend_features',
			__( 'Features', 'tapio-clean-backend' ),
			array( $this, 'render_section' ),
			self::PAGE
		);

		add_settings_field(
			'collapse_sidebar',
			__( 'Sidebar More', 'tapio-clean-backend' ),
			array( $this, 'render_collapse_sidebar_field' ),
			self::PAGE,
			'tapio_clean_backend_features'
		);

		add_settings_field(
			'relocate_notices',
			__( 'Notifications', 'tapio-clean-backend' ),
			array( $this, 'render_relocate_notices_field' ),
			self::PAGE,
			'tapio_clean_backend_features'
		);
	}

	/**
	 * Sanitizes posted settings. Unchecked boxes are treated as off.
	 *
	 * @param mixed $input Raw option value.
	 * @return array{collapse_sidebar: bool, relocate_notices: bool}
	 */
	public function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		return array(
			'collapse_sidebar' => ! empty( $input['collapse_sidebar'] ),
			'relocate_notices' => ! empty( $input['relocate_notices'] ),
		);
	}

	/**
	 * Section intro.
	 *
	 * @return void
	 */
	public function render_section() {
		echo '<p>' . esc_html__( 'Nothing is removed. These switches only choose which rearrangements are active.', 'tapio-clean-backend' ) . '</p>';
	}

	/**
	 * Sidebar checkbox.
	 *
	 * @return void
	 */
	public function render_collapse_sidebar_field() {
		$settings = self::get();
		$name     = self::OPTION . '[collapse_sidebar]';
		?>
		<label for="tapio-clean-backend-collapse-sidebar">
			<input
				type="checkbox"
				id="tapio-clean-backend-collapse-sidebar"
				name="<?php echo esc_attr( $name ); ?>"
				value="1"
				<?php checked( $settings['collapse_sidebar'] ); ?>
			/>
			<?php esc_html_e( 'Collapse plugin and custom post type menus under More', 'tapio-clean-backend' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Core menus stay in the sidebar. Plugin menus remain available inside the More section.', 'tapio-clean-backend' ); ?>
		</p>
		<?php
	}

	/**
	 * Notices checkbox.
	 *
	 * @return void
	 */
	public function render_relocate_notices_field() {
		$settings = self::get();
		$name     = self::OPTION . '[relocate_notices]';
		?>
		<label for="tapio-clean-backend-relocate-notices">
			<input
				type="checkbox"
				id="tapio-clean-backend-relocate-notices"
				name="<?php echo esc_attr( $name ); ?>"
				value="1"
				<?php checked( $settings['relocate_notices'] ); ?>
			/>
			<?php esc_html_e( 'Move admin notices into the Notifications panel in the admin bar', 'tapio-clean-backend' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Notices are not discarded. They appear in the admin bar with a badge count.', 'tapio-clean-backend' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the Tools settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
