<?php
/**
 * AJAX handlers for ekidio plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rodinne_Ulohy_Ajax {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Children AJAX
        add_action('wp_ajax_rodinne_ulohy_save_child', array($this, 'save_child'));
        add_action('wp_ajax_rodinne_ulohy_delete_child', array($this, 'delete_child'));
        add_action('wp_ajax_rodinne_ulohy_get_child', array($this, 'get_child'));
        
        // Packages AJAX
        add_action('wp_ajax_rodinne_ulohy_save_package', array($this, 'save_package'));
        add_action('wp_ajax_rodinne_ulohy_delete_package', array($this, 'delete_package'));
        add_action('wp_ajax_rodinne_ulohy_get_package', array($this, 'get_package'));
        add_action('wp_ajax_rodinne_ulohy_save_package_children', array($this, 'save_package_children'));
        
        // Tasks AJAX
        add_action('wp_ajax_rodinne_ulohy_save_task', array($this, 'save_task'));
        add_action('wp_ajax_rodinne_ulohy_delete_task', array($this, 'delete_task'));
        add_action('wp_ajax_rodinne_ulohy_get_task', array($this, 'get_task'));
        add_action('wp_ajax_rodinne_ulohy_update_task_rating', array($this, 'update_task_rating'));
        add_action('wp_ajax_rodinne_ulohy_update_task_type', array($this, 'update_task_type'));
        add_action('wp_ajax_rodinne_ulohy_update_task_days', array($this, 'update_task_days'));
        add_action('wp_ajax_rodinne_ulohy_update_task_field', array($this, 'update_task_field'));
        add_action('wp_ajax_rodinne_ulohy_save_task_relations', array($this, 'save_task_relations'));
        add_action('wp_ajax_rodinne_ulohy_get_tasks', array($this, 'get_tasks_list'));
        add_action('wp_ajax_rodinne_ulohy_get_children', array($this, 'get_children_list'));
        add_action('wp_ajax_rodinne_ulohy_add_child_to_task', array($this, 'add_child_to_task'));
        add_action('wp_ajax_rodinne_ulohy_remove_child_from_task', array($this, 'remove_child_from_task'));
        
        // Assignments AJAX
        add_action('wp_ajax_rodinne_ulohy_update_assignment_status', array($this, 'update_assignment_status'));
        add_action('wp_ajax_rodinne_ulohy_regenerate_week', array($this, 'regenerate_week'));
        add_action('wp_ajax_rodinne_ulohy_manual_reset_previous_day', array($this, 'manual_reset_previous_day'));
        
        // Points AJAX
        add_action('wp_ajax_rodinne_ulohy_add_points', array($this, 'add_points'));
        add_action('wp_ajax_rodinne_ulohy_deduct_points', array($this, 'deduct_points'));
        add_action('wp_ajax_rodinne_ulohy_delete_points_entry', array($this, 'delete_points_entry'));
        add_action('wp_ajax_rodinne_ulohy_save_weekend_multiplier', array($this, 'save_weekend_multiplier'));
        add_action('wp_ajax_rodinne_ulohy_points_overview', array($this, 'points_overview'));
        
        // Rewards AJAX (frontend)
        add_action('wp_ajax_rodinne_ulohy_purchase_reward', array($this, 'purchase_reward'));
        add_action('wp_ajax_nopriv_rodinne_ulohy_purchase_reward', array($this, 'purchase_reward'));
        add_action('wp_ajax_rodinne_ulohy_child_overview', array($this, 'child_overview'));
        add_action('wp_ajax_nopriv_rodinne_ulohy_child_overview', array($this, 'child_overview'));
        add_action('wp_ajax_rodinne_ulohy_move_task_to_package', array($this, 'move_task_to_package'));
        
        // Admin rewards AJAX
        add_action('wp_ajax_rodinne_ulohy_mark_reward_used', array($this, 'mark_reward_used'));
        add_action('wp_ajax_rodinne_ulohy_get_rewards', array($this, 'get_rewards'));
        add_action('wp_ajax_rodinne_ulohy_save_reward', array($this, 'save_reward'));
        add_action('wp_ajax_rodinne_ulohy_delete_reward', array($this, 'delete_reward'));

        // Overview AJAX (SPA parent)
        add_action('wp_ajax_rodinne_ulohy_overview', array($this, 'overview'));
    }

    /**
     * List tasks for SPA (parents)
     */
    public function get_tasks_list() {
        $this->verify_nonce();

        $tasks = Rodinne_Ulohy_Database::get_tasks();
        $result = array();

        foreach ($tasks as $task) {
            $children = Rodinne_Ulohy_Database::get_task_children($task->id);
            $result[] = array(
                'id' => intval($task->id),
                'name' => $task->name,
                'description' => isset($task->description) ? $task->description : '',
                'task_category' => isset($task->task_category) ? $task->task_category : 'povinne',
                'task_type' => isset($task->task_type) ? $task->task_type : 'weekly',
                'days_of_week' => isset($task->days_of_week) ? $task->days_of_week : '',
                'rotation_enabled' => isset($task->rotation_enabled) ? intval($task->rotation_enabled) : 0,
                'shared_task' => isset($task->shared_task) ? intval($task->shared_task) : 0,
                'rating' => isset($task->rating) ? intval($task->rating) : 0,
                'package_id' => isset($task->package_id) ? intval($task->package_id) : null,
                'children' => array_map(function($c) {
                    return array(
                        'id' => intval($c->id),
                        'name' => $c->name,
                        'avatar_url' => isset($c->avatar_url) ? $c->avatar_url : '',
                        'color' => isset($c->color) ? $c->color : '',
                    );
                }, $children),
            );
        }

        wp_send_json_success($result);
    }
    
    private function verify_nonce() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rodinne_ulohy_nonce')) {
            wp_send_json_error(array('message' => __('Neplatný bezpečnostný token', 'rodinne-ulohy')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Nemáte oprávnenie', 'rodinne-ulohy')));
        }
    }
    
    // Children AJAX handlers
    public function save_child() {
        $this->verify_nonce();
        
        $data = array(
            'id' => isset($_POST['id']) || isset($_POST['child_id']) ? intval($_POST['id'] ?? $_POST['child_id'] ?? 0) : 0,
            'name' => sanitize_text_field($_POST['name'] ?? $_POST['child_name'] ?? ''),
            'email' => isset($_POST['email']) || isset($_POST['child_email']) ? sanitize_email($_POST['email'] ?? $_POST['child_email'] ?? '') : '',
            'password' => isset($_POST['password']) || isset($_POST['child_password']) ? ($_POST['password'] ?? $_POST['child_password'] ?? '') : '',
            'avatar_url' => esc_url_raw($_POST['avatar_url'] ?? $_POST['child_avatar_url'] ?? ''),
            'color' => isset($_POST['color']) || isset($_POST['child_color']) ? sanitize_hex_color($_POST['color'] ?? $_POST['child_color'] ?? '#4CAF50') : '#4CAF50'
        );
        
        if (empty($data['name'])) {
            wp_send_json_error(array('message' => __('Meno je povinné', 'rodinne-ulohy')));
        }
        
        $result = Rodinne_Ulohy_Database::save_child($data);
        
        if ($result !== false) {
            global $wpdb;
            $child_id = isset($data['id']) && $data['id'] ? $data['id'] : $wpdb->insert_id;
            wp_send_json_success(array(
                'message' => __('Dieťa bolo uložené', 'rodinne-ulohy'),
                'id' => $child_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri ukladaní', 'rodinne-ulohy')));
        }
    }
    
    public function delete_child() {
        $this->verify_nonce();
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(array('message' => __('Neplatné ID', 'rodinne-ulohy')));
        }
        
        $result = Rodinne_Ulohy_Database::delete_child($id);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Dieťa bolo odstránené', 'rodinne-ulohy')));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri odstraňovaní', 'rodinne-ulohy')));
        }
    }
    
    public function get_child() {
        $this->verify_nonce();
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(array('message' => __('Neplatné ID', 'rodinne-ulohy')));
        }
        
        $child = Rodinne_Ulohy_Database::get_child($id);
        
        if ($child) {
            wp_send_json_success($child);
        } else {
            wp_send_json_error(array('message' => __('Dieťa nebolo nájdené', 'rodinne-ulohy')));
        }
    }
    
    // Package AJAX handlers
    public function save_package() {
        $this->verify_nonce();
        
        $data = array(
            'id' => isset($_POST['id']) ? intval($_POST['id']) : 0,
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? '')
        );
        
        if (empty($data['name'])) {
            wp_send_json_error(array('message' => __('Názov je povinný', 'rodinne-ulohy')));
        }
        
        $result = Rodinne_Ulohy_Database::save_package($data);
        
        if ($result !== false) {
            global $wpdb;
            $package_id = isset($data['id']) && $data['id'] ? $data['id'] : $wpdb->insert_id;
            
            // Save package children (always save, even if empty array)
            $child_ids = array();
            if (isset($_POST['child_ids']) && is_array($_POST['child_ids'])) {
                $child_ids = array_filter(array_map('intval', $_POST['child_ids']));
            }
            Rodinne_Ulohy_Database::save_package_children($package_id, $child_ids);
            
            // Save package tasks (always save, even if empty array)
            $task_ids = array();
            if (isset($_POST['task_ids']) && is_array($_POST['task_ids'])) {
                $task_ids = array_filter(array_map('intval', $_POST['task_ids']));
            }
            Rodinne_Ulohy_Database::save_package_tasks($package_id, $task_ids);
            
            wp_send_json_success(array(
                'message' => __('Balíček bol uložený', 'rodinne-ulohy'),
                'id' => $package_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri ukladaní', 'rodinne-ulohy')));
        }
    }
    
    public function delete_package() {
        $this->verify_nonce();
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(array('message' => __('Neplatné ID', 'rodinne-ulohy')));
        }
        
        $result = Rodinne_Ulohy_Database::delete_package($id);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Balíček bol odstránený', 'rodinne-ulohy')));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri odstraňovaní', 'rodinne-ulohy')));
        }
    }
    
    public function get_package() {
        $this->verify_nonce();
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(array('message' => __('Neplatné ID', 'rodinne-ulohy')));
        }
        
        $package = Rodinne_Ulohy_Database::get_package($id);
        if ($package) {
            $package->children = Rodinne_Ulohy_Database::get_package_children($id);
            $package->tasks = Rodinne_Ulohy_Database::get_tasks($id);
            wp_send_json_success($package);
        } else {
            wp_send_json_error(array('message' => __('Balíček nebol nájdený', 'rodinne-ulohy')));
        }
    }
    
    public function save_package_children() {
        $this->verify_nonce();
        
        $package_id = intval($_POST['package_id'] ?? 0);
        $child_ids = isset($_POST['child_ids']) && is_array($_POST['child_ids']) 
            ? array_map('intval', $_POST['child_ids']) 
            : array();
        
        if (!$package_id) {
            wp_send_json_error(array('message' => __('Neplatné ID balíčka', 'rodinne-ulohy')));
        }
        
        Rodinne_Ulohy_Database::save_package_children($package_id, $child_ids);
        wp_send_json_success(array('message' => __('Priradenia boli uložené', 'rodinne-ulohy')));
    }
    
    // Task AJAX handlers
    public function save_task() {
        $this->verify_nonce();
        
        // Process days_of_week
        $days_of_week = '';
        if (isset($_POST['days_of_week']) && !empty($_POST['days_of_week'])) {
            $days_of_week = sanitize_text_field($_POST['days_of_week']);
        } elseif (isset($_POST['task_type'])) {
            // Fallback: convert old task_type to days_of_week for backward compatibility
            $task_type = sanitize_text_field($_POST['task_type']);
            if ($task_type === 'daily') {
                $days_of_week = '1,2,3,4,5';
            } elseif ($task_type === 'weekend') {
                $days_of_week = '6';
            } elseif ($task_type === 'weekly') {
                $days_of_week = '1,2,3,4,5,6,0';
            }
        }

        // We no longer store/use task_type = 'weekend'. Saturday-only tasks are represented by days_of_week='6'.
        $task_type_raw = sanitize_text_field($_POST['task_type'] ?? 'daily');
        if ($task_type_raw === 'weekend' && empty($days_of_week)) {
            $days_of_week = '6';
        }
        // Any task with explicit days behaves as "daily" for points + dedupe.
        $task_type = !empty($days_of_week) ? 'daily' : ($task_type_raw === 'weekly' ? 'weekly' : 'daily');
        
        $data = array(
            'id' => isset($_POST['id']) ? intval($_POST['id']) : 0,
            'package_id' => !empty($_POST['package_id']) ? intval($_POST['package_id']) : null,
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'task_type' => $task_type,
            'days_of_week' => $days_of_week,
            'task_category' => sanitize_text_field($_POST['task_category'] ?? 'povinne'),
            'rotation_enabled' => isset($_POST['rotation_enabled']) ? 1 : 0,
            'shared_task' => isset($_POST['shared_task']) ? 1 : 0,
            'estimated_time' => !empty($_POST['estimated_time']) ? intval($_POST['estimated_time']) : null,
            'rating' => isset($_POST['rating']) && $_POST['rating'] !== '' ? intval($_POST['rating']) : null,
            'icon' => sanitize_text_field($_POST['icon'] ?? ''),
        );
        
        if (empty($data['name'])) {
            wp_send_json_error(array('message' => __('Názov úlohy je povinný', 'rodinne-ulohy')));
        }
        
        $result = Rodinne_Ulohy_Database::save_task($data);
        
        if ($result !== false) {
            global $wpdb;
            $task_id = isset($data['id']) && $data['id'] ? $data['id'] : $wpdb->insert_id;
            
            // Save task children if provided (for standalone tasks only)
            if (empty($data['package_id'])) {
                // Only save children for standalone tasks (not in package)
                $child_ids = array();
                if (isset($_POST['assigned_children'])) {
                    if (is_array($_POST['assigned_children'])) {
                        // Filter out empty values and convert to integers
                        $child_ids = array_filter(array_map('intval', $_POST['assigned_children']));
                    } elseif (!empty($_POST['assigned_children'])) {
                        // Single value
                        $child_ids = array(intval($_POST['assigned_children']));
                    }
                }
                // Always save (even if empty array - removes all assignments)
                Rodinne_Ulohy_Database::save_task_children($task_id, $child_ids);
            } else {
                // If task is in package, remove any assigned children (they use package children)
                Rodinne_Ulohy_Database::save_task_children($task_id, array());
            }
            
            wp_send_json_success(array(
                'message' => __('Úloha bola uložená', 'rodinne-ulohy'),
                'id' => $task_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri ukladaní', 'rodinne-ulohy')));
        }
    }
    
    public function delete_task() {
        $this->verify_nonce();
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(array('message' => __('Neplatné ID', 'rodinne-ulohy')));
        }
        
        $result = Rodinne_Ulohy_Database::delete_task($id);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Úloha bola odstránená', 'rodinne-ulohy')));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri odstraňovaní', 'rodinne-ulohy')));
        }
    }
    
    public function get_task() {
        $this->verify_nonce();
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(array('message' => __('Neplatné ID', 'rodinne-ulohy')));
        }
        
        $task = Rodinne_Ulohy_Database::get_task($id);
        
        if ($task) {
            // Get assigned children
            $task->children = Rodinne_Ulohy_Database::get_task_children($id);
            
            wp_send_json_success($task);
        } else {
            wp_send_json_error(array('message' => __('Úloha nebola nájdená', 'rodinne-ulohy')));
        }
    }
    
    // Assignment AJAX handlers
    public function update_assignment_status() {
        $this->verify_nonce();
        
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? 'todo');
        
        if (!$assignment_id) {
            wp_send_json_error(array('message' => __('Neplatné ID', 'rodinne-ulohy')));
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
                        // Task completed: add points
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
                } elseif ($status !== 'completed' && $task_category === 'povinne' && $week_start === Rodinne_Ulohy_Database::get_current_week_start()) {
                    // Povinná úloha not completed - show potential loss (but don't deduct yet)
                    if ($rating > 0) {
                        $points_message = '-' . $rating;
                    }
                }
            }
            
            // Get updated points balance
            $points_balance = Rodinne_Ulohy_Database::get_points_balance($assignment->child_id);
            
            wp_send_json_success(array(
                'message' => __('Stav bol aktualizovaný', 'rodinne-ulohy'),
                'points_balance' => intval($points_balance->balance),
                'points_added' => $points_added,
                'points_message' => $points_message
            ));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri aktualizácii', 'rodinne-ulohy')));
        }
    }
    
    public function add_points() {
        $this->verify_nonce();
        
        $child_id = intval($_POST['child_id'] ?? 0);
        $points = intval($_POST['points'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? __('Manuálne pripočítanie', 'rodinne-ulohy'));
        
        if (!$child_id || $points <= 0) {
            wp_send_json_error(array('message' => __('Neplatné údaje', 'rodinne-ulohy')));
        }
        
        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $result = Rodinne_Ulohy_Database::add_points(
            $child_id,
            $points,
            $week_start,
            null,
            null,
            $reason,
            'manual'
        );
        
        if ($result !== false) {
            $balance = Rodinne_Ulohy_Database::get_points_balance($child_id);
            $week_summary = Rodinne_Ulohy_Database::get_week_points_summary($child_id, $week_start);
            
            wp_send_json_success(array(
                'message' => __('Body boli pripočítané', 'rodinne-ulohy'),
                'child_id' => $child_id,
                'points_balance' => intval($balance->balance),
                'week_summary' => array(
                    'earned' => intval($week_summary->earned),
                    'lost' => intval($week_summary->lost),
                    'total' => intval($week_summary->total)
                )
            ));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri pripočítaní bodov', 'rodinne-ulohy')));
        }
    }
    
    public function deduct_points() {
        $this->verify_nonce();
        
        $child_id = intval($_POST['child_id'] ?? 0);
        $points = intval($_POST['points'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? __('Manuálne odpočítanie', 'rodinne-ulohy'));
        
        if (!$child_id || $points <= 0) {
            wp_send_json_error(array('message' => __('Neplatné údaje', 'rodinne-ulohy')));
        }
        
        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $result = Rodinne_Ulohy_Database::add_points(
            $child_id,
            -$points,
            $week_start,
            null,
            null,
            $reason,
            'manual'
        );
        
        if ($result !== false) {
            $balance = Rodinne_Ulohy_Database::get_points_balance($child_id);
            $week_summary = Rodinne_Ulohy_Database::get_week_points_summary($child_id, $week_start);
            
            wp_send_json_success(array(
                'message' => __('Body boli odpočítané', 'rodinne-ulohy'),
                'child_id' => $child_id,
                'points_balance' => intval($balance->balance),
                'week_summary' => array(
                    'earned' => intval($week_summary->earned),
                    'lost' => intval($week_summary->lost),
                    'total' => intval($week_summary->total)
                )
            ));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri odpočítaní bodov', 'rodinne-ulohy')));
        }
    }
    
    public function delete_points_entry() {
        $this->verify_nonce();
        
        $entry_id = intval($_POST['entry_id'] ?? 0);
        if (!$entry_id) {
            wp_send_json_error(array('message' => __('Neplatné ID záznamu', 'rodinne-ulohy')));
        }
        
        $result = Rodinne_Ulohy_Database::delete_points_entry($entry_id);
        if (!$result) {
            wp_send_json_error(array('message' => __('Záznam sa nepodarilo odstrániť', 'rodinne-ulohy')));
        }
        
        $child_id = $result['child_id'];
        $week_start = $result['week_start'] ?: Rodinne_Ulohy_Database::get_current_week_start();
        $balance = Rodinne_Ulohy_Database::get_points_balance($child_id);
        $week_summary = Rodinne_Ulohy_Database::get_week_points_summary($child_id, $week_start);
        
        wp_send_json_success(array(
            'message' => __('Záznam bol odstránený', 'rodinne-ulohy'),
            'entry_id' => $entry_id,
            'child_id' => $child_id,
            'points_balance' => intval($balance->balance),
            'week_summary' => array(
                'earned' => intval($week_summary->earned),
                'lost' => intval($week_summary->lost),
                'total' => intval($week_summary->total)
            )
        ));
    }
    
    public function regenerate_week() {
        $this->verify_nonce();
        
        $result = Rodinne_Ulohy_Rotation::get_instance()->regenerate_current_week();
        
        if ($result) {
            wp_send_json_success(array('message' => __('Týždeň bol regenerovaný', 'rodinne-ulohy')));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri regenerácii', 'rodinne-ulohy')));
        }
    }
    
    public function manual_reset_previous_day() {
        $this->verify_nonce();
        
        $result = Rodinne_Ulohy_Rotation::get_instance()->manual_reset_previous_day();
        
        if ($result && isset($result['success']) && $result['success']) {
            $message = sprintf(
                __('Vyčistenie dokončené: %d úloh vynulovaných, %d pokút pridaných za %s', 'rodinne-ulohy'),
                $result['reset_count'],
                $result['penalty_count'],
                $result['yesterday']
            );
            wp_send_json_success(array(
                'message' => $message,
                'reset_count' => $result['reset_count'],
                'penalty_count' => $result['penalty_count'],
                'yesterday' => $result['yesterday']
            ));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri vyčistení', 'rodinne-ulohy')));
        }
    }
    
    public function move_task_to_package() {
        $this->verify_nonce();
        
        $task_id = intval($_POST['task_id'] ?? 0);
        $package_id = !empty($_POST['package_id']) ? intval($_POST['package_id']) : null;
        
        if (!$task_id) {
            wp_send_json_error(array('message' => __('Neplatné ID úlohy', 'rodinne-ulohy')));
        }
        
        // Get task
        $task = Rodinne_Ulohy_Database::get_task($task_id);
        if (!$task) {
            wp_send_json_error(array('message' => __('Úloha nebola nájdená', 'rodinne-ulohy')));
        }
        
        // Update task package
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $result = $wpdb->update(
            $table,
            array('package_id' => $package_id),
            array('id' => $task_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Úloha bola presunutá', 'rodinne-ulohy')));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri presúvaní úlohy', 'rodinne-ulohy')));
        }
    }
    
    public function update_task_type() {
        $this->verify_nonce();
        
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $task_type = isset($_POST['task_type']) ? sanitize_text_field($_POST['task_type']) : 'daily';
        
        if (!$task_id) {
            wp_send_json_error(array('message' => __('Neplatné ID úlohy', 'rodinne-ulohy')));
        }
        
        // Legacy 'weekend' is mapped to Saturday-only days_of_week=6 and stored as 'daily'.
        if (!in_array($task_type, array('daily', 'weekly', 'weekend'), true)) {
            wp_send_json_error(array('message' => __('Neplatný typ úlohy', 'rodinne-ulohy')));
        }

        if ($task_type === 'weekend') {
            Rodinne_Ulohy_Database::update_task_field($task_id, 'days_of_week', '6');
            $task_type = 'daily';
        }
        $result = Rodinne_Ulohy_Database::update_task_field($task_id, 'task_type', $task_type);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Typ úlohy bol aktualizovaný', 'rodinne-ulohy')));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri aktualizácii typu úlohy', 'rodinne-ulohy')));
        }
    }
    
    public function update_task_days() {
        $this->verify_nonce();
        
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $days_of_week = isset($_POST['days_of_week']) ? sanitize_text_field($_POST['days_of_week']) : '';
        
        if (!$task_id) {
            wp_send_json_error(array('message' => __('Neplatné ID úlohy', 'rodinne-ulohy')));
        }
        
        $result = Rodinne_Ulohy_Database::update_task_field($task_id, 'days_of_week', $days_of_week);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Dni úlohy boli aktualizované', 'rodinne-ulohy')));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri aktualizácii dní úlohy', 'rodinne-ulohy')));
        }
    }
    
    public function update_task_field() {
        $this->verify_nonce();
        
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $value = isset($_POST['value']) ? $_POST['value'] : '';
        
        if (!$task_id || !$field) {
            wp_send_json_error(array('message' => __('Neplatné parametre', 'rodinne-ulohy')));
        }
        
        // Sanitize value based on field
        if ($field === 'name') {
            $value = sanitize_text_field($value);
        } elseif ($field === 'description') {
            $value = sanitize_textarea_field($value);
        }
        
        $result = Rodinne_Ulohy_Database::update_task_field($task_id, $field, $value);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Úloha bola aktualizovaná', 'rodinne-ulohy')));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri aktualizácii', 'rodinne-ulohy')));
        }
    }
    
    /**
     * Save locked / excluded task relations for rotation
     */
    public function save_task_relations() {
        $this->verify_nonce();
        wp_send_json_error(array(
            'message' => __('Možnosť zomknutých/vylúčených úloh bola odstránená', 'rodinne-ulohy')
        ));
    }
    
    public function get_children_list() {
        $this->verify_nonce();
        
        $children = Rodinne_Ulohy_Database::get_children();
        $children_data = array();
        
        foreach ($children as $child) {
            $children_data[] = array(
                'id' => $child->id,
                'name' => $child->name,
                'avatar_url' => $child->avatar_url
            );
        }
        
        wp_send_json_success($children_data);
    }
    
    public function add_child_to_task() {
        $this->verify_nonce();
        
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $child_id = isset($_POST['child_id']) ? intval($_POST['child_id']) : 0;
        
        if (!$task_id || !$child_id) {
            wp_send_json_error(array('message' => __('Neplatné parametre', 'rodinne-ulohy')));
        }
        
        // Get current children
        $current_children = Rodinne_Ulohy_Database::get_task_children($task_id);
        $current_child_ids = array();
        foreach ($current_children as $child) {
            $current_child_ids[] = $child->id;
        }
        
        // Check if already assigned
        if (in_array($child_id, $current_child_ids)) {
            wp_send_json_error(array('message' => __('Dieťa je už priradené k tejto úlohe', 'rodinne-ulohy')));
        }
        
        // Add child
        $new_child_ids = $current_child_ids;
        $new_child_ids[] = $child_id;
        
        $result = Rodinne_Ulohy_Database::save_task_children($task_id, $new_child_ids);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Dieťa bolo pridané', 'rodinne-ulohy')));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri pridávaní dieťaťa', 'rodinne-ulohy')));
        }
    }
    
    public function remove_child_from_task() {
        $this->verify_nonce();
        
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $child_id = isset($_POST['child_id']) ? intval($_POST['child_id']) : 0;
        
        if (!$task_id || !$child_id) {
            wp_send_json_error(array('message' => __('Neplatné parametre', 'rodinne-ulohy')));
        }
        
        // Get current children
        $current_children = Rodinne_Ulohy_Database::get_task_children($task_id);
        $current_child_ids = array();
        foreach ($current_children as $child) {
            $current_child_ids[] = $child->id;
        }
        
        // Remove child
        $new_child_ids = array_diff($current_child_ids, array($child_id));
        $new_child_ids = array_values($new_child_ids); // Re-index array
        
        $result = Rodinne_Ulohy_Database::save_task_children($task_id, $new_child_ids);
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Dieťa bolo odstránené', 'rodinne-ulohy')));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri odstraňovaní dieťaťa', 'rodinne-ulohy')));
        }
    }
    
    public function update_task_rating() {
        $this->verify_nonce();
        
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
        
        if (!$task_id) {
            wp_send_json_error(array('message' => __('Neplatné ID úlohy', 'rodinne-ulohy')));
        }
        
        if ($rating < 0 || $rating > 10) {
            wp_send_json_error(array('message' => __('Hodnotenie musí byť medzi 0 a 10', 'rodinne-ulohy')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        
        // Check if rating column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $table LIKE 'rating'"
        ));
        
        if (empty($column_exists)) {
            // Column doesn't exist, add it
            $wpdb->query("ALTER TABLE $table ADD COLUMN rating int(11) DEFAULT NULL");
        }
        
        // Get current rating to check if update is needed
        $current_rating = $wpdb->get_var($wpdb->prepare(
            "SELECT rating FROM $table WHERE id = %d",
            $task_id
        ));
        
        // Update rating
        $result = $wpdb->update(
            $table,
            array('rating' => $rating),
            array('id' => $task_id),
            array('%d'),
            array('%d')
        );
        
        // Check for database errors
        if ($wpdb->last_error) {
            wp_send_json_error(array(
                'message' => __('Chyba databázy: ', 'rodinne-ulohy') . $wpdb->last_error
            ));
        }
        
        // Success if update returned number (0 or more) or if rating is already correct
        if ($result !== false || ($current_rating !== null && intval($current_rating) === $rating)) {
            wp_send_json_success(array(
                'message' => __('Hodnotenie bolo uložené', 'rodinne-ulohy'),
                'rating' => $rating
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Chyba pri ukladaní hodnotenia', 'rodinne-ulohy'),
                'debug' => array(
                    'task_id' => $task_id,
                    'rating' => $rating,
                    'result' => $result,
                    'last_error' => $wpdb->last_error
                )
            ));
        }
    }
    
    public function purchase_reward() {
        check_ajax_referer('rodinne_ulohy_frontend_nonce', 'nonce');
        
        $reward_id = intval($_POST['reward_id'] ?? 0);
        $child_id = intval($_POST['child_id'] ?? 0);
        
        if (!$reward_id || !$child_id) {
            wp_send_json_error(array('message' => __('Neplatné údaje', 'rodinne-ulohy')));
        }
        
        $child = Rodinne_Ulohy_Database::get_child($child_id);
        if (!$child) {
            wp_send_json_error(array('message' => __('Dieťa nebolo nájdené', 'rodinne-ulohy')));
        }
        
        $result = Rodinne_Ulohy_Database::purchase_reward($child_id, $reward_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        $balance = Rodinne_Ulohy_Database::get_points_balance($child_id);
        $points_today = Rodinne_Ulohy_Database::get_today_points_total($child_id);
        $active = Rodinne_Ulohy_Database::get_child_active_reward_purchases($child_id);
        
        $counts = array();
        if (!empty($active)) {
            foreach ($active as $purchase) {
                $rid = intval($purchase->reward_id);
                if (!isset($counts[$rid])) {
                    $counts[$rid] = 0;
                }
                $counts[$rid]++;
            }
        }
        
        wp_send_json_success(array(
            'message' => __('Odmena bola kúpená', 'rodinne-ulohy'),
            'points_balance' => intval($balance->balance),
            'points_today' => intval($points_today),
            'active_counts' => $counts
        ));
    }
    
    public function mark_reward_used() {
        $this->verify_nonce();
        
        $purchase_id = intval($_POST['purchase_id'] ?? 0);
        if (!$purchase_id) {
            wp_send_json_error(array('message' => __('Neplatné ID nákupu', 'rodinne-ulohy')));
        }
        
        $result = Rodinne_Ulohy_Database::mark_reward_used($purchase_id);
        
        if ($result === true) {
            wp_send_json_success(array(
                'message' => __('Odmena bola označená ako použitá', 'rodinne-ulohy'),
                'purchase_id' => $purchase_id
            ));
        } else {
            // Check if purchase exists and get its status
            global $wpdb;
            $table = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';
            $purchase = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $purchase_id
            ));
            
            if (!$purchase) {
                wp_send_json_error(array('message' => __('Nákup nebol nájdený', 'rodinne-ulohy')));
            } elseif ($purchase->status !== 'active') {
                wp_send_json_error(array('message' => __('Odmena už bola označená ako použitá', 'rodinne-ulohy')));
            } else {
                wp_send_json_error(array('message' => __('Chyba pri označení odmeny', 'rodinne-ulohy')));
            }
        }
    }

    /**
     * Rewards CRUD for parent SPA
     */
    public function get_rewards() {
        $this->verify_nonce();
        $rewards = Rodinne_Ulohy_Database::get_rewards();
        wp_send_json_success($rewards);
    }

    public function save_reward() {
        $this->verify_nonce();

        $data = array(
            'id' => intval($_POST['id'] ?? 0),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'details' => sanitize_text_field($_POST['details'] ?? ''),
            'icon' => sanitize_text_field($_POST['icon'] ?? ''),
            'points_cost' => intval($_POST['points_cost'] ?? 0),
        );

        if (empty($data['title'])) {
            wp_send_json_error(array('message' => __('Názov je povinný', 'rodinne-ulohy')));
        }

        $result = Rodinne_Ulohy_Database::save_reward($data);
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Odmena uložená', 'rodinne-ulohy')));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri ukladaní odmeny', 'rodinne-ulohy')));
        }
    }

    public function delete_reward() {
        $this->verify_nonce();

        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(array('message' => __('Neplatné ID odmeny', 'rodinne-ulohy')));
        }

        $result = Rodinne_Ulohy_Database::delete_reward($id);
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Odmena odstránená', 'rodinne-ulohy')));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri odstraňovaní odmeny', 'rodinne-ulohy')));
        }
    }

    /**
     * Get child overview data for SPA (tasks, points, rewards)
     */
    public function child_overview() {
        check_ajax_referer('rodinne_ulohy_frontend_nonce', 'nonce');

        $child_id = isset($_POST['child_id']) ? intval($_POST['child_id']) : 0;
        $requested_day = isset($_POST['day']) ? intval($_POST['day']) : null;
        $pin = isset($_POST['pin']) ? sanitize_text_field($_POST['pin']) : '';

        // Fallback: if no child_id provided, try first child (useful when SPA nevie ID)
        if (!$child_id) {
            $all_children = Rodinne_Ulohy_Database::get_children();
            if (!empty($all_children)) {
                $child_id = intval($all_children[0]->id);
            }
        }

        if (!$child_id) {
            wp_send_json_error(array('message' => __('Neplatné ID dieťaťa', 'rodinne-ulohy')));
        }

        $child = Rodinne_Ulohy_Database::get_child($child_id);
        if (!$child) {
            wp_send_json_error(array('message' => __('Dieťa nebolo nájdené', 'rodinne-ulohy')));
        }

        // PIN check: if password set, require correct PIN
        $enforce_pin = !current_user_can('manage_options'); // rodič môže pozerať bez PINu
        if ($enforce_pin && !empty($child->password)) {
            if (empty($pin)) {
                wp_send_json_error(array(
                    'message' => __('Vyžaduje sa PIN', 'rodinne-ulohy'),
                    'code' => 'pin_required'
                ));
            }
            if (!wp_check_password($pin, $child->password)) {
                wp_send_json_error(array(
                    'message' => __('Nesprávny PIN', 'rodinne-ulohy'),
                    'code' => 'pin_invalid'
                ));
            }
        }

        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $week_range = Rodinne_Ulohy_Database::get_week_range($week_start);
        $all_assignments = Rodinne_Ulohy_Database::get_child_assignments($child_id, $week_start);

        // Points
        $points_balance = Rodinne_Ulohy_Database::get_points_balance($child_id);
        $today_points = Rodinne_Ulohy_Database::get_today_points_total($child_id);
        $week_points = Rodinne_Ulohy_Database::get_week_points_total($child_id, $week_start);

        // Rewards
        $rewards = Rodinne_Ulohy_Database::get_rewards();
        $purchases = Rodinne_Ulohy_Database::get_child_active_reward_purchases($child_id);
        $active_reward_counts = array();
        $active_purchases_payload = array();
        foreach ($purchases as $p) {
            $rid = intval($p->reward_id);
            if (!isset($active_reward_counts[$rid])) {
                $active_reward_counts[$rid] = 0;
            }
            $active_reward_counts[$rid]++;
            
            $active_purchases_payload[] = array(
                'id' => intval($p->id),
                'reward_id' => $rid,
                'title' => isset($p->reward_title) ? $p->reward_title : '',
                'icon' => isset($p->reward_icon) ? $p->reward_icon : '',
                'points_cost' => isset($p->reward_points_cost) ? intval($p->reward_points_cost) : 0,
                'created_at' => isset($p->created_at) ? $p->created_at : ''
            );
        }

        // Filter assignments based on days/type
        $current_day = date('w'); // 0 = Sunday, 6 = Saturday (today)
        $day_to_show = is_null($requested_day) ? $current_day : max(0, min(6, $requested_day));
        $is_saturday = ($day_to_show == 6);

        $povinne_assignments = array();
        $dobrovolne_assignments = array();

        foreach ($all_assignments as $assignment) {
            $task = Rodinne_Ulohy_Database::get_task($assignment->task_id);
            if (!$task) {
                continue;
            }

            $task_category = isset($task->task_category) ? $task->task_category : 'povinne';

            $should_show = true;
            $days_of_week = isset($task->days_of_week) && !empty($task->days_of_week) ? $task->days_of_week : '';

            if (!empty($days_of_week)) {
                $task_days = array_map('intval', explode(',', $days_of_week));
                if (!in_array(intval($day_to_show), $task_days)) {
                    $should_show = false;
                }
            } else {
                // No days_of_week configured => show every day (legacy behavior).
                $should_show = true;
            }

            if ($should_show) {
                $assignment->task_rating = isset($assignment->task_rating) && $assignment->task_rating !== null ? intval($assignment->task_rating) : 0;
                $assignment->task_category = isset($assignment->task_category) ? $assignment->task_category : 'povinne';
                $assignment->task_type = isset($task->task_type) ? $task->task_type : 'weekly';
                $assignment->rotation_enabled = isset($task->rotation_enabled) ? intval($task->rotation_enabled) : 0;
                $assignment->description = isset($task->description) ? $task->description : '';
                $assignment->days_of_week = $days_of_week;

                if ($assignment->task_category === 'dobrovolne') {
                    $dobrovolne_assignments[] = $assignment;
                } else {
                    $povinne_assignments[] = $assignment;
                }
            }
        }

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

        wp_send_json_success(array(
            'child' => array(
                'id' => intval($child->id),
                'name' => $child->name,
                'avatar_url' => isset($child->avatar_url) ? $child->avatar_url : '',
                'color' => isset($child->color) ? $child->color : '',
            ),
            'has_pin' => !empty($child->password),
            'week_range' => $week_range,
            'day' => intval($day_to_show),
            'points_balance' => intval($points_balance->balance),
            'points_today' => intval($today_points),
            'points_week' => intval($week_points),
            'tasks' => array(
                'povinne' => array(
                    'items' => $povinne_assignments,
                    'completed' => $povinne_completed,
                    'total' => $povinne_total,
                ),
                'dobrovolne' => array(
                    'items' => $dobrovolne_assignments,
                    'completed' => $dobrovolne_completed,
                    'total' => $dobrovolne_total,
                ),
            ),
            'rewards' => array(
                'items' => $rewards,
                'active_counts' => $active_reward_counts,
                'active_purchases' => $active_purchases_payload
            ),
        ));
    }
    
    public function save_weekend_multiplier() {
        check_ajax_referer('rodinne_ulohy_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Nemáte oprávnenie', 'rodinne-ulohy')));
        }
        
        $multiplier = isset($_POST['multiplier']) ? floatval($_POST['multiplier']) : 3;
        
        if ($multiplier < 1) {
            wp_send_json_error(array('message' => __('Multiplikátor musí byť aspoň 1', 'rodinne-ulohy')));
        }
        
        $result = Rodinne_Ulohy_Database::save_weekend_penalty_multiplier($multiplier);
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Multiplikátor bol uložený', 'rodinne-ulohy'),
                'multiplier' => $multiplier
            ));
        } else {
            wp_send_json_error(array('message' => __('Chyba pri ukladaní multiplikátora', 'rodinne-ulohy')));
        }
    }

    /**
     * Points overview for parent SPA
     */
    public function points_overview() {
        $this->verify_nonce();

        $child_id = isset($_POST['child_id']) ? intval($_POST['child_id']) : 0;

        // Fallback to first child if not provided
        if (!$child_id) {
            $children = Rodinne_Ulohy_Database::get_children();
            if (!empty($children)) {
                $child_id = intval($children[0]->id);
            }
        }

        if (!$child_id) {
            wp_send_json_error(array('message' => __('Neplatné ID dieťaťa', 'rodinne-ulohy')));
        }

        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $week_range = Rodinne_Ulohy_Database::get_week_range($week_start);
        $points_balance = Rodinne_Ulohy_Database::get_points_balance($child_id);
        $points_today = Rodinne_Ulohy_Database::get_today_points_total($child_id);
        $points_week = Rodinne_Ulohy_Database::get_week_points_total($child_id, $week_start);
        $week_summary = Rodinne_Ulohy_Database::get_week_points_summary($child_id, $week_start);
        $history = Rodinne_Ulohy_Database::get_points_history($child_id, $week_start);

        wp_send_json_success(array(
            'child_id' => $child_id,
            'week_range' => $week_range,
            'points_balance' => intval($points_balance->balance),
            'points_today' => intval($points_today),
            'points_week' => intval($points_week),
            'week_summary' => $week_summary,
            'history' => $history
        ));
    }

    /**
     * Overview for parent SPA (weekly assignments grouped by child)
     */
    public function overview() {
        $this->verify_nonce();

        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $week_range = Rodinne_Ulohy_Database::get_week_range($week_start);
        $assignments = Rodinne_Ulohy_Database::get_week_assignments($week_start);

        $grouped = array();

        foreach ($assignments as $a) {
            $child_id = intval($a->child_id);
            if (!isset($grouped[$child_id])) {
                $grouped[$child_id] = array(
                    'child' => array(
                        'id' => $child_id,
                        'name' => $a->child_name,
                        'avatar' => $a->child_avatar
                    ),
                    'tasks' => array(
                        'povinne' => array(),
                        'dobrovolne' => array()
                    )
                );
            }

            $cat = isset($a->task_category) && $a->task_category === 'dobrovolne' ? 'dobrovolne' : 'povinne';
            $grouped[$child_id]['tasks'][$cat][] = array(
                'id' => intval($a->id),
                'task_id' => intval($a->task_id),
                'name' => $a->task_name,
                'task_type' => isset($a->task_type) ? $a->task_type : 'weekly',
                'task_rating' => isset($a->task_rating) ? intval($a->task_rating) : 0,
                'status' => $a->status
            );
        }

        // Compute totals
        foreach ($grouped as &$g) {
            foreach (array('povinne', 'dobrovolne') as $cat) {
                $items = $g['tasks'][$cat];
                $completed = 0;
                foreach ($items as $it) {
                    if ($it['status'] === 'completed') {
                        $completed++;
                    }
                }
                $g['tasks'][$cat] = array(
                    'items' => $items,
                    'completed' => $completed,
                    'total' => count($items)
                );
            }
        }
        unset($g);

        wp_send_json_success(array(
            'week_start' => $week_start,
            'week_range' => $week_range,
            'children' => array_values($grouped)
        ));
    }
}

