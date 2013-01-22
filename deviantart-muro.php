<?php
/**
 * @package deviantart-muro
 * @version 1.0.0
 */
/*
Plugin Name: deviantART muro
Plugin URI: http://github.com/deviantART/embedded-deviantART-muro-wordpress/
Description: Adds support for <a href="http://sta.sh/muro">deviantART muro</a>, the HTML5 drawing application, for image drawing in your Media Library, articles and comments.
Author: deviantART, Inc
Version: 1.0.0
Author URI: http://www.deviantart.com/
License: BSD
*/

class Deviantart_Muro {

    static $version = "1.0.0"; // If anyone needs it.
    static $deviantart_muro_url = 'http://sta.sh/muro';

    private static $_comment_drawings_available = null;
    private static $_comment_drawings_unavailability_reasons = array();

    // Ugh, hack time.
    private static $_inline_css_displayed = false;

    public static function register_hooks() {
        load_plugin_textdomain('deviantart-muro', false, basename(dirname(__FILE__)) . '/languages');

        add_shortcode('damuro', array(__CLASS__, 'damuro_shortcode'));

        add_action('wp_default_scripts',    array(__CLASS__, 'default_scripts'));
        add_action('print_media_templates', array(__CLASS__, 'print_media_templates'));
        add_action('media_upload_damuro',   array(__CLASS__, 'media_upload_damuro'));
        add_action('admin_menu',            array(__CLASS__, 'register_admin_hooks'));

        add_filter('media_upload_tabs', array(__CLASS__, 'media_upload_tabs'));

        if (self::are_comment_drawings_enabled()) {
            self::register_comment_hooks();
        }
    }

    public static function register_admin_hooks() {
        /* Admin bits */
        add_options_page(
            __("Configure deviantART muro", "deviantart-muro"), // page title.
            __("deviantART muro", "deviantart-muro"),           // menu item.
            'manage_options',                                   // priv
            'deviantart-muro-settings',                         // slug
            array(__CLASS__, 'settings_page'));
        add_filter('plugin_action_links', array(__CLASS__, 'plugin_action_links'), 10, 2);
    }

    public static function register_comment_hooks() {
        add_action('wp_insert_comment', array(__CLASS__, 'insert_comment'), 10, 2);
        add_action('delete_comment',    array(__CLASS__, 'delete_comment'));

        add_action('comment_form_after_fields',    array(__CLASS__, 'comment_form_after_fields'));
        add_action('comment_form_logged_in_after', array(__CLASS__, 'comment_form_after_fields'), 20);

        add_filter('get_comment_text',  array(__CLASS__, 'get_comment_text'),  10, 2);
        add_filter('comment_id_fields', array(__CLASS__, 'comment_id_fields'));
    }

    public static function are_comment_drawings_enabled() {
        return get_option("damuro_comments_enabled") && self::are_comment_drawings_available();
    }

    public static function are_comment_drawings_available() {
        if (!is_null(self::$_comment_drawings_available)) {
            return self::$_comment_drawings_available;
        }

        if (!self::can_validate_image()) {
            self::$_comment_drawings_unavailability_reasons[] = "Your PHP install is missing the required GD, ImageMagick or Cairo extensions to safely validate image uploads.";
        }

        return self::$_comment_drawings_available = empty(self::$_comment_drawings_unavailability_reasons);
    }

    private static function damuro_iframe($options, $context = 'shortcode') {

        $url_options = array();
        if (!empty($options['background'])) {
            $url_options['background'] = urlencode($options['background']);
        } else {
            $value = get_option("damuro_default_background");
            if (!empty($value)) {
                $url_options['background'] = urlencode($value);
            }
        }
        foreach (array('width', 'height') as $dimension) {
            if ($value = get_option("damuro_default_canvas_{$dimension}")) {
                $url_options[$dimension] = urlencode($value);
            }
        }
        // Where on Sta.sh to save the drawing (defaults to "WordPress Drawings")
        if ($value = get_option("damuro_stash_folder")) {
            $url_options['stash_folder'] = urlencode($value);
        } else {
            $url_options['stash_folder'] = urlencode("WordPress Drawings");
        }
        // Debug option for dA developers, points it at our local muro virtual machine.
        $url_options['vm'] = 1;

        $url = get_option("damuro_sandbox_url");
        if (empty($url)) {
            $url = plugin_dir_url(__FILE__) . "deviantart_muro_sandbox.html";
        }
        $url = add_query_arg($url_options, $url);

        $ret = '<iframe class="muro" ' .
            (($context === 'comment' || $context === 'shortcode') ? 'data-' : '') .
            'src="' . esc_attr($url) . '"';

        if (!empty($options['id'])) {
            $ret .= ' id="' . esc_attr($options['id']) . '"';
        }

        $dimensions = self::get_dimensions_from_options($options, $context);
        foreach ($dimensions as $dimension => $value) {
            $ret .= " {$dimension}=\"{$value}\"";
        }

        $ret .= "></iframe>";

        // TODO: debug only
        //return esc_html($ret);
        return $ret;
    }

    public static function get_dimensions_from_options($options, $context) {
        $dimensions = array();
        foreach (array('width', 'height') as $dimension) {
            $value = empty($options[$dimension]) ? null : $options[$dimension];
            if (empty($value)) {
                $value = get_option("damuro_default_{$dimension}");
                if (empty($value)) {
                    $value = ($dimension == 'width') ? 800 : 600;
                }
            }
            // Set it to 'auto' or something if you want the width
            // to not be set, ie: if you're setting it via CSS.
            $value = (intval($value) > 0) ? intval($value) : null;
            if (!is_null($value)) {
                $dimensions[$dimension] = $value;
            }
        }
        return $dimensions;
    }

    // [damuro]
    // [damuro background='/my/amazing/image.jpg']
    // [damuro width='1024' height='768' background='/another/image.jpg']
    public static function damuro_shortcode($atts) {
        // Check that comments are enabled, display placeholder if not.
        if (!self::are_comment_drawings_enabled()) {
            return '[' . __("Error: Cannot use \"damuro\" shortcode while deviantART muro comments are disabled", "deviantart-muro") . ']';
        }

        // TODO: check is: comments_open($comment_post_ID) - ID defaults to current post
        // TODO: see /wp-comments-post.php for other restrictions.
        $atts = shortcode_atts(array(
            'background'    => '',
            'width'         => '',
            'height'        => '',
            ), $atts);

        return self::get_muro_container($atts, 'shortcode');
    }

    public static function print_media_templates() {
        wp_enqueue_script('damuro_media_library');
    }

    public static function default_scripts(&$scripts) {
        $scripts->add('damuro_uploader',       plugin_dir_url(__FILE__) . "js/deviantart_muro_uploader.js", array(), false);
        $scripts->add('damuro_media_uploader', plugin_dir_url(__FILE__) . "js/deviantart_muro_media_uploader.js", array('plupload', 'damuro_uploader'), false);
        $scripts->add('damuro_media_library',  plugin_dir_url(__FILE__) . "js/deviantart_muro_media_library.js", array(), false);
        if (self::are_comment_drawings_enabled()) {
            $scripts->add('damuro_comments',       plugin_dir_url(__FILE__) . "js/deviantart_muro_comments.js", array(), false);
        }
    }

    public static function media_upload_tabs($tabs) {
        $tabs['damuro'] = __("deviantART muro", "deviantart-muro");
        return $tabs;
    }

    public static function get_inline_splash_stylesheet() {
        if (self::$_inline_css_displayed) {
            return '';
        }
        self::$_inline_css_displayed = true;
        // Inline stylesheet is a pain, but we get loading flicker otherwise.
        return <<<'EOT'
            <style type="text/css">
            iframe { border: 0 none; width: 100%; height: 100%; }
            body { margin: 0; }
            .muro-modal {
                position: fixed;
                top:      30px;
                right:    30px;
                bottom:   30px;
                left:     30px;
                z-index:  160000;
                background-color: #fff;
            }
            .muro-modal-backdrop {
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
                z-index: 159900;
                opacity: 0.7;
                background-color: #000;
                min-height: 360px;
            }
            .muro-container, .muro, .muro-loading-inner { width: 100%; height: 100%; border: 0; }
            .muro-container { position: absolute; overflow: auto; }
            .muro-splash { display: table; position: absolute; width: 100%; height: 100%; }
            .muro-splash-inner { display: table-cell; vertical-align: middle; text-align: center; }
            .muro { position: absolute; }
            .muro, .muro-saving, .muro-unavailable { visibility: hidden; }
            </style>
EOT;
    }

    public static function get_muro_container($options, $context) {
        $dimensions = self::get_dimensions_from_options($options, $context);
        $style = '';
        if (!empty($dimensions)) {
            $style .= ' style="';
            foreach ($dimensions as $dimension => $value) {
                $style .= "{$dimension}: {$value}px;";
            }
            $style .= '"';
        }

        $ret = self::get_inline_splash_stylesheet();
        $ret .= '<div class="muro-' . esc_attr($context) . '"' . $style . '>';

        if ($options['modal']) {
            // TODO: add close button on modal during loading/saving
            $ret .= '<div class="muro-modal-container" style="display: none;"><div class="muro-modal">';
        }
        $ret .= '<div class="muro-container"' . $style . '>' .
            '<div class="muro-loading muro-splash"><div class="muro-splash-inner">' .
            __("Loading deviantART muro...", "deviantart-muro") .
            '</div></div>' .
            '<div class="muro-saving muro-splash"><div class="muro-splash-inner">' .
            __("Saving from deviantART muro...", "deviantart-muro") .
            '</div></div>' .
            '<div class="muro-unavailable muro-splash"><div class="muro-splash-inner">' .
            __("deviantART muro is unavailable while comment posting is disabled.", "deviantart-muro") .
            '</div></div>' .
            self::damuro_iframe($options, $context) .
            '</div>';
        if ($options['modal']) {
            $ret .= '</div><div class="muro-modal-backdrop"></div></div>';
        }
        $ret .= '</div>';
        return $ret;
    }

    public static function media_upload_damuro() {
        wp_enqueue_style('colors');
        wp_enqueue_media();
        wp_enqueue_script('damuro_media_uploader');
        echo self::get_muro_container(array(
            'id'     => 'media-tab-muro',
            'width'  => 'auto',
            'height' => 'auto',
            ), 'media-tab');
        do_action('admin_print_footer_scripts');
    }

    public static function plugin_action_links($links, $file) {
        if ($file == plugin_basename(dirname(__FILE__) . '/deviantart-muro.php')) {
            $links[] = '<a href="options-general.php?page=deviantart-muro-settings">'.__('Settings').'</a>';
        }

        return $links;
    }

    public static function settings_page() {

        ?><div class="wrap"><?php
        screen_icon();
        ?><h2><?php _e('Configure deviantART muro', "deviantart-muro") ?></h2><?php

        if (isset($_POST['submit'])) {
            if ( function_exists('current_user_can') && !current_user_can('manage_options') ) {
                die(__('Cheatin&#8217; uh?'));
            }

            check_admin_referer('deviantart-muro-settings');

            // TODO: Validation. This stuff is safe to pass on regardless, but better UX with validation.
            update_option('damuro_default_background',    $_POST['damuro_default_background']);
            update_option('damuro_sandbox_url',           $_POST['damuro_sandbox_url']);
            update_option('damuro_default_width',         $_POST['damuro_default_width']);
            update_option('damuro_default_height',        $_POST['damuro_default_height']);
            update_option('damuro_default_canvas_width',  $_POST['damuro_default_canvas_width']);
            update_option('damuro_default_canvas_height', $_POST['damuro_default_canvas_height']);
            update_option('damuro_comments_enabled',      $_POST['damuro_comments_enabled']);
            ?><div id="message" class="updated fade"><p><strong><?php _e('Options saved.', "deviantart-muro") ?></strong></p></div><?php
        }

        $wiki = 'https://github.com/deviantART/embedded-deviantART-muro/wiki/';
        $settingswiki   = $wiki . 'Embed-Options-Reference';
        $howitworkswiki = $wiki . 'How-It-Works';

        ?>
        <form action="options-general.php?page=deviantart-muro-settings" method="post">
        <?php wp_nonce_field('deviantart-muro-settings'); ?>

        <h3><?php _e('Image Settings', "deviantart-muro") ?></h3>
        <p><?php _e('The settings below effect the default values for the initial canvas that will be created when opening deviantART muro.', "deviantart-muro"); ?></p>
        <table class="form-table">

        <tr valign="top">
        <th scope="row"><label for="damuro_default_background"><?php _e('Default Background', "deviantart-muro") ?></label></th>
        <td><input id="damuro_default_background" name="damuro_default_background" type="text" size="45" value="<?php echo form_option('damuro_default_background'); ?>"></td>
        <td>(<a href="<?php echo $settingswiki ?>#wiki-background"><?php _e('What is this?', "deviantart-muro") ?></a>)</td>
        </tr>
        <tr valign="top">

        <th scope="row"><?php _e('Image Dimensions', "deviantart-muro") ?></th>
        <td>
          <label for="damuro_default_canvas_width">Width</label>
          <input id="damuro_default_canvas_width" name="damuro_default_canvas_width" type="number" step="1" min="0" size="4" value="<?php echo form_option('damuro_default_canvas_width'); ?>">
          <label for="damuro_default_canvas_height">Height</label>
          <input id="damuro_default_canvas_height" name="damuro_default_canvas_height" type="number" step="1" min="0" size="4" value="<?php echo form_option('damuro_default_canvas_height'); ?>">
        </td>
        <td>(<a href="<?php echo $settingswiki ?>#wiki-width"><?php _e('What is this?', "deviantart-muro") ?></a>)</td>
        </tr>
        <tr valign="top">
        <th scope="row"><label for="damuro_stash_folder"><?php _e('Sta.sh Folder', "deviantart-muro") ?></label></th>
        <td><input id="damuro_stash_folder" name="damuro_stash_folder" type="text" size="45" value="<?php echo form_option('damuro_stash_folder'); ?>"></td>
        <td>(<a href="<?php echo $settingswiki ?>#wiki-stash_folder"><?php _e('What is this?', "deviantart-muro") ?></a>)</td>
        </tr>

        </table>

        <h3><?php _e('Window Settings', "deviantart-muro") ?></h3>
        <p><?php _e("The settings below alter the size of the deviantART muro window that will be opened. You should probably leave these blank unless you're having issues with the deviantART muro window opening in an odd size because of a conflicting theme.", "deviantart-muro"); ?></p>
        <table class="form-table">

        <th scope="row"><?php _e('Window Dimensions', "deviantart-muro") ?></th>
        <td>
          <label for="damuro_default_width">Width</label>
          <input id="damuro_default_width" name="damuro_default_width" type="number" step="1" min="0" size="4" value="<?php echo form_option('damuro_default_width'); ?>">
          <label for="damuro_default_height">Height</label>
          <input id="damuro_default_height" name="damuro_default_height" type="number" step="1" min="0" size="4" value="<?php echo form_option('damuro_default_height'); ?>">
        </td>
        <td></td>
        </tr>

        </table>

        <h3><?php _e('Comments Settings', "deviantart-muro") ?></h3>
        <p><?php _e('The settings below effect the behaviour of deviantART muro in comments.', "deviantart-muro"); ?></p><?php
        if (!self::are_comment_drawings_available()) {
            ?><div class="error"><p><?php _e('Warning: deviantART muro comment drawings are not available because:', "deviantart-muro"); ?></p><ul style="list-style-type: disc; padding: 0 20px;"><?php
            foreach (self::$_comment_drawings_unavailability_reasons as $reason) {
                ?><li><?php _e($reason, "deviantart-muro"); ?></li><?php
            }
            ?></ul></div><?php
        }
        ?><table class="form-table">

        <tr valign="top">
        <th scope="row"><label for="damuro_comments_enabled"><?php _e('Allow deviantART muro comments?', "deviantart-muro") ?></label></th>
        <td><input id="damuro_comments_enabled" name="damuro_comments_enabled" type="checkbox" value="1"<?php echo get_option('damuro_comments_enabled') ? ' checked="1"' : '' ?>></td>
        <td></td>
        </tr>

        </table>

        <h3><?php _e('Security Settings', "deviantart-muro") ?></h3>
        <p><?php _e('The settings below effect the security configuration of the deviantART muro iframe.', "deviantart-muro"); ?></p>
        <table class="form-table">

        <tr valign="top">
        <th scope="row"><label for="damuro_sandbox_url"><?php _e('Sandbox URL', "deviantart-muro") ?></label></th>
        <td><input id="damuro_sandbox_url" name="damuro_sandbox_url" type="text" size="45" value="<?php echo form_option('damuro_sandbox_url'); ?>"></td>
        <td>(<a href="<?php echo $howitworkswiki ?>"><?php _e('What is this?', "deviantart-muro") ?></a>)</td>
        </tr>

        </table>


        <?php submit_button(); ?>
        </form>
        </div>
        <?php
    }

    /* Do we have any support libraries to validate that an uploaded image is an image? */
    public static function can_validate_image() {
        return function_exists('getimagesize') ||
            class_exists('Imagick') ||
            class_exists('CairoImageSurface');
    }

    public static function validate_image($file) {
        if (function_exists('getimagesize')) {
            if (!($info = @getimagesize($file))) {
                return false;
            }
            if ($info[2] === IMAGETYPE_PNG) {
                return array(
                    'width'  => $info[0],
                    'height' => $info[1],
                    );
            }
            return false;
        }

        // In case of crazy installs without getimagesize(), fallback to alternatives...

        if (class_exists('Imagick')) {
            $im = new Imagick();
            // This is a blind guess on the return from getImageFormat, it's undocumented and I don't have an install to test. :/
            if ($im->readImage($file) && (strtolower($im->getImageFormat()) === 'png')) {
                $ret = array(
                    'width'  => $im->getImageWidth(),
                    'height' => $im->getImageHeight(),
                    );
            } else {
                $ret = false;
            }
            $im->destroy();
            return $ret;
        }

        if (class_exists('CairoImageSurface')) {
            if (!($surface = CairoImageSurface::createFromPng($file))) {
                return false;
            }
            return array(
                'width'  => $surface->getWidth(),
                'height' => $surface->getHeight(),
                );
        }

        return false;
    }

    // TODO: save and validate before inserting comment, rename after inserting comment
    public static function insert_comment($comment_id, $comment) {

        if (!self::can_validate_image()) {
            return;
        }

        $post_id  = $comment->comment_post_ID;
        $file_id  = "deviantart_muro_image_{$post_id}";
        $filename = "deviantart_muro_comment_drawing_{$comment_id}.png";

        if (empty($_FILES[$file_id])) {
            if (empty($_POST['comment_deviantart_muro_image'])) {
                return;
            }
            // Fallback to grabbing raw base64 data from post field.
            $contents = base64_decode(str_replace(' ', '+', $_POST['comment_deviantart_muro_image']));
            $tmp_filename = tempnam(sys_get_temp_dir(), "damuro");
            if (file_put_contents($tmp_filename, $contents) === false) {
                // TODO: die with error
                return;
            }
        } else {
            $tmp_filename = $_FILES[$file_id]['tmp_name'];
            $contents     = null;
        }

        $validated = self::validate_image($tmp_filename);

        if ($contents) {
            unlink($tmp_filename);
        }

        if (!$validated) {
            // TODO: die with error
            return;
        }

        if (!$contents) {
            $contents = file_get_contents($tmp_filename);
        }

        $upload = wp_upload_bits($filename, null, $contents);
        if ($upload['error'] !== false) {
            // TODO: die with error
            return;
        }

        // TODO: resizing, etc
        // TODO: make thumbnail

        add_comment_meta($comment_id, 'deviantart_muro_image', array(
            'file'   => $upload['file'],
            'url'    => $upload['url'],
            'width'  => $validated['width'],
            'height' => $validated['height'],
            ));
    }

    public static function delete_comment($comment_id) {
        if (!($image = get_comment_meta($comment_id, 'deviantart_muro_image', true))) {
            return;
        }
        unlink($image['file']);
    }

    public static function get_comment_text($comment_content, $comment) {
        $comment_id = $comment->comment_ID;
        // TODO: check if image display is on.
        if (!($image = get_comment_meta($comment_id, 'deviantart_muro_image', true))) {
            return $comment_content;
        }
        // TODO: sizing, thumbnail, click-to-view options, etc
        // TODO: alignment
        $comment_content = '<div class="muro-comment-image"><div class="wp-caption" style="width: ' . (10 + max((int)$image['width'], 150)) . 'px"><img src="' .
            esc_attr($image['url']) . '" alt="" title="Drawn with deviantART muro." />' .
            '<p class="wp-caption-text">Drawn with <a href="' . esc_attr(self::$deviantart_muro_url) . '">deviantART muro</a>.</p></div></div>' . $comment_content;
        return $comment_content;
    }

    public static function comment_form_after_fields() {
        wp_enqueue_script('damuro_comments');
        ?>
        <div class="muro-comment-preview" style="display: none;"><img class="muro-comment-preview-image" /></div>
        <?php
        echo self::get_muro_container(array(
            'id'     => 'comment-muro',
            'width'  => 'auto',
            'height' => 'auto',
            'modal'  => true,
            ), 'comment');
    }

    /* This is a somewhat semantically-dodgy hijack of the hidden comment-form fields filter
     * as it's the only hook that runs with in the submit buttons div where we want to inject
     * our "Add Drawing" button.
     * If the hidden fields are moved outside the submit buttons div, this will break horribly.
     * Requires wordpress 3.0.0.
     */
    public static function comment_id_fields($result) {
        // To temporarily store the base64 image data before they submit.
        $result .= "<input class='muro-comment-store' type='hidden' name='comment_deviantart_muro_image' value='' />\n" .
            "<input class='muro-comment-add' type='button' name='draw' value='" . esc_attr__('Add drawing with deviantART muro', 'deviantart-muro') . "' />\n";
        return $result;
    }
}

Deviantart_Muro::register_hooks();

?>
