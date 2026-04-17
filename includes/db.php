<?php
defined('ABSPATH') || exit;

function KWWD_Slider_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $p       = $wpdb->prefix;

    $wpdb->query(
        "CREATE TABLE IF NOT EXISTS `{$p}KWWD_Slider_sliders` (
            `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
            `name`            VARCHAR(255)     NOT NULL,
            `subtitle`        VARCHAR(255)     NOT NULL,
            `use_global`      TINYINT(1)       NOT NULL DEFAULT 1,
            `show_title`      TINYINT(1)       NOT NULL DEFAULT 0,
            `show_subtitle`   TINYINT(1)       NOT NULL DEFAULT 0,
            `slides_visible`  TINYINT UNSIGNED NOT NULL DEFAULT 5,
            `autoplay`        TINYINT(1)       NOT NULL DEFAULT 1,
            `autoplay_delay`  INT UNSIGNED     NOT NULL DEFAULT 3000,
            `loop`            TINYINT(1)       NOT NULL DEFAULT 1,
            `bg_color`        VARCHAR(20)      NOT NULL DEFAULT '#f9f9f9',
            `arrow_color`     VARCHAR(20)      NOT NULL DEFAULT '#e72153',
            `dot_color`       VARCHAR(20)      NOT NULL DEFAULT '#e72153',
            `dot_color_light` VARCHAR(20)      NOT NULL DEFAULT '#f76b8f',
            `border`          TINYINT(1)       NOT NULL DEFAULT 0,
            `border_color`    VARCHAR(20)      NOT NULL DEFAULT '#e72153',
            `border_radius`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `slide_border`    TINYINT(1)       NOT NULL DEFAULT 0,
            `slide_border_color` VARCHAR(20)   NOT NULL DEFAULT '#e72153',
            `slide_border_radius` TINYINT UNSIGNED NOT NULL DEFAULT 4,
            `transition_speed`    INT UNSIGNED     NOT NULL DEFAULT 300,
            `effect`              VARCHAR(20)      NOT NULL DEFAULT 'slide',
            `space_between`       SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            `centered_slides`     TINYINT(1)       NOT NULL DEFAULT 0,
            `touch_swipe`         TINYINT(1)       NOT NULL DEFAULT 1,
            `grab_cursor`         TINYINT(1)       NOT NULL DEFAULT 1,
            `pause_on_hover`      TINYINT(1)       NOT NULL DEFAULT 1,
            `show_arrows`         TINYINT(1)       NOT NULL DEFAULT 1,
            `show_dots`           TINYINT(1)       NOT NULL DEFAULT 1,
            `generate_shortlinks` TINYINT(1)       NOT NULL DEFAULT 1,
            `shortlink_prefix` VARCHAR(50)       NULL,
            `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) $charset"
    );

    /** Global settings: key-value store **/
    $wpdb->query(
        "CREATE TABLE IF NOT EXISTS `{$p}KWWD_Slider_global_settings` (
            `setting_key`   VARCHAR(64)  NOT NULL,
            `setting_value` VARCHAR(512) NOT NULL DEFAULT '',
            PRIMARY KEY (`setting_key`)
        ) $charset"
    );

    $wpdb->query(
        "CREATE TABLE IF NOT EXISTS `{$p}KWWD_Slider_slides` (
            `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
            `slider_id`     INT UNSIGNED      NOT NULL,
            `title`         VARCHAR(255)      NOT NULL,
            `image_url`     VARCHAR(512)      NOT NULL DEFAULT '',
            `attachment_id` INT UNSIGNED      NOT NULL DEFAULT 0,
            `caption`       TEXT,
            `dest_url`      VARCHAR(512)      NOT NULL DEFAULT '',
            `short_url`     VARCHAR(512)      NOT NULL DEFAULT '',
            `slide_order`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `active`        TINYINT(1)        NOT NULL DEFAULT 1,
            `created_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `slider_id` (`slider_id`)
        ) $charset"
    );
}

/********************************************************************
 * Global settings
 * The canonical defaults for every global setting key.
 * Any key not yet saved in the DB will fall back to these values,
 * so the rest of the code always gets a fully-populated array.
 *******************************************************************/
function KWWDSlider_global_defaults(): array {
    return [
        'show_title'           => '0',
        'show_subtitle'        => '0',
        'subtitle'             => '',
        'subtitle_color'       => '#555555',
        'subtitle_size'        => '14',
        'subtitle_bold'        => '0',
        'subtitle_italic'      => '0',
        'subtitle_align'       => 'left',
        'slides_visible'       => '5',
        'autoplay'             => '1',
        'autoplay_delay'       => '3000',
        'loop'                 => '1',
        'bg_color'             => '#f9f9f9',
        'arrow_color'          => '#e72153',
        'dot_color'            => '#e72153',
        'dot_color_light'      => '#f76b8f',
        'border'               => '0',
        'border_color'         => '#e72153',
        'border_radius'        => '0',
        'slide_border'         => '0',
        'slide_border_color'   => '#e72153',
        'slide_border_radius'  => '4',
        // Swiper behaviour
        'transition_speed'     => '300',
        'effect'               => 'slide',
        'space_between'        => '10',
        'centered_slides'      => '0',
        'touch_swipe'          => '1',
        'grab_cursor'          => '1',
        'pause_on_hover'       => '1',
        'show_arrows'          => '1',
        'show_dots'            => '1',
        'generate_shortlinks'  => '0',
        'shortlink_prefix'     => '',
    ];
}

/********************************************************************************
 * Reads all rows from {prefix}KWWDSlider_global_settings and returns them as an
 * associative array merged over the defaults, so every key is always present.
 *******************************************************************************/
function KWWDSlider_get_global_settings(): array {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT setting_key, setting_value FROM `{$wpdb->prefix}KWWD_Slider_global_settings`",
        ARRAY_A
    ) ?: [];

    $saved = [];
    foreach ($rows as $row) {
        $saved[$row['setting_key']] = $row['setting_value'];
    }

    return array_merge(KWWDSlider_global_defaults(), $saved);
}

/***********************************************************************************
 * Validates $raw (typically $_POST) and upserts every setting key into the
 * {prefix}KWWDSlider_global_settings table. Uses INSERT … ON DUPLICATE KEY UPDATE
 * so it works whether a key already exists or is being saved for the first time.
 **********************************************************************************/
function KWWDSlider_save_global_settings(array $raw): void {
    global $wpdb;
    $tbl = $wpdb->prefix . 'KWWD_Slider_global_settings';
    $d   = KWWDSlider_global_defaults();

    $settings = [
        'show_title'          => (string)(int) !empty($raw['show_title']),
        'show_subtitle'       => (string)(int) !empty($raw['show_subtitle']),
        'subtitle'            => sanitize_text_field(wp_unslash($raw['subtitle'] ?? '')),
        'subtitle_color'      => sanitize_hex_color($raw['subtitle_color']  ?? $d['subtitle_color'])  ?: $d['subtitle_color'],
        'subtitle_size'       => (string) min(72, max(8, (int)($raw['subtitle_size'] ?? $d['subtitle_size']))),
        'subtitle_bold'       => (string)(int) !empty($raw['subtitle_bold']),
        'subtitle_italic'     => (string)(int) !empty($raw['subtitle_italic']),
        'subtitle_align'      => in_array($raw['subtitle_align'] ?? '', ['left','center','right'], true)
                                    ? $raw['subtitle_align']
                                    : $d['subtitle_align'],
        'slides_visible'      => (string) max(1, (int)($raw['slides_visible'] ?? $d['slides_visible'])),
        'autoplay'            => (string)(int) !empty($raw['autoplay']),
        'autoplay_delay'      => (string) max(1000, (int)($raw['autoplay_delay'] ?? $d['autoplay_delay'])),
        'loop'                => (string)(int) !empty($raw['loop']),
        'bg_color'            => sanitize_hex_color($raw['bg_color']           ?? $d['bg_color'])           ?: $d['bg_color'],
        'arrow_color'         => sanitize_hex_color($raw['arrow_color']        ?? $d['arrow_color'])        ?: $d['arrow_color'],
        'dot_color'           => sanitize_hex_color($raw['dot_color']          ?? $d['dot_color'])          ?: $d['dot_color'],
        'dot_color_light'     => sanitize_hex_color($raw['dot_color_light']    ?? $d['dot_color_light'])    ?: $d['dot_color_light'],
        'border'              => (string)(int) !empty($raw['border']),
        'border_color'        => sanitize_hex_color($raw['border_color']       ?? $d['border_color'])       ?: $d['border_color'],
        'border_radius'       => (string) min(50, max(0, (int)($raw['border_radius']  ?? $d['border_radius']))),
        'slide_border'        => (string)(int) !empty($raw['slide_border']),
        'slide_border_color'  => sanitize_hex_color($raw['slide_border_color'] ?? $d['slide_border_color']) ?: $d['slide_border_color'],
        'slide_border_radius' => (string) min(50, max(0, (int)($raw['slide_border_radius'] ?? $d['slide_border_radius']))),
        // Swiper behaviour
        'transition_speed'    => (string) min(5000, max(100, (int)($raw['transition_speed'] ?? $d['transition_speed']))),
        'effect'              => in_array($raw['effect'] ?? '', ['slide','fade','coverflow','flip','cube','cards','creative' ], true)
                                    ? $raw['effect'] : $d['effect'],
        'space_between'       => (string) min(100, max(0, (int)($raw['space_between'] ?? $d['space_between']))),
        'centered_slides'     => (string)(int) !empty($raw['centered_slides']),
        'touch_swipe'         => (string)(int) !empty($raw['touch_swipe']),
        'grab_cursor'         => (string)(int) !empty($raw['grab_cursor']),
        'pause_on_hover'      => (string)(int) !empty($raw['pause_on_hover']),
        'show_arrows'         => (string)(int) !empty($raw['show_arrows']),
        'show_dots'           => (string)(int) !empty($raw['show_dots']),
        'generate_shortlinks' => (int)$raw['generate_shortlinks'],
        'shortlink_prefix'    => $raw['shortlink_prefix'] ?? '',
    ];

    foreach ($settings as $key => $value) {
        $wpdb->query($wpdb->prepare(
            "INSERT INTO `{$tbl}` (setting_key, setting_value)
             VALUES (%s, %s)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            $key, $value
        ));
    }
}

/** Sliders **/
function KWWDSlider_get_sliders(): array {
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}KWWD_Slider_sliders ORDER BY id DESC") ?: [];
}

function KWWDSlider_get_slider(int $id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}KWWD_Slider_sliders WHERE id = %d", $id));
}

/******************************************************************************
 * Returns the effective design settings for a slider as a plain object.
 *
 * When use_global is 1 the global settings table values override every design
 * property so the front end always reflects the master settings.
 * The slider's own name/subtitle/id are always taken from the slider row.
 *****************************************************************************/
function KWWDSlider_get_active_slider_settings(int $slider_id): ?object {
    $slider = KWWDSlider_get_slider($slider_id);
    if (!$slider) return null;

    if (!empty($slider->use_global)) {
        $g = KWWDSlider_get_global_settings(); // defined in db.php

        /**  Overlay global values onto the slider object, keeping identity  **/
        foreach ($g as $key => $value) {
            $slider->$key = $value;
        }
    }

    return $slider;
}

function KWWDSlider_save_slider(array $data, int $id = 0): int {
    global $wpdb;
    $tbl  = $wpdb->prefix . 'KWWD_Slider_sliders';

    $name = sanitize_text_field(wp_unslash($data['name'] ?? ''));
    $subtitle = sanitize_text_field(wp_unslash($data['subtitle'] ?? ''));
    $ug   = (int) !empty($data['use_global']);
    $sv   = max(1, (int)($data['slides_visible'] ?? 5));
    $ap   = (int)!empty($data['autoplay']);
    $ad   = max(1000, (int)($data['autoplay_delay'] ?? 3000));
    $lp   = (int)!empty($data['loop']);
    $st   = (int)!empty($data['show_title']);
    $showsubtitle = (int)!empty($data['show_subtitle']);
    $bg   = sanitize_hex_color($data['bg_color']            ?? '#f9f9f9') ?: '#f9f9f9';
    $ac   = sanitize_hex_color($data['arrow_color']         ?? '#e72153') ?: '#e72153';
    $dc   = sanitize_hex_color($data['dot_color']           ?? '#e72153') ?: '#e72153';
    $dcl  = sanitize_hex_color($data['dot_color_light']     ?? '#f76b8f') ?: '#f76b8f';
    $bo   = (int)!empty($data['border']);
    $bc   = sanitize_hex_color($data['border_color']        ?? '#e72153') ?: '#e72153';
    $br   = min(50, max(0, (int)($data['border_radius']     ?? 0)));
    $sb   = (int)!empty($data['slide_border']);
    $sbc  = sanitize_hex_color($data['slide_border_color']  ?? '#e72153') ?: '#e72153';
    $sbr  = min(50, max(0, (int)($data['slide_border_radius'] ?? 4)));
    // Swiper behaviour
    $ts   = min(5000, max(100, (int)($data['transition_speed'] ?? 300)));
    $ef   = in_array($data['effect'] ?? '', ['slide','fade','coverflow','flip', 'cube', 'cards', 'creative'], true) ? $data['effect'] : 'slide';
    $spb  = min(100, max(0, (int)($data['space_between'] ?? 10)));
    $cs   = (int)!empty($data['centered_slides']);
    $tsw  = (int)!empty($data['touch_swipe'] ?? 1);
    $gc   = (int)!empty($data['grab_cursor'] ?? 1);
    $poh  = (int)!empty($data['pause_on_hover'] ?? 1);
    $sa   = isset($data['show_arrows']) ? (int)!empty($data['show_arrows']) : 1;
    $sd   = isset($data['show_dots'])   ? (int)!empty($data['show_dots'])   : 1;
    $gl = (int)$data['generate_shortlinks'];
    $lpre = sanitize_text_field($data['shortlink_prefix']);

    if ($id) {
        $wpdb->query($wpdb->prepare(
            "UPDATE `{$tbl}` SET name=%s, subtitle=%s, use_global=%d,
             show_title=%d, show_subtitle=%d, slides_visible=%d,
             autoplay=%d, autoplay_delay=%d, `loop`=%d,
             bg_color=%s, arrow_color=%s, dot_color=%s, dot_color_light=%s,
             border=%d, border_color=%s, border_radius=%d,
             slide_border=%d, slide_border_color=%s, slide_border_radius=%d,
             transition_speed=%d, effect=%s, space_between=%d,
             centered_slides=%d, touch_swipe=%d, grab_cursor=%d,
             pause_on_hover=%d, show_arrows=%d, show_dots=%d, generate_shortlinks=%d, shortlink_prefix=%s
             WHERE id=%d",
            $name, $subtitle, $ug,
            $st, $showsubtitle, $sv, $ap, $ad, $lp,
            $bg, $ac, $dc, $dcl,
            $bo, $bc, $br,
            $sb, $sbc, $sbr,
            $ts, $ef, $spb,
            $cs, $tsw, $gc,
            $poh, $sa, $sd, $gl, $lpre,
            $id
        ));
        return $id;
    }

    $wpdb->query($wpdb->prepare(
        "INSERT INTO `{$tbl}`
         (name, subtitle, use_global, show_title, show_subtitle, slides_visible,
          autoplay, autoplay_delay, `loop`,
          bg_color, arrow_color, dot_color, dot_color_light,
          border, border_color, border_radius,
          slide_border, slide_border_color, slide_border_radius,
          transition_speed, effect, space_between,
          centered_slides, touch_swipe, grab_cursor,
          pause_on_hover, show_arrows, show_dots, generate_shortlinks, shortlink_prefix)
         VALUES (%s,%s,%d,%d,%d,%d,%d,%d,%d,%s,%s,%s,%s,%d,%s,%d,%d,%s,%d,%d,%s,%d,%d,%d,%d,%d,%d,%d,%d,%s)",
        $name, $subtitle, $ug,
        $st, $showsubtitle, $sv, $ap, $ad, $lp,
        $bg, $ac, $dc, $dcl,
        $bo, $bc, $br,
        $sb, $sbc, $sbr,
        $ts, $ef, $spb,
        $cs, $tsw, $gc,
        $poh, $sa, $sd, $gl, $lpre  
    ));

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `{$tbl}` WHERE name = %s ORDER BY id DESC LIMIT 1", $name
    ));
}

function KWWDSlider_delete_slider(int $id): void {
    global $wpdb;
    // Clean up all short links for slides in this slider first
    $slides = KWWDSlider_get_slides($id);
    $UsingShortlink = KWWDSlider_has_shortlink_active();
    foreach ($slides as $slide) {
        if ($UsingShortlink && $slide->short_url) {
            KWWDSlider_delete_short_link($slide->short_url);
        }
    }
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}KWWD_Slider_sliders WHERE id = %d", $id));
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}KWWD_Slider_slides WHERE slider_id = %d", $id));
}

function KWWDSlider_delete_short_link(string $slug): void {
    global $wpdb;
    $links_tbl  = $wpdb->prefix . 'kc_us_links';
    $groups_tbl = $wpdb->prefix . 'kc_us_links_groups';
    $link_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `{$links_tbl}` WHERE slug = %s LIMIT 1", $slug
    ));
    if ($link_id) {
        $wpdb->delete($groups_tbl, ['link_id' => $link_id], ['%d']);
        $wpdb->delete($links_tbl,  ['id'      => $link_id], ['%d']);
    }
}

function KWWDSlider_get_slides(int $slider_id): array {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}KWWD_Slider_slides WHERE slider_id = %d ORDER BY slide_order ASC, id ASC",
        $slider_id
    )) ?: [];
}

function KWWDSlider_get_slide(int $id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}KWWD_Slider_slides WHERE id = %d", $id));
}

function KWWDSlider_save_slide(array $data, int $id = 0): int {
    global $wpdb;
    $tbl       = $wpdb->prefix . 'KWWD_Slider_slides';
    $title = sanitize_text_field(wp_unslash($data['title'] ?? ''));
    $caption = sanitize_textarea_field(wp_unslash($data['caption'] ?? ''));
    $dest      = esc_url_raw($data['dest_url'] ?? '');
    $short     = $data['short_url'] ?? '';
    $slider_id = (int)($data['slider_id'] ?? 0);
    $UsingShortlink = (int)KWWDSlider_has_shortlink_active(); // If the URL Shortify Plugin is active
    $GenerateShortLinks = 0; // Default
    $ShortlinkPrefix = '';

    // Look up if using global or slider settings - only if we have plugin running!
if($UsingShortlink===1)
{

    $SettingQuery = $wpdb->prepare("SELECT use_global, generate_shortlinks, shortlink_prefix FROM {$wpdb->prefix}KWWD_Slider_sliders WHERE id=%d", $slider_id);

    $SettingResult = $wpdb->get_row($SettingQuery);

    if((int)$SettingResult->use_global===0)
    {
        // Grab per slider settings
        $GenerateShortLinks = (int)$SettingResult->generate_shortlinks; // user setting to use short URL
        $ShortlinkPrefix = $SettingResult->shortlink_prefix ?? '';
    }
    else
    {
        // grab our global settings
        $g = KWWDSlider_get_global_settings();
        $GenerateShortLinks = (int)$g['generate_shortlinks']; // user setting to use short URL
        $ShortlinkPrefix = $g['shortlink_prefix'] ?? '';
    }
}

    if ($UsingShortlink && ($dest && empty($short)) && $GenerateShortLinks) {
        $short = KWWDSlider_create_short_link($title, $dest, $ShortlinkPrefix);
    }



    if ($id) {
        $wpdb->query($wpdb->prepare(
            "UPDATE `{$tbl}` SET title=%s, image_url=%s, attachment_id=%d, caption=%s,
             dest_url=%s, short_url=%s, slide_order=%d, active=%d WHERE id=%d",
            $title,
            esc_url_raw($data['image_url'] ?? ''),
            (int)($data['attachment_id'] ?? 0),
            $caption,
            $dest, sanitize_text_field($short),
            (int)($data['slide_order'] ?? 0),
            (int)!empty($data['active']),
            $id
        ));
        return $id;
    }

    $max_order = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(slide_order) FROM `{$tbl}` WHERE slider_id = %d", $slider_id
    ));

    $wpdb->query($wpdb->prepare(
        "INSERT INTO `{$tbl}` (slider_id, title, image_url, attachment_id, caption, dest_url, short_url, slide_order, active)
         VALUES (%d,%s,%s,%d,%s,%s,%s,%d,%d)",
        $slider_id, $title,
        esc_url_raw($data['image_url'] ?? ''),
        (int)($data['attachment_id'] ?? 0),
        sanitize_textarea_field($data['caption'] ?? ''),
        $dest, sanitize_text_field($short),
        $max_order + 1,
        (int)!empty($data['active'])
    ));

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `{$tbl}` WHERE slider_id = %d AND title = %s ORDER BY id DESC LIMIT 1",
        $slider_id, $title
    ));
}

function KWWDSlider_delete_slide(int $id): void {
    global $wpdb;
    $slide = KWWDSlider_get_slide($id);
    if ($slide && $slide->short_url) {
        KWWDSlider_delete_short_link($slide->short_url);
    }
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}KWWDSlider_slides WHERE id = %d", $id));
}

function KWWDSlider_reorder_slides(array $order): void {
    global $wpdb;
    $tbl = $wpdb->prefix . 'KWWD_Slider_slides';
    foreach ($order as $position => $slide_id) {
        $wpdb->query($wpdb->prepare("UPDATE `{$tbl}` SET slide_order = %d WHERE id = %d", (int)$position, (int)$slide_id));
    }
}

function KWWDSlider_toggle_slide(int $id, int $active): void {
    global $wpdb;
    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}KWWD_Slider_slides SET active = %d WHERE id = %d", $active, $id));
}

function KWWDSlider_make_slug(string $title): string {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-');
}

function KWWDSlider_create_short_link(string $title, string $dest_url, string $ShortlinkPrefix): string {
    global $wpdb;
    $slug       = KWWDSlider_make_slug($title);
    $slug       = $ShortlinkPrefix.$slug; // add in the user prefix
    $now        = current_time('mysql');
    $user_id    = get_current_user_id();
    $links_tbl  = $wpdb->prefix . 'kc_us_links';
    $groups_tbl = $wpdb->prefix . 'kc_us_links_groups';

    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$links_tbl}` WHERE slug = %s LIMIT 1", $slug));
    if ($existing) {
        // That aready exists, make a unique slug
        $slug .= '-' . substr(uniqid(), -4);
    }

    $wpdb->query($wpdb->prepare(
        "INSERT INTO `{$links_tbl}` (slug, name, url, nofollow, sponsored, track_me, redirect_type, status, created_at, created_by_id, updated_at, updated_by_id)
         VALUES (%s,%s,%s,1,1,1,'307',1,%s,%d,%s,%d)",
        $slug, $title, $dest_url, $now, $user_id, $now, $user_id
    ));

    $link_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$links_tbl}` WHERE slug = %s LIMIT 1", $slug));

    if ($link_id) {
        $wpdb->query($wpdb->prepare(
            "INSERT INTO `{$groups_tbl}` (link_id, group_id, created_by_id, created_at) VALUES (%d,%d,%d,%s)",
            $link_id, KWWDSlider_SHORTIFY_GROUP, $user_id, $now
        ));
    }

    return $link_id ? $slug : '';
}

function KWWDSlider_handle_image_upload(array $file, string $title): array {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $uploaded = wp_handle_upload($file, ['test_form' => false]);
    if (isset($uploaded['error'])) return ['error' => $uploaded['error']];

    $src_path = $uploaded['file'];
    if (!KWWDSlider_resize_image($src_path)) return ['error' => 'Image resize failed — check GD is enabled'];

    $clean_name = sanitize_title($title);
    $ext        = strtolower(pathinfo($src_path, PATHINFO_EXTENSION));
    $new_path   = dirname($src_path) . '/' . $clean_name . '.' . $ext;
    if ($new_path !== $src_path) rename($src_path, $new_path);

    $attachment_id = wp_insert_attachment([
        'post_title'     => $title,
        'post_content'   => '',
        'post_status'    => 'inherit',
        'post_mime_type' => $uploaded['type'],
    ], $new_path);

    if (is_wp_error($attachment_id)) return ['error' => $attachment_id->get_error_message()];

    update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);
    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $new_path));

    return ['attachment_id' => $attachment_id, 'url' => wp_get_attachment_url($attachment_id)];
}

function KWWDSlider_resize_image(string $path): bool {
    $info = @getimagesize($path);
    if (!$info) return false;

    [$origW, $origH, $type] = [$info[0], $info[1], $info[2]];

    $newH = KWWDSlider_IMG_MAX_HEIGHT;
    $newW = (int) round($origW * ($newH / $origH));

    $src = match($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG  => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => @imagecreatefromwebp($path),
        IMAGETYPE_GIF  => @imagecreatefromgif($path),
        default        => false,
    };
    if (!$src) return false;

    $dst = imagecreatetruecolor($newW, $newH);

    if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    } else {
        imagefilledrectangle($dst, 0, 0, $newW, $newH, imagecolorallocate($dst, 255, 255, 255));
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    $ok = match($type) {
        IMAGETYPE_JPEG => imagejpeg($dst, $path, 90),
        IMAGETYPE_PNG  => imagepng($dst, $path, 6),
        IMAGETYPE_WEBP => imagewebp($dst, $path, 90),
        IMAGETYPE_GIF  => imagegif($dst, $path),
        default        => false,
    };

    //imagedestroy($src);
    //imagedestroy($dst);
    unset($src);
    unset($dst);
    return (bool) $ok;
}

function KWWDSlider_has_shortlink_active(): bool {
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    return is_plugin_active('url-shortify/url-shortify.php');
}
?>