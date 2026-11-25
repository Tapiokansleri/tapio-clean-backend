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
	 * AJAX action and nonce name of the automatic save.
	 *
	 * @var string
	 */
	const AJAX_ACTION = 'tapio_clean_backend_save';

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
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_save' ) );
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
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'action'          => self::AJAX_ACTION,
				'nonce'           => wp_create_nonce( self::AJAX_ACTION ),
				'maxNameLength'   => Menu_Layout::MAX_NAME_LENGTH,
				'saving'          => __( 'Saving…', 'tapio-clean-backend' ),
				'saved'           => __( 'Saved', 'tapio-clean-backend' ),
				'failed'          => __( 'Saving failed. Check the connection, or reload the page.', 'tapio-clean-backend' ),
				'retry'           => __( 'Try again', 'tapio-clean-backend' ),
				'nameField'       => __( 'Menu name', 'tapio-clean-backend' ),
				/* translators: %s: original menu name */
				'renameTitle'     => __( 'Click to rename. Original name: %s', 'tapio-clean-backend' ),
				/* translators: %s: new menu name */
				'renamed'         => __( 'Name is now %s.', 'tapio-clean-backend' ),
				/* translators: %s: menu name */
				'hide'            => __( 'Hide %s', 'tapio-clean-backend' ),
				/* translators: %s: menu name */
				'show'            => __( 'Show %s', 'tapio-clean-backend' ),
				/* translators: %s: menu name */
				'nowHidden'       => __( '%s is now hidden.', 'tapio-clean-backend' ),
				/* translators: %s: menu name */
				'nowShown'        => __( '%s is shown again.', 'tapio-clean-backend' ),
				'separator'       => __( 'Separator', 'tapio-clean-backend' ),
				'removeSeparator' => __( 'Remove separator', 'tapio-clean-backend' ),
				'separatorAdded'  => __( 'A new separator was added above the More link. Drag it where you want it.', 'tapio-clean-backend' ),
				'sidebar'         => __( 'Sidebar', 'tapio-clean-backend' ),
				/* translators: 1: menu name, 2: list name, 3: position, 4: number of menus in the list */
				'moved'           => __( '%1$s is now in %2$s, position %3$d of %4$d.', 'tapio-clean-backend' ),
				'confirmReset'    => __( 'Click again to restore the default menu and names', 'tapio-clean-backend' ),
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
	 * Saves a change made on the settings page.
	 *
	 * @return void
	 */
	public function ajax_save() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$input = array(
			'collapse_sidebar' => ! empty( $_POST['collapse_sidebar'] ),
			'relocate_notices' => ! empty( $_POST['relocate_notices'] ),
		);

		if ( ! empty( $_POST['reset_menu'] ) ) {
			$input['reset_menu'] = true;
		}

		if ( isset( $_POST['menu_json'] ) && is_string( $_POST['menu_json'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, decoded and cleaned in sanitize().
			$input['menu_json'] = wp_unslash( $_POST['menu_json'] );
		}

		// The registered setting runs sanitize(); admin-ajax.php runs admin_init, which registers it.
		update_option( self::OPTION, $input );

		$settings = self::get();

		wp_send_json_success(
			array(
				'menu'  => $settings['menu'],
				'saved' => Menu_Layout::is_saved( $settings['menu'] ),
			)
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
				// Names and visibility only: the saved order, or the default and 1.1.x choices, stay as they are.
				$menu = Menu_Layout::apply_details( Menu_Layout::clean( $posted ), $previous['menu'] );
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
				class="tcb-switch"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>"
				data-setting="<?php echo esc_attr( $key ); ?>"
				value="1"
				<?php checked( $settings[ $key ] ); ?>
			/>
			<?php echo esc_html( $label ); ?>
		</label>
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php
	}

	/**
	 * The sidebar editor. It looks like the sidebar, with the More link open.
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
		<p class="tcb-help" id="tapio-clean-backend-editor-help">
			<?php esc_html_e( 'Drag to reorder. Items below the More link show when it is open. Click a name to rename it (an empty name restores the original), or the eye to hide it. Changes save as you make them and apply to every user.', 'tapio-clean-backend' ); ?>
		</p>
		<?php if ( ! $settings['collapse_sidebar'] ) : ?>
			<p class="tcb-help"><?php esc_html_e( 'The More link is off, so the items below it follow the main ones once you have moved something; until then the sidebar keeps the WordPress order.', 'tapio-clean-backend' ); ?></p>
		<?php endif; ?>
		<p class="screen-reader-text" id="tapio-clean-backend-keyboard-help"><?php esc_html_e( 'Keyboard: focus an item and use the up and down arrow keys to move it.', 'tapio-clean-backend' ); ?></p>

		<div class="tcb-sidebar" id="tapio-clean-backend-editor" aria-describedby="tapio-clean-backend-editor-help tapio-clean-backend-keyboard-help">
			<ul class="tcb-list" data-section="main" aria-label="<?php esc_attr_e( 'Sidebar', 'tapio-clean-backend' ); ?>">
				<?php
				foreach ( $items['main'] as $item ) {
					$this->render_editor_item( $item );
				}
				?>
			</ul>
			<div class="tcb-more-row">
				<span class="tcb-icon dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<span class="tcb-label">
					<button
						type="button"
						class="tcb-name tcb-more-name"
						data-original="<?php echo esc_attr( $default ); ?>"
						data-name="<?php echo esc_attr( $more_label ); ?>"
						title="<?php /* translators: %s: original menu name */ echo esc_attr( sprintf( __( 'Click to rename. Original name: %s', 'tapio-clean-backend' ), $default ) ); ?>"
					><?php echo esc_html( '' !== $more_label ? $more_label : $default ); ?></button>
				</span>
				<span class="tcb-chevron dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
			</div>
			<ul class="tcb-list" data-section="more" aria-label="<?php echo esc_attr( '' !== $more_label ? $more_label : $default ); ?>" data-empty="<?php esc_attr_e( 'Drag items here.', 'tapio-clean-backend' ); ?>">
				<?php
				foreach ( $items['more'] as $item ) {
					$this->render_editor_item( $item );
				}
				?>
			</ul>
			<button type="button" class="tcb-add-separator">
				<span class="tcb-icon dashicons dashicons-plus" aria-hidden="true"></span>
				<span class="tcb-label"><?php esc_html_e( 'New separator', 'tapio-clean-backend' ); ?></span>
			</button>
		</div>

		<p>
			<button type="button" class="button-link tcb-reset" id="tapio-clean-backend-reset"><?php esc_html_e( 'Restore default menu', 'tapio-clean-backend' ); ?></button>
		</p>
		<div class="tcb-toast" id="tapio-clean-backend-save-status" role="status" hidden></div>
		<p class="screen-reader-text" id="tapio-clean-backend-status" aria-live="polite"></p>
		<noscript><p><?php esc_html_e( 'The sidebar editor needs JavaScript. Saving without it keeps the current menu.', 'tapio-clean-backend' ); ?></p></noscript>
		<?php
	}

	/**
	 * One row of the sidebar editor.
	 *
	 * @param array{slug: string, key: string, title: string, bubble: string, icon: string, separator: bool, custom: bool, name: string, hidden: bool} $item Entry.
	 * @return void
	 */
	private function render_editor_item( array $item ) {
		$title = $item['separator'] ? __( 'Separator', 'tapio-clean-backend' ) : $item['title'];
		$shown = '' !== $item['name'] ? $item['name'] : $title;
		?>
		<li
			class="tcb-item<?php echo $item['separator'] ? ' tcb-separator' : ''; ?><?php echo $item['hidden'] ? ' tcb-is-hidden' : ''; ?>"
			data-slug="<?php echo esc_attr( $item['slug'] ); ?>"
			data-key="<?php echo esc_attr( $item['key'] ); ?>"
			data-type="<?php echo $item['separator'] ? 'separator' : 'menu'; ?>"
			<?php if ( $item['separator'] ) : ?>
				tabindex="0"
				aria-label="<?php echo esc_attr( $title ); ?>"
			<?php endif; ?>
		>
			<?php if ( $item['separator'] ) : ?>
				<span class="tcb-line" aria-hidden="true"></span>
			<?php else : ?>
				<?php $this->render_menu_icon( $item['icon'] ); ?>
				<span class="tcb-label">
					<button
						type="button"
						class="tcb-name"
						data-original="<?php echo esc_attr( $title ); ?>"
						data-name="<?php echo esc_attr( $item['name'] ); ?>"
						title="<?php /* translators: %s: original menu name */ echo esc_attr( sprintf( __( 'Click to rename. Original name: %s', 'tapio-clean-backend' ), $title ) ); ?>"
					><?php echo esc_html( $shown ); ?></button>
					<?php
					echo wp_kses(
						$item['bubble'],
						array(
							'span' => array(
								'class'       => true,
								'aria-hidden' => true,
							),
						)
					);
					?>
				</span>
			<?php endif; ?>
			<?php if ( $item['custom'] ) : ?>
				<button type="button" class="tcb-action tcb-remove dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Remove separator', 'tapio-clean-backend' ); ?>" title="<?php esc_attr_e( 'Remove separator', 'tapio-clean-backend' ); ?>"></button>
			<?php else : ?>
				<?php
				/* translators: %s: menu name */
				$hide_label = sprintf( __( 'Hide %s', 'tapio-clean-backend' ), $shown );
				/* translators: %s: menu name */
				$action_title = $item['hidden'] ? sprintf( __( 'Show %s', 'tapio-clean-backend' ), $shown ) : $hide_label;
				?>
				<button
					type="button"
					class="tcb-action tcb-eye dashicons <?php echo $item['hidden'] ? 'dashicons-hidden' : 'dashicons-visibility'; ?>"
					aria-pressed="<?php echo $item['hidden'] ? 'true' : 'false'; ?>"
					aria-label="<?php echo esc_attr( $hide_label ); ?>"
					title="<?php echo esc_attr( $action_title ); ?>"
				></button>
			<?php endif; ?>
			<span class="tcb-grip dashicons dashicons-menu" aria-hidden="true"></span>
		</li>
		<?php
	}

	/**
	 * A menu icon. The editor script replaces it with the icon the sidebar shows.
	 *
	 * @param string $icon Icon from $menu[n][6]: a dashicons class, an SVG data URI, an image URL or nothing.
	 * @return void
	 */
	private function render_menu_icon( $icon ) {
		if ( 0 === strpos( $icon, 'dashicons-' ) ) {
			printf( '<span class="tcb-icon dashicons %s" aria-hidden="true"></span>', esc_attr( $icon ) );
			return;
		}

		echo '<span class="tcb-icon" aria-hidden="true">';
		if ( 0 === strpos( $icon, 'data:image/svg+xml;base64,' ) ) {
			printf( '<img src="%s" alt="" />', esc_attr( $icon ) );
		} elseif ( preg_match( '#^(https?:)?//#', $icon ) ) {
			printf( '<img src="%s" alt="" />', esc_url( $icon ) );
		}
		echo '</span>';
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
			// Without JavaScript the form is submitted to options.php. Confirm here, not in the Notifications panel.
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
				?>
				<noscript><?php submit_button(); ?></noscript>
			</form>
		</div>
		<?php
	}
}
