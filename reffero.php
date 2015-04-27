<?php
defined('ABSPATH') or die("No script kiddies please!");
/**
 * Plugin Name: Reffero
 * Description: Allows restricting access to your posts or pages via the Reffero.com service.
 * Version: 1.0
 * Author: Reffero.com
 * Author URI: http://reffero.com
 */

function reffero_custom_meta() {
    add_meta_box( 'reffero_meta', __( 'Reffero Options', 'reffero-textdomain' ), 'reffero_meta_callback', 'post', 'side' );
}

/**
 * Outputs the content of the meta box
 */
function reffero_meta_callback( $post ) {
    wp_nonce_field( basename( __FILE__ ), 'reffero_nonce' );
    $prfx_stored_meta = get_post_meta( $post->ID );
    ?>

    <p>
        <input type="checkbox" name="use-reffero" id="use-reffero"  value="1" <?php if ( isset ( $prfx_stored_meta['use-reffero'] ) && $prfx_stored_meta['use-reffero'][0]) echo "checked=\"checked\""; ?> />
        <label for="use-reffero" class="prfx-row-title"><?php _e( 'Restrict this post with Reffero?', 'reffero-textdomain' )?></label>
    </p>
    <p>
        <label for="page-reffero" class="prfx-row-title"><?php _e( 'Landing page URL:', 'reffero-textdomain' )?></label>
        <input type="text" name="page-reffero" id="page-reffero"  value="<?php echo $prfx_stored_meta['page-reffero'][0] ?>"  />
    </p>

<?php
}

/**
 * Saves the custom meta input
 */
function reffero_meta_save( $post_id ) {

    // Checks save status
    $is_autosave = wp_is_post_autosave( $post_id );
    $is_revision = wp_is_post_revision( $post_id );
    $is_valid_nonce = ( isset( $_POST[ 'reffero_nonce' ] ) && wp_verify_nonce( $_POST[ 'reffero_nonce' ], basename( __FILE__ ) ) ) ? 'true' : 'false';

    // Exits script depending on save status
    if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
        return;
    }

    // Checks for input and sanitizes/saves if needed
    if( isset( $_POST[ 'use-reffero' ] ) ) {
        update_post_meta( $post_id, 'use-reffero', sanitize_text_field( $_POST[ 'use-reffero' ] ) );
    } else {
        update_post_meta( $post_id, 'use-reffero', 0 );
    }
    if( isset( $_POST[ 'page-reffero' ] ) ) {
        update_post_meta( $post_id, 'page-reffero', sanitize_text_field( $_POST[ 'page-reffero' ] ) );
    } else {
        update_post_meta( $post_id, 'page-reffero', '' );
    }
}

function reffero_template_redirect()
{
    if (get_post_meta( get_the_ID(), 'use-reffero', true )) {
        if (isset($_GET['ppiId']) && isset ($_GET['pppId'])) {
            $reffero_result = json_decode(file_get_contents('http://reffero.com/api/json?ppiId=' . $_GET['ppiId'] . '&pppId=' . $_GET['pppId']));
            if ($reffero_result->status == -1) {
                die('error');
            } elseif ( $reffero_result->status == 0) {

            } else {
                header('Location: ' . get_option("denied_page", ''));
            }
        } else {
            if (get_post_meta( get_the_ID(), 'page-reffero', true )) {
                header('Location: '.get_post_meta( get_the_ID(), 'page-reffero', true ));
            }
        }
    }

}

function reffero_menu() {
    add_options_page( 'Reffero configuration', 'Reffero', 'manage_options', 'reffero-menu', 'reffero_options');
    add_action( 'admin_init', 'register_reffero' );
}

function register_reffero() {
    register_setting( 'reffero-group', 'denied_page' );
    register_setting( 'reffero-group', 'error_page' );
}

function reffero_options() {
    ?>
    <div class="wrap">
        <h2>Reffero configuration</h2>

        <form method="post" action="options.php">
            <?php settings_fields( 'reffero-group' ); ?>
            <?php do_settings_sections( 'reffero-group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Denied page</th>
                    <td><input type="text" name="denied_page" value="<?php echo get_option('denied_page'); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Error page</th>
                    <td><input type="text" name="error_page" value="<?php echo get_option('error_page'); ?>" /></td>
                </tr>
            </table>

            <?php submit_button(); ?>

        </form>
    </div>
<?php }

add_action( 'save_post', 'reffero_meta_save' );

add_action( 'add_meta_boxes', 'reffero_custom_meta' );

add_action( 'template_redirect', 'reffero_template_redirect' );

add_action( 'admin_menu', 'reffero_menu' );
