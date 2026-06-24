<?php
/**
 * WP REST API endpoints for ekidio (mobile-friendly).
 */
if (!defined('ABSPATH')) {
    exit;
}

class Rodinne_Ulohy_Rest {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        // Mobile/Capacitor runs from a different origin -> allow Authorization header.
        add_filter('rest_pre_serve_request', array($this, 'add_cors_headers'), 10, 4);
        // Prevent hosting/CDN caches from serving stale JSON for authenticated endpoints.
        add_filter('rest_post_dispatch', array($this, 'add_no_cache_headers'), 10, 3);
        // Replace generic WP REST auth message with a friendlier app-specific one.
        add_filter('rest_authentication_errors', array($this, 'filter_authentication_errors'));
    }

    private function get_current_rest_route() {
        $route = '';

        if (isset($_GET['rest_route'])) {
            $route = wp_unslash($_GET['rest_route']);
        } elseif (!empty($_SERVER['REQUEST_URI'])) {
            $request_uri = wp_unslash($_SERVER['REQUEST_URI']);
            $path = wp_parse_url($request_uri, PHP_URL_PATH);
            $prefix = '/' . rest_get_url_prefix() . '/';
            $pos = is_string($path) ? strpos($path, $prefix) : false;
            if ($pos !== false) {
                $route = substr($path, $pos + strlen($prefix) - 1);
            }
        }

        return is_string($route) ? $route : '';
    }

    private function is_public_rest_route($route) {
        $public_routes = array(
            '/rodinne-ulohy/v1/ping',
            '/rodinne-ulohy/v1/auth/login',
            '/rodinne-ulohy/v1/auth/register',
            '/rodinne-ulohy/v1/auth/register/verify',
            '/rodinne-ulohy/v1/auth/register/resend',
            '/rodinne-ulohy/v1/auth/google',
            '/rodinne-ulohy/v1/auth/invite/accept',
        );

        return in_array($route, $public_routes, true);
    }

    private function has_bearer_token_in_request() {
        $header = '';

        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = strval($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = strval($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }

        return is_string($header) && preg_match('/^\s*Bearer\s+.+$/i', $header) === 1;
    }

    public function filter_authentication_errors($result) {
        if (!is_wp_error($result)) return $result;

        $route = $this->get_current_rest_route();
        if (strpos($route, '/rodinne-ulohy/v1/') !== 0) {
            return $result;
        }

        $code = $result->get_error_code();

        // If our app sends a Bearer token, prefer plugin token auth over WP cookie/nonce auth.
        if ($this->has_bearer_token_in_request() && in_array($code, array('rest_cookie_invalid_nonce', 'rest_not_logged_in'), true)) {
            return null;
        }

        // Public auth endpoints must remain accessible without a WP login.
        if ($this->is_public_rest_route($route) && $code === 'rest_not_logged_in') {
            return null;
        }

        if ($code === 'rest_not_logged_in') {
            return new WP_Error(
                'ru_rest_not_logged_in',
                __('Pre pokračovanie sa prihláste.', 'rodinne-ulohy'),
                array('status' => 401)
            );
        }

        return $result;
    }

    public function add_cors_headers($served, $result, $request, $server) {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        if ($origin) {
            $allowed = array(
                'capacitor://localhost',
                'http://localhost',
                'https://localhost',
            );
            $allow_origin = '';
            if (in_array($origin, $allowed, true)) {
                $allow_origin = $origin;
            } elseif (preg_match('#^https?://localhost:\d+$#', $origin)) {
                $allow_origin = $origin;
            }

            if ($allow_origin) {
                header('Access-Control-Allow-Origin: ' . $allow_origin);
                header('Vary: Origin');
                header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Authorization, Content-Type');
            }
        }
        return $served;
    }

    /**
     * Ensure ekidio REST responses are never cached and vary by Authorization.
     * This prevents "stale JSON until hard refresh" issues on some WP hostings/CDNs.
     */
    public function add_no_cache_headers($response, $server, $request) {
        try {
            $route = $request ? $request->get_route() : '';
            // WP gives routes like "/rodinne-ulohy/v1/child/overview"
            if (strpos($route, '/rodinne-ulohy/v1') !== 0) {
                return $response;
            }
            if (is_wp_error($response)) {
                return $response;
            }
            $res = rest_ensure_response($response);
            if (!($res instanceof WP_REST_Response)) {
                return $res;
            }

            $res->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $res->header('Pragma', 'no-cache');
            $res->header('Expires', '0');

            // Ensure Vary contains Authorization + Origin (for Capacitor CORS)
            $headers = $res->get_headers();
            $vary_raw = '';
            if (isset($headers['Vary'])) $vary_raw = $headers['Vary'];
            if (isset($headers['vary'])) $vary_raw = $headers['vary'];

            $parts = array();
            if (!empty($vary_raw)) {
                $parts = array_filter(array_map('trim', explode(',', strval($vary_raw))));
            }
            if (!in_array('Authorization', $parts, true)) $parts[] = 'Authorization';
            if (!in_array('Origin', $parts, true)) $parts[] = 'Origin';
            $res->header('Vary', implode(', ', $parts));

            return $res;
        } catch (Exception $e) {
            return $response;
        }
    }

    public function register_routes() {
        $ns = 'rodinne-ulohy/v1';

        // Healthcheck (debug)
        register_rest_route($ns, '/ping', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => function() {
                return array('ok' => true, 'ts' => time());
            }
        ));

        // Auth
        register_rest_route($ns, '/auth/login', array(
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => array($this, 'auth_login'),
        ));
        register_rest_route($ns, '/auth/register', array(
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => array($this, 'auth_register'),
        ));
        register_rest_route($ns, '/auth/register/verify', array(
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => array($this, 'auth_register_verify'),
        ));
        register_rest_route($ns, '/auth/register/resend', array(
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => array($this, 'auth_register_resend'),
        ));
        register_rest_route($ns, '/auth/google', array(
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => array($this, 'auth_google'),
        ));
        register_rest_route($ns, '/auth/me', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'permission_authenticated'),
            'callback' => array($this, 'auth_me'),
        ));
        register_rest_route($ns, '/auth/logout', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_authenticated'),
            'callback' => array($this, 'auth_logout'),
        ));

        // Invite-only access (additional adults in the same family)
        register_rest_route($ns, '/auth/invite/accept', array(
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => array($this, 'auth_invite_accept'),
        ));
        register_rest_route($ns, '/family/invites', array(
            array(
                'methods' => 'GET',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'family_invites_list'),
            ),
            array(
                'methods' => 'POST',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'family_invites_create'),
            ),
        ));
        register_rest_route($ns, '/family/invites/revoke', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'family_invites_revoke'),
        ));
        register_rest_route($ns, '/family/members', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'family_members_list'),
        ));

        // Feedback (temporary)
        register_rest_route($ns, '/feedback', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_authenticated'),
            'callback' => array($this, 'feedback_submit'),
        ));

        // Parent (admin) API
        register_rest_route($ns, '/overview', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'parent_overview'),
        ));

        register_rest_route($ns, '/tasks', array(
            array(
                'methods' => 'GET',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'tasks_list'),
            ),
            array(
                'methods' => 'POST',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'tasks_save'),
            ),
        ));

        register_rest_route($ns, '/tasks/library', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'tasks_library'),
        ));
        register_rest_route($ns, '/tasks/import-library', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'tasks_import_from_library'),
        ));
        register_rest_route($ns, '/tasks/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'tasks_get'),
            ),
            array(
                'methods' => 'DELETE',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'tasks_delete'),
            ),
        ));
        register_rest_route($ns, '/tasks/(?P<id>\d+)/days', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'tasks_update_days'),
        ));
        register_rest_route($ns, '/tasks/(?P<id>\d+)/field', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'tasks_update_field'),
        ));
        register_rest_route($ns, '/tasks/(?P<id>\d+)/children/add', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'tasks_add_child'),
        ));
        register_rest_route($ns, '/tasks/(?P<id>\d+)/children/remove', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'tasks_remove_child'),
        ));
        // NOTE: task relations (locked/excluded) were removed.

        register_rest_route($ns, '/children', array(
            array(
                'methods' => 'GET',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'children_list'),
            ),
            array(
                'methods' => 'POST',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'children_save'),
            ),
        ));
        register_rest_route($ns, '/children/(?P<id>\d+)', array(
            array(
                'methods' => 'DELETE',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'children_delete'),
            ),
        ));
        register_rest_route($ns, '/children/reorder', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'children_reorder'),
        ));

        register_rest_route($ns, '/assignments/(?P<id>\d+)/status', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'parent_update_assignment_status'),
        ));

        register_rest_route($ns, '/points/overview', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'points_overview'),
        ));
        register_rest_route($ns, '/points/add', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'points_add'),
        ));
        register_rest_route($ns, '/points/deduct', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'points_deduct'),
        ));
        register_rest_route($ns, '/points/entry/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'points_delete_entry'),
        ));

        // Parent: change WP account password
        register_rest_route($ns, '/parent/password', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'parent_change_password'),
        ));
        register_rest_route($ns, '/parent/account/delete', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'parent_delete_account'),
        ));

        register_rest_route($ns, '/rewards', array(
            array(
                'methods' => 'GET',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'rewards_list'),
            ),
            array(
                'methods' => 'POST',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'rewards_save'),
            ),
        ));
        register_rest_route($ns, '/rewards/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'rewards_delete'),
        ));
        register_rest_route($ns, '/rewards/library', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'rewards_library'),
        ));
        register_rest_route($ns, '/rewards/import-library', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'rewards_import_from_library'),
        ));
        register_rest_route($ns, '/rewards/purchases/(?P<id>\d+)/use', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_authenticated'),
            'callback' => array($this, 'rewards_mark_used'),
        ));

        register_rest_route($ns, '/admin/regenerate-week', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'admin_regenerate_week'),
        ));
        register_rest_route($ns, '/admin/shift-rotation', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'admin_shift_rotation'),
        ));
        register_rest_route($ns, '/admin/shift-task', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'admin_shift_single_task'),
        ));
        register_rest_route($ns, '/admin/rotation-settings', array(
            array(
                'methods' => 'GET',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'admin_get_rotation_settings'),
            ),
            array(
                'methods' => 'POST',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'admin_save_rotation_settings'),
            ),
        ));
        register_rest_route($ns, '/admin/weekend-multiplier', array(
            array(
                'methods' => 'GET',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'admin_get_weekend_multiplier'),
            ),
            array(
                'methods' => 'POST',
                'permission_callback' => array($this, 'permission_parent'),
                'callback' => array($this, 'admin_weekend_multiplier'),
            ),
        ));

        // Dangerous admin resets (parent only)
        register_rest_route($ns, '/admin/reset/children', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'admin_reset_children'),
        ));
        register_rest_route($ns, '/admin/reset/tasks', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'admin_reset_tasks'),
        ));
        register_rest_route($ns, '/admin/reset/rewards', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_parent'),
            'callback' => array($this, 'admin_reset_rewards'),
        ));

        // Child / mobile-friendly endpoints (token-based)
        register_rest_route($ns, '/child/overview', array(
            'methods' => 'GET',
            'permission_callback' => array($this, 'permission_authenticated'),
            'callback' => array($this, 'child_overview'),
        ));
        register_rest_route($ns, '/child/task-status', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_authenticated'),
            'callback' => array($this, 'child_update_task_status'),
        ));
        register_rest_route($ns, '/child/rewards/purchase', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_authenticated'),
            'callback' => array($this, 'child_purchase_reward'),
        ));
        register_rest_route($ns, '/child/avatar', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_authenticated'),
            'callback' => array($this, 'child_save_avatar'),
        ));
        register_rest_route($ns, '/child/avatar/upload', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_authenticated'),
            'callback' => array($this, 'child_upload_avatar'),
        ));
        register_rest_route($ns, '/child/color', array(
            'methods' => 'POST',
            'permission_callback' => array($this, 'permission_authenticated'),
            'callback' => array($this, 'child_set_color'),
        ));
    }

    /**
     * Submit feedback from logged-in parent/child (token auth).
     * Payload: { text: string }
     */
    public function feedback_submit($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;

        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();
        $text = sanitize_textarea_field($body['text'] ?? '');
        $path = sanitize_text_field($body['path'] ?? '');
        if (empty(trim($text))) {
            return new WP_Error('ru_invalid', __('Spätná väzba je prázdna', 'rodinne-ulohy'), array('status' => 400));
        }

        $subject_type = isset($ctx['subject_type']) ? strval($ctx['subject_type']) : '';
        $subject_id = isset($ctx['subject_id']) ? intval($ctx['subject_id']) : 0;

        $name = '';
        if ($subject_type === 'wp_user') {
            $u = wp_get_current_user();
            $name = $u && !empty($u->display_name) ? $u->display_name : ($u && !empty($u->user_login) ? $u->user_login : 'Parent');
        } elseif ($subject_type === 'child') {
            $child = Rodinne_Ulohy_Database::get_child($subject_id);
            $name = $child && !empty($child->name) ? $child->name : 'Child';
        } else {
            $name = 'Unknown';
        }

        $id = Rodinne_Ulohy_Database::add_feedback($subject_type, $subject_id, $name, $text, $path);
        if (!$id) {
            return new WP_Error('ru_failed', __('Chyba pri ukladaní spätnej väzby', 'rodinne-ulohy'), array('status' => 500));
        }

        return array('ok' => true, 'id' => intval($id));
    }

    // -----------------------
    // Auth helpers
    // -----------------------
    private function extract_bearer_token($request) {
        $hdr = $request->get_header('authorization');
        if (!$hdr) return '';
        if (preg_match('/Bearer\s+(.+)/i', $hdr, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function authenticate($request) {
        $token = $this->extract_bearer_token($request);
        if (empty($token)) {
            return new WP_Error('ru_auth_missing', __('Chýba Authorization Bearer token', 'rodinne-ulohy'), array('status' => 401));
        }

        $row = Rodinne_Ulohy_Database::get_api_token($token);
        if (!$row) {
            return new WP_Error('ru_auth_invalid', __('Neplatný alebo expirovaný token', 'rodinne-ulohy'), array('status' => 401));
        }

        $ctx = array(
            'token' => $token,
            'subject_type' => $row->subject_type,
            'subject_id' => intval($row->subject_id),
        );

        if ($row->subject_type === 'wp_user') {
            wp_set_current_user(intval($row->subject_id));
        }

        return $ctx;
    }

    public function permission_authenticated($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        return true;
    }

    public function permission_parent($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        if ($ctx['subject_type'] !== 'wp_user') {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }
        // Parent app should work for non-admin WP roles too (e.g. Prispievateľ).
        if (!current_user_can('read')) {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }
        return true;
    }

    private function is_parent_ctx($ctx) {
        return isset($ctx['subject_type']) && $ctx['subject_type'] === 'wp_user' && current_user_can('read');
    }

    /**
     * Resolve effective owner_user_id for current authenticated WP user.
     * This enables multiple adults to share one "family" dataset.
     */
    private function resolve_owner_user_id($ctx) {
        if (!isset($ctx['subject_type']) || $ctx['subject_type'] !== 'wp_user') return 0;
        $uid = isset($ctx['subject_id']) ? intval($ctx['subject_id']) : 0;
        return Rodinne_Ulohy_Database::resolve_owner_user_id_for_wp_user($uid);
    }

    private function resolve_child_id($request, $ctx) {
        if (isset($ctx['subject_type']) && $ctx['subject_type'] === 'child') {
            return intval($ctx['subject_id']);
        }
        // Parent token: accept explicit child_id (must belong to owner) or fallback to first child of owner
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        $child_id = intval($request->get_param('child_id') ?? 0);
        if ($child_id) {
            $child = Rodinne_Ulohy_Database::get_child($child_id, $owner_user_id);
            if (!$child) {
                return new WP_Error('ru_forbidden', __('Nemáte oprávnenie k tomuto dieťaťu', 'rodinne-ulohy'), array('status' => 403));
            }
            return $child_id;
        }
        $children = Rodinne_Ulohy_Database::get_children('', $owner_user_id);
        if (!empty($children)) {
            return intval($children[0]->id);
        }
        return 0;
    }

    // -----------------------
    // Auth endpoints
    // -----------------------
    private function ru_get_google_client_ids() {
        $raw = '';
        if (defined('RODINNE_ULOHY_GOOGLE_CLIENT_ID')) {
            $raw = strval(constant('RODINNE_ULOHY_GOOGLE_CLIENT_ID'));
        } elseif (defined('RODINNE_ULOHY_GOOGLE_CLIENT_IDS')) {
            $raw = strval(constant('RODINNE_ULOHY_GOOGLE_CLIENT_IDS'));
        }
        if ($raw === '') {
            $raw = strval(get_option('rodinne_ulohy_google_client_id', ''));
        }
        if ($raw === '') {
            $raw = strval(get_option('rodinne_ulohy_google_client_ids', ''));
        }

        $parts = preg_split('/[\s,]+/', $raw);
        $out = array();
        foreach ((array) $parts as $part) {
            $part = trim(strval($part));
            if ($part !== '') $out[] = $part;
        }
        return array_values(array_unique($out));
    }

    private function ru_issue_parent_auth_response($user_id) {
        $user_id = intval($user_id);
        $issued = Rodinne_Ulohy_Database::create_api_token('wp_user', $user_id, 60 * 60 * 24 * 30);
        $user = get_user_by('id', $user_id);
        $owner_user_id = Rodinne_Ulohy_Database::resolve_owner_user_id_for_wp_user($user_id);

        return array(
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'subject' => array(
                'type' => 'parent',
                'user_id' => $user_id,
                'display_name' => $user ? $user->display_name : '',
                'email' => $user ? $user->user_email : '',
                'owner_user_id' => intval($owner_user_id),
            ),
        );
    }

    private function ru_make_email_verification_code() {
        return str_pad(strval(random_int(0, 999999)), 6, '0', STR_PAD_LEFT);
    }

    private function ru_set_email_verification_meta($user_id, $code) {
        $user_id = intval($user_id);
        $code = preg_replace('/\D+/', '', strval($code));
        update_user_meta($user_id, 'ru_email_verified', 0);
        update_user_meta($user_id, 'ru_email_verify_code', $code);
        update_user_meta($user_id, 'ru_email_verify_expires_at', time() + (30 * 60));
    }

    private function ru_send_registration_verification_email($user, $code) {
        if (!$user || empty($user->user_email)) return false;
        $first_name = trim(strval(get_user_meta($user->ID, 'first_name', true)));
        $display = $first_name !== '' ? $first_name : ($user->display_name ?: $user->user_login);
        $subject = __('Overenie emailu pre ekidio', 'rodinne-ulohy');
        $message = sprintf(
            __("Ahoj %s,\n\nďakujeme za registráciu do ekidio.\n\nTvoj overovací kód je: %s\n\nKód platí 30 minút.\n", 'rodinne-ulohy'),
            $display,
            $code
        );

        return $this->ru_send_mail($user->user_email, $subject, $message);
    }

    private function ru_send_mail($to, $subject, $message, $headers = array()) {
        $headers = is_array($headers) ? $headers : array();
        $headers[] = 'From: Ekidio team <info@ekidio.com>';
        return wp_mail($to, $subject, $message, $headers);
    }

    private function ru_set_user_names($user_id, $first_name, $last_name) {
        $user_id = intval($user_id);
        $first_name = sanitize_text_field($first_name);
        $last_name = sanitize_text_field($last_name);
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        $display = trim($first_name . ' ' . $last_name);
        if ($display !== '') {
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $display,
                'nickname' => $display,
            ));
        }
    }

    private function ru_create_unique_username_from_email($email, $fallback = 'rodic') {
        $base = explode('@', strval($email))[0] ?? '';
        $base = sanitize_user($base, true);
        if ($base === '') $base = sanitize_user($fallback, true);
        if ($base === '') $base = 'rodic';

        $candidate = $base;
        $i = 1;
        while (username_exists($candidate)) {
            $candidate = $base . '_' . $i;
            $i++;
            if ($i > 500) {
                $candidate = $base . '_' . wp_generate_password(4, false, false);
                break;
            }
        }
        return $candidate;
    }

    private function ru_get_wp_user_by_login_or_email($login) {
        $login = trim(strval($login));
        if ($login === '') return false;

        if (is_email($login)) {
            $user = get_user_by('email', $login);
            if ($user) return $user;
        }

        return get_user_by('login', $login);
    }

    private function ru_is_user_email_verified($user_id) {
        $value = get_user_meta(intval($user_id), 'ru_email_verified', true);
        if ($value === '' || $value === null) return true;
        return intval($value) === 1;
    }

    private function ru_validate_google_credential($credential) {
        $credential = trim(strval($credential));
        if ($credential === '') {
            return new WP_Error('ru_invalid', __('Chýba Google credential', 'rodinne-ulohy'), array('status' => 400));
        }

        $allowed_ids = $this->ru_get_google_client_ids();
        if (empty($allowed_ids)) {
            return new WP_Error('ru_google_disabled', __('Google prihlásenie nie je nakonfigurované', 'rodinne-ulohy'), array('status' => 501));
        }

        $res = wp_remote_get(
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential),
            array('timeout' => 15)
        );
        if (is_wp_error($res)) {
            return new WP_Error('ru_google_failed', __('Google overenie zlyhalo', 'rodinne-ulohy'), array('status' => 502));
        }

        $status = intval(wp_remote_retrieve_response_code($res));
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if ($status !== 200 || !is_array($body)) {
            return new WP_Error('ru_google_invalid', __('Google credential je neplatný', 'rodinne-ulohy'), array('status' => 401));
        }

        $aud = strval($body['aud'] ?? '');
        $iss = strval($body['iss'] ?? '');
        $sub = strval($body['sub'] ?? '');
        $email = sanitize_email($body['email'] ?? '');
        $email_verified = $body['email_verified'] ?? '';
        $exp = intval($body['exp'] ?? 0);

        if (!$aud || !in_array($aud, $allowed_ids, true)) {
            return new WP_Error('ru_google_invalid', __('Google credential je určený pre iný projekt', 'rodinne-ulohy'), array('status' => 401));
        }
        if (!in_array($iss, array('accounts.google.com', 'https://accounts.google.com'), true)) {
            return new WP_Error('ru_google_invalid', __('Google issuer nie je platný', 'rodinne-ulohy'), array('status' => 401));
        }
        if ($sub === '' || empty($email) || !in_array(strval($email_verified), array('true', '1', 'yes'), true)) {
            return new WP_Error('ru_google_invalid', __('Google účet nemá overený email', 'rodinne-ulohy'), array('status' => 401));
        }
        if ($exp > 0 && $exp < time()) {
            return new WP_Error('ru_google_invalid', __('Google prihlásenie expirovalo', 'rodinne-ulohy'), array('status' => 401));
        }

        return array(
            'sub' => $sub,
            'email' => $email,
            'name' => sanitize_text_field($body['name'] ?? ''),
            'first_name' => sanitize_text_field($body['given_name'] ?? ''),
            'last_name' => sanitize_text_field($body['family_name'] ?? ''),
        );
    }

    public function auth_login($request) {
        $username = sanitize_text_field($request->get_param('username') ?? '');
        $password = $request->get_param('password') ?? '';
        $child_id = intval($request->get_param('child_id') ?? 0);
        $child_code_raw = $request->get_param('child_code') ?? ($request->get_param('code') ?? '');
        $child_code = preg_replace('/\D+/', '', strval($child_code_raw));

        // Parent login (WP user)
        if (!empty($username) || !empty($password)) {
            if (empty($username) || empty($password)) {
                return new WP_Error('ru_login_invalid', __('Chýba meno alebo heslo', 'rodinne-ulohy'), array('status' => 400));
            }
            $user_login = $username;
            $user_by_login = $this->ru_get_wp_user_by_login_or_email($username);
            if ($user_by_login && !empty($user_by_login->user_login)) {
                $user_login = $user_by_login->user_login;
            }

            $user = wp_authenticate($user_login, $password);
            if (is_wp_error($user)) {
                return new WP_Error('ru_login_failed', __('Nesprávne prihlasovacie údaje', 'rodinne-ulohy'), array('status' => 401));
            }
            // Allow any logged-in WP user role to act as parent in the app.
            if (!user_can($user, 'read')) {
                return new WP_Error('ru_login_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
            }
            if (!$this->ru_is_user_email_verified($user->ID) && get_user_meta($user->ID, 'ru_google_sub', true) === '') {
                return new WP_Error('ru_email_not_verified', __('Najprv over svoj email', 'rodinne-ulohy'), array('status' => 403));
            }

            return $this->ru_issue_parent_auth_response($user->ID);
        }

        // Child login by code (6 digits)
        if (strlen($child_code) === 6) {
            $child = Rodinne_Ulohy_Database::get_child_by_login_code($child_code);
            if (!$child) {
                return new WP_Error('ru_login_invalid', __('Neplatný kód dieťaťa', 'rodinne-ulohy'), array('status' => 404));
            }
            $child_id = intval($child->id);
        }

        // Do NOT allow child login by raw child_id (prevents guessing IDs)
        if ($child_id && strlen($child_code) !== 6) {
            return new WP_Error('ru_login_invalid', __('Použite 6-miestny kód dieťaťa', 'rodinne-ulohy'), array('status' => 400));
        }

        if (!$child_id) {
            if ($username === '' && $password === '' && $child_code === '') {
                return new WP_Error('ru_login_invalid', __('Chýba meno alebo heslo', 'rodinne-ulohy'), array('status' => 400));
            }
            return new WP_Error('ru_login_invalid', __('Chýba kód dieťaťa', 'rodinne-ulohy'), array('status' => 400));
        }
        $child = Rodinne_Ulohy_Database::get_child($child_id);
        if (!$child) {
            return new WP_Error('ru_login_invalid', __('Dieťa nebolo nájdené', 'rodinne-ulohy'), array('status' => 404));
        }

        $issued = Rodinne_Ulohy_Database::create_api_token('child', $child_id, 60 * 60 * 24 * 30);
        return array(
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'subject' => array(
                'type' => 'child',
                'child_id' => intval($child->id),
                'name' => $child->name,
            ),
        );
    }

    public function auth_register($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();

        $first_name = sanitize_text_field($body['first_name'] ?? '');
        $last_name = sanitize_text_field($body['last_name'] ?? '');
        $email = sanitize_email($body['email'] ?? '');
        $password = strval($body['password'] ?? '');

        if ($first_name === '' || $last_name === '') {
            return new WP_Error('ru_invalid', __('Vyplň meno aj priezvisko', 'rodinne-ulohy'), array('status' => 400));
        }
        if (!is_email($email)) {
            return new WP_Error('ru_invalid', __('Zadaj platný email', 'rodinne-ulohy'), array('status' => 400));
        }
        if (strlen($password) < 6) {
            return new WP_Error('ru_invalid', __('Heslo musí mať aspoň 6 znakov', 'rodinne-ulohy'), array('status' => 400));
        }

        $user = get_user_by('email', $email);
        $user_id = 0;
        if ($user && !empty($user->ID)) {
            $user_id = intval($user->ID);
            if ($this->ru_is_user_email_verified($user_id) || get_user_meta($user_id, 'ru_google_sub', true) !== '') {
                return new WP_Error('ru_exists', __('Účet s týmto emailom už existuje', 'rodinne-ulohy'), array('status' => 409));
            }

            wp_set_password($password, $user_id);
        } else {
            $username = $this->ru_create_unique_username_from_email($email, $first_name !== '' ? $first_name : 'rodic');
            $created = wp_create_user($username, $password, $email);
            if (is_wp_error($created)) {
                return new WP_Error('ru_failed', $created->get_error_message(), array('status' => 500));
            }
            $user_id = intval($created);
        }

        if (!$user_id) {
            return new WP_Error('ru_failed', __('Nepodarilo sa vytvoriť účet', 'rodinne-ulohy'), array('status' => 500));
        }

        $uobj = new WP_User($user_id);
        if ($uobj && method_exists($uobj, 'set_role') && !user_can($user_id, 'read')) {
            $uobj->set_role('subscriber');
        }

        $this->ru_set_user_names($user_id, $first_name, $last_name);
        delete_user_meta($user_id, 'ru_google_sub');

        $code = $this->ru_make_email_verification_code();
        $this->ru_set_email_verification_meta($user_id, $code);
        $user = get_user_by('id', $user_id);
        $sent = $this->ru_send_registration_verification_email($user, $code);

        return array(
            'ok' => true,
            'email' => $email,
            'email_sent' => $sent ? true : false,
            'verification_required' => true,
        );
    }

    public function auth_register_verify($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();

        $email = sanitize_email($body['email'] ?? '');
        $code = preg_replace('/\D+/', '', strval($body['code'] ?? ''));

        if (!is_email($email) || strlen($code) !== 6) {
            return new WP_Error('ru_invalid', __('Neplatný email alebo kód', 'rodinne-ulohy'), array('status' => 400));
        }

        $user = get_user_by('email', $email);
        if (!$user || empty($user->ID)) {
            return new WP_Error('ru_not_found', __('Registrácia nebola nájdená', 'rodinne-ulohy'), array('status' => 404));
        }

        $stored_code = preg_replace('/\D+/', '', strval(get_user_meta($user->ID, 'ru_email_verify_code', true)));
        $expires_at = intval(get_user_meta($user->ID, 'ru_email_verify_expires_at', true));
        if ($stored_code === '' || $stored_code !== $code) {
            return new WP_Error('ru_invalid', __('Overovací kód nie je správny', 'rodinne-ulohy'), array('status' => 400));
        }
        if ($expires_at > 0 && $expires_at < time()) {
            return new WP_Error('ru_expired', __('Overovací kód expiroval', 'rodinne-ulohy'), array('status' => 410));
        }

        update_user_meta($user->ID, 'ru_email_verified', 1);
        delete_user_meta($user->ID, 'ru_email_verify_code');
        delete_user_meta($user->ID, 'ru_email_verify_expires_at');

        return $this->ru_issue_parent_auth_response($user->ID);
    }

    public function auth_register_resend($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();

        $email = sanitize_email($body['email'] ?? '');
        if (!is_email($email)) {
            return new WP_Error('ru_invalid', __('Zadaj platný email', 'rodinne-ulohy'), array('status' => 400));
        }

        $user = get_user_by('email', $email);
        if (!$user || empty($user->ID)) {
            return new WP_Error('ru_not_found', __('Registrácia nebola nájdená', 'rodinne-ulohy'), array('status' => 404));
        }
        if ($this->ru_is_user_email_verified($user->ID)) {
            return new WP_Error('ru_verified', __('Email je už overený', 'rodinne-ulohy'), array('status' => 409));
        }

        $code = $this->ru_make_email_verification_code();
        $this->ru_set_email_verification_meta($user->ID, $code);
        $sent = $this->ru_send_registration_verification_email($user, $code);

        return array(
            'ok' => true,
            'email' => $email,
            'email_sent' => $sent ? true : false,
        );
    }

    public function auth_google($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();

        $verified = $this->ru_validate_google_credential($body['credential'] ?? '');
        if (is_wp_error($verified)) return $verified;

        $email = sanitize_email($verified['email'] ?? '');
        $sub = strval($verified['sub'] ?? '');
        $first_name = sanitize_text_field($verified['first_name'] ?? '');
        $last_name = sanitize_text_field($verified['last_name'] ?? '');
        $name = sanitize_text_field($verified['name'] ?? '');

        $user = get_user_by('email', $email);
        $user_id = 0;
        if ($user && !empty($user->ID)) {
            $user_id = intval($user->ID);
            $existing_sub = strval(get_user_meta($user_id, 'ru_google_sub', true));
            if ($existing_sub !== '' && $existing_sub !== $sub) {
                return new WP_Error('ru_google_mismatch', __('Tento email je už prepojený s iným Google účtom', 'rodinne-ulohy'), array('status' => 409));
            }
        } else {
            $username = $this->ru_create_unique_username_from_email($email, $first_name !== '' ? $first_name : 'google');
            $created = wp_create_user($username, wp_generate_password(24, true, true), $email);
            if (is_wp_error($created)) {
                return new WP_Error('ru_failed', $created->get_error_message(), array('status' => 500));
            }
            $user_id = intval($created);
        }

        if (!$user_id) {
            return new WP_Error('ru_failed', __('Nepodarilo sa prihlásiť cez Google', 'rodinne-ulohy'), array('status' => 500));
        }

        $uobj = new WP_User($user_id);
        if ($uobj && method_exists($uobj, 'set_role') && !user_can($user_id, 'read')) {
            $uobj->set_role('subscriber');
        }

        if ($first_name !== '' || $last_name !== '') {
            $this->ru_set_user_names($user_id, $first_name, $last_name);
        } elseif ($name !== '') {
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $name,
                'nickname' => $name,
            ));
        }

        update_user_meta($user_id, 'ru_google_sub', $sub);
        update_user_meta($user_id, 'ru_email_verified', 1);
        delete_user_meta($user_id, 'ru_email_verify_code');
        delete_user_meta($user_id, 'ru_email_verify_expires_at');

        return $this->ru_issue_parent_auth_response($user_id);
    }

    public function auth_me($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;

        if ($ctx['subject_type'] === 'wp_user') {
            $u = wp_get_current_user();
            return array(
                'subject' => array(
                    'type' => 'parent',
                    'user_id' => intval($u->ID),
                    'display_name' => $u->display_name,
                )
            );
        }

        $child = Rodinne_Ulohy_Database::get_child(intval($ctx['subject_id']));
        if (!$child) {
            return new WP_Error('ru_not_found', __('Dieťa nebolo nájdené', 'rodinne-ulohy'), array('status' => 404));
        }
        return array(
            'subject' => array(
                'type' => 'child',
                'child_id' => intval($ctx['subject_id']),
                'name' => $child ? $child->name : '',
            )
        );
    }

    public function auth_logout($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        Rodinne_Ulohy_Database::revoke_api_token($ctx['token']);
        return array('ok' => true);
    }

    /**
     * Accept an invite token and (re)set WP credentials for the invited email.
     * This is intentionally invite-only (no public registration form needed).
     */
    public function auth_invite_accept($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();

        $token = trim(strval($body['token'] ?? ''));
        $password = strval($body['password'] ?? '');
        $first_name = sanitize_text_field($body['first_name'] ?? '');
        $last_name = sanitize_text_field($body['last_name'] ?? '');

        if ($token === '' || strlen($token) < 12) {
            return new WP_Error('ru_invalid', __('Neplatný pozývací kód', 'rodinne-ulohy'), array('status' => 400));
        }
        if (strlen($password) < 6) {
            return new WP_Error('ru_invalid', __('Heslo musí mať aspoň 6 znakov', 'rodinne-ulohy'), array('status' => 400));
        }

        $invite = Rodinne_Ulohy_Database::get_invite_by_token($token);
        if (!$invite) {
            return new WP_Error('ru_not_found', __('Pozvánka je neplatná alebo expirovala', 'rodinne-ulohy'), array('status' => 404));
        }

        $owner_user_id = intval($invite->owner_user_id);
        $email = sanitize_email($invite->email);
        if (!$owner_user_id || empty($email)) {
            return new WP_Error('ru_invalid', __('Pozvánka je poškodená', 'rodinne-ulohy'), array('status' => 400));
        }

        // Create or reuse WP user for this email (invite email is the proof).
        $user = get_user_by('email', $email);
        $user_id = 0;
        if ($user && !empty($user->ID)) {
            $user_id = intval($user->ID);
            // Invite works like a "set password" flow (email token is the proof).
            wp_set_password($password, $user_id);
        } else {
            $base = explode('@', $email)[0] ?? '';
            $base = sanitize_user($base, true);
            if ($base === '') $base = 'rodic';
            $candidate = $base;
            $i = 1;
            while (username_exists($candidate)) {
                $candidate = $base . '_' . $i;
                $i++;
                if ($i > 500) {
                    $candidate = $base . '_' . wp_generate_password(4, false, false);
                    break;
                }
            }
            $created = wp_create_user($candidate, $password, $email);
            if (is_wp_error($created)) {
                return new WP_Error('ru_failed', $created->get_error_message(), array('status' => 500));
            }
            $user_id = intval($created);

            // Ensure minimal permissions for parent app (read capability).
            $uobj = new WP_User($user_id);
            if ($uobj && method_exists($uobj, 'set_role')) {
                $uobj->set_role('subscriber');
            }
        }

        if (!$user_id) {
            return new WP_Error('ru_failed', __('Nepodarilo sa vytvoriť účet', 'rodinne-ulohy'), array('status' => 500));
        }

        // Ensure the user can access parent endpoints.
        if (!user_can($user_id, 'read')) {
            $uobj = new WP_User($user_id);
            if ($uobj) {
                $uobj->add_role('subscriber');
            }
        }

        // Link into the invited family (shared owner_user_id).
        Rodinne_Ulohy_Database::set_owner_user_id_for_wp_user($user_id, $owner_user_id);

        // Store name (optional).
        if ($first_name !== '') update_user_meta($user_id, 'first_name', $first_name);
        if ($last_name !== '') update_user_meta($user_id, 'last_name', $last_name);
        if ($first_name !== '' || $last_name !== '') {
            $display = trim(($first_name ? $first_name : '') . ' ' . ($last_name ? $last_name : ''));
            if ($display !== '') {
                wp_update_user(array('ID' => $user_id, 'display_name' => $display));
            }
        }

        update_user_meta($user_id, 'ru_email_verified', 1);
        delete_user_meta($user_id, 'ru_email_verify_code');
        delete_user_meta($user_id, 'ru_email_verify_expires_at');

        // Mark invite accepted.
        Rodinne_Ulohy_Database::accept_invite(intval($invite->id), $user_id);

        // Issue API token for mobile/web app.
        $issued = Rodinne_Ulohy_Database::create_api_token('wp_user', $user_id, 60 * 60 * 24 * 30);
        $u = get_user_by('id', $user_id);

        return array(
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'subject' => array(
                'type' => 'parent',
                'user_id' => intval($user_id),
                'display_name' => $u ? $u->display_name : '',
                'email' => $email,
                'owner_user_id' => $owner_user_id,
            ),
        );
    }

    public function family_invites_list($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }

        $rows = Rodinne_Ulohy_Database::list_invites($owner_user_id, false);
        $out = array();
        foreach ($rows as $r) {
            $inviter = get_user_by('id', intval($r->inviter_user_id));
            $out[] = array(
                'id' => intval($r->id),
                'email' => strval($r->email),
                'role' => strval($r->role),
                'created_at' => strval($r->created_at),
                'expires_at' => strval($r->expires_at),
                'inviter' => array(
                    'user_id' => intval($r->inviter_user_id),
                    'display_name' => $inviter ? ($inviter->display_name ?: $inviter->user_login) : '',
                ),
            );
        }
        return $out;
    }

    public function family_invites_create($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        $inviter_user_id = isset($ctx['subject_id']) ? intval($ctx['subject_id']) : 0;
        if (!$owner_user_id || !$inviter_user_id) {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }

        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();
        $email = sanitize_email($body['email'] ?? '');
        if (empty($email)) {
            return new WP_Error('ru_invalid', __('Zadajte email', 'rodinne-ulohy'), array('status' => 400));
        }

        $invite = Rodinne_Ulohy_Database::create_invite($owner_user_id, $inviter_user_id, $email, 'parent', 60 * 60 * 24 * 7);
        if (!$invite) {
            return new WP_Error('ru_failed', __('Pozvánku sa nepodarilo vytvoriť', 'rodinne-ulohy'), array('status' => 500));
        }

        // Email the invite code (works even when we don't know the exact SPA page URL).
        $inviter = get_user_by('id', $inviter_user_id);
        $inviter_name = $inviter ? ($inviter->display_name ?: $inviter->user_login) : __('ekidio', 'rodinne-ulohy');

        $subject = sprintf(__('Pozvánka do ekidio (%s)', 'rodinne-ulohy'), $inviter_name);
        $message = sprintf(
            __("Boli ste pozvaný/á do ekidio.\n\nPozývací kód:\n%s\n\nAko to použiť:\n1) Otvor aplikáciu ekidio\n2) Prihlásenie → Som rodič → Mám pozvánku\n3) Zadaj kód a nastav si heslo\n\nPozvánka platí 7 dní.\n", 'rodinne-ulohy'),
            $invite['token']
        );
        $sent = false;
        try {
            $sent = $this->ru_send_mail($email, $subject, $message);
        } catch (Throwable $e) {
            $sent = false;
        }

        return array(
            'invite' => array(
                'id' => intval($invite['id']),
                'email' => $invite['email'],
                'role' => $invite['role'],
                'expires_at' => $invite['expires_at'],
                // Returned only to the inviter so they can copy/share it if email fails.
                'code' => $invite['token'],
            ),
            'email_sent' => $sent ? true : false,
        );
    }

    public function family_invites_revoke($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }
        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();
        $invite_id = intval($body['id'] ?? $body['invite_id'] ?? 0);
        if (!$invite_id) {
            return new WP_Error('ru_invalid', __('Neplatné ID pozvánky', 'rodinne-ulohy'), array('status' => 400));
        }
        $ok = Rodinne_Ulohy_Database::revoke_invite($invite_id, $owner_user_id);
        if ($ok === false) {
            return new WP_Error('ru_failed', __('Pozvánku sa nepodarilo zrušiť', 'rodinne-ulohy'), array('status' => 500));
        }
        return array('ok' => true);
    }

    public function family_members_list($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }

        $users = array();
        $owner = get_user_by('id', $owner_user_id);
        if ($owner) {
            $users[intval($owner->ID)] = array(
                'user_id' => intval($owner->ID),
                'display_name' => $owner->display_name ?: $owner->user_login,
                'email' => $owner->user_email ?: '',
                'is_owner' => true,
            );
        }

        $linked = get_users(array(
            'meta_key' => 'ru_owner_user_id',
            'meta_value' => $owner_user_id,
            'fields' => array('ID', 'display_name', 'user_email', 'user_login'),
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => 100,
        ));

        foreach ($linked as $user) {
            $uid = intval($user->ID);
            if ($uid <= 0) continue;
            $users[$uid] = array(
                'user_id' => $uid,
                'display_name' => $user->display_name ?: $user->user_login,
                'email' => $user->user_email ?: '',
                'is_owner' => $uid === intval($owner_user_id),
            );
        }

        return array_values($users);
    }

    // -----------------------
    // Parent endpoints (admin)
    // -----------------------
    public function parent_overview($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $week_range = Rodinne_Ulohy_Database::get_week_range($week_start);
        $assignments = Rodinne_Ulohy_Database::get_week_assignments($week_start, $owner_user_id);

        $grouped = array();
        foreach ($assignments as $a) {
            $child_id = intval($a->child_id);
            if (!isset($grouped[$child_id])) {
                $grouped[$child_id] = array(
                    'child' => array(
                        'id' => $child_id,
                        'name' => $a->child_name,
                        'avatar' => $a->child_avatar,
                    ),
                    'tasks' => array(
                        'povinne' => array(),
                        'dobrovolne' => array(),
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
                'status' => $a->status,
            );
        }

        foreach ($grouped as &$g) {
            foreach (array('povinne', 'dobrovolne') as $cat) {
                $items = $g['tasks'][$cat];
                $completed = 0;
                foreach ($items as $it) {
                    if ($it['status'] === 'completed') $completed++;
                }
                $g['tasks'][$cat] = array(
                    'items' => $items,
                    'completed' => $completed,
                    'total' => count($items),
                );
            }
        }
        unset($g);

        return array(
            'week_start' => $week_start,
            'week_range' => $week_range,
            'children' => array_values($grouped),
        );
    }

    public function tasks_list($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $tasks = Rodinne_Ulohy_Database::get_tasks(null, null, $owner_user_id);
        $result = array();
        foreach ($tasks as $task) {
            // For package tasks, children are assigned via package (not per-task)
            if (!empty($task->package_id)) {
                $children = Rodinne_Ulohy_Database::get_package_children(intval($task->package_id), $owner_user_id);
            } else {
                $children = Rodinne_Ulohy_Database::get_task_children($task->id, $owner_user_id);
            }
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
                'icon' => isset($task->icon) ? (string) $task->icon : '',
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
        return $result;
    }

    public function tasks_library($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }
        $items = Rodinne_Ulohy_Database::get_task_library_items();
        return array(
            'title' => __('Knižnica úloh', 'rodinne-ulohy'),
            'count' => count($items),
            'items' => array_map(function($item) {
                return array(
                    'id' => intval($item->id ?? 0),
                    'name' => (string) ($item->name ?? ''),
                    'description' => (string) ($item->description ?? ''),
                    'task_category' => (string) ($item->task_category ?? 'povinne'),
                    'rotation_enabled' => intval($item->rotation_enabled ?? 0),
                    'days_of_week' => (string) ($item->days_of_week ?? ''),
                    'rating' => intval($item->rating ?? 0),
                );
            }, $items),
        );
    }

    public function tasks_import_from_library($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }
        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();
        $selected_ids = isset($body['selected_ids']) && is_array($body['selected_ids']) ? $body['selected_ids'] : array();
        $selected_ids = array_values(array_unique(array_filter(array_map('intval', $selected_ids))));
        if (empty($selected_ids)) {
            return new WP_Error('ru_invalid', __('Vyberte aspoň jednu úlohu z knižnice', 'rodinne-ulohy'), array('status' => 400));
        }
        $items = Rodinne_Ulohy_Database::get_task_library_items();
        if (empty($items)) {
            return new WP_Error('ru_invalid', __('Knižnica úloh je prázdna', 'rodinne-ulohy'), array('status' => 400));
        }

        $res = Rodinne_Ulohy_Database::import_tasks_from_library($owner_user_id, $selected_ids);
        if (is_wp_error($res)) return $res;
        if ($res === false) {
            return new WP_Error('ru_failed', __('Import sa nepodaril', 'rodinne-ulohy'), array('status' => 500));
        }

        try {
            Rodinne_Ulohy_Rotation::get_instance()->regenerate_current_period($owner_user_id);
            delete_option('rodinne_ulohy_needs_regen_' . intval($owner_user_id));
        } catch (Throwable $e) {
            // ignore: import still succeeded; user can regenerate manually later
        }

        return array(
            'ok' => true,
            'imported' => intval($res['imported'] ?? 0),
        );
    }

    public function rewards_library($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }
        $items = Rodinne_Ulohy_Database::get_reward_library_items();
        return array(
            'title' => __('Knižnica odmien', 'rodinne-ulohy'),
            'count' => count($items),
            'items' => array_map(function($item) {
                return array(
                    'id' => intval($item->id ?? 0),
                    'title' => (string) ($item->title ?? ''),
                    'category' => (string) ($item->category ?? ''),
                    'details' => (string) ($item->details ?? ''),
                    'icon' => (string) ($item->icon ?? ''),
                    'points_cost' => intval($item->points_cost ?? 0),
                );
            }, $items),
        );
    }

    public function rewards_import_from_library($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }
        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();
        $selected_ids = isset($body['selected_ids']) && is_array($body['selected_ids']) ? $body['selected_ids'] : array();
        $selected_ids = array_values(array_unique(array_filter(array_map('intval', $selected_ids))));
        if (empty($selected_ids)) {
            return new WP_Error('ru_invalid', __('Vyberte aspoň jednu odmenu z knižnice', 'rodinne-ulohy'), array('status' => 400));
        }
        $items = Rodinne_Ulohy_Database::get_reward_library_items();
        if (empty($items)) {
            return new WP_Error('ru_invalid', __('Knižnica odmien je prázdna', 'rodinne-ulohy'), array('status' => 400));
        }

        $res = Rodinne_Ulohy_Database::import_rewards_from_library($owner_user_id, $selected_ids);
        if (is_wp_error($res)) return $res;
        if ($res === false) {
            return new WP_Error('ru_failed', __('Import sa nepodaril', 'rodinne-ulohy'), array('status' => 500));
        }

        return array(
            'ok' => true,
            'imported' => intval($res['imported'] ?? 0),
        );
    }

    public function tasks_get($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $id = intval($request['id']);
        $task = Rodinne_Ulohy_Database::get_task($id, $owner_user_id);
        if (!$task) return new WP_Error('ru_not_found', __('Úloha nebola nájdená', 'rodinne-ulohy'), array('status' => 404));
        $task->children = Rodinne_Ulohy_Database::get_task_children($id, $owner_user_id);
        return $task;
    }

    public function tasks_delete($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $id = intval($request['id']);
        if (!$id) return new WP_Error('ru_invalid', __('Neplatné ID', 'rodinne-ulohy'), array('status' => 400));
        $task = Rodinne_Ulohy_Database::get_task($id, $owner_user_id);
        if (!$task) return new WP_Error('ru_not_found', __('Úloha nebola nájdená', 'rodinne-ulohy'), array('status' => 404));
        $res = Rodinne_Ulohy_Database::delete_task($id);
        if ($res === false) return new WP_Error('ru_failed', __('Chyba pri odstraňovaní', 'rodinne-ulohy'), array('status' => 500));
        if ($owner_user_id && intval($task->rotation_enabled ?? 0) === 1) {
            update_option('rodinne_ulohy_needs_regen_' . $owner_user_id, 1, false);
            // Auto-regenerate immediately (does NOT advance rotation).
            $ok = Rodinne_Ulohy_Rotation::get_instance()->regenerate_current_period($owner_user_id);
            if ($ok) {
                delete_option('rodinne_ulohy_needs_regen_' . $owner_user_id);
            }
        }
        return array('ok' => true);
    }

    public function tasks_save($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $task = $request->get_json_params();
        if (!is_array($task)) $task = array();

        // days_of_week drives "shows up on selected days", which is daily behavior in the app.
        // Ensure task_type matches so points can be awarded per-day (not once per week).
        $days_of_week = sanitize_text_field($task['days_of_week'] ?? '');
        $task_type = sanitize_text_field($task['task_type'] ?? 'daily');
        if (!empty($days_of_week)) {
            $task_type = 'daily';
        }
        // Legacy: 'weekend' is mapped to Saturday-only days_of_week=6 and stored as 'daily'.
        if ($task_type === 'weekend') {
            if (empty($days_of_week)) {
                $days_of_week = '6';
            }
            $task_type = 'daily';
        }

        $data = array(
            'id' => intval($task['id'] ?? 0),
            'package_id' => !empty($task['package_id']) ? intval($task['package_id']) : null,
            'owner_user_id' => $owner_user_id,
            'name' => sanitize_text_field($task['name'] ?? ''),
            'description' => sanitize_textarea_field($task['description'] ?? ''),
            'task_type' => $task_type,
            'days_of_week' => $days_of_week,
            'task_category' => sanitize_text_field($task['task_category'] ?? 'povinne'),
            'rotation_enabled' => !empty($task['rotation_enabled']) ? 1 : 0,
            'shared_task' => !empty($task['shared_task']) ? 1 : 0,
            'estimated_time' => $task['estimated_time'] ?? null,
            'rating' => $task['rating'] ?? null,
            'icon' => sanitize_text_field($task['icon'] ?? ''),
        );

        $is_new = empty($data['id']);
        $prev_task = null;
        $prev_children_ids = array();
        if (!$is_new) {
            $prev_task = Rodinne_Ulohy_Database::get_task(intval($data['id']), $owner_user_id);
            $prev_children = Rodinne_Ulohy_Database::get_task_children(intval($data['id']), $owner_user_id);
            foreach ($prev_children as $pc) $prev_children_ids[] = intval($pc->id);
            sort($prev_children_ids);
        }

        if (!empty($data['id'])) {
            $existing = Rodinne_Ulohy_Database::get_task(intval($data['id']), $owner_user_id);
            if (!$existing) {
                return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
            }
        }

        if (empty($data['name'])) {
            return new WP_Error('ru_invalid', __('Názov úlohy je povinný', 'rodinne-ulohy'), array('status' => 400));
        }

        $result = Rodinne_Ulohy_Database::save_task($data);
        if ($result === false) {
            return new WP_Error('ru_failed', __('Chyba pri ukladaní', 'rodinne-ulohy'), array('status' => 500));
        }

        global $wpdb;
        $task_id = $data['id'] ? $data['id'] : intval($wpdb->insert_id);

        // Save assigned children for standalone tasks (same behavior as AJAX)
        $assigned_children = $task['assigned_children'] ?? $task['children'] ?? array();
        if (empty($data['package_id'])) {
            $child_ids = array();
            if (is_array($assigned_children)) {
                foreach ($assigned_children as $c) {
                    $child_ids[] = intval(is_array($c) ? ($c['id'] ?? 0) : $c);
                }
                $child_ids = array_filter($child_ids);
            }
            if ($owner_user_id) {
                $allowed = Rodinne_Ulohy_Database::get_children('', $owner_user_id);
                $allowed_ids = array();
                foreach ($allowed as $ac) $allowed_ids[intval($ac->id)] = true;
                $child_ids = array_values(array_filter($child_ids, function($cid) use ($allowed_ids) {
                    return isset($allowed_ids[intval($cid)]);
                }));
            }
            Rodinne_Ulohy_Database::save_task_children($task_id, $child_ids);

            // If a rotating task is NEW or its rotation conditions changed, mark that redistribution is needed.
            // (e.g. user added/removed children from rotation)
            if ($owner_user_id && intval($data['rotation_enabled']) === 1) {
                $new_children_ids = array_map('intval', $child_ids);
                sort($new_children_ids);
                $prev_rotation = $prev_task ? intval($prev_task->rotation_enabled ?? 1) : 1;
                $prev_rating = $prev_task ? intval($prev_task->rating ?? 0) : 0;
                $new_rating = intval($data['rating'] ?? 0);

                $conditions_changed =
                    $is_new ||
                    $prev_rotation !== 1 || // switched from non-rotating -> rotating
                    $prev_children_ids !== $new_children_ids ||
                    $prev_rating !== $new_rating;

                if ($conditions_changed) {
                    update_option('rodinne_ulohy_needs_regen_' . $owner_user_id, 1, false);
                    // Auto-regenerate immediately (does NOT advance rotation).
                    $ok = Rodinne_Ulohy_Rotation::get_instance()->regenerate_current_period($owner_user_id);
                    if ($ok) {
                        delete_option('rodinne_ulohy_needs_regen_' . $owner_user_id);
                    }
                }
            }

            // If task is non-rotating, assign it immediately for the current week.
            if (intval($data['rotation_enabled']) === 0) {
                global $wpdb;
                $week_start = Rodinne_Ulohy_Database::get_current_week_start();
                $assignments_table = $wpdb->prefix . 'rodinne_ulohy_assignments';
                $children_table = $wpdb->prefix . 'rodinne_ulohy_children';

                // If no explicit children selected, assign to all children of this owner.
                if (empty($child_ids) && $owner_user_id) {
                    $child_ids = array();
                    foreach ($allowed as $ac) $child_ids[] = intval($ac->id);
                }

                // Remove assignments for children that are no longer selected (current week only, scoped to owner).
                if ($owner_user_id) {
                    if (!empty($child_ids)) {
                        $in = implode(',', array_fill(0, count($child_ids), '%d'));
                        $sql = "DELETE a FROM $assignments_table a
                                INNER JOIN $children_table c ON a.child_id = c.id
                                WHERE a.week_start = %s AND a.task_id = %d AND c.owner_user_id = %d
                                  AND a.child_id NOT IN ($in)";
                        $args = array_merge(array($week_start, $task_id, $owner_user_id), $child_ids);
                        $wpdb->query($wpdb->prepare($sql, ...$args));
                    } else {
                        $wpdb->query($wpdb->prepare(
                            "DELETE a FROM $assignments_table a
                             INNER JOIN $children_table c ON a.child_id = c.id
                             WHERE a.week_start = %s AND a.task_id = %d AND c.owner_user_id = %d",
                            $week_start,
                            $task_id,
                            $owner_user_id
                        ));
                    }
                }

                // Ensure assignments exist for selected/all children.
                if (!empty($child_ids)) {
                    foreach ($child_ids as $cid) {
                        Rodinne_Ulohy_Database::save_assignment($task_id, intval($cid), $week_start, 'todo');
                    }
                }
            }
        } else {
            Rodinne_Ulohy_Database::save_task_children($task_id, array());
        }

        return array('id' => $task_id);
    }

    public function tasks_update_days($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $task_id = intval($request['id']);
        if ($owner_user_id) {
            $existing = Rodinne_Ulohy_Database::get_task($task_id, $owner_user_id);
            if (!$existing) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }
        $body = $request->get_json_params();
        $days = isset($body['days_of_week']) ? sanitize_text_field($body['days_of_week']) : '';
        $res = Rodinne_Ulohy_Database::update_task_field($task_id, 'days_of_week', $days);
        if ($res === false) return new WP_Error('ru_failed', __('Chyba pri aktualizácii', 'rodinne-ulohy'), array('status' => 500));
        return array('ok' => true);
    }

    public function tasks_update_field($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $task_id = intval($request['id']);
        if ($owner_user_id) {
            $existing = Rodinne_Ulohy_Database::get_task($task_id, $owner_user_id);
            if (!$existing) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }
        $body = $request->get_json_params();
        $field = sanitize_text_field($body['field'] ?? '');
        $value = $body['value'] ?? '';
        if (!$field) return new WP_Error('ru_invalid', __('Neplatné parametre', 'rodinne-ulohy'), array('status' => 400));
        $res = Rodinne_Ulohy_Database::update_task_field($task_id, $field, $value);
        if ($res === false) return new WP_Error('ru_failed', __('Chyba pri aktualizácii', 'rodinne-ulohy'), array('status' => 500));
        return array('ok' => true);
    }

    public function tasks_add_child($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $task_id = intval($request['id']);
        $body = $request->get_json_params();
        $child_id = intval($body['child_id'] ?? 0);
        if (!$task_id || !$child_id) return new WP_Error('ru_invalid', __('Neplatné parametre', 'rodinne-ulohy'), array('status' => 400));
        if ($owner_user_id) {
            $task = Rodinne_Ulohy_Database::get_task($task_id, $owner_user_id);
            if (!$task) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
            $child = Rodinne_Ulohy_Database::get_child($child_id, $owner_user_id);
            if (!$child) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }
        $current = Rodinne_Ulohy_Database::get_task_children($task_id);
        $ids = array();
        foreach ($current as $c) $ids[] = intval($c->id);
        if (!in_array($child_id, $ids)) $ids[] = $child_id;
        $ok = Rodinne_Ulohy_Database::save_task_children($task_id, $ids);
        if ($ok === false) return new WP_Error('ru_failed', __('Chyba pri pridávaní dieťaťa', 'rodinne-ulohy'), array('status' => 500));
        if ($owner_user_id) {
            $task = Rodinne_Ulohy_Database::get_task($task_id, $owner_user_id);
            if ($task && intval($task->rotation_enabled ?? 0) === 1) {
                update_option('rodinne_ulohy_needs_regen_' . $owner_user_id, 1, false);
                // Auto-regenerate immediately (does NOT advance rotation).
                $ok = Rodinne_Ulohy_Rotation::get_instance()->regenerate_current_period($owner_user_id);
                if ($ok) {
                    delete_option('rodinne_ulohy_needs_regen_' . $owner_user_id);
                }
            }
        }
        return array('ok' => true);
    }

    public function tasks_remove_child($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $task_id = intval($request['id']);
        $body = $request->get_json_params();
        $child_id = intval($body['child_id'] ?? 0);
        if (!$task_id || !$child_id) return new WP_Error('ru_invalid', __('Neplatné parametre', 'rodinne-ulohy'), array('status' => 400));
        if ($owner_user_id) {
            $task = Rodinne_Ulohy_Database::get_task($task_id, $owner_user_id);
            if (!$task) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
            $child = Rodinne_Ulohy_Database::get_child($child_id, $owner_user_id);
            if (!$child) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }
        $current = Rodinne_Ulohy_Database::get_task_children($task_id);
        $ids = array();
        foreach ($current as $c) $ids[] = intval($c->id);
        $ids = array_values(array_diff($ids, array($child_id)));
        $ok = Rodinne_Ulohy_Database::save_task_children($task_id, $ids);
        if ($ok === false) return new WP_Error('ru_failed', __('Chyba pri odstraňovaní dieťaťa', 'rodinne-ulohy'), array('status' => 500));
        if ($owner_user_id) {
            $task = Rodinne_Ulohy_Database::get_task($task_id, $owner_user_id);
            if ($task && intval($task->rotation_enabled ?? 0) === 1) {
                update_option('rodinne_ulohy_needs_regen_' . $owner_user_id, 1, false);
                // Auto-regenerate immediately (does NOT advance rotation).
                $ok = Rodinne_Ulohy_Rotation::get_instance()->regenerate_current_period($owner_user_id);
                if ($ok) {
                    delete_option('rodinne_ulohy_needs_regen_' . $owner_user_id);
                }
            }
        }
        return array('ok' => true);
    }

    public function tasks_save_relations($request) {
        return new WP_Error(
            'ru_removed',
            __('Možnosť zomknutých/vylúčených úloh bola odstránená.', 'rodinne-ulohy'),
            array('status' => 410)
        );
    }

    public function children_list($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $children = Rodinne_Ulohy_Database::get_children('', $owner_user_id);
        $out = array();
        foreach ($children as $c) {
            $code = Rodinne_Ulohy_Database::ensure_child_login_code(intval($c->id));
            $out[] = array(
                'id' => intval($c->id),
                'name' => $c->name,
                'avatar_url' => isset($c->avatar_url) ? $c->avatar_url : '',
                'color' => isset($c->color) ? $c->color : '',
                'login_code' => $code,
                'sort_order' => isset($c->sort_order) ? intval($c->sort_order) : 0,
            );
        }
        return $out;
    }

    public function children_save($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();
        $data = array(
            'id' => intval($body['id'] ?? $body['child_id'] ?? 0),
            'owner_user_id' => $owner_user_id,
            'name' => sanitize_text_field($body['name'] ?? $body['child_name'] ?? ''),
            'email' => sanitize_email($body['email'] ?? $body['child_email'] ?? ''),
            'password' => $body['password'] ?? $body['child_password'] ?? '',
            'avatar_url' => esc_url_raw($body['avatar_url'] ?? $body['child_avatar_url'] ?? ''),
            'color' => sanitize_hex_color($body['color'] ?? $body['child_color'] ?? '#4CAF50'),
        );
        $is_new = empty($data['id']);
        if (empty($data['name'])) return new WP_Error('ru_invalid', __('Meno je povinné', 'rodinne-ulohy'), array('status' => 400));
        if (!empty($data['id'])) {
            $existing = Rodinne_Ulohy_Database::get_child(intval($data['id']), $owner_user_id);
            if (!$existing) {
                return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
            }
        }
        $res = Rodinne_Ulohy_Database::save_child($data);
        if ($res === false) return new WP_Error('ru_failed', __('Chyba pri ukladaní', 'rodinne-ulohy'), array('status' => 500));
        global $wpdb;
        $child_id = $data['id'] ? $data['id'] : intval($wpdb->insert_id);
        // If a new child was added, rotating tasks that rotate among "all children" need redistribution.
        if ($is_new && $owner_user_id) {
            update_option('rodinne_ulohy_needs_regen_' . $owner_user_id, 1, false);
            // Auto-regenerate immediately (does NOT advance rotation).
            $ok = Rodinne_Ulohy_Rotation::get_instance()->regenerate_current_period($owner_user_id);
            if ($ok) {
                delete_option('rodinne_ulohy_needs_regen_' . $owner_user_id);
            }
        }
        return array('id' => $child_id);
    }

    public function children_delete($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $id = intval($request['id']);
        if (!$id) return new WP_Error('ru_invalid', __('Neplatné ID', 'rodinne-ulohy'), array('status' => 400));
        $existing = Rodinne_Ulohy_Database::get_child($id, $owner_user_id);
        if (!$existing) return new WP_Error('ru_not_found', __('Dieťa nebolo nájdené', 'rodinne-ulohy'), array('status' => 404));
        $res = Rodinne_Ulohy_Database::delete_child($id);
        if ($res === false) return new WP_Error('ru_failed', __('Chyba pri odstraňovaní', 'rodinne-ulohy'), array('status' => 500));
        if ($owner_user_id) {
            update_option('rodinne_ulohy_needs_regen_' . $owner_user_id, 1, false);
            // Auto-regenerate immediately (does NOT advance rotation).
            $ok = Rodinne_Ulohy_Rotation::get_instance()->regenerate_current_period($owner_user_id);
            if ($ok) {
                delete_option('rodinne_ulohy_needs_regen_' . $owner_user_id);
            }
        }
        return array('ok' => true);
    }

    public function children_reorder($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }

        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();
        $ids = $body['ids'] ?? $body['children'] ?? array();
        if (!is_array($ids)) $ids = array();
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return new WP_Error('ru_invalid', __('Neplatné poradie', 'rodinne-ulohy'), array('status' => 400));
        }

        // Validate ownership
        $allowed = Rodinne_Ulohy_Database::get_children('', $owner_user_id);
        $allowed_ids = array();
        foreach ($allowed as $c) $allowed_ids[intval($c->id)] = true;
        foreach ($ids as $cid) {
            if (!isset($allowed_ids[intval($cid)])) {
                return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rodinne_ulohy_children';
        $cols = $wpdb->get_col("DESCRIBE $table");
        if (empty($cols) || !is_array($cols) || !in_array('sort_order', $cols, true)) {
            // Ensure migration ran
            $wpdb->query("ALTER TABLE $table ADD COLUMN sort_order int(11) NOT NULL DEFAULT 0 AFTER owner_user_id");
            $wpdb->query("ALTER TABLE $table ADD KEY sort_order (sort_order)");
        }

        $i = 1;
        foreach ($ids as $cid) {
            $wpdb->update(
                $table,
                array('sort_order' => $i),
                array('id' => intval($cid), 'owner_user_id' => $owner_user_id),
                array('%d'),
                array('%d', '%d')
            );
            $i++;
        }
        return array('ok' => true);
    }

    public function parent_update_assignment_status($request) {
        $assignment_id = intval($request['id']);
        $body = $request->get_json_params();
        $status = sanitize_text_field($body['status'] ?? 'todo');
        if (!$assignment_id) return new WP_Error('ru_invalid', __('Neplatné ID', 'rodinne-ulohy'), array('status' => 400));

        // Copy logic from Rodinne_Ulohy_Ajax::update_assignment_status
        global $wpdb;
        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, t.rating, t.task_category, t.task_type, t.name as task_name
             FROM {$wpdb->prefix}rodinne_ulohy_assignments a
             INNER JOIN {$wpdb->prefix}rodinne_ulohy_tasks t ON a.task_id = t.id
             WHERE a.id = %d",
            $assignment_id
        ));
        if (!$assignment) return new WP_Error('ru_not_found', __('Priradenie nebolo nájdené', 'rodinne-ulohy'), array('status' => 404));

        $old_status = $assignment->status;
        $result = Rodinne_Ulohy_Database::update_assignment_status($assignment_id, $status);
        if ($result === false) return new WP_Error('ru_failed', __('Chyba pri aktualizácii', 'rodinne-ulohy'), array('status' => 500));

        $points_added = 0;
        $points_message = '';
        if ($old_status !== $status) {
            $week_start = $assignment->week_start;
            $rating = isset($assignment->rating) && $assignment->rating !== null ? intval($assignment->rating) : 0;
            $task_category = isset($assignment->task_category) ? $assignment->task_category : 'povinne';
            $task_type = isset($assignment->task_type) ? $assignment->task_type : 'daily';

            if ($status === 'completed') {
                $already_added = Rodinne_Ulohy_Database::points_already_added($assignment_id, $task_type);
                if (!$already_added && $rating > 0) {
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
                    $points_message = __('Body už boli pripočítané', 'rodinne-ulohy');
                }
            } elseif ($old_status === 'completed' && $status !== 'completed') {
                $last_points = Rodinne_Ulohy_Database::get_last_points_for_assignment($assignment_id);
                if ($last_points && $last_points->points > 0) {
                    $can_reverse = false;
                    if ($task_type === 'daily') {
                        $today = current_time('Y-m-d');
                        if (date('Y-m-d', strtotime($last_points->created_at)) === $today) $can_reverse = true;
                    } else {
                        if ($last_points->week_start === $week_start) $can_reverse = true;
                    }
                    if ($can_reverse) {
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
                if ($rating > 0) $points_message = '-' . $rating;
            }
        }

        $points_balance = Rodinne_Ulohy_Database::get_points_balance($assignment->child_id);
        return array(
            'points_balance' => intval($points_balance->balance),
            'points_added' => $points_added,
            'points_message' => $points_message,
        );
    }

    public function points_overview($request) {
        // This endpoint is protected by permission_parent(), which already authenticates.
        // But it can also be called internally (e.g. from points_add/points_deduct) without headers.
        $ctx = $this->authenticate($request);
        $owner_user_id = 0;
        if (!is_wp_error($ctx)) {
            $owner_user_id = $this->resolve_owner_user_id($ctx);
        } elseif (is_user_logged_in() && current_user_can('read')) {
            $owner_user_id = Rodinne_Ulohy_Database::resolve_owner_user_id_for_wp_user(get_current_user_id());
        } else {
            return $ctx;
        }

        $child_id = intval($request->get_param('child_id') ?? 0);
        if (!$child_id) {
            $children = Rodinne_Ulohy_Database::get_children('', $owner_user_id);
            if (!empty($children)) $child_id = intval($children[0]->id);
        }
        if (!$child_id) return new WP_Error('ru_invalid', __('Neplatné ID dieťaťa', 'rodinne-ulohy'), array('status' => 400));

        // Ensure child belongs to this owner
        if ($owner_user_id) {
            $child = Rodinne_Ulohy_Database::get_child($child_id, $owner_user_id);
            if (!$child) {
                return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
            }
        }

        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        return array(
            'child_id' => $child_id,
            'week_range' => Rodinne_Ulohy_Database::get_week_range($week_start),
            'points_balance' => intval(Rodinne_Ulohy_Database::get_points_balance($child_id)->balance),
            'points_today' => intval(Rodinne_Ulohy_Database::get_today_points_total($child_id)),
            'points_week' => intval(Rodinne_Ulohy_Database::get_week_points_total($child_id, $week_start)),
            'week_summary' => Rodinne_Ulohy_Database::get_week_points_summary($child_id, $week_start),
            // UI wants a rolling window (not just "this week").
            'history' => Rodinne_Ulohy_Database::get_points_history_last_days($child_id, 7),
        );
    }

    public function points_add($request) {
        $ctx = $this->authenticate($request);
        $owner_user_id = 0;
        if (!is_wp_error($ctx)) {
            $owner_user_id = $this->resolve_owner_user_id($ctx);
        } elseif (is_user_logged_in() && current_user_can('read')) {
            $owner_user_id = Rodinne_Ulohy_Database::resolve_owner_user_id_for_wp_user(get_current_user_id());
        } else {
            return $ctx;
        }

        $body = $request->get_json_params();
        $child_id = intval($body['child_id'] ?? 0);
        $points = intval($body['points'] ?? 0);
        $reason = sanitize_text_field($body['reason'] ?? __('Manuálne pripočítanie', 'rodinne-ulohy'));
        if (!$child_id || $points <= 0) return new WP_Error('ru_invalid', __('Neplatné údaje', 'rodinne-ulohy'), array('status' => 400));
        if ($owner_user_id) {
            $child = Rodinne_Ulohy_Database::get_child($child_id, $owner_user_id);
            if (!$child) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }

        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $res = Rodinne_Ulohy_Database::add_points($child_id, $points, $week_start, null, null, $reason, 'manual');
        if ($res === false) return new WP_Error('ru_failed', __('Chyba pri pripočítaní bodov', 'rodinne-ulohy'), array('status' => 500));
        $req = new WP_REST_Request('GET');
        $req->set_param('child_id', $child_id);
        return $this->points_overview($req);
    }

    public function points_deduct($request) {
        $ctx = $this->authenticate($request);
        $owner_user_id = 0;
        if (!is_wp_error($ctx)) {
            $owner_user_id = $this->resolve_owner_user_id($ctx);
        } elseif (is_user_logged_in() && current_user_can('read')) {
            $owner_user_id = Rodinne_Ulohy_Database::resolve_owner_user_id_for_wp_user(get_current_user_id());
        } else {
            return $ctx;
        }

        $body = $request->get_json_params();
        $child_id = intval($body['child_id'] ?? 0);
        $points = intval($body['points'] ?? 0);
        $reason = sanitize_text_field($body['reason'] ?? __('Manuálne odpočítanie', 'rodinne-ulohy'));
        if (!$child_id || $points <= 0) return new WP_Error('ru_invalid', __('Neplatné údaje', 'rodinne-ulohy'), array('status' => 400));
        if ($owner_user_id) {
            $child = Rodinne_Ulohy_Database::get_child($child_id, $owner_user_id);
            if (!$child) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }

        $week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $res = Rodinne_Ulohy_Database::add_points($child_id, -$points, $week_start, null, null, $reason, 'manual');
        if ($res === false) return new WP_Error('ru_failed', __('Chyba pri odpočítaní bodov', 'rodinne-ulohy'), array('status' => 500));
        $req = new WP_REST_Request('GET');
        $req->set_param('child_id', $child_id);
        return $this->points_overview($req);
    }

    public function points_delete_entry($request) {
        $entry_id = intval($request['id']);
        if (!$entry_id) return new WP_Error('ru_invalid', __('Neplatné ID záznamu', 'rodinne-ulohy'), array('status' => 400));
        $res = Rodinne_Ulohy_Database::delete_points_entry($entry_id);
        if (!$res) return new WP_Error('ru_failed', __('Záznam sa nepodarilo odstrániť', 'rodinne-ulohy'), array('status' => 500));
        return array('ok' => true);
    }

    /**
     * Parent: change WP user password (requires current password).
     */
    public function parent_change_password($request) {
        // permission_parent already authenticated and set current user.
        $u = wp_get_current_user();
        if (!$u || empty($u->ID)) {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }

        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();
        $current = strval($body['current_password'] ?? '');
        $new = strval($body['new_password'] ?? '');

        if ($current === '' || $new === '') {
            return new WP_Error('ru_invalid', __('Vyplňte aktuálne aj nové heslo', 'rodinne-ulohy'), array('status' => 400));
        }
        if (strlen($new) < 6) {
            return new WP_Error('ru_invalid', __('Nové heslo musí mať aspoň 6 znakov', 'rodinne-ulohy'), array('status' => 400));
        }
        if (!wp_check_password($current, $u->user_pass, $u->ID)) {
            return new WP_Error('ru_invalid', __('Aktuálne heslo nie je správne', 'rodinne-ulohy'), array('status' => 400));
        }

        // Update password
        wp_set_password($new, $u->ID);
        return array('ok' => true);
    }

    /**
     * Parent: permanently delete the authenticated WP user account.
     */
    public function parent_delete_account($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;

        $u = wp_get_current_user();
        if (!$u || empty($u->ID)) {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }

        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();
        $current = strval($body['current_password'] ?? '');
        $confirm = strtoupper(trim(strval($body['confirm_text'] ?? '')));
        $confirm = str_replace(array('Á', 'Ä'), array('A', 'A'), $confirm);

        $google_sub = strval(get_user_meta($u->ID, 'ru_google_sub', true));
        $has_google = $google_sub !== '';

        if ($has_google && $current === '') {
            if ($confirm !== 'ZRUSIT') {
                return new WP_Error('ru_invalid', __('Pre potvrdenie napíšte ZRUŠIŤ', 'rodinne-ulohy'), array('status' => 400));
            }
        } elseif ($current === '' || !wp_check_password($current, $u->user_pass, $u->ID)) {
            return new WP_Error('ru_invalid', __('Heslo nie je správne', 'rodinne-ulohy'), array('status' => 400));
        }

        $user_id = intval($u->ID);
        $token = isset($ctx['token']) ? strval($ctx['token']) : '';

        $res = Rodinne_Ulohy_Database::delete_wp_user_account($user_id);
        if (is_wp_error($res)) return $res;
        if ($res === false) {
            return new WP_Error('ru_failed', __('Účet sa nepodarilo zrušiť', 'rodinne-ulohy'), array('status' => 500));
        }

        if ($token !== '') {
            Rodinne_Ulohy_Database::revoke_api_token($token);
        }

        return array(
            'ok' => true,
            'deleted_owner_data' => !empty($res['deleted_owner_data']),
        );
    }

    public function rewards_list($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = 0;
        if ($ctx['subject_type'] === 'wp_user') {
            $owner_user_id = $this->resolve_owner_user_id($ctx);
        } elseif ($ctx['subject_type'] === 'child') {
            $child = Rodinne_Ulohy_Database::get_child(intval($ctx['subject_id']));
            $owner_user_id = $child ? intval($child->owner_user_id) : 0;
        }
        // SECURITY: never return "global" rewards. If we can't resolve owner, return empty.
        if (!$owner_user_id) {
            return array();
        }
        return Rodinne_Ulohy_Database::get_rewards($owner_user_id);
    }

    public function rewards_save($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();
        $data = array(
            'id' => intval($body['id'] ?? 0),
            'owner_user_id' => $owner_user_id,
            'title' => sanitize_text_field($body['title'] ?? ''),
            'category' => sanitize_text_field($body['category'] ?? ''),
            'details' => sanitize_text_field($body['details'] ?? ''),
            'icon' => sanitize_text_field($body['icon'] ?? ''),
            'points_cost' => intval($body['points_cost'] ?? 0),
        );
        if (empty($data['title'])) return new WP_Error('ru_invalid', __('Názov je povinný', 'rodinne-ulohy'), array('status' => 400));
        if (!empty($data['id'])) {
            $existing = Rodinne_Ulohy_Database::get_reward(intval($data['id']), $owner_user_id);
            if (!$existing) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }
        $res = Rodinne_Ulohy_Database::save_reward($data);
        if ($res === false) return new WP_Error('ru_failed', __('Chyba pri ukladaní odmeny', 'rodinne-ulohy'), array('status' => 500));
        return array('ok' => true);
    }

    public function rewards_delete($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $id = intval($request['id']);
        if (!$id) return new WP_Error('ru_invalid', __('Neplatné ID odmeny', 'rodinne-ulohy'), array('status' => 400));
        $existing = Rodinne_Ulohy_Database::get_reward($id, $owner_user_id);
        if (!$existing) return new WP_Error('ru_not_found', __('Odmena nebola nájdená', 'rodinne-ulohy'), array('status' => 404));
        $res = Rodinne_Ulohy_Database::delete_reward($id);
        if ($res === false) return new WP_Error('ru_failed', __('Chyba pri odstraňovaní odmeny', 'rodinne-ulohy'), array('status' => 500));
        return array('ok' => true);
    }

    public function rewards_mark_used($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;

        $purchase_id = intval($request['id']);
        if (!$purchase_id) return new WP_Error('ru_invalid', __('Neplatné ID nákupu', 'rodinne-ulohy'), array('status' => 400));

        global $wpdb;
        $table_purchases = $wpdb->prefix . 'rodinne_ulohy_reward_purchases';
        $table_children = $wpdb->prefix . 'rodinne_ulohy_children';

        $purchase = $wpdb->get_row($wpdb->prepare(
            "SELECT rp.id, rp.child_id, rp.status, c.owner_user_id
             FROM $table_purchases rp
             INNER JOIN $table_children c ON rp.child_id = c.id
             WHERE rp.id = %d",
            $purchase_id
        ));
        if (!$purchase) {
            return new WP_Error('ru_not_found', __('Nákup nebol nájdený', 'rodinne-ulohy'), array('status' => 404));
        }

        if ($ctx['subject_type'] === 'child') {
            if (intval($purchase->child_id) !== intval($ctx['subject_id'])) {
                return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
            }
        } elseif ($ctx['subject_type'] === 'wp_user') {
            $owner_user_id = $this->resolve_owner_user_id($ctx);
            if (!$owner_user_id || intval($purchase->owner_user_id) !== intval($owner_user_id)) {
                return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
            }
        } else {
            return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
        }

        $ok = Rodinne_Ulohy_Database::mark_reward_used($purchase_id);
        if ($ok !== true) return new WP_Error('ru_failed', __('Chyba pri označení odmeny', 'rodinne-ulohy'), array('status' => 500));
        return array('ok' => true);
    }

    public function admin_regenerate_week($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);

        $rotation = Rodinne_Ulohy_Rotation::get_instance();
        $result = $rotation->regenerate_current_period($owner_user_id);
        if (!$result) return new WP_Error('ru_failed', __('Chyba pri regenerácii', 'rodinne-ulohy'), array('status' => 500));
        $conflicts = $rotation->get_last_rotation_conflicts();
        if (!empty($conflicts)) {
            $first = isset($conflicts[0]['message']) ? strval($conflicts[0]['message']) : __('Konflikt v rozdelení úloh.', 'rodinne-ulohy');
            return new WP_Error('ru_rotation_conflict', $first, array(
                'status' => 409,
                'conflicts' => $conflicts,
            ));
        }
        if ($owner_user_id) {
            delete_option('rodinne_ulohy_needs_regen_' . $owner_user_id);
        }
        return array('ok' => true);
    }

    public function admin_shift_rotation($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));

        $rotation = Rodinne_Ulohy_Rotation::get_instance();
        $result = $rotation->shift_rotation_current_period($owner_user_id);
        if (!$result) return new WP_Error('ru_failed', __('Chyba pri posune rotácie', 'rodinne-ulohy'), array('status' => 500));
        $conflicts = $rotation->get_last_rotation_conflicts();
        if (!empty($conflicts)) {
            $first = isset($conflicts[0]['message']) ? strval($conflicts[0]['message']) : __('Konflikt v rozdelení úloh.', 'rodinne-ulohy');
            return new WP_Error('ru_rotation_conflict', $first, array(
                'status' => 409,
                'conflicts' => $conflicts,
            ));
        }
        delete_option('rodinne_ulohy_needs_regen_' . $owner_user_id);
        return array('ok' => true);
    }

    public function admin_shift_single_task($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));

        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();
        $task_id = intval($body['task_id'] ?? 0);
        $to_child_id = intval($body['to_child_id'] ?? 0);
        if (!$task_id || !$to_child_id) {
            return new WP_Error('ru_invalid', __('Neplatné parametre posunu úlohy.', 'rodinne-ulohy'), array('status' => 400));
        }

        $result = Rodinne_Ulohy_Rotation::get_instance()->shift_single_task_current_period($task_id, $to_child_id, $owner_user_id);
        if (empty($result['ok'])) {
            return new WP_Error('ru_rotation_conflict', strval($result['message'] ?? __('Konflikt v rozdelení úloh.', 'rodinne-ulohy')), array('status' => 409));
        }
        return $result;
    }

    public function admin_get_rotation_settings($request) {
        $s = Rodinne_Ulohy_Rotation::get_rotation_settings();
        $next = wp_next_scheduled(Rodinne_Ulohy_Rotation::ROTATION_CRON_HOOK);
        return array(
            'frequency' => $s['frequency'],
            'day' => $s['day'],
            'nextRunTs' => $next ? intval($next) : 0,
        );
    }

    public function admin_save_rotation_settings($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) $body = array();
        $frequency = isset($body['frequency']) ? sanitize_text_field($body['frequency']) : 'weekly';
        $day = isset($body['day']) ? sanitize_text_field($body['day']) : 'monday';
        Rodinne_Ulohy_Rotation::save_rotation_settings($frequency, $day);
        return $this->admin_get_rotation_settings($request);
    }

    public function admin_get_weekend_multiplier($request) {
        return array('multiplier' => floatval(Rodinne_Ulohy_Database::get_weekend_penalty_multiplier()));
    }

    public function admin_weekend_multiplier($request) {
        $body = $request->get_json_params();
        $multiplier = isset($body['multiplier']) ? floatval($body['multiplier']) : 3;
        if ($multiplier < 1) return new WP_Error('ru_invalid', __('Multiplikátor musí byť aspoň 1', 'rodinne-ulohy'), array('status' => 400));
        $res = Rodinne_Ulohy_Database::save_weekend_penalty_multiplier($multiplier);
        if ($res === false) return new WP_Error('ru_failed', __('Chyba pri ukladaní multiplikátora', 'rodinne-ulohy'), array('status' => 500));
        return array('multiplier' => $multiplier);
    }

    public function admin_reset_children($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));

        $res = Rodinne_Ulohy_Database::reset_children_for_owner($owner_user_id);
        if ($res === false) return new WP_Error('ru_failed', __('Reset detí sa nepodaril', 'rodinne-ulohy'), array('status' => 500));
        return array('ok' => true, 'deleted' => $res);
    }

    public function admin_reset_tasks($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));

        $res = Rodinne_Ulohy_Database::reset_tasks_for_owner($owner_user_id);
        if ($res === false) return new WP_Error('ru_failed', __('Reset úloh sa nepodaril', 'rodinne-ulohy'), array('status' => 500));
        return array('ok' => true, 'deleted' => $res);
    }

    public function admin_reset_rewards($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $owner_user_id = $this->resolve_owner_user_id($ctx);
        if (!$owner_user_id) return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));

        $res = Rodinne_Ulohy_Database::reset_rewards_for_owner($owner_user_id);
        if ($res === false) return new WP_Error('ru_failed', __('Reset odmien sa nepodaril', 'rodinne-ulohy'), array('status' => 500));
        return array('ok' => true, 'deleted' => $res);
    }

    // -----------------------
    // Child endpoints
    // -----------------------
    private function resolve_overview_week_start($request) {
        $raw = sanitize_text_field($request->get_param('week_start') ?? '');
        if ($raw === '') {
            return Rodinne_Ulohy_Database::get_current_week_start();
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return Rodinne_Ulohy_Database::get_current_week_start();
        }
        try {
            $dt = new DateTime($raw, wp_timezone());
            if ((int) $dt->format('N') !== 1) {
                return Rodinne_Ulohy_Database::get_current_week_start();
            }
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return Rodinne_Ulohy_Database::get_current_week_start();
        }
    }

    public function child_overview($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $child_id = $this->resolve_child_id($request, $ctx);
        if (!$child_id) return new WP_Error('ru_invalid', __('Neplatné ID dieťaťa', 'rodinne-ulohy'), array('status' => 400));

        $requested_day = $request->get_param('day');
        $requested_day = is_null($requested_day) ? null : intval($requested_day);

        $child = Rodinne_Ulohy_Database::get_child($child_id);
        if (!$child) return new WP_Error('ru_not_found', __('Dieťa nebolo nájdené', 'rodinne-ulohy'), array('status' => 404));
        $owner_user_id = isset($child->owner_user_id) ? intval($child->owner_user_id) : 0;

        $current_week_start = Rodinne_Ulohy_Database::get_current_week_start();
        $week_start = $this->resolve_overview_week_start($request);
        $is_current_week = ($week_start === $current_week_start);
        $week_range = Rodinne_Ulohy_Database::get_week_range($week_start);
        $all_assignments = Rodinne_Ulohy_Database::get_child_assignments($child_id, $week_start);

        // Auto-regeneration when there's only one child:
        // If tasks/rotation conditions changed, parent UI normally asks to "regenerate week".
        // With a single child, redistribution is trivial, so do it automatically.
        $auto_regenerated = false;
        $needs_regen_flag = false;
        if (
            $is_current_week &&
            isset($ctx['subject_type']) &&
            $ctx['subject_type'] === 'wp_user' &&
            !empty($owner_user_id) &&
            intval($this->resolve_owner_user_id($ctx)) === intval($owner_user_id)
        ) {
            $needs_regen_flag = !empty(get_option('rodinne_ulohy_needs_regen_' . intval($owner_user_id), 0));
            if ($needs_regen_flag) {
                $children = Rodinne_Ulohy_Database::get_children('', $owner_user_id);
                if (is_array($children) && count($children) <= 1) {
                    $ok = Rodinne_Ulohy_Rotation::get_instance()->regenerate_current_week($owner_user_id);
                    if ($ok) {
                        delete_option('rodinne_ulohy_needs_regen_' . intval($owner_user_id));
                        $needs_regen_flag = false;
                        $auto_regenerated = true;
                        // Refresh assignments after regeneration so UI gets the updated list.
                        $all_assignments = Rodinne_Ulohy_Database::get_child_assignments($child_id, $week_start);
                    }
                }
            }
        }

        $points_balance = Rodinne_Ulohy_Database::get_points_balance($child_id);
        $today_points = Rodinne_Ulohy_Database::get_today_points_total($child_id);
        $week_points = Rodinne_Ulohy_Database::get_week_points_total($child_id, $week_start);

        $rewards = Rodinne_Ulohy_Database::get_rewards($owner_user_id);
        $purchases = Rodinne_Ulohy_Database::get_child_active_reward_purchases($child_id);
        $active_reward_counts = array();
        $active_purchases_payload = array();
        foreach ($purchases as $p) {
            $rid = intval($p->reward_id);
            if (!isset($active_reward_counts[$rid])) $active_reward_counts[$rid] = 0;
            $active_reward_counts[$rid]++;
            $active_purchases_payload[] = array(
                'id' => intval($p->id),
                'reward_id' => $rid,
                'title' => isset($p->reward_title) ? $p->reward_title : '',
                'icon' => isset($p->reward_icon) ? $p->reward_icon : '',
                'points_cost' => isset($p->reward_points_cost) ? intval($p->reward_points_cost) : 0,
                'created_at' => isset($p->created_at) ? $p->created_at : '',
            );
        }

        $current_day = date('w');
        $day_to_show = is_null($requested_day) ? $current_day : max(0, min(6, $requested_day));
        $today_date = current_time('Y-m-d');

        $povinne_assignments = array();
        $dobrovolne_assignments = array();

        foreach ($all_assignments as $assignment) {
            $task = Rodinne_Ulohy_Database::get_task($assignment->task_id, $owner_user_id);
            if (!$task) continue;

            $days_of_week = isset($task->days_of_week) && !empty($task->days_of_week) ? $task->days_of_week : '';
            $should_show = true;
            if (!empty($days_of_week)) {
                $task_days = array_map('intval', explode(',', $days_of_week));
                if (!in_array(intval($day_to_show), $task_days)) $should_show = false;
            } else {
                // No days_of_week configured => show every day (legacy behavior).
                $should_show = true;
            }

            if ($should_show) {
                $assignment->task_rating = isset($assignment->task_rating) && $assignment->task_rating !== null ? intval($assignment->task_rating) : 0;
                $assignment->task_category = isset($assignment->task_category) ? $assignment->task_category : 'povinne';
                // IMPORTANT: tasks with days_of_week behave as daily occurrences in the UI and points system.
                $assignment->task_type = !empty($days_of_week) ? 'daily' : (isset($task->task_type) ? $task->task_type : 'weekly');
                $assignment->rotation_enabled = isset($task->rotation_enabled) ? intval($task->rotation_enabled) : 0;
                $assignment->description = isset($task->description) ? $task->description : '';
                $assignment->days_of_week = $days_of_week;
                $assignment->task_icon = isset($task->icon) ? (string) $task->icon : '';

                // Self-heal: daily reset safety net
                // If WP cron didn't run, a task completed "yesterday" may remain completed today.
                // For today's view only, reset such tasks back to todo based on completed_at date.
                if ($is_current_week && intval($day_to_show) === intval($current_day) && $assignment->task_type === 'daily') {
                    $completed_at = isset($assignment->completed_at) ? strval($assignment->completed_at) : '';
                    if ($assignment->status === 'completed' && !empty($completed_at)) {
                        $completed_date = date('Y-m-d', strtotime($completed_at));
                        if ($completed_date !== $today_date) {
                            $assignment->status = 'todo';
                            $assignment->completed_at = null;
                            // Persist best-effort so subsequent calls and other clients are consistent.
                            try {
                                global $wpdb;
                                $wpdb->update(
                                    $wpdb->prefix . 'rodinne_ulohy_assignments',
                                    array('status' => 'todo', 'completed_at' => null),
                                    array('id' => intval($assignment->id)),
                                    array('%s', '%s'),
                                    array('%d')
                                );
                            } catch (Exception $e) {
                                // ignore DB write errors
                            }
                        }
                    }
                }

                // Self-heal: if assignment status got reset (e.g. after regeneration),
                // but points were already awarded for this task, mark it as completed again.
                // This prevents "double clicking" after plan changes and keeps UI consistent with points history.
                if ($is_current_week && intval($day_to_show) === intval($current_day) && $assignment->status !== 'completed') {
                    $effective_task_type = isset($assignment->task_type) ? $assignment->task_type : 'weekly';
                    $already = Rodinne_Ulohy_Database::points_already_added_for_task(
                        intval($assignment->child_id),
                        intval($assignment->task_id),
                        $effective_task_type,
                        $week_start
                    );
                    if ($already) {
                        $last = Rodinne_Ulohy_Database::get_last_points_for_task(
                            intval($assignment->child_id),
                            intval($assignment->task_id),
                            $effective_task_type,
                            $week_start
                        );
                        $completed_at = ($last && !empty($last->created_at)) ? $last->created_at : current_time('mysql');
                        $assignment->status = 'completed';
                        $assignment->completed_at = $completed_at;
                        // Persist best-effort (keeps daily reset logic consistent)
                        try {
                            global $wpdb;
                            $wpdb->update(
                                $wpdb->prefix . 'rodinne_ulohy_assignments',
                                array('status' => 'completed', 'completed_at' => $completed_at),
                                array('id' => intval($assignment->id)),
                                array('%s', '%s'),
                                array('%d')
                            );
                        } catch (Exception $e) {
                            // ignore DB write errors (UI still shows correct state)
                        }
                    }
                }

                // When browsing another day in the calendar, daily assignments in DB may already
                // be reset for today. Reconstruct that day's done-state from points history.
                if (intval($day_to_show) !== intval($current_day)) {
                    $target_ymd = Rodinne_Ulohy_Database::ymd_for_week_day($week_start, $day_to_show);
                    if (!empty($target_ymd) && $target_ymd <= $today_date) {
                        $entry = Rodinne_Ulohy_Database::get_last_task_points_entry_for_date(
                            intval($assignment->child_id),
                            intval($assignment->task_id),
                            $target_ymd
                        );
                        if ($entry && intval($entry->points) > 0) {
                            $assignment->status = 'completed';
                            $assignment->completed_at = $entry->created_at;
                        } else {
                            $assignment->status = 'todo';
                            $assignment->completed_at = null;
                        }
                    }
                }
                if ($assignment->task_category === 'dobrovolne') {
                    $dobrovolne_assignments[] = $assignment;
                } else {
                    $povinne_assignments[] = $assignment;
                }
            }
        }

        $povinne_completed = 0;
        foreach ($povinne_assignments as $a) if ($a->status === 'completed') $povinne_completed++;
        $dobrovolne_completed = 0;
        foreach ($dobrovolne_assignments as $a) if ($a->status === 'completed') $dobrovolne_completed++;

        return array(
            'child' => array(
                'id' => intval($child->id),
                'name' => $child->name,
                'avatar_url' => isset($child->avatar_url) ? $child->avatar_url : '',
                'color' => isset($child->color) ? $child->color : '',
            ),
            'has_pin' => !empty($child->password),
            'needs_regeneration' => (
                $is_current_week &&
                isset($ctx['subject_type']) &&
                $ctx['subject_type'] === 'wp_user' &&
                !empty($owner_user_id) &&
                intval($this->resolve_owner_user_id($ctx)) === intval($owner_user_id) &&
                !empty($needs_regen_flag)
            ),
            'week_start' => $week_start,
            'week_range' => $week_range,
            'day' => intval($day_to_show),
            'points_balance' => intval($points_balance->balance),
            'points_today' => intval($today_points),
            'points_week' => intval($week_points),
            'tasks' => array(
                'povinne' => array(
                    'items' => $povinne_assignments,
                    'completed' => $povinne_completed,
                    'total' => count($povinne_assignments),
                ),
                'dobrovolne' => array(
                    'items' => $dobrovolne_assignments,
                    'completed' => $dobrovolne_completed,
                    'total' => count($dobrovolne_assignments),
                ),
            ),
            'rewards' => array(
                'items' => $rewards,
                'active_counts' => $active_reward_counts,
                'active_purchases' => $active_purchases_payload,
            ),
            'weekendMultiplier' => floatval(Rodinne_Ulohy_Database::get_weekend_penalty_multiplier()),
        );
    }

    public function child_update_task_status($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $body = $request->get_json_params();
        $assignment_id = intval($body['assignment_id'] ?? 0);
        $status = sanitize_text_field($body['status'] ?? 'todo');
        if (!$assignment_id) return new WP_Error('ru_invalid', __('Neplatné ID', 'rodinne-ulohy'), array('status' => 400));
        if (!in_array($status, array('todo', 'completed'), true)) {
            return new WP_Error('ru_invalid', __('Neplatný stav', 'rodinne-ulohy'), array('status' => 400));
        }

        global $wpdb;
        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, t.rating, t.task_category, t.task_type, t.name as task_name
             FROM {$wpdb->prefix}rodinne_ulohy_assignments a
             INNER JOIN {$wpdb->prefix}rodinne_ulohy_tasks t ON a.task_id = t.id
             WHERE a.id = %d",
            $assignment_id
        ));
        if (!$assignment) return new WP_Error('ru_not_found', __('Priradenie nebolo nájdené', 'rodinne-ulohy'), array('status' => 404));

        // If child token: ensure ownership
        if (isset($ctx['subject_type']) && $ctx['subject_type'] === 'child') {
            if (intval($assignment->child_id) !== intval($ctx['subject_id'])) {
                return new WP_Error('ru_forbidden', __('Nemáte oprávnenie', 'rodinne-ulohy'), array('status' => 403));
            }
        }

        $old_status = $assignment->status;
        $result = Rodinne_Ulohy_Database::update_assignment_status($assignment_id, $status);
        if ($result === false) return new WP_Error('ru_failed', __('Chyba pri aktualizácii', 'rodinne-ulohy'), array('status' => 500));

        $points_added = 0;
        $points_message = '';
        if ($old_status !== $status) {
            $week_start = $assignment->week_start;
            $rating = isset($assignment->rating) && $assignment->rating !== null ? intval($assignment->rating) : 0;
            $task_category = isset($assignment->task_category) ? $assignment->task_category : 'povinne';
            $task_type = isset($assignment->task_type) ? $assignment->task_type : 'daily';

            if ($status === 'completed') {
                $already_added = Rodinne_Ulohy_Database::points_already_added($assignment_id, $task_type);
                if (!$already_added && $rating > 0) {
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
                    $points_message = __('Body už boli pripočítané', 'rodinne-ulohy');
                }
            } elseif ($old_status === 'completed' && $status !== 'completed') {
                $last_points = Rodinne_Ulohy_Database::get_last_points_for_assignment($assignment_id);
                if ($last_points && $last_points->points > 0) {
                    $can_reverse = false;
                    if ($task_type === 'daily') {
                        $today = current_time('Y-m-d');
                        if (date('Y-m-d', strtotime($last_points->created_at)) === $today) $can_reverse = true;
                    } else {
                        if ($last_points->week_start === $week_start) $can_reverse = true;
                    }
                    if ($can_reverse) {
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
                if ($rating > 0) $points_message = '-' . $rating;
            }
        }

        $all_assignments = Rodinne_Ulohy_Database::get_child_assignments($assignment->child_id, $assignment->week_start);
        $total = count($all_assignments);
        $completed = 0;
        foreach ($all_assignments as $a) if ($a->status === 'completed') $completed++;

        $points_balance = Rodinne_Ulohy_Database::get_points_balance($assignment->child_id);
        $points_today_total = Rodinne_Ulohy_Database::get_today_points_total($assignment->child_id);

        return array(
            'all_completed' => $completed === $total && $total > 0,
            'points_balance' => intval($points_balance->balance),
            'points_today' => intval($points_today_total),
            'points_added' => $points_added,
            'points_message' => $points_message,
        );
    }

    public function child_purchase_reward($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $body = $request->get_json_params();
        $reward_id = intval($body['reward_id'] ?? 0);
        if (!$reward_id) return new WP_Error('ru_invalid', __('Neplatné údaje', 'rodinne-ulohy'), array('status' => 400));

        $child_id = $this->resolve_child_id($request, $ctx);
        if (!$child_id) return new WP_Error('ru_invalid', __('Neplatné ID dieťaťa', 'rodinne-ulohy'), array('status' => 400));

        $result = Rodinne_Ulohy_Database::purchase_reward($child_id, $reward_id);
        if (is_wp_error($result)) {
            return new WP_Error('ru_failed', $result->get_error_message(), array('status' => 400));
        }

        $balance = Rodinne_Ulohy_Database::get_points_balance($child_id);
        $points_today = Rodinne_Ulohy_Database::get_today_points_total($child_id);
        $active = Rodinne_Ulohy_Database::get_child_active_reward_purchases($child_id);
        $counts = array();
        $active_purchases_payload = array();
        foreach ($active as $purchase) {
            $rid = intval($purchase->reward_id);
            if (!isset($counts[$rid])) $counts[$rid] = 0;
            $counts[$rid]++;
            $active_purchases_payload[] = array(
                'id' => intval($purchase->id),
                'reward_id' => $rid,
                'title' => isset($purchase->reward_title) ? $purchase->reward_title : '',
                'icon' => isset($purchase->reward_icon) ? $purchase->reward_icon : '',
                'points_cost' => isset($purchase->reward_points_cost) ? intval($purchase->reward_points_cost) : 0,
                'created_at' => isset($purchase->created_at) ? $purchase->created_at : '',
            );
        }

        return array(
            'points_balance' => intval($balance->balance),
            'points_today' => intval($points_today),
            'active_counts' => $counts,
            'active_purchases' => $active_purchases_payload,
            'purchase_id' => intval($result['purchase_id'] ?? 0),
        );
    }

    public function child_save_avatar($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $body = $request->get_json_params();
        $avatar_url = esc_url_raw($body['avatar_url'] ?? '');
        $child_id = $this->resolve_child_id($request, $ctx);
        if (!$child_id) return new WP_Error('ru_invalid', __('Neplatné ID dieťaťa', 'rodinne-ulohy'), array('status' => 400));

        $child = Rodinne_Ulohy_Database::get_child($child_id);
        if (!$child) return new WP_Error('ru_not_found', __('Dieťa nebolo nájdené', 'rodinne-ulohy'), array('status' => 404));

        $res = Rodinne_Ulohy_Database::save_child(array(
            'id' => $child_id,
            'name' => $child->name,
            'email' => $child->email ?? '',
            'avatar_url' => $avatar_url,
            'color' => $child->color ?? '#4CAF50',
        ));
        if ($res === false) return new WP_Error('ru_failed', __('Chyba pri ukladaní avatara', 'rodinne-ulohy'), array('status' => 500));
        return array('avatar_url' => $avatar_url);
    }

    public function child_upload_avatar($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        if (empty($_FILES['avatar'])) {
            return new WP_Error('ru_invalid', __('Chýba súbor', 'rodinne-ulohy'), array('status' => 400));
        }
        $file = $_FILES['avatar'];
        $allowed = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($file['type'], $allowed, true)) {
            return new WP_Error('ru_invalid', __('Nepodporovaný typ súboru', 'rodinne-ulohy'), array('status' => 400));
        }
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $upload = wp_handle_upload($file, array('test_form' => false));
        if (isset($upload['error'])) {
            return new WP_Error('ru_failed', $upload['error'], array('status' => 500));
        }
        return array('url' => esc_url_raw($upload['url']));
    }

    public function child_set_color($request) {
        $ctx = $this->authenticate($request);
        if (is_wp_error($ctx)) return $ctx;
        $body = $request->get_json_params();
        $color = sanitize_hex_color($body['color'] ?? '');
        $child_id = $this->resolve_child_id($request, $ctx);
        if (!$child_id || !$color) return new WP_Error('ru_invalid', __('Neplatné dáta', 'rodinne-ulohy'), array('status' => 400));

        $child = Rodinne_Ulohy_Database::get_child($child_id);
        if (!$child) return new WP_Error('ru_not_found', __('Dieťa nebolo nájdené', 'rodinne-ulohy'), array('status' => 404));

        $res = Rodinne_Ulohy_Database::save_child(array(
            'id' => $child_id,
            'name' => $child->name,
            'email' => $child->email ?? '',
            'password' => $child->password ?? '',
            'avatar_url' => $child->avatar_url ?? '',
            'color' => $color,
        ));
        if ($res === false) return new WP_Error('ru_failed', __('Chyba pri ukladaní farby', 'rodinne-ulohy'), array('status' => 500));
        return array('color' => $color);
    }
}

