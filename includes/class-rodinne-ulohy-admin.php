<?php
/**
 * Admin pages for ekidio
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rodinne_Ulohy_Admin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_post_rodinne_ulohy_delete_feedback', array($this, 'handle_delete_feedback'));
        add_action('admin_post_rodinne_ulohy_save_task_library', array($this, 'handle_save_task_library'));
        add_action('admin_post_rodinne_ulohy_delete_task_library', array($this, 'handle_delete_task_library'));
        add_action('admin_post_rodinne_ulohy_save_reward_library', array($this, 'handle_save_reward_library'));
        add_action('admin_post_rodinne_ulohy_delete_reward_library', array($this, 'handle_delete_reward_library'));
        add_action('admin_post_rodinne_ulohy_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_rodinne_ulohy_devtools_run', array($this, 'handle_devtools_run'));
    }

    public function enqueue_admin_assets($hook_suffix) {
        if (
            'toplevel_page_rodinne-ulohy-feedback' !== $hook_suffix &&
            strpos($hook_suffix, 'rodinne-ulohy-task-library') === false &&
            strpos($hook_suffix, 'rodinne-ulohy-reward-library') === false &&
            strpos($hook_suffix, 'rodinne-ulohy-settings') === false &&
            strpos($hook_suffix, 'rodinne-ulohy-devtools') === false
        ) {
            return;
        }

        wp_enqueue_style(
            'rodinne-ulohy-admin',
            RODINNE_ULOHY_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            defined('RODINNE_ULOHY_VERSION') ? RODINNE_ULOHY_VERSION : null
        );
    }

    public function register_menu() {
        // Single entry: top-level menu opens Feedback page (WordPress auto-adds the first submenu).
        add_menu_page(
            __('ekidio', 'rodinne-ulohy'),
            __('ekidio', 'rodinne-ulohy'),
            'manage_options',
            'rodinne-ulohy-feedback',
            array($this, 'render_feedback_page'),
            'dashicons-groups',
            56
        );

        add_submenu_page(
            'rodinne-ulohy-feedback',
            __('Knižnica úloh', 'rodinne-ulohy'),
            __('Knižnica úloh', 'rodinne-ulohy'),
            'manage_options',
            'rodinne-ulohy-task-library',
            array($this, 'render_task_library_page')
        );

        add_submenu_page(
            'rodinne-ulohy-feedback',
            __('Knižnica odmien', 'rodinne-ulohy'),
            __('Knižnica odmien', 'rodinne-ulohy'),
            'manage_options',
            'rodinne-ulohy-reward-library',
            array($this, 'render_reward_library_page')
        );

        add_submenu_page(
            'rodinne-ulohy-feedback',
            __('Nastavenia', 'rodinne-ulohy'),
            __('Nastavenia', 'rodinne-ulohy'),
            'manage_options',
            'rodinne-ulohy-settings',
            array($this, 'render_settings_page')
        );

        // Developer tools (only in debug / explicitly enabled).
        if (defined('RODINNE_ULOHY_DEV_TOOLS') && RODINNE_ULOHY_DEV_TOOLS) {
            add_submenu_page(
                'rodinne-ulohy-feedback',
                __('Dev Tools', 'rodinne-ulohy'),
                __('Dev Tools', 'rodinne-ulohy'),
                'manage_options',
                'rodinne-ulohy-devtools',
                array($this, 'render_devtools_page')
            );
        }
    }

    public function handle_delete_feedback() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte oprávnenie', 'rodinne-ulohy'));
        }

        check_admin_referer('rodinne_ulohy_delete_feedback');

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id) {
            Rodinne_Ulohy_Database::delete_feedback($id);
        }

        $redirect = admin_url('admin.php?page=rodinne-ulohy-feedback&deleted=1');
        wp_safe_redirect($redirect);
        exit;
    }

    public function render_feedback_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte oprávnenie', 'rodinne-ulohy'));
        }

        $deleted = !empty($_GET['deleted']);
        $entries = Rodinne_Ulohy_Database::get_feedback_entries(300, 0);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Spätná väzba', 'rodinne-ulohy') . '</h1>';

        if ($deleted) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Záznam bol odstránený.', 'rodinne-ulohy') . '</p></div>';
        }

        echo '<p>' . esc_html__('Zoznam prijatej spätnej väzby z aplikácie.', 'rodinne-ulohy') . '</p>';

        echo '<div class="ru-admin-table-wrap">';
        echo '<table class="widefat striped ru-feedback-table">';
        echo '<thead><tr>';
        echo '<th style="width:170px">' . esc_html__('Dátum', 'rodinne-ulohy') . '</th>';
        echo '<th style="width:220px">' . esc_html__('Meno', 'rodinne-ulohy') . '</th>';
        echo '<th style="width:220px">' . esc_html__('Stránka', 'rodinne-ulohy') . '</th>';
        echo '<th>' . esc_html__('Text', 'rodinne-ulohy') . '</th>';
        echo '<th style="width:70px">' . esc_html__('X', 'rodinne-ulohy') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (empty($entries)) {
            echo '<tr><td class="ru-feedback-empty" colspan="5">' . esc_html__('Zatiaľ žiadna spätná väzba.', 'rodinne-ulohy') . '</td></tr>';
        } else {
            $label_date = esc_attr__('Dátum', 'rodinne-ulohy');
            $label_name = esc_attr__('Meno', 'rodinne-ulohy');
            $label_path = esc_attr__('Stránka', 'rodinne-ulohy');
            $label_text = esc_attr__('Text', 'rodinne-ulohy');
            $label_delete = esc_attr__('Odstrániť', 'rodinne-ulohy');

            foreach ($entries as $e) {
                $created = !empty($e->created_at) ? mysql2date('d.m.Y H:i', $e->created_at, true) : '';
                $name = isset($e->name) ? $e->name : '';
                $path = isset($e->path) ? $e->path : '';
                $text = isset($e->text) ? $e->text : '';

                echo '<tr>';
                echo '<td data-label="' . $label_date . '">' . esc_html($created) . '</td>';
                echo '<td data-label="' . $label_name . '">' . esc_html($name) . '</td>';
                echo '<td data-label="' . $label_path . '"><code class="ru-feedback-path">' . esc_html($path) . '</code></td>';
                echo '<td data-label="' . $label_text . '" class="ru-feedback-text" style="white-space:pre-wrap">' . esc_html($text) . '</td>';
                echo '<td data-label="' . $label_delete . '" class="ru-feedback-actions">';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(__('Naozaj odstrániť?', 'rodinne-ulohy')) . '\')">';
                echo '<input type="hidden" name="action" value="rodinne_ulohy_delete_feedback" />';
                echo '<input type="hidden" name="id" value="' . esc_attr(intval($e->id)) . '" />';
                wp_nonce_field('rodinne_ulohy_delete_feedback');
                echo '<button type="submit" class="button button-small" aria-label="' . esc_attr__('Odstrániť', 'rodinne-ulohy') . '">×</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
    }

    private function task_library_page_url($args = array()) {
        return add_query_arg($args, admin_url('admin.php?page=rodinne-ulohy-task-library'));
    }

    private function reward_library_page_url($args = array()) {
        return add_query_arg($args, admin_url('admin.php?page=rodinne-ulohy-reward-library'));
    }

    private function settings_page_url($args = array()) {
        return add_query_arg($args, admin_url('admin.php?page=rodinne-ulohy-settings'));
    }

    private function get_notice_html($saved, $deleted, $error) {
        if ($saved) {
            return '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Záznam bol uložený.', 'rodinne-ulohy') . '</p></div>';
        }
        if ($deleted) {
            return '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Záznam bol odstránený.', 'rodinne-ulohy') . '</p></div>';
        }
        if (!empty($error)) {
            return '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }
        return '';
    }

    private function parse_days_from_request($key) {
        $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : array();
        if (!is_array($raw)) {
            return '';
        }
        $allowed = array('0', '1', '2', '3', '4', '5', '6');
        $days = array();
        foreach ($raw as $val) {
            $val = sanitize_text_field($val);
            if (in_array($val, $allowed, true)) {
                $days[] = $val;
            }
        }
        $days = array_values(array_unique($days));
        return implode(',', $days);
    }

    private function get_task_library_form_defaults() {
        return (object) array(
            'id' => 0,
            'name' => '',
            'description' => '',
            'task_category' => 'povinne',
            'rotation_enabled' => 1,
            'days_of_week' => '1,2,3,4,5',
            'rating' => 0,
            'estimated_time' => '',
            'sort_order' => 0,
        );
    }

    private function get_reward_library_form_defaults() {
        return (object) array(
            'id' => 0,
            'title' => '',
            'category' => '',
            'details' => '',
            'icon' => '🎁',
            'points_cost' => 0,
            'sort_order' => 0,
        );
    }

    public function render_task_library_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte oprávnenie', 'rodinne-ulohy'));
        }

        $saved = !empty($_GET['saved']);
        $deleted = !empty($_GET['deleted']);
        $error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $item = $edit_id ? Rodinne_Ulohy_Database::get_task_library_item($edit_id) : null;
        if (!$item) {
            $item = $this->get_task_library_form_defaults();
        }
        $items = Rodinne_Ulohy_Database::get_task_library_items();
        $selected_days = array_filter(array_map('trim', explode(',', (string) ($item->days_of_week ?? ''))));
        $day_labels = array(
            '1' => __('Po', 'rodinne-ulohy'),
            '2' => __('Ut', 'rodinne-ulohy'),
            '3' => __('St', 'rodinne-ulohy'),
            '4' => __('Št', 'rodinne-ulohy'),
            '5' => __('Pi', 'rodinne-ulohy'),
            '6' => __('So', 'rodinne-ulohy'),
            '0' => __('Ne', 'rodinne-ulohy'),
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Knižnica úloh', 'rodinne-ulohy') . '</h1>';
        echo '<p>' . esc_html__('Tu nastavíš globálnu knižnicu úloh, z ktorej si rodičia v appke pridajú úlohy do svojej rodiny.', 'rodinne-ulohy') . '</p>';
        echo $this->get_notice_html($saved, $deleted, $error);

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="rodinne_ulohy_save_task_library" />';
        echo '<input type="hidden" name="id" value="' . esc_attr(intval($item->id ?? 0)) . '" />';
        wp_nonce_field('rodinne_ulohy_save_task_library');

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="ru_task_library_name">' . esc_html__('Názov úlohy', 'rodinne-ulohy') . '</label></th><td><input name="name" id="ru_task_library_name" type="text" class="regular-text" value="' . esc_attr($item->name ?? '') . '" required /></td></tr>';
        echo '<tr><th scope="row"><label for="ru_task_library_description">' . esc_html__('Popis', 'rodinne-ulohy') . '</label></th><td><textarea name="description" id="ru_task_library_description" rows="3" class="large-text">' . esc_textarea($item->description ?? '') . '</textarea></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Typ úlohy', 'rodinne-ulohy') . '</th><td><fieldset>';
        echo '<label><input type="radio" name="task_category" value="povinne" ' . checked(($item->task_category ?? 'povinne'), 'povinne', false) . ' /> ' . esc_html__('Povinná', 'rodinne-ulohy') . '</label><br />';
        echo '<label><input type="radio" name="task_category" value="dobrovolne" ' . checked(($item->task_category ?? 'povinne'), 'dobrovolne', false) . ' /> ' . esc_html__('Dobrovoľná', 'rodinne-ulohy') . '</label>';
        echo '</fieldset></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Priraďovanie', 'rodinne-ulohy') . '</th><td><fieldset>';
        echo '<label><input type="radio" name="rotation_enabled" value="1" ' . checked(intval($item->rotation_enabled ?? 1), 1, false) . ' /> ' . esc_html__('Rotuje medzi deťmi', 'rodinne-ulohy') . '</label><br />';
        echo '<label><input type="radio" name="rotation_enabled" value="0" ' . checked(intval($item->rotation_enabled ?? 1), 0, false) . ' /> ' . esc_html__('Bez rotácie', 'rodinne-ulohy') . '</label>';
        echo '</fieldset></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Dni', 'rodinne-ulohy') . '</th><td><fieldset>';
        foreach ($day_labels as $value => $label) {
            echo '<label style="margin-right:12px;display:inline-block;"><input type="checkbox" name="days_of_week[]" value="' . esc_attr($value) . '" ' . checked(in_array($value, $selected_days, true), true, false) . ' /> ' . esc_html($label) . '</label>';
        }
        echo '<p class="description">' . esc_html__('Ak nevyberieš nič, úloha sa uloží bez pevne nastavených dní.', 'rodinne-ulohy') . '</p>';
        echo '</fieldset></td></tr>';
        echo '<tr><th scope="row"><label for="ru_task_library_rating">' . esc_html__('Body', 'rodinne-ulohy') . '</label></th><td><input name="rating" id="ru_task_library_rating" type="number" min="0" class="small-text" value="' . esc_attr((string) ($item->rating ?? 0)) . '" /></td></tr>';
        echo '<tr><th scope="row"><label for="ru_task_library_estimated_time">' . esc_html__('Odhadovaný čas (min)', 'rodinne-ulohy') . '</label></th><td><input name="estimated_time" id="ru_task_library_estimated_time" type="number" min="0" class="small-text" value="' . esc_attr((string) ($item->estimated_time ?? '')) . '" /></td></tr>';
        echo '<tr><th scope="row"><label for="ru_task_library_sort_order">' . esc_html__('Poradie', 'rodinne-ulohy') . '</label></th><td><input name="sort_order" id="ru_task_library_sort_order" type="number" class="small-text" value="' . esc_attr((string) ($item->sort_order ?? 0)) . '" /></td></tr>';
        echo '</tbody></table>';
        submit_button($edit_id ? __('Uložiť úlohu', 'rodinne-ulohy') : __('Pridať úlohu', 'rodinne-ulohy'));
        if ($edit_id) {
            echo '<a class="button button-secondary" href="' . esc_url($this->task_library_page_url()) . '">' . esc_html__('Zrušiť úpravu', 'rodinne-ulohy') . '</a>';
        }
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__('Položky v knižnici', 'rodinne-ulohy') . '</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Názov', 'rodinne-ulohy') . '</th>';
        echo '<th>' . esc_html__('Typ', 'rodinne-ulohy') . '</th>';
        echo '<th>' . esc_html__('Rotácia', 'rodinne-ulohy') . '</th>';
        echo '<th>' . esc_html__('Dni', 'rodinne-ulohy') . '</th>';
        echo '<th>' . esc_html__('Body', 'rodinne-ulohy') . '</th>';
        echo '<th>' . esc_html__('Poradie', 'rodinne-ulohy') . '</th>';
        echo '<th style="width:160px">' . esc_html__('Akcie', 'rodinne-ulohy') . '</th>';
        echo '</tr></thead><tbody>';
        if (empty($items)) {
            echo '<tr><td colspan="7">' . esc_html__('Knižnica úloh je zatiaľ prázdna.', 'rodinne-ulohy') . '</td></tr>';
        } else {
            foreach ($items as $row) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($row->name) . '</strong><br /><span class="description">' . esc_html($row->description ?? '') . '</span></td>';
                echo '<td>' . esc_html($row->task_category === 'dobrovolne' ? __('Dobrovoľná', 'rodinne-ulohy') : __('Povinná', 'rodinne-ulohy')) . '</td>';
                echo '<td>' . esc_html(!empty($row->rotation_enabled) ? __('Rotuje', 'rodinne-ulohy') : __('Bez rotácie', 'rodinne-ulohy')) . '</td>';
                echo '<td>' . esc_html((string) ($row->days_of_week ?? '')) . '</td>';
                echo '<td>' . esc_html((string) intval($row->rating ?? 0)) . '</td>';
                echo '<td>' . esc_html((string) intval($row->sort_order ?? 0)) . '</td>';
                echo '<td>';
                echo '<a class="button button-small" href="' . esc_url($this->task_library_page_url(array('edit' => intval($row->id)))) . '">' . esc_html__('Upraviť', 'rodinne-ulohy') . '</a> ';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;" onsubmit="return confirm(\'' . esc_js(__('Naozaj odstrániť?', 'rodinne-ulohy')) . '\')">';
                echo '<input type="hidden" name="action" value="rodinne_ulohy_delete_task_library" />';
                echo '<input type="hidden" name="id" value="' . esc_attr(intval($row->id)) . '" />';
                wp_nonce_field('rodinne_ulohy_delete_task_library');
                echo '<button type="submit" class="button button-small">' . esc_html__('Zmazať', 'rodinne-ulohy') . '</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    public function handle_save_task_library() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte oprávnenie', 'rodinne-ulohy'));
        }
        check_admin_referer('rodinne_ulohy_save_task_library');

        $data = array(
            'id' => isset($_POST['id']) ? intval($_POST['id']) : 0,
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
            'task_category' => isset($_POST['task_category']) ? sanitize_text_field(wp_unslash($_POST['task_category'])) : 'povinne',
            'rotation_enabled' => isset($_POST['rotation_enabled']) ? intval(wp_unslash($_POST['rotation_enabled'])) : 1,
            'days_of_week' => $this->parse_days_from_request('days_of_week'),
            'rating' => isset($_POST['rating']) ? intval(wp_unslash($_POST['rating'])) : 0,
            'estimated_time' => isset($_POST['estimated_time']) ? sanitize_text_field(wp_unslash($_POST['estimated_time'])) : '',
            'sort_order' => isset($_POST['sort_order']) ? intval(wp_unslash($_POST['sort_order'])) : 0,
        );

        $saved = Rodinne_Ulohy_Database::save_task_library_item($data);
        if (is_wp_error($saved)) {
            wp_safe_redirect($this->task_library_page_url(array('error' => $saved->get_error_message(), 'edit' => $data['id'])));
            exit;
        }
        if ($saved === false) {
            wp_safe_redirect($this->task_library_page_url(array('error' => __('Úlohu sa nepodarilo uložiť.', 'rodinne-ulohy'), 'edit' => $data['id'])));
            exit;
        }

        wp_safe_redirect($this->task_library_page_url(array('saved' => 1)));
        exit;
    }

    public function handle_delete_task_library() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte oprávnenie', 'rodinne-ulohy'));
        }
        check_admin_referer('rodinne_ulohy_delete_task_library');
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id) {
            Rodinne_Ulohy_Database::delete_task_library_item($id);
        }
        wp_safe_redirect($this->task_library_page_url(array('deleted' => 1)));
        exit;
    }

    public function render_reward_library_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte oprávnenie', 'rodinne-ulohy'));
        }

        $saved = !empty($_GET['saved']);
        $deleted = !empty($_GET['deleted']);
        $error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $item = $edit_id ? Rodinne_Ulohy_Database::get_reward_library_item($edit_id) : null;
        if (!$item) {
            $item = $this->get_reward_library_form_defaults();
        }
        $items = Rodinne_Ulohy_Database::get_reward_library_items();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Knižnica odmien', 'rodinne-ulohy') . '</h1>';
        echo '<p>' . esc_html__('Tu nastavíš globálnu knižnicu odmien, z ktorej si rodičia v appke pridajú odmeny do svojej rodiny.', 'rodinne-ulohy') . '</p>';
        echo $this->get_notice_html($saved, $deleted, $error);

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="rodinne_ulohy_save_reward_library" />';
        echo '<input type="hidden" name="id" value="' . esc_attr(intval($item->id ?? 0)) . '" />';
        wp_nonce_field('rodinne_ulohy_save_reward_library');
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="ru_reward_library_title">' . esc_html__('Názov odmeny', 'rodinne-ulohy') . '</label></th><td><input name="title" id="ru_reward_library_title" type="text" class="regular-text" value="' . esc_attr($item->title ?? '') . '" required /></td></tr>';
        echo '<tr><th scope="row"><label for="ru_reward_library_category">' . esc_html__('Kategória', 'rodinne-ulohy') . '</label></th><td><input name="category" id="ru_reward_library_category" type="text" class="regular-text" value="' . esc_attr($item->category ?? '') . '" /></td></tr>';
        echo '<tr><th scope="row"><label for="ru_reward_library_details">' . esc_html__('Popis', 'rodinne-ulohy') . '</label></th><td><input name="details" id="ru_reward_library_details" type="text" class="regular-text" value="' . esc_attr($item->details ?? '') . '" /></td></tr>';
        echo '<tr><th scope="row"><label for="ru_reward_library_icon">' . esc_html__('Ikona', 'rodinne-ulohy') . '</label></th><td><input name="icon" id="ru_reward_library_icon" type="text" class="small-text" value="' . esc_attr($item->icon ?? '🎁') . '" /></td></tr>';
        echo '<tr><th scope="row"><label for="ru_reward_library_points">' . esc_html__('Cena v bodoch', 'rodinne-ulohy') . '</label></th><td><input name="points_cost" id="ru_reward_library_points" type="number" min="0" class="small-text" value="' . esc_attr((string) ($item->points_cost ?? 0)) . '" /></td></tr>';
        echo '<tr><th scope="row"><label for="ru_reward_library_sort_order">' . esc_html__('Poradie', 'rodinne-ulohy') . '</label></th><td><input name="sort_order" id="ru_reward_library_sort_order" type="number" class="small-text" value="' . esc_attr((string) ($item->sort_order ?? 0)) . '" /></td></tr>';
        echo '</tbody></table>';
        submit_button($edit_id ? __('Uložiť odmenu', 'rodinne-ulohy') : __('Pridať odmenu', 'rodinne-ulohy'));
        if ($edit_id) {
            echo '<a class="button button-secondary" href="' . esc_url($this->reward_library_page_url()) . '">' . esc_html__('Zrušiť úpravu', 'rodinne-ulohy') . '</a>';
        }
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__('Položky v knižnici', 'rodinne-ulohy') . '</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Názov', 'rodinne-ulohy') . '</th>';
        echo '<th>' . esc_html__('Kategória', 'rodinne-ulohy') . '</th>';
        echo '<th>' . esc_html__('Ikona', 'rodinne-ulohy') . '</th>';
        echo '<th>' . esc_html__('Body', 'rodinne-ulohy') . '</th>';
        echo '<th>' . esc_html__('Poradie', 'rodinne-ulohy') . '</th>';
        echo '<th style="width:160px">' . esc_html__('Akcie', 'rodinne-ulohy') . '</th>';
        echo '</tr></thead><tbody>';
        if (empty($items)) {
            echo '<tr><td colspan="6">' . esc_html__('Knižnica odmien je zatiaľ prázdna.', 'rodinne-ulohy') . '</td></tr>';
        } else {
            foreach ($items as $row) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($row->title) . '</strong><br /><span class="description">' . esc_html($row->details ?? '') . '</span></td>';
                echo '<td>' . esc_html($row->category ?? '') . '</td>';
                echo '<td>' . esc_html($row->icon ?: '🎁') . '</td>';
                echo '<td>' . esc_html((string) intval($row->points_cost ?? 0)) . '</td>';
                echo '<td>' . esc_html((string) intval($row->sort_order ?? 0)) . '</td>';
                echo '<td>';
                echo '<a class="button button-small" href="' . esc_url($this->reward_library_page_url(array('edit' => intval($row->id)))) . '">' . esc_html__('Upraviť', 'rodinne-ulohy') . '</a> ';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;" onsubmit="return confirm(\'' . esc_js(__('Naozaj odstrániť?', 'rodinne-ulohy')) . '\')">';
                echo '<input type="hidden" name="action" value="rodinne_ulohy_delete_reward_library" />';
                echo '<input type="hidden" name="id" value="' . esc_attr(intval($row->id)) . '" />';
                wp_nonce_field('rodinne_ulohy_delete_reward_library');
                echo '<button type="submit" class="button button-small">' . esc_html__('Zmazať', 'rodinne-ulohy') . '</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    public function handle_save_reward_library() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte oprávnenie', 'rodinne-ulohy'));
        }
        check_admin_referer('rodinne_ulohy_save_reward_library');

        $data = array(
            'id' => isset($_POST['id']) ? intval($_POST['id']) : 0,
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '',
            'category' => isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '',
            'details' => isset($_POST['details']) ? sanitize_text_field(wp_unslash($_POST['details'])) : '',
            'icon' => isset($_POST['icon']) ? sanitize_text_field(wp_unslash($_POST['icon'])) : '',
            'points_cost' => isset($_POST['points_cost']) ? intval(wp_unslash($_POST['points_cost'])) : 0,
            'sort_order' => isset($_POST['sort_order']) ? intval(wp_unslash($_POST['sort_order'])) : 0,
        );

        $saved = Rodinne_Ulohy_Database::save_reward_library_item($data);
        if (is_wp_error($saved)) {
            wp_safe_redirect($this->reward_library_page_url(array('error' => $saved->get_error_message(), 'edit' => $data['id'])));
            exit;
        }
        if ($saved === false) {
            wp_safe_redirect($this->reward_library_page_url(array('error' => __('Odmenu sa nepodarilo uložiť.', 'rodinne-ulohy'), 'edit' => $data['id'])));
            exit;
        }

        wp_safe_redirect($this->reward_library_page_url(array('saved' => 1)));
        exit;
    }

    public function handle_delete_reward_library() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte oprávnenie', 'rodinne-ulohy'));
        }
        check_admin_referer('rodinne_ulohy_delete_reward_library');
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id) {
            Rodinne_Ulohy_Database::delete_reward_library_item($id);
        }
        wp_safe_redirect($this->reward_library_page_url(array('deleted' => 1)));
        exit;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte oprávnenie', 'rodinne-ulohy'));
        }

        $saved = !empty($_GET['saved']);
        $google_client_id = strval(get_option('rodinne_ulohy_google_client_id', ''));
        $is_defined_in_constant = defined('RODINNE_ULOHY_GOOGLE_CLIENT_ID');
        $constant_value = $is_defined_in_constant ? strval(constant('RODINNE_ULOHY_GOOGLE_CLIENT_ID')) : '';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Nastavenia', 'rodinne-ulohy') . '</h1>';
        echo '<p>' . esc_html__('Základné nastavenia pluginu ekidio.', 'rodinne-ulohy') . '</p>';

        if ($saved) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Nastavenia boli uložené.', 'rodinne-ulohy') . '</p></div>';
        }

        if ($is_defined_in_constant) {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Google Client ID je momentálne definovaný konštantou v konfigurácii WordPressu. Hodnota uložená nižšie sa bude ignorovať, kým konštanta zostáva nastavená.', 'rodinne-ulohy');
            echo '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="rodinne_ulohy_save_settings" />';
        wp_nonce_field('rodinne_ulohy_save_settings');
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr>';
        echo '<th scope="row"><label for="ru_google_client_id">' . esc_html__('Google Client ID', 'rodinne-ulohy') . '</label></th>';
        echo '<td>';
        echo '<input name="google_client_id" id="ru_google_client_id" type="text" class="regular-text code" value="' . esc_attr($google_client_id) . '" placeholder="1234567890-abc123.apps.googleusercontent.com" />';
        echo '<p class="description">' . esc_html__('Sem vlož OAuth Client ID typu Web application z Google Cloud Console. Tento client ID sa použije na webe aj v Android appke.', 'rodinne-ulohy') . '</p>';
        if ($is_defined_in_constant && $constant_value !== '') {
            echo '<p class="description"><strong>' . esc_html__('Aktívna hodnota z konštanty:', 'rodinne-ulohy') . '</strong> <code>' . esc_html($constant_value) . '</code></p>';
        }
        echo '</td>';
        echo '</tr>';

        echo '</tbody></table>';
        submit_button(__('Uložiť nastavenia', 'rodinne-ulohy'));
        echo '</form>';
        echo '</div>';
    }

    public function handle_save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte oprávnenie', 'rodinne-ulohy'));
        }
        check_admin_referer('rodinne_ulohy_save_settings');

        $google_client_id = isset($_POST['google_client_id'])
            ? sanitize_text_field(wp_unslash($_POST['google_client_id']))
            : '';

        update_option('rodinne_ulohy_google_client_id', $google_client_id, false);

        wp_safe_redirect($this->settings_page_url(array('saved' => 1)));
        exit;
    }

    public function render_devtools_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte oprávnenie', 'rodinne-ulohy'));
        }
        if (!(defined('RODINNE_ULOHY_DEV_TOOLS') && RODINNE_ULOHY_DEV_TOOLS)) {
            wp_die(__('Dev Tools sú vypnuté.', 'rodinne-ulohy'));
        }

        $msg = isset($_GET['ru_msg']) ? sanitize_text_field(wp_unslash($_GET['ru_msg'])) : '';
        $payload = array();

        // Preferred: load payload from transient by token (avoids huge URLs and connection drops).
        $token = isset($_GET['ru_token']) ? sanitize_text_field(wp_unslash($_GET['ru_token'])) : '';
        if (!empty($token)) {
            $user_id = get_current_user_id();
            $key = 'ru_devtools_payload_' . intval($user_id) . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $token);
            $stored = get_transient($key);
            if (is_array($stored)) {
                $payload = $stored;
            }
            delete_transient($key);
        } else {
            // Backward-compatible fallback: payload in URL (may break for big payloads).
            $payload_raw = isset($_GET['ru_payload']) ? wp_unslash($_GET['ru_payload']) : '';
            if (!empty($payload_raw)) {
                $decoded = json_decode($payload_raw, true);
                if (is_array($decoded)) $payload = $decoded;
            }
        }

        // Default to "now" in WP timezone.
        $default_dt = function_exists('current_datetime') ? current_datetime()->format('Y-m-d\TH:i') : wp_date('Y-m-d\TH:i', current_time('timestamp'));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('ekidio – Dev Tools', 'rodinne-ulohy') . '</h1>';
        echo '<p>' . esc_html__('Nástroje na simuláciu cronov bez čakania týždeň. Používajte len na testovacej inštalácii.', 'rodinne-ulohy') . '</p>';

        if (!empty($msg)) {
            echo '<div class="notice notice-info is-dismissible"><p><strong>' . esc_html($msg) . '</strong></p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="rodinne_ulohy_devtools_run" />';
        wp_nonce_field('rodinne_ulohy_devtools_run');

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr>';
        echo '<th scope="row"><label for="ru_action">' . esc_html__('Akcia', 'rodinne-ulohy') . '</label></th>';
        echo '<td>';
        echo '<select name="ru_action" id="ru_action">';
        echo '<option value="daily_reset">' . esc_html__('Denná uzávierka (22:50) – penalizácie + reset', 'rodinne-ulohy') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="ru_now">' . esc_html__('Simulovaný dátum/čas (WP timezone)', 'rodinne-ulohy') . '</label></th>';
        echo '<td>';
        echo '<input type="datetime-local" id="ru_now" name="ru_now" value="' . esc_attr($default_dt) . '" />';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Dry-run', 'rodinne-ulohy') . '</th>';
        echo '<td><label><input type="checkbox" name="ru_dry_run" value="1" checked /> ' . esc_html__('Len vypísať čo by sa spravilo (bez zmien v DB)', 'rodinne-ulohy') . '</label></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Detaily', 'rodinne-ulohy') . '</th>';
        echo '<td><label><input type="checkbox" name="ru_details" value="1" /> ' . esc_html__('Zobraziť zoznam úloh (pre debugging)', 'rodinne-ulohy') . '</label></td>';
        echo '</tr>';

        echo '</tbody></table>';

        submit_button(__('Spustiť', 'rodinne-ulohy'));
        echo '</form>';

        if (!empty($payload)) {
            echo '<h2>' . esc_html__('Výsledok', 'rodinne-ulohy') . '</h2>';
            echo '<pre style="background:#0b1020;color:#d6e1ff;padding:12px;border-radius:8px;max-width:1100px;overflow:auto;">' . esc_html(wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
        }

        echo '</div>';
    }

    public function handle_devtools_run() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte oprávnenie', 'rodinne-ulohy'));
        }
        if (!(defined('RODINNE_ULOHY_DEV_TOOLS') && RODINNE_ULOHY_DEV_TOOLS)) {
            wp_die(__('Dev Tools sú vypnuté.', 'rodinne-ulohy'));
        }
        check_admin_referer('rodinne_ulohy_devtools_run');

        $action = isset($_POST['ru_action']) ? sanitize_text_field(wp_unslash($_POST['ru_action'])) : '';
        $now_raw = isset($_POST['ru_now']) ? sanitize_text_field(wp_unslash($_POST['ru_now'])) : '';
        $dry_run = !empty($_POST['ru_dry_run']);
        $details = !empty($_POST['ru_details']);

        $payload = array('ok' => false);
        $msg = __('Hotovo', 'rodinne-ulohy');

        // Parse datetime-local (YYYY-MM-DDTHH:MM) in WP timezone.
        $now_ts = current_time('timestamp');
        if (!empty($now_raw)) {
            $now_raw = str_replace('T', ' ', $now_raw);
            try {
                $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
                $dt = new DateTimeImmutable($now_raw, $tz);
                $now_ts = $dt->getTimestamp();
            } catch (Throwable $e) {
                // keep default
            }
        }

        if ($action === 'daily_reset') {
            $payload = Rodinne_Ulohy_Rotation::get_instance()->run_daily_reset($now_ts, $dry_run, $details);
            $msg = $dry_run ? __('Daily reset (dry-run) dokončený', 'rodinne-ulohy') : __('Daily reset vykonaný', 'rodinne-ulohy');
        } else {
            $msg = __('Neznáma akcia', 'rodinne-ulohy');
            $payload = array('ok' => false, 'error' => 'unknown_action');
        }

        // Store payload server-side to avoid massive URL (details can be large).
        $user_id = get_current_user_id();
        $token = function_exists('wp_generate_password') ? wp_generate_password(16, false, false) : substr(md5(uniqid('', true)), 0, 16);
        $key = 'ru_devtools_payload_' . intval($user_id) . '_' . $token;
        set_transient($key, $payload, 10 * MINUTE_IN_SECONDS);

        $redirect = admin_url('admin.php?page=rodinne-ulohy-devtools&ru_msg=' . rawurlencode($msg) . '&ru_token=' . rawurlencode($token));
        wp_safe_redirect($redirect);
        exit;
    }
}
