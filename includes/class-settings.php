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
	 * Hook suffix of the settings page, set when the page is added.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Default feature switches (both on), no menus kept out of More and no custom order.
	 *
	 * @return array{collapse_sidebar: bool, relocate_notices: bool, keep_visible: string[], menu_order: string[]}
	 */
	public static function defaults() {
		return array(
			'collapse_sidebar' => true,
			'relocate_notices' => true,
			'keep_visible'     => array(),
			'menu_order'       => array(),
		);
	}

	/**
	 * Returns merged settings.
	 *
	 * @return array{collapse_sidebar: bool, relocate_notices: bool, keep_visible: string[], menu_order: string[]}
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
			'keep_visible'     => self::clean_slugs( $settings['keep_visible'] ),
			'menu_order'       => self::clean_slugs( $settings['menu_order'] ),
		);
	}

	/**
	 * Menu slugs the user keeps in the main sidebar instead of More.
	 *
	 * @return string[]
	 */
	public static function get_kept_slugs() {
		$settings = self::get();
		return $settings['keep_visible'];
	}

	/**
	 * Plugin and custom post type menu slugs in the order the user dragged them.
	 *
	 * @return string[]
	 */
	public static function get_menu_order() {
		$settings = self::get();
		return $settings['menu_order'];
	}

	/**
	 * Keeps plausible menu slugs from a stored or posted list.
	 *
	 * Menu slugs are file names or admin URLs such as admin.php?page=x, so
	 * anything with markup, quotes or whitespace is dropped.
	 *
	 * @param mixed $slugs Raw list.
	 * @return string[]
	 */
	private static function clean_slugs( $slugs ) {
		if ( ! is_array( $slugs ) ) {
			return array();
		}

		$clean = array();
		foreach ( $slugs as $slug ) {
			if ( ! is_string( $slug ) ) {
				continue;
			}

			$slug = trim( $slug );
			if ( '' === $slug || strlen( $slug ) > 200 || preg_match( '/[<>"\'\s]/', $slug ) ) {
				continue;
			}

			$clean[] = $slug;
		}

		return array_values( array_unique( $clean ) );
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TAPIO_CLEAN_BACKEND_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Loads the sortable list script and styles on the settings page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'tapio-clean-backend-settings',
			TAPIO_CLEAN_BACKEND_URL . 'assets/css/admin-settings.css',
			array( 'dashicons' ),
			TAPIO_CLEAN_BACKEND_VERSION
		);

		wp_enqueue_script(
			'tapio-clean-backend-settings',
			TAPIO_CLEAN_BACKEND_URL . 'assets/js/admin-settings.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			TAPIO_CLEAN_BACKEND_VERSION,
			true
		);

		wp_localize_script(
			'tapio-clean-backend-settings',
			'tapioCleanBackendSettings',
			array(
				/* translators: 1: menu name, 2: new position, 3: number of menus */
				'moved' => __( '%1$s is now at position %2$d of %3$d.', 'tapio-clean-backend' ),
				'reset' => __( 'Default order restored. Save changes to keep it.', 'tapio-clean-backend' ),
			)
		);
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
		$this->hook_suffix = (string) add_management_page(
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
			'keep_visible',
			__( 'Plugin menus', 'tapio-clean-backend' ),
			array( $this, 'render_keep_visible_field' ),
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
	 * @return array{collapse_sidebar: bool, relocate_notices: bool, keep_visible: string[], menu_order: string[]}
	 */
	public function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		return array(
			'collapse_sidebar' => ! empty( $input['collapse_sidebar'] ),
			'relocate_notices' => ! empty( $input['relocate_notices'] ),
			'keep_visible'     => self::clean_slugs( isset( $input['keep_visible'] ) ? $input['keep_visible'] : array() ),
			'menu_order'       => self::clean_slugs( isset( $input['menu_order'] ) ? $input['menu_order'] : array() ),
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
	 * Sortable list of plugin and custom post type menus.
	 *
	 * Each row has a drag handle for the order and a checkbox that keeps the
	 * menu in the main sidebar instead of moving it under More. The hidden
	 * menu_order inputs are posted in the order the rows are in.
	 *
	 * @return void
	 */
	public function render_keep_visible_field() {
		$settings   = self::get();
		$classifier = new Menu_Classifier();
		$menus      = $classifier->get_candidate_menus();

		if ( empty( $menus ) ) {
			echo '<p>' . esc_html__( 'No plugin or custom post type menus were found.', 'tapio-clean-backend' ) . '</p>';
			return;
		}

		// Positions in the order WordPress built, for the Restore default order button.
		$default = Menu_Order::get_default_order();
		$default = is_array( $default ) ? $default : array_keys( $menus );
		$index   = 0;
		?>
		<p class="description" id="tapio-clean-backend-menus-help">
			<?php esc_html_e( 'Drag the rows to change the order of these menus in the sidebar and under More. Ticked menus stay in the main sidebar. The rest go under More when Sidebar More is on.', 'tapio-clean-backend' ); ?>
		</p>
		<ul class="tapio-clean-backend-menus" id="tapio-clean-backend-menus" aria-describedby="tapio-clean-backend-menus-help">
			<?php
			foreach ( $menus as $slug => $menu_item ) {
				$id       = 'tapio-clean-backend-keep-' . md5( $slug );
				$position = array_search( $slug, $default, true );
				?>
				<li
					class="tapio-clean-backend-menu"
					data-title="<?php echo esc_attr( $menu_item['title'] ); ?>"
					data-default-index="<?php echo esc_attr( (string) ( false === $position ? 1000 + $index : $position ) ); ?>"
				>
					<button
						type="button"
						class="tapio-clean-backend-handle"
						aria-describedby="tapio-clean-backend-keyboard-help"
						aria-label="<?php /* translators: %s: menu name */ echo esc_attr( sprintf( __( 'Move %s', 'tapio-clean-backend' ), $menu_item['title'] ) ); ?>"
					>
						<span class="dashicons dashicons-menu" aria-hidden="true"></span>
					</button>
					<input type="hidden" name="<?php echo esc_attr( self::OPTION . '[menu_order][]' ); ?>" value="<?php echo esc_attr( $slug ); ?>" />
					<label for="<?php echo esc_attr( $id ); ?>">
						<input
							type="checkbox"
							id="<?php echo esc_attr( $id ); ?>"
							name="<?php echo esc_attr( self::OPTION . '[keep_visible][]' ); ?>"
							value="<?php echo esc_attr( $slug ); ?>"
							<?php checked( $classifier->is_kept_menu( $slug, $settings['keep_visible'] ) ); ?>
						/>
						<?php $this->render_menu_icon( $menu_item['icon'] ); ?>
						<span class="screen-reader-text"><?php esc_html_e( 'Keep in the sidebar:', 'tapio-clean-backend' ); ?></span>
						<?php echo esc_html( $menu_item['title'] ); ?>
					</label>
				</li>
				<?php
				++$index;
			}
			?>
		</ul>
		<p class="description" id="tapio-clean-backend-keyboard-help">
			<?php esc_html_e( 'To move a row with the keyboard, focus its handle and press the up or down arrow key.', 'tapio-clean-backend' ); ?>
		</p>
		<p>
			<button type="button" class="button-link" id="tapio-clean-backend-reset-order">
				<?php esc_html_e( 'Restore default order', 'tapio-clean-backend' ); ?>
			</button>
		</p>
		<p class="screen-reader-text" id="tapio-clean-backend-order-status" aria-live="polite"></p>
		<?php
	}

	/**
	 * Small version of a menu icon, so the list is easy to match with the sidebar.
	 *
	 * @param string $icon Icon from $menu[n][6]: a dashicons class, an SVG data URI or an image URL.
	 * @return void
	 */
	private function render_menu_icon( $icon ) {
		$icon = (string) $icon;

		if ( 0 === strpos( $icon, 'dashicons-' ) ) {
			printf( '<span class="dashicons %s tapio-clean-backend-menu-icon" aria-hidden="true"></span>', esc_attr( $icon ) );
			return;
		}

		if ( 0 === strpos( $icon, 'data:image/svg+xml;base64,' ) ) {
			printf( '<img class="tapio-clean-backend-menu-icon" src="%s" alt="" />', esc_attr( $icon ) );
			return;
		}

		if ( preg_match( '#^https?://#', $icon ) ) {
			printf( '<img class="tapio-clean-backend-menu-icon" src="%s" alt="" />', esc_url( $icon ) );
		}
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
