<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TGS_AI_Guides
{
    const ROUTE_VIEW = 'ai-guides';

    public static function init()
    {
        if (!is_admin()) {
            return;
        }

        TGS_AI_Guides_Ajax::init();

        add_filter('tgs_shop_dashboard_routes', array(__CLASS__, 'register_tgs_route'));
        add_action('tgs_shop_ai_menu', array(__CLASS__, 'render_tgs_ai_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

    public static function register_tgs_route($routes)
    {
        $routes[self::ROUTE_VIEW] = array(
            'AI hướng dẫn sử dụng',
            TGS_AI_GUIDES_DIR . 'admin/views/settings.php',
        );

        return $routes;
    }

    public static function render_tgs_ai_menu($current_view)
    {
        $active = ($current_view === self::ROUTE_VIEW) ? 'active' : '';
        $url = admin_url('admin.php?page=tgs-shop-management&view=' . self::ROUTE_VIEW);

        echo '<li><a href="' . esc_url($url) . '" class="' . esc_attr($active) . '"><i class="bx bx-bot"></i>AI hướng dẫn sử dụng</a></li>';
    }

    public static function enqueue_assets($hook)
    {
        if (!self::is_tgs_shop_page()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'tgs-shop-management';
        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'dashboard';
        if ($view === '') {
            $view = 'dashboard';
        }

        $tour = TGS_AI_Guides_Registry::get_tour($view, $page);
        $has_seen = TGS_AI_Guides_Ajax::has_seen($view, $tour['version'], $page);

        wp_enqueue_style(
            'tgs-ai-guides-driver',
            TGS_AI_GUIDES_URL . 'assets/vendor/driver.js/driver.css',
            array(),
            '1.4.0'
        );

        wp_enqueue_style(
            'tgs-ai-guides',
            TGS_AI_GUIDES_URL . 'assets/css/tgs-ai-guides.css',
            array('tgs-ai-guides-driver'),
            self::asset_version('assets/css/tgs-ai-guides.css')
        );

        wp_enqueue_script(
            'tgs-ai-guides-driver',
            TGS_AI_GUIDES_URL . 'assets/vendor/driver.js/driver.js.iife.js',
            array(),
            '1.4.0',
            true
        );

        wp_enqueue_script(
            'tgs-ai-guides',
            TGS_AI_GUIDES_URL . 'assets/js/tgs-ai-guides.js',
            array('tgs-ai-guides-driver'),
            self::asset_version('assets/js/tgs-ai-guides.js'),
            true
        );

        $config = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(TGS_AI_Guides_Ajax::NONCE_ACTION),
            'view' => $view,
            'page' => $page,
            'siteId' => get_current_blog_id(),
            'userId' => get_current_user_id(),
            'autoStart' => !$has_seen && !empty($tour['steps']),
            'globalStepCooldownMinutes' => (int) apply_filters('tgs_ai_guides_global_step_cooldown_minutes', 180, $view, $page),
            'tour' => $tour,
            'labels' => array(
                'launcher' => 'AI hỗ trợ',
                'panelTitle' => 'AI hỗ trợ hướng dẫn',
                'panelSubtitle' => 'Trả lời theo màn hình hiện tại',
                'scopePage' => 'Trang này',
                'scopeProject' => 'Toàn dự án',
                'replayTour' => 'Hướng dẫn lại',
                'skipPage' => 'Bỏ qua trang này',
                'askPlaceholder' => 'Hỏi nhanh về trang này...',
                'send' => 'Gửi',
                'typing' => 'Đang kiểm tra hướng dẫn...',
                'tourUnavailable' => 'Trang này chưa có đủ thành phần để chạy tour. Bạn vẫn có thể hỏi nhanh ở khung hỗ trợ.',
                'emptyAnswer' => 'Mình chưa trả lời được câu này trong bộ hướng dẫn hiện tại.',
                'requestFailed' => 'Không gọi được khung hỗ trợ lúc này. Vui lòng thử lại sau.',
                'sessionExpired' => 'Phiên đăng nhập có thể đã hết hạn. Vui lòng tải lại trang hoặc đăng nhập lại.',
                'driverMissing' => 'Không tìm thấy driver.js trên trang. Kiểm tra lại asset của plugin TGS AI Guides.',
                'skipConfirmed' => 'Đã ghi nhận bỏ qua hướng dẫn cho trang này. Bạn vẫn có thể bấm "Hướng dẫn lại" bất cứ lúc nào.',
                'resetConfirmed' => 'Đã reset lịch sử hướng dẫn đã xem cho tài khoản hiện tại trên website này.',
                'skipTour' => 'Bỏ qua',
                'closeTour' => 'Bỏ qua hướng dẫn',
                'nextStep' => 'Tiếp',
                'prevStep' => 'Quay lại',
                'doneStep' => 'Hoàn tất',
            ),
        );

        wp_add_inline_script(
            'tgs-ai-guides',
            'window.TGSAIGuidesConfig = ' . wp_json_encode($config) . ';',
            'before'
        );
    }

    public static function is_tgs_shop_page()
    {
        if (!is_admin() || is_network_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        $supported_pages = apply_filters(
            'tgs_ai_guides_supported_pages',
            array('tgs-shop-management', 'tgs-permission', 'tgs-permission-roles', 'bizgpt-tmd-pos')
        );

        return in_array($page, array_map('sanitize_key', $supported_pages), true);
    }

    private static function asset_version($relative_path)
    {
        $path = TGS_AI_GUIDES_DIR . ltrim($relative_path, '/');

        return file_exists($path) ? (string) filemtime($path) : TGS_AI_GUIDES_VERSION;
    }
}
