<?php
/**
 * Shortcode handler for ekidio plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rodinne_Ulohy_Shortcode {
    
    private static $instance = null;
    private static $page_has_shortcode = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('rodinne_ulohy_child', array($this, 'render_child_overview'));
        add_shortcode('rodinne_ulohy_app', array($this, 'render_app'));
        add_shortcode('rodinne_ulohy_app_parent', array($this, 'render_app_parent'));
        add_shortcode('rodinne_ulohy_app_child', array($this, 'render_app_child'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_ajax_rodinne_ulohy_update_task_status', array($this, 'update_task_status'));
        add_action('wp_ajax_nopriv_rodinne_ulohy_update_task_status', array($this, 'update_task_status'));
        add_action('wp_ajax_rodinne_ulohy_save_child_avatar', array($this, 'save_child_avatar'));
        add_action('wp_ajax_nopriv_rodinne_ulohy_save_child_avatar', array($this, 'save_child_avatar'));
        add_action('wp_ajax_rodinne_ulohy_upload_child_avatar', array($this, 'upload_child_avatar'));
        add_action('wp_ajax_nopriv_rodinne_ulohy_upload_child_avatar', array($this, 'upload_child_avatar'));
        add_action('wp_ajax_rodinne_ulohy_set_child_pin', array($this, 'set_child_pin'));
        add_action('wp_ajax_nopriv_rodinne_ulohy_set_child_pin', array($this, 'set_child_pin'));
        add_action('wp_ajax_rodinne_ulohy_set_child_color', array($this, 'set_child_color'));
        add_action('wp_ajax_nopriv_rodinne_ulohy_set_child_color', array($this, 'set_child_color'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        global $post;
        $has_child_shortcode = false;
        $has_app_shortcode = false;
        
        // Check current post content
        if ($post && isset($post->post_content)) {
            $has_child_shortcode = has_shortcode($post->post_content, 'rodinne_ulohy_child');
            $has_app_shortcode = (
                has_shortcode($post->post_content, 'rodinne_ulohy_app') ||
                has_shortcode($post->post_content, 'rodinne_ulohy_app_parent') ||
                has_shortcode($post->post_content, 'rodinne_ulohy_app_child')
            );
        }
        
        // Check all posts if shortcode might be used
        if (!$has_child_shortcode || !$has_app_shortcode) {
            // Simple check - if we're on a page/post, check its content
            if (is_singular() && $post) {
                if (!$has_child_shortcode) {
                    $has_child_shortcode = has_shortcode($post->post_content, 'rodinne_ulohy_child');
                }
                if (!$has_app_shortcode) {
                    $has_app_shortcode = (
                        has_shortcode($post->post_content, 'rodinne_ulohy_app') ||
                        has_shortcode($post->post_content, 'rodinne_ulohy_app_parent') ||
                        has_shortcode($post->post_content, 'rodinne_ulohy_app_child')
                    );
                }
            }
        }
        
        if ($has_child_shortcode) {
            self::$page_has_shortcode = true;
            
            // Load Google Font with full Latin support
            wp_enqueue_style(
                'rodinne-ulohy-fonts',
                'https://fonts.googleapis.com/css2?family=Patrick+Hand:wght@400&display=swap',
                array(),
                null
            );
            
            wp_enqueue_style(
                'rodinne-ulohy-frontend',
                RODINNE_ULOHY_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                RODINNE_ULOHY_VERSION
            );
            
            wp_enqueue_script(
                'rodinne-ulohy-frontend',
                RODINNE_ULOHY_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                RODINNE_ULOHY_VERSION,
                true
            );
            
            // Enqueue WordPress media uploader for avatar upload
            wp_enqueue_media();
            
            wp_localize_script('rodinne-ulohy-frontend', 'rodinneUlohyFrontend', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rodinne_ulohy_frontend_nonce'),
                'strings' => array(
                    'purchaseButton' => __('Chcem', 'rodinne-ulohy'),
                    'confirmPurchase' => __('Odpočíta sa ti %s bodov. OK', 'rodinne-ulohy'),
                    'processing' => __('Spracovávam...', 'rodinne-ulohy'),
                    'purchaseError' => __('Odmenu sa nepodarilo kúpiť.', 'rodinne-ulohy'),
                    'notEnoughPoints' => __('Máš málo bodov', 'rodinne-ulohy'),
                    'purchasedLabel' => __('Zakúpené %d× – platí ešte 48 hodín', 'rodinne-ulohy')
                ),
            ));
        }

        if ($has_app_shortcode) {
            // Enqueue SPA assets (prefer Vite manifest with hashed filenames).
            $assets = $this->get_spa_assets();
            $css_files = isset($assets['css']) && is_array($assets['css']) ? $assets['css'] : array();
            $js_file = isset($assets['js']) ? $assets['js'] : '';

            // Vue SPA bundle - just enqueue, don't localize here
            // (localization happens per-shortcode in enqueue_spa_with_config)
            $i = 0;
            foreach ($css_files as $css_rel) {
                $i++;
                $css_path = RODINNE_ULOHY_PLUGIN_DIR . ltrim($css_rel, '/');
                $css_ver = file_exists($css_path) ? filemtime($css_path) : RODINNE_ULOHY_VERSION;
                $handle = $i === 1 ? 'rodinne-ulohy-spa' : ('rodinne-ulohy-spa-css-' . $i);
                wp_enqueue_style(
                    $handle,
                    RODINNE_ULOHY_PLUGIN_URL . ltrim($css_rel, '/'),
                    array(),
                    $css_ver
                );
            }

            if (!empty($js_file)) {
                $js_path  = RODINNE_ULOHY_PLUGIN_DIR . ltrim($js_file, '/');
                $js_ver  = file_exists($js_path) ? filemtime($js_path) : RODINNE_ULOHY_VERSION;
                wp_enqueue_script(
                    'rodinne-ulohy-spa',
                    RODINNE_ULOHY_PLUGIN_URL . ltrim($js_file, '/'),
                    array(),
                    $js_ver,
                    true
                );
            }
            // Vite build je ES module – označíme typ, aby sa predišlo chybe "import.meta"
            wp_script_add_data('rodinne-ulohy-spa', 'type', 'module');
            // Záloha: ak niektorý cache/optimizer odstráni type="module", vynútime ho filtrom
            add_filter('script_loader_tag', function($tag, $handle, $src) {
                if ($handle === 'rodinne-ulohy-spa') {
                    $tag = sprintf(
                        '<script type="module" src="%s" id="%s-js"></script>',
                        esc_url($src),
                        esc_attr($handle)
                    );
                }
                return $tag;
            }, 10, 3);
        }
    }

    /**
     * Resolve SPA asset paths.
     * Prefers Vite dist/manifest.json (hashed files) and falls back to legacy fixed filenames.
     *
     * @return array{js:string, css:array<int,string>}
     */
    private function get_spa_assets() {
        // Prefer manifest in dist root (easier deployments). Fallback to Vite default (.vite) if needed.
        $manifest_path = RODINNE_ULOHY_PLUGIN_DIR . 'dist/manifest.json';
        if (!file_exists($manifest_path)) {
            $manifest_path = RODINNE_ULOHY_PLUGIN_DIR . 'dist/.vite/manifest.json';
        }
        if (file_exists($manifest_path)) {
            $raw = file_get_contents($manifest_path);
            $manifest = $raw ? json_decode($raw, true) : null;
            if (is_array($manifest) && isset($manifest['index.html']) && is_array($manifest['index.html'])) {
                $entry = $manifest['index.html'];
                $js = !empty($entry['file']) ? ('dist/' . ltrim($entry['file'], '/')) : '';
                $css = array();
                if (!empty($entry['css']) && is_array($entry['css'])) {
                    foreach ($entry['css'] as $c) {
                        if (!$c) continue;
                        $css[] = 'dist/' . ltrim($c, '/');
                    }
                }
                // Some builds can inline CSS; keep legacy fallback then.
                if ($js) {
                    // Guard against partial deployments: manifest updated but hashed files missing on disk.
                    $js_path = RODINNE_ULOHY_PLUGIN_DIR . ltrim($js, '/');
                    if (!file_exists($js_path)) {
                        // Fall through to directory scan fallback below.
                    } else {
                    return array(
                        'js' => $js,
                        'css' => $css ? $css : array('dist/assets/app.css'),
                    );
                    }
                }
            }
        }
        // Fallback: scan dist/assets for latest hashed Vite output (helps when manifest is stale/missing).
        $assets_dir = RODINNE_ULOHY_PLUGIN_DIR . 'dist/assets/';
        if (is_dir($assets_dir)) {
            $js_candidates = glob($assets_dir . 'index-*.js');
            $css_candidates = glob($assets_dir . 'index-*.css');

            $pick_latest = function($files) {
                if (!is_array($files) || empty($files)) return '';
                usort($files, function($a, $b) {
                    return (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0);
                });
                return $files[0] ?? '';
            };

            $js_file = $pick_latest($js_candidates);
            $css_file = $pick_latest($css_candidates);

            if (!empty($js_file) && file_exists($js_file)) {
                $rel_js = 'dist/assets/' . basename($js_file);
                $rel_css = (!empty($css_file) && file_exists($css_file)) ? array('dist/assets/' . basename($css_file)) : array();
                return array(
                    'js' => $rel_js,
                    'css' => $rel_css,
                );
            }
        }

        // Legacy fallback (older builds without manifest)
        return array(
            'js' => 'dist/assets/app.js',
            'css' => array('dist/assets/app.css'),
        );
    }
    
    
    /**
     * Render child overview shortcode
     */
    public function render_child_overview($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'name' => '',
        ), $atts);
        
        // Get child by ID or name
        if (!empty($atts['id'])) {
            $child = Rodinne_Ulohy_Database::get_child(intval($atts['id']));
        } elseif (!empty($atts['name'])) {
            $children = Rodinne_Ulohy_Database::get_children();
            $child = null;
            foreach ($children as $c) {
                if (strtolower($c->name) === strtolower($atts['name'])) {
                    $child = $c;
                    break;
                }
            }
        } else {
            return '<p>' . __('Chýba ID alebo meno dieťaťa', 'rodinne-ulohy') . '</p>';
        }
        
        if (!$child) {
            return '<p>' . __('Dieťa nebolo nájdené', 'rodinne-ulohy') . '</p>';
        }
        
        // Get current week assignments
        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $week_range = Rodinne_Ulohy_Database::get_week_range($week_start);
        
        // Current day label (e.g., "Utorok - 2.12. 2025")
        $current_timestamp = current_time('timestamp');
        $weekday_index = intval(date_i18n('w', $current_timestamp));
        $weekday_names = array(
            __('Nedeľa', 'rodinne-ulohy'),
            __('Pondelok', 'rodinne-ulohy'),
            __('Utorok', 'rodinne-ulohy'),
            __('Streda', 'rodinne-ulohy'),
            __('Štvrtok', 'rodinne-ulohy'),
            __('Piatok', 'rodinne-ulohy'),
            __('Sobota', 'rodinne-ulohy'),
        );
        $current_day_label = $weekday_names[$weekday_index] ?? date_i18n('l', $current_timestamp);
        $current_date_label = $current_day_label . ' - ' . date_i18n('j.n. Y', $current_timestamp);
        $all_assignments = Rodinne_Ulohy_Database::get_child_assignments($child->id, $week_start);
        
        // Get points balance
        $points_balance = Rodinne_Ulohy_Database::get_points_balance($child->id);
        $today_points = Rodinne_Ulohy_Database::get_today_points_total($child->id);
        
        // Get rewards and active purchases
        $rewards = Rodinne_Ulohy_Database::get_rewards();
        $purchases = Rodinne_Ulohy_Database::get_child_active_reward_purchases($child->id);
        $active_reward_counts = array();
        foreach ($purchases as $p) {
            $rid = intval($p->reward_id);
            if (!isset($active_reward_counts[$rid])) {
                $active_reward_counts[$rid] = 0;
            }
            $active_reward_counts[$rid]++;
        }
        
        // Filter assignments based on task type and current day, and separate by category
        $current_day = date('w'); // 0 = Sunday, 6 = Saturday
        $is_saturday = ($current_day == 6);
        
        $povinne_assignments = array();
        $dobrovolne_assignments = array();
        
        foreach ($all_assignments as $assignment) {
            // Get task details to check task_type and task_category
            $task = Rodinne_Ulohy_Database::get_task($assignment->task_id);
            if (!$task) {
                continue;
            }
            
            $task_category = isset($task->task_category) ? $task->task_category : 'povinne';
            
            // Filter tasks based on days_of_week
            $should_show = true;
            $days_of_week = isset($task->days_of_week) && !empty($task->days_of_week) ? $task->days_of_week : '';
            
            if (!empty($days_of_week)) {
                $task_days = array_map('intval', explode(',', $days_of_week));
                // Check if current day is in the task's days
                if (!in_array($current_day, $task_days)) {
                    $should_show = false;
                }
            } else {
                // No days_of_week configured => show every day (legacy behavior).
                $should_show = true;
            }
            
            if ($should_show) {
                // Use task details from assignment (already loaded from database)
                $assignment->task_rating = isset($assignment->task_rating) && $assignment->task_rating !== null ? intval($assignment->task_rating) : 0;
                $assignment->task_category = isset($assignment->task_category) ? $assignment->task_category : 'povinne';
                
                if ($assignment->task_category === 'dobrovolne') {
                    $dobrovolne_assignments[] = $assignment;
                } else {
                    $povinne_assignments[] = $assignment;
                }
            }
        }
        
        // Calculate totals
        $povinne_completed = 0;
        $povinne_total = count($povinne_assignments);
        $dobrovolne_completed = 0;
        $dobrovolne_total = count($dobrovolne_assignments);
        
        foreach ($povinne_assignments as $a) {
            if ($a->status === 'completed') {
                $povinne_completed++;
            }
        }
        
        foreach ($dobrovolne_assignments as $a) {
            if ($a->status === 'completed') {
                $dobrovolne_completed++;
            }
        }
        
        $all_completed = ($povinne_completed === $povinne_total && $dobrovolne_completed === $dobrovolne_total && ($povinne_total + $dobrovolne_total) > 0);
        
        ob_start();
        ?>
        <div class="rodinne-ulohy-child-overview" data-child-id="<?php echo esc_attr($child->id); ?>">
            <!-- Sticky Header -->
            <div class="child-mobile-header">
                <div class="child-header-avatar">
                    <?php if (!empty($child->avatar_url)): ?>
                        <img src="<?php echo esc_url($child->avatar_url); ?>" alt="<?php echo esc_attr($child->name); ?>" class="child-avatar-img">
                    <?php else: ?>
                        <div class="child-avatar-placeholder">
                            <span class="child-avatar-initial"><?php echo esc_html(mb_substr($child->name, 0, 1, 'UTF-8')); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="child-header-name">
                    <?php echo esc_html($child->name); ?>
                </div>
                <div class="child-header-points">
                    <span class="points-number"><?php echo intval($points_balance->balance); ?></span>
                    <span class="points-star">⭐</span>
                </div>
            </div>
            
            <!-- Content Area -->
            <div class="child-mobile-content">
                <!-- Úlohy Section -->
                <div class="child-section active" data-section="tasks">
                    <?php if (!empty($povinne_assignments) || !empty($dobrovolne_assignments)): ?>
                        <!-- Povinné úlohy -->
                        <?php if (!empty($povinne_assignments)): ?>
                            <div class="child-tasks-section povinne-section">
                                <div class="section-header">
                                    <div class="section-icon">📋</div>
                                    <div class="section-title-wrapper">
                                        <h3 class="section-title">
                                            <?php _e('Povinné úlohy', 'rodinne-ulohy'); ?>
                                        </h3>
                                        <span class="section-progress">
                                            <?php echo $povinne_completed; ?>/<?php echo $povinne_total; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="child-tasks-list">
                                    <?php foreach ($povinne_assignments as $assignment): 
                                        $task = Rodinne_Ulohy_Database::get_task($assignment->task_id);
                                        $is_daily = $task && isset($task->task_type) && $task->task_type === 'daily';
                                        $rating = isset($assignment->task_rating) ? intval($assignment->task_rating) : 0;
                                        $is_completed = $assignment->status === 'completed';
                                        $task_description = $task && !empty($task->description) ? $task->description : '';
                                        
                                        if ($is_completed) {
                                            $badge_bg = '#dcfce7';
                                            $badge_color = '#16a34a';
                                            $points_text = $rating . ' ⭐';
                                        } else {
                                            $badge_bg = '#fee2e2';
                                            $badge_color = '#dc2626';
                                            $points_text = '-' . $rating . ' ⭐';
                                        }
                                    ?>
                                        <div class="child-task-item <?php echo $is_completed ? 'task-completed' : ''; ?>" data-assignment-id="<?php echo esc_attr($assignment->id); ?>" data-task-type="<?php echo esc_attr($task ? ($task->task_type ?? 'weekly') : 'weekly'); ?>">
                                            <div class="task-checkbox-wrapper">
                                                <input 
                                                    type="checkbox" 
                                                    class="child-task-checkbox" 
                                                    data-assignment-id="<?php echo esc_attr($assignment->id); ?>"
                                                    id="task-<?php echo esc_attr($assignment->id); ?>"
                                                    <?php checked($assignment->status, 'completed'); ?>
                                                >
                                                <label for="task-<?php echo esc_attr($assignment->id); ?>" class="checkbox-custom"></label>
                                            </div>
                                            <div class="task-content">
                                                <span class="child-task-name"><?php echo esc_html($assignment->task_name); ?></span>
                                                <?php if (!empty($task_description)): ?>
                                                    <p class="task-description"><?php echo esc_html($task_description); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="task-points-badge" data-rating="<?php echo esc_attr($rating); ?>" style="background: <?php echo esc_attr($badge_bg); ?>; color: <?php echo esc_attr($badge_color); ?>;">
                                                <?php echo esc_html($points_text); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Dobrovoľné úlohy -->
                        <?php if (!empty($dobrovolne_assignments)): ?>
                            <div class="child-tasks-section dobrovolne-section">
                                <div class="section-header">
                                    <div class="section-icon">✨</div>
                                    <div class="section-title-wrapper">
                                        <h3 class="section-title">
                                            <?php _e('Dobrovoľné úlohy', 'rodinne-ulohy'); ?>
                                        </h3>
                                        <span class="section-progress">
                                            <?php echo $dobrovolne_completed; ?>/<?php echo $dobrovolne_total; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="child-tasks-list">
                                    <?php foreach ($dobrovolne_assignments as $assignment): 
                                        $task = Rodinne_Ulohy_Database::get_task($assignment->task_id);
                                        $is_daily = $task && isset($task->task_type) && $task->task_type === 'daily';
                                        $rating = isset($assignment->task_rating) ? intval($assignment->task_rating) : 0;
                                        $is_completed = $assignment->status === 'completed';
                                        $task_description = $task && !empty($task->description) ? $task->description : '';
                                    ?>
                                        <div class="child-task-item <?php echo $is_completed ? 'task-completed' : ''; ?>" data-assignment-id="<?php echo esc_attr($assignment->id); ?>" data-task-type="<?php echo esc_attr($task ? ($task->task_type ?? 'weekly') : 'weekly'); ?>">
                                            <div class="task-checkbox-wrapper">
                                                <input 
                                                    type="checkbox" 
                                                    class="child-task-checkbox" 
                                                    data-assignment-id="<?php echo esc_attr($assignment->id); ?>"
                                                    id="task-<?php echo esc_attr($assignment->id); ?>"
                                                    <?php checked($assignment->status, 'completed'); ?>
                                                >
                                                <label for="task-<?php echo esc_attr($assignment->id); ?>" class="checkbox-custom"></label>
                                            </div>
                                            <div class="task-content">
                                                <span class="child-task-name"><?php echo esc_html($assignment->task_name); ?></span>
                                                <?php if (!empty($task_description)): ?>
                                                    <p class="task-description"><?php echo esc_html($task_description); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="task-points-badge" data-rating="<?php echo esc_attr($rating); ?>" style="background: #dcfce7; color: #16a34a;">
                                                <?php echo $rating; ?> ⭐
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($all_completed): ?>
                            <div class="child-completion-message show">
                                <div class="completion-emoji">🎉</div>
                                <p><?php _e('Jupííí, máš to hotové!', 'rodinne-ulohy'); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="child-completion-message">
                                <div class="completion-emoji">🎉</div>
                                <p><?php _e('Jupííí, máš to hotové!', 'rodinne-ulohy'); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="child-no-tasks">
                            <?php _e('Pre tento týždeň nemáš žiadne úlohy.', 'rodinne-ulohy'); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Odmeny Section -->
                <div class="child-section" data-section="rewards">
                    <?php if (!empty($rewards)): ?>
                        <div class="child-rewards-section">
                            <div class="section-header">
                                <div class="section-icon">🎁</div>
                                <div class="section-title-wrapper">
                                    <h3 class="section-title"><?php _e('Odmeny', 'rodinne-ulohy'); ?></h3>
                                    <span class="section-progress">
                                        <?php echo count($rewards); ?> <?php _e('položiek', 'rodinne-ulohy'); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="child-rewards-list">
                                <?php foreach ($rewards as $reward): 
                                    $cost = intval($reward->points_cost);
                                    $affordable = intval($points_balance->balance) >= $cost;
                                    $reward_id = intval($reward->id);
                                    $active_count = isset($active_reward_counts[$reward_id]) ? intval($active_reward_counts[$reward_id]) : 0;
                                ?>
                                    <div class="reward-card <?php echo $affordable ? '' : 'reward-disabled'; ?> <?php echo $active_count > 0 ? 'reward-purchased' : ''; ?>"
                                        data-reward-id="<?php echo esc_attr($reward_id); ?>"
                                        data-reward-title="<?php echo esc_attr($reward->title); ?>"
                                        data-cost="<?php echo esc_attr($cost); ?>">
                                        <?php if ($active_count > 0): ?>
                                            <div class="reward-purchased-badge"><?php echo esc_html($active_count); ?>x</div>
                                        <?php endif; ?>
                                        <div class="reward-icon"><?php echo esc_html($reward->icon ? $reward->icon : '🎁'); ?></div>
                                        <div class="reward-title"><?php echo esc_html($reward->title); ?></div>
                                        <?php if (!empty($reward->details)): ?>
                                            <div class="reward-details"><?php echo esc_html($reward->details); ?></div>
                                        <?php endif; ?>
                                        <div class="reward-cost"><?php echo esc_html($cost); ?> <?php _e('bodov', 'rodinne-ulohy'); ?></div>
                                        <button type="button" class="reward-buy-btn" <?php disabled(!$affordable); ?>>
                                            <?php echo $affordable ? __('Chcem', 'rodinne-ulohy') : __('Máš málo bodov', 'rodinne-ulohy'); ?>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="child-no-tasks">
                            <?php _e('Momentálne nie sú dostupné žiadne odmeny.', 'rodinne-ulohy'); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Nastavenia Section -->
                <div class="child-section" data-section="settings">
                    <div class="child-settings-section">
                        <h3 class="section-title"><?php _e('Nastavenia', 'rodinne-ulohy'); ?></h3>
                        <div class="settings-avatar-upload">
                            <label><?php _e('Avatar', 'rodinne-ulohy'); ?></label>
                            <div class="avatar-upload-wrapper">
                                <div class="avatar-preview">
                                    <?php if (!empty($child->avatar_url)): ?>
                                        <img src="<?php echo esc_url($child->avatar_url); ?>" alt="<?php echo esc_attr($child->name); ?>" id="settings-avatar-preview-img">
                                    <?php else: ?>
                                        <div class="avatar-preview-placeholder" id="settings-avatar-placeholder">
                                            <span><?php echo esc_html(mb_substr($child->name, 0, 1, 'UTF-8')); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" id="settings-avatar-url" value="<?php echo esc_attr($child->avatar_url ?? ''); ?>">
                                <button type="button" class="btn-upload-avatar" id="upload-avatar-frontend">
                                    <?php _e('Zmeniť avatara', 'rodinne-ulohy'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sticky Footer Menu -->
            <div class="child-mobile-footer">
                <button class="footer-menu-item active" data-section="tasks">
                    <span class="menu-icon">📋</span>
                    <span class="menu-label"><?php _e('Úlohy', 'rodinne-ulohy'); ?></span>
                </button>
                <button class="footer-menu-item" data-section="rewards">
                    <span class="menu-icon">🎁</span>
                    <span class="menu-label"><?php _e('Odmeny', 'rodinne-ulohy'); ?></span>
                </button>
                <button class="footer-menu-item" data-section="settings">
                    <span class="menu-icon">⚙️</span>
                    <span class="menu-label"><?php _e('Nastavenia', 'rodinne-ulohy'); ?></span>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render unified SPA root
     */
    public function render_app($atts) {
        $atts = shortcode_atts(array(
            // Deprecated: role/child attributes were used to force UI mode.
            // We keep them for backward compatibility but ignore them.
            'role' => '',
            'child' => '',
            'child_id' => ''
        ), $atts);
        // Unified app: do not force parent/child mode by shortcode.
        // Mode is determined by the API token (/auth/me).
        $role = 'child';
        $child_id = 0;

        // Enqueue SPA assets with correct per-shortcode config
        $this->enqueue_spa_with_config($role, $child_id);
        
        // Force per-shortcode config on page (overrides any previous localization)
        $config = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => esc_url_raw(rest_url('rodinne-ulohy/v1')),
            'nonce' => wp_create_nonce('rodinne_ulohy_nonce'),
            'frontendNonce' => wp_create_nonce('rodinne_ulohy_frontend_nonce'),
            'pluginUrl' => RODINNE_ULOHY_PLUGIN_URL,
            // Do not force role; UI will resolve from token.
            'isParent' => false,
            'currentUserId' => get_current_user_id(),
            'children' => Rodinne_Ulohy_Database::get_children(),
            'role' => 'child',
            'childId' => 0,
            'forceChild' => false,
            'weekendMultiplier' => Rodinne_Ulohy_Database::get_weekend_penalty_multiplier(),
        );
        wp_add_inline_script(
            'rodinne-ulohy-spa',
            'window.rodinneUlohyApp = ' . wp_json_encode($config) . ';',
            'before'
        );
        // Optional debug overlay: append ?ru_debug=1 to the page URL
        if (isset($_GET['ru_debug']) && $_GET['ru_debug']) {
            wp_add_inline_script(
                'rodinne-ulohy-spa',
                '(function(){try{var el=document.getElementById("rodinne-ulohy-app");var ds=el?el.dataset:{};var cfg=window.rodinneUlohyApp||{};var box=document.createElement("div");box.style.cssText="position:fixed;left:8px;bottom:8px;z-index:999999;padding:8px 10px;border-radius:10px;background:rgba(0,0,0,.8);color:#fff;font:12px/1.35 monospace;max-width:92vw;";box.textContent="RU DEBUG | data-role="+(ds.role||"")+" | data-child-id="+(ds.childId||"")+" | cfg.role="+(cfg.role||"")+" | cfg.childId="+(cfg.childId||"")+" | cfg.forceChild="+(cfg.forceChild?"1":"0")+" | hash="+(location.hash||"");document.body.appendChild(box);console.log("RU DEBUG dataset",ds);console.log("RU DEBUG cfg",cfg);}catch(e){console.warn("RU DEBUG failed",e);}})();',
                'before'
            );
        }

        ob_start();
        ?>
        <div id="rodinne-ulohy-app"
             data-role="child"
             data-child-id="">
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Explicit parent-only SPA shortcode
     */
    public function render_app_parent($atts) {
        return $this->render_app($atts);
    }
    
    /**
     * Explicit child-only SPA shortcode (with optional child id)
     */
    public function render_app_child($atts) {
        return $this->render_app($atts);
    }

    /**
     * Ensure SPA assets are enqueued/localized with correct role/child_id
     */
    private function enqueue_spa_with_config($role, $child_id) {
        // Enqueue SPA assets (prefer Vite manifest with hashed filenames).
        $assets = $this->get_spa_assets();
        $css_files = isset($assets['css']) && is_array($assets['css']) ? $assets['css'] : array();
        $js_file = isset($assets['js']) ? $assets['js'] : '';
        $build_ver = 0;

        // Enqueue styles (may be multiple files)
        $i = 0;
        foreach ($css_files as $css_rel) {
            $i++;
            $css_path = RODINNE_ULOHY_PLUGIN_DIR . ltrim($css_rel, '/');
            $css_ver = file_exists($css_path) ? filemtime($css_path) : RODINNE_ULOHY_VERSION;
            if (is_numeric($css_ver)) $build_ver = max($build_ver, intval($css_ver));
            $handle = $i === 1 ? 'rodinne-ulohy-spa' : ('rodinne-ulohy-spa-css-' . $i);
            wp_enqueue_style(
                $handle,
                RODINNE_ULOHY_PLUGIN_URL . ltrim($css_rel, '/'),
                array(),
                $css_ver
            );
        }

        // Enqueue script
        $js_ver = RODINNE_ULOHY_VERSION;
        if (!empty($js_file)) {
            $js_path  = RODINNE_ULOHY_PLUGIN_DIR . ltrim($js_file, '/');
            $js_ver  = file_exists($js_path) ? filemtime($js_path) : RODINNE_ULOHY_VERSION;
            if (is_numeric($js_ver)) $build_ver = max($build_ver, intval($js_ver));
            wp_enqueue_script(
                'rodinne-ulohy-spa',
                RODINNE_ULOHY_PLUGIN_URL . ltrim($js_file, '/'),
                array(),
                $js_ver,
                true
            );
        }
        wp_script_add_data('rodinne-ulohy-spa', 'type', 'module');

        // Prepare children list for SPA (scope to current WP user when logged in)
        $owner_user_id = is_user_logged_in()
            ? Rodinne_Ulohy_Database::resolve_owner_user_id_for_wp_user(get_current_user_id())
            : 0;
        $children = $owner_user_id ? Rodinne_Ulohy_Database::get_children('', $owner_user_id) : array();
        $safe_children = array();
        foreach ($children as $child) {
            $safe_children[] = array(
                'id' => intval($child->id),
                'name' => $child->name,
                'avatar_url' => isset($child->avatar_url) ? $child->avatar_url : '',
                'color' => isset($child->color) ? $child->color : '',
                'created_at' => isset($child->created_at) ? $child->created_at : '',
            );
        }

        // Issue (and reuse) bearer token for any logged-in WP user so Vue can talk only via REST API.
        $api_token = '';
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $t_key = 'rodinne_ulohy_api_token_user_' . $uid;
            $api_token = get_transient($t_key);
            if (empty($api_token)) {
                $issued = Rodinne_Ulohy_Database::create_api_token('wp_user', $uid, 60 * 60 * 24 * 30);
                $api_token = $issued['token'];
                set_transient($t_key, $api_token, 60 * 60 * 24 * 30);
            }
        }

        $google_client_id = '';
        if (defined('RODINNE_ULOHY_GOOGLE_CLIENT_ID')) {
            $google_client_id = strval(constant('RODINNE_ULOHY_GOOGLE_CLIENT_ID'));
        }
        if ($google_client_id === '') {
            $google_client_id = strval(get_option('rodinne_ulohy_google_client_id', ''));
        }
        if ($google_client_id === '') {
            $google_client_id = strval(get_option('rodinne_ulohy_google_client_ids', ''));
        }
        if ($google_client_id !== '') {
            $google_parts = preg_split('/[\s,]+/', $google_client_id);
            $google_client_id = isset($google_parts[0]) ? trim(strval($google_parts[0])) : '';
        }

        wp_localize_script('rodinne-ulohy-spa', 'rodinneUlohyApp', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiBaseUrl' => esc_url_raw(rest_url('rodinne-ulohy/v1')),
            'nonce' => wp_create_nonce('rodinne_ulohy_nonce'),
            'frontendNonce' => wp_create_nonce('rodinne_ulohy_frontend_nonce'),
            'pluginUrl' => RODINNE_ULOHY_PLUGIN_URL,
            'appVersion' => RODINNE_ULOHY_VERSION,
            // Build version hint (filemtime of current bundled assets)
            'buildVersion' => $build_ver ? $build_ver : (is_numeric($js_ver) ? intval($js_ver) : 0),
            // Unified app: role is derived from token, not shortcode.
            'isParent' => false,
            'currentUserId' => get_current_user_id(),
            'children' => $safe_children,
            'role' => 'child',
            'childId' => 0,
            'forceChild' => false,
            'weekendMultiplier' => Rodinne_Ulohy_Database::get_weekend_penalty_multiplier(),
            'apiToken' => $api_token,
            'googleClientId' => $google_client_id,
        ));
    }
    
    /**
     * Update task status via AJAX
     */
    public function update_task_status() {
        check_ajax_referer('rodinne_ulohy_frontend_nonce', 'nonce');
        
        $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'todo';
        
        if (!$assignment_id) {
            wp_send_json_error(array('message' => __('Neplatné ID', 'rodinne-ulohy')));
        }
        
        if (!in_array($status, array('todo', 'completed'))) {
            wp_send_json_error(array('message' => __('Neplatný stav', 'rodinne-ulohy')));
        }
        
        // Get assignment details before updating
        global $wpdb;
        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, t.rating, t.task_category, t.task_type, t.name as task_name
            FROM {$wpdb->prefix}rodinne_ulohy_assignments a
            INNER JOIN {$wpdb->prefix}rodinne_ulohy_tasks t ON a.task_id = t.id
            WHERE a.id = %d",
            $assignment_id
        ));
        
        if (!$assignment) {
            wp_send_json_error(array('message' => __('Priradenie nebolo nájdené', 'rodinne-ulohy')));
        }
        
        $old_status = $assignment->status;
        $result = Rodinne_Ulohy_Database::update_assignment_status($assignment_id, $status);
        
        if ($result !== false) {
            // Handle points: only if status changed
            $points_added = 0;
            $points_message = '';
            
            if ($old_status !== $status) {
                $week_start = $assignment->week_start;
                $rating = isset($assignment->rating) && $assignment->rating !== null ? intval($assignment->rating) : 0;
                $task_category = isset($assignment->task_category) ? $assignment->task_category : 'povinne';
                $task_type = isset($assignment->task_type) ? $assignment->task_type : 'daily';
                
                if ($status === 'completed') {
                    // Check if points were already added for this task today/week
                    $already_added = Rodinne_Ulohy_Database::points_already_added($assignment_id, $task_type);
                    
                    if (!$already_added && $rating > 0) {
                        // Task completed: add points (for both povinne and dobrovolne)
                        $history_id = Rodinne_Ulohy_Database::add_points(
                            $assignment->child_id,
                            $rating,
                            $week_start,
                            $assignment->task_id,
                            $assignment_id,
                            sprintf(__('Splnená úloha: %s', 'rodinne-ulohy'), $assignment->task_name),
                            'task'
                        );
                        if ($history_id) {
                            $points_added = $rating;
                            $points_message = '+' . $rating;
                        }
                    } elseif ($already_added) {
                        // Points already added, don't add again
                        $points_message = __('Body už boli pripočítané', 'rodinne-ulohy');
                    }
                } elseif ($old_status === 'completed' && $status !== 'completed') {
                    // Task was completed but now is not: check if we need to reverse points
                    $last_points = Rodinne_Ulohy_Database::get_last_points_for_assignment($assignment_id);
                    
                    if ($last_points && $last_points->points > 0) {
                        // Check if this reversal is for today (daily) or this week (weekly)
                        $can_reverse = false;
                        if ($task_type === 'daily') {
                            // For daily tasks, only reverse if points were added today
                            $today = current_time('Y-m-d');
                            if (date('Y-m-d', strtotime($last_points->created_at)) === $today) {
                                $can_reverse = true;
                            }
                        } else {
                            // For weekly tasks, only reverse if points were added this week
                            if ($last_points->week_start === $week_start) {
                                $can_reverse = true;
                            }
                        }
                        
                        if ($can_reverse) {
                            // Reverse the last positive points transaction
                            $history_id = Rodinne_Ulohy_Database::add_points(
                                $assignment->child_id,
                                -$last_points->points,
                                $week_start,
                                $assignment->task_id,
                                $assignment_id,
                                sprintf(__('Zrušená úloha: %s', 'rodinne-ulohy'), $assignment->task_name),
                                'task'
                            );
                            if ($history_id) {
                                $points_added = -$last_points->points;
                                $points_message = '-' . $last_points->points;
                            }
                        }
                    }
                } elseif ($status !== 'completed' && $task_category === 'povinne') {
                    // Povinná úloha not completed - show potential loss (but don't deduct yet)
                    if ($rating > 0) {
                        $points_message = '-' . $rating;
                    }
                }
            }
            
            // Get all assignments for this child to check if all are completed
            if ($assignment) {
                $all_assignments = Rodinne_Ulohy_Database::get_child_assignments($assignment->child_id, $assignment->week_start);
                $total = count($all_assignments);
                $completed = 0;
                
                foreach ($all_assignments as $a) {
                    if ($a->status === 'completed') {
                        $completed++;
                    }
                }
                
                // Get updated points balance & today total
                $points_balance = Rodinne_Ulohy_Database::get_points_balance($assignment->child_id);
                $points_today_total = Rodinne_Ulohy_Database::get_today_points_total($assignment->child_id);
                
                wp_send_json_success(array(
                    'message' => __('Stav bol aktualizovaný', 'rodinne-ulohy'),
                    'all_completed' => $completed === $total && $total > 0,
                    'points_balance' => intval($points_balance->balance),
                    'points_today' => intval($points_today_total),
                    'points_added' => $points_added,
                    'points_message' => $points_message
                ));
            } else {
                wp_send_json_success(array('message' => __('Stav bol aktualizovaný', 'rodinne-ulohy')));
            }
        } else {
            wp_send_json_error(array('message' => __('Chyba pri aktualizácii', 'rodinne-ulohy')));
        }
    }
    
    /**
     * Save child avatar via AJAX
     */
    public function save_child_avatar() {
        check_ajax_referer('rodinne_ulohy_frontend_nonce', 'nonce');
        
        $child_id = isset($_POST['child_id']) ? intval($_POST['child_id']) : 0;
        $avatar_url = isset($_POST['avatar_url']) ? esc_url_raw($_POST['avatar_url']) : '';
        
        if (!$child_id) {
            wp_send_json_error(array('message' => __('Neplatné ID dieťaťa', 'rodinne-ulohy')));
        }
        
        // Get existing child data
        $child = Rodinne_Ulohy_Database::get_child($child_id);
        if (!$child) {
            wp_send_json_error(array('message' => __('Dieťa nebolo nájdené', 'rodinne-ulohy')));
        }
        
        // Update with existing data plus new avatar
        $result = Rodinne_Ulohy_Database::save_child(array(
            'id' => $child_id,
            'name' => $child->name,
            'email' => $child->email ?? '',
            'avatar_url' => $avatar_url,
            'color' => $child->color ?? '#4CAF50'
        ));
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Avatar bol uložený', 'rodinne-ulohy'),
                'avatar_url' => $avatar_url
            ));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri ukladaní avatara', 'rodinne-ulohy')));
        }
    }

    /**
     * Upload child avatar (file upload) and return URL.
     */
    public function upload_child_avatar() {
        check_ajax_referer('rodinne_ulohy_frontend_nonce', 'nonce');

        if (empty($_FILES['avatar'])) {
            wp_send_json_error(array('message' => __('Chýba súbor', 'rodinne-ulohy')));
        }

        $file = $_FILES['avatar'];
        // Allow only images
        $allowed = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($file['type'], $allowed)) {
            wp_send_json_error(array('message' => __('Nepodporovaný typ súboru', 'rodinne-ulohy')));
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $upload = wp_handle_upload($file, array('test_form' => false));
        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
        }

        $url = $upload['url'];
        wp_send_json_success(array('url' => esc_url_raw($url)));
    }

    /**
     * Enable/disable 4-digit PIN for child access.
     */
    public function set_child_pin() {
        check_ajax_referer('rodinne_ulohy_frontend_nonce', 'nonce');

        $child_id = isset($_POST['child_id']) ? intval($_POST['child_id']) : 0;
        $pin_enabled = isset($_POST['pin_enabled']) ? intval($_POST['pin_enabled']) : 0;
        $pin = isset($_POST['pin']) ? sanitize_text_field($_POST['pin']) : '';

        if (!$child_id) {
            wp_send_json_error(array('message' => __('Neplatné ID dieťaťa', 'rodinne-ulohy')));
        }

        if ($pin_enabled) {
            if (!preg_match('/^\d{4}$/', $pin)) {
                wp_send_json_error(array('message' => __('PIN musí byť 4 číslice', 'rodinne-ulohy')));
            }
            $hashed = wp_hash_password($pin);
        } else {
            // prázdny string = PIN vypnutý
            $hashed = '';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_children';
        $updated = $wpdb->update(
            $table,
            array('password' => $hashed),
            array('id' => $child_id),
            array('%s'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => __('Chyba pri ukladaní PINu', 'rodinne-ulohy')));
        }

        wp_send_json_success(array(
            'has_pin' => $pin_enabled ? true : false
        ));
    }

    /**
     * Set child color (accent) - frontend (child) allowed.
     */
    public function set_child_color() {
        check_ajax_referer('rodinne_ulohy_frontend_nonce', 'nonce');

        $child_id = isset($_POST['child_id']) ? intval($_POST['child_id']) : 0;
        $color = isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : '';

        if (!$child_id || !$color) {
            wp_send_json_error(array('message' => __('Neplatné dáta', 'rodinne-ulohy')));
        }

        $child = Rodinne_Ulohy_Database::get_child($child_id);
        if (!$child) {
            wp_send_json_error(array('message' => __('Dieťa nebolo nájdené', 'rodinne-ulohy')));
        }

        // Preserve existing fields, update color
        $result = Rodinne_Ulohy_Database::save_child(array(
            'id' => $child_id,
            'name' => $child->name,
            'email' => $child->email ?? '',
            'password' => $child->password ?? '',
            'avatar_url' => $child->avatar_url ?? '',
            'color' => $color,
        ));

        if ($result === false) {
            wp_send_json_error(array('message' => __('Chyba pri ukladaní farby', 'rodinne-ulohy')));
        }

        wp_send_json_success(array('color' => $color));
    }
}

