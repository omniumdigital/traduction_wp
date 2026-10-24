<?php
/**
 * WordPress navigation-menu integration.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Switcher;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a special switcher item to Appearance > Menus and renders it on front.
 */
final class MenuIntegration {

	private const ITEM_URL = '#omtr-switcher';

	/**
	 * Constructor.
	 *
	 * @param Switcher $switcher Language switcher renderer.
	 */
	public function __construct( private Switcher $switcher ) {}

	/** Register the special item box on the classic menu screen. */
	public function register_meta_box(): void {
		add_meta_box(
			'add-omtr-switcher',
			__( 'Language selector', 'omnium-translate' ),
			array( $this, 'render_meta_box' ),
			'nav-menus',
			'side',
			'default'
		);
	}

	/** Render fields understood by WordPress core's menu-item JavaScript. */
	public function render_meta_box(): void {
		$title = __( 'Language selector', 'omnium-translate' );
		?>
		<div id="posttype-omtr-switcher" class="posttypediv">
			<div id="tabs-panel-omtr-switcher" class="tabs-panel tabs-panel-active">
				<ul id="omtr-switcher-checklist" class="categorychecklist form-no-clear">
					<li>
						<label class="menu-item-title">
							<input type="checkbox" class="menu-item-checkbox" name="menu-item[-1][menu-item-object-id]" value="-1" />
							<?php echo esc_html( $title ); ?>
						</label>
						<input type="hidden" class="menu-item-type" name="menu-item[-1][menu-item-type]" value="custom" />
						<input type="hidden" class="menu-item-title" name="menu-item[-1][menu-item-title]" value="<?php echo esc_attr( $title ); ?>" />
						<input type="hidden" class="menu-item-url" name="menu-item[-1][menu-item-url]" value="<?php echo esc_attr( self::ITEM_URL ); ?>" />
					</li>
				</ul>
			</div>
			<p class="button-controls wp-clearfix">
				<span class="add-to-menu">
					<input type="submit" class="button-secondary submit-add-to-menu right" value="<?php echo esc_attr__( 'Add to Menu', 'omnium-translate' ); ?>" name="add-post-type-menu-item" id="submit-posttype-omtr-switcher" />
					<span class="spinner"></span>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Replace the marker link inside a rendered menu item.
	 *
	 * @param string $item_output Existing walker output.
	 * @param object $menu_item   Prepared menu item.
	 * @param int    $depth       Menu depth.
	 * @param object $args        Menu rendering arguments.
	 */
	public function filter_item_output( string $item_output, object $menu_item, int $depth, object $args ): string {
		unset( $depth, $args );
		$url = isset( $menu_item->url ) && is_string( $menu_item->url ) ? $menu_item->url : '';
		if ( self::ITEM_URL !== $url ) {
			return $item_output;
		}
		return $this->switcher->render_menu();
	}
}
