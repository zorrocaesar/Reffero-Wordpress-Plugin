<?php
defined('ABSPATH') or die("No script kiddies please!");
/**
 * Plugin Name: Reffero
 * Description: Allows restricting access to your posts or pages via the Reffero.com service.
 * Version: 0.0.1
 * Author: Reffero.com
 * Author URI: http://reffero.com
 */
define( 'REFFERO__API_ENDPOINT', 'http://test.local/app_dev.php/api/v1/validate' );
define( 'REFFERO__NEW_CAMPAIGN_URL', 'http://test.local/app_dev.php');

register_activation_hook( __FILE__, 'plugin_activation' );

function plugin_activation(){
    if (!function_exists('curl_init') && !function_exists('file_get_contents')) {
        echo "Neither <strong>cUrl</strong> nor <strong>file_get_contents</strong> are avaialble one your server. This plugin needs one of these methods to access the Reffero API.";
        exit();
    }
}

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
        <label for="use-reffero" class="prfx-row-title">
            <?php _e( 'Attach a Reffero campaign to this post?', 'reffero-textdomain' )?>
        </label>
        <p class="howto">
            <span class="dashicons dashicons-info"></span>If you attach a Reffero campaign to this post, visitors will have to post to a Social Network before being able to view it.
        </p>
    </p>
    <p>
        <input type="checkbox" name="reffero-one-time-only" id="reffero-one-time-only"  value="1" <?php if ( isset ( $prfx_stored_meta['reffero-one-time-only'] ) && $prfx_stored_meta['reffero-one-time-only'][0]) echo "checked=\"checked\""; ?> />
        <label for="reffero-one-time-only" class="prfx-row-title">
            <?php _e( 'One-time-only access', 'reffero-textdomain' )?>
        </label>
        <p class="howto">
            <span class="dashicons dashicons-info"></span>One-time-only access means that your visitors will only be able to access this post once after they post on a Social Network via Reffero. If they want to access the page again, they would have to post again.
        </p>
    </p>
    <p>
        <label for="page-reffero" class="prfx-row-title">
            <?php _e( 'Reffero campaign URL:', 'reffero-textdomain' )?>
        </label>
        <input type="text" name="page-reffero" id="page-reffero"  value="<?php echo $prfx_stored_meta['reffero_campaign'][0] ?>"  />
        <br />
        or

        <br />
        <a class="button" href="<?php echo REFFERO__NEW_CAMPAIGN_URL ?>" target="wp-preview-1">Create a new campaign on Reffero</a>
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
    if( isset( $_POST[ 'reffero_campaign' ] ) ) {
        update_post_meta( $post_id, 'reffero_campaign', sanitize_text_field( $_POST[ 'reffero_campaign' ] ) );
    } else {
        update_post_meta( $post_id, 'reffero_campaign', '' );
    }
    if( isset( $_POST[ 'reffero-one-time-only' ] ) ) {
        update_post_meta( $post_id, 'reffero-one-time-only', sanitize_text_field( $_POST[ 'reffero-one-time-only' ] ) );
    } else {
        update_post_meta( $post_id, 'reffero-one-time-only', 0 );
    }
}

function reffero_template_redirect()
{
    if (get_post_meta( get_the_ID(), 'use-reffero', true )) {
        if (isset($_GET['campaignId']) && isset ($_GET['paymentId'])) {
            if (get_post_meta(get_the_ID(), 'reffero-one-time-only', true)) {
                $enpointUrl = REFFERO__API_ENDPOINT
                    . '/' . $_GET['campaignId']
                    . '/' . $_GET['paymentId'];

                $apiResult = performHttpRequest($enpointUrl);

                if ($apiResult->used === true) {
                    header('Location: ' . get_option("denied_page", ''));
                } elseif ($apiResult->used === false) {

                } else {
                    header('Location: ' . get_option("error_page", ''));
                }
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

    if (function_exists("curl_init")){
        $curlAvaiable = true;
    } else {
        $curlAvaiable = false;
    }
    if (function_exists("file_get_contents")) {
        $fileGetContentesAvailable = true;
    } else {
        $fileGetContentesAvailable = false;
    }
    ?>
    <div class="wrap">
        <h2>Reffero configuration</h2>

        <form method="post" action="options.php">
            <?php settings_fields( 'reffero-group' ); ?>
            <?php do_settings_sections( 'reffero-group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="denied_page">Denied page</label>
                    </th>
                    <td>
                        <input class="regular-text" type="text" id="denied_page" name="denied_page" value="<?php echo get_option('denied_page'); ?>" />
                        <p class="description" id="tagline-description">Redirect to this URL when visitors try to re-use the unique ID that grants them one-time-only access.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="error_page">Error page</label>
                    </th>
                    <td>
                        <input class="regular-text" type="text" id="error_page" name="error_page" value="<?php echo get_option('error_page'); ?>" />
                        <p class="description" id="tagline-description">Display this page when the Reffero validation service is unavailable.</p>
                    </td>
                </tr>
            <tr valign="top">
                    <th scope="row">
                        HTTP client
                    </th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span>HTTP Client</span>
                            </legend>
                            <label title="cURL">
                                <input
                                    type="radio"
                                    name="http_client"
                                    value="curl"
                                    <?php if (!$curlAvaiable) echo "disabled=\"disabled\"" ?>
                                    <?php if($curlAvaiable && !$fileGetContentesAvailable) echo "checked=\"checked\"" ?>
                                    /> cURL <?php if ($curlAvaiable === false) echo "(not available on your server)" ?>
                            </label>
                            <br />
                            </label>
                            <label title="file_get_contents">
                                <input
                                    type="radio"
                                    name="file_get_contents()"
                                    value="file_get_contents"
                                    <?php if (!$fileGetContentesAvailable) echo "disabled=disabled" ?>
                                    <?php if($fileGetContentesAvailable && !$curlAvaiable) echo "checked=\"checked\"" ?>
                                    /> file_get_contents() <?php if ($fileGetContentesAvailable === false) echo "(not available on your server)" ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>

        </form>
    </div>
<?php }

function performHttpRequest($url)
{
    if (get_option('http_client') == 'curl') {
        $apiResult = performCurlRequest($url);
    } else {
        $apiResult = performDefaultRequest($url);
    }

    //TODO error handling
    $result = json_decode($apiResult);
    return $result;
}

function performCurlRequest($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);

    $refferoResult = curl_exec($ch);
    curl_close($ch);

    //TODO error handling
    return $refferoResult;
}

function performDefaultRequest($url)
{
    //TODO error handling
    return file_get_contents('http://reffero.com/api/json?ppiId=' . $_GET['ppiId'] . '&pppId=' . $_GET['pppId']);
}

add_action( 'save_post', 'reffero_meta_save' );

add_action( 'add_meta_boxes', 'reffero_custom_meta' );

add_action( 'template_redirect', 'reffero_template_redirect' );

add_action( 'admin_menu', 'reffero_menu' );
