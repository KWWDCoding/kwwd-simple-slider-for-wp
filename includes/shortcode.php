<?php
defined('ABSPATH') || exit;

add_shortcode('simple_slider', 'KWWDSlider_slider_shortcode');

function KWWDSlider_slider_shortcode(array $atts): string {
    $atts   = shortcode_atts(['id' => 0], $atts, 'KWWDSlider_slider');
    $id     = (int) $atts['id'];
    if (!$id) return '<!-- KWWDSlider_slider: no id provided -->';

    /********************************************************************************* 
     * Returns the slider row with design fields overridden by global settings
     * if the slider's use_global flag is set — otherwise returns per-slider values.
     *********************************************************************************/
    $slider = KWWDSlider_get_active_slider_settings($id);
    if (!$slider) return '<!-- KWWDSlider_slider: slider not found -->';

    $slides = array_filter(KWWDSlider_get_slides($id), fn($s) => $s->active);
    if (empty($slides)) return '';

    $uid = 'kwwd-swiper-' . $id;

    /**  Build inline CSS variables for this slider instance **/
    $css = implode(';', array_filter([
        '--kwwd-bg:'         . esc_attr($slider->bg_color),
        '--kwwd-arrow:'      . esc_attr($slider->arrow_color),
        '--kwwd-dot:'        . esc_attr($slider->dot_color),
        '--kwwd-dot-light:'  . esc_attr($slider->dot_color_light),
        '--kwwd-br:'         . (int)$slider->border_radius . 'px',
        '--kwwd-slide-br:'   . (int)$slider->slide_border_radius . 'px',
        $slider->border       ? '--kwwd-border:1px solid ' . esc_attr($slider->border_color)       : '',
        $slider->slide_border ? '--kwwd-slide-border:1px solid ' . esc_attr($slider->slide_border_color) : '',
    ]));

    ob_start();
    ?>
    <div class="kwwd-slider-wrap" style="<?= esc_attr($css) ?>">
        <?php if ($slider->show_title): ?>
        <h3 class="kwwd-slider-title"><?= esc_html($slider->name) ?></h3>
        <?php endif; ?>
        <?php if ($slider->show_subtitle):
            /******************************************************************************
             * When using global settings the subtitle text and styling come from
             * wp_options; per-slider mode just renders the slider's own subtitle plainly.
             *******************************************************************************/
            if (!empty($slider->use_global)) {
                $g = KWWDSlider_get_global_settings();
                $subtitle_text = $g['subtitle'];
                $sub_css = implode(';', array_filter([
                    'color:'       . esc_attr($g['subtitle_color']),
                    'font-size:'   . (int) $g['subtitle_size'] . 'px',
                    'font-weight:' . ($g['subtitle_bold']   ? 'bold'   : 'normal'),
                    'font-style:'  . ($g['subtitle_italic'] ? 'italic' : 'normal'),
                    'text-align:'  . esc_attr($g['subtitle_align']),
                ]));
            } else {
                $subtitle_text = $slider->subtitle;
                $sub_css = '';
            }
        ?>
        <p class="kwwd-slider-subtitle"<?= $sub_css ? ' style="' . esc_attr($sub_css) . '"' : '' ?>>
            <?= esc_html($subtitle_text) ?>
        </p>
        <?php endif; ?>
        <div class="swiper <?= esc_attr($uid) ?>"
             data-slides-visible="<?= (int)$slider->slides_visible ?>"
             data-autoplay="<?= (int)$slider->autoplay ?>"
             data-autoplay-delay="<?= (int)$slider->autoplay_delay ?>"
             data-loop="<?= (int)$slider->loop ?>"
             data-transition-speed="<?= (int)$slider->transition_speed ?>"
             data-effect="<?= esc_attr($slider->effect) ?>"
             data-space-between="<?= (int)$slider->space_between ?>"
             data-centered-slides="<?= (int)$slider->centered_slides ?>"
             data-touch-swipe="<?= (int)$slider->touch_swipe ?>"
             data-grab-cursor="<?= (int)$slider->grab_cursor ?>"
             data-pause-on-hover="<?= (int)$slider->pause_on_hover ?>"
             data-show-arrows="<?= (int)$slider->show_arrows ?>"
             data-show-dots="<?= (int)$slider->show_dots ?>">
            <div class="swiper-wrapper">
                <?php 
                $using_shortify = KWWDSlider_has_shortlink_active();

                foreach ($slides as $slide):
                    if ($using_shortify && !empty($slide->short_url)) {
                        $su = $slide->short_url;
                        $su = preg_replace('#^https?://[^/]*/(?:go/)?#i', '', $su);
                        $su = trim($su, '/');
                        $link = $su ? home_url('/' . $su) : $slide->dest_url;
                    } else {
                        $link = $slide->dest_url;
                    }
                ?>
                <div class="swiper-slide kwwd-slide">
                    <?php if ($link): ?>
                    <a href="<?= esc_url($link) ?>" target="_blank" rel="nofollow sponsored noopener">
                    <?php endif; ?>    
                        <img src="<?= esc_url($slide->image_url) ?>"
                             alt="<?= esc_attr($slide->title) ?>"
                             loading="lazy"
                             height="<?= KWWDSlider_IMG_MAX_HEIGHT ?>">
                        <?php if (!empty($slide->caption)): ?>
                        <p class="kwwd-caption"><?= esc_html($slide->caption) ?></p>
                        <?php endif; ?>
                    <?php if ($link): ?></a><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($slider->show_arrows): ?>
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
            <?php endif; ?>
            <?php if ($slider->show_dots): ?>
            <div class="swiper-pagination"></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>