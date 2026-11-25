<?php
/**
 * Tools settings page: feature switches and the sidebar editor.
 *
 * @package Tapio\CleanBackend
 */

namespace Tapio\CleanBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Tools > Tapio Clean Backend and reads the saved settings.
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
	 * Sidebar order and names, for the editor.
	 *
	 * @var Admin_Menu
	 */
	private $admin_menu;

	/**
	 * Hook suffix of the settings page, set when the page is added.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Constructor.
	 *
	 * @param Admin_Menu $admin_menu Sidebar service.
	 */
	public function __construct( Admin_Menu $admin_menu ) {
		$this->admin_menu = $admin_menu;
	}

	/**
	 * Default settings: both features on and no saved sidebar layout.
	 *
	 * keep_visible and menu_order are what versions 1.1.x stored. They still
	 * shape the sidebar until a layout is saved in the editor.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'collapse_sidebar' => true,
			'relocate_notices' => true,
			'menu'             => Menu_Layout::empty_layout(),
			'keep_visible'     => array(),
			'menu_order'       => array(),
		);
	}

	/**
	 * Saved settings with defaults filled in and values cleaned.
	 *
	 * @return array{collapse_sidebar: bool, relocate_notices: bool, menu: array, keep_visible: string[], menu_order: string[]}
	 */
	public static function get() {
		$saved    = get_option( self::OPTION, array() );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );

		return array(
			'collapse_sidebar' => ! empty( $settings['collapse_sidebar'] ),
			'relocate_notices' => ! empty( $settings['relocate_notices'] ),
			'menu'             => Menu_Layout::clean( $settings['menu'] ),
			'keep_visible'     => Menu_Layout::clean_slugs( $settings['keep_visible'] ),
			'menu_order'       => Menu_Layout::clean_slugs( $settings['menu_order'] ),
		);
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
	 * Loads the editor script and styles on the settings page only.
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
			array( 'jquery', 'jquery-ui-sortable', 'jquery-touch-punch' ),
			TAPIO_CLEAN_BACKEND_VERSION,
			true
		);

		wp_localize_script(
			'tapio-clean-backend-settings',
			'tapioCleanBackendSettings',
			array(
				'maxNameLength' => Menu_Layout::MAX_NAME_LENGTH,
				'nameField'     => __( 'Menu name', 'tapio-clean-backend' ),
				/* translators: %s: menu name */
				'moveLabel'     => __( 'Move %s', 'tapio-clean-backend' ),
				'sidebar'       => __( 'Sidebar', 'tapio-clean-backend' ),
				'more'          => __( 'More', 'tapio-clean-backend' ),
				/* translators: 1: menu name, 2: list name, 3: position, 4: number of menus in the list */
				'moved'         => __( '%1$s is now in %2$s, position %3$d of %4$d.', 'tapio-clean-backend' ),
				/* translators: %s: new menu name */
				'renamed'       => __( 'Name is now %s. Save changes to keep it.', 'tapio-clean-backend' ),
				'confirmReset'  => __( 'Click again to restore the default menu and names', 'tapio-clean-backend' ),
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

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'tools.php?page=' . self::PAGE ) ),
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
	 * Registers the option, sections and fields.
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
			array( $this, 'render_features_intro' ),
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

		add_settings_section(
			'tapio_clean_backend_menu',
			__( 'Sidebar menu', 'tapio-clean-backend' ),
			array( $this, 'render_menu_editor' ),
			self::PAGE
		);
	}

	/**
	 * Cleans the posted settings. Unchecked boxes are off.
	 *
	 * The sidebar layout arrives as JSON, and only when it was changed in the
	 * editor; otherwise the saved layout is kept. WordPress runs this callback
	 * twice when the option is created, so its output must also be valid input.
	 *
	 * @param mixed $input Raw option value.
	 * @return array
	 */
	public function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$previous     = self::get();
		$menu         = $previous['menu'];
		$keep_visible = $previous['keep_visible'];
		$menu_order   = $previous['menu_order'];

		if ( ! empty( $input['reset_menu'] ) ) {
			$menu         = Menu_Layout::empty_layout();
			$keep_visible = array();
			$menu_order   = array();
		} elseif ( ! empty( $input['menu_json'] ) && is_string( $input['menu_json'] ) ) {
			$posted = json_decode( $input['menu_json'], true );
			if ( is_array( $posted ) && ! empty( $posted['moved'] ) ) {
				$menu         = Menu_Layout::merge_unlisted( Menu_Layout::clean( $posted ), $previous['menu'] );
				$keep_visible = array();
				$menu_order   = array();
			} elseif ( is_array( $posted ) ) {
				// Names only: the saved order, or the default and 1.1.x choices, stay as they are.
				$menu = Menu_Layout::rename_only( Menu_Layout::clean( $posted ), $previous['menu'] );
			}
		} elseif ( isset( $input['menu'] ) ) {
			$menu         = Menu_Layout::clean( $input['menu'] );
			$keep_visible = Menu_Layout::clean_slugs( isset( $input['keep_visible'] ) ? $input['keep_visible'] : array() );
			$menu_order   = Menu_Layout::clean_slugs( isset( $input['menu_order'] ) ? $input['menu_order'] : array() );
		}

		return array(
			'collapse_sidebar' => ! empty( $input['collapse_sidebar'] ),
			'relocate_notices' => ! empty( $input['relocate_notices'] ),
			'menu'             => $menu,
			'keep_visible'     => $keep_visible,
			'menu_order'       => $menu_order,
		);
	}

	/**
	 * Intro of the feature switches.
	 *
	 * @return void
	 */
	public function render_features_intro() {
		echo '<p>' . esc_html__( 'Nothing is removed. These switches only choose which rearrangements are active.', 'tapio-clean-backend' ) . '</p>';
	}

	/**
	 * More section checkbox.
	 *
	 * @return void
	 */
	public function render_collapse_sidebar_field() {
		$this->render_checkbox(
			'collapse_sidebar',
			__( 'Show a More link in the sidebar', 'tapio-clean-backend' ),
			__( 'The menus you place under More open when you click it. When this is off, they follow the main menus.', 'tapio-clean-backend' )
		);
	}

	/**
	 * Notices checkbox.
	 *
	 * @return void
	 */
	public function render_relocate_notices_field() {
		$this->render_checkbox(
			'relocate_notices',
			__( 'Move admin notices into the Notifications panel in the admin bar', 'tapio-clean-backend' ),
			__( 'Notices are not discarded. They appear in the admin bar with a badge count.', 'tapio-clean-backend' )
		);
	}

	/**
	 * A feature switch.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Checkbox label.
	 * @param string $description Help text.
	 * @return void
	 */
	private function render_checkbox( $key, $label, $description ) {
		$settings = self::get();
		$id       = 'tapio-clean-backend-' . str_replace( '_', '-', $key );
		?>
		<label for="<?php echo esc_attr( $id ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>"
				value="1"
				<?php checked( $settings[ $key ] ); ?>
			/>
			<?php echo esc_html( $label ); ?>
		</label>
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php
	}

	/**
	 * The sidebar editor: two lists that menus can be dragged between.
	 *
	 * @return void
	 */
	public function render_menu_editor() {
		$items      = $this->admin_menu->get_editor_items();
		$settings   = self::get();
		$more_label = $settings['menu']['more_label'];
		$default    = __( 'More', 'tapio-clean-backend' );

		if ( ! $items['main'] && ! $items['more'] ) {
			echo '<p>' . esc_html__( 'The sidebar could not be read on this screen.', 'tapio-clean-backend' ) . '</p>';
			return;
		}
		?>
		<p id="tapio-clean-backend-editor-help">
			<?php esc_html_e( 'Drag menus to change their order or to move them between the sidebar and More. Click a name to rename it; leave the name empty to get the original back. Changes apply to every user when you save.', 'tapio-clean-backend' ); ?>
		</p>
		<?php if ( ! $settings['collapse_sidebar'] ) : ?>
			<p><?php esc_html_e( 'The More link is off. The menus in the More list follow the main ones once you have moved a menu and saved; until then the sidebar keeps the WordPress order.', 'tapio-clean-backend' ); ?></p>
		<?php endif; ?>
		<div class="tcb-editor" id="tapio-clean-backend-editor" aria-describedby="tapio-clean-backend-editor-help">
			<div class="tcb-column">
				<h3 id="tapio-clean-backend-main-title"><?php esc_html_e( 'Sidebar', 'tapio-clean-backend' ); ?></h3>
				<p class="tcb-column-help"><?php esc_html_e( 'Always visible.', 'tapio-clean-backend' ); ?></p>
				<ul class="tcb-list" data-section="main" aria-labelledby="tapio-clean-backend-main-title">
					<?php
					foreach ( $items['main'] as $item ) {
						$this->render_editor_item( $item );
					}
					?>
				</ul>
			</div>
			<div class="tcb-column">
				<h3 id="tapio-clean-backend-more-title">
					<button
						type="button"
						class="tcb-name tcb-more-name"
						data-original="<?php echo esc_attr( $default ); ?>"
						data-name="<?php echo esc_attr( $more_label ); ?>"
						title="<?php esc_attr_e( 'Click to rename', 'tapio-clean-backend' ); ?>"
					><?php echo esc_html( '' !== $more_label ? $more_label : $default ); ?></button>
				</h3>
				<p class="tcb-column-help"><?php esc_html_e( 'Shown when the More link is open. Click the title to rename the link.', 'tapio-clean-backend' ); ?></p>
				<ul class="tcb-list" data-section="more" aria-labelledby="tapio-clean-backend-more-title" data-empty="<?php esc_attr_e( 'Drag menus here.', 'tapio-clean-backend' ); ?>">
					<?php
					foreach ( $items['more'] as $item ) {
						$this->render_editor_item( $item );
					}
					?>
				</ul>
			</div>
		</div>
		<p class="description" id="tapio-clean-backend-keyboard-help">
			<?php esc_html_e( 'Keyboard: focus a drag handle and use the up and down arrow keys to move a menu, and the right and left arrow keys to move it to More and back.', 'tapio-clean-backend' ); ?>
		</p>
		<p>
			<button type="button" class="button-link tcb-reset" id="tapio-clean-backend-reset"><?php esc_html_e( 'Restore default menu', 'tapio-clean-backend' ); ?></button>
		</p>
		<input type="hidden" id="tapio-clean-backend-menu-json" name="<?php echo esc_attr( self::OPTION . '[menu_json]' ); ?>" value="" />
		<input type="hidden" id="tapio-clean-backend-reset-menu" name="<?php echo esc_attr( self::OPTION . '[reset_menu]' ); ?>" value="" />
		<p class="screen-reader-text" id="tapio-clean-backend-status" aria-live="polite"></p>
		<noscript><p><?php esc_html_e( 'The sidebar editor needs JavaScript. Saving without it keeps the current menu.', 'tapio-clean-backend' ); ?></p></noscript>
		<?php
	}

	/**
	 * One row of the sidebar editor.
	 *
	 * @param array{slug: string, title: string, icon: string, separator: bool, name: string} $item Menu.
	 * @return void
	 */
	private function render_editor_item( array $item ) {
		$title = $item['separator'] ? __( 'Separator', 'tapio-clean-backend' ) : $item['title'];
		$shown = '' !== $item['name'] ? $item['name'] : $title;
		?>
		<li
			class="tcb-item<?php echo $item['separator'] ? ' tcb-separator' : ''; ?>"
			data-slug="<?php echo esc_attr( $item['slug'] ); ?>"
			data-type="<?php echo $item['separator'] ? 'separator' : 'menu'; ?>"
		>
			<button
				type="button"
				class="tcb-handle"
				aria-describedby="tapio-clean-backend-keyboard-help"
				aria-label="<?php /* translators: %s: menu name */ echo esc_attr( sprintf( __( 'Move %s', 'tapio-clean-backend' ), $shown ) ); ?>"
			><span class="dashicons dashicons-menu" aria-hidden="true"></span></button>
			<?php if ( $item['separator'] ) : ?>
				<span class="tcb-separator-label"><?php echo esc_html( $title ); ?></span>
			<?php else : ?>
				<?php $this->render_menu_icon( $item['icon'] ); ?>
				<button
					type="button"
					class="tcb-name"
					data-original="<?php echo esc_attr( $title ); ?>"
					data-name="<?php echo esc_attr( $item['name'] ); ?>"
					title="<?php esc_attr_e( 'Click to rename', 'tapio-clean-backend' ); ?>"
				><?php echo esc_html( $shown ); ?></button>
				<span class="tcb-original"<?php echo '' === $item['name'] ? ' hidden' : ''; ?>><?php echo esc_html( $title ); ?></span>
			<?php endif; ?>
		</li>
		<?php
	}

	/**
	 * Small version of a menu icon, so the rows are easy to match with the sidebar.
	 *
	 * @param string $icon Icon from $menu[n][6]: a dashicons class, an SVG data URI, an image URL or nothing.
	 * @return void
	 */
	private function render_menu_icon( $icon ) {
		if ( 0 === strpos( $icon, 'dashicons-' ) ) {
			printf( '<span class="dashicons %s tcb-icon" aria-hidden="true"></span>', esc_attr( $icon ) );
		} elseif ( 0 === strpos( $icon, 'data:image/svg+xml;base64,' ) ) {
			printf( '<img class="tcb-icon" src="%s" alt="" />', esc_attr( $icon ) );
		} elseif ( preg_match( '#^(https?:)?//#', $icon ) ) {
			printf( '<img class="tcb-icon" src="%s" alt="" />', esc_url( $icon ) );
		} else {
			echo '<span class="tcb-icon" aria-hidden="true"></span>';
		}
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
			<?php
			// Shown here, not in the Notifications panel, so saving is confirmed where it happened.
			if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
				wp_admin_notice(
					__( 'Settings saved.', 'tapio-clean-backend' ),
					array(
						'type'               => 'success',
						'additional_classes' => array( 'inline' ),
					)
				);
			}
			?>
			<form action="options.php" method="post" id="tapio-clean-backend-form">
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
