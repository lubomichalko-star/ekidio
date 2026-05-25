<?php
/**
 * Weekly rotation handler for ekidio plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rodinne_Ulohy_Rotation {
    
    private static $instance = null;
    private $owner_user_id = 0;
    private $last_rotation_conflicts = array();

    const ROTATION_CRON_HOOK = 'rodinne_ulohy_rotation';
    const OPT_ROTATION_FREQ = 'rodinne_ulohy_rotation_frequency';
    const OPT_ROTATION_DAY = 'rodinne_ulohy_rotation_day';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Legacy hook (older installs)
        add_action('rodinne_ulohy_weekly_rotation', array($this, 'rotate_weekly_assignments'));
        // New unified schedule hook (supports weekly/biweekly/monthly)
        add_action(self::ROTATION_CRON_HOOK, array($this, 'run_scheduled_rotation'));
        
        // Generate initial week if needed
        add_action('admin_init', array($this, 'maybe_generate_initial_week'));
        
        // Daily reset for daily tasks
        add_action('rodinne_ulohy_daily_reset', array($this, 'reset_daily_tasks'));
        
        // Weekend tasks penalty check (runs on Monday morning)
        add_action('rodinne_ulohy_weekend_penalty', array($this, 'check_weekend_tasks_penalty'));

        // Ensure recurring schedules are aligned to WP timezone (not server timezone).
        $this->ensure_misc_schedules();

        // Ensure the new rotation is scheduled (single event).
        $this->ensure_rotation_scheduled();
    }

    private function reset_rotation_conflicts() {
        $this->last_rotation_conflicts = array();
    }

    private function add_rotation_conflict($task_id, $task_name, $message) {
        $task_id = intval($task_id);
        $task_name = is_string($task_name) ? $task_name : '';
        $message = is_string($message) ? $message : '';
        $this->last_rotation_conflicts[] = array(
            'task_id' => $task_id,
            'task_name' => $task_name,
            'message' => $message,
        );
    }

    /**
     * Last conflicts from the most recent rotation/generation run.
     *
     * @return array<int, array{task_id:int, task_name:string, message:string}>
     */
    public function get_last_rotation_conflicts() {
        return is_array($this->last_rotation_conflicts) ? $this->last_rotation_conflicts : array();
    }

    /**
     * Ensure daily reset + weekend penalty schedules exist and match intended local times.
     * Uses WP timezone. If existing schedules drifted (older installs / server TZ), reschedules safely.
     */
    private function ensure_misc_schedules() {
        // Daily reset @ 22:50
        try {
            $desired_daily = $this->compute_next_daily_reset_ts();
            $next_daily = wp_next_scheduled('rodinne_ulohy_daily_reset');
            if (!$next_daily || wp_date('H:i', $next_daily) !== '22:50') {
                wp_clear_scheduled_hook('rodinne_ulohy_daily_reset');
                wp_schedule_event($desired_daily, 'daily', 'rodinne_ulohy_daily_reset');
            }
        } catch (Throwable $e) {
            // Legacy fallback
            if (!wp_next_scheduled('rodinne_ulohy_daily_reset')) {
                wp_schedule_event(strtotime('tomorrow 22:50'), 'daily', 'rodinne_ulohy_daily_reset');
            }
        }

        // We no longer use a separate "weekend penalty" cron.
        // Saturday penalties are applied in the daily reset with a multiplier.
        // Clear any legacy schedules so old installs don't keep firing it.
        wp_clear_scheduled_hook('rodinne_ulohy_weekend_penalty');
    }

    private function compute_next_daily_reset_ts() {
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $dt = new DateTimeImmutable('now', $tz);
        $today2250 = $dt->setTime(22, 50);
        if ($dt->getTimestamp() < $today2250->getTimestamp()) {
            return $today2250->getTimestamp();
        }
        return $today2250->modify('+1 day')->getTimestamp();
    }

    // NOTE: compute_next_weekend_penalty_ts() removed (weekend penalty cron removed).

    /**
     * Global rotation settings (site-wide).
     *
     * @return array{frequency:string, day:string}
     */
    public static function get_rotation_settings() {
        $freq = get_option(self::OPT_ROTATION_FREQ, 'weekly');
        $day = get_option(self::OPT_ROTATION_DAY, 'monday');
        $freq = is_string($freq) ? strtolower($freq) : 'weekly';
        $day = is_string($day) ? strtolower($day) : 'monday';

        if (!in_array($freq, array('weekly', 'biweekly', 'monthly'), true)) {
            $freq = 'weekly';
        }
        if (!in_array($day, array('saturday', 'sunday', 'monday'), true)) {
            $day = 'monday';
        }

        // Monthly ignores day, but we keep it stored for later switches.
        return array(
            'frequency' => $freq,
            'day' => $day,
        );
    }

    /**
     * Save settings and reschedule the next rotation.
     *
     * @param string $frequency weekly|biweekly|monthly
     * @param string $day saturday|sunday|monday
     * @return bool
     */
    public static function save_rotation_settings($frequency, $day) {
        $frequency = is_string($frequency) ? strtolower($frequency) : 'weekly';
        $day = is_string($day) ? strtolower($day) : 'monday';
        if (!in_array($frequency, array('weekly', 'biweekly', 'monthly'), true)) $frequency = 'weekly';
        if (!in_array($day, array('saturday', 'sunday', 'monday'), true)) $day = 'monday';

        update_option(self::OPT_ROTATION_FREQ, $frequency, false);
        update_option(self::OPT_ROTATION_DAY, $day, false);

        self::clear_rotation_schedule();
        self::get_instance()->ensure_rotation_scheduled(true);
        return true;
    }

    /**
     * Clear scheduled rotation events (new + legacy).
     */
    public static function clear_rotation_schedule() {
        wp_clear_scheduled_hook(self::ROTATION_CRON_HOOK);
        wp_clear_scheduled_hook('rodinne_ulohy_weekly_rotation');
    }

    /**
     * Ensure a next single-event rotation is scheduled.
     *
     * @param bool $force If true, always reschedule next run.
     * @param int|null $from_ts Base timestamp (WP timezone)
     * @param bool $after_run If true, schedule relative to a just-executed run (biweekly = +14 days)
     * @return void
     */
    public function ensure_rotation_scheduled($force = false, $from_ts = null, $after_run = false) {
        // Prevent double-rotation if an older recurring event is still scheduled.
        wp_clear_scheduled_hook('rodinne_ulohy_weekly_rotation');

        $next = wp_next_scheduled(self::ROTATION_CRON_HOOK);
        if ($next && !$force) return;

        // Remove any stale events and schedule the next one.
        wp_clear_scheduled_hook(self::ROTATION_CRON_HOOK);
        $base = is_null($from_ts) ? current_time('timestamp') : intval($from_ts);
        $ts = $this->compute_next_rotation_ts($base, $after_run);
        if ($ts) {
            wp_schedule_single_event($ts, self::ROTATION_CRON_HOOK);
        }
    }

    /**
     * Compute next rotation timestamp based on settings.
     */
    private function compute_next_rotation_ts($from_ts, $after_run = false) {
        $from_ts = intval($from_ts ?: current_time('timestamp'));
        $s = self::get_rotation_settings();
        $freq = $s['frequency'];
        $day = $s['day'];

        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $dt = (new DateTimeImmutable('@' . $from_ts))->setTimezone($tz);

            if ($freq === 'monthly') {
                return $dt->modify('first day of next month')->setTime(0, 1)->getTimestamp();
            }

            $w = 'monday';
            if ($day === 'saturday') $w = 'saturday';
            if ($day === 'sunday') $w = 'sunday';
            if ($day === 'monday') $w = 'monday';

            if ($freq === 'biweekly' && $after_run) {
                // When scheduling immediately after a run, keep exact 14-day cadence.
                return $dt->modify('+14 days')->setTime(0, 1)->getTimestamp();
            }

            return $dt->modify('next ' . $w)->setTime(0, 1)->getTimestamp();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Scheduled rotation handler. Generates assignments based on configured period.
     * Then schedules the next run.
     */
    public function run_scheduled_rotation() {
        $s = self::get_rotation_settings();
        $freq = $s['frequency'];
        $day = $s['day'];

        $now = current_time('timestamp');

        if ($freq === 'monthly') {
            $this->rotate_monthly_assignments();
            $this->ensure_rotation_scheduled(true, $now, true);
            return;
        }

        // Weekly/Biweekly: choose which week_start we are preparing.
        // - Monday run: generate the current week (week starts today).
        // - Saturday/Sunday run: generate NEXT week (starting next Monday).
        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        if ($day === 'saturday' || $day === 'sunday') {
            try {
                $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
                $dt = (new DateTimeImmutable('@' . $now))->setTimezone($tz);
                $week_start = $dt->modify('next monday')->setTime(0, 0)->format('Y-m-d');
            } catch (Throwable $e) {}
        }

        $this->rotate_assignments_for_week($week_start);

        if ($freq === 'biweekly') {
            // Keep the same distribution for the 2-week period by cloning week 1 into week 2.
            $w2 = date('Y-m-d', strtotime($week_start . ' +7 days'));
            $this->clone_week_assignments($week_start, $w2);
        }

        $this->ensure_rotation_scheduled(true, $now, true);
    }

    /**
     * Generate assignments for a given week_start for all owners.
     */
    private function rotate_assignments_for_week($week_start) {
        $week_start = $week_start ?: Rodinne_Ulohy_Database::get_current_week_start();
        global $wpdb;
        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        $owners = $wpdb->get_col("SELECT DISTINCT owner_user_id FROM $children_table WHERE owner_user_id IS NOT NULL AND owner_user_id > 0");
        if (empty($owners)) {
            // Legacy fallback
            $this->generate_week_assignments($week_start);
            return;
        }
        foreach ($owners as $oid) {
            $this->generate_week_assignments($week_start, array(), intval($oid));
        }
    }

    /**
     * Clone assignments from one week_start to another (keeps the same mapping).
     */
    private function clone_week_assignments($from_week_start, $to_week_start) {
        if (!$from_week_start || !$to_week_start || $from_week_start === $to_week_start) return;

        global $wpdb;
        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        $owners = $wpdb->get_col("SELECT DISTINCT owner_user_id FROM $children_table WHERE owner_user_id IS NOT NULL AND owner_user_id > 0");
        if (empty($owners)) {
            $this->clone_week_assignments_for_owner($from_week_start, $to_week_start, 0);
            return;
        }
        foreach ($owners as $oid) {
            $this->clone_week_assignments_for_owner($from_week_start, $to_week_start, intval($oid));
        }
    }

    private function clone_week_assignments_for_owner($from_week_start, $to_week_start, $owner_user_id = 0) {
        global $wpdb;
        $owner_user_id = intval($owner_user_id);
        $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';

        // Delete existing assignments for target week (scoped to owner).
        if ($owner_user_id) {
            $wpdb->query($wpdb->prepare(
                "DELETE a FROM $assignments_table a
                 INNER JOIN $children_table c ON a.child_id = c.id
                 WHERE a.week_start = %s AND c.owner_user_id = %d",
                $to_week_start,
                $owner_user_id
            ));
        } else {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $assignments_table WHERE week_start = %s",
                $to_week_start
            ));
        }

        $rows = Rodinne_Ulohy_Database::get_week_assignments($from_week_start, $owner_user_id);
        if (empty($rows)) return;

        foreach ($rows as $r) {
            $task_id = intval($r->task_id);
            $child_id = intval($r->child_id);
            if (!$task_id || !$child_id) continue;
            // Future weeks start as todo.
            Rodinne_Ulohy_Database::save_assignment($task_id, $child_id, $to_week_start, 'todo');
        }
    }

    /**
     * Monthly rotation: generate assignments for all Mondays in the current month,
     * using the same distribution for the whole month.
     */
    private function rotate_monthly_assignments() {
        $now = current_time('timestamp');
        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $dt = (new DateTimeImmutable('@' . $now))->setTimezone($tz);
            $first_of_month = $dt->setDate(intval($dt->format('Y')), intval($dt->format('m')), 1)->setTime(0, 0);
            $first_monday = $first_of_month->modify('first monday of this month')->setTime(0, 0);

            $month = $first_of_month->format('m');
            $weeks = array();
            $cursor = $first_monday;
            while ($cursor && $cursor->format('m') === $month) {
                $weeks[] = $cursor->format('Y-m-d');
                $cursor = $cursor->modify('+7 days');
            }
        } catch (Throwable $e) {
            return;
        }
        if (empty($weeks)) return;

        $base = $weeks[0];
        $this->rotate_assignments_for_week($base);

        // Clone into the remaining weeks of the month.
        for ($i = 1; $i < count($weeks); $i++) {
            $this->clone_week_assignments($base, $weeks[$i]);
        }
    }
    
    /**
     * Generate initial week assignments if they don't exist
     */
    public function maybe_generate_initial_week() {
        if (!current_user_can('read')) {
            return;
        }
        $this->owner_user_id = get_current_user_id();

        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $assignments = Rodinne_Ulohy_Database::get_week_assignments($week_start, $this->owner_user_id);
        
        if (empty($assignments)) {
            $this->generate_week_assignments($week_start, array(), $this->owner_user_id);
        }
    }
    
    /**
     * Rotate tasks for the new week
     */
    public function rotate_weekly_assignments() {
        // Backward-compatible: run "weekly" rotation for the current week_start.
        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $this->rotate_assignments_for_week($week_start);
    }
    
    /**
     * Generate assignments for a specific week.
     * All rotating tasks use one unified cyclic algorithm (no rating-based distribution).
     */
    public function generate_week_assignments($week_start = null, $current_week_child_tasks = array(), $owner_user_id = 0, $mode = 'rotate', $freeze_task_to_child = array()) {
        if (!$week_start) {
            $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        }
        $this->owner_user_id = intval($owner_user_id);
        $mode = is_string($mode) ? strtolower($mode) : 'rotate';
        if (!in_array($mode, array('rotate', 'regenerate', 'shift'), true)) {
            $mode = 'rotate';
        }
        if (!is_array($freeze_task_to_child)) {
            $freeze_task_to_child = array();
        }
        
        // Legacy arg kept for backward compatibility; unified rotation uses $freeze_task_to_child.
        
        // Get all tasks
        $tasks = Rodinne_Ulohy_Database::get_tasks(null, null, $this->owner_user_id);
        
        $rotation_tasks = array(); // rotation_enabled = 1 and assigned to 2+ children
        $fixed_tasks = array(); // rotation_enabled = 1 but assigned to exactly one child (no rotation needed)
        $shared_tasks = array(); // legacy shared_task = 1 (assign to all children at once)
        $non_rotating_tasks = array(); // rotation_enabled = 0 (assign to all assigned children)

        $all_children = Rodinne_Ulohy_Database::get_children('', $this->owner_user_id);

        foreach ($tasks as $task) {
            $task_children = Rodinne_Ulohy_Database::get_task_children($task->id, $this->owner_user_id);
            $is_shared = isset($task->shared_task) && intval($task->shared_task) === 1;
            $rotation_enabled = !isset($task->rotation_enabled) ? 1 : intval($task->rotation_enabled);

            // IMPORTANT:
            // If a task has NO assigned children, it is considered "unassigned" and must NOT be
            // auto-assigned to all children. This is critical for imported tasks (no assignments imported).
            if (empty($task_children)) {
                continue;
            }

            // Legacy shared_task: assign to all children (or assigned children if set)
            if ($is_shared) {
                $shared_tasks[] = $task;
                continue;
            }

            // NEW: rotation_enabled = 0 means "Nerotuje" -> everyone assigned gets it (every week/day).
            if ($rotation_enabled === 0) {
                $non_rotating_tasks[] = $task;
                continue;
            }

            // rotation_enabled = 1
            if (count($task_children) === 1) {
                $fixed_tasks[] = array('task' => $task, 'child_id' => $task_children[0]->id);
            } else {
                // 2+ children assigned -> rotates among those children (handled in algorithm)
                $rotation_tasks[] = $task;
            }
        }

        // 0. Assign non-rotating tasks to assigned children only (never to all by default)
        if (!empty($non_rotating_tasks)) {
            foreach ($non_rotating_tasks as $task) {
                $task_children = Rodinne_Ulohy_Database::get_task_children($task->id, $this->owner_user_id);
                if (empty($task_children)) continue;
                foreach ($task_children as $child) {
                    Rodinne_Ulohy_Database::save_assignment($task->id, $child->id, $week_start, 'todo');
                }
            }
        }
        
        // 1. Assign fixed tasks (non-rotating, single child)
        foreach ($fixed_tasks as $item) {
            Rodinne_Ulohy_Database::save_assignment($item['task']->id, $item['child_id'], $week_start, 'todo');
        }
        
        // 2. Assign shared tasks (legacy; to all assigned children at once)
        foreach ($shared_tasks as $task) {
            $task_children = Rodinne_Ulohy_Database::get_task_children($task->id, $this->owner_user_id);
            
            // If no specific children assigned, do not auto-assign.
            if (empty($task_children)) continue;
            
            // Assign task to all children
            foreach ($task_children as $child) {
                Rodinne_Ulohy_Database::save_assignment($task->id, $child->id, $week_start, 'todo');
            }
        }
        
        // 3. Assign all rotating tasks with one unified cyclic algorithm
        // (no rating/points balancing, only rotation + lock/exclude constraints).
        $this->reset_rotation_conflicts();
        $this->assign_rotating_tasks($rotation_tasks, $week_start, $mode, $freeze_task_to_child);
    }
    
    /**
     * Unified cyclic assignment for all rotating tasks.
     * Respects:
     * - task's assigned children
     * Modes:
     * - rotate: rotate from previous period
     * - regenerate: keep current mapping when possible (no rotation advance)
     * - shift: force advance from current mapping
     */
    private function assign_rotating_tasks($tasks, $week_start, $mode = 'rotate', $freeze_task_to_child = array()) {
        if (empty($tasks)) {
            return;
        }

        $mode = is_string($mode) ? strtolower($mode) : 'rotate';
        if (!in_array($mode, array('rotate', 'regenerate', 'shift'), true)) {
            $mode = 'rotate';
        }
        if (!is_array($freeze_task_to_child)) {
            $freeze_task_to_child = array();
        }

        $all_children = Rodinne_Ulohy_Database::get_children('', $this->owner_user_id);
        if (empty($all_children)) {
            error_log('Rodinne Ulohy: No children found for rotating tasks');
            return;
        }

        global $wpdb;
        
        // Deterministic order: stable rotation per task.
        usort($tasks, function($a, $b) {
            return $a->id - $b->id;
        });

        $previous_week_start = date('Y-m-d', strtotime($week_start . ' -7 days'));

        if ($mode === 'shift' && !empty($freeze_task_to_child)) {
            error_log("Rodinne Ulohy: shift - Using current period task->child mapping: " . count($freeze_task_to_child));
        }

        $assigned_count = 0;
        foreach ($tasks as $task) {
            $task_children = Rodinne_Ulohy_Database::get_task_children($task->id, $this->owner_user_id);
            if (empty($task_children)) {
                continue;
            }

            usort($task_children, function($a, $b) {
                return $a->id - $b->id;
            });
            
            $task_child_ids = array_map(function($child) {
                return $child->id;
            }, $task_children);

            // Conflict: rotating task must have at least 2 assigned children.
            if (count($task_child_ids) < 2) {
                $msg = sprintf(
                    __('Konflikt rozdelenia: rotačná úloha "%s" musí mať priradené aspoň 2 deti.', 'rodinne-ulohy'),
                    isset($task->name) ? $task->name : ('#' . intval($task->id))
                );
                $this->add_rotation_conflict($task->id, isset($task->name) ? $task->name : '', $msg);
                continue;
            }

            // Regenerate mode: keep current mapping stable (do not advance rotation).
            if ($mode === 'regenerate') {
                $keep_child_id = intval($freeze_task_to_child[$task->id] ?? 0);
                if ($keep_child_id && in_array($keep_child_id, $task_child_ids, true)) {
                } else {
                    $keep_child_id = 0;
                }

                if ($keep_child_id) {
                    $result = Rodinne_Ulohy_Database::save_assignment($task->id, $keep_child_id, $week_start, 'todo');
                    if ($result !== false) {
                        $assigned_count++;
                        error_log("Rodinne Ulohy: ✓ Kept rotating task {$task->id} assigned to child {$keep_child_id} (regenerate, no rotation advance)");
                        continue;
                    }
                }
            }

            // Find who had this task previously.
            $previous_child_id = null;
            if ($mode === 'shift') {
                $previous_child_id = intval($freeze_task_to_child[intval($task->id)] ?? 0);
            }

            if (!$previous_child_id) {
                if (!empty($this->owner_user_id)) {
                    $previous_child_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT a.child_id FROM {$wpdb->prefix}rodinne_ulohy_assignments a
                        INNER JOIN {$wpdb->prefix}rodinne_ulohy_children c ON a.child_id = c.id
                        WHERE a.task_id = %d AND a.week_start = %s AND c.owner_user_id = %d
                        LIMIT 1",
                        $task->id,
                        $previous_week_start,
                        $this->owner_user_id
                    ));
                } else {
                    $previous_child_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT child_id FROM {$wpdb->prefix}rodinne_ulohy_assignments 
                        WHERE task_id = %d AND week_start = %s 
                        LIMIT 1",
                        $task->id,
                        $previous_week_start
                    ));
                }

                if (!$previous_child_id) {
                    if (!empty($this->owner_user_id)) {
                        $previous_child_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT a.child_id FROM {$wpdb->prefix}rodinne_ulohy_assignments a
                            INNER JOIN {$wpdb->prefix}rodinne_ulohy_children c ON a.child_id = c.id
                            WHERE a.task_id = %d AND a.week_start < %s AND c.owner_user_id = %d
                            ORDER BY a.week_start DESC LIMIT 1",
                            $task->id,
                            $week_start,
                            $this->owner_user_id
                        ));
                    } else {
                        $previous_child_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT child_id FROM {$wpdb->prefix}rodinne_ulohy_assignments 
                            WHERE task_id = %d AND week_start < %s 
                            ORDER BY week_start DESC LIMIT 1",
                            $task->id,
                            $week_start
                        ));
                    }
                }
            }

            $start_index = 0;
            if ($previous_child_id && in_array($previous_child_id, $task_child_ids)) {
                $prev_index = array_search($previous_child_id, $task_child_ids);
                $start_index = ($prev_index + 1) % count($task_child_ids);
            } else {
                $seed = crc32(strval($this->owner_user_id) . '|' . strval($week_start) . '|' . strval($task->id));
                $start_index = intval($seed % max(1, count($task_child_ids)));
            }

            $assigned = false;
            $attempts = 0;
            $child_index = $start_index;

            // First pass: respect strict constraints + avoid repeating same task for same child.
            while (!$assigned && $attempts < count($task_child_ids)) {
                $child_id = $task_child_ids[$child_index];
                $had_same_task = ($previous_child_id && intval($child_id) === intval($previous_child_id));
                if (!$had_same_task) {
                    $result = Rodinne_Ulohy_Database::save_assignment($task->id, $child_id, $week_start, 'todo');
                    if ($result !== false) {
                        $assigned = true;
                        $assigned_count++;
                        error_log("Rodinne Ulohy: ✓ Assigned rotating task {$task->id} to child {$child_id}");
                    } else {
                        error_log("Rodinne Ulohy: ✗ Failed to assign rotating task {$task->id} to child {$child_id}");
                    }
                }

                $child_index = ($child_index + 1) % count($task_child_ids);
                $attempts++;
            }

            if (!$assigned) {
                $msg = sprintf(
                    __('Konflikt rozdelenia: úlohu "%s" sa nepodarilo priradiť bez opakovania tomu istému dieťaťu.', 'rodinne-ulohy'),
                    isset($task->name) ? $task->name : ('#' . intval($task->id))
                );
                $this->add_rotation_conflict($task->id, isset($task->name) ? $task->name : '', $msg);
                error_log("Rodinne Ulohy: ✗✗✗ CRITICAL: Rotating task {$task->id} was NOT assigned to any child!");
            }
        }
        error_log("Rodinne Ulohy: Assigned {$assigned_count} rotating tasks out of " . count($tasks));
    }

    /**
     * Manually move one rotating task to a selected child in the current period.
     *
     * @return array{ok:bool, task_id:int, from_child_id:int, to_child_id:int, week_start:string, weeks_updated:int, message:string}|array{ok:bool, message:string}
     */
    public function shift_single_task_current_period($task_id, $to_child_id, $owner_user_id = 0) {
        global $wpdb;

        $this->owner_user_id = intval($owner_user_id);
        $task_id = intval($task_id);
        $to_child_id = intval($to_child_id);
        if (!$task_id || !$to_child_id) {
            return array('ok' => false, 'message' => __('Neplatné parametre posunu úlohy.', 'rodinne-ulohy'));
        }

        $task = Rodinne_Ulohy_Database::get_task($task_id, $this->owner_user_id);
        if (!$task) {
            return array('ok' => false, 'message' => __('Úloha nebola nájdená.', 'rodinne-ulohy'));
        }
        if (intval($task->rotation_enabled ?? 0) !== 1) {
            return array('ok' => false, 'message' => __('Posun je povolený iba pre rotačné úlohy.', 'rodinne-ulohy'));
        }

        $task_children = Rodinne_Ulohy_Database::get_task_children($task_id, $this->owner_user_id);
        $task_child_ids = array_map(function($c) { return intval($c->id); }, is_array($task_children) ? $task_children : array());
        $task_child_ids = array_values(array_filter($task_child_ids, function($v) { return $v > 0; }));
        if (count($task_child_ids) < 2) {
            return array('ok' => false, 'message' => __('Konflikt: rotačná úloha musí mať aspoň 2 priradené deti.', 'rodinne-ulohy'));
        }
        if (!in_array($to_child_id, $task_child_ids, true)) {
            return array('ok' => false, 'message' => __('Vybrané dieťa nie je priradené k tejto úlohe.', 'rodinne-ulohy'));
        }

        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        $from_child_id = 0;

        if ($this->owner_user_id) {
            $from_child_id = intval($wpdb->get_var($wpdb->prepare(
                "SELECT a.child_id
                 FROM $assignments_table a
                 INNER JOIN $children_table c ON a.child_id = c.id
                 WHERE a.week_start = %s AND a.task_id = %d AND c.owner_user_id = %d
                 LIMIT 1",
                $week_start,
                $task_id,
                $this->owner_user_id
            )));
        } else {
            $from_child_id = intval($wpdb->get_var($wpdb->prepare(
                "SELECT child_id FROM $assignments_table WHERE week_start = %s AND task_id = %d LIMIT 1",
                $week_start,
                $task_id
            )));
        }

        if ($from_child_id && $from_child_id === $to_child_id) {
            return array('ok' => false, 'message' => __('Úloha je už priradená tomuto dieťaťu.', 'rodinne-ulohy'));
        }

        $weeks = array($week_start);
        try {
            $settings = self::get_rotation_settings();
            $freq = $settings['frequency'];
            if ($freq === 'biweekly') {
                $weeks[] = date('Y-m-d', strtotime($week_start . ' +7 days'));
            } elseif ($freq === 'monthly') {
                $now = current_time('timestamp');
                $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
                $dt = (new DateTimeImmutable('@' . $now))->setTimezone($tz);
                $first_of_month = $dt->setDate(intval($dt->format('Y')), intval($dt->format('m')), 1)->setTime(0, 0);
                $first_monday = $first_of_month->modify('first monday of this month')->setTime(0, 0);
                $month = $first_of_month->format('m');
                $cursor = $first_monday;
                while ($cursor && $cursor->format('m') === $month) {
                    $ws = $cursor->format('Y-m-d');
                    if (strtotime($ws) >= strtotime($week_start)) {
                        $weeks[] = $ws;
                    }
                    $cursor = $cursor->modify('+7 days');
                }
            }
        } catch (Throwable $e) {}
        $weeks = array_values(array_unique(array_filter($weeks)));

        foreach ($weeks as $ws) {
            if ($this->owner_user_id) {
                $wpdb->query($wpdb->prepare(
                    "DELETE a FROM $assignments_table a
                     INNER JOIN $children_table c ON a.child_id = c.id
                     WHERE a.week_start = %s AND a.task_id = %d AND c.owner_user_id = %d",
                    $ws,
                    $task_id,
                    $this->owner_user_id
                ));
            } else {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $assignments_table WHERE week_start = %s AND task_id = %d",
                    $ws,
                    $task_id
                ));
            }
            Rodinne_Ulohy_Database::save_assignment($task_id, $to_child_id, $ws, 'todo');
        }

        return array(
            'ok' => true,
            'task_id' => $task_id,
            'from_child_id' => $from_child_id,
            'to_child_id' => $to_child_id,
            'week_start' => $week_start,
            'weeks_updated' => count($weeks),
            'message' => __('Úloha bola manuálne presunutá na vybrané dieťa.', 'rodinne-ulohy'),
        );
    }
    
    /**
     * Get next child in rotation
     */
    private function get_next_child($child_ids, $current_child_id = null) {
        if (empty($child_ids)) {
            return null;
        }
        
        if (!$current_child_id || !in_array($current_child_id, $child_ids)) {
            // Start with first child
            return $child_ids[0];
        }
        
        $current_index = array_search($current_child_id, $child_ids);
        $next_index = ($current_index + 1) % count($child_ids);
        
        return $child_ids[$next_index];
    }
    
    /**
     * Manually regenerate current week's plan
     */
    public function regenerate_current_week($owner_user_id = 0) {
        // Backward-compatible name. This now regenerates the current period
        // (week / 2 weeks / remaining month weeks), based on configured rotation settings.
        return $this->regenerate_current_period($owner_user_id);
    }

    /**
     * Manually regenerate the current period.
     * - weekly: current week
     * - biweekly: current week + next week (cloned)
     * - monthly: current week (as base) + remaining weeks in current month (cloned)
     */
    public function regenerate_current_period($owner_user_id = 0) {
        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $this->owner_user_id = intval($owner_user_id);

        // IMPORTANT: Preserve completion status for tasks that were already done.
        // Regeneration deletes assignments and recreates them, which would otherwise reset checkboxes.
        // We restore status by stable key: (child_id + task_id + week_start).
        $old_assignment_states = array();
        global $wpdb;
        if (!empty($this->owner_user_id)) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT a.child_id, a.task_id, a.status, a.completed_at
                 FROM {$wpdb->prefix}rodinne_ulohy_assignments a
                 INNER JOIN {$wpdb->prefix}rodinne_ulohy_children c ON a.child_id = c.id
                 WHERE a.week_start = %s AND c.owner_user_id = %d",
                $week_start,
                $this->owner_user_id
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT child_id, task_id, status, completed_at
                 FROM {$wpdb->prefix}rodinne_ulohy_assignments
                 WHERE week_start = %s",
                $week_start
            ));
        }
        if (!empty($rows)) {
            foreach ($rows as $r) {
                $cid = intval($r->child_id);
                $tid = intval($r->task_id);
                if (!$cid || !$tid) continue;
                $old_assignment_states[$cid . '_' . $tid] = array(
                    'status' => isset($r->status) ? $r->status : 'todo',
                    'completed_at' => isset($r->completed_at) ? $r->completed_at : null,
                );
            }
        }
        
        // IMPORTANT: Get current period rotating assignments BEFORE deleting them.
        // We use them ONLY to "freeze" the mapping during regeneration,
        // so regeneration does NOT advance rotation.
        if (!empty($this->owner_user_id)) {
            $current_week_assignments = $wpdb->get_results($wpdb->prepare(
                "SELECT a.task_id, a.child_id
                FROM {$wpdb->prefix}rodinne_ulohy_assignments a
                INNER JOIN {$wpdb->prefix}rodinne_ulohy_tasks t ON a.task_id = t.id
                INNER JOIN {$wpdb->prefix}rodinne_ulohy_children c ON a.child_id = c.id
                WHERE a.week_start = %s AND t.rotation_enabled = 1 AND t.owner_user_id = %d AND c.owner_user_id = %d",
                $week_start,
                $this->owner_user_id,
                $this->owner_user_id
            ));
        } else {
            $current_week_assignments = $wpdb->get_results($wpdb->prepare(
                "SELECT a.task_id, a.child_id
                FROM {$wpdb->prefix}rodinne_ulohy_assignments a
                INNER JOIN {$wpdb->prefix}rodinne_ulohy_tasks t ON a.task_id = t.id
                WHERE a.week_start = %s AND t.rotation_enabled = 1",
                $week_start
            ));
        }
        
        // Store current week's assignments as a freeze-map: task_id => child_id
        $freeze_task_to_child = array();
        foreach ($current_week_assignments as $curr) {
            $tid = intval($curr->task_id);
            $cid = intval($curr->child_id);
            if ($tid && $cid) {
                $freeze_task_to_child[$tid] = $cid;
            }
        }
        
        if (!empty($freeze_task_to_child)) {
            error_log("Rodinne Ulohy: regenerate_current_week - Found " . count($freeze_task_to_child) . " rotating assignments, will freeze mapping (no rotation advance)");
        }
        
        // IMPORTANT: Points balance and history are NEVER deleted during regeneration
        // Only assignments are deleted and regenerated
        
        // Now delete existing assignments for this week
        if (!empty($this->owner_user_id)) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE a FROM {$wpdb->prefix}rodinne_ulohy_assignments a
                 INNER JOIN {$wpdb->prefix}rodinne_ulohy_children c ON a.child_id = c.id
                 WHERE a.week_start = %s AND c.owner_user_id = %d",
                $week_start,
                $this->owner_user_id
            ));
        } else {
            $deleted = $wpdb->delete(
                $wpdb->prefix . 'rodinne_ulohy_assignments',
                array('week_start' => $week_start),
                array('%s')
            );
        }
        
        error_log("Rodinne Ulohy: regenerate_current_week - Deleted {$deleted} assignments for week {$week_start}");
        
        // Verify deletion
        if (!empty($this->owner_user_id)) {
            $remaining = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rodinne_ulohy_assignments a
                 INNER JOIN {$wpdb->prefix}rodinne_ulohy_children c ON a.child_id = c.id
                 WHERE a.week_start = %s AND c.owner_user_id = %d",
                $week_start,
                $this->owner_user_id
            ));
        } else {
            $remaining = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rodinne_ulohy_assignments WHERE week_start = %s",
                $week_start
            ));
        }
        
        if ($remaining > 0) {
            error_log("Rodinne Ulohy: WARNING - Still {$remaining} assignments remaining after deletion!");
            // Force delete any remaining
            if (!empty($this->owner_user_id)) {
                $wpdb->query($wpdb->prepare(
                    "DELETE a FROM {$wpdb->prefix}rodinne_ulohy_assignments a
                     INNER JOIN {$wpdb->prefix}rodinne_ulohy_children c ON a.child_id = c.id
                     WHERE a.week_start = %s AND c.owner_user_id = %d",
                    $week_start,
                    $this->owner_user_id
                ));
            } else {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}rodinne_ulohy_assignments WHERE week_start = %s",
                    $week_start
                ));
            }
        }
        
        // Generate new assignments in "regenerate" mode:
        // - keep existing rotating assignments stable when possible
        // - do NOT advance rotation pointers
        $this->generate_week_assignments($week_start, array(), $this->owner_user_id, 'regenerate', $freeze_task_to_child);

        // Restore completion state for assignments that still exist after regeneration.
        // (If a task moved to a different child due to redistribution, we do NOT carry over completion.)
        if (!empty($old_assignment_states)) {
            $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
            foreach ($old_assignment_states as $key => $state) {
                $parts = explode('_', $key);
                if (count($parts) !== 2) continue;
                $cid = intval($parts[0]);
                $tid = intval($parts[1]);
                if (!$cid || !$tid) continue;

                $status = isset($state['status']) ? sanitize_text_field($state['status']) : 'todo';
                if (!in_array($status, array('todo', 'completed'), true)) {
                    $status = 'todo';
                }
                $completed_at = ($status === 'completed') ? ($state['completed_at'] ?? current_time('mysql')) : null;

                // Only update if the regenerated assignment exists for the same child/task/week.
                $wpdb->update(
                    $assignments_table,
                    array(
                        'status' => $status,
                        'completed_at' => $completed_at,
                    ),
                    array(
                        'week_start' => $week_start,
                        'task_id' => $tid,
                        'child_id' => $cid,
                    ),
                    array('%s', '%s'),
                    array('%s', '%d', '%d')
                );
            }
        }

        // Extend regeneration to the configured period by cloning the freshly regenerated base week.
        // Note: cloned weeks always start as "todo" (future periods shouldn't inherit completion).
        try {
            $settings = self::get_rotation_settings();
            $freq = $settings['frequency'];

            if ($freq === 'biweekly') {
                $w2 = date('Y-m-d', strtotime($week_start . ' +7 days'));
                $this->clone_week_assignments_for_owner($week_start, $w2, $this->owner_user_id);
            } elseif ($freq === 'monthly') {
                $now = current_time('timestamp');
                $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
                $dt = (new DateTimeImmutable('@' . $now))->setTimezone($tz);
                $first_of_month = $dt->setDate(intval($dt->format('Y')), intval($dt->format('m')), 1)->setTime(0, 0);
                $first_monday = $first_of_month->modify('first monday of this month')->setTime(0, 0);
                $month = $first_of_month->format('m');

                $cursor = $first_monday;
                while ($cursor && $cursor->format('m') === $month) {
                    $ws = $cursor->format('Y-m-d');
                    if (strtotime($ws) > strtotime($week_start)) {
                        $this->clone_week_assignments_for_owner($week_start, $ws, $this->owner_user_id);
                    }
                    $cursor = $cursor->modify('+7 days');
                }
            }
        } catch (Throwable $e) {
            // Don't fail the whole regeneration if cloning fails for any reason.
        }
        
        return true;
    }

    /**
     * Manually shift (advance) rotation for the current period.
     * This is an explicit user action: regenerate + advance rotation based on CURRENT week's mapping.
     */
    public function shift_rotation_current_period($owner_user_id = 0) {
        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $this->owner_user_id = intval($owner_user_id);

        // Preserve completion status where child+task stays the same (same logic as regeneration).
        $old_assignment_states = array();
        global $wpdb;
        if (!empty($this->owner_user_id)) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT a.child_id, a.task_id, a.status, a.completed_at
                 FROM {$wpdb->prefix}rodinne_ulohy_assignments a
                 INNER JOIN {$wpdb->prefix}rodinne_ulohy_children c ON a.child_id = c.id
                 WHERE a.week_start = %s AND c.owner_user_id = %d",
                $week_start,
                $this->owner_user_id
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT child_id, task_id, status, completed_at
                 FROM {$wpdb->prefix}rodinne_ulohy_assignments
                 WHERE week_start = %s",
                $week_start
            ));
        }
        if (!empty($rows)) {
            foreach ($rows as $r) {
                $cid = intval($r->child_id);
                $tid = intval($r->task_id);
                if (!$cid || !$tid) continue;
                $old_assignment_states[$cid . '_' . $tid] = array(
                    'status' => isset($r->status) ? $r->status : 'todo',
                    'completed_at' => isset($r->completed_at) ? $r->completed_at : null,
                );
            }
        }

        // Capture CURRENT period rotating mapping (task_id => child_id) to advance from it.
        if (!empty($this->owner_user_id)) {
            $current_week_assignments = $wpdb->get_results($wpdb->prepare(
                "SELECT a.task_id, a.child_id
                 FROM {$wpdb->prefix}rodinne_ulohy_assignments a
                 INNER JOIN {$wpdb->prefix}rodinne_ulohy_tasks t ON a.task_id = t.id
                 INNER JOIN {$wpdb->prefix}rodinne_ulohy_children c ON a.child_id = c.id
                 WHERE a.week_start = %s AND t.rotation_enabled = 1 AND t.owner_user_id = %d AND c.owner_user_id = %d",
                $week_start,
                $this->owner_user_id,
                $this->owner_user_id
            ));
        } else {
            $current_week_assignments = $wpdb->get_results($wpdb->prepare(
                "SELECT a.task_id, a.child_id
                 FROM {$wpdb->prefix}rodinne_ulohy_assignments a
                 INNER JOIN {$wpdb->prefix}rodinne_ulohy_tasks t ON a.task_id = t.id
                 WHERE a.week_start = %s AND t.rotation_enabled = 1",
                $week_start
            ));
        }
        $current_task_to_child = array();
        foreach ($current_week_assignments as $curr) {
            $cid = intval($curr->child_id);
            $tid = intval($curr->task_id);
            if ($cid && $tid) {
                $current_task_to_child[$tid] = $cid;
            }
        }

        // Delete existing assignments for this week (owner-scoped).
        if (!empty($this->owner_user_id)) {
            $wpdb->query($wpdb->prepare(
                "DELETE a FROM {$wpdb->prefix}rodinne_ulohy_assignments a
                 INNER JOIN {$wpdb->prefix}rodinne_ulohy_children c ON a.child_id = c.id
                 WHERE a.week_start = %s AND c.owner_user_id = %d",
                $week_start,
                $this->owner_user_id
            ));
        } else {
            $wpdb->delete(
                $wpdb->prefix . 'rodinne_ulohy_assignments',
                array('week_start' => $week_start),
                array('%s')
            );
        }

        // Generate new assignments in "shift" mode.
        // We pass task->child mapping so shift always advances each rotating task.
        $this->generate_week_assignments($week_start, array(), $this->owner_user_id, 'shift', $current_task_to_child);

        // Restore completion for assignments that still exist for the same child/task/week.
        if (!empty($old_assignment_states)) {
            $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
            foreach ($old_assignment_states as $key => $state) {
                $parts = explode('_', $key);
                if (count($parts) !== 2) continue;
                $cid = intval($parts[0]);
                $tid = intval($parts[1]);
                if (!$cid || !$tid) continue;

                $status = isset($state['status']) ? sanitize_text_field($state['status']) : 'todo';
                if (!in_array($status, array('todo', 'completed'), true)) {
                    $status = 'todo';
                }
                $completed_at = ($status === 'completed') ? ($state['completed_at'] ?? current_time('mysql')) : null;

                $wpdb->update(
                    $assignments_table,
                    array('status' => $status, 'completed_at' => $completed_at),
                    array('week_start' => $week_start, 'task_id' => $tid, 'child_id' => $cid),
                    array('%s', '%s'),
                    array('%s', '%d', '%d')
                );
            }
        }

        // Extend to configured period (same as regeneration).
        try {
            $settings = self::get_rotation_settings();
            $freq = $settings['frequency'];

            if ($freq === 'biweekly') {
                $w2 = date('Y-m-d', strtotime($week_start . ' +7 days'));
                $this->clone_week_assignments_for_owner($week_start, $w2, $this->owner_user_id);
            } elseif ($freq === 'monthly') {
                $now = current_time('timestamp');
                $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
                $dt = (new DateTimeImmutable('@' . $now))->setTimezone($tz);
                $first_of_month = $dt->setDate(intval($dt->format('Y')), intval($dt->format('m')), 1)->setTime(0, 0);
                $first_monday = $first_of_month->modify('first monday of this month')->setTime(0, 0);
                $month = $first_of_month->format('m');

                $cursor = $first_monday;
                while ($cursor && $cursor->format('m') === $month) {
                    $ws = $cursor->format('Y-m-d');
                    if (strtotime($ws) > strtotime($week_start)) {
                        $this->clone_week_assignments_for_owner($week_start, $ws, $this->owner_user_id);
                    }
                    $cursor = $cursor->modify('+7 days');
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        return true;
    }
    
    /**
     * Reset daily tasks - set all daily task assignments to 'todo' status
     */
    public function reset_daily_tasks() {
        // Backward-compatible wrapper (scheduled cron uses "now").
        $this->run_daily_reset(null, false, false);
    }

    /**
     * Run the daily reset logic for a specific timestamp (dev/testing) with optional dry-run.
     *
     * IMPORTANT: Saturday-only tasks are represented as days_of_week = only 6.
     * must NOT be processed by daily reset on Saturday night.
     * Otherwise they get reset to "todo" on Saturday night, and then Monday's weekend
     * penalty would incorrectly penalize even completed Saturday tasks.
     *
     * @param int|null $now_ts WP-local timestamp; null = current_time('timestamp')
     * @param bool $dry_run If true, does not write anything; returns a summary array.
     * @param bool $include_details If true, include per-assignment details (dev/debug).
     * @return array{ok:bool, now:string, current_day:int, week_start:string, penalty_date:string, considered:int, reset:int, penalties:int, details?:array, details_truncated?:bool, details_limit?:int}
     */
    public function run_daily_reset($now_ts = null, $dry_run = false, $include_details = false) {
        global $wpdb;
        
        $now_ts = is_null($now_ts) ? current_time('timestamp') : intval($now_ts);
        $week_start = Rodinne_Ulohy_Database::get_week_start_for_ts($now_ts);
        $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        $tasks_table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        
        // Get all task assignments that should reset daily (have days_of_week set)
        // Tasks with days_of_week are reset daily if they have at least one weekday (1-5)
        // Use WP timezone for day-of-week to avoid server TZ drift.
        $current_day = intval(wp_date('w', $now_ts)); // 0 = Sunday, 6 = Saturday
        $daily_assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.child_id, c.name as child_name, a.status, a.task_id, t.name as task_name, t.task_category, t.rating, t.days_of_week, t.task_type
            FROM $assignments_table a
            INNER JOIN $tasks_table t ON a.task_id = t.id
            LEFT JOIN $children_table c ON a.child_id = c.id
            WHERE a.week_start = %s AND (
                (t.days_of_week IS NOT NULL AND t.days_of_week != '' AND FIND_IN_SET(%d, t.days_of_week) > 0)
                OR (t.days_of_week IS NULL OR t.days_of_week = '') AND (t.task_type = 'daily' OR t.task_type IS NULL OR t.task_type = '')
            )",
            $week_start,
            $current_day
        ));
        
        $penalty_date = wp_date('Y-m-d', $now_ts);
        $reset_count = 0;
        $penalty_count = 0;
        $details = array();
        $details_limit = 200;
        $details_truncated = false;
        
        foreach ($daily_assignments as $assignment) {
            $days_raw = isset($assignment->days_of_week) ? strval($assignment->days_of_week) : '';
            $task_type = isset($assignment->task_type) ? strval($assignment->task_type) : '';
            $days = array();
            if ($days_raw !== '') {
                $parts = array_filter(array_map('trim', explode(',', $days_raw)), function($v) { return $v !== ''; });
                foreach ($parts as $p) {
                    $days[] = intval($p);
                }
                $days = array_values(array_unique($days));
                sort($days);
            }
            $is_saturday_only = (!empty($days) && count($days) === 1 && intval($days[0]) === 6);

            $task_category = !empty($assignment->task_category) ? strtolower($assignment->task_category) : 'povinne';
            $rating = isset($assignment->rating) ? intval($assignment->rating) : 0;

            // Saturday-only tasks get the special multiplier (e.g. 6x).
            $multiplier = 1.0;
            if ($current_day === 6 && $is_saturday_only) {
                $multiplier = floatval(Rodinne_Ulohy_Database::get_weekend_penalty_multiplier());
                if ($multiplier < 1) $multiplier = 1.0;
            }

            $would_penalize = ($assignment->status !== 'completed' && $task_category === 'povinne' && $rating > 0);
            $already_penalized = $would_penalize ? Rodinne_Ulohy_Database::penalty_already_added($assignment->id, $penalty_date) : false;
            $will_penalize = ($would_penalize && !$already_penalized);
            $penalty_points = $will_penalize ? -intval(round($rating * $multiplier)) : 0;

            if ($will_penalize) {
                if (!$dry_run) {
                    $reason = ($current_day === 6 && $is_saturday_only)
                        ? sprintf(__('Nesplnená sobotná povinná úloha: %s (×%s)', 'rodinne-ulohy'), $assignment->task_name, number_format($multiplier, 1))
                        : sprintf(__('Nesplnená povinná úloha: %s', 'rodinne-ulohy'), $assignment->task_name);
                    Rodinne_Ulohy_Database::add_points(
                        $assignment->child_id,
                        $penalty_points,
                        $week_start,
                        $assignment->task_id,
                        $assignment->id,
                        $reason,
                        'penalty'
                    );
                }
                $penalty_count++;
            }
            
            // Reset all daily task assignments to 'todo' for the new day
            if (!$dry_run) {
                $wpdb->update(
                    $assignments_table,
                    array(
                        'status' => 'todo',
                        'completed_at' => null
                    ),
                    array('id' => $assignment->id),
                    array('%s', '%s'),
                    array('%d')
                );
            }
            $reset_count++;

            if ($include_details) {
                if (count($details) < $details_limit) {
                    $details[] = array(
                        'assignment_id' => intval($assignment->id),
                        'child_id' => intval($assignment->child_id),
                        'child_name' => isset($assignment->child_name) ? strval($assignment->child_name) : '',
                        'task_id' => intval($assignment->task_id),
                        'task_name' => isset($assignment->task_name) ? strval($assignment->task_name) : '',
                        'status' => isset($assignment->status) ? strval($assignment->status) : '',
                        'task_category' => $task_category,
                        'rating' => $rating,
                        'would_penalize' => $would_penalize,
                        'already_penalized' => (bool) $already_penalized,
                        'will_penalize' => $will_penalize,
                        'penalty_points' => $penalty_points,
                        'will_reset_to_todo' => true,
                        'days_of_week' => $days_raw,
                        'task_type' => $task_type,
                        'is_saturday_only' => $is_saturday_only,
                        'multiplier' => $multiplier,
                    );
                } else {
                    $details_truncated = true;
                }
            }
        }

        $out = array(
            'ok' => true,
            'now' => wp_date('Y-m-d H:i:s', $now_ts),
            'current_day' => $current_day,
            'week_start' => $week_start,
            'penalty_date' => $penalty_date,
            'considered' => is_array($daily_assignments) ? count($daily_assignments) : 0,
            'reset' => $reset_count,
            'penalties' => $penalty_count,
        );
        if ($include_details) {
            $out['details'] = $details;
            $out['details_truncated'] = $details_truncated;
            $out['details_limit'] = $details_limit;
        }
        return $out;
    }
    
    /**
     * Manually reset daily tasks and deduct points for the previous day
     * This is a manual trigger in case the automatic cron at 22:50 didn't run
     */
    public function manual_reset_previous_day() {
        global $wpdb;
        
        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        $tasks_table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        
        // Get yesterday's date for penalty
        $now_ts = current_time('timestamp');
        $yesterday = wp_date('Y-m-d', $now_ts - DAY_IN_SECONDS);
        $today = wp_date('Y-m-d', $now_ts);
        
        // Get all task assignments that should reset daily (have days_of_week set)
        // For manual reset, we check yesterday's day
        $yesterday_day = intval(wp_date('w', $now_ts - DAY_IN_SECONDS));
        $daily_assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.child_id, a.status, a.task_id, a.completed_at, t.name as task_name, t.task_category, t.rating, t.days_of_week, t.task_type
            FROM $assignments_table a
            INNER JOIN $tasks_table t ON a.task_id = t.id
            WHERE a.week_start = %s AND (
                (t.days_of_week IS NOT NULL AND t.days_of_week != '' AND FIND_IN_SET(%d, t.days_of_week) > 0)
                OR (t.days_of_week IS NULL OR t.days_of_week = '') AND (t.task_type = 'daily' OR t.task_type IS NULL OR t.task_type = '')
            )",
            $week_start,
            $yesterday_day
        ));
        
        $reset_count = 0;
        $penalty_count = 0;
        
        foreach ($daily_assignments as $assignment) {
            $task_category = !empty($assignment->task_category) ? strtolower($assignment->task_category) : 'povinne';
            $rating = isset($assignment->rating) ? intval($assignment->rating) : 0;
            
            // Check if task was completed today (if so, skip resetting it)
            $completed_today = false;
            if ($assignment->completed_at) {
                $completed_date = date('Y-m-d', strtotime($assignment->completed_at));
                $completed_today = ($completed_date === $today);
            }
            
            // Skip tasks completed today (they shouldn't be reset)
            if ($completed_today) {
                continue;
            }
            
            // Deduct points for uncompleted mandatory tasks (for yesterday)
            if ($assignment->status !== 'completed' && $task_category === 'povinne' && $rating > 0) {
                $already_penalized = Rodinne_Ulohy_Database::penalty_already_added($assignment->id, $yesterday);
                
                if (!$already_penalized) {
                    Rodinne_Ulohy_Database::add_points(
                        $assignment->child_id,
                        -$rating,
                        $week_start,
                        $assignment->task_id,
                        $assignment->id,
                        sprintf(__('Nesplnená povinná úloha (včera): %s', 'rodinne-ulohy'), $assignment->task_name),
                        'penalty'
                    );
                    $penalty_count++;
                }
            }
            
            // Reset all daily task assignments to 'todo' (except those completed today)
            if ($assignment->status !== 'todo') {
                $wpdb->update(
                    $assignments_table,
                    array(
                        'status' => 'todo',
                        'completed_at' => null
                    ),
                    array('id' => $assignment->id),
                    array('%s', '%s'),
                    array('%d')
                );
                $reset_count++;
            }
        }
        
        return array(
            'success' => true,
            'reset_count' => $reset_count,
            'penalty_count' => $penalty_count,
            'yesterday' => $yesterday
        );
    }
    
    /**
     * Check and apply penalties for uncompleted weekend tasks
     * This runs on Monday morning to check the previous weekend
     */
    public function check_weekend_tasks_penalty() {
        // Deprecated: weekend penalty no longer exists. Kept only so older schedules/hooks do no harm.
        return array('ok' => true, 'skipped' => true, 'reason' => 'deprecated_weekend_penalty_removed');
    }

    /**
     * Run the weekend penalty logic for a specific timestamp (dev/testing) with optional dry-run.
     *
     * @param int|null $now_ts WP-local timestamp; null = current_time('timestamp')
     * @param bool $dry_run If true, does not write anything; returns a summary array.
     * @param bool $force If true, bypasses the "must be Monday" guard (dev only).
     * @param bool $include_details If true, include per-assignment details (dev/debug).
     * @return array{ok:bool, now:string, is_monday:bool, previous_week_start:string, penalty_date?:string, considered:int, penalties:int, skipped_not_monday:bool, details?:array, details_truncated?:bool, details_limit?:int}
     */
    public function run_weekend_penalty($now_ts = null, $dry_run = false, $force = false, $include_details = false) {
        // Deprecated: weekend penalty no longer exists. Kept only so older schedules/hooks do no harm.
        return array('ok' => true, 'skipped' => true, 'reason' => 'deprecated_weekend_penalty_removed');
    }
}

