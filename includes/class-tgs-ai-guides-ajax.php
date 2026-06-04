<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TGS_AI_Guides_Ajax
{
    const NONCE_ACTION = 'tgs_ai_guides_nonce';

    public static function init()
    {
        add_action('wp_ajax_tgs_ai_guides_mark_seen', array(__CLASS__, 'mark_seen'));
        add_action('wp_ajax_tgs_ai_guides_reset_seen', array(__CLASS__, 'reset_seen'));
        add_action('wp_ajax_tgs_ai_guides_chat', array(__CLASS__, 'chat'));
    }

    public static function has_seen($view, $version, $page = 'tgs-shop-management')
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }

        return (bool) get_user_meta($user_id, self::seen_key($view, $version, $page), true);
    }

    public static function mark_seen()
    {
        self::verify_request();

        $view = isset($_POST['view']) ? sanitize_key(wp_unslash($_POST['view'])) : 'dashboard';
        $page = isset($_POST['page']) ? sanitize_key(wp_unslash($_POST['page'])) : 'tgs-shop-management';
        $version = isset($_POST['version']) ? sanitize_key(wp_unslash($_POST['version'])) : TGS_AI_Guides_Registry::VERSION;
        $source = isset($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : 'unknown';

        update_user_meta(
            get_current_user_id(),
            self::seen_key($view, $version, $page),
            array(
                'seen_at' => current_time('mysql'),
                'source' => $source,
                'blog_id' => get_current_blog_id(),
                'page' => $page,
            )
        );

        wp_send_json_success(array('message' => 'marked'));
    }

    public static function reset_seen()
    {
        self::verify_request();

        $scope = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : 'current';
        $view = isset($_POST['view']) ? sanitize_key(wp_unslash($_POST['view'])) : 'dashboard';
        $page = isset($_POST['page']) ? sanitize_key(wp_unslash($_POST['page'])) : 'tgs-shop-management';
        $version = isset($_POST['version']) ? sanitize_key(wp_unslash($_POST['version'])) : TGS_AI_Guides_Registry::VERSION;
        $user_id = get_current_user_id();

        if ($scope === 'site') {
            $all_meta = get_user_meta($user_id);
            $prefix = 'tgs_ai_guides_seen_' . get_current_blog_id() . '_';
            foreach (array_keys($all_meta) as $key) {
                if (strpos($key, $prefix) === 0) {
                    delete_user_meta($user_id, $key);
                }
            }
        } else {
            delete_user_meta($user_id, self::seen_key($view, $version, $page));
        }

        wp_send_json_success(array('message' => 'reset'));
    }

    public static function chat()
    {
        self::verify_request();

        $view = isset($_POST['view']) ? sanitize_key(wp_unslash($_POST['view'])) : 'dashboard';
        $page = isset($_POST['page']) ? sanitize_key(wp_unslash($_POST['page'])) : 'tgs-shop-management';
        $question = isset($_POST['question']) ? sanitize_text_field(wp_unslash($_POST['question'])) : '';

        if ($question === '') {
            wp_send_json_error(array('message' => 'Câu hỏi đang trống.'), 400);
        }

        $tour = TGS_AI_Guides_Registry::get_tour($view, $page);

        $external_answer = apply_filters('tgs_ai_guides_ai_answer', null, $question, $view, $tour, $page);
        if (is_array($external_answer)) {
            wp_send_json_success($external_answer);
        }

        wp_send_json_success(TGS_AI_Guides_Registry::answer_question($view, $question, $page));
    }

    public static function seen_key($view, $version, $page = 'tgs-shop-management')
    {
        return 'tgs_ai_guides_seen_' . get_current_blog_id() . '_' . sanitize_key($page) . '_' . sanitize_key($view) . '_' . sanitize_key($version);
    }

    private static function verify_request()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!is_user_logged_in() || !current_user_can('exist')) {
            wp_send_json_error(array('message' => 'Bạn chưa có quyền sử dụng hướng dẫn.'), 403);
        }
    }
}
