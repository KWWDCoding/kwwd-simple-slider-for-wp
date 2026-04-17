<?php
defined('ABSPATH') || exit;

// ── Admin Menu ────────────────────────────────────────────────────────────────

add_action('admin_menu', 'KWWD_Slider_admin_menu');

function KWWD_Slider_admin_menu() {
    add_menu_page(
        'Simple Sliders',
        'Simple Sliders',
        'manage_options',
        'kwwd-slider',
        'KWWD_Slider_page',
        'dashicons-images-alt2',
        30
    );
    // Keep the top-level item visible as "All Sliders" in the submenu
    add_submenu_page(
        'kwwd-slider',
        'All Sliders',
        'All Sliders',
        'manage_options',
        'kwwd-slider',
        'KWWD_Slider_page'
    );
}

// ── Admin Page Router ─────────────────────────────────────────────────────────

function KWWD_Slider_page() {
    $action = $_GET['action'] ?? 'list';
    $id     = (int) ($_GET['slider_id'] ?? 0);

    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('KWWD_Slider_action')) {
        $action_post = $_POST['KWWDSlider_action'] ?? '';

        if ($action_post === 'save_slider') {
            $slider_id = (int) ($_POST['slider_id'] ?? 0);
            $new_id = KWWDSlider_save_slider($_POST, $slider_id);
            wp_redirect(admin_url('admin.php?page=kwwd-slider&action=edit&slider_id=' . $new_id . '&saved=1'));
            exit;
        }

        if ($action_post === 'save_slide') {
            $slide_id  = (int) ($_POST['slide_id']  ?? 0);
            $slider_id = (int) ($_POST['slider_id'] ?? 0);

            // Handle image upload if provided
            if (!empty($_FILES['slide_image']['name'])) {
                $upload = KWWDSlider_handle_image_upload($_FILES['slide_image'], sanitize_text_field($_POST['title'] ?? 'slide'));
                if (isset($upload['error'])) {
                    wp_redirect(admin_url('admin.php?page=kwwd-slider&action=slide&slider_id=' . $slider_id . '&slide_id=' . $slide_id . '&error=' . urlencode($upload['error'])));
                    exit;
                }
                $_POST['attachment_id'] = $upload['attachment_id'];
                $_POST['image_url']     = $upload['url'];
            } elseif ($slide_id) {
                // Keep existing image
                $existing = KWWDSlider_get_slide($slide_id);
                $_POST['attachment_id'] = $existing->attachment_id ?? 0;
                $_POST['image_url']     = $existing->image_url ?? '';
            }

            KWWDSlider_save_slide($_POST, $slide_id);
            wp_redirect(admin_url('admin.php?page=kwwd-slider&action=edit&slider_id=' . $slider_id . '&saved=1'));
            exit;
        }

        if ($action_post === 'bulk_upload_csv') {
            $slider_id = (int) ($_POST['slider_id'] ?? 0);
            $result = KWWDSlider_process_csv_upload($slider_id, $_FILES['csv_file'] ?? null);
            
            if ($result['success']) {
                wp_redirect(admin_url('admin.php?page=kwwd-slider&action=edit&slider_id=' . $slider_id . '&csv_uploaded=' . $result['count'] . '&csv_errors=' . count($result['errors'])));
            } else {
                wp_redirect(admin_url('admin.php?page=kwwd-slider&action=edit&slider_id=' . $slider_id . '&csv_error=' . urlencode($result['message'])));
            }
            exit;
        }

        if ($action_post === 'delete_slider') {
            KWWDSlider_delete_slider((int) ($_POST['slider_id'] ?? 0));
            wp_redirect(admin_url('admin.php?page=kwwd-slider&deleted=1'));
            exit;
        }

        if ($action_post === 'delete_slide') {
            $slider_id = (int) ($_POST['slider_id'] ?? 0);
            KWWDSlider_delete_slide((int) ($_POST['slide_id'] ?? 0));
            wp_redirect(admin_url('admin.php?page=kwwd-slider&action=edit&slider_id=' . $slider_id . '&deleted=1'));
            exit;
        }
    }

    // Render views
    match ($action) {
        'edit'   => KWWDSlider_view_edit_slider($id),
        'new'    => KWWDSlider_view_edit_slider(0),
        'slide'  => KWWDSlider_view_edit_slide($id, (int) ($_GET['slide_id'] ?? 0)),
        default  => KWWDSlider_view_list(),
    };
}

// ── CSV Bulk Upload Processing ───────────────────────────────────────────────

function KWWDSlider_process_csv_upload($slider_id, $file) {

    // grab our global settings
    $g = KWWDSlider_get_global_settings();
    $GenerateShortLinks = (int)$g['generate-shortlinks'];
    $ShortlinkPrefix = $g['shortlink-prefix'] ?? '';

    $UsingShortlink = (int)KWWDSlider_has_shortlink_active(); // If the URL Shortify Plugin is active

    $result = [
        'success' => false,
        'count' => 0,
        'errors' => [],
        'message' => ''
    ];

    // Validate file upload
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'File upload failed.';
        return $result;
    }

    // Validate file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'csv') {
        $result['message'] = 'Please upload a CSV file.';
        return $result;
    }

    // Get current max slide order for this slider
    global $wpdb;
    $max_order = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(slide_order) FROM {$wpdb->prefix}KWWD_Slider_slides WHERE slider_id = %d",
        $slider_id
    ));
    $next_order = $max_order + 1;

    // Parse CSV
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        $result['message'] = 'Could not open CSV file.';
        return $result;
    }

    // Read header row
    $header = fgetcsv($handle);
    if (!$header) {
        $result['message'] = 'CSV file is empty.';
        fclose($handle);
        return $result;
    }

    // Normalize headers (case-insensitive, trim whitespace)
    $header = array_map(function($h) {
        return strtolower(trim($h));
    }, $header);

    // Expected columns
    $expected_cols = ['slide title', 'image', 'destination url', 'short url', 'slide order'];
    
    $row_number = 1; // Start at 1 since we read the header
    
    while (($row = fgetcsv($handle)) !== false) {
        $row_number++;
        
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }

        // Map CSV columns to associative array
        $data = [];
        foreach ($header as $index => $col_name) {
            $data[$col_name] = $row[$index] ?? '';
        }

        // Extract and validate fields
        $slide_title = trim($data['slide title'] ?? '');
        $image_url = trim($data['image'] ?? '');
        $dest_url = trim($data['destination url'] ?? '');
        $short_url = trim($data['short url'] ?? '');
        $slide_order = trim($data['slide order'] ?? '');

        // Validate required fields
        if (empty($slide_title)) {
            $result['errors'][] = "Row {$row_number}: Missing slide title.";
            continue;
        }

        if (empty($image_url)) {
            $result['errors'][] = "Row {$row_number}: Missing image URL.";
            continue;
        }

        // Validate image URL
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            $result['errors'][] = "Row {$row_number}: Invalid image URL.";
            continue;
        }

        // Validate destination URL or short URL
        if (empty($dest_url) && empty($short_url)) {
            $result['errors'][] = "Row {$row_number}: Must provide either Destination URL or Short URL.";
            continue;
        }

        // Validate destination URL if provided
        if (!empty($dest_url) && !filter_var($dest_url, FILTER_VALIDATE_URL)) {
            $result['errors'][] = "Row {$row_number}: Invalid destination URL.";
            continue;
        }

        // Determine slide order
        if (!empty($slide_order) && is_numeric($slide_order)) {
            $order = (int) $slide_order;
        } else {
            $order = $next_order++;
        }

        // Handle Short URL creation if needed

        if (empty($short_url) && !empty($dest_url)) {
            
            // 2. Only attempt generation if the settings allow it
            if ($UsingShortlink && $GenerateShortLinks === 1) {

                // This is where we call your updated function
                $short_url = KWWDSlider_create_short_link($slide_title, $dest_url, $ShortlinkPrefix);
                
                // 3. ONLY fail the row if we specifically TRIED to generate a link and it failed
                if (empty($short_url)) {
                    $result['errors'][] = "Row {$row_number}: Short link generation failed.";
                    continue; 
                }
            } 
            // If $GenerateShortLinks is 0, we skip the block above. 
            // $short_url remains an empty string, and we proceed to the save step below.
        }

        // Prepare slide data
        $slide_data = [
            'slider_id' => $slider_id,
            'title' => $slide_title,
            'caption' => $slide_title, // Using title as caption by default
            'image_url' => $image_url,
            'dest_url' => $dest_url,
            'short_url' => $short_url,
            'slide_order' => $order,
            'active' => 1,
            'attachment_id' => 0 // No attachment since we're using existing URLs
        ];

        // Save the slide
        try {
            KWWDSlider_save_slide($slide_data, 0);
            $result['count']++;
        } catch (Exception $e) {
            $result['errors'][] = "Row {$row_number}: " . $e->getMessage();
        }
    }

    fclose($handle);

    $result['success'] = $result['count'] > 0;
    if (!$result['success'] && empty($result['errors'])) {
        $result['message'] = 'No valid slides found in CSV.';
    }

    return $result;
}

function KWWDSlider_create_short_url($dest_url, $title = '') {
    // Check if URL Shortify plugin is active
    if (!function_exists('WORDPRESS_PLUGIN_URL_SHORTIFY')) {
        // Fallback: create a simple slug from title
        $slug = sanitize_title($title);
        if (empty($slug)) {
            $slug = 'slide-' . substr(md5($dest_url), 0, 6);
        }
        
        // This is a placeholder that returns a formatted slug
        return $slug;
    }
    
    
    // Return a simple slug for the shortlink
    $slug = sanitize_title($title);
    if (empty($slug)) {
        $slug = 'slide-' . substr(md5($dest_url), 0, 6);
    }
    
    return $slug;
}

// ── AJAX: Reorder ─────────────────────────────────────────────────────────────

add_action('wp_ajax_KWWDSlider_reorder_slides', 'KWWDSlider_ajax_reorder_slides');

function KWWDSlider_ajax_reorder_slides() {
    check_ajax_referer('KWWD_Slider_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    $order = $_POST['order'] ?? [];
    KWWDSlider_reorder_slides(array_map('intval', $order));
    wp_send_json_success();
}

// ── AJAX: Toggle Active ───────────────────────────────────────────────────────

add_action('wp_ajax_KWWDSlider_toggle_slide', 'KWWDSlider_ajax_toggle_slide');

function KWWDSlider_ajax_toggle_slide() {
    check_ajax_referer('KWWD_Slider_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    KWWDSlider_toggle_slide((int) $_POST['slide_id'], (int) $_POST['active']);
    wp_send_json_success();
}

// ── Views ─────────────────────────────────────────────────────────────────────

function KWWDSlider_view_list() {
    global $wpdb;
    $per_page    = 20;
    $current_page = max(1, (int) ($_GET['paged'] ?? 1));
    $offset      = ($current_page - 1) * $per_page;
    $search      = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));

    // ── Slider list with pagination ───────────────────────────────────────────
    $TotalSQL = "SELECT COUNT(*) FROM {$wpdb->prefix}KWWD_Slider_sliders";
    echo $TotalSQL;
    $total_sliders = (int) $wpdb->get_var($TotalSQL);
    $total_pages   = max(1, (int) ceil($total_sliders / $per_page));
    $sliders       = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}KWWD_Slider_sliders ORDER BY id DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    // ── Slide search ──────────────────────────────────────────────────────────
    $search_results = [];
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $search_results = $wpdb->get_results($wpdb->prepare(
            "SELECT sl.*, s.name AS slider_name, s.id AS slider_id
             FROM {$wpdb->prefix}KWWD_Slider_slides sl
             JOIN {$wpdb->prefix}KWWD_Slider_sliders s ON s.id = sl.slider_id
             WHERE sl.title LIKE %s
                OR sl.caption LIKE %s
                OR sl.dest_url LIKE %s
             ORDER BY s.name ASC, sl.slide_order ASC",
            $like, $like, $like
        ));
    }

    $base_url = admin_url('admin.php?page=kwwd-slider');
    ?>
    <div class="wrap kwwd-admin">
        <h1 class="wp-heading-inline">WKRN Sliders</h1>
        <a href="<?= admin_url('admin.php?page=kwwd-slider&action=new') ?>" class="page-title-action">Add New</a>

        <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible"><p>Deleted successfully.</p></div>
        <?php endif; ?>

        <!-- Search form -->
        <form method="get" style="margin: 1rem 0;">
            <input type="hidden" name="page" value="kwwd-slider">
            <p class="search-box">
                <label for="slide-search" style="font-weight:600">Search Slides:</label><br>
                <input type="search" id="slide-search" name="s"
                       value="<?= esc_attr($search) ?>"
                       placeholder="Title, caption or URL…"
                       style="width:300px;margin-right:.5rem">
                <button type="submit" class="button">Search</button>
                <?php if ($search): ?>
                <a href="<?= esc_url($base_url) ?>" class="button" style="margin-left:.25rem">Clear</a>
                <?php endif; ?>
            </p>
        </form>

        <!-- Search results -->
        <?php if ($search !== ''): ?>
        <div style="margin-bottom:1.5rem">
            <h2 style="font-size:1rem;margin-bottom:.5rem">
                Search results for <em>"<?= esc_html($search) ?>"</em>
                — <?= count($search_results) ?> slide<?= count($search_results) !== 1 ? 's' : '' ?> found
            </h2>
            <?php if (empty($search_results)): ?>
            <p>No slides matched your search.</p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:56px">Image</th>
                        <th>Slide Title</th>
                        <th>Caption</th>
                        <th>Slider</th>
                        <th style="width:80px">Active</th>
                        <th style="width:80px">Edit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($search_results as $slide): ?>
                    <tr>
                        <td>
                            <?php if ($slide->image_url): ?>
                            <img src="<?= esc_url($slide->image_url) ?>" style="height:40px;width:auto">
                            <?php else: ?>
                            <span style="color:#aaa">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= esc_html($slide->title) ?></td>
                        <td><?= esc_html($slide->caption) ?></td>
                        <td>
                            <a href="<?= esc_url(admin_url('admin.php?page=kwwd-slider&action=edit&slider_id=' . $slide->slider_id)) ?>">
                                <?= esc_html($slide->slider_name) ?>
                            </a>
                        </td>
                        <td><?= $slide->active ? '✅' : '❌' ?></td>
                        <td>
                            <a href="<?= esc_url(admin_url('admin.php?page=kwwd-slider&action=slide&slider_id=' . $slide->slider_id . '&slide_id=' . $slide->id)) ?>" class="button button-small">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Slider list -->
        <?php if (empty($sliders)): ?>
        <p>No sliders yet. <a href="<?= admin_url('admin.php?page=kwwd-slider&action=new') ?>">Create your first slider</a>.</p>
        <?php else: ?>

        <?php if ($total_pages > 1): ?>
        <div class="tablenav top">
            <div class="tablenav-pages">
                <span class="displaying-num"><?= $total_sliders ?> sliders</span>
                <span class="pagination-links">
                    <?php if ($current_page > 1): ?>
                    <a class="first-page button" href="<?= esc_url(add_query_arg('paged', 1, $base_url)) ?>">«</a>
                    <a class="prev-page button" href="<?= esc_url(add_query_arg('paged', $current_page - 1, $base_url)) ?>">‹</a>
                    <?php else: ?>
                    <span class="first-page button disabled">«</span>
                    <span class="prev-page button disabled">‹</span>
                    <?php endif; ?>

                    <span class="paging-input">
                        <?= $current_page ?> of <span class="total-pages"><?= $total_pages ?></span>
                    </span>

                    <?php if ($current_page < $total_pages): ?>
                    <a class="next-page button" href="<?= esc_url(add_query_arg('paged', $current_page + 1, $base_url)) ?>">›</a>
                    <a class="last-page button" href="<?= esc_url(add_query_arg('paged', $total_pages, $base_url)) ?>">»</a>
                    <?php else: ?>
                    <span class="next-page button disabled">›</span>
                    <span class="last-page button disabled">»</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th style="width:60px">Slides</th>
                    <th style="width:60px">Visible</th>
                    <th style="width:110px">Settings</th>
                    <th>Shortcode</th>
                    <th style="width:120px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sliders as $slider):
                    $count = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}KWWD_Slider_slides WHERE slider_id = %d", $slider->id
                    ));
                ?>
                <tr>
                    <td><strong><?= esc_html($slider->name) ?></strong></td>
                    <td><?= $count ?></td>
                    <td><?= (int) $slider->slides_visible ?></td>
                    <td>
                        <?php if (!empty($slider->use_global)): ?>
                        <span style="color:#2271b1;font-weight:600" title="Using Global Settings">🌐 Global</span>
                        <?php else: ?>
                        <span style="color:#888" title="Using per-slider settings">⚙️ Custom</span>
                        <?php endif; ?>
                    </td>
                    <td><code>[simple_slider id="<?= (int) $slider->id ?>"]</code></td>
                    <td>
                        <a href="<?= admin_url('admin.php?page=kwwd-slider&action=edit&slider_id=' . $slider->id) ?>">Edit</a>
                        &nbsp;|&nbsp;
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this slider and all its slides?')">
                            <?php wp_nonce_field('KWWD_Slider_action') ?>
                            <input type="hidden" name="KWWDSlider_action" value="delete_slider">
                            <input type="hidden" name="slider_id" value="<?= (int) $slider->id ?>">
                            <button type="submit" class="button-link kwwd-delete-btn">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?= $total_sliders ?> sliders</span>
                <span class="pagination-links">
                    <?php if ($current_page > 1): ?>
                    <a class="first-page button" href="<?= esc_url(add_query_arg('paged', 1, $base_url)) ?>">«</a>
                    <a class="prev-page button" href="<?= esc_url(add_query_arg('paged', $current_page - 1, $base_url)) ?>">‹</a>
                    <?php else: ?>
                    <span class="first-page button disabled">«</span>
                    <span class="prev-page button disabled">‹</span>
                    <?php endif; ?>
                    <span class="paging-input">
                        <?= $current_page ?> of <span class="total-pages"><?= $total_pages ?></span>
                    </span>
                    <?php if ($current_page < $total_pages): ?>
                    <a class="next-page button" href="<?= esc_url(add_query_arg('paged', $current_page + 1, $base_url)) ?>">›</a>
                    <a class="last-page button" href="<?= esc_url(add_query_arg('paged', $total_pages, $base_url)) ?>">»</a>
                    <?php else: ?>
                    <span class="next-page button disabled">›</span>
                    <span class="last-page button disabled">»</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php
}

function KWWDSlider_view_edit_slider(int $id) {
    $slider = $id ? KWWDSlider_get_slider($id) : null;
    $slides = $id ? KWWDSlider_get_slides($id) : [];

    // Default use_global to ON for new sliders
    $use_global = ($slider === null) ? 1 : (int) $slider->use_global;
    ?>
    <div class="wrap kwwd-admin">
        <h1><?= $id ? 'Edit Slider' : 'New Slider' ?></h1>
        <?php if (isset($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>Saved successfully.</p></div><?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?><div class="notice notice-success is-dismissible"><p>Slide deleted.</p></div><?php endif; ?>
        <?php if (isset($_GET['csv_uploaded'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>CSV Import Complete!</strong> Successfully imported <?= (int)$_GET['csv_uploaded'] ?> slide(s).
            <?php if (isset($_GET['csv_errors']) && (int)$_GET['csv_errors'] > 0): ?>
                <br><em><?= (int)$_GET['csv_errors'] ?> row(s) had errors and were skipped.</em>
            <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['csv_error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?= esc_html(urldecode($_GET['csv_error'])) ?></p></div>
        <?php endif; ?>

        <div class="kwwd-two-col">

            <!-- LEFT: Slider settings -->
            <div class="kwwd-col-settings">
                <div class="postbox">
                    <div class="postbox-header"><h2>Slider Settings</h2></div>
                    <div class="inside">
                        <form method="post">
                            <?php wp_nonce_field('KWWD_Slider_action') ?>
                            <input type="hidden" name="KWWDSlider_action" value="save_slider">
                            <input type="hidden" name="slider_id" value="<?= $id ?>">

                            <table class="form-table" style="margin-top:0">
                                <tr>
                                    <th><label for="name">Slider Name</label></th>
                                    <td><input type="text" id="name" name="name" class="regular-text" value="<?= esc_attr($slider->name ?? '') ?>" required></td>
                                </tr>
                                <!-- ── Use Global Settings toggle ─────────── -->
                                <tr>
                                    <th><label for="use_global">Use Global Settings</label></th>
                                    <td>
                                        <label style="display:inline-flex;align-items:center;gap:.5rem;cursor:pointer">
                                            <input type="checkbox" id="use_global" name="use_global" value="1"
                                                   <?= $use_global ? 'checked' : '' ?>>
                                            <span>
                                                Inherit design settings from
                                                <a href="<?= admin_url('admin.php?page=kwwd-slider-global') ?>" target="_blank">Global Settings</a>
                                            </span>
                                        </label>
                                        <p class="description" style="margin-top:.3rem">
                                            When checked, the sections below are ignored and the global values are used instead.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- ══ Per-slider design sections (hidden when Use Global is on) ══ -->
                            <div id="kwwd-per-slider-settings">

                                <table class="form-table" style="margin-top:0">
                                    <tr>
                                        <th><label for="subtitle">Slider Subtitle</label></th>
                                        <td><input type="text" id="subtitle" name="subtitle" class="regular-text" value="<?= esc_attr($slider->subtitle ?? '') ?>"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="show_title">Show Title</label></th>
                                        <td><input type="checkbox" id="show_title" name="show_title" value="1" <?= !empty($slider->show_title) ? 'checked' : '' ?>> Display name above carousel</td>
                                    </tr>
                                    <tr>
                                        <th><label for="show_subtitle">Show Subtitle</label></th>
                                        <td><input type="checkbox" id="show_subtitle" name="show_subtitle" value="1" <?= !empty($slider->show_subtitle) ? 'checked' : '' ?>> Show subtitle under title</td>
                                    </tr>
                                    <tr>
                                        <th><label for="slides_visible">Slides Visible</label></th>
                                        <td><input type="number" id="slides_visible" name="slides_visible" min="1" max="10" value="<?= (int)($slider->slides_visible ?? 5) ?>"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="autoplay">Autoplay</label></th>
                                        <td><input type="checkbox" id="autoplay" name="autoplay" value="1" <?= ($slider === null || !empty($slider->autoplay)) ? 'checked' : '' ?>> Enable autoplay</td>
                                    </tr>
                                    <tr>
                                        <th><label for="autoplay_delay">Autoplay Delay (ms)</label></th>
                                        <td><input type="number" id="autoplay_delay" name="autoplay_delay" min="1000" step="500" value="<?= (int)($slider->autoplay_delay ?? 3000) ?>"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="loop">Loop</label></th>
                                        <td><input type="checkbox" id="loop" name="loop" value="1" <?= ($slider === null || !empty($slider->loop)) ? 'checked' : '' ?>> Enable loop</td>
                                    </tr>
                                </table>

                                <h3 class="kwwd-section-head">Colours</h3>
                                <table class="form-table" style="margin-top:0">
                                    <tr>
                                        <th><label for="bg_color">Background</label></th>
                                        <td><input type="color" id="bg_color" name="bg_color" value="<?= esc_attr($slider->bg_color ?? '#f9f9f9') ?>"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="arrow_color">Arrows</label></th>
                                        <td><input type="color" id="arrow_color" name="arrow_color" value="<?= esc_attr($slider->arrow_color ?? '#e72153') ?>"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="dot_color">Dot (active)</label></th>
                                        <td><input type="color" id="dot_color" name="dot_color" value="<?= esc_attr($slider->dot_color ?? '#e72153') ?>"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="dot_color_light">Dot (inactive)</label></th>
                                        <td><input type="color" id="dot_color_light" name="dot_color_light" value="<?= esc_attr($slider->dot_color_light ?? '#f76b8f') ?>"></td>
                                    </tr>
                                </table>

                                <h3 class="kwwd-section-head">Slider Border</h3>
                                <table class="form-table" style="margin-top:0">
                                    <tr>
                                        <th><label for="border">Show Border</label></th>
                                        <td><input type="checkbox" id="border" name="border" value="1" <?= !empty($slider->border) ? 'checked' : '' ?>></td>
                                    </tr>
                                    <tr>
                                        <th><label for="border_color">Colour</label></th>
                                        <td><input type="color" id="border_color" name="border_color" value="<?= esc_attr($slider->border_color ?? '#e72153') ?>"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="border_radius">Radius (px)</label></th>
                                        <td><input type="number" id="border_radius" name="border_radius" min="0" max="50" value="<?= (int)($slider->border_radius ?? 5) ?>"></td>
                                    </tr>
                                </table>

                                <h3 class="kwwd-section-head">Slide Border</h3>
                                <table class="form-table" style="margin-top:0">
                                    <tr>
                                        <th><label for="slide_border">Show Border</label></th>
                                        <td><input type="checkbox" id="slide_border" name="slide_border" value="1" <?= !empty($slider->slide_border) ? 'checked' : '' ?>></td>
                                    </tr>
                                    <tr>
                                        <th><label for="slide_border_color">Colour</label></th>
                                        <td><input type="color" id="slide_border_color" name="slide_border_color" value="<?= esc_attr($slider->slide_border_color ?? '#e72153') ?>"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="slide_border_radius">Radius (px)</label></th>
                                        <td><input type="number" id="slide_border_radius" name="slide_border_radius" min="0" max="50" value="<?= (int)($slider->slide_border_radius ?? 4) ?>"></td>
                                    </tr>
                                </table>

                                <h3 class="kwwd-section-head">Carousel Behaviour</h3>
                                <table class="form-table" style="margin-top:0">
                                    <tr>
                                        <th><label for="effect">Transition Effect</label></th>
                                        <td>
                                            <select id="effect" name="effect">
                                                <?php foreach (['slide'=>'Slide','fade'=>'Fade','coverflow'=>'Coverflow','flip'=>'Flip'] as $val => $label): ?>
                                                <option value="<?= $val ?>" <?= ($slider->effect ?? 'slide') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">Coverflow looks great for book/DVD covers.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="transition_speed">Transition Speed (ms)</label></th>
                                        <td>
                                            <input type="number" id="transition_speed" name="transition_speed" min="100" max="5000" step="50" value="<?= (int)($slider->transition_speed ?? 300) ?>">
                                            <p class="description">How fast the slide animation is (not the pause between slides).</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="space_between">Space Between Slides (px)</label></th>
                                        <td><input type="number" id="space_between" name="space_between" min="0" max="100" value="<?= (int)($slider->space_between ?? 10) ?>"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="centered_slides">Centred Slides</label></th>
                                        <td>
                                            <input type="checkbox" id="centered_slides" name="centered_slides" value="1" <?= !empty($slider->centered_slides) ? 'checked' : '' ?>>
                                            Keep the active slide centred in the viewport
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="touch_swipe">Touch / Swipe</label></th>
                                        <td>
                                            <input type="checkbox" id="touch_swipe" name="touch_swipe" value="1" <?= ($slider === null || !empty($slider->touch_swipe)) ? 'checked' : '' ?>>
                                            Allow swiping on touch devices
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="grab_cursor">Grab Cursor</label></th>
                                        <td>
                                            <input type="checkbox" id="grab_cursor" name="grab_cursor" value="1" <?= ($slider === null || !empty($slider->grab_cursor)) ? 'checked' : '' ?>>
                                            Show grab cursor and allow mouse drag on desktop
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="pause_on_hover">Pause on Hover</label></th>
                                        <td>
                                            <input type="checkbox" id="pause_on_hover" name="pause_on_hover" value="1" <?= ($slider === null || !empty($slider->pause_on_hover)) ? 'checked' : '' ?>>
                                            Pause autoplay when the mouse is over the carousel
                                        </td>
                                    </tr>
                                </table>

                                <h3 class="kwwd-section-head">Navigation</h3>
                                <table class="form-table" style="margin-top:0">
                                    <tr>
                                        <th><label for="show_arrows">Show Arrows</label></th>
                                        <td><input type="checkbox" id="show_arrows" name="show_arrows" value="1" <?= ($slider === null || !empty($slider->show_arrows)) ? 'checked' : '' ?>></td>
                                    </tr>
                                    <tr>
                                        <th><label for="show_dots">Show Dots</label></th>
                                        <td><input type="checkbox" id="show_dots" name="show_dots" value="1" <?= ($slider === null || !empty($slider->show_dots)) ? 'checked' : '' ?>></td>
                                    </tr>
                                </table>

                            </div><!-- /#kwwd-per-slider-settings -->

                            <?php submit_button($id ? 'Update Slider' : 'Create Slider') ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Slides list -->
            <div class="kwwd-col-slides">
                
                <div class="postbox">
                    <div class="postbox-header" style="display:flex;align-items:center;justify-content:space-between;padding:.5rem 1rem">
                        <h2 style="margin:0">Slides</h2>
                        <?php if ($id): ?>
                        <div style="display:flex;gap:.5rem">
                            <a href="<?= admin_url('admin.php?page=kwwd-slider&action=slide&slider_id=' . $id) ?>" class="button button-primary">+ Add Slide</a>
                            <button type="button" class="button" id="kwwd-bulk-upload-btn">📁 Bulk Upload CSV</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="inside">
                        <?php if (!$id): ?>
                        <p class="description">Save the slider first, then add slides.</p>
                        <?php elseif (empty($slides)): ?>
                        <p>No slides yet. <a href="<?= admin_url('admin.php?page=kwwd-slider&action=slide&slider_id=' . $id) ?>">Add the first slide</a> or use the Bulk Upload CSV button above.</p>
                        <?php else: ?>
                        <?php
                        // Shortcode bar — rendered as a reusable PHP variable so we
                        // can drop the same markup at the top and bottom of the list.
                        $shortcode_text = '[simple_slider id="' . $id . '"]';
                        $shortcode_bar  = '<p class="kwwd-shortcode-bar" style="margin:.5rem 0;display:flex;align-items:center;gap:.6rem">'
                            . '<strong>Shortcode:</strong>'
                            . '<code class="kwwd-shortcode-text" style="flex:1;padding:.3rem .5rem;background:#f0f0f0;border:1px solid #ddd;border-radius:3px;cursor:pointer"'
                            . ' title="Click to copy">'
                            . esc_html($shortcode_text)
                            . '</code>'
                            . '<button type="button" class="button kwwd-copy-shortcode" data-shortcode="' . esc_attr($shortcode_text) . '">Copy</button>'
                            . '<span class="kwwd-copy-notice" style="color:#2271b1;display:none">✔ Copied!</span>'
                            . '</p>';

                            // grab our global settings
    $g = KWWDSlider_get_global_settings();
    $GenerateShortLinks = (int)$g['generate-shortlinks'];
    $UsingShortlink = (int)KWWDSlider_has_shortlink_active(); // If the URL Shortify Plugin is active
?>
                        <?php echo $shortcode_bar; ?>

                        <p class="description" style="margin-top:0">Drag rows to reorder. Toggle to show/hide.</p>
                        <table class="wp-list-table widefat fixed striped" id="kwwd-slides-table">
                            <thead>
                                <tr>
                                    <th style="width:24px"></th>
                                    <th style="width:56px">Image</th>
                                    <th>Title</th>
                                    <th style="width:70px">Active</th>
                                    <th style="width:100px">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="kwwd-sortable">
                                <?php foreach ($slides as $slide):
                                $SlideTitle = $slide->title;
                                // Are we using short links or just main links?
                               if(($UsingShortlink===1) && ($GenerateShortLinks===1))
                                {
                                    
                                    if(empty($slide->short_url))
                                    {
                                        // Blank short so default to full URL
                                        $SlideURL = $slide->dest_url;
                                    }
                                    else
                                    {
                                        $SlideURL = site_url().'/'.$slide->short_url;
                                    }    
                                }
                                else
                                {
                                    // ELSE Use Normal URL
                                    $SlideURL = $slide->dest_url;
                                }
                                $SlideURLLink = '';
                                if(!empty($SlideURL))
                                {
                                $SlideURLLink = '<a href="'.$SlideURL.'" target="_blank" title="'.$SlideURL.'">🔗 Slide URL</a>';
                                }
                                    ?>
                                <tr data-id="<?= (int)$slide->id ?>">
                                    <td class="kwwd-handle">☰</td>
                                    <td>
                                        <?php if ($slide->image_url): ?>
                                        <img src="<?= esc_url($slide->image_url) ?>" style="height:50px;width:auto">
                                        <?php else: ?>
                                        <span class="kwwd-no-image">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= esc_html($SlideTitle) ?><br>
                                        <?php
                                        if(!empty($SlideURLLink))
                                        {
                                            echo $SlideURLLink;
                                        } 
                                        ?>
                                    </td>
                                    <td>
                                        <label class="kwwd-toggle">
                                            <input type="checkbox" class="kwwd-active-toggle" data-id="<?= (int)$slide->id ?>" <?= $slide->active ? 'checked' : '' ?>>
                                            <span class="kwwd-toggle-slider"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <a href="<?= admin_url('admin.php?page=kwwd-slider&action=slide&slider_id=' . $id . '&slide_id=' . $slide->id) ?>">Edit</a>
                                        &nbsp;|&nbsp;
                                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this slide?')">
                                            <?php wp_nonce_field('KWWD_Slider_action') ?>
                                            <input type="hidden" name="KWWDSlider_action" value="delete_slide">
                                            <input type="hidden" name="slider_id" value="<?= $id ?>">
                                            <input type="hidden" name="slide_id" value="<?= (int)$slide->id ?>">
                                            <button type="submit" class="button-link kwwd-delete-btn">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?= $shortcode_bar ?>

                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div><!-- .kwwd-two-col -->

        <p><a href="<?= admin_url('admin.php?page=kwwd-slider') ?>">&larr; Back to Sliders</a></p>
    </div>

    <!-- CSV Bulk Upload Modal -->
    <?php if ($id): ?>
    <div id="kwwd-csv-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:100000;align-items:center;justify-content:center">
        <div style="background:#fff;padding:2rem;border-radius:8px;max-width:600px;width:90%">
            <h2 style="margin-top:0">Bulk Upload Slides from CSV</h2>
            <p>Upload a CSV file with the following columns:</p>
            <ul style="line-height:1.8">
                <li><strong>Slide Title</strong> (required)</li>
                <li><strong>Image</strong> (required - full URL to existing image)</li>
                <li><strong>Destination URL</strong> (affiliate link - optional if Short URL provided)</li>
                <li><strong>Short URL</strong> (optional - will be created automatically if blank)</li>
                <li><strong>Slide Order</strong> (optional - numeric position)</li>
            </ul>
            <p class="description">Download a <a href="<?= plugins_url('csv-template.csv', __FILE__) ?>" download>sample CSV template</a> to get started.</p>
            
            <form method="post" enctype="multipart/form-data" id="kwwd-csv-form">
                <?php wp_nonce_field('KWWD_Slider_action') ?>
                <input type="hidden" name="KWWDSlider_action" value="bulk_upload_csv">
                <input type="hidden" name="slider_id" value="<?= $id ?>">
                
                <p>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required style="width:100%">
                </p>
                
                <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem">
                    <button type="button" class="button" id="kwwd-csv-cancel">Cancel</button>
                    <button type="submit" class="button button-primary">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    (function () {
        // ── Use Global Settings: hide/show per-slider design fields ───────────
        var checkbox = document.getElementById('use_global');
        var panel    = document.getElementById('kwwd-per-slider-settings');

        if (checkbox && panel) {
            function toggle() {
                panel.style.display = checkbox.checked ? 'none' : '';
            }
            toggle();
            checkbox.addEventListener('change', toggle);
        }

        // ── Shortcode: copy to clipboard on button or code click ──────────────
        document.addEventListener('click', function (e) {
            // Match either the Copy button or the <code> element itself
            var btn = e.target.closest('.kwwd-copy-shortcode, .kwwd-shortcode-text');
            if (!btn) return;

            var bar       = btn.closest('.kwwd-shortcode-bar');
            var shortcode = bar.querySelector('.kwwd-copy-shortcode').dataset.shortcode;
            var notice    = bar.querySelector('.kwwd-copy-notice');

            navigator.clipboard.writeText(shortcode).then(function () {
                notice.style.display = 'inline';
                setTimeout(function () { notice.style.display = 'none'; }, 2000);
            });
        });

        // ── CSV Bulk Upload Modal ──────────────────────────────────────────────
        var bulkBtn = document.getElementById('kwwd-bulk-upload-btn');
        var modal = document.getElementById('kwwd-csv-modal');
        var cancelBtn = document.getElementById('kwwd-csv-cancel');

        if (bulkBtn && modal) {
            bulkBtn.addEventListener('click', function() {
                modal.style.display = 'flex';
            });
        }

        if (cancelBtn && modal) {
            cancelBtn.addEventListener('click', function() {
                modal.style.display = 'none';
                document.getElementById('csv_file').value = '';
            });
        }

        // Close modal on outside click
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                    document.getElementById('csv_file').value = '';
                }
            });
        }
    })();
    </script>
    <?php
}


function KWWDSlider_view_edit_slide(int $slider_id, int $slide_id) {
    $slide  = $slide_id ? KWWDSlider_get_slide($slide_id) : null;
    $slider = KWWDSlider_get_slider($slider_id);
    if (!$slider) {
        echo '<div class="wrap"><p>Slider not found.</p></div>';
        return;
    }

    // grab our global settings
    $g = KWWDSlider_get_global_settings();
    $ShortlinkPrefix = $g['shortlink-prefix'];
    $GenerateShortLinks = (int)$g['generate-shortlinks'];
    $UsingShortlink = (int)KWWDSlider_has_shortlink_active();
    ?>
    <div class="wrap kwwd-admin">
        <h1><?= $slide_id ? 'Edit Slide' : 'Add Slide' ?> — <?= esc_html($slider->name) ?></h1>
        <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?= esc_html(urldecode($_GET['error'])) ?></p></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('KWWD_Slider_action') ?>
            <input type="hidden" name="KWWDSlider_action" value="save_slide">
            <input type="hidden" name="slider_id" value="<?= $slider_id ?>">
            <input type="hidden" name="slide_id"  value="<?= $slide_id ?>">
            <input type="hidden" name="slide_order" value="<?= (int) ($slide->slide_order ?? 999) ?>">
            <table class="form-table">
                <tr>
                    <th><label for="title">Slide Title</label></th>
                    <td>
                        <input type="text" id="title" name="title" class="regular-text"
                               value="<?= esc_attr($slide->title ?? '') ?>" required>
                        <button type="button" class="button" id="copy-title-to-caption" style="margin-left:.4rem">
                            Copy to Caption →
                        </button>
                        <p class="description">Used as alt text and image filename.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="slide_image">Image</label></th>
                    <td>
                        <?php if ($slide && $slide->image_url): ?>
                        <div style="margin-bottom:.5rem">
                            <img src="<?= esc_url($slide->image_url) ?>" style="max-width:<?= KWWDSlider_IMG_MAX_WIDTH ?>px;height:auto;display:block;margin-bottom:.25rem">
                            <small>Current image. Upload a new one to replace it.</small>
                        </div>
                        <?php endif; ?>
                        <input type="file" id="slide_image" name="slide_image" accept="image/jpeg,image/png,image/webp,image/gif">
                        <p class="description">Will be resized to max <?= KWWDSlider_IMG_MAX_WIDTH ?>px wide, maintaining aspect ratio.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="caption">Caption</label></th>
                    <td>
                        <input type="text" id="caption" name="caption" class="regular-text"
                               value="<?= esc_attr($slide->caption ?? '') ?>">
                        <p class="description">Optional. Displayed below the image.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="dest_url">Destination URL</label></th>
                    <td>
                        <input type="url" id="dest_url" name="dest_url" class="regular-text"
                               value="<?= esc_attr($slide->dest_url ?? '') ?>"
                               placeholder="https://amzn.to/xxxxxx">
                        <p class="description">The affiliate link. A short URL will be created automatically on save.</p>
                    </td>
                </tr>
                <?php
                // Show Shortlinks row if using
                if(($UsingShortlink===1) && ($GenerateShortLinks===1))
                {
                ?>
                <tr>
                    <th>Short URL</th>
                    <td>
                        <?php if ($slide && $slide->short_url):
                            $su = preg_replace('#^https?://[^/]*/(?:go/)?#i', '', $slide->short_url);
                            $su = trim($su, '/');
                            $full_short = $su ? home_url('/' . $su) : '';
                        ?>
                        <a href="<?= esc_url($full_short) ?>" target="_blank">
                            <?= esc_html($full_short) ?>
                        </a>
                        <?php else: ?>
                        <em style="color:#999">Will be generated on save format: <?php echo get_site_url().'/'.$ShortlinkPrefix;?>[SHORT_URL]</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
                }
                ?>
                <tr>
                    <th><label for="active">Active</label></th>
                    <td>
                        <input type="checkbox" id="active" name="active" value="1"
                               <?= !$slide || $slide->active ? 'checked' : '' ?>>
                        Show this slide in the carousel.
                    </td>
                </tr>
            </table>

            <?php submit_button($slide_id ? 'Update Slide' : 'Add Slide') ?>
        </form>

        <p><a href="<?= admin_url('admin.php?page=kwwd-slider&action=edit&slider_id=' . $slider_id) ?>">&larr; Back to <?= esc_html($slider->name) ?></a></p>
    </div>
    <?php
}
?>
