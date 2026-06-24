<?php
/**
 * Database handler for ekidio plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rodinne_Ulohy_Database {
    
    private static $instance = null;

    // Increment when DB schema/data migrations change.
    // Used to avoid running migrations on every request.
    private const DB_VERSION = 3;
    private const DB_VERSION_OPTION = 'rodinne_ulohy_db_version';
    private const DB_MIGRATING_TRANSIENT = 'rodinne_ulohy_db_migrating';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
            self::maybe_migrate();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }

    /**
     * Run migrations only when needed (version bump).
     */
    public static function maybe_migrate() {
        $current = intval(get_option(self::DB_VERSION_OPTION, 0));
        if ($current >= self::DB_VERSION) {
            return;
        }

        // Basic lock to avoid concurrent migrations.
        if (get_transient(self::DB_MIGRATING_TRANSIENT)) {
            return;
        }
        set_transient(self::DB_MIGRATING_TRANSIENT, 1, 2 * MINUTE_IN_SECONDS);

        try {
            self::migrate_tables();
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION, true);
        } finally {
            delete_transient(self::DB_MIGRATING_TRANSIENT);
        }
    }
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Children table
        $table_children = $wpdb->prefix . 'rodinne_ulohy_children';
        $sql_children = "CREATE TABLE $table_children (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            sort_order int(11) NOT NULL DEFAULT 0,
            name varchar(255) NOT NULL,
            email varchar(255) DEFAULT NULL,
            password varchar(255) DEFAULT NULL,
            avatar_url varchar(500) DEFAULT NULL,
            color varchar(7) DEFAULT '#4CAF50',
            login_code varchar(6) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY login_code (login_code),
            KEY owner_user_id (owner_user_id),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        // Tasks table
        $table_tasks = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $sql_tasks = "CREATE TABLE $table_tasks (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            package_id bigint(20) UNSIGNED DEFAULT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            task_type varchar(20) DEFAULT 'daily',
            days_of_week varchar(50) DEFAULT NULL,
            task_category varchar(20) DEFAULT 'povinne',
            rotation_enabled tinyint(1) DEFAULT 1,
            shared_task tinyint(1) DEFAULT 0,
            estimated_time int(11) DEFAULT NULL,
            points int(11) DEFAULT NULL,
            rating int(11) DEFAULT NULL,
            icon varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY owner_user_id (owner_user_id),
            KEY package_id (package_id)
        ) $charset_collate;";
        
        // Task packages table (legacy - no longer used for drag & drop, kept for backward compatibility)
        $table_packages = $wpdb->prefix . 'rodinne_ulohy_packages';
        $sql_packages = "CREATE TABLE $table_packages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY owner_user_id (owner_user_id)
        ) $charset_collate;";
        
        // Points balance table (current balance per child)
        $table_points_balance = $wpdb->prefix . 'rodinne_ulohy_points_balance';
        $sql_points_balance = "CREATE TABLE $table_points_balance (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            child_id bigint(20) UNSIGNED NOT NULL,
            balance int(11) DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_child (child_id),
            KEY child_id (child_id)
        ) $charset_collate;";
        
        // Points history table (all point transactions)
        $table_points_history = $wpdb->prefix . 'rodinne_ulohy_points_history';
        $sql_points_history = "CREATE TABLE $table_points_history (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            child_id bigint(20) UNSIGNED NOT NULL,
            points int(11) NOT NULL,
            week_start date DEFAULT NULL,
            task_id bigint(20) UNSIGNED DEFAULT NULL,
            assignment_id bigint(20) UNSIGNED DEFAULT NULL,
            reason varchar(255) DEFAULT NULL,
            type varchar(20) DEFAULT 'task',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY child_id (child_id),
            KEY week_start (week_start),
            KEY task_id (task_id),
            KEY assignment_id (assignment_id)
        ) $charset_collate;";
        
        // Package children assignment table
        $table_package_children = $wpdb->prefix . 'rodinne_ulohy_package_children';
        $sql_package_children = "CREATE TABLE $table_package_children (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            package_id bigint(20) UNSIGNED NOT NULL,
            child_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_assignment (package_id, child_id),
            KEY package_id (package_id),
            KEY child_id (child_id)
        ) $charset_collate;";
        
        // Task children assignment table (for standalone tasks)
        $table_task_children = $wpdb->prefix . 'rodinne_ulohy_task_children';
        $sql_task_children = "CREATE TABLE $table_task_children (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id bigint(20) UNSIGNED NOT NULL,
            child_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_task_assignment (task_id, child_id),
            KEY task_id (task_id),
            KEY child_id (child_id)
        ) $charset_collate;";
        
        // Task links table (locked tasks that rotate together)
        $table_task_links = $wpdb->prefix . 'rodinne_ulohy_task_links';
        $sql_task_links = "CREATE TABLE $table_task_links (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id bigint(20) UNSIGNED NOT NULL,
            linked_task_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_link (task_id, linked_task_id),
            KEY task_id (task_id),
            KEY linked_task_id (linked_task_id)
        ) $charset_collate;";
        
        // Task exclusions table (tasks that must not be together for the same child/week)
        $table_task_exclusions = $wpdb->prefix . 'rodinne_ulohy_task_exclusions';
        $sql_task_exclusions = "CREATE TABLE $table_task_exclusions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id bigint(20) UNSIGNED NOT NULL,
            excluded_task_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_exclusion (task_id, excluded_task_id),
            KEY task_id (task_id),
            KEY excluded_task_id (excluded_task_id)
        ) $charset_collate;";
        
        // Rewards table
        $table_rewards = $wpdb->prefix . 'rodinne_ulohy_rewards';
        $sql_rewards = "CREATE TABLE $table_rewards (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            title varchar(255) NOT NULL,
            category varchar(255) DEFAULT NULL,
            details varchar(255) DEFAULT NULL,
            icon varchar(100) DEFAULT NULL,
            points_cost int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY owner_user_id (owner_user_id)
        ) $charset_collate;";

        // Global task library table
        $table_task_library = $wpdb->prefix . 'rodinne_ulohy_task_library';
        $sql_task_library = "CREATE TABLE $table_task_library (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            task_type varchar(20) DEFAULT 'daily',
            days_of_week varchar(50) DEFAULT NULL,
            task_category varchar(20) DEFAULT 'povinne',
            rotation_enabled tinyint(1) DEFAULT 1,
            shared_task tinyint(1) DEFAULT 0,
            estimated_time int(11) DEFAULT NULL,
            rating int(11) DEFAULT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        // Global reward library table
        $table_reward_library = $wpdb->prefix . 'rodinne_ulohy_reward_library';
        $sql_reward_library = "CREATE TABLE $table_reward_library (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            category varchar(255) DEFAULT NULL,
            details varchar(255) DEFAULT NULL,
            icon varchar(100) DEFAULT NULL,
            points_cost int(11) NOT NULL DEFAULT 0,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        // Reward purchases table
        $table_reward_purchases = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';
        $sql_reward_purchases = "CREATE TABLE $table_reward_purchases (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            reward_id bigint(20) UNSIGNED NOT NULL,
            child_id bigint(20) UNSIGNED NOT NULL,
            points_spent int(11) NOT NULL,
            status varchar(20) DEFAULT 'active',
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY reward_child (reward_id, child_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Weekly assignments table
        $table_assignments = $wpdb->prefix . 'rodinne_ulohy_assignments';
        $sql_assignments = "CREATE TABLE $table_assignments (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id bigint(20) UNSIGNED NOT NULL,
            child_id bigint(20) UNSIGNED NOT NULL,
            week_start date NOT NULL,
            status varchar(20) DEFAULT 'todo',
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY child_id (child_id),
            KEY week_start (week_start),
            UNIQUE KEY unique_week_assignment (task_id, child_id, week_start)
        ) $charset_collate;";

        // Feedback table (temporary feedback collection)
        $table_feedback = $wpdb->prefix . 'rodinne_ulohy_feedback';
        $sql_feedback = "CREATE TABLE $table_feedback (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subject_type varchar(20) NOT NULL,
            subject_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            name varchar(255) NOT NULL,
            path varchar(255) DEFAULT NULL,
            text longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY subject (subject_type, subject_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_children);
        dbDelta($sql_packages);
        dbDelta($sql_tasks);
        dbDelta($sql_package_children);
        dbDelta($sql_task_children);
        dbDelta($sql_assignments);
        dbDelta($sql_points_balance);
        dbDelta($sql_points_history);
        dbDelta($sql_task_links);
        dbDelta($sql_task_exclusions);
        dbDelta($sql_rewards);
        dbDelta($sql_task_library);
        dbDelta($sql_reward_library);
        dbDelta($sql_reward_purchases);
        dbDelta($sql_feedback);
        
        // Run migrations for existing installations
        self::maybe_migrate();
    }
    
    /**
     * Migrate database tables for existing installations
     */
    public static function migrate_tables() {
        global $wpdb;
        $table_tasks = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $charset_collate = $wpdb->get_charset_collate();

        // Determine default owner for legacy data (best-effort)
        $default_owner = get_current_user_id();
        if (empty($default_owner)) {
            $admins = get_users(array(
                'role__in' => array('administrator'),
                'number' => 1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'fields' => array('ID'),
            ));
            if (!empty($admins) && !empty($admins[0]->ID)) {
                $default_owner = intval($admins[0]->ID);
            } else {
                $default_owner = 0;
            }
        }
        
        // Check if task_type column exists
        $columns = $wpdb->get_col("DESCRIBE $table_tasks");
        $has_task_type = in_array('task_type', $columns);
        $has_task_category = in_array('task_category', $columns);
        $has_days_of_week = in_array('days_of_week', $columns);
        $has_frequency = in_array('frequency', $columns);
        $has_tasks_owner = in_array('owner_user_id', $columns);
        
        // Add task_type column if it doesn't exist
        if (!$has_task_type) {
            $wpdb->query("ALTER TABLE $table_tasks ADD COLUMN task_type varchar(20) DEFAULT 'daily' AFTER description");
        }
        
        // Add days_of_week column if it doesn't exist
        if (!$has_days_of_week) {
            $wpdb->query("ALTER TABLE $table_tasks ADD COLUMN days_of_week varchar(50) DEFAULT NULL AFTER task_type");
        }
        
        // Add task_category column if it doesn't exist
        if (!$has_task_category) {
            $wpdb->query("ALTER TABLE $table_tasks ADD COLUMN task_category varchar(20) DEFAULT 'povinne' AFTER days_of_week");
        }
        
        // Add shared_task column if it doesn't exist
        $has_shared_task = in_array('shared_task', $columns);
        if (!$has_shared_task) {
            $wpdb->query("ALTER TABLE $table_tasks ADD COLUMN shared_task tinyint(1) DEFAULT 0 AFTER rotation_enabled");
        }

        $has_task_icon = in_array('icon', $columns);
        if (!$has_task_icon) {
            $wpdb->query("ALTER TABLE $table_tasks ADD COLUMN icon varchar(100) DEFAULT NULL AFTER rating");
        }

        if (!$has_tasks_owner) {
            $wpdb->query("ALTER TABLE $table_tasks ADD COLUMN owner_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER id");
        }
        
        // Remove frequency column if it exists
        if ($has_frequency) {
            $wpdb->query("ALTER TABLE $table_tasks DROP COLUMN frequency");
        }
        
        // Migrate existing task_type to days_of_week if days_of_week is NULL
        if ($has_task_type && $has_days_of_week) {
            // Migrate 'daily' -> [1,2,3,4,5] (PO-PI)
            $wpdb->query("UPDATE $table_tasks SET days_of_week = '1,2,3,4,5' WHERE task_type = 'daily' AND (days_of_week IS NULL OR days_of_week = '')");
            
            // Legacy: 'weekend' -> Saturday-only [6]
            $wpdb->query("UPDATE $table_tasks SET days_of_week = '6' WHERE task_type = 'weekend' AND (days_of_week IS NULL OR days_of_week = '')");
            
            // Migrate 'weekly' -> [1,2,3,4,5,6,0] (všetky dni)
            $wpdb->query("UPDATE $table_tasks SET days_of_week = '1,2,3,4,5,6,0' WHERE task_type = 'weekly' AND (days_of_week IS NULL OR days_of_week = '')");
            
            // Default for NULL/empty task_type -> [6] (SO)
            $wpdb->query("UPDATE $table_tasks SET days_of_week = '6' WHERE (task_type IS NULL OR task_type = '') AND (days_of_week IS NULL OR days_of_week = '')");

            // IMPORTANT:
            // The app uses days_of_week to mean "task repeats on selected days".
            // For points awarding/duplicate prevention we must treat these as daily tasks,
            // otherwise a task can only award points once per week (legacy behavior).
            // Keep tasks without days_of_week untouched (they may be true weekly/monthly chores).
            $wpdb->query("UPDATE $table_tasks SET task_type = 'daily' WHERE (days_of_week IS NOT NULL AND days_of_week != '') AND (task_type IS NULL OR task_type = '' OR task_type != 'daily')");
        }
        
        // Remove legacy 'weekend' completely (we only use explicit days_of_week now).
        if ($has_task_type) {
            $wpdb->query("UPDATE $table_tasks SET task_type = 'daily' WHERE task_type = 'weekend'");
        }

        // Update existing tasks without task_type to 'daily'
        if ($has_task_type) {
            $wpdb->query("UPDATE $table_tasks SET task_type = 'daily' WHERE task_type IS NULL OR task_type = ''");
        }
        
        // Update existing tasks without task_category to 'povinne'
        if ($has_task_category) {
            $wpdb->query("UPDATE $table_tasks SET task_category = 'povinne' WHERE task_category IS NULL OR task_category = ''");
        }
        
        // Migrate children table - add email, password, color columns
        $table_children = $wpdb->prefix . 'rodinne_ulohy_children';
        $children_columns = $wpdb->get_col("DESCRIBE $table_children");
        $has_email = in_array('email', $children_columns);
        $has_password = in_array('password', $children_columns);
        $has_color = in_array('color', $children_columns);
        $has_login_code = in_array('login_code', $children_columns);
        $has_children_owner = in_array('owner_user_id', $children_columns);
        $has_sort_order = in_array('sort_order', $children_columns);
        
        if (!$has_email) {
            $wpdb->query("ALTER TABLE $table_children ADD COLUMN email varchar(255) DEFAULT NULL AFTER name");
        }
        if (!$has_password) {
            $wpdb->query("ALTER TABLE $table_children ADD COLUMN password varchar(255) DEFAULT NULL AFTER email");
        }
        if (!$has_color) {
            $wpdb->query("ALTER TABLE $table_children ADD COLUMN color varchar(7) DEFAULT '#4CAF50' AFTER avatar_url");
        }
        if (!$has_login_code) {
            $wpdb->query("ALTER TABLE $table_children ADD COLUMN login_code varchar(6) DEFAULT NULL AFTER color");
        }
        if (!$has_children_owner) {
            $wpdb->query("ALTER TABLE $table_children ADD COLUMN owner_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER id");
        }
        if (!$has_sort_order) {
            $wpdb->query("ALTER TABLE $table_children ADD COLUMN sort_order int(11) NOT NULL DEFAULT 0 AFTER owner_user_id");
        }

        // Ensure unique index on login_code
        $login_code_index = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = %s
             AND INDEX_NAME = %s",
            $table_children,
            'login_code'
        ));
        if (empty($login_code_index)) {
            // Ignore error if duplicates exist; codes will be generated below for empty rows only.
            $wpdb->query("ALTER TABLE $table_children ADD UNIQUE KEY login_code (login_code)");
        }

        // Backfill missing login codes
        $missing = $wpdb->get_col("SELECT id FROM $table_children WHERE login_code IS NULL OR login_code = ''");
        if (!empty($missing)) {
            foreach ($missing as $cid) {
                self::ensure_child_login_code(intval($cid));
            }
        }

        // Backfill owner_user_id for legacy data (children/tasks)
        if (!empty($default_owner)) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_children SET owner_user_id = %d WHERE (owner_user_id IS NULL OR owner_user_id = 0)",
                $default_owner
            ));
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_tasks SET owner_user_id = %d WHERE (owner_user_id IS NULL OR owner_user_id = 0)",
                $default_owner
            ));
        }

        // Backfill sort_order for legacy children (per owner) to stable order by id.
        if ($has_sort_order || !$has_sort_order) {
            $owners = $wpdb->get_col("SELECT DISTINCT owner_user_id FROM $table_children WHERE owner_user_id IS NOT NULL AND owner_user_id > 0");
            if (!empty($owners)) {
                foreach ($owners as $oid) {
                    $oid = intval($oid);
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT id FROM $table_children WHERE owner_user_id = %d ORDER BY sort_order ASC, id ASC",
                        $oid
                    ));
                    $i = 1;
                    foreach ($rows as $r) {
                        $wpdb->update(
                            $table_children,
                            array('sort_order' => $i),
                            array('id' => intval($r->id)),
                            array('%d'),
                            array('%d')
                        );
                        $i++;
                    }
                }
            }
        }
        
        // Ensure rewards tables exist on update
        $table_rewards = $wpdb->prefix . 'rodinne_ulohy_rewards';
        $sql_rewards = "CREATE TABLE $table_rewards (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            title varchar(255) NOT NULL,
            category varchar(255) DEFAULT NULL,
            details varchar(255) DEFAULT NULL,
            icon varchar(100) DEFAULT NULL,
            points_cost int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY owner_user_id (owner_user_id)
        ) $charset_collate;";
        
        $table_reward_purchases = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';
        $sql_reward_purchases = "CREATE TABLE $table_reward_purchases (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            reward_id bigint(20) UNSIGNED NOT NULL,
            child_id bigint(20) UNSIGNED NOT NULL,
            points_spent int(11) NOT NULL,
            status varchar(20) DEFAULT 'active',
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY reward_child (reward_id, child_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        $table_task_library = $wpdb->prefix . 'rodinne_ulohy_task_library';
        $sql_task_library = "CREATE TABLE $table_task_library (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            task_type varchar(20) DEFAULT 'daily',
            days_of_week varchar(50) DEFAULT NULL,
            task_category varchar(20) DEFAULT 'povinne',
            rotation_enabled tinyint(1) DEFAULT 1,
            shared_task tinyint(1) DEFAULT 0,
            estimated_time int(11) DEFAULT NULL,
            rating int(11) DEFAULT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        $table_reward_library = $wpdb->prefix . 'rodinne_ulohy_reward_library';
        $sql_reward_library = "CREATE TABLE $table_reward_library (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            category varchar(255) DEFAULT NULL,
            details varchar(255) DEFAULT NULL,
            icon varchar(100) DEFAULT NULL,
            points_cost int(11) NOT NULL DEFAULT 0,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_rewards);
        dbDelta($sql_task_library);
        dbDelta($sql_reward_library);
        dbDelta($sql_reward_purchases);

        // Ensure packages table has owner_user_id (legacy installs)
        $table_packages = $wpdb->prefix . 'rodinne_ulohy_packages';
        $pkg_cols = $wpdb->get_col("DESCRIBE $table_packages");
        if (!in_array('owner_user_id', $pkg_cols)) {
            $wpdb->query("ALTER TABLE $table_packages ADD COLUMN owner_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER id");
        }

        // Backfill owner_user_id for legacy rewards/packages
        if (!empty($default_owner)) {
            $reward_cols = $wpdb->get_col("DESCRIBE $table_rewards");
            if (in_array('owner_user_id', $reward_cols)) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table_rewards SET owner_user_id = %d WHERE (owner_user_id IS NULL OR owner_user_id = 0)",
                    $default_owner
                ));
            }
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_packages SET owner_user_id = %d WHERE (owner_user_id IS NULL OR owner_user_id = 0)",
                $default_owner
            ));
        }

        // API tokens table (mobile-friendly auth)
        $table_tokens = $wpdb->prefix . 'rodinne_ulohy_api_tokens';
        $sql_tokens = "CREATE TABLE $table_tokens (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            token_hash char(64) NOT NULL,
            subject_type varchar(20) NOT NULL,
            subject_id bigint(20) UNSIGNED NOT NULL,
            expires_at datetime DEFAULT NULL,
            revoked tinyint(1) DEFAULT 0,
            last_used_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            KEY subject (subject_type, subject_id),
            KEY revoked (revoked),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        dbDelta($sql_tokens);

        // Family invites table (invite-only access for additional adults)
        $table_invites = $wpdb->prefix . 'rodinne_ulohy_invites';
        $sql_invites = "CREATE TABLE $table_invites (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_user_id bigint(20) UNSIGNED NOT NULL,
            inviter_user_id bigint(20) UNSIGNED NOT NULL,
            email varchar(255) NOT NULL,
            role varchar(20) DEFAULT 'parent',
            token_hash char(64) NOT NULL,
            expires_at datetime DEFAULT NULL,
            revoked tinyint(1) DEFAULT 0,
            accepted_user_id bigint(20) UNSIGNED DEFAULT NULL,
            accepted_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            KEY owner_user_id (owner_user_id),
            KEY inviter_user_id (inviter_user_id),
            KEY email (email),
            KEY revoked (revoked),
            KEY expires_at (expires_at),
            KEY accepted_user_id (accepted_user_id)
        ) $charset_collate;";
        dbDelta($sql_invites);

        // Feedback table (ensure it exists on updates)
        $table_feedback = $wpdb->prefix . 'rodinne_ulohy_feedback';
        $sql_feedback = "CREATE TABLE $table_feedback (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subject_type varchar(20) NOT NULL,
            subject_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            name varchar(255) NOT NULL,
            path varchar(255) DEFAULT NULL,
            text longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY subject (subject_type, subject_id)
        ) $charset_collate;";
        dbDelta($sql_feedback);

        // If table existed before we added "path", ensure the column exists (dbDelta can be flaky depending on schema diffs).
        try {
            $fb_cols = $wpdb->get_col("DESCRIBE $table_feedback");
            if (is_array($fb_cols) && !in_array('path', $fb_cols, true)) {
                $wpdb->query("ALTER TABLE $table_feedback ADD COLUMN path varchar(255) DEFAULT NULL AFTER name");
            }
        } catch (Exception $e) {
            // ignore (best-effort migration)
        }
    }

    // -----------------------
    // Feedback
    // -----------------------
    public static function add_feedback($subject_type, $subject_id, $name, $text, $path = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_feedback';

        $subject_type = sanitize_text_field($subject_type);
        $subject_id = intval($subject_id);
        $name = sanitize_text_field($name);
        $path = sanitize_text_field($path);
        // Keep new lines, but strip tags and normalize.
        $text = sanitize_textarea_field($text);

        if (empty($text)) {
            return false;
        }

        // Backward-compatible insert: older installs may not have the "path" column yet.
        $has_path = false;
        try {
            $cols = $wpdb->get_col("DESCRIBE $table");
            if (is_array($cols) && in_array('path', $cols, true)) {
                $has_path = true;
            }
        } catch (Exception $e) {
            $has_path = false;
        }

        $data = array(
            'subject_type' => $subject_type,
            'subject_id' => $subject_id,
            'name' => $name ? $name : '',
            'text' => $text,
        );
        $format = array('%s', '%d', '%s', '%s');
        if ($has_path) {
            $data['path'] = $path ? $path : null;
            // Insert order must match formats; rebuild to be safe.
            $data = array(
                'subject_type' => $subject_type,
                'subject_id' => $subject_id,
                'name' => $name ? $name : '',
                'path' => $path ? $path : null,
                'text' => $text,
            );
            $format = array('%s', '%d', '%s', '%s', '%s');
        }

        $res = $wpdb->insert($table, $data, $format);

        // If insert failed and we tried with path, retry without it (in case schema is stale).
        if ($res === false && $has_path) {
            $res = $wpdb->insert(
                $table,
                array(
                    'subject_type' => $subject_type,
                    'subject_id' => $subject_id,
                    'name' => $name ? $name : '',
                    'text' => $text,
                ),
                array('%s', '%d', '%s', '%s')
            );
        }

        if ($res === false) {
            // Helpful for debugging server-side.
            error_log('Rodinne Ulohy: add_feedback failed: ' . $wpdb->last_error);
        }

        return $res !== false ? intval($wpdb->insert_id) : false;
    }

    public static function get_feedback_entries($limit = 200, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_feedback';
        $limit = max(1, min(1000, intval($limit)));
        $offset = max(0, intval($offset));

        // Note: LIMIT/OFFSET are ints; prepare safely.
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    public static function delete_feedback($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_feedback';
        $id = intval($id);
        if (!$id) return false;
        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    // -----------------------
    // Tasks import (template)
    // -----------------------
    public static function import_tasks_from_owner($source_owner_user_id, $target_owner_user_id) {
        global $wpdb;

        $source_owner_user_id = intval($source_owner_user_id);
        $target_owner_user_id = intval($target_owner_user_id);
        if (!$source_owner_user_id || !$target_owner_user_id) return false;

        $tasks_table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $links_table = $wpdb->prefix . 'rodinne_ulohy_task_links';
        $excl_table  = $wpdb->prefix . 'rodinne_ulohy_task_exclusions';

        // Load source tasks
        $src_tasks = self::get_tasks(null, null, $source_owner_user_id);
        if (empty($src_tasks)) {
            return array('imported' => 0);
        }

        // Ensure target is empty (safety)
        $target_existing = self::get_tasks(null, null, $target_owner_user_id);
        if (!empty($target_existing)) {
            return new WP_Error('ru_invalid', __('Cieľ už obsahuje úlohy', 'rodinne-ulohy'));
        }

        $id_map = array(); // old_id => new_id
        $imported = 0;
        $has_rotation = false;

        foreach ($src_tasks as $t) {
            $payload = array(
                'owner_user_id' => $target_owner_user_id,
                'package_id' => null, // do not import packages
                'name' => sanitize_text_field($t->name ?? ''),
                'description' => sanitize_textarea_field($t->description ?? ''),
                'task_type' => sanitize_text_field($t->task_type ?? 'daily'),
                'days_of_week' => sanitize_text_field($t->days_of_week ?? ''),
                'task_category' => sanitize_text_field($t->task_category ?? 'povinne'),
                'rotation_enabled' => !empty($t->rotation_enabled) ? 1 : 0,
                'shared_task' => !empty($t->shared_task) ? 1 : 0,
                'estimated_time' => isset($t->estimated_time) && $t->estimated_time !== null ? intval($t->estimated_time) : null,
                'rating' => isset($t->rating) && $t->rating !== null ? intval($t->rating) : null,
            );

            $ok = $wpdb->insert(
                $tasks_table,
                $payload,
                array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
            );
            if ($ok === false) {
                error_log('Rodinne Ulohy: import_tasks insert failed: ' . $wpdb->last_error);
                continue;
            }
            $new_id = intval($wpdb->insert_id);
            $old_id = intval($t->id);
            if ($old_id && $new_id) {
                $id_map[$old_id] = $new_id;
            }
            $imported++;
            if (!empty($t->rotation_enabled)) $has_rotation = true;
        }

        if (empty($id_map)) {
            return array('imported' => 0);
        }

        // Copy locked links between source tasks
        $src_links = $wpdb->get_results($wpdb->prepare(
            "SELECT l.task_id, l.linked_task_id
             FROM $links_table l
             INNER JOIN $tasks_table t1 ON t1.id = l.task_id
             INNER JOIN $tasks_table t2 ON t2.id = l.linked_task_id
             WHERE t1.owner_user_id = %d AND t2.owner_user_id = %d",
            $source_owner_user_id,
            $source_owner_user_id
        ));
        if (!empty($src_links)) {
            foreach ($src_links as $ln) {
                $a_old = intval($ln->task_id);
                $b_old = intval($ln->linked_task_id);
                if (empty($id_map[$a_old]) || empty($id_map[$b_old])) continue;
                $a = intval($id_map[$a_old]);
                $b = intval($id_map[$b_old]);
                if ($a <= 0 || $b <= 0 || $a === $b) continue;
                $x = min($a, $b);
                $y = max($a, $b);
                // Ignore duplicates (unique key)
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO $links_table (task_id, linked_task_id) VALUES (%d, %d)",
                    $x,
                    $y
                ));
            }
        }

        // Copy exclusions between source tasks
        $src_excl = $wpdb->get_results($wpdb->prepare(
            "SELECT e.task_id, e.excluded_task_id
             FROM $excl_table e
             INNER JOIN $tasks_table t1 ON t1.id = e.task_id
             INNER JOIN $tasks_table t2 ON t2.id = e.excluded_task_id
             WHERE t1.owner_user_id = %d AND t2.owner_user_id = %d",
            $source_owner_user_id,
            $source_owner_user_id
        ));
        if (!empty($src_excl)) {
            foreach ($src_excl as $ex) {
                $a_old = intval($ex->task_id);
                $b_old = intval($ex->excluded_task_id);
                if (empty($id_map[$a_old]) || empty($id_map[$b_old])) continue;
                $a = intval($id_map[$a_old]);
                $b = intval($id_map[$b_old]);
                if ($a <= 0 || $b <= 0 || $a === $b) continue;
                $x = min($a, $b);
                $y = max($a, $b);
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO $excl_table (task_id, excluded_task_id) VALUES (%d, %d)",
                    $x,
                    $y
                ));
            }
        }

        // If imported rotating tasks, mark for week regeneration.
        if ($has_rotation && $target_owner_user_id) {
            update_option('rodinne_ulohy_needs_regen_' . $target_owner_user_id, 1, false);
        }

        return array('imported' => $imported);
    }

    public static function import_rewards_from_owner($source_owner_user_id, $target_owner_user_id) {
        global $wpdb;

        $source_owner_user_id = intval($source_owner_user_id);
        $target_owner_user_id = intval($target_owner_user_id);
        if (!$source_owner_user_id || !$target_owner_user_id) return false;

        self::ensure_rewards_owner_column();
        $rewards_table = $wpdb->prefix . 'rodinne_ulohy_rewards';

        $src_rewards = self::get_rewards($source_owner_user_id);
        if (empty($src_rewards)) {
            return array('imported' => 0);
        }

        $target_existing = self::get_rewards($target_owner_user_id);
        if (!empty($target_existing)) {
            return new WP_Error('ru_invalid', __('Cieľ už obsahuje odmeny', 'rodinne-ulohy'));
        }

        $imported = 0;
        foreach ($src_rewards as $reward) {
            $payload = array(
                'owner_user_id' => $target_owner_user_id,
                'title' => sanitize_text_field($reward->title ?? ''),
                'category' => sanitize_text_field($reward->category ?? ''),
                'details' => sanitize_text_field($reward->details ?? ''),
                'icon' => sanitize_text_field($reward->icon ?? ''),
                'points_cost' => isset($reward->points_cost) ? max(0, intval($reward->points_cost)) : 0,
            );

            if (empty($payload['title'])) {
                continue;
            }

            $ok = $wpdb->insert(
                $rewards_table,
                $payload,
                array('%d', '%s', '%s', '%s', '%s', '%d')
            );

            if ($ok === false) {
                error_log('Rodinne Ulohy: import_rewards insert failed: ' . $wpdb->last_error);
                continue;
            }
            $imported++;
        }

        wp_cache_delete('rewards_list', 'rodinne_ulohy');
        wp_cache_flush();

        return array('imported' => $imported);
    }

    public static function get_task_library_items() {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_task_library';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC, name ASC, id ASC");
    }

    public static function get_task_library_item($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_task_library';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($id)));
    }

    public static function save_task_library_item($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_task_library';

        $id = intval($data['id'] ?? 0);
        $days_of_week = sanitize_text_field($data['days_of_week'] ?? '');
        $task_type = sanitize_text_field($data['task_type'] ?? 'daily');
        if (!empty($days_of_week)) {
            $task_type = 'daily';
        }

        $payload = array(
            'name' => sanitize_text_field($data['name'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'task_type' => $task_type,
            'days_of_week' => $days_of_week,
            'task_category' => sanitize_text_field($data['task_category'] ?? 'povinne'),
            'rotation_enabled' => !empty($data['rotation_enabled']) ? 1 : 0,
            'shared_task' => 0,
            'estimated_time' => isset($data['estimated_time']) && $data['estimated_time'] !== '' ? intval($data['estimated_time']) : null,
            'rating' => isset($data['rating']) && $data['rating'] !== '' ? max(0, intval($data['rating'])) : 0,
            'sort_order' => isset($data['sort_order']) && $data['sort_order'] !== '' ? intval($data['sort_order']) : 0,
        );

        if (empty($payload['name'])) {
            return new WP_Error('ru_invalid', __('Názov úlohy je povinný', 'rodinne-ulohy'));
        }

        $formats = array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d');

        if ($id > 0) {
            $ok = $wpdb->update($table, $payload, array('id' => $id), $formats, array('%d'));
            if ($ok === false) {
                return false;
            }
            return $id;
        }

        $ok = $wpdb->insert($table, $payload, $formats);
        if ($ok === false) {
            return false;
        }

        return intval($wpdb->insert_id);
    }

    public static function delete_task_library_item($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_task_library';
        return $wpdb->delete($table, array('id' => intval($id)), array('%d'));
    }

    public static function import_tasks_from_library($target_owner_user_id, $selected_ids = array()) {
        global $wpdb;

        $target_owner_user_id = intval($target_owner_user_id);
        if (!$target_owner_user_id) return false;

        $tasks_table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $items = self::get_task_library_items();
        if (empty($items)) {
            return array('imported' => 0);
        }

        $selected_ids = array_values(array_unique(array_filter(array_map('intval', is_array($selected_ids) ? $selected_ids : array()))));
        if (!empty($selected_ids)) {
            $items = array_values(array_filter($items, function($item) use ($selected_ids) {
                return in_array(intval($item->id ?? 0), $selected_ids, true);
            }));
        }
        if (empty($items)) {
            return array('imported' => 0);
        }

        $imported = 0;
        $has_rotation = false;
        foreach ($items as $item) {
            $payload = array(
                'owner_user_id' => $target_owner_user_id,
                'package_id' => null,
                'name' => sanitize_text_field($item->name ?? ''),
                'description' => sanitize_textarea_field($item->description ?? ''),
                'task_type' => sanitize_text_field($item->task_type ?? 'daily'),
                'days_of_week' => sanitize_text_field($item->days_of_week ?? ''),
                'task_category' => sanitize_text_field($item->task_category ?? 'povinne'),
                'rotation_enabled' => !empty($item->rotation_enabled) ? 1 : 0,
                'shared_task' => 0,
                'estimated_time' => isset($item->estimated_time) && $item->estimated_time !== null ? intval($item->estimated_time) : null,
                'rating' => isset($item->rating) && $item->rating !== null ? intval($item->rating) : 0,
            );

            $ok = $wpdb->insert(
                $tasks_table,
                $payload,
                array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
            );
            if ($ok === false) {
                continue;
            }
            $imported++;
            if (!empty($item->rotation_enabled)) {
                $has_rotation = true;
            }
        }

        if ($has_rotation) {
            update_option('rodinne_ulohy_needs_regen_' . $target_owner_user_id, 1, false);
        }

        return array('imported' => $imported);
    }

    public static function get_reward_library_items() {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_reward_library';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC, title ASC, id ASC");
    }

    public static function get_reward_library_item($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_reward_library';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($id)));
    }

    public static function save_reward_library_item($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_reward_library';

        $id = intval($data['id'] ?? 0);
        $payload = array(
            'title' => sanitize_text_field($data['title'] ?? ''),
            'category' => sanitize_text_field($data['category'] ?? ''),
            'details' => sanitize_text_field($data['details'] ?? ''),
            'icon' => sanitize_text_field($data['icon'] ?? ''),
            'points_cost' => isset($data['points_cost']) ? max(0, intval($data['points_cost'])) : 0,
            'sort_order' => isset($data['sort_order']) && $data['sort_order'] !== '' ? intval($data['sort_order']) : 0,
        );

        if (empty($payload['title'])) {
            return new WP_Error('ru_invalid', __('Názov odmeny je povinný', 'rodinne-ulohy'));
        }

        $formats = array('%s', '%s', '%s', '%s', '%d', '%d');

        if ($id > 0) {
            $ok = $wpdb->update($table, $payload, array('id' => $id), $formats, array('%d'));
            if ($ok === false) {
                return false;
            }
            return $id;
        }

        $ok = $wpdb->insert($table, $payload, $formats);
        if ($ok === false) {
            return false;
        }

        return intval($wpdb->insert_id);
    }

    public static function delete_reward_library_item($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_reward_library';
        return $wpdb->delete($table, array('id' => intval($id)), array('%d'));
    }

    private static function get_task_library_max_sort_order() {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_task_library';
        return max(0, intval($wpdb->get_var("SELECT MAX(sort_order) FROM $table")));
    }

    private static function get_reward_library_max_sort_order() {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_reward_library';
        return max(0, intval($wpdb->get_var("SELECT MAX(sort_order) FROM $table")));
    }

    /**
     * Copy all tasks from a user's family into the global task library.
     */
    public static function import_tasks_to_library_from_owner($source_owner_user_id) {
        $source_owner_user_id = intval($source_owner_user_id);
        if (!$source_owner_user_id) {
            return new WP_Error('ru_invalid', __('Neplatný zdrojový používateľ.', 'rodinne-ulohy'));
        }

        $tasks = self::get_tasks(null, null, $source_owner_user_id);
        if (empty($tasks)) {
            return array('imported' => 0);
        }

        $sort_order = self::get_task_library_max_sort_order();
        $imported = 0;

        foreach ($tasks as $task) {
            $name = sanitize_text_field($task->name ?? '');
            if ($name === '') {
                continue;
            }

            $rating = isset($task->rating) && $task->rating !== null ? max(0, intval($task->rating)) : 0;
            if ($rating === 0 && isset($task->points) && $task->points !== null) {
                $rating = max(0, intval($task->points));
            }

            $sort_order++;
            $saved = self::save_task_library_item(array(
                'name' => $name,
                'description' => $task->description ?? '',
                'task_type' => $task->task_type ?? 'daily',
                'days_of_week' => $task->days_of_week ?? '',
                'task_category' => $task->task_category ?? 'povinne',
                'rotation_enabled' => !empty($task->rotation_enabled) ? 1 : 0,
                'estimated_time' => isset($task->estimated_time) && $task->estimated_time !== null ? intval($task->estimated_time) : '',
                'rating' => $rating,
                'sort_order' => $sort_order,
            ));

            if ($saved && !is_wp_error($saved)) {
                $imported++;
            }
        }

        return array('imported' => $imported);
    }

    /**
     * Copy all rewards from a user's family into the global reward library.
     */
    public static function import_rewards_to_library_from_owner($source_owner_user_id) {
        $source_owner_user_id = intval($source_owner_user_id);
        if (!$source_owner_user_id) {
            return new WP_Error('ru_invalid', __('Neplatný zdrojový používateľ.', 'rodinne-ulohy'));
        }

        self::ensure_rewards_owner_column();
        $rewards = self::get_rewards($source_owner_user_id);
        if (empty($rewards)) {
            return array('imported' => 0);
        }

        $sort_order = self::get_reward_library_max_sort_order();
        $imported = 0;

        foreach ($rewards as $reward) {
            $title = sanitize_text_field($reward->title ?? '');
            if ($title === '') {
                continue;
            }

            $sort_order++;
            $saved = self::save_reward_library_item(array(
                'title' => $title,
                'category' => $reward->category ?? '',
                'details' => $reward->details ?? '',
                'icon' => $reward->icon ?? '',
                'points_cost' => isset($reward->points_cost) ? max(0, intval($reward->points_cost)) : 0,
                'sort_order' => $sort_order,
            ));

            if ($saved && !is_wp_error($saved)) {
                $imported++;
            }
        }

        return array('imported' => $imported);
    }

    public static function import_rewards_from_library($target_owner_user_id, $selected_ids = array()) {
        global $wpdb;

        $target_owner_user_id = intval($target_owner_user_id);
        if (!$target_owner_user_id) return false;

        self::ensure_rewards_owner_column();
        $rewards_table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        $items = self::get_reward_library_items();
        if (empty($items)) {
            return array('imported' => 0);
        }

        $selected_ids = array_values(array_unique(array_filter(array_map('intval', is_array($selected_ids) ? $selected_ids : array()))));
        if (!empty($selected_ids)) {
            $items = array_values(array_filter($items, function($item) use ($selected_ids) {
                return in_array(intval($item->id ?? 0), $selected_ids, true);
            }));
        }
        if (empty($items)) {
            return array('imported' => 0);
        }

        $imported = 0;
        foreach ($items as $item) {
            $payload = array(
                'owner_user_id' => $target_owner_user_id,
                'title' => sanitize_text_field($item->title ?? ''),
                'category' => sanitize_text_field($item->category ?? ''),
                'details' => sanitize_text_field($item->details ?? ''),
                'icon' => sanitize_text_field($item->icon ?? ''),
                'points_cost' => isset($item->points_cost) ? max(0, intval($item->points_cost)) : 0,
            );

            if (empty($payload['title'])) {
                continue;
            }

            $ok = $wpdb->insert(
                $rewards_table,
                $payload,
                array('%d', '%s', '%s', '%s', '%s', '%d')
            );
            if ($ok === false) {
                continue;
            }
            $imported++;
        }

        wp_cache_delete('rewards_list', 'rodinne_ulohy');
        wp_cache_flush();

        return array('imported' => $imported);
    }

    // -----------------------
    // Dangerous resets (owner-scoped)
    // -----------------------
    public static function reset_tasks_for_owner($owner_user_id) {
        global $wpdb;
        $owner_user_id = intval($owner_user_id);
        if (!$owner_user_id) return false;

        $tasks_table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $assign_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        $task_children_table = $wpdb->prefix . 'rodinne_ulohy_task_children';
        $links_table = $wpdb->prefix . 'rodinne_ulohy_task_links';
        $excl_table = $wpdb->prefix . 'rodinne_ulohy_task_exclusions';

        $task_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $tasks_table WHERE owner_user_id = %d",
            $owner_user_id
        ));
        $task_ids = array_values(array_filter(array_map('intval', $task_ids ?: array())));
        if (empty($task_ids)) {
            return array('tasks' => 0);
        }

        $in = implode(',', array_fill(0, count($task_ids), '%d'));
        // Delete relations first
        $wpdb->query($wpdb->prepare("DELETE FROM $task_children_table WHERE task_id IN ($in)", ...$task_ids));
        $wpdb->query($wpdb->prepare("DELETE FROM $assign_table WHERE task_id IN ($in)", ...$task_ids));
        $wpdb->query($wpdb->prepare("DELETE FROM $links_table WHERE task_id IN ($in) OR linked_task_id IN ($in)", ...array_merge($task_ids, $task_ids)));
        $wpdb->query($wpdb->prepare("DELETE FROM $excl_table WHERE task_id IN ($in) OR excluded_task_id IN ($in)", ...array_merge($task_ids, $task_ids)));

        $deleted_tasks = $wpdb->query($wpdb->prepare("DELETE FROM $tasks_table WHERE id IN ($in)", ...$task_ids));

        // Clean regen flag
        delete_option('rodinne_ulohy_needs_regen_' . $owner_user_id);

        return array('tasks' => intval($deleted_tasks));
    }

    public static function reset_children_for_owner($owner_user_id) {
        global $wpdb;
        $owner_user_id = intval($owner_user_id);
        if (!$owner_user_id) return false;

        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        $assign_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        $points_balance = $wpdb->prefix . 'rodinne_ulohy_points_balance';
        $points_history = $wpdb->prefix . 'rodinne_ulohy_points_history';
        $reward_purchases = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';
        $task_children_table = $wpdb->prefix . 'rodinne_ulohy_task_children';
        $package_children_table = $wpdb->prefix . 'rodinne_ulohy_package_children';

        $child_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $children_table WHERE owner_user_id = %d",
            $owner_user_id
        ));
        $child_ids = array_values(array_filter(array_map('intval', $child_ids ?: array())));
        if (empty($child_ids)) {
            return array('children' => 0);
        }

        $in = implode(',', array_fill(0, count($child_ids), '%d'));

        // Delete dependent data
        $wpdb->query($wpdb->prepare("DELETE FROM $assign_table WHERE child_id IN ($in)", ...$child_ids));
        $wpdb->query($wpdb->prepare("DELETE FROM $points_history WHERE child_id IN ($in)", ...$child_ids));
        $wpdb->query($wpdb->prepare("DELETE FROM $points_balance WHERE child_id IN ($in)", ...$child_ids));
        $wpdb->query($wpdb->prepare("DELETE FROM $reward_purchases WHERE child_id IN ($in)", ...$child_ids));
        $wpdb->query($wpdb->prepare("DELETE FROM $task_children_table WHERE child_id IN ($in)", ...$child_ids));
        $wpdb->query($wpdb->prepare("DELETE FROM $package_children_table WHERE child_id IN ($in)", ...$child_ids));

        $deleted_children = $wpdb->query($wpdb->prepare("DELETE FROM $children_table WHERE owner_user_id = %d", $owner_user_id));

        // Rotating tasks may need regeneration after children change
        update_option('rodinne_ulohy_needs_regen_' . $owner_user_id, 1, false);

        return array('children' => intval($deleted_children));
    }

    public static function reset_rewards_for_owner($owner_user_id) {
        global $wpdb;
        $owner_user_id = intval($owner_user_id);
        if (!$owner_user_id) return false;

        $rewards_table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        $reward_purchases = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';

        $reward_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $rewards_table WHERE owner_user_id = %d",
            $owner_user_id
        ));
        $reward_ids = array_values(array_filter(array_map('intval', $reward_ids ?: array())));
        if (empty($reward_ids)) {
            return array('rewards' => 0);
        }

        $in = implode(',', array_fill(0, count($reward_ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM $reward_purchases WHERE reward_id IN ($in)", ...$reward_ids));
        $deleted_rewards = $wpdb->query($wpdb->prepare("DELETE FROM $rewards_table WHERE id IN ($in)", ...$reward_ids));

        return array('rewards' => intval($deleted_rewards));
    }
    
    // Children methods
    public static function get_children($search = '', $owner_user_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_children';
        $owner_user_id = intval($owner_user_id);
        
        if (!empty($search)) {
            if ($owner_user_id) {
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table WHERE owner_user_id = %d AND name LIKE %s ORDER BY sort_order ASC, name ASC",
                    $owner_user_id,
                    '%' . $wpdb->esc_like($search) . '%'
                ));
            }
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE name LIKE %s ORDER BY name ASC",
                '%' . $wpdb->esc_like($search) . '%'
            ));
        }
        
        if ($owner_user_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE owner_user_id = %d ORDER BY sort_order ASC, name ASC",
                $owner_user_id
            ));
        }
        return $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC, name ASC");
    }
    
    public static function get_child($id, $owner_user_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_children';
        $owner_user_id = intval($owner_user_id);
        if ($owner_user_id) {
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND owner_user_id = %d", $id, $owner_user_id));
        }
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    public static function get_child_by_login_code($code) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_children';
        $code = preg_replace('/\D+/', '', strval($code));
        if (strlen($code) !== 6) return null;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE login_code = %s LIMIT 1", $code));
    }

    private static function generate_unique_login_code() {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_children';
        for ($i = 0; $i < 30; $i++) {
            $code = str_pad(strval(random_int(0, 999999)), 6, '0', STR_PAD_LEFT);
            if ($code === '000000') continue;
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE login_code = %s LIMIT 1", $code));
            if (empty($exists)) return $code;
        }
        // Fallback (extremely unlikely)
        return str_pad(strval(time() % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function ensure_child_login_code($child_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_children';
        $child_id = intval($child_id);
        if (!$child_id) return '';
        $current = $wpdb->get_var($wpdb->prepare("SELECT login_code FROM $table WHERE id = %d", $child_id));
        $current = $current ? strval($current) : '';
        if (preg_match('/^\d{6}$/', $current)) return $current;
        $code = self::generate_unique_login_code();
        $wpdb->update(
            $table,
            array('login_code' => $code),
            array('id' => $child_id),
            array('%s'),
            array('%d')
        );
        return $code;
    }
    
    public static function save_child($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_children';
        $children_table = $table;
        
        $payload = array(
            'name' => sanitize_text_field($data['name']),
            'email' => isset($data['email']) ? sanitize_email($data['email']) : null,
            'avatar_url' => esc_url_raw($data['avatar_url'] ?? ''),
            'color' => isset($data['color']) ? sanitize_hex_color($data['color']) : '#4CAF50'
        );

        // Always store owner on create; for updates, it is optional but supported.
        $payload['owner_user_id'] = isset($data['owner_user_id']) ? intval($data['owner_user_id']) : 0;
        
        // Only update password if provided (not empty)
        if (isset($data['password']) && !empty($data['password'])) {
            $payload['password'] = wp_hash_password($data['password']);
        }
        
        if (isset($data['id']) && $data['id']) {
            // On update, do not overwrite owner unless explicitly provided.
            if (!isset($data['owner_user_id'])) {
                unset($payload['owner_user_id']);
            }

            $format = array();
            foreach ($payload as $key => $value) {
                if ($key === 'owner_user_id') {
                    $format[] = '%d';
                } else {
                    $format[] = '%s';
                }
            }
            return $wpdb->update(
                $table,
                $payload,
                array('id' => intval($data['id'])),
                $format,
                array('%d')
            );
        } else {
            // New child: password is NOT required (child login is via login_code).
            // Set sort_order to the end for this owner (best-effort; if column doesn't exist, insert ignores it)
            $owner_id = intval($payload['owner_user_id'] ?? 0);
            if ($owner_id > 0) {
                $cols = $wpdb->get_col("DESCRIBE $children_table");
                if (is_array($cols) && in_array('sort_order', $cols, true)) {
                    $max = $wpdb->get_var($wpdb->prepare(
                        "SELECT MAX(sort_order) FROM $children_table WHERE owner_user_id = %d",
                        $owner_id
                    ));
                    $payload['sort_order'] = intval($max) + 1;
                }
            }
            return $wpdb->insert(
                $table,
                $payload,
                array_values(array_map(function ($key) {
                    if ($key === 'owner_user_id' || $key === 'sort_order') return '%d';
                    return '%s';
                }, array_keys($payload)))
            );
        }
    }
    
    public static function delete_child($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_children';
        return $wpdb->delete($table, array('id' => intval($id)), array('%d'));
    }
    
    // Package methods
    public static function get_packages() {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_packages';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }
    
    public static function get_package($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_packages';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public static function save_package($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_packages';
        
        if (isset($data['id']) && $data['id']) {
            return $wpdb->update(
                $table,
                array(
                    'name' => sanitize_text_field($data['name']),
                    'description' => sanitize_textarea_field($data['description'] ?? '')
                ),
                array('id' => intval($data['id'])),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            return $wpdb->insert(
                $table,
                array(
                    'name' => sanitize_text_field($data['name']),
                    'description' => sanitize_textarea_field($data['description'] ?? '')
                ),
                array('%s', '%s')
            );
        }
    }
    
    public static function delete_package($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_packages';
        return $wpdb->delete($table, array('id' => intval($id)), array('%d'));
    }
    
    public static function get_package_children($package_id, $owner_user_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_package_children';
        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        $owner_user_id = intval($owner_user_id);
        
        if ($owner_user_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT c.* FROM $children_table c
                INNER JOIN $table pc ON c.id = pc.child_id
                WHERE pc.package_id = %d AND c.owner_user_id = %d
                ORDER BY c.name ASC",
                $package_id,
                $owner_user_id
            ));
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.* FROM $children_table c
            INNER JOIN $table pc ON c.id = pc.child_id
            WHERE pc.package_id = %d
            ORDER BY c.name ASC",
            $package_id
        ));
    }
    
    public static function save_package_children($package_id, $child_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_package_children';
        
        // Delete existing assignments
        $wpdb->delete($table, array('package_id' => intval($package_id)), array('%d'));
        
        // Insert new assignments
        if (!empty($child_ids) && is_array($child_ids)) {
            foreach ($child_ids as $child_id) {
                $wpdb->insert(
                    $table,
                    array(
                        'package_id' => intval($package_id),
                        'child_id' => intval($child_id)
                    ),
                    array('%d', '%d')
                );
            }
        }
    }
    
    // Task methods
    public static function get_tasks($package_id = null, $task_category = null, $owner_user_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $owner_user_id = intval($owner_user_id);
        
        $where = array();
        $where_values = array();

        if ($owner_user_id) {
            $where[] = "owner_user_id = %d";
            $where_values[] = $owner_user_id;
        }
        
        if ($package_id) {
            $where[] = "package_id = %d";
            $where_values[] = $package_id;
        }
        
        if ($task_category) {
            $where[] = "task_category = %s";
            $where_values[] = $task_category;
        }
        
        $where_sql = '';
        if (!empty($where)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where);
            if (count($where_values) > 0) {
                $sql = "SELECT * FROM $table $where_sql ORDER BY name ASC";
                return $wpdb->get_results($wpdb->prepare($sql, ...$where_values));
            }
        }
        
        return $wpdb->get_results("SELECT * FROM $table $where_sql ORDER BY name ASC");
    }
    
    public static function get_task($id, $owner_user_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $owner_user_id = intval($owner_user_id);
        if ($owner_user_id) {
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND owner_user_id = %d", $id, $owner_user_id));
        }
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public static function save_task($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        
        // Process days_of_week - convert array to comma-separated string
        $days_of_week = '';
        if (isset($data['days_of_week']) && is_array($data['days_of_week'])) {
            $days_of_week = implode(',', array_map('intval', $data['days_of_week']));
        } elseif (isset($data['days_of_week']) && !empty($data['days_of_week'])) {
            $days_of_week = sanitize_text_field($data['days_of_week']);
        }
        
        $task_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'task_type' => sanitize_text_field($data['task_type'] ?? 'daily'),
            'days_of_week' => $days_of_week,
            'task_category' => sanitize_text_field($data['task_category'] ?? 'povinne'),
            // IMPORTANT: bool flags must respect falsey values (0/'0'/false)
            'rotation_enabled' => !empty($data['rotation_enabled']) ? 1 : 0,
            'shared_task' => !empty($data['shared_task']) ? 1 : 0,
        );

        if (isset($data['owner_user_id'])) {
            $task_data['owner_user_id'] = intval($data['owner_user_id']);
        }
        
        // Add optional fields only if they have values
        if (!empty($data['package_id'])) {
            $task_data['package_id'] = intval($data['package_id']);
        }
        if (!empty($data['estimated_time'])) {
            $task_data['estimated_time'] = intval($data['estimated_time']);
        }
        if (isset($data['rating']) && $data['rating'] !== null && $data['rating'] !== '') {
            $task_data['rating'] = intval($data['rating']);
        }
        if (array_key_exists('icon', $data)) {
            $icon = sanitize_text_field($data['icon'] ?? '');
            $icon = preg_replace('/[^a-zA-Z0-9_-]/', '', $icon);
            $task_data['icon'] = $icon !== '' ? $icon : null;
        }
        
        // Build format array based on actual data
        $format = array();
        foreach ($task_data as $key => $value) {
            if ($key === 'owner_user_id' || $key === 'package_id' || $key === 'estimated_time' || $key === 'rating' || $key === 'rotation_enabled' || $key === 'shared_task') {
                $format[] = '%d';
            } else {
                $format[] = '%s';
            }
        }
        
        if (isset($data['id']) && $data['id']) {
            return $wpdb->update(
                $table,
                $task_data,
                array('id' => intval($data['id'])),
                $format,
                array('%d')
            );
        } else {
            return $wpdb->insert($table, $task_data, $format);
        }
    }
    
    public static function delete_task($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        return $wpdb->delete($table, array('id' => intval($id)), array('%d'));
    }
    
    public static function get_package_task_count($package_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE package_id = %d",
            $package_id
        ));
    }
    
    public static function get_task_children($task_id, $owner_user_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_task_children';
        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        $owner_user_id = intval($owner_user_id);
        
        if ($owner_user_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT c.* FROM $children_table c
                INNER JOIN $table tc ON c.id = tc.child_id
                WHERE tc.task_id = %d AND c.owner_user_id = %d
                ORDER BY c.name ASC",
                $task_id,
                $owner_user_id
            ));
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.* FROM $children_table c
            INNER JOIN $table tc ON c.id = tc.child_id
            WHERE tc.task_id = %d
            ORDER BY c.name ASC",
            $task_id
        ));
    }
    
    public static function save_task_children($task_id, $child_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_task_children';
        
        // Delete existing assignments
        $wpdb->delete($table, array('task_id' => intval($task_id)), array('%d'));
        
        // Insert new assignments
        if (!empty($child_ids) && is_array($child_ids)) {
            foreach ($child_ids as $child_id) {
                $result = $wpdb->insert(
                    $table,
                    array(
                        'task_id' => intval($task_id),
                        'child_id' => intval($child_id)
                    ),
                    array('%d', '%d')
                );
                if ($result === false) {
                    error_log('Error inserting task_child: ' . $wpdb->last_error);
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get IDs of tasks locked (zomknuté) with given task (symmetric)
     */
    public static function get_locked_tasks($task_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_task_links';
        
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                CASE 
                    WHEN task_id = %d THEN linked_task_id 
                    ELSE task_id 
                END as linked_task_id
            FROM $table
            WHERE task_id = %d OR linked_task_id = %d",
            $task_id,
            $task_id,
            $task_id
        ));
        
        if (empty($rows)) {
            return array();
        }
        
        return array_map(function($row) {
            return intval($row->linked_task_id);
        }, $rows);
    }
    
    /**
     * Save locked tasks for a given task (replaces existing links)
     */
    public static function save_locked_tasks($task_id, $linked_task_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_task_links';
        
        $task_id = intval($task_id);
        
        // Remove existing links involving this task
        $wpdb->delete($table, array('task_id' => $task_id), array('%d'));
        $wpdb->delete($table, array('linked_task_id' => $task_id), array('%d'));
        
        if (empty($linked_task_ids) || !is_array($linked_task_ids)) {
            return true;
        }
        
        // Insert normalized pairs (smaller id first) to keep symmetry and uniqueness
        foreach ($linked_task_ids as $other_id) {
            $other_id = intval($other_id);
            if ($other_id <= 0 || $other_id === $task_id) {
                continue;
            }
            
            $a = min($task_id, $other_id);
            $b = max($task_id, $other_id);
            
            $wpdb->insert(
                $table,
                array(
                    'task_id' => $a,
                    'linked_task_id' => $b
                ),
                array('%d', '%d')
            );
        }
        
        return true;
    }
    
    /**
     * Get IDs of tasks that are excluded (vylúčené) with given task (symmetric)
     */
    public static function get_excluded_tasks($task_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_task_exclusions';
        
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                CASE 
                    WHEN task_id = %d THEN excluded_task_id 
                    ELSE task_id 
                END as excluded_task_id
            FROM $table
            WHERE task_id = %d OR excluded_task_id = %d",
            $task_id,
            $task_id,
            $task_id
        ));
        
        if (empty($rows)) {
            return array();
        }
        
        return array_map(function($row) {
            return intval($row->excluded_task_id);
        }, $rows);
    }
    
    /**
     * Save excluded tasks for a given task (replaces existing exclusions)
     */
    public static function save_excluded_tasks($task_id, $excluded_task_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_task_exclusions';
        
        $task_id = intval($task_id);
        
        // Remove existing exclusions involving this task
        $wpdb->delete($table, array('task_id' => $task_id), array('%d'));
        $wpdb->delete($table, array('excluded_task_id' => $task_id), array('%d'));
        
        if (empty($excluded_task_ids) || !is_array($excluded_task_ids)) {
            return true;
        }
        
        foreach ($excluded_task_ids as $other_id) {
            $other_id = intval($other_id);
            if ($other_id <= 0 || $other_id === $task_id) {
                continue;
            }
            
            $a = min($task_id, $other_id);
            $b = max($task_id, $other_id);
            
            $wpdb->insert(
                $table,
                array(
                    'task_id' => $a,
                    'excluded_task_id' => $b
                ),
                array('%d', '%d')
            );
        }
        
        return true;
    }
    
    public static function save_package_tasks($package_id, $task_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        
        // First, remove all tasks from this package
        $wpdb->update(
            $table,
            array('package_id' => null),
            array('package_id' => intval($package_id)),
            array('%d'),
            array('%d')
        );
        
        // Then, assign selected tasks to this package
        if (!empty($task_ids) && is_array($task_ids)) {
            foreach ($task_ids as $task_id) {
                $wpdb->update(
                    $table,
                    array('package_id' => intval($package_id)),
                    array('id' => intval($task_id)),
                    array('%d'),
                    array('%d')
                );
            }
        }
    }
    
    // Assignment methods
    public static function get_week_assignments($week_start = null, $owner_user_id = 0) {
        global $wpdb;
        $owner_user_id = intval($owner_user_id);
        
        if (!$week_start) {
            $week_start = self::get_current_week_start();
        }
        
        $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        $tasks_table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        
        if ($owner_user_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT a.*, t.name as task_name, t.description as task_description, 
                        t.task_type as task_type, t.rating as task_rating, t.task_category,
                        c.name as child_name, c.avatar_url as child_avatar
                FROM $assignments_table a
                INNER JOIN $tasks_table t ON a.task_id = t.id
                INNER JOIN $children_table c ON a.child_id = c.id
                WHERE a.week_start = %s AND c.owner_user_id = %d
                ORDER BY c.name ASC, t.name ASC",
                $week_start,
                $owner_user_id
            ));
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, t.name as task_name, t.description as task_description, 
                    t.task_type as task_type, t.rating as task_rating, t.task_category,
                    c.name as child_name, c.avatar_url as child_avatar
            FROM $assignments_table a
            INNER JOIN $tasks_table t ON a.task_id = t.id
            INNER JOIN $children_table c ON a.child_id = c.id
            WHERE a.week_start = %s
            ORDER BY c.name ASC, t.name ASC",
            $week_start
        ));
    }
    
    public static function get_child_assignments($child_id, $week_start = null, $owner_user_id = 0) {
        global $wpdb;
        $owner_user_id = intval($owner_user_id);
        
        if (!$week_start) {
            $week_start = self::get_current_week_start();
        }
        
        $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        $tasks_table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        
        if ($owner_user_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT a.*, t.name as task_name, t.description as task_description, t.task_type as task_type, t.rating as task_rating, t.task_category as task_category, t.icon as task_icon
                FROM $assignments_table a
                INNER JOIN $tasks_table t ON a.task_id = t.id
                INNER JOIN $children_table c ON a.child_id = c.id
                WHERE a.child_id = %d AND a.week_start = %s AND c.owner_user_id = %d
                ORDER BY t.task_category ASC, t.name ASC",
                $child_id,
                $week_start,
                $owner_user_id
            ));
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, t.name as task_name, t.description as task_description, t.task_type as task_type, t.rating as task_rating, t.task_category as task_category, t.icon as task_icon
            FROM $assignments_table a
            INNER JOIN $tasks_table t ON a.task_id = t.id
            WHERE a.child_id = %d AND a.week_start = %s
            ORDER BY t.task_category ASC, t.name ASC",
            $child_id,
            $week_start
        ));
    }
    
    public static function save_assignment($task_id, $child_id, $week_start, $status = 'todo') {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE task_id = %d AND child_id = %d AND week_start = %s",
            $task_id, $child_id, $week_start
        ));
        
        $data = array(
            'task_id' => intval($task_id),
            'child_id' => intval($child_id),
            'week_start' => $week_start,
            'status' => sanitize_text_field($status),
            'completed_at' => $status === 'completed' ? current_time('mysql') : null
        );
        
        if ($existing) {
            return $wpdb->update(
                $table,
                $data,
                array('id' => $existing),
                array('%d', '%d', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            return $wpdb->insert($table, $data, array('%d', '%d', '%s', '%s', '%s'));
        }
    }
    
    public static function update_assignment_status($assignment_id, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        
        $data = array(
            'status' => sanitize_text_field($status),
            'completed_at' => $status === 'completed' ? current_time('mysql') : null
        );
        
        return $wpdb->update(
            $table,
            $data,
            array('id' => intval($assignment_id)),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    public static function update_task_field($task_id, $field, $value) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        
        // Validate field name to prevent SQL injection
        $allowed_fields = array('task_type', 'days_of_week', 'task_category', 'rotation_enabled', 'shared_task', 'rating', 'name', 'description', 'package_id', 'estimated_time');
        if (!in_array($field, $allowed_fields)) {
            return false;
        }
        
        // Determine format based on field type
        $format = '%s';
        if (in_array($field, array('rotation_enabled', 'rating', 'package_id', 'estimated_time'))) {
            $format = '%d';
            $value = intval($value);
        } elseif ($field === 'description') {
            $format = '%s';
            $value = sanitize_textarea_field($value);
        } else {
            $format = '%s';
            $value = sanitize_text_field($value);
        }
        
        return $wpdb->update(
            $table,
            array($field => $value),
            array('id' => intval($task_id)),
            array($format),
            array('%d')
        );
    }
    
    public static function get_current_week_start() {
        return self::get_week_start_for_ts(current_time('timestamp'));
    }

    /**
     * Compute week_start ("monday this week") for an arbitrary WP-local timestamp.
     * Useful for dev/testing tools that simulate time.
     *
     * @param int|null $ts WP-local timestamp (as returned by current_time('timestamp'))
     * @return string Y-m-d
     */
    public static function get_week_start_for_ts($ts = null) {
        // IMPORTANT:
        // Use WP timezone consistently (not server PHP timezone), otherwise around midnight
        // we can compute the wrong week_start and accidentally rotate/penalize the wrong week.
        $ts = is_null($ts) ? current_time('timestamp') : intval($ts);
        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $dt = (new DateTimeImmutable('@' . $ts))->setTimezone($tz);
            $monday = $dt->modify('monday this week')->setTime(0, 0);
            return $monday->format('Y-m-d');
        } catch (Throwable $e) {
            // Fallback (legacy). Might be timezone-sensitive but keeps the app functional.
            $day_of_week = date('w', $ts); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
            $days_back = ($day_of_week == 0) ? 6 : ($day_of_week - 1);
            return date('Y-m-d', strtotime('-' . $days_back . ' days', $ts));
        }
    }
    
    public static function get_week_range($week_start) {
        $start = new DateTime($week_start);
        $end = clone $start;
        $end->modify('+6 days'); // Monday to Sunday (6 days)
        
        return array(
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'start_formatted' => $start->format('d.m.Y'),
            'end_formatted' => $end->format('d.m.Y')
        );
    }

    /**
     * Map JS getDay() (0=Sun..6=Sat) to Y-m-d within a week that starts on Monday.
     */
    public static function ymd_for_week_day($week_start, $day_w) {
        $week_start = sanitize_text_field($week_start);
        $day_w = intval($day_w);
        $offsets = array(1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 0 => 6);
        if ($week_start === '' || !isset($offsets[$day_w])) {
            return '';
        }

        try {
            $dt = new DateTime($week_start, wp_timezone());
            $dt->modify('+' . intval($offsets[$day_w]) . ' days');
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return '';
        }
    }
    
    // Points methods
    public static function get_points_balance($child_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_points_balance';
        $history_table = $wpdb->prefix . 'rodinne_ulohy_points_history';
        $child_id = intval($child_id);
        
        $balance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE child_id = %d",
            $child_id
        ));
        
        if (!$balance) {
            // Initialize balance if doesn't exist.
            // IMPORTANT: do NOT default to 0 if the child already has points in history
            // (e.g. older installs/migrations, or if balance rows were deleted).
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(points), 0) FROM $history_table WHERE child_id = %d",
                $child_id
            ));
            $total = $total !== null ? intval($total) : 0;
            if ($total < 0) $total = 0;

            // Insert (race-safe: if it fails due to duplicate key, fetch existing row)
            $inserted = $wpdb->insert(
                $table,
                array('child_id' => $child_id, 'balance' => $total),
                array('%d', '%d')
            );
            if ($inserted === false) {
                $balance = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE child_id = %d",
                    $child_id
                ));
                if ($balance) return $balance;
            }

            return (object) array('child_id' => $child_id, 'balance' => $total);
        }
        
        return $balance;
    }
    
    /**
     * Get total net points gained/lost today for a child.
     * This respects the same "grouping" idea as history (Variant B) so
     * opakované klikanie na rovnakú úlohu v ten istý deň sa nepočíta viackrát.
     */
    public static function get_today_points_total($child_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_points_history';
        
        $today = current_time('Y-m-d');
        
        // Fetch today's raw rows (include stable task_id via assignment_id if needed)
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ph.*, COALESCE(ph.task_id, a.task_id) as effective_task_id
             FROM $table ph
             LEFT JOIN {$wpdb->prefix}rodinne_ulohy_assignments a ON ph.assignment_id = a.id
             WHERE ph.child_id = %d
               AND DATE(ph.created_at) = %s
             ORDER BY ph.created_at DESC, ph.id DESC",
            $child_id,
            $today
        ));
        
        if (empty($rows)) {
            return 0;
        }
        
        // Group task entries per (task_id + date) for today,
        // so repeated toggling doesn't double count and regenerate doesn't split.
        $grouped = array();
        
        foreach ($rows as $row) {
            $type = isset($row->type) ? $row->type : 'task';
            
            if ($type === 'task') {
                $tid = !empty($row->effective_task_id) ? intval($row->effective_task_id) : (!empty($row->task_id) ? intval($row->task_id) : 0);
                $key = $tid ? ('today_task_' . $tid) : ('today_row_' . intval($row->id));
                
                if (!isset($grouped[$key])) {
                    $grouped[$key] = clone $row;
                } else {
                    $existing = $grouped[$key];
                    $existing->points += intval($row->points);
                    
                    if (strtotime($row->created_at) > strtotime($existing->created_at)) {
                        $existing->created_at = $row->created_at;
                        $existing->reason = $row->reason;
                    }
                    
                    $grouped[$key] = $existing;
                }
            } else {
                $unique_key = 'manual_' . $row->id;
                $grouped[$unique_key] = $row;
            }
        }
        
        // Filter out entries with 0 net points
        $grouped = array_filter($grouped, function($entry) {
            return intval($entry->points) !== 0;
        });
        
        if (empty($grouped)) {
            return 0;
        }
        
        // Sum all remaining points as "Dnes"
        $total_today = 0;
        foreach ($grouped as $entry) {
            $total_today += intval($entry->points);
        }
        
        return $total_today;
    }

    /**
     * Get total net points for a given week (by week_start) for a child.
     */
    public static function get_week_points_total($child_id, $week_start) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_points_history';

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) 
             FROM $table 
             WHERE child_id = %d 
               AND week_start = %s",
            $child_id,
            $week_start
        ));

        return intval($total);
    }
    
    public static function update_points_balance($child_id, $points) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_points_balance';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE child_id = %d",
            $child_id
        ));
        
        if ($existing) {
            return $wpdb->update(
                $table,
                array('balance' => intval($points)),
                array('child_id' => intval($child_id)),
                array('%d'),
                array('%d')
            );
        } else {
            return $wpdb->insert(
                $table,
                array('child_id' => intval($child_id), 'balance' => intval($points)),
                array('%d', '%d')
            );
        }
    }
    
    /**
     * Recalculate and persist the total balance for a child based on history
     */
    public static function recalculate_points_balance($child_id) {
        global $wpdb;
        $history_table = $wpdb->prefix . 'rodinne_ulohy_points_history';
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM $history_table WHERE child_id = %d",
            $child_id
        ));
        
        $total = $total !== null ? intval($total) : 0;
        if ($total < 0) {
            $total = 0;
        }
        
        self::update_points_balance($child_id, $total);
        return $total;
    }
    
    public static function add_points($child_id, $points, $week_start = null, $task_id = null, $assignment_id = null, $reason = null, $type = 'task') {
        global $wpdb;
        
        if (!$week_start) {
            $week_start = self::get_current_week_start();
        }
        $week_start = sanitize_text_field($week_start);
        $type_value = $type ? sanitize_text_field($type) : 'task';
        
        // Add to history
        $history_table = $wpdb->prefix . 'rodinne_ulohy_points_history';
        $result = $wpdb->insert(
            $history_table,
            array(
                'child_id' => intval($child_id),
                'points' => intval($points),
                'week_start' => $week_start,
                'task_id' => $task_id ? intval($task_id) : null,
                'assignment_id' => $assignment_id ? intval($assignment_id) : null,
                'reason' => $reason ? sanitize_text_field($reason) : null,
                'type' => $type_value
            ),
            array('%d', '%d', '%s', '%d', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        // Update balance
        $current_balance = self::get_points_balance($child_id);
        $new_balance = intval($current_balance->balance) + intval($points);
        if ($new_balance < 0) {
            $new_balance = 0;
        }
        self::update_points_balance($child_id, $new_balance);
        
        return $wpdb->insert_id; // Return the history ID
    }
    
    /**
     * Check if points were already added for this assignment without being reverted
     */
    public static function points_already_added($assignment_id, $task_type = 'daily') {
        global $wpdb;
        $history_table = $wpdb->prefix . 'rodinne_ulohy_points_history';
        
        // Get assignment details
        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, t.task_type 
            FROM {$wpdb->prefix}rodinne_ulohy_assignments a
            INNER JOIN {$wpdb->prefix}rodinne_ulohy_tasks t ON a.task_id = t.id
            WHERE a.id = %d",
            $assignment_id
        ));
        
        if (!$assignment) {
            return false;
        }
        
        $actual_task_type = isset($assignment->task_type) ? $assignment->task_type : $task_type;
        $week_start = !empty($assignment->week_start) ? $assignment->week_start : self::get_current_week_start();
        // IMPORTANT: use (child_id + task_id + day/week) instead of assignment_id,
        // so regenerated assignments can't be claimed twice.
        return self::points_already_added_for_task(
            intval($assignment->child_id),
            intval($assignment->task_id),
            $actual_task_type,
            $week_start
        );
    }

    /**
     * Check if points were already added for this task (for a child) and not reversed.
     * Uses task_id as a stable key (assignment_id can change after regeneration).
     */
    public static function points_already_added_for_task($child_id, $task_id, $task_type = 'daily', $week_start = null) {
        global $wpdb;
        $history_table = $wpdb->prefix . 'rodinne_ulohy_points_history';

        $child_id = intval($child_id);
        $task_id = intval($task_id);
        if (!$child_id || !$task_id) return false;

        $task_type = $task_type ? sanitize_text_field($task_type) : 'daily';
        $last_entry = null;

        if ($task_type === 'daily') {
            $today = current_time('Y-m-d');
            $last_entry = $wpdb->get_row($wpdb->prepare(
                "SELECT points FROM $history_table
                 WHERE child_id = %d AND task_id = %d AND type = 'task'
                   AND DATE(created_at) = %s
                 ORDER BY created_at DESC, id DESC LIMIT 1",
                $child_id,
                $task_id,
                $today
            ));
        } else {
            if (!$week_start) {
                $week_start = self::get_current_week_start();
            }
            $week_start = sanitize_text_field($week_start);
            $last_entry = $wpdb->get_row($wpdb->prepare(
                "SELECT points FROM $history_table
                 WHERE child_id = %d AND task_id = %d AND type = 'task'
                   AND week_start = %s
                 ORDER BY created_at DESC, id DESC LIMIT 1",
                $child_id,
                $task_id,
                $week_start
            ));
        }

        return $last_entry && intval($last_entry->points) > 0;
    }

    /**
     * Last task points entry for a child/task on a specific calendar day.
     */
    public static function get_last_task_points_entry_for_date($child_id, $task_id, $ymd) {
        global $wpdb;
        $history_table = $wpdb->prefix . 'rodinne_ulohy_points_history';

        $child_id = intval($child_id);
        $task_id = intval($task_id);
        $ymd = sanitize_text_field($ymd);
        if (!$child_id || !$task_id || $ymd === '') {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, points, created_at FROM $history_table
             WHERE child_id = %d AND task_id = %d AND type = 'task'
               AND DATE(created_at) = %s
             ORDER BY created_at DESC, id DESC LIMIT 1",
            $child_id,
            $task_id,
            $ymd
        ));
    }
    
    /**
     * Check if a penalty entry was already added for assignment on given date
     */
    public static function penalty_already_added($assignment_id, $date = null) {
        global $wpdb;
        if (!$date) $date = current_time('Y-m-d');
        $date = sanitize_text_field($date);

        // Use stable key (child_id + task_id + date) instead of assignment_id
        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT child_id, task_id FROM {$wpdb->prefix}rodinne_ulohy_assignments WHERE id = %d",
            intval($assignment_id)
        ));
        if (!$assignment) return false;

        return self::penalty_already_added_for_task(intval($assignment->child_id), intval($assignment->task_id), $date);
    }

    /**
     * Check if a penalty entry was already added for a task/child on a given date.
     */
    public static function penalty_already_added_for_task($child_id, $task_id, $date = null) {
        global $wpdb;
        $history_table = $wpdb->prefix . 'rodinne_ulohy_points_history';

        $child_id = intval($child_id);
        $task_id = intval($task_id);
        if (!$child_id || !$task_id) return false;

        if (!$date) $date = current_time('Y-m-d');
        $date = sanitize_text_field($date);

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $history_table
             WHERE child_id = %d AND task_id = %d
               AND type = 'penalty'
               AND DATE(created_at) = %s
             LIMIT 1",
            $child_id,
            $task_id,
            $date
        ));

        return !empty($exists);
    }
    
    /**
     * Rewards
     */
    private static function ensure_rewards_owner_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        $cols = $wpdb->get_col("DESCRIBE $table");
        if (empty($cols) || !is_array($cols)) return;
        if (in_array('owner_user_id', $cols, true)) return;

        // Add owner scoping for legacy installs
        $wpdb->query("ALTER TABLE $table ADD COLUMN owner_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER id");
        // Best-effort index
        $wpdb->query("ALTER TABLE $table ADD KEY owner_user_id (owner_user_id)");

        // Best-effort backfill: assign legacy rewards to the first existing owner from children.
        // (Prevents rewards from being visible to random accounts.)
        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        $owner = $wpdb->get_var("SELECT MIN(owner_user_id) FROM $children_table WHERE owner_user_id IS NOT NULL AND owner_user_id > 0");
        $owner = intval($owner);
        if ($owner > 0) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET owner_user_id = %d WHERE owner_user_id = 0",
                $owner
            ));
        }
    }

    public static function get_rewards($owner_user_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        $owner_user_id = intval($owner_user_id);
        
        // Clear cache before getting rewards
        wp_cache_delete('rewards_list', 'rodinne_ulohy');

        // SECURITY: rewards are always scoped to an owner. No owner => return empty.
        if (!$owner_user_id) {
            return array();
        }

        self::ensure_rewards_owner_column();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE owner_user_id = %d ORDER BY created_at DESC",
            $owner_user_id
        ));
    }
    
    public static function get_reward($id, $owner_user_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        $owner_user_id = intval($owner_user_id);
        if ($owner_user_id) {
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND owner_user_id = %d", $id, $owner_user_id));
        }
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    /**
     * Claim legacy rewards (owner_user_id=0) for an owner.
     * This is a one-time migration helper so older installs don't "lose" rewards after scoping.
     */
    public static function claim_legacy_rewards($owner_user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        $owner_user_id = intval($owner_user_id);
        if (!$owner_user_id) return 0;
        self::ensure_rewards_owner_column();
        return $wpdb->query($wpdb->prepare(
            "UPDATE $table SET owner_user_id = %d WHERE owner_user_id = 0",
            $owner_user_id
        ));
    }

    /**
     * If rewards table currently has a single owner_user_id (legacy backfill),
     * allow re-assigning all rewards to a new owner (safe only when there's exactly 1 distinct owner).
     */
    public static function claim_rewards_from_single_owner($new_owner_user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        $new_owner_user_id = intval($new_owner_user_id);
        if (!$new_owner_user_id) return 0;

        self::ensure_rewards_owner_column();
        $distinct = $wpdb->get_col("SELECT DISTINCT owner_user_id FROM $table");
        $distinct = array_values(array_unique(array_map('intval', $distinct ?: array())));
        if (count($distinct) !== 1) {
            return 0;
        }
        $current_owner = intval($distinct[0]);
        if ($current_owner === $new_owner_user_id) {
            return 0;
        }
        return $wpdb->query($wpdb->prepare(
            "UPDATE $table SET owner_user_id = %d",
            $new_owner_user_id
        ));
    }
    
    public static function save_reward($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        self::ensure_rewards_owner_column();
        
        $payload = array(
            'owner_user_id' => isset($data['owner_user_id']) ? intval($data['owner_user_id']) : 0,
            'title' => sanitize_text_field($data['title'] ?? ''),
            'category' => sanitize_text_field($data['category'] ?? ''),
            'details' => sanitize_text_field($data['details'] ?? ''),
            'icon' => sanitize_text_field($data['icon'] ?? ''),
            'points_cost' => isset($data['points_cost']) ? max(0, intval($data['points_cost'])) : 0,
        );
        
        if (empty($payload['title'])) {
            return false;
        }
        
        if (!empty($data['id'])) {
            $result = $wpdb->update(
                $table,
                $payload,
                array('id' => intval($data['id'])),
                array('%d', '%s', '%s', '%s', '%s', '%d'),
                array('%d')
            );
            
            // Clear cache after update
            if ($result !== false) {
                wp_cache_delete('rewards_list', 'rodinne_ulohy');
                wp_cache_flush();
            }
            
            return $result;
        }
        
        $result = $wpdb->insert(
            $table,
            $payload,
            array('%d', '%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            // Surface DB error in logs for debugging (but keep API response generic).
            error_log('Rodinne Ulohy: save_reward insert failed: ' . $wpdb->last_error);
        }

        // Clear cache after insert
        if ($result !== false) {
            wp_cache_delete('rewards_list', 'rodinne_ulohy');
            wp_cache_flush();
        }
        
        return $result;
    }
    
    public static function delete_reward($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        
        $result = $wpdb->delete($table, array('id' => intval($id)), array('%d'));
        
        // Clear cache after delete
        if ($result !== false) {
            wp_cache_delete('rewards_list', 'rodinne_ulohy');
            wp_cache_flush();
        }
        
        return $result;
    }
    
    public static function get_child_active_reward_purchases($child_id, $hours = 48) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';
        $rewards_table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        
        // Clear any cached results for this query
        wp_cache_delete('child_active_rewards_' . $child_id, 'rodinne_ulohy');
        
        // Odmeny už neexpirovali - vracia len aktívne odmeny (status = 'active')
        // Používame JOIN, aby sme filtrovali len purchase, ktoré majú existujúcu odmenu
        // Use direct query to bypass any caching
        $query = $wpdb->prepare(
            "SELECT rp.*, r.title as reward_title, r.points_cost as reward_points_cost, r.icon as reward_icon
            FROM $table rp
            INNER JOIN $rewards_table r ON rp.reward_id = r.id
            WHERE rp.child_id = %d 
              AND rp.status = 'active'
            ORDER BY rp.created_at DESC",
            $child_id
        );
        
        // Get results directly without caching
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Convert to objects and verify status (double-check)
        $active_purchases = array();
        if ($results) {
            foreach ($results as $row) {
                // Extra verification - only include if status is truly 'active'
                // Also verify by querying the database directly
                $purchase_status = $wpdb->get_var($wpdb->prepare(
                    "SELECT status FROM $table WHERE id = %d",
                    $row['id']
                ));
                
                if ($purchase_status === 'active') {
                    $active_purchases[] = (object) $row;
                }
            }
        }
        
        return $active_purchases;
    }
    
    /**
     * Clean up orphaned reward purchases (purchases with non-existent rewards)
     */
    public static function cleanup_orphaned_reward_purchases() {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';
        $rewards_table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        
        // Find and mark as 'used' all active purchases where reward doesn't exist
        return $wpdb->query(
            "UPDATE $table rp
            LEFT JOIN $rewards_table r ON rp.reward_id = r.id
            SET rp.status = 'used'
            WHERE rp.status = 'active'
              AND r.id IS NULL"
        );
    }
    
    /**
     * Mark reward purchase as used
     */
    public static function mark_reward_used($purchase_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';
        
        $purchase_id = intval($purchase_id);
        if (!$purchase_id) {
            return false;
        }
        
        // Check if purchase exists and is active
        $purchase = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $purchase_id
        ));
        
        if (!$purchase) {
            return false;
        }
        
        // Only update if status is 'active' (don't update if already 'used')
        if ($purchase->status !== 'active') {
            // Already used or in another state - return false
            return false;
        }
        
        // Update status to 'used' using direct SQL to ensure it works
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'used' WHERE id = %d AND status = 'active'",
            $purchase_id
        ));
        
        // Verify the update was successful by checking the status again
        $verify_purchase = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $purchase_id
        ));
        
        if (!$verify_purchase || $verify_purchase->status !== 'used') {
            // Update failed or status wasn't changed
            return false;
        }
        
        // Clear all cache related to this child and purchases
        wp_cache_delete('child_active_rewards_' . $purchase->child_id, 'rodinne_ulohy');
        wp_cache_flush();
        
        // Clear database query cache
        $wpdb->flush();
        
        // Return true if update was successful
        return true;
    }
    
    /**
     * Get all active reward purchases for all children (for admin)
     */
    public static function get_all_active_reward_purchases() {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';
        $rewards_table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        
        return $wpdb->get_results(
            "SELECT rp.*, r.title as reward_title, r.icon as reward_icon, r.points_cost as reward_cost,
                    c.name as child_name
             FROM $table rp
             INNER JOIN $rewards_table r ON rp.reward_id = r.id
             INNER JOIN $children_table c ON rp.child_id = c.id
             WHERE rp.status = 'active'
             ORDER BY rp.created_at DESC"
        );
    }
    
    public static function purchase_reward($child_id, $reward_id) {
        global $wpdb;
        
        $child_id = intval($child_id);
        $reward_id = intval($reward_id);
        
        if (!$child_id || !$reward_id) {
            return new WP_Error('invalid', __('Neplatné údaje', 'rodinne-ulohy'));
        }
        
        $reward = self::get_reward($reward_id);
        if (!$reward) {
            return new WP_Error('not_found', __('Odmena nebola nájdená', 'rodinne-ulohy'));
        }
        
        $cost = intval($reward->points_cost);
        if ($cost <= 0) {
            return new WP_Error('invalid_cost', __('Odmena nemá nastavenú cenu', 'rodinne-ulohy'));
        }
        
        $balance = self::get_points_balance($child_id);
        if (!$balance || intval($balance->balance) < $cost) {
            return new WP_Error('no_points', __('Nedostatok bodov', 'rodinne-ulohy'));
        }
        
        // Deduct points
        $history_id = self::add_points(
            $child_id,
            -$cost,
            self::get_current_week_start(),
            null,
            null,
            sprintf(__('Odmena: %s', 'rodinne-ulohy'), $reward->title),
            'reward'
        );
        
        if (!$history_id) {
            return new WP_Error('points_failed', __('Nepodarilo sa odpočítať body', 'rodinne-ulohy'));
        }
        
        $table = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';
        
        // Odmeny už neexpirovali - zostávajú aktívne, kým ich admin neoznačí ako použité
        $wpdb->insert(
            $table,
            array(
                'reward_id' => $reward_id,
                'child_id' => $child_id,
                'points_spent' => $cost,
                'status' => 'active',
                'expires_at' => null, // Odmeny už neexpirovali
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );

        $purchase_id = intval($wpdb->insert_id);
        
        return array(
            'reward' => $reward,
            'points_spent' => $cost,
            'purchase_id' => $purchase_id,
        );
    }
    
    /**
     * Get last points transaction for an assignment (to check if we need to reverse it)
     */
    public static function get_last_points_for_assignment($assignment_id) {
        global $wpdb;
        // Legacy wrapper: map assignment_id -> (child_id, task_id, task_type, week_start)
        $assignment_id = intval($assignment_id);
        if (!$assignment_id) return null;

        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.child_id, a.task_id, a.week_start, t.task_type
             FROM {$wpdb->prefix}rodinne_ulohy_assignments a
             INNER JOIN {$wpdb->prefix}rodinne_ulohy_tasks t ON a.task_id = t.id
             WHERE a.id = %d",
            $assignment_id
        ));
        if (!$assignment) return null;

        return self::get_last_points_for_task(
            intval($assignment->child_id),
            intval($assignment->task_id),
            isset($assignment->task_type) ? $assignment->task_type : 'daily',
            !empty($assignment->week_start) ? $assignment->week_start : self::get_current_week_start()
        );
    }

    /**
     * Get last points transaction for a task (stable across regeneration).
     */
    public static function get_last_points_for_task($child_id, $task_id, $task_type = 'daily', $week_start = null) {
        global $wpdb;
        $history_table = $wpdb->prefix . 'rodinne_ulohy_points_history';

        $child_id = intval($child_id);
        $task_id = intval($task_id);
        if (!$child_id || !$task_id) return null;

        $task_type = $task_type ? sanitize_text_field($task_type) : 'daily';

        if ($task_type === 'daily') {
            $today = current_time('Y-m-d');
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $history_table
                 WHERE child_id = %d AND task_id = %d AND type = 'task'
                   AND DATE(created_at) = %s
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1",
                $child_id,
                $task_id,
                $today
            ));
        }

        if (!$week_start) {
            $week_start = self::get_current_week_start();
        }
        $week_start = sanitize_text_field($week_start);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $history_table
             WHERE child_id = %d AND task_id = %d AND type = 'task'
               AND week_start = %s
             ORDER BY created_at DESC, id DESC
             LIMIT 1",
            $child_id,
            $task_id,
            $week_start
        ));
    }
    
    /**
     * Delete a single points history entry and update balances/summaries
     */
    public static function delete_points_entry($entry_id) {
        global $wpdb;
        $history_table = $wpdb->prefix . 'rodinne_ulohy_points_history';
        
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $history_table WHERE id = %d",
            $entry_id
        ));
        
        if (!$entry) {
            return false;
        }
        
        $deleted = $wpdb->delete($history_table, array('id' => intval($entry_id)), array('%d'));
        if ($deleted === false) {
            return false;
        }

        // Update balance: subtract the points from the deleted entry
        // If entry had +10 points, subtract 10 from balance
        // If entry had -5 points, subtract -5 from balance (which means add 5)
        $current_balance = self::get_points_balance($entry->child_id);
        $points_to_subtract = intval($entry->points);
        $new_balance = intval($current_balance->balance) - $points_to_subtract;
        
        // Ensure balance doesn't go below 0
        if ($new_balance < 0) {
            $new_balance = 0;
        }
        
        // Update the balance
        self::update_points_balance($entry->child_id, $new_balance);

        return array(
            'child_id' => intval($entry->child_id),
            'points_removed' => intval($entry->points),
            'new_balance' => $new_balance,
            'week_start' => $entry->week_start ?: null
        );
    }
    
    public static function get_points_history($child_id, $week_start = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_points_history';
        $tasks_table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        
        if ($week_start) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT ph.*,
                        COALESCE(ph.task_id, a.task_id) as effective_task_id,
                        COALESCE(ph.week_start, a.week_start) as effective_week_start,
                        t.name as task_name,
                        t.task_type as task_type
                 FROM $table ph
                 LEFT JOIN $assignments_table a ON ph.assignment_id = a.id
                 LEFT JOIN $tasks_table t ON t.id = COALESCE(ph.task_id, a.task_id)
                 WHERE ph.child_id = %d AND ph.week_start = %s
                 ORDER BY ph.created_at DESC, ph.id DESC",
                $child_id,
                $week_start
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT ph.*,
                        COALESCE(ph.task_id, a.task_id) as effective_task_id,
                        COALESCE(ph.week_start, a.week_start) as effective_week_start,
                        t.name as task_name,
                        t.task_type as task_type
                 FROM $table ph
                 LEFT JOIN $assignments_table a ON ph.assignment_id = a.id
                 LEFT JOIN $tasks_table t ON t.id = COALESCE(ph.task_id, a.task_id)
                 WHERE ph.child_id = %d
                 ORDER BY ph.created_at DESC, ph.id DESC
                 LIMIT 30",
                $child_id
            ));
        }
        
        if (empty($rows)) {
            return array();
        }
        
        // Variant B: group history so repeated toggling doesn't create many rows.
        // IMPORTANT: daily tasks must be grouped per DAY, otherwise 2 days become "+18" in one row.
        $grouped = array();
        
        foreach ($rows as $row) {
            $type = isset($row->type) ? $row->type : 'task';
            
            // Only group automatic task entries; manual entries stay separate
            if ($type === 'task' || $type === 'penalty') {
                $tid = !empty($row->effective_task_id) ? intval($row->effective_task_id) : (!empty($row->task_id) ? intval($row->task_id) : 0);
                $day = !empty($row->created_at) ? date('Y-m-d', strtotime($row->created_at)) : '';
                $ws = !empty($row->effective_week_start) ? $row->effective_week_start : (!empty($row->week_start) ? $row->week_start : '');
                $task_type = isset($row->task_type) ? strval($row->task_type) : '';

                // Daily tasks: group by day + task_id, weekly/other: group by week_start + task_id.
                if ($type === 'task' && $task_type === 'daily' && $day && $tid) {
                    $key = $day . '_task_' . $tid;
                } elseif ($day && $tid && $type === 'penalty') {
                    $key = $day . '_penalty_' . $tid;
                } else {
                    $key = ($ws ? $ws : 'week') . '_' . ($tid ? ($type . '_' . $tid) : ($type . '_row_' . intval($row->id)));
                }
                
                if (!isset($grouped[$key])) {
                    // First occurrence – clone row
                    $grouped[$key] = clone $row;
                } else {
                    // Merge: sum points, keep latest created_at / reason / task_name
                    $existing = $grouped[$key];
                    $existing->points += intval($row->points);
                    
                    if (strtotime($row->created_at) > strtotime($existing->created_at)) {
                        $existing->created_at = $row->created_at;
                        $existing->reason = $row->reason;
                        $existing->task_name = $row->task_name;
                    }
                    
                    $grouped[$key] = $existing;
                }
            } else {
                // Manual or other types: use unique key to keep as-is
                $unique_key = 'manual_' . $row->id;
                $grouped[$unique_key] = $row;
            }
        }
        
        // Filter out entries with 0 points (e.g. check/uncheck resulting in 0 change)
        $grouped = array_filter($grouped, function($entry) {
            return intval($entry->points) !== 0;
        });
        
        // Sort by created_at DESC
        usort($grouped, function($a, $b) {
            $timeA = isset($a->created_at) ? strtotime($a->created_at) : 0;
            $timeB = isset($b->created_at) ? strtotime($b->created_at) : 0;
            if ($timeA === $timeB) {
                return 0;
            }
            return ($timeA > $timeB) ? -1 : 1;
        });
        
        return array_values($grouped);
    }

    /**
     * Get points history for the last N days (rolling window, including today).
     * Uses the same grouping logic idea as get_points_history(), but for a day-based window.
     *
     * @param int $child_id
     * @param int $days default 7
     * @return array
     */
    public static function get_points_history_last_days($child_id, $days = 7) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_points_history';
        $tasks_table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';

        $child_id = intval($child_id);
        $days = intval($days);
        if ($days <= 0) $days = 7;

        // Rolling window: include today + previous (days-1) days.
        $from_ts = current_time('timestamp') - max(0, ($days - 1)) * DAY_IN_SECONDS;
        $from_date = date('Y-m-d', $from_ts);

        // Fetch a bit more raw rows so grouping can collapse toggles without losing recent info.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ph.*,
                    COALESCE(ph.task_id, a.task_id) as effective_task_id,
                    COALESCE(ph.week_start, a.week_start) as effective_week_start,
                    t.name as task_name,
                    t.task_type as task_type
             FROM $table ph
             LEFT JOIN $assignments_table a ON ph.assignment_id = a.id
             LEFT JOIN $tasks_table t ON t.id = COALESCE(ph.task_id, a.task_id)
             WHERE ph.child_id = %d
               AND DATE(ph.created_at) >= %s
             ORDER BY ph.created_at DESC, ph.id DESC
             LIMIT 120",
            $child_id,
            $from_date
        ));

        if (empty($rows)) {
            return array();
        }

        // Group by DAY for task/penalty entries so the "last 7 days" view makes sense.
        $grouped = array();
        foreach ($rows as $row) {
            $type = isset($row->type) ? $row->type : 'task';

            if ($type === 'task' || $type === 'penalty') {
                $tid = !empty($row->effective_task_id) ? intval($row->effective_task_id) : (!empty($row->task_id) ? intval($row->task_id) : 0);
                $day = !empty($row->created_at) ? date('Y-m-d', strtotime($row->created_at)) : '';

                if ($day && $tid) {
                    $key = $day . '_' . $type . '_' . $tid;
                } else {
                    $key = $type . '_row_' . intval($row->id);
                }

                if (!isset($grouped[$key])) {
                    $grouped[$key] = clone $row;
                } else {
                    $existing = $grouped[$key];
                    $existing->points += intval($row->points);

                    if (strtotime($row->created_at) > strtotime($existing->created_at)) {
                        $existing->created_at = $row->created_at;
                        $existing->reason = $row->reason;
                        $existing->task_name = $row->task_name;
                    }

                    $grouped[$key] = $existing;
                }
            } else {
                // Manual/reward/etc entries: keep as-is
                $unique_key = 'manual_' . $row->id;
                $grouped[$unique_key] = $row;
            }
        }

        // Filter out net-0 entries
        $grouped = array_filter($grouped, function($entry) {
            return intval($entry->points) !== 0;
        });

        // Sort by created_at DESC
        usort($grouped, function($a, $b) {
            $timeA = isset($a->created_at) ? strtotime($a->created_at) : 0;
            $timeB = isset($b->created_at) ? strtotime($b->created_at) : 0;
            if ($timeA === $timeB) return 0;
            return ($timeA > $timeB) ? -1 : 1;
        });

        return array_values($grouped);
    }
    
    public static function get_week_points_summary($child_id, $week_start) {
        // Use grouped history (Variant B) so repeated checkbox clicks
        // for the same assignment in a week don't distort weekly stats.
        $history = self::get_points_history($child_id, $week_start);
        
        $earned = 0;
        $lost = 0;
        
        if (!empty($history)) {
            foreach ($history as $entry) {
                $points = intval($entry->points);
                if ($points > 0) {
                    $earned += $points;
                } elseif ($points < 0) {
                    $lost += abs($points);
                }
            }
        }
        
        $total = $earned - $lost;
        
        return (object) array(
            'earned' => $earned,
            'lost' => $lost,
            'total' => $total
        );
    }
    
    /**
     * Get weekend penalty multiplier (X)
     */
    public static function get_weekend_penalty_multiplier() {
        $multiplier = get_option('rodinne_ulohy_weekend_penalty_multiplier', 3);
        return max(1, floatval($multiplier)); // Minimum 1
    }
    
    /**
     * Save weekend penalty multiplier (X)
     */
    public static function save_weekend_penalty_multiplier($multiplier) {
        $multiplier = max(1, floatval($multiplier)); // Minimum 1
        return update_option('rodinne_ulohy_weekend_penalty_multiplier', $multiplier);
    }

    // -----------------------
    // API token auth helpers
    // -----------------------
    public static function create_api_token($subject_type, $subject_id, $ttl_seconds = 2592000) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_api_tokens';

        $subject_type = sanitize_text_field($subject_type);
        $subject_id = intval($subject_id);

        $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $raw);

        $expires_at = null;
        if (!empty($ttl_seconds) && intval($ttl_seconds) > 0) {
            $expires_at = gmdate('Y-m-d H:i:s', time() + intval($ttl_seconds));
        }

        $wpdb->insert(
            $table,
            array(
                'token_hash' => $hash,
                'subject_type' => $subject_type,
                'subject_id' => $subject_id,
                'expires_at' => $expires_at,
                'revoked' => 0,
                'last_used_at' => null,
            ),
            array('%s', '%s', '%d', '%s', '%d', '%s')
        );

        return array(
            'token' => $raw,
            'expires_at' => $expires_at,
        );
    }

    public static function get_api_token($raw_token) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_api_tokens';

        $raw_token = trim(strval($raw_token));
        if ($raw_token === '') return null;

        $hash = hash('sha256', $raw_token);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token_hash = %s AND revoked = 0 LIMIT 1",
            $hash
        ));

        if (!$row) return null;

        // Expiry check (expires_at stored in UTC)
        if (!empty($row->expires_at)) {
            $now = gmdate('Y-m-d H:i:s');
            if ($row->expires_at < $now) {
                // Auto-revoke expired token
                $wpdb->update(
                    $table,
                    array('revoked' => 1),
                    array('token_hash' => $hash),
                    array('%d'),
                    array('%s')
                );
                return null;
            }
        }

        // Update last_used_at (best-effort)
        $wpdb->update(
            $table,
            array('last_used_at' => gmdate('Y-m-d H:i:s')),
            array('token_hash' => $hash),
            array('%s'),
            array('%s')
        );

        return $row;
    }

    public static function revoke_api_token($raw_token) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_api_tokens';
        $raw_token = trim(strval($raw_token));
        if ($raw_token === '') return false;
        $hash = hash('sha256', $raw_token);
        $res = $wpdb->update(
            $table,
            array('revoked' => 1),
            array('token_hash' => $hash),
            array('%d'),
            array('%s')
        );
        return $res !== false;
    }

    public static function revoke_api_tokens_for_subject($subject_type, $subject_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_api_tokens';
        $subject_type = sanitize_text_field($subject_type);
        $subject_id = intval($subject_id);
        if ($subject_id <= 0 || $subject_type === '') return false;

        $wpdb->update(
            $table,
            array('revoked' => 1),
            array(
                'subject_type' => $subject_type,
                'subject_id' => $subject_id,
            ),
            array('%d'),
            array('%s', '%d')
        );
        return true;
    }

    public static function revoke_api_tokens_for_owner_children($owner_user_id) {
        global $wpdb;
        $owner_user_id = intval($owner_user_id);
        if (!$owner_user_id) return;

        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        $child_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $children_table WHERE owner_user_id = %d",
            $owner_user_id
        ));
        foreach ($child_ids ?: array() as $child_id) {
            self::revoke_api_tokens_for_subject('child', intval($child_id));
        }
    }

    public static function unlink_family_members($owner_user_id) {
        $owner_user_id = intval($owner_user_id);
        if (!$owner_user_id) return;

        $linked = get_users(array(
            'meta_key' => 'ru_owner_user_id',
            'meta_value' => $owner_user_id,
            'fields' => array('ID'),
            'number' => 100,
        ));

        foreach ($linked as $user) {
            $uid = is_object($user) ? intval($user->ID) : intval($user);
            if ($uid <= 0 || $uid === $owner_user_id) continue;
            delete_user_meta($uid, 'ru_owner_user_id');
            self::revoke_api_tokens_for_subject('wp_user', $uid);
            delete_transient('rodinne_ulohy_api_token_user_' . $uid);
        }
    }

    /**
     * Permanently delete a parent WP account.
     * Owners also lose all family data; linked adults are unlinked.
     */
    public static function delete_wp_user_account($wp_user_id) {
        $wp_user_id = intval($wp_user_id);
        if ($wp_user_id <= 0) {
            return new WP_Error('ru_invalid', __('Neplatný používateľ', 'rodinne-ulohy'));
        }

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $owner_user_id = self::resolve_owner_user_id_for_wp_user($wp_user_id);
        $is_owner = ($owner_user_id === $wp_user_id);
        $deleted_owner_data = false;

        if ($is_owner) {
            self::revoke_api_tokens_for_owner_children($owner_user_id);
            self::clear_all_owner_data($owner_user_id);
            self::unlink_family_members($owner_user_id);
            delete_option('rodinne_ulohy_needs_regen_' . $owner_user_id);
            $deleted_owner_data = true;
        }

        self::revoke_api_tokens_for_subject('wp_user', $wp_user_id);
        delete_transient('rodinne_ulohy_api_token_user_' . $wp_user_id);

        $deleted = wp_delete_user($wp_user_id);
        if (!$deleted) {
            return new WP_Error('ru_failed', __('Účet sa nepodarilo zrušiť', 'rodinne-ulohy'));
        }

        return array(
            'ok' => true,
            'deleted_owner_data' => $deleted_owner_data,
        );
    }

    // -----------------------
    // Family invites (invite-only access for additional adults)
    // -----------------------
    private static function ru_make_invite_token() {
        // Shorter than api tokens but still strong enough (email delivery is the real gate).
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    public static function create_invite($owner_user_id, $inviter_user_id, $email, $role = 'parent', $ttl_seconds = 604800) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_invites';

        $owner_user_id = intval($owner_user_id);
        $inviter_user_id = intval($inviter_user_id);
        $email = sanitize_email($email);
        $role = sanitize_text_field($role);
        if ($role === '') $role = 'parent';

        if ($owner_user_id <= 0 || $inviter_user_id <= 0 || empty($email)) return false;

        // Revoke existing active invites for this email in this family (avoid confusion).
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET revoked = 1 WHERE owner_user_id = %d AND email = %s AND revoked = 0 AND accepted_at IS NULL",
            $owner_user_id,
            $email
        ));

        $raw = self::ru_make_invite_token();
        $hash = hash('sha256', $raw);

        $expires_at = null;
        if (!empty($ttl_seconds) && intval($ttl_seconds) > 0) {
            $expires_at = gmdate('Y-m-d H:i:s', time() + intval($ttl_seconds));
        }

        $ok = $wpdb->insert(
            $table,
            array(
                'owner_user_id' => $owner_user_id,
                'inviter_user_id' => $inviter_user_id,
                'email' => $email,
                'role' => $role,
                'token_hash' => $hash,
                'expires_at' => $expires_at,
                'revoked' => 0,
                'accepted_user_id' => null,
                'accepted_at' => null,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );

        if ($ok === false) return false;

        return array(
            'id' => intval($wpdb->insert_id),
            'token' => $raw,
            'expires_at' => $expires_at,
            'email' => $email,
            'role' => $role,
            'owner_user_id' => $owner_user_id,
            'inviter_user_id' => $inviter_user_id,
        );
    }

    public static function get_invite_by_token($raw_token) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_invites';
        $raw_token = trim(strval($raw_token));
        if ($raw_token === '') return null;

        $hash = hash('sha256', $raw_token);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token_hash = %s AND revoked = 0 AND accepted_at IS NULL LIMIT 1",
            $hash
        ));

        if (!$row) return null;

        // Expiry check (expires_at stored in UTC)
        if (!empty($row->expires_at)) {
            $now = gmdate('Y-m-d H:i:s');
            if ($row->expires_at < $now) {
                // Auto-revoke expired invite
                $wpdb->update(
                    $table,
                    array('revoked' => 1),
                    array('token_hash' => $hash),
                    array('%d'),
                    array('%s')
                );
                return null;
            }
        }

        return $row;
    }

    public static function list_invites($owner_user_id, $include_revoked = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_invites';
        $owner_user_id = intval($owner_user_id);
        if ($owner_user_id <= 0) return array();

        if ($include_revoked) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE owner_user_id = %d ORDER BY created_at DESC",
                $owner_user_id
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
             WHERE owner_user_id = %d AND revoked = 0 AND accepted_at IS NULL
             ORDER BY created_at DESC",
            $owner_user_id
        ));
    }

    public static function revoke_invite($invite_id, $owner_user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_invites';
        $invite_id = intval($invite_id);
        $owner_user_id = intval($owner_user_id);
        if ($invite_id <= 0 || $owner_user_id <= 0) return false;
        $res = $wpdb->update(
            $table,
            array('revoked' => 1),
            array('id' => $invite_id, 'owner_user_id' => $owner_user_id),
            array('%d'),
            array('%d', '%d')
        );
        return $res !== false;
    }

    public static function accept_invite($invite_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_invites';
        $invite_id = intval($invite_id);
        $user_id = intval($user_id);
        if ($invite_id <= 0 || $user_id <= 0) return false;

        $res = $wpdb->update(
            $table,
            array(
                'accepted_user_id' => $user_id,
                'accepted_at' => current_time('mysql'),
                'revoked' => 1,
            ),
            array('id' => $invite_id),
            array('%d', '%s', '%d'),
            array('%d')
        );

        return $res !== false;
    }

    // -----------------------
    // Admin export / import (owner-scoped full snapshot)
    // -----------------------
    private const OWNER_EXPORT_FORMAT = 'ekidio-owner-export';
    private const OWNER_EXPORT_VERSION = 1;

    /**
     * Summary counts for admin UI.
     */
    public static function get_owner_data_summary($owner_user_id) {
        global $wpdb;
        $owner_user_id = intval($owner_user_id);
        if (!$owner_user_id) {
            return array('children' => 0, 'tasks' => 0, 'rewards' => 0);
        }

        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        $tasks_table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $rewards_table = $wpdb->prefix . 'rodinne_ulohy_rewards';

        return array(
            'children' => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $children_table WHERE owner_user_id = %d",
                $owner_user_id
            ))),
            'tasks' => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $tasks_table WHERE owner_user_id = %d",
                $owner_user_id
            ))),
            'rewards' => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $rewards_table WHERE owner_user_id = %d",
                $owner_user_id
            ))),
        );
    }

    private static function export_rows($rows) {
        $out = array();
        if (empty($rows) || !is_array($rows)) {
            return $out;
        }
        foreach ($rows as $row) {
            $out[] = (array) $row;
        }
        return $out;
    }

    /**
     * Export all family data for an owner into a portable JSON structure.
     */
    public static function export_owner_data($owner_user_id, $source_wp_user_id = 0) {
        global $wpdb;

        $owner_user_id = intval($owner_user_id);
        if (!$owner_user_id) {
            return new WP_Error('ru_export_invalid', __('Neplatný používateľ pre export.', 'rodinne-ulohy'));
        }

        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        $tasks_table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $packages_table = $wpdb->prefix . 'rodinne_ulohy_packages';
        $package_children_table = $wpdb->prefix . 'rodinne_ulohy_package_children';
        $task_children_table = $wpdb->prefix . 'rodinne_ulohy_task_children';
        $links_table = $wpdb->prefix . 'rodinne_ulohy_task_links';
        $excl_table = $wpdb->prefix . 'rodinne_ulohy_task_exclusions';
        $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        $points_balance_table = $wpdb->prefix . 'rodinne_ulohy_points_balance';
        $points_history_table = $wpdb->prefix . 'rodinne_ulohy_points_history';
        $rewards_table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        $reward_purchases_table = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';
        $invites_table = $wpdb->prefix . 'rodinne_ulohy_invites';

        $children = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $children_table WHERE owner_user_id = %d ORDER BY sort_order ASC, id ASC",
            $owner_user_id
        ));
        $child_ids = array_values(array_filter(array_map(function ($row) {
            return intval($row->id ?? 0);
        }, $children ?: array())));

        $tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tasks_table WHERE owner_user_id = %d ORDER BY id ASC",
            $owner_user_id
        ));
        $task_ids = array_values(array_filter(array_map(function ($row) {
            return intval($row->id ?? 0);
        }, $tasks ?: array())));

        $packages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $packages_table WHERE owner_user_id = %d ORDER BY id ASC",
            $owner_user_id
        ));
        $package_ids = array_values(array_filter(array_map(function ($row) {
            return intval($row->id ?? 0);
        }, $packages ?: array())));

        $rewards = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $rewards_table WHERE owner_user_id = %d ORDER BY id ASC",
            $owner_user_id
        ));
        $reward_ids = array_values(array_filter(array_map(function ($row) {
            return intval($row->id ?? 0);
        }, $rewards ?: array())));

        $task_links = array();
        if (!empty($task_ids)) {
            $in = implode(',', array_fill(0, count($task_ids), '%d'));
            $task_links = $wpdb->get_results($wpdb->prepare(
                "SELECT l.* FROM $links_table l
                 INNER JOIN $tasks_table t1 ON t1.id = l.task_id
                 INNER JOIN $tasks_table t2 ON t2.id = l.linked_task_id
                 WHERE t1.owner_user_id = %d AND t2.owner_user_id = %d",
                $owner_user_id,
                $owner_user_id
            ));
        }

        $task_exclusions = array();
        if (!empty($task_ids)) {
            $task_exclusions = $wpdb->get_results($wpdb->prepare(
                "SELECT e.* FROM $excl_table e
                 INNER JOIN $tasks_table t1 ON t1.id = e.task_id
                 INNER JOIN $tasks_table t2 ON t2.id = e.excluded_task_id
                 WHERE t1.owner_user_id = %d AND t2.owner_user_id = %d",
                $owner_user_id,
                $owner_user_id
            ));
        }

        $task_children = array();
        if (!empty($task_ids)) {
            $in = implode(',', array_fill(0, count($task_ids), '%d'));
            $task_children = $wpdb->get_results($wpdb->prepare(
                "SELECT tc.* FROM $task_children_table tc
                 INNER JOIN $tasks_table t ON t.id = tc.task_id
                 WHERE t.owner_user_id = %d",
                $owner_user_id
            ));
        }

        $package_children = array();
        if (!empty($package_ids)) {
            $in = implode(',', array_fill(0, count($package_ids), '%d'));
            $package_children = $wpdb->get_results($wpdb->prepare(
                "SELECT pc.* FROM $package_children_table pc
                 INNER JOIN $packages_table p ON p.id = pc.package_id
                 WHERE p.owner_user_id = %d",
                $owner_user_id
            ));
        }

        $assignments = array();
        if (!empty($child_ids)) {
            $in = implode(',', array_fill(0, count($child_ids), '%d'));
            $assignments = $wpdb->get_results($wpdb->prepare(
                "SELECT a.* FROM $assignments_table a
                 INNER JOIN $children_table c ON c.id = a.child_id
                 WHERE c.owner_user_id = %d",
                $owner_user_id
            ));
        }

        $points_balance = array();
        $points_history = array();
        if (!empty($child_ids)) {
            $in = implode(',', array_fill(0, count($child_ids), '%d'));
            $points_balance = $wpdb->get_results($wpdb->prepare(
                "SELECT pb.* FROM $points_balance_table pb
                 INNER JOIN $children_table c ON c.id = pb.child_id
                 WHERE c.owner_user_id = %d",
                $owner_user_id
            ));
            $points_history = $wpdb->get_results($wpdb->prepare(
                "SELECT ph.* FROM $points_history_table ph
                 INNER JOIN $children_table c ON c.id = ph.child_id
                 WHERE c.owner_user_id = %d
                 ORDER BY ph.id ASC",
                $owner_user_id
            ));
        }

        $reward_purchases = array();
        if (!empty($reward_ids)) {
            $in = implode(',', array_fill(0, count($reward_ids), '%d'));
            $reward_purchases = $wpdb->get_results($wpdb->prepare(
                "SELECT rp.* FROM $reward_purchases_table rp
                 INNER JOIN $rewards_table r ON r.id = rp.reward_id
                 WHERE r.owner_user_id = %d
                 ORDER BY rp.id ASC",
                $owner_user_id
            ));
        }

        $invites = $wpdb->get_results($wpdb->prepare(
            "SELECT id, owner_user_id, inviter_user_id, email, role, expires_at, revoked, accepted_user_id, accepted_at, created_at
             FROM $invites_table
             WHERE owner_user_id = %d
             ORDER BY id ASC",
            $owner_user_id
        ));

        return array(
            'format' => self::OWNER_EXPORT_FORMAT,
            'version' => self::OWNER_EXPORT_VERSION,
            'exported_at' => gmdate('c'),
            'plugin_version' => defined('RODINNE_ULOHY_VERSION') ? RODINNE_ULOHY_VERSION : '',
            'source_wp_user_id' => intval($source_wp_user_id),
            'source_owner_user_id' => $owner_user_id,
            'needs_regen' => !empty(get_option('rodinne_ulohy_needs_regen_' . $owner_user_id, 0)) ? 1 : 0,
            'data' => array(
                'children' => self::export_rows($children),
                'packages' => self::export_rows($packages),
                'tasks' => self::export_rows($tasks),
                'task_links' => self::export_rows($task_links),
                'task_exclusions' => self::export_rows($task_exclusions),
                'task_children' => self::export_rows($task_children),
                'package_children' => self::export_rows($package_children),
                'assignments' => self::export_rows($assignments),
                'points_balance' => self::export_rows($points_balance),
                'points_history' => self::export_rows($points_history),
                'rewards' => self::export_rows($rewards),
                'reward_purchases' => self::export_rows($reward_purchases),
                'invites' => self::export_rows($invites),
            ),
        );
    }

    /**
     * Delete all plugin data scoped to one owner (used before import replace).
     */
    public static function clear_all_owner_data($owner_user_id) {
        global $wpdb;

        $owner_user_id = intval($owner_user_id);
        if (!$owner_user_id) {
            return false;
        }

        self::reset_tasks_for_owner($owner_user_id);
        self::reset_rewards_for_owner($owner_user_id);
        self::reset_children_for_owner($owner_user_id);

        $packages_table = $wpdb->prefix . 'rodinne_ulohy_packages';
        $package_children_table = $wpdb->prefix . 'rodinne_ulohy_package_children';
        $invites_table = $wpdb->prefix . 'rodinne_ulohy_invites';

        $package_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $packages_table WHERE owner_user_id = %d",
            $owner_user_id
        ));
        $package_ids = array_values(array_filter(array_map('intval', $package_ids ?: array())));
        if (!empty($package_ids)) {
            $in = implode(',', array_fill(0, count($package_ids), '%d'));
            $wpdb->query($wpdb->prepare("DELETE FROM $package_children_table WHERE package_id IN ($in)", ...$package_ids));
        }
        $wpdb->delete($packages_table, array('owner_user_id' => $owner_user_id), array('%d'));
        $wpdb->delete($invites_table, array('owner_user_id' => $owner_user_id), array('%d'));

        delete_option('rodinne_ulohy_needs_regen_' . $owner_user_id);
        wp_cache_flush();

        return true;
    }

    private static function import_pick_row_fields($row, $allowed_fields) {
        $out = array();
        if (!is_array($row)) {
            return $out;
        }
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $row)) {
                $out[$field] = $row[$field];
            }
        }
        return $out;
    }

    /**
     * Replace all data for target owner with an exported snapshot.
     */
    public static function import_owner_data($target_owner_user_id, $payload, $target_wp_user_id = 0) {
        global $wpdb;

        $target_owner_user_id = intval($target_owner_user_id);
        $target_wp_user_id = intval($target_wp_user_id);
        if (!$target_owner_user_id) {
            return new WP_Error('ru_import_invalid', __('Neplatný cieľový používateľ.', 'rodinne-ulohy'));
        }
        if (!is_array($payload)) {
            return new WP_Error('ru_import_invalid', __('Neplatný importný súbor.', 'rodinne-ulohy'));
        }
        if (($payload['format'] ?? '') !== self::OWNER_EXPORT_FORMAT) {
            return new WP_Error('ru_import_invalid', __('Súbor nie je export z ekidio.', 'rodinne-ulohy'));
        }
        if (intval($payload['version'] ?? 0) !== self::OWNER_EXPORT_VERSION) {
            return new WP_Error('ru_import_invalid', __('Nepodporovaná verzia exportu.', 'rodinne-ulohy'));
        }
        if (empty($payload['data']) || !is_array($payload['data'])) {
            return new WP_Error('ru_import_invalid', __('Export neobsahuje dáta.', 'rodinne-ulohy'));
        }

        $data = $payload['data'];
        self::clear_all_owner_data($target_owner_user_id);

        $children_table = $wpdb->prefix . 'rodinne_ulohy_children';
        $tasks_table = $wpdb->prefix . 'rodinne_ulohy_tasks';
        $packages_table = $wpdb->prefix . 'rodinne_ulohy_packages';
        $package_children_table = $wpdb->prefix . 'rodinne_ulohy_package_children';
        $task_children_table = $wpdb->prefix . 'rodinne_ulohy_task_children';
        $links_table = $wpdb->prefix . 'rodinne_ulohy_task_links';
        $excl_table = $wpdb->prefix . 'rodinne_ulohy_task_exclusions';
        $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
        $points_balance_table = $wpdb->prefix . 'rodinne_ulohy_points_balance';
        $points_history_table = $wpdb->prefix . 'rodinne_ulohy_points_history';
        $rewards_table = $wpdb->prefix . 'rodinne_ulohy_rewards';
        $reward_purchases_table = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';

        $child_map = array();
        $package_map = array();
        $task_map = array();
        $reward_map = array();
        $has_rotation = false;

        foreach ($data['children'] ?? array() as $row) {
            $old_id = intval($row['id'] ?? 0);
            $insert = self::import_pick_row_fields($row, array(
                'sort_order', 'name', 'email', 'password', 'avatar_url', 'color',
            ));
            $insert['owner_user_id'] = $target_owner_user_id;
            $insert['login_code'] = null;
            if (!empty($row['created_at'])) {
                $insert['created_at'] = $row['created_at'];
            }

            $ok = $wpdb->insert($children_table, $insert);
            if ($ok === false || !$old_id) {
                continue;
            }
            $new_id = intval($wpdb->insert_id);
            $child_map[$old_id] = $new_id;
            self::ensure_child_login_code($new_id);
        }

        foreach ($data['packages'] ?? array() as $row) {
            $old_id = intval($row['id'] ?? 0);
            $insert = self::import_pick_row_fields($row, array('name', 'description'));
            $insert['owner_user_id'] = $target_owner_user_id;
            if (!empty($row['created_at'])) {
                $insert['created_at'] = $row['created_at'];
            }
            $ok = $wpdb->insert($packages_table, $insert);
            if ($ok === false || !$old_id) {
                continue;
            }
            $package_map[$old_id] = intval($wpdb->insert_id);
        }

        foreach ($data['tasks'] ?? array() as $row) {
            $old_id = intval($row['id'] ?? 0);
            $insert = self::import_pick_row_fields($row, array(
                'name', 'description', 'task_type', 'days_of_week', 'task_category',
                'rotation_enabled', 'shared_task', 'estimated_time', 'points', 'rating',
            ));
            $insert['owner_user_id'] = $target_owner_user_id;
            $package_id = intval($row['package_id'] ?? 0);
            $insert['package_id'] = ($package_id && !empty($package_map[$package_id])) ? intval($package_map[$package_id]) : null;
            if (!empty($row['created_at'])) {
                $insert['created_at'] = $row['created_at'];
            }
            $ok = $wpdb->insert($tasks_table, $insert);
            if ($ok === false || !$old_id) {
                continue;
            }
            $task_map[$old_id] = intval($wpdb->insert_id);
            if (!empty($row['rotation_enabled'])) {
                $has_rotation = true;
            }
        }

        foreach ($data['task_links'] ?? array() as $row) {
            $a_old = intval($row['task_id'] ?? 0);
            $b_old = intval($row['linked_task_id'] ?? 0);
            if (empty($task_map[$a_old]) || empty($task_map[$b_old])) {
                continue;
            }
            $a = min($task_map[$a_old], $task_map[$b_old]);
            $b = max($task_map[$a_old], $task_map[$b_old]);
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $links_table (task_id, linked_task_id) VALUES (%d, %d)",
                $a,
                $b
            ));
        }

        foreach ($data['task_exclusions'] ?? array() as $row) {
            $a_old = intval($row['task_id'] ?? 0);
            $b_old = intval($row['excluded_task_id'] ?? 0);
            if (empty($task_map[$a_old]) || empty($task_map[$b_old])) {
                continue;
            }
            $a = min($task_map[$a_old], $task_map[$b_old]);
            $b = max($task_map[$a_old], $task_map[$b_old]);
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $excl_table (task_id, excluded_task_id) VALUES (%d, %d)",
                $a,
                $b
            ));
        }

        foreach ($data['task_children'] ?? array() as $row) {
            $task_id = intval($task_map[intval($row['task_id'] ?? 0)] ?? 0);
            $child_id = intval($child_map[intval($row['child_id'] ?? 0)] ?? 0);
            if (!$task_id || !$child_id) {
                continue;
            }
            $insert = array('task_id' => $task_id, 'child_id' => $child_id);
            if (!empty($row['created_at'])) {
                $insert['created_at'] = $row['created_at'];
            }
            $wpdb->insert($task_children_table, $insert);
        }

        foreach ($data['package_children'] ?? array() as $row) {
            $package_id = intval($package_map[intval($row['package_id'] ?? 0)] ?? 0);
            $child_id = intval($child_map[intval($row['child_id'] ?? 0)] ?? 0);
            if (!$package_id || !$child_id) {
                continue;
            }
            $insert = array('package_id' => $package_id, 'child_id' => $child_id);
            if (!empty($row['created_at'])) {
                $insert['created_at'] = $row['created_at'];
            }
            $wpdb->insert($package_children_table, $insert);
        }

        foreach ($data['assignments'] ?? array() as $row) {
            $task_id = intval($task_map[intval($row['task_id'] ?? 0)] ?? 0);
            $child_id = intval($child_map[intval($row['child_id'] ?? 0)] ?? 0);
            if (!$task_id || !$child_id || empty($row['week_start'])) {
                continue;
            }
            $insert = self::import_pick_row_fields($row, array('week_start', 'status', 'completed_at'));
            $insert['task_id'] = $task_id;
            $insert['child_id'] = $child_id;
            if (!empty($row['created_at'])) {
                $insert['created_at'] = $row['created_at'];
            }
            $wpdb->insert($assignments_table, $insert);
        }

        foreach ($data['points_balance'] ?? array() as $row) {
            $child_id = intval($child_map[intval($row['child_id'] ?? 0)] ?? 0);
            if (!$child_id) {
                continue;
            }
            $insert = array(
                'child_id' => $child_id,
                'balance' => intval($row['balance'] ?? 0),
            );
            if (!empty($row['updated_at'])) {
                $insert['updated_at'] = $row['updated_at'];
            }
            $wpdb->insert($points_balance_table, $insert);
        }

        foreach ($data['points_history'] ?? array() as $row) {
            $child_id = intval($child_map[intval($row['child_id'] ?? 0)] ?? 0);
            if (!$child_id) {
                continue;
            }
            $insert = self::import_pick_row_fields($row, array(
                'points', 'week_start', 'reason', 'type', 'created_at',
            ));
            $insert['child_id'] = $child_id;
            $task_id = intval($row['task_id'] ?? 0);
            $insert['task_id'] = ($task_id && !empty($task_map[$task_id])) ? intval($task_map[$task_id]) : null;
            $insert['assignment_id'] = null;
            $wpdb->insert($points_history_table, $insert);
        }

        foreach ($data['rewards'] ?? array() as $row) {
            $old_id = intval($row['id'] ?? 0);
            $insert = self::import_pick_row_fields($row, array(
                'title', 'category', 'details', 'icon', 'points_cost', 'created_at', 'updated_at',
            ));
            $insert['owner_user_id'] = $target_owner_user_id;
            $ok = $wpdb->insert($rewards_table, $insert);
            if ($ok === false || !$old_id) {
                continue;
            }
            $reward_map[$old_id] = intval($wpdb->insert_id);
        }

        foreach ($data['reward_purchases'] ?? array() as $row) {
            $reward_id = intval($reward_map[intval($row['reward_id'] ?? 0)] ?? 0);
            $child_id = intval($child_map[intval($row['child_id'] ?? 0)] ?? 0);
            if (!$reward_id || !$child_id) {
                continue;
            }
            $insert = self::import_pick_row_fields($row, array(
                'points_spent', 'status', 'expires_at', 'created_at',
            ));
            $insert['reward_id'] = $reward_id;
            $insert['child_id'] = $child_id;
            $wpdb->insert($reward_purchases_table, $insert);
        }

        $inviter_user_id = $target_wp_user_id > 0 ? $target_wp_user_id : $target_owner_user_id;
        foreach ($data['invites'] ?? array() as $row) {
            if (!empty($row['accepted_at']) || !empty($row['revoked'])) {
                continue;
            }
            $email = sanitize_email($row['email'] ?? '');
            if ($email === '') {
                continue;
            }
            $role = sanitize_text_field($row['role'] ?? 'parent');
            self::create_invite($target_owner_user_id, $inviter_user_id, $email, $role);
        }

        if ($has_rotation || !empty($payload['needs_regen'])) {
            update_option('rodinne_ulohy_needs_regen_' . $target_owner_user_id, 1, false);
        }

        wp_cache_flush();

        $summary = self::get_owner_data_summary($target_owner_user_id);
        return array(
            'ok' => true,
            'summary' => $summary,
        );
    }

    // -----------------------
    // Multi-adult "family" support (owner resolution)
    // -----------------------
    /**
     * Resolve "effective" owner_user_id for a WP user.
     *
     * Data in plugin tables is scoped by owner_user_id. To allow multiple adults
     * (second parent, grandparents...) to manage the same family, we map each WP user
     * to a shared owner_user_id via user_meta('ru_owner_user_id').
     *
     * If no mapping exists, the WP user is their own owner.
     */
    public static function resolve_owner_user_id_for_wp_user($wp_user_id) {
        $wp_user_id = intval($wp_user_id);
        if ($wp_user_id <= 0) return 0;

        $mapped = get_user_meta($wp_user_id, 'ru_owner_user_id', true);
        $mapped = intval($mapped);
        if ($mapped > 0) return $mapped;

        return $wp_user_id;
    }

    /**
     * Link a WP user into an existing owner's "family".
     */
    public static function set_owner_user_id_for_wp_user($wp_user_id, $owner_user_id) {
        $wp_user_id = intval($wp_user_id);
        $owner_user_id = intval($owner_user_id);
        if ($wp_user_id <= 0 || $owner_user_id <= 0) return false;
        return update_user_meta($wp_user_id, 'ru_owner_user_id', $owner_user_id) ? true : false;
    }
}

