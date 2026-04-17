<?php
/*************************************************************************************
 * Simple Slider by KWWD – Global Settings (admin UI)
 *
 * Data storage and retrieval is handled entirely by KWWDSlider_get_global_settings()
 * and KWWDSlider_save_global_settings() in db.php, which read/write the
 * {prefix}KWWDSlider_global_settings key-value table.
 *
 * This file registers the submenu page and renders the settings form only.
 ************************************************************************************/

defined('ABSPATH') || exit;

/** Admin: register submenu **/

add_action('admin_menu', 'KWWDSlider_global_settings_menu', 20);

function KWWDSlider_global_settings_menu(): void {
    add_submenu_page(
        'kwwd-slider',
        'Global Settings',
        'Global Settings',
        'manage_options',
        'kwwd-slider-global',
        'KWWDSlider_global_settings_page'
    );
}

/** Admin: page renderer **/

function KWWDSlider_global_settings_page(): void {

    $saved = false;
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['KWWDSlider_global_action'])
        && $_POST['KWWDSlider_global_action'] === 'save_global'
        && check_admin_referer('KWWDSlider_global_settings')
        && current_user_can('manage_options')
    ) {
        KWWDSlider_save_global_settings($_POST);
        $saved = true;
    }

    $g = KWWDSlider_get_global_settings();
    ?>
    <div class="wrap kwwd-admin">
        <h1>Simple Slider by KWWD – Global Settings</h1>
        <p class="description" style="margin-bottom:1rem">
            These values are used by any slider that has <strong>Use Global Settings</strong> turned on.
            Changes here are reflected immediately on the front end — no need to re-save individual sliders.
        </p>

        <?php if ($saved): ?>
        <div class="notice notice-success is-dismissible"><p>Global settings saved.</p></div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('KWWDSlider_global_settings') ?>
            <input type="hidden" name="KWWDSlider_global_action" value="save_global">

            <!-- ** Display ** -->
            <div class="postbox" style="max-width:760px">
                <div class="postbox-header"><h2>Display</h2></div>
                <div class="inside">
                    <table class="form-table" style="margin-top:0">
                        <tr>
                            <th><label for="g_show_title">Show Title</label></th>
                            <td>
                                <input type="checkbox" id="g_show_title" name="show_title" value="1"
                                       <?= $g['show_title'] ? 'checked' : '' ?>>
                                Display the slider name above the carousel
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_show_subtitle">Show Subtitle</label></th>
                            <td>
                                <input type="checkbox" id="g_show_subtitle" name="show_subtitle" value="1"
                                       <?= $g['show_subtitle'] ? 'checked' : '' ?>>
                                Show subtitle text below the title
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_slides_visible">Slides Visible</label></th>
                            <td>
                                <input type="number" id="g_slides_visible" name="slides_visible"
                                       min="1" max="10" value="<?= (int) $g['slides_visible'] ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_autoplay">Autoplay</label></th>
                            <td>
                                <input type="checkbox" id="g_autoplay" name="autoplay" value="1"
                                       <?= $g['autoplay'] ? 'checked' : '' ?>>
                                Enable autoplay
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_autoplay_delay">Autoplay Delay (ms)</label></th>
                            <td>
                                <input type="number" id="g_autoplay_delay" name="autoplay_delay"
                                       min="1000" step="500" value="<?= (int) $g['autoplay_delay'] ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_loop">Loop</label></th>
                            <td>
                                <input type="checkbox" id="g_loop" name="loop" value="1"
                                       <?= $g['loop'] ? 'checked' : '' ?>>
                                Enable loop
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ** Default Subtitle ** -->
            <div class="postbox" style="max-width:760px;margin-top:1rem">
                <div class="postbox-header"><h2>Default Subtitle</h2></div>
                <div class="inside">
                    <p class="description" style="margin-bottom:.75rem">
                        When <strong>Show Subtitle</strong> is enabled above, this text and styling
                        will appear on every slider that uses Global Settings — no need to set it per slider.
                    </p>
                    <table class="form-table" style="margin-top:0">
                        <tr>
                            <th><label for="g_subtitle">Subtitle Text</label></th>
                            <td>
                                <input type="text" id="g_subtitle" name="subtitle" class="large-text"
                                       value="<?= esc_attr($g['subtitle']) ?>"
                                       placeholder="e.g. These are Amazon Affiliate links…">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_subtitle_color">Font Colour</label></th>
                            <td><input type="color" id="g_subtitle_color" name="subtitle_color" value="<?= esc_attr($g['subtitle_color']) ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="g_subtitle_size">Font Size (px)</label></th>
                            <td>
                                <input type="number" id="g_subtitle_size" name="subtitle_size"
                                       min="8" max="72" value="<?= (int) $g['subtitle_size'] ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Style</th>
                            <td>
                                <label style="margin-right:1.25rem">
                                    <input type="checkbox" name="subtitle_bold" value="1"
                                           <?= $g['subtitle_bold'] ? 'checked' : '' ?>>
                                    <strong>Bold</strong>
                                </label>
                                <label>
                                    <input type="checkbox" name="subtitle_italic" value="1"
                                           <?= $g['subtitle_italic'] ? 'checked' : '' ?>>
                                    <em>Italic</em>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_subtitle_align">Alignment</label></th>
                            <td>
                                <select id="g_subtitle_align" name="subtitle_align">
                                    <option value="left"   <?= $g['subtitle_align'] === 'left'   ? 'selected' : '' ?>>Left</option>
                                    <option value="center" <?= $g['subtitle_align'] === 'center' ? 'selected' : '' ?>>Centre</option>
                                    <option value="right"  <?= $g['subtitle_align'] === 'right'  ? 'selected' : '' ?>>Right</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ** Colours ** -->
            <div class="postbox" style="max-width:760px;margin-top:1rem">
                <div class="postbox-header"><h2>Colours</h2></div>
                <div class="inside">
                    <table class="form-table" style="margin-top:0">
                        <tr>
                            <th><label for="g_bg_color">Background</label></th>
                            <td><input type="color" id="g_bg_color" name="bg_color" value="<?= esc_attr($g['bg_color']) ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="g_arrow_color">Arrows</label></th>
                            <td><input type="color" id="g_arrow_color" name="arrow_color" value="<?= esc_attr($g['arrow_color']) ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="g_dot_color">Dot (active)</label></th>
                            <td><input type="color" id="g_dot_color" name="dot_color" value="<?= esc_attr($g['dot_color']) ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="g_dot_color_light">Dot (inactive)</label></th>
                            <td><input type="color" id="g_dot_color_light" name="dot_color_light" value="<?= esc_attr($g['dot_color_light']) ?>"></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ** Slider Border ** -->
            <div class="postbox" style="max-width:760px;margin-top:1rem">
                <div class="postbox-header"><h2>Slider Border</h2></div>
                <div class="inside">
                    <table class="form-table" style="margin-top:0">
                        <tr>
                            <th><label for="g_border">Show Border</label></th>
                            <td><input type="checkbox" id="g_border" name="border" value="1" <?= $g['border'] ? 'checked' : '' ?>></td>
                        </tr>
                        <tr>
                            <th><label for="g_border_color">Colour</label></th>
                            <td><input type="color" id="g_border_color" name="border_color" value="<?= esc_attr($g['border_color']) ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="g_border_radius">Radius (px)</label></th>
                            <td><input type="number" id="g_border_radius" name="border_radius" min="0" max="50" value="<?= (int) $g['border_radius'] ?>"></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ** Slide Border ** -->
            <div class="postbox" style="max-width:760px;margin-top:1rem">
                <div class="postbox-header"><h2>Slide Border</h2></div>
                <div class="inside">
                    <table class="form-table" style="margin-top:0">
                        <tr>
                            <th><label for="g_slide_border">Show Border</label></th>
                            <td><input type="checkbox" id="g_slide_border" name="slide_border" value="1" <?= $g['slide_border'] ? 'checked' : '' ?>></td>
                        </tr>
                        <tr>
                            <th><label for="g_slide_border_color">Colour</label></th>
                            <td><input type="color" id="g_slide_border_color" name="slide_border_color" value="<?= esc_attr($g['slide_border_color']) ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="g_slide_border_radius">Radius (px)</label></th>
                            <td><input type="number" id="g_slide_border_radius" name="slide_border_radius" min="0" max="50" value="<?= (int) $g['slide_border_radius'] ?>"></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ** Carousel Behaviour ** -->
            <div class="postbox" style="max-width:760px;margin-top:1rem">
                <div class="postbox-header"><h2>Carousel Behaviour</h2></div>
                <div class="inside">
                    <table class="form-table" style="margin-top:0">
                        <tr>
                            <th><label for="g_effect">Transition Effect</label></th>
                            <td>
                                <select id="g_effect" name="effect">
                                    <?php foreach (['slide'=>'Slide','fade'=>'Fade','coverflow'=>'Coverflow','flip'=>'Flip','cube'=>'Cube','cards'=>'Cards','creative'=>'Creative'] as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $g['effect'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Coverflow looks great for book/DVD covers.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_transition_speed">Transition Speed (ms)</label></th>
                            <td>
                                <input type="number" id="g_transition_speed" name="transition_speed"
                                       min="100" max="5000" step="50" value="<?= (int) $g['transition_speed'] ?>">
                                <p class="description">How fast the slide animation is (not the pause between slides).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_space_between">Space Between Slides (px)</label></th>
                            <td>
                                <input type="number" id="g_space_between" name="space_between"
                                       min="0" max="100" value="<?= (int) $g['space_between'] ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_centered_slides">Centred Slides</label></th>
                            <td>
                                <input type="checkbox" id="g_centered_slides" name="centered_slides" value="1"
                                       <?= $g['centered_slides'] ? 'checked' : '' ?>>
                                Keep the active slide centred in the viewport
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_touch_swipe">Touch / Swipe</label></th>
                            <td>
                                <input type="checkbox" id="g_touch_swipe" name="touch_swipe" value="1"
                                       <?= $g['touch_swipe'] ? 'checked' : '' ?>>
                                Allow swiping on touch devices
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_grab_cursor">Grab Cursor</label></th>
                            <td>
                                <input type="checkbox" id="g_grab_cursor" name="grab_cursor" value="1"
                                       <?= $g['grab_cursor'] ? 'checked' : '' ?>>
                                Show grab cursor and allow mouse drag on desktop
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_pause_on_hover">Pause on Hover</label></th>
                            <td>
                                <input type="checkbox" id="g_pause_on_hover" name="pause_on_hover" value="1"
                                       <?= $g['pause_on_hover'] ? 'checked' : '' ?>>
                                Pause autoplay when the mouse is over the carousel
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ** Navigation ** -->
            <div class="postbox" style="max-width:760px;margin-top:1rem">
                <div class="postbox-header"><h2>Navigation</h2></div>
                <div class="inside">
                    <table class="form-table" style="margin-top:0">
                        <tr>
                            <th><label for="g_show_arrows">Show Arrows</label></th>
                            <td>
                                <input type="checkbox" id="g_show_arrows" name="show_arrows" value="1"
                                       <?= $g['show_arrows'] ? 'checked' : '' ?>>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="g_show_dots">Show Dots</label></th>
                            <td>
                                <input type="checkbox" id="g_show_dots" name="show_dots" value="1"
                                       <?= $g['show_dots'] ? 'checked' : '' ?>>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <p style="margin-top:1rem"><?php submit_button('Save Global Settings', 'primary', 'submit', false) ?></p>
        </form>
    </div>
    <?php
}
?>