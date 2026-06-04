<?php
/**
 * Plugin Name: TGS AI Guides
 * Plugin URI:  https://thegioisua.vn
 * Description: Huong dan su dung theo tung man hinh cho TGS Shop Management, dung driver.js va tro ly hoi dap theo ngu canh trang.
 * Version:     0.1.0
 * Author:      TGS Development Team
 * Text Domain: tgs-ai-guides
 * Network:     true
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TGS_AI_GUIDES_VERSION', '0.1.0');
define('TGS_AI_GUIDES_DIR', plugin_dir_path(__FILE__));
define('TGS_AI_GUIDES_URL', plugin_dir_url(__FILE__));
define('TGS_AI_GUIDES_BASENAME', plugin_basename(__FILE__));

require_once TGS_AI_GUIDES_DIR . 'includes/class-tgs-ai-guides-registry.php';
require_once TGS_AI_GUIDES_DIR . 'includes/class-tgs-ai-guides-ajax.php';
require_once TGS_AI_GUIDES_DIR . 'includes/class-tgs-ai-guides.php';

add_action('plugins_loaded', array('TGS_AI_Guides', 'init'));
