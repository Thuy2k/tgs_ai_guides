<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TGS_AI_Guides_Registry
{
    const VERSION = '2026-06-04-04';

    public static function get_tour($view, $page = 'tgs-shop-management')
    {
        $view = sanitize_key($view ?: 'dashboard');
        $page = sanitize_key($page ?: 'tgs-shop-management');
        $definitions = self::definitions();
        $guide_key = self::resolve_guide_key($view, $page);
        $guide = isset($definitions[$guide_key]) ? $definitions[$guide_key] : $definitions['generic'];
        $base = $definitions['base'];

        $quick_questions = array_values(array_unique(array_merge(
            $guide['quick_questions'],
            array('Hướng dẫn lại trang này', 'Trang này dùng để làm gì?')
        )));

        $context = wp_parse_args(
            $guide['context'],
            array(
                'page' => $page,
                'view' => $view,
                'guideKey' => $guide_key,
                'source' => 'TGS Shop Management operating guide',
                'aiReady' => true,
                'aiInstruction' => 'Trả lời bằng tiếng Việt, bám sát view hiện tại, chỉ hướng dẫn nghiệp vụ và thao tác trong trang này.',
            )
        );

        $global_steps = array_values($base['steps']);
        $page_steps = array_values($guide['steps']);

        $tour = array(
            'id' => 'tgs-' . $page . '-' . $view,
            'version' => self::VERSION,
            'page' => $page,
            'view' => $view,
            'group' => $guide_key,
            'guideKey' => $guide_key,
            'title' => $guide['title'],
            'summary' => $guide['summary'],
            'quickQuestions' => $quick_questions,
            'globalSteps' => $global_steps,
            'pageSteps' => $page_steps,
            'steps' => array_values(array_merge($page_steps, $global_steps)),
            'knowledge' => array_values(array_merge($base['knowledge'], $guide['knowledge'])),
            'context' => $context,
        );

        return apply_filters('tgs_ai_guides_tour', $tour, $view, $guide_key, $page);
    }

    public static function answer_question($view, $question, $page = 'tgs-shop-management', $scope = 'page')
    {
        $scope = sanitize_key($scope ?: 'page');
        if ($scope === 'project') {
            return self::answer_project_question($question, $view, $page);
        }

        $tour = self::get_tour($view, $page);
        $normalized_question = self::normalize($question);
        $best = null;
        $best_score = 0;

        foreach ($tour['knowledge'] as $entry) {
            $score = 0;
            foreach ($entry['terms'] as $term) {
                $needle = self::normalize($term);
                if ($needle !== '' && strpos($normalized_question, $needle) !== false) {
                    $score += strlen($needle) > 8 ? 3 : 1;
                }
            }

            if ($score > $best_score) {
                $best_score = $score;
                $best = $entry;
            }
        }

        if ($best) {
            return array(
                'answer' => $best['answer'],
                'matched' => true,
                'quickQuestions' => $tour['quickQuestions'],
                'context' => $tour['context'],
            );
        }

        return array(
            'answer' => 'Mình đang trả lời theo ngữ cảnh riêng của màn hình "' . $tour['title'] . '". ' . $tour['summary'] . ' Bạn có thể hỏi cụ thể hơn như: "' . implode('", "', array_slice($tour['quickQuestions'], 0, 3)) . '".',
            'matched' => false,
            'quickQuestions' => $tour['quickQuestions'],
            'context' => $tour['context'],
        );
    }

    private static function answer_project_question($question, $view, $page)
    {
        $definitions = self::definitions();
        $normalized_question = self::normalize($question);
        $best = null;
        $best_score = 0;

        foreach ($definitions as $guide_key => $guide) {
            $entries = isset($guide['knowledge']) ? $guide['knowledge'] : array();
            foreach ($entries as $entry) {
                $score = 0;
                foreach ($entry['terms'] as $term) {
                    $needle = self::normalize($term);
                    if ($needle !== '' && strpos($normalized_question, $needle) !== false) {
                        $score += strlen($needle) > 8 ? 3 : 1;
                    }
                }

                if ($score > $best_score) {
                    $best_score = $score;
                    $best = array(
                        'guideKey' => $guide_key,
                        'title' => isset($guide['title']) ? $guide['title'] : 'Hướng dẫn chung',
                        'answer' => $entry['answer'],
                    );
                }
            }
        }

        $quick_questions = self::project_quick_questions();

        if ($best) {
            return array(
                'answer' => 'Theo phần "' . $best['title'] . '": ' . $best['answer'],
                'matched' => true,
                'scope' => 'project',
                'guideKey' => $best['guideKey'],
                'quickQuestions' => $quick_questions,
                'context' => self::project_context($view, $page),
            );
        }

        return array(
            'answer' => 'Mình đang tìm trong toàn bộ bộ hướng dẫn dự án TGS: sản phẩm, đối tác, phiếu giao dịch, kho, quét tồn/PO, mua hàng, luân chuyển nội bộ, báo cáo và cấu hình hệ thống. Bạn có thể hỏi cụ thể hơn như: "' . implode('", "', array_slice($quick_questions, 0, 3)) . '".',
            'matched' => false,
            'scope' => 'project',
            'quickQuestions' => $quick_questions,
            'context' => self::project_context($view, $page),
        );
    }

    private static function project_quick_questions()
    {
        return array(
            'Quét tồn thông minh dùng thế nào?',
            'PO theo gợi ý và PO chủ động khác gì?',
            'Cấu hình min/max tồn kho ở đâu?',
            'Lô mua, hợp đồng và chính sách mua liên quan thế nào?',
            'Luân chuyển nội bộ gồm những bước nào?',
        );
    }

    private static function project_context($view, $page)
    {
        return array(
            'page' => sanitize_key($page ?: 'tgs-shop-management'),
            'view' => sanitize_key($view ?: 'dashboard'),
            'scope' => 'project',
            'source' => 'TGS Shop Management full operating guide',
            'aiInstruction' => 'Trả lời bằng tiếng Việt. Khi scope là project, được tổng hợp toàn bộ ngữ cảnh vận hành trong TGS Shop, Purchase, Transfer và Reporting; khi cần thao tác cụ thể thì chỉ rõ màn hình hoặc view liên quan.',
            'availableDomains' => array(
                'Sản phẩm và danh mục',
                'Đối tác, khách hàng, nhà cung cấp',
                'Phiếu giao dịch và kho',
                'Quét tồn thông minh và PO',
                'Mua hàng, min/max, lô mua, hợp đồng, chính sách',
                'Luân chuyển hàng nội bộ',
                'Báo cáo và cấu hình hệ thống',
            ),
        );
    }

    public static function all_groups()
    {
        $definitions = self::definitions();
        unset($definitions['base']);

        return $definitions;
    }

    public static function view_coverage()
    {
        return self::view_map();
    }

    private static function resolve_guide_key($view, $page = 'tgs-shop-management')
    {
        $view = sanitize_key($view ?: 'dashboard');
        $page = sanitize_key($page ?: 'tgs-shop-management');
        $page_map = array(
            'tgs-permission' => 'permission_settings',
            'tgs-permission-roles' => 'permission_roles',
            'bizgpt-tmd-pos' => 'pos_screen',
        );

        if ($page !== 'tgs-shop-management' && isset($page_map[$page])) {
            return apply_filters('tgs_ai_guides_group_for_view', $page_map[$page], $view, $page);
        }

        $map = self::view_map();
        if (isset($map[$view])) {
            return apply_filters('tgs_ai_guides_group_for_view', $map[$view], $view, $page);
        }

        if (strpos($view, 'ticket-') === 0) {
            if (strpos($view, '-detail') !== false) {
                return apply_filters('tgs_ai_guides_group_for_view', 'ticket_detail', $view, $page);
            }
            if (strpos($view, '-edit') !== false) {
                return apply_filters('tgs_ai_guides_group_for_view', 'ticket_edit', $view, $page);
            }
            return apply_filters('tgs_ai_guides_group_for_view', 'ticket_list', $view, $page);
        }

        if (strpos($view, 'report-') === 0) {
            return apply_filters('tgs_ai_guides_group_for_view', 'report_detail', $view, $page);
        }

        if (strpos($view, 'selling-policy') === 0 || strpos($view, 'selling-') === 0) {
            return apply_filters('tgs_ai_guides_group_for_view', 'selling_policy', $view, $page);
        }

        if (strpos($view, 'loyalty-') === 0) {
            return apply_filters('tgs_ai_guides_group_for_view', 'loyalty_policy', $view, $page);
        }

        if (strpos($view, 'viettel-invoice') === 0) {
            return apply_filters('tgs_ai_guides_group_for_view', 'viettel_invoice', $view, $page);
        }

        if (strpos($view, 'purchase-po') === 0 || strpos($view, 'po-') === 0 || strpos($view, 'stock-scan') === 0 || strpos($view, 'smart-stock') === 0) {
            return apply_filters('tgs_ai_guides_group_for_view', 'purchase_po', $view, $page);
        }

        if (strpos($view, 'purchase-') === 0) {
            return apply_filters('tgs_ai_guides_group_for_view', 'purchase_dashboard', $view, $page);
        }

        if (strpos($view, 'transfer-') === 0 || strpos($view, 'ticket-transfer-') === 0 || strpos($view, 'ticket-internal-') === 0) {
            return apply_filters('tgs_ai_guides_group_for_view', 'transfer_internal', $view, $page);
        }

        if (strpos($view, 'product') === 0) {
            return apply_filters('tgs_ai_guides_group_for_view', 'product_list', $view, $page);
        }

        return apply_filters('tgs_ai_guides_group_for_view', 'generic', $view, $page);
    }

    private static function view_map()
    {
        return array(
            'dashboard' => 'entry_dashboard',
            'dashboard-info' => 'entry_dashboard',
            'dashboard-global' => 'dashboard_global',

            'products-v2' => 'product_list',
            'product-add' => 'product_form',
            'product-edit' => 'product_form',
            'products-excel-import' => 'product_excel_import',
            'products-category-import' => 'product_excel_import',
            'products-price-import' => 'product_excel_import',
            'products-price-merge' => 'product_bulk_tools',
            'products-smart-price-update' => 'product_bulk_tools',
            'products-smart-thumbnail-update' => 'product_bulk_tools',
            'products-smart-unit-update' => 'product_bulk_tools',
            'products-smart-barcode-update' => 'product_bulk_tools',
            'products-quantity-import' => 'product_excel_import',
            'products-tracking-enable' => 'product_hsd_bulk',
            'products-tracking-disable' => 'product_hsd_bulk',
            'milk-under24m' => 'milk_under24m',
            'milk-under24m-import' => 'milk_under24m_import',

            'categories-v2' => 'category_list',
            'category-add' => 'category_form',
            'category-edit' => 'category_form',
            'categories-excel-import' => 'category_import',

            'contacts' => 'contact_list',
            'customers-excel-import' => 'customer_import',
            'contact-detail' => 'contact_detail',
            'suppliers' => 'supplier_list',
            'shops' => 'shop_list',
            'suppliers-global' => 'supplier_list',
            'supplier-global-detail' => 'supplier_detail',
            'supplier-migration' => 'supplier_migration',
            'suppliers-excel-import' => 'supplier_import',
            'org-chart-view' => 'partner_list',

            'ticket-relationships' => 'ticket_relationships',
            'purchases' => 'ticket_list',
            'sales' => 'ticket_list',
            'ticket-purchases' => 'ticket_list',
            'ticket-sales' => 'ticket_list',
            'ticket-returns' => 'ticket_list',
            'ticket-supplier-returns' => 'ticket_list',
            'ticket-damages' => 'ticket_list',
            'ticket-adjustments' => 'ticket_list',
            'ticket-receipts-v2' => 'ticket_list',
            'ticket-payments-v2' => 'ticket_list',
            'ticket-imports-v2' => 'ticket_list',
            'ticket-exports-v2' => 'ticket_list',
            'purchase-add' => 'ticket_create',
            'sale-add' => 'ticket_create',
            'return-add' => 'ticket_create',
            'supplier-return-add' => 'ticket_create',
            'damage-add' => 'ticket_create',
            'ticket-adjustment-add' => 'ticket_create',
            'receipt-v2-add' => 'transaction_create',
            'payment-v2-add' => 'transaction_create',
            'purchase-detail' => 'ticket_detail',
            'sale-detail' => 'ticket_detail',
            'adjustment-detail' => 'ticket_detail',
            'ticket-purchase-detail' => 'ticket_detail',
            'ticket-sale-detail' => 'ticket_detail',
            'ticket-return-detail' => 'ticket_detail',
            'ticket-supplier-return-detail' => 'ticket_detail',
            'ticket-damage-detail' => 'ticket_detail',
            'ticket-adjustment-detail' => 'ticket_detail',
            'ticket-receipt-v2-detail' => 'ticket_detail',
            'ticket-payment-v2-detail' => 'ticket_detail',
            'ticket-import-v2-detail' => 'ticket_detail',
            'ticket-export-v2-detail' => 'ticket_detail',
            'ticket-purchase-edit' => 'ticket_edit',
            'ticket-sale-edit' => 'ticket_edit',
            'ticket-transfer-export-edit' => 'ticket_edit',

            'inventory' => 'inventory_report',
            'inventory-print' => 'inventory_print',
            'inventory-manual-v2' => 'inventory_manual',
            'analytics-shop-inventory' => 'inventory_report',
            'warehouse-zone' => 'warehouse_zone',
            'lot-tracking' => 'lot_tracking',
            'identifier-generate-detail' => 'identifier_generate',
            'lots' => 'lot_tracking',
            'import' => 'legacy_import',
            'ledger' => 'ledger_view',

            'reports' => 'reports_overview',
            'report-dashboard' => 'report_dashboard',
            'storekeeper-stock-report' => 'storekeeper_report',
            'analytics-report-overview' => 'report_dashboard',
            'analytics-report-sales' => 'report_detail',
            'analytics-report-product-sales' => 'report_detail',
            'analytics-report-inventory' => 'inventory_report',
            'analytics-report-transfer' => 'transfer_report',
            'analytics-report-purchase-suggestion' => 'purchase_po',
            'analytics-report-customer' => 'report_detail',
            'analytics-report-sell-speed' => 'purchase_sell_speed_config',
            'analytics-report-stockout-forecast' => 'purchase_po',

            'admin-settings' => 'settings',
            'label-print-settings' => 'label_print_settings',
            'brand-settings' => 'brand_settings',
            'sync-categories' => 'sync_data',
            'sync-products' => 'sync_data',
            'api' => 'api_list',
            'api-detail' => 'api_detail',
            'tools-merge-guest-persons' => 'merge_guest_persons',
            'ai-guides' => 'guide_settings',

            'purchase-dashboard' => 'purchase_dashboard',
            'purchase-alerts' => 'purchase_alerts',
            'purchase-stock-config' => 'purchase_stock_config',
            'purchase-sell-speed-config' => 'purchase_sell_speed_config',
            'purchase-contracts' => 'purchase_contract',
            'purchase-contract-add' => 'purchase_contract_form',
            'purchase-contract-detail' => 'purchase_contract',
            'purchase-lots' => 'purchase_lot',
            'purchase-lot-add' => 'purchase_lot_form',
            'purchase-lot-detail' => 'purchase_lot',
            'purchase-policies' => 'purchase_policy',
            'purchase-policy-add' => 'purchase_policy_form',
            'purchase-policy-detail' => 'purchase_policy',
            'purchase-batches' => 'purchase_batch',
            'purchase-batch-detail' => 'purchase_batch',
            'purchase-payments' => 'purchase_payment',
            'purchase-payment-detail' => 'purchase_payment',
            'purchase-po-list' => 'purchase_po',
            'purchase-po-add' => 'purchase_po',
            'purchase-po-detail' => 'purchase_po',
            'purchase-stock-scan' => 'purchase_po',
            'purchase-smart-stock-scan' => 'purchase_po',

            'transfer-report' => 'transfer_report',
            'transfer-export-add' => 'transfer_export',
            'ticket-transfer-exports' => 'transfer_export_list',
            'ticket-transfer-export-detail' => 'transfer_detail',
            'transfer-pending-imports' => 'transfer_pending_import',
            'transfer-import-add' => 'transfer_import',
            'ticket-transfer-imports' => 'transfer_import_list',
            'ticket-transfer-import-detail' => 'transfer_detail',
            'transfer-return-add' => 'transfer_return',
            'ticket-internal-returns' => 'transfer_return_list',
            'ticket-internal-return-detail' => 'transfer_detail',
            'transfer-pending-returns' => 'transfer_pending_return',
            'transfer-return-receive-add' => 'transfer_return_receive',
            'ticket-internal-return-receives' => 'transfer_return_receive_list',
            'ticket-internal-return-receive-detail' => 'transfer_detail',

            'selling-dashboard' => 'selling_dashboard',
            'selling-policies' => 'selling_policy',
            'selling-policy-add' => 'selling_policy_form',
            'selling-policy-detail' => 'selling_policy_detail',
            'selling-policy-group-detail' => 'selling_policy_group',
            'selling-policy-report' => 'selling_policy_report',

            'loyalty-dashboard' => 'loyalty_policy',
            'loyalty-policies' => 'loyalty_policy',
            'loyalty-policy-add' => 'loyalty_policy',
            'loyalty-policy-detail' => 'loyalty_policy',
            'loyalty-members' => 'loyalty_policy',
            'loyalty-settings' => 'loyalty_policy',

            'zalo-oa' => 'zalo_oa',
            'zalo-log' => 'zalo_oa',
            'viettel-invoice-create' => 'viettel_invoice',
            'viettel-invoice-settings' => 'viettel_invoice_settings',
            'viettel-invoice-guide' => 'viettel_invoice',
        );
    }

    private static function definitions()
    {
        $generic_steps = array(
            self::step('h4, .card-title, .wrap h1', 'Tiêu đề nghiệp vụ', 'Đọc tiêu đề để xác nhận đúng màn hình trước khi nhập liệu, lọc báo cáo hoặc xử lý chứng từ.', 'bottom', 'start'),
            self::step('.card, table, form, .wrap', 'Khối thao tác chính', 'Các bảng, form, card hoặc vùng báo cáo trên trang là nơi nhập liệu, kiểm tra và xử lý nghiệp vụ của view hiện tại.', 'top', 'center'),
        );

        $generic_knowledge = array(
            self::knowledge(array('bat dau', 'thao tac', 'dung de lam gi', 'trang nay'), 'Hãy xác nhận tiêu đề trang, xem nhóm nút ở đầu trang, sau đó thao tác trong bảng hoặc form chính. Nếu chưa chắc vị trí thao tác, bấm "Hướng dẫn lại trang này".'),
        );

        return array(
            'base' => array(
                'steps' => array(
                    self::step('#tgs-mega-nav, .layout-navbar, .menu, #adminmenu', 'Thanh điều hướng chính', 'Dùng để chuyển giữa Dashboard, Sản phẩm, Đối tác, Giao dịch, Kho hàng, Báo cáo, Công cụ và Hệ thống.', 'bottom', 'center', array('scope' => 'global', 'cadence' => 'cooldown')),
                    self::step('#globalSearchWrapper, #globalSearchInput, .tgs-global-search', 'Tìm kiếm toàn hệ thống', 'Tra nhanh barcode, sản phẩm hoặc phiếu. Khi đang thao tác bằng bàn phím, có thể dùng Ctrl+K nếu trang hỗ trợ.', 'bottom', 'center', array('scope' => 'global', 'cadence' => 'cooldown')),
                    self::step('.tgs-nav-items, .menu-inner, #adminmenu', 'Nhóm menu nghiệp vụ', 'Menu được chia theo luồng vận hành: hàng hóa, đối tác, chứng từ, kho, báo cáo, hệ thống và các plugin mở rộng.', 'bottom', 'center', array('scope' => 'global', 'cadence' => 'cooldown')),
                    self::step('.container-xxl.flex-grow-1.container-p-y, .wrap, #wpbody-content', 'Vùng làm việc hiện tại', 'Toàn bộ bảng dữ liệu, form nhập liệu, báo cáo và cấu hình của view đang mở nằm trong khu vực này.', 'top', 'center', array('scope' => 'global', 'cadence' => 'cooldown')),
                    self::step('.tgs-ai-guide-launcher', 'AI hỗ trợ theo trang', 'Bấm nút này để chạy lại tour driver.js hoặc hỏi nhanh. Câu trả lời bám theo đúng view hiện tại, không dùng chung ngữ cảnh với trang khác.', 'left', 'end', array('scope' => 'global', 'cadence' => 'cooldown')),
                ),
                'knowledge' => array(
                    self::knowledge(array('tim kiem', 'search', 'ctrl k', 'barcode'), 'Dùng thanh tìm kiếm trên đầu trang để tra barcode, sản phẩm hoặc phiếu. Nếu trang hỗ trợ phím tắt, bấm Ctrl+K rồi nhập từ khóa.'),
                    self::knowledge(array('huong dan lai', 'xem lai', 'driver', 'tour'), 'Bấm nút "AI hỗ trợ" ở góc dưới bên phải, sau đó chọn "Hướng dẫn lại" để chạy lại tour driver.js của đúng màn hình hiện tại.'),
                    self::knowledge(array('bo qua', 'khong hien nua', 'an huong dan'), 'Bấm "Bỏ qua trang này" hoặc đóng tour. Trạng thái đã xem được lưu theo tài khoản, site hiện tại và view hiện tại.'),
                    self::knowledge(array('ai that', 'ket noi ai', 'chatgpt', 'mo rong'), 'Khung hiện tại trả lời theo bộ hướng dẫn nội bộ của từng view. Plugin đã để sẵn filter `tgs_ai_guides_ai_answer`; sau này có thể nối AI thật và truyền toàn bộ `tour.context` để trả lời theo trang.'),
                ),
            ),

            'entry_dashboard' => self::guide(
                'Trang chọn khu vực làm việc',
                'Màn hình đầu vào giúp nhân viên chọn POS bán hàng hoặc khu vực quản trị kho.',
                array('Khi nào vào POS?', 'Khi nào vào quản trị kho?', 'Tôi muốn xem dashboard kho'),
                array(
                    self::step('.tgs-entry-hero, .tgs-entry-card-pos, .tgs-entry-card-admin', 'Chọn đúng khu vực làm việc', 'POS dành cho bán hàng tại quầy; quản trị kho dành cho hàng hóa, chứng từ, tồn kho và báo cáo.', 'bottom', 'center'),
                    self::step('.tgs-entry-card-pos, a[href*="pos"], a[href*="bizgpt-tmd-pos"]', 'POS bán hàng', 'Đi vào luồng tạo đơn nhanh, thanh toán và in hóa đơn tại quầy.', 'right', 'center'),
                    self::step('.tgs-entry-card-admin, a[href*="dashboard-global"]', 'Quản trị kho', 'Dành cho kho, kế toán mua, quản lý và admin khi cần xử lý sản phẩm, phiếu, tồn và cấu hình.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('pos', 'ban hang', 'tai quay'), 'Vào POS khi đang bán hàng trực tiếp tại quầy, cần tạo đơn nhanh, thanh toán, in phiếu hoặc gửi thông tin đơn cho khách.'),
                    self::knowledge(array('quan tri', 'kho', 'dashboard'), 'Vào quản trị kho khi cần xem tồn, tạo phiếu, quản lý sản phẩm, nhà cung cấp, phân kho, báo cáo hoặc cài đặt vận hành.'),
                ),
                array('sourceSections' => array('8. Quản lý bán hàng ở POS', '15. Nhóm báo cáo'))
            ),

            'dashboard_global' => self::guide(
                'Dashboard quản trị kho',
                'Trang tổng quan hiển thị số liệu nhanh về sản phẩm, tồn kho, đối tác và chứng từ gần đây.',
                array('Các thẻ số liệu nghĩa là gì?', 'Làm mới dashboard thế nào?', 'Tạo phiếu nhanh ở đâu?'),
                array(
                    self::step('.tgs-dash-stats, .row .card', 'Các chỉ số nhanh', 'Theo dõi số lượng sản phẩm, tồn kho, khách hàng, nhà cung cấp và tình hình chứng từ.', 'bottom', 'center'),
                    self::step('#d-refresh, .btn[id*="refresh"], button[id*="Refresh"]', 'Làm mới dữ liệu', 'Bấm khi cần tải lại số liệu sau khi nhập phiếu, đồng bộ hoặc chỉnh dữ liệu.', 'left', 'center'),
                    self::step('#d-recent, table, .card-datatable', 'Giao dịch gần đây', 'Kiểm tra các phiếu mới phát sinh, loại phiếu, đối tác, số lượng và trạng thái.', 'top', 'center'),
                    self::step('.tgs-quick-link, a[href*="purchase-add"], a[href*="sale-add"]', 'Truy cập nhanh', 'Đi thẳng tới các thao tác hay dùng như tạo phiếu, thêm sản phẩm hoặc mở danh sách sản phẩm.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('lam moi', 'refresh', 'cap nhat'), 'Bấm nút làm mới ở phần đầu dashboard để tải lại số liệu mới nhất.'),
                    self::knowledge(array('giao dich gan day', 'phieu gan day'), 'Khu giao dịch gần đây giúp quản lý kiểm tra chứng từ vừa phát sinh và trạng thái xử lý.'),
                    self::knowledge(array('truy cap nhanh', 'tao phieu nhanh'), 'Dùng nhóm truy cập nhanh để tạo phiếu nhập, phiếu xuất, thêm sản phẩm hoặc mở danh sách sản phẩm mà không phải đi qua nhiều menu.'),
                ),
                array('sourceSections' => array('3. Quản lý giao dịch', '4. Kho hàng'))
            ),

            'product_list' => self::guide(
                'Quản lý sản phẩm',
                'Trang danh sách sản phẩm dùng để tra cứu hàng hóa, lọc danh mục/NCC, import Excel, theo dõi HSD và quản lý mã định danh.',
                array('Nhập sản phẩm từ Excel ở đâu?', 'Lọc theo danh mục thế nào?', 'Theo dõi HSD là gì?'),
                array(
                    self::step('.products-v2-page h4, h4:has(.text-muted), h4', 'Danh sách sản phẩm', 'Xác nhận đang ở đúng màn hình hàng hóa trước khi lọc, sửa hoặc chạy thao tác hàng loạt.', 'bottom', 'start'),
                    self::step('.products-v2-page .dropdown, .dt-buttons, .d-flex.gap-2', 'Nhóm thao tác dữ liệu', 'Các nút nhập/xuất Excel, cập nhật giá, cập nhật barcode, ảnh, đơn vị tính và thao tác hàng loạt nằm ở đầu trang.', 'bottom', 'end'),
                    self::step('.tgs-stats-row, .row .card', 'Thống kê nhanh', 'Theo dõi tổng sản phẩm, trạng thái hoạt động, tạm dừng và tổng tồn để biết tình hình trước khi lọc sâu.', 'bottom', 'center'),
                    self::step('#categorySidebar, .category-sidebar', 'Lọc danh mục/NCC', 'Dùng cây danh mục và bộ lọc nhà cung cấp để thu hẹp danh sách sản phẩm cần xử lý.', 'right', 'start'),
                    self::step('#productsSmartSearchBlock, .dataTables_filter, input[type="search"]', 'Tìm sản phẩm trong trang', 'Lọc theo tên, SKU, barcode hoặc thông tin liên quan ngay trong bảng sản phẩm.', 'bottom', 'center'),
                    self::step('#productsV2Table, table.dataTable', 'Bảng sản phẩm', 'Kiểm tra tồn, giá, HSD, trạng thái và mở thao tác sửa/chi tiết cho từng dòng.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('excel', 'import', 'nhap san pham', 'nhap tu excel'), 'Mở nhóm nhập/xuất ở đầu trang và chọn đúng luồng import: sản phẩm HTSoft, danh mục, giá, số lượng hoặc theo dõi HSD.'),
                    self::knowledge(array('danh muc', 'cay danh muc', 'loc danh muc'), 'Chọn node trong cây danh mục để bảng chỉ còn các sản phẩm thuộc nhánh đó. Dùng thêm lọc NCC nếu cần thu hẹp nguồn cung.'),
                    self::knowledge(array('nha cung cap', 'ncc', 'supplier'), 'Lọc theo NCC để biết sản phẩm đang gắn với nhà cung cấp nào hoặc nhóm sản phẩm chưa gắn NCC.'),
                    self::knowledge(array('hsd', 'han su dung', 'tracking'), 'Theo dõi HSD dùng cho sản phẩm cần quản lý hạn sử dụng/mã định danh. Việc bật/tắt ảnh hưởng tới nhập, bán, hoàn, hủy và báo cáo tồn.'),
                    self::knowledge(array('barcode', 'ma dinh danh', 'sku'), 'Barcode/SKU là khóa tra cứu quan trọng khi bán hàng, nhập kho và quét mã định danh. Chỉ chạy thao tác hàng loạt khi đã kiểm tra nguồn dữ liệu.'),
                ),
                array('sourceSections' => array('1. Quản lý sản phẩm', '14. Xuất nhập Excel'))
            ),

            'product_form' => self::guide(
                'Thêm/Sửa sản phẩm',
                'Form sản phẩm chuẩn hóa thông tin hàng hóa, danh mục, đơn vị tính, giá, thuế, ảnh, trạng thái và cấu hình theo dõi HSD.',
                array('Trường nào bắt buộc?', 'Chọn danh mục sản phẩm ở đâu?', 'Lưu và quay lại khác gì lưu thường?'),
                array(
                    self::step('.app-ecommerce h4, h4', 'Thông tin sản phẩm', 'Xác nhận đang thêm mới hay sửa sản phẩm để tránh ghi đè sai dữ liệu hàng hóa.', 'bottom', 'start'),
                    self::step('.box-product-info, .box-basic-info, form .card:first-of-type', 'Thông tin cơ bản', 'Nhập mã, tên, barcode/SKU và các thông tin nhận diện chính dùng xuyên suốt hệ thống.', 'bottom', 'center'),
                    self::step('#selectedCategoriesDisplay, #btnOpenCategoryModal, #categoryTreeModal', 'Danh mục sản phẩm', 'Chọn đúng nhóm hàng để lọc, báo cáo và import Excel đồng bộ với dữ liệu HTSoft.', 'right', 'center'),
                    self::step('#inventoryHelperCard, .box-unit, .box-price, #product_price_after_tax', 'Đơn vị tính và giá', 'Cấu hình đơn vị bán, đơn vị Excel, giá sau thuế/trước thuế và các thông tin phục vụ bán hàng.', 'top', 'center'),
                    self::step('.box-gallery, #product_gallery, #galleryPreview', 'Ảnh sản phẩm', 'Thêm ảnh đại diện hoặc gallery để dễ nhận diện khi bán hàng và quản lý sản phẩm.', 'top', 'center'),
                    self::step('.publish-box, #product_status, #btnSave, #btnSaveSticky', 'Trạng thái và lưu', 'Kiểm tra trạng thái hoạt động rồi lưu. Dùng lưu và quay lại khi đã hoàn tất nhập liệu.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('bat buoc', 'ma san pham', 'ten san pham', 'sku'), 'Ưu tiên nhập đầy đủ mã/SKU, tên sản phẩm, barcode nếu có, danh mục, đơn vị tính và giá. Đây là dữ liệu nền cho nhập kho, bán hàng và báo cáo.'),
                    self::knowledge(array('danh muc', 'chon danh muc'), 'Bấm nút chọn danh mục để mở cây danh mục, tích đúng nhóm hàng rồi xác nhận. Danh mục sai sẽ làm lọc và báo cáo bị lệch.'),
                    self::knowledge(array('don vi tinh', 'quy doi', 'loc', 'thung'), 'Nếu nhập theo thùng/lốc nhưng bán theo hộp, hãy cấu hình đơn vị tính và quy đổi đúng để tồn kho được tính chính xác.'),
                    self::knowledge(array('gia', 'thue', 'vat'), 'Giá sau thuế, giá trước thuế và VAT cần thống nhất với dữ liệu bán hàng và hóa đơn để tránh sai doanh thu hoặc thuế.'),
                    self::knowledge(array('luu', 'luu va quay lai'), 'Lưu thường giữ bạn ở form để kiểm tra tiếp; lưu và quay lại dùng khi đã hoàn tất và muốn trở về danh sách.'),
                ),
                array('sourceSections' => array('1. Quản lý sản phẩm', '1.4 Cấu hình quy đổi đơn vị tính'))
            ),

            'product_excel_import' => self::guide(
                'Nhập dữ liệu sản phẩm từ Excel',
                'Các màn hình import/cập nhật Excel giúp đưa nhanh dữ liệu HTSoft, giá, số lượng, danh mục hoặc trạng thái theo dõi vào hệ thống.',
                array('Chọn file Excel nào?', 'Import có ghi đè không?', 'Cần kiểm tra gì trước khi chạy?'),
                array(
                    self::step('h4, .card-title', 'Đúng loại import', 'Kiểm tra tiêu đề để chắc chắn đang import đúng loại dữ liệu: sản phẩm, danh mục, giá, số lượng hoặc HSD.', 'bottom', 'start'),
                    self::step('input[type="file"], .dropzone, .file-upload', 'File Excel nguồn', 'Chọn file xuất từ HTSoft hoặc mẫu hệ thống yêu cầu, không trộn sai định dạng giữa các luồng import.', 'bottom', 'center'),
                    self::step('.alert, .card:has(table), table', 'Kiểm tra dữ liệu xem trước', 'Đọc cảnh báo, cột lỗi và số dòng hợp lệ trước khi xác nhận cập nhật hàng loạt.', 'top', 'center'),
                    self::step('button[type="submit"], .btn-primary, .btn-success', 'Xác nhận import', 'Chỉ chạy khi đã kiểm tra đúng file, đúng site/shop và hiểu phạm vi cập nhật.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('file', 'excel', 'htsoft', 'mau'), 'Dùng đúng file Excel theo loại import. File sản phẩm, danh mục, giá và số lượng không nên dùng lẫn vì cột và nghiệp vụ khác nhau.'),
                    self::knowledge(array('ghi de', 'cap nhat', 'import'), 'Một số luồng import có thể cập nhật dữ liệu đang tồn tại. Hãy đọc phần xem trước/cảnh báo trước khi xác nhận.'),
                    self::knowledge(array('loi', 'dong loi', 'kiem tra'), 'Nếu có dòng lỗi, xử lý lại file Excel trước khi import để tránh dữ liệu thiếu SKU, sai đơn vị, sai danh mục hoặc sai giá.'),
                ),
                array('sourceSections' => array('1.1 Nhập sản phẩm từ Excel HTSoft', '14. Xuất nhập Excel'))
            ),

            'product_bulk_tools' => self::guide(
                'Công cụ cập nhật sản phẩm hàng loạt',
                'Các trang cập nhật thông minh dùng để chuẩn hóa giá, ảnh, barcode, đơn vị tính hoặc gộp file dữ liệu sản phẩm hàng loạt.',
                array('Công cụ này ảnh hưởng gì?', 'Khi nào nên chạy cập nhật toàn bộ?', 'Cần backup trước không?'),
                array(
                    self::step('h4, .card-title', 'Công cụ cập nhật hàng loạt', 'Xác nhận đúng loại cập nhật trước khi chạy vì thao tác có thể ảnh hưởng nhiều sản phẩm.', 'bottom', 'start'),
                    self::step('.alert, .card-body p, .text-muted', 'Điều kiện và cảnh báo', 'Đọc mô tả để biết công cụ sẽ cập nhật giá, ảnh, barcode hay đơn vị tính theo nguồn nào.', 'bottom', 'center'),
                    self::step('input[type="file"], table, .card', 'Nguồn dữ liệu/kiểm tra', 'Nếu công cụ có file hoặc bảng xem trước, kiểm tra kỹ dữ liệu trước khi xác nhận.', 'top', 'center'),
                    self::step('.btn-primary, .btn-danger, button[type="submit"]', 'Chạy cập nhật', 'Chỉ người phụ trách dữ liệu nên chạy thao tác này, nhất là với barcode, giá và đơn vị tính.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('anh huong', 'hang loat', 'toan bo'), 'Cập nhật hàng loạt có thể tác động nhiều sản phẩm trong site hiện tại hoặc dữ liệu dùng chung. Kiểm tra phạm vi trước khi chạy.'),
                    self::knowledge(array('gia', 'barcode', 'don vi', 'anh'), 'Giá, barcode, đơn vị tính và ảnh là dữ liệu vận hành quan trọng; sai một trong các phần này có thể ảnh hưởng bán hàng, nhập kho và đối soát.'),
                    self::knowledge(array('backup', 'sao luu'), 'Nếu thao tác cập nhật diện rộng và không chắc nguồn dữ liệu, nên có bản sao Excel/backup trước khi chạy để dễ đối chiếu.'),
                ),
                array('sourceSections' => array('1. Quản lý sản phẩm', '14. Xuất nhập Excel'))
            ),

            'product_hsd_bulk' => self::guide(
                'Bật/Tắt theo dõi HSD hàng loạt',
                'Trang này dùng để bật hoặc tắt theo dõi hạn sử dụng/mã định danh cho nhiều sản phẩm theo file Excel.',
                array('Bật theo dõi HSD khi nào?', 'Tắt theo dõi có rủi ro gì?', 'Excel cần cột nào?'),
                array(
                    self::step('h4, .card-title', 'Theo dõi HSD hàng loạt', 'Kiểm tra đang bật hay tắt tracking trước khi chọn file.', 'bottom', 'start'),
                    self::step('input[type="file"], .file-upload', 'File danh sách sản phẩm', 'File nên chứa mã hàng/SKU/barcode đúng để hệ thống xác định sản phẩm cần đổi trạng thái.', 'bottom', 'center'),
                    self::step('.alert, table', 'Cảnh báo và kết quả dò', 'Đọc kỹ số dòng hợp lệ, dòng không tìm thấy và cảnh báo trước khi xác nhận.', 'top', 'center'),
                    self::step('.btn-primary, .btn-danger, button[type="submit"]', 'Xác nhận đổi trạng thái', 'Chỉ xác nhận khi chắc chắn sản phẩm thuộc nhóm cần quản lý HSD/mã định danh.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('bat', 'theo doi', 'hsd'), 'Bật theo dõi HSD cho sản phẩm cần ghi nhận hạn dùng hoặc quản lý từng mã định danh khi nhập, bán, hoàn, hủy.'),
                    self::knowledge(array('tat', 'rui ro'), 'Tắt theo dõi HSD có thể làm luồng nhập/bán không yêu cầu mã định danh cho sản phẩm đó. Chỉ tắt khi nghiệp vụ đã xác nhận.'),
                    self::knowledge(array('excel', 'sku', 'barcode'), 'File Excel cần mã nhận diện đủ rõ như SKU/barcode/mã sản phẩm để hệ thống đổi đúng sản phẩm.'),
                ),
                array('sourceSections' => array('1.5 Quản lý hạn sử dụng khi nhập hàng'))
            ),

            'milk_under24m' => self::guide(
                'Quản lý sản phẩm sữa dưới 24 tháng',
                'Trang này quản lý danh sách sản phẩm sữa dưới 24 tháng để kiểm soát chính sách khuyến mại và dữ liệu liên quan thuế.',
                array('Vì sao cần danh sách này?', 'Import danh sách ở đâu?', 'Sản phẩm nào nên đưa vào?'),
                array(
                    self::step('h4, .card-title', 'Danh sách sữa dưới 24 tháng', 'Đây là nhóm sản phẩm cần kiểm soát riêng khi áp dụng khuyến mại và truyền dữ liệu hóa đơn.', 'bottom', 'start'),
                    self::step('a[href*="milk-under24m-import"], .btn, .dt-buttons', 'Nhập/Xuất danh sách', 'Dùng import Excel khi cần cập nhật hàng loạt danh sách sản phẩm thuộc nhóm này.', 'bottom', 'center'),
                    self::step('table, #milkUnder24mTable, .dataTable', 'Bảng sản phẩm', 'Kiểm tra mã hàng, tên hàng và trạng thái có thuộc nhóm sữa dưới 24 tháng hay không.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('duoi 24', 'sua', 'khuyen mai', 'thue'), 'Danh sách này giúp nhận diện sản phẩm sữa dưới 24 tháng để tránh áp dụng/gửi khuyến mại không phù hợp lên dữ liệu thuế.'),
                    self::knowledge(array('import', 'excel'), 'Dùng trang import sữa dưới 24 tháng để cập nhật hàng loạt thay vì sửa thủ công từng sản phẩm.'),
                    self::knowledge(array('san pham nao', 'dua vao'), 'Chỉ đưa sản phẩm thuộc nhóm sữa dưới 24 tháng theo chính sách nội bộ/tuân thủ vào danh sách này.'),
                ),
                array('sourceSections' => array('1.2 Quản lý sản phẩm sữa dưới 24 tháng'))
            ),

            'milk_under24m_import' => self::guide(
                'Nhập Excel sữa dưới 24 tháng',
                'Trang import cập nhật nhanh danh sách sản phẩm sữa dưới 24 tháng theo file Excel.',
                array('File cần chuẩn bị gì?', 'Import xong kiểm tra ở đâu?', 'Sai mã hàng thì sao?'),
                array_merge($generic_steps, array(
                    self::step('input[type="file"], .file-upload', 'File danh sách sữa dưới 24 tháng', 'Chọn file Excel chứa mã hàng/SKU/barcode của các sản phẩm cần đánh dấu.', 'bottom', 'center'),
                    self::step('button[type="submit"], .btn-primary', 'Chạy import', 'Xác nhận sau khi đã kiểm tra đúng file và đúng phạm vi sản phẩm.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('file', 'excel', 'sku', 'barcode'), 'File nên có mã nhận diện sản phẩm rõ ràng như SKU hoặc barcode để hệ thống map đúng sản phẩm.'),
                    self::knowledge(array('kiem tra', 'sau import'), 'Sau khi import, quay lại danh sách sữa dưới 24 tháng để kiểm tra sản phẩm đã được ghi nhận đúng.'),
                )),
                array('sourceSections' => array('1.2 Quản lý sản phẩm sữa dưới 24 tháng'))
            ),

            'category_list' => self::guide(
                'Quản lý danh mục sản phẩm',
                'Trang danh mục chuẩn hóa cây nhóm hàng để lọc sản phẩm, nhập liệu, báo cáo và đồng bộ dữ liệu HTSoft.',
                array('Thêm danh mục ở đâu?', 'Thống nhất danh mục là gì?', 'Lọc cây danh mục thế nào?'),
                array(
                    self::step('.categories-v2-page h4, h4', 'Danh mục sản phẩm', 'Màn hình này quản lý cây nhóm hàng dùng chung cho sản phẩm, nhập liệu và báo cáo.', 'bottom', 'start'),
                    self::step('#categorySidebar, .category-sidebar', 'Cây danh mục', 'Chọn node để xem danh mục con hoặc tìm nhanh nhóm hàng trong cây.', 'right', 'start'),
                    self::step('#btnUnifyAll, #btnUnifyCategories, .btn[id*="Unify"]', 'Thống nhất danh mục', 'Dùng để chuẩn hóa dữ liệu danh mục khi cần đồng bộ lại cấu trúc nhóm hàng.', 'left', 'center'),
                    self::step('#categoriesV2Table, table.dataTable', 'Bảng danh mục', 'Kiểm tra mã nhóm, tên nhóm, đường dẫn, trạng thái và thao tác sửa/xóa.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('them danh muc', 'tao danh muc'), 'Bấm thêm danh mục ở đầu trang để tạo nhóm hàng mới, sau đó nhập mã/tên và chọn danh mục cha nếu có.'),
                    self::knowledge(array('thong nhat', 'chuan hoa'), 'Thống nhất danh mục dùng khi cần chuẩn hóa lại cấu trúc nhóm hàng. Nên kiểm tra kỹ trước khi chạy trên dữ liệu thật.'),
                    self::knowledge(array('duong dan', 'path', 'cha con'), 'Đường dẫn cho biết vị trí danh mục trong cây cha/con, giúp tránh nhầm nhóm khi lọc sản phẩm hoặc báo cáo.'),
                ),
                array('sourceSections' => array('1.3 Quản lý danh mục sản phẩm'))
            ),

            'category_form' => self::guide(
                'Thêm/Sửa danh mục',
                'Form danh mục dùng để tạo hoặc cập nhật mã nhóm, tên nhóm, danh mục cha, trạng thái và ghi chú.',
                array('Mã danh mục nhập thế nào?', 'Chọn danh mục cha ở đâu?', 'Trạng thái hoạt động ảnh hưởng gì?'),
                array(
                    self::step('.app-ecommerce h4, h4', 'Form danh mục', 'Xác nhận đang thêm mới hay sửa danh mục trước khi lưu.', 'bottom', 'start'),
                    self::step('.box-category-info, #category_code, #category_name', 'Thông tin danh mục', 'Nhập mã danh mục, tên hiển thị và ghi chú nếu cần để nhân viên dễ nhận diện.', 'bottom', 'center'),
                    self::step('.box-parent-category, #btnOpenParentModal, #parentCategoryModal', 'Danh mục cha', 'Chọn nhóm cha để đặt đúng vị trí trong cây danh mục.', 'right', 'center'),
                    self::step('.publish-box, #category_status, #btnPublish, #btnSaveSticky', 'Trạng thái và lưu', 'Kiểm tra trạng thái hoạt động trước khi lưu để danh mục xuất hiện đúng trong lọc và nhập liệu.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('ma danh muc', 'code'), 'Mã danh mục nên ổn định và dễ đối chiếu với nguồn HTSoft hoặc quy ước nội bộ.'),
                    self::knowledge(array('danh muc cha', 'parent'), 'Chọn danh mục cha nếu nhóm này nằm dưới một nhóm lớn hơn. Nếu để trống, danh mục sẽ là nhóm gốc.'),
                    self::knowledge(array('trang thai', 'hoat dong'), 'Danh mục tắt hoạt động có thể không còn xuất hiện trong một số bộ lọc hoặc form chọn danh mục.'),
                ),
                array('sourceSections' => array('1.3 Quản lý danh mục sản phẩm'))
            ),

            'category_import' => self::guide(
                'Nhập danh mục từ Excel',
                'Trang import danh mục giúp đưa nhanh cây nhóm hàng từ Excel/HTSoft vào hệ thống.',
                array('File danh mục cần cột nào?', 'Import danh mục có tạo nhóm cha không?', 'Lỗi path xử lý thế nào?'),
                array_merge($generic_steps, array(
                    self::step('input[type="file"], .file-upload', 'File Excel danh mục', 'Chọn đúng file danh mục, không dùng file sản phẩm hoặc file giá.', 'bottom', 'center'),
                    self::step('table, .alert', 'Xem trước lỗi import', 'Kiểm tra mã danh mục, tên nhóm, path cha/con và dòng lỗi trước khi xác nhận.', 'top', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('path', 'cha con', 'parent'), 'Nếu file có path cha/con, kiểm tra đúng dấu phân cấp để tránh tạo cây danh mục sai.'),
                    self::knowledge(array('loi', 'dong loi'), 'Dòng lỗi thường do thiếu mã, sai path hoặc trùng dữ liệu. Sửa file rồi import lại để tránh cây danh mục bị lệch.'),
                )),
                array('sourceSections' => array('1.3 Quản lý danh mục sản phẩm', '14. Xuất nhập Excel'))
            ),

            'partner_list' => self::guide(
                'Quản lý đối tác',
                'Trang đối tác dùng chung cho khách hàng, nhà cung cấp và các dữ liệu liên hệ phục vụ bán hàng, mua hàng và đối soát.',
                array('Tìm đối tác thế nào?', 'Nhập Excel ở đâu?', 'Chi tiết đối tác xem ở đâu?'),
                array_merge($generic_steps, array(
                    self::step('.app-user-list h4, h4', 'Danh sách đối tác', 'Xác nhận đang xem khách hàng, liên hệ hay nhà cung cấp.', 'bottom', 'start'),
                    self::step('.app-user-list .dt-buttons, .app-user-list .d-flex.gap-2', 'Thao tác đầu trang', 'Các nút thêm mới, import/export Excel, đồng bộ hoặc thao tác hàng loạt nằm ở khu vực này.', 'bottom', 'end'),
                    self::step('#contactsTable, #suppliersTable, table.dataTable', 'Bảng đối tác', 'Tìm kiếm, kiểm tra trạng thái và mở chi tiết đối tác từ bảng chính.', 'top', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('khach hang', 'lien he', 'tim khach'), 'Dùng ô tìm kiếm của bảng để tra theo tên, email hoặc số điện thoại khách hàng/liên hệ.'),
                    self::knowledge(array('nha cung cap', 'supplier', 'ncc'), 'Nhà cung cấp là dữ liệu nền cho phiếu mua, kế hoạch mua và cấu hình mặt hàng theo NCC.'),
                    self::knowledge(array('excel', 'xuat excel', 'nhap excel'), 'Dùng nhập/xuất Excel khi cần cập nhật danh sách đối tác hàng loạt hoặc gửi dữ liệu cho bộ phận khác.'),
                )),
                array('sourceSections' => array('2. Quản lý đối tác', '14. Xuất nhập Excel'))
            ),

            'contact_list' => self::guide(
                'Danh sách khách hàng/liên hệ',
                'Trang này quản lý khách hàng, người dùng/liên hệ, trạng thái và dữ liệu phục vụ bán hàng, chăm sóc khách hàng, báo cáo.',
                array('Thêm khách hàng ở đâu?', 'Tìm khách bằng số điện thoại thế nào?', 'Thùng rác dùng để làm gì?'),
                array(
                    self::step('.app-user-list h4, h4', 'Danh sách liên hệ', 'Kiểm tra đúng danh sách đang hoạt động hay thùng rác trước khi thao tác.', 'bottom', 'start'),
                    self::step('.dt-buttons, .tgs-um-btn-add, .tgs-um-btn-import, .tgs-um-btn-export', 'Thêm/Import/Export', 'Thêm người dùng, nhập khách hàng từ Excel, xuất dữ liệu hoặc chọn thao tác hàng loạt.', 'bottom', 'center'),
                    self::step('#contactsTable, table.dataTable', 'Bảng khách hàng', 'Tra cứu theo tên, email, số điện thoại, loại khách và trạng thái.', 'top', 'center'),
                    self::step('.btn-edit-user, .btn-delete-user, .btn-restore-user, .btn-toggle-status', 'Thao tác từng dòng', 'Mở chi tiết, sửa nhanh, khóa/mở hoặc chuyển khách vào thùng rác.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('them', 'them khach', 'them nguoi dung'), 'Bấm nút thêm người dùng/khách hàng ở đầu bảng để tạo mới. Nhập tên, số điện thoại, email và trạng thái phù hợp.'),
                    self::knowledge(array('so dien thoai', 'tim khach', 'email'), 'Dùng ô tìm kiếm của DataTable để tra theo số điện thoại, email hoặc tên khách hàng.'),
                    self::knowledge(array('thung rac', 'xoa', 'khoi phuc'), 'Thùng rác chứa dữ liệu đã xóa tạm. Có thể khôi phục nếu chuyển nhầm, trước khi xóa vĩnh viễn nếu hệ thống có hỗ trợ.'),
                ),
                array('sourceSections' => array('2.1 Khách hàng'))
            ),

            'contact_detail' => self::guide(
                'Chi tiết khách hàng/liên hệ',
                'Trang chi tiết lưu thông tin cá nhân, đơn hàng, hoạt động, bảo mật và trạng thái của khách hàng/người dùng.',
                array('Sửa thông tin cá nhân ở đâu?', 'Xem lịch sử đơn hàng thế nào?', 'Vai trò và trạng thái ảnh hưởng gì?'),
                array(
                    self::step('.app-user-view h4, h4', 'Chi tiết liên hệ', 'Kiểm tra đúng người dùng/khách hàng trước khi cập nhật thông tin.', 'bottom', 'start'),
                    self::step('.nav-pills, .nav-tabs', 'Các tab thông tin', 'Chuyển giữa thông tin cá nhân, đơn hàng, hoạt động và bảo mật.', 'bottom', 'center'),
                    self::step('#formAccountSettings, form', 'Form thông tin cá nhân', 'Cập nhật họ tên, email, số điện thoại, địa chỉ, vai trò và trạng thái.', 'top', 'center'),
                    self::step('#tab-orders, table', 'Lịch sử giao dịch', 'Xem các đơn/phiếu liên quan để đối chiếu hoạt động của khách.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('sua thong tin', 'ca nhan'), 'Mở tab thông tin cá nhân, chỉnh các trường cần thiết rồi lưu thay đổi.'),
                    self::knowledge(array('don hang', 'lich su'), 'Tab đơn hàng/hoạt động dùng để xem giao dịch hoặc lịch sử liên quan tới khách hàng.'),
                    self::knowledge(array('vai tro', 'trang thai', 'khoa'), 'Vai trò và trạng thái quyết định quyền truy cập hoặc khả năng hoạt động của người dùng trong hệ thống.'),
                ),
                array('sourceSections' => array('2.1 Khách hàng'))
            ),

            'customer_import' => self::guide(
                'Nhập khách hàng từ Excel',
                'Trang import khách hàng giúp cập nhật danh sách khách/liên hệ hàng loạt phục vụ POS, chăm sóc khách hàng và báo cáo.',
                array('File khách hàng cần gì?', 'Trùng số điện thoại xử lý sao?', 'Import xong xem ở đâu?'),
                array_merge($generic_steps, array(
                    self::step('input[type="file"], .file-upload', 'File khách hàng', 'Chọn file chứa tên, số điện thoại, email và thông tin liên hệ cần đưa vào hệ thống.', 'bottom', 'center'),
                    self::step('table, .alert', 'Kiểm tra trước import', 'Xem dòng trùng, dòng sai số điện thoại/email và dòng hợp lệ trước khi xác nhận.', 'top', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('so dien thoai', 'trung'), 'Số điện thoại thường là khóa quan trọng để nhận diện khách. Kiểm tra kỹ dòng trùng trước khi import.'),
                    self::knowledge(array('xem o dau', 'sau import'), 'Sau khi import, quay lại danh sách liên hệ để tìm và kiểm tra khách hàng vừa nhập.'),
                )),
                array('sourceSections' => array('2.1 Khách hàng', '14. Xuất nhập Excel'))
            ),

            'supplier_list' => self::guide(
                'Danh sách nhà cung cấp',
                'Trang nhà cung cấp quản lý đối tác mua hàng và làm nền cho phiếu mua, hợp đồng, chính sách và cấu hình mặt hàng theo NCC.',
                array('Thêm nhà cung cấp ở đâu?', 'Xuất danh sách NCC thế nào?', 'Sản phẩm của NCC xem ở đâu?'),
                array(
                    self::step('.app-user-list h4, h4', 'Danh sách nhà cung cấp', 'Xem toàn bộ NCC đang hợp tác hoặc ngừng hợp tác.', 'bottom', 'start'),
                    self::step('#btnExportExcel, a[href*="suppliers-excel-import"], .btn-primary', 'Thêm/Import/Export NCC', 'Thêm NCC mới, nhập từ Excel hoặc xuất danh sách để đối chiếu.', 'bottom', 'center'),
                    self::step('#suppliersTable, table.dataTable', 'Bảng NCC', 'Kiểm tra tên, mã, liên hệ, trạng thái và mở trang chi tiết để cấu hình sâu hơn.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('them nha cung cap', 'them ncc'), 'Bấm "Thêm nhà cung cấp" để tạo NCC mới, nhập thông tin liên hệ và trạng thái hợp tác.'),
                    self::knowledge(array('xuat excel', 'export'), 'Dùng nút xuất Excel để lấy danh sách NCC ra ngoài phục vụ đối chiếu hoặc cập nhật hàng loạt.'),
                    self::knowledge(array('mat hang', 'san pham ncc', 'cung cap'), 'Mối quan hệ sản phẩm - NCC giúp bộ phận mua hàng biết mặt hàng nào thuộc nhà cung cấp nào và giảm nhầm khi lập phiếu mua.'),
                ),
                array('sourceSections' => array('2.2 Nhà cung cấp', '7. Lô mua và hợp đồng'))
            ),

            'supplier_detail' => self::guide(
                'Chi tiết nhà cung cấp',
                'Trang chi tiết NCC dùng để cập nhật thông tin liên hệ, trạng thái hợp tác và các thông tin phục vụ mua hàng.',
                array('Sửa thông tin NCC ở đâu?', 'Cấu hình sản phẩm NCC thế nào?', 'Khi nào ngừng hợp tác?'),
                array_merge($generic_steps, array(
                    self::step('form, .card', 'Thông tin NCC', 'Cập nhật mã/tên NCC, liên hệ, địa chỉ, ghi chú và trạng thái.', 'top', 'center'),
                    self::step('table, .nav-tabs, .card-datatable', 'Dữ liệu liên quan', 'Nếu trang có tab sản phẩm/hợp đồng, dùng để xem các dữ liệu mua hàng gắn với NCC.', 'top', 'center'),
                    self::step('#btnSave, button[type="submit"], .btn-success', 'Lưu thông tin', 'Lưu sau khi kiểm tra đúng NCC và dữ liệu liên hệ.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('sua', 'thong tin ncc'), 'Cập nhật thông tin NCC trong form chi tiết rồi lưu. Dữ liệu này được dùng khi tạo phiếu mua và đối chiếu công nợ.'),
                    self::knowledge(array('san pham', 'mat hang'), 'Nếu có vùng cấu hình mặt hàng, hãy gắn đúng sản phẩm với NCC để hỗ trợ lập kế hoạch mua hàng.'),
                )),
                array('sourceSections' => array('2.2 Nhà cung cấp'))
            ),

            'supplier_import' => self::guide(
                'Nhập nhà cung cấp từ Excel',
                'Trang import NCC cập nhật hàng loạt thông tin nhà cung cấp từ file Excel.',
                array('File NCC cần cột nào?', 'Trùng NCC xử lý sao?', 'Import xong kiểm tra đâu?'),
                array_merge($generic_steps, array(
                    self::step('input[type="file"], .file-upload', 'File Excel NCC', 'Chọn file chứa mã/tên NCC, liên hệ, điện thoại, địa chỉ và trạng thái nếu có.', 'bottom', 'center'),
                    self::step('table, .alert', 'Xem trước import', 'Kiểm tra dòng trùng, dòng thiếu dữ liệu và dòng hợp lệ trước khi cập nhật.', 'top', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('trung', 'ma ncc', 'ten ncc'), 'Nếu file có NCC trùng, kiểm tra quy tắc cập nhật/ghi đè trước khi import.'),
                    self::knowledge(array('kiem tra', 'sau import'), 'Sau import, quay lại danh sách nhà cung cấp để tìm theo mã/tên và kiểm tra trạng thái.'),
                )),
                array('sourceSections' => array('2.2 Nhà cung cấp', '14. Xuất nhập Excel'))
            ),

            'shop_list' => self::guide(
                'Quản lý shop con',
                'Trang shop con dùng để kiểm tra các website chi nhánh trong mô hình multisite và các thông tin vận hành gắn với từng site.',
                array('Shop con dùng để làm gì?', 'Cài đặt lưu theo shop nào?', 'Khi nào cần kiểm tra chi nhánh?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title, .wrap h1', 'Danh sách shop con', 'Kiểm tra đúng màn hình quản lý chi nhánh trước khi xem hoặc chỉnh thông tin site.', 'bottom', 'start'),
                    self::step('table, .card-datatable, .wp-list-table', 'Bảng website/chi nhánh', 'Đọc danh sách shop, trạng thái và thông tin nhận diện để đối chiếu dữ liệu vận hành.', 'top', 'center'),
                    self::step('form, input, select, .button-primary, .btn-primary', 'Thông tin và thao tác', 'Nếu trang có form hoặc nút thao tác, chỉ cập nhật khi đã xác nhận đúng chi nhánh/site hiện tại.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('shop con', 'chi nhanh', 'website chi nhanh', 'site'), 'Shop con đại diện cho từng website/chi nhánh trong multisite. Kiểm tra đúng site trước khi cấu hình thương hiệu, in phiếu hoặc dữ liệu vận hành.'),
                    self::knowledge(array('cai dat luu theo shop nao', 'multisite', 'luu theo site'), 'Các cài đặt như thương hiệu, in tem hoặc một số feature thường lưu theo website chi nhánh hiện tại, không dùng chung cho toàn mạng.'),
                    self::knowledge(array('kiem tra chi nhanh', 'doi chieu'), 'Khi số liệu hoặc giao diện không khớp, hãy kiểm tra đang đứng đúng chi nhánh/site trước khi xử lý dữ liệu sản phẩm, phiếu hoặc báo cáo.'),
                )),
                array('sourceSections' => array('13. Cài đặt thương hiệu và in phiếu', '16. Bảng tóm tắt chức năng'))
            ),

            'purchase_dashboard' => self::guide(
                'Dashboard mua hàng',
                'Trang tổng quan mua hàng gom nhanh hợp đồng, chính sách, lô mua, batch, công nợ và cảnh báo cần xử lý.',
                array('Dashboard mua hàng xem gì?', 'Cảnh báo mua hàng ở đâu?', 'Đi tới lô mua/hợp đồng thế nào?'),
                array(
                    self::step('h4, .card-title', 'Tổng quan mua hàng', 'Xem nhanh sức khỏe mua hàng trước khi đi sâu vào hợp đồng, lô mua, chính sách hoặc công nợ.', 'bottom', 'start'),
                    self::step('#stat-contracts, #stat-policies, .row .card', 'Các chỉ số nhanh', 'Theo dõi hợp đồng, chính sách, lô mua, batch và công nợ đang cần chú ý.', 'bottom', 'center'),
                    self::step('a[href*="purchase-alerts"], a[href*="purchase-stock-config"], .btn', 'Lối tắt nghiệp vụ', 'Đi nhanh tới cảnh báo tồn kho, cấu hình min/max, lô mua, hợp đồng hoặc chính sách mua.', 'bottom', 'center'),
                    self::step('table, .table-responsive', 'Danh sách gần đây', 'Kiểm tra các bản ghi mua hàng mới nhất để xử lý tiếp hoặc mở chi tiết.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('dashboard mua hang', 'tong quan mua hang'), 'Dashboard mua hàng giúp quản lý nắm nhanh hợp đồng, chính sách, lô mua, batch và công nợ cần xử lý. Dùng các thẻ và bảng gần đây để đi vào phần chi tiết.'),
                    self::knowledge(array('canh bao', 'thieu hang', 'min max'), 'Mở Trung tâm Cảnh báo hoặc Cấu hình Min/Max khi cần xử lý thiếu hàng, dưới min, vượt max hoặc batch sắp hết.'),
                    self::knowledge(array('lo mua', 'hop dong', 'chinh sach'), 'Lô mua gom nhu cầu mua theo đợt/NCC; hợp đồng lưu điều khoản lớn; chính sách mua lưu các ưu đãi, quà tặng, giá hoặc điều kiện áp dụng.'),
                ),
                array('sourceSections' => array('5. Quét tồn thông minh và PO', '6. Quản lý mua hàng', '7. Lô mua và hợp đồng'))
            ),

            'purchase_alerts' => self::guide(
                'Trung tâm cảnh báo mua hàng',
                'Màn hình này là lớp quét tồn thông minh hiện có: so tồn thực tế với cấu hình min/max, theo dõi batch sắp hết, cam kết NCC và công nợ/hợp đồng.',
                array('Quét tồn thông minh ở đâu?', 'Dưới Min và trên Max nghĩa là gì?', 'Cần xử lý tab nào trước?'),
                array(
                    self::step('#alert-summary-row, .kpi-card', 'KPI cảnh báo', 'Ưu tiên đọc hết hàng, dưới min, batch sắp hết, cam kết chậm và nợ quá hạn trước khi xử lý chi tiết.', 'bottom', 'center'),
                    self::step('#alert-tabs, .nav-tabs', 'Nhóm cảnh báo', 'Chuyển giữa tồn kho shop, batch sắp hết, cam kết NCC và công nợ/hợp đồng.', 'bottom', 'center'),
                    self::step('#tab-shop-stock, #ss-tbody', 'Tồn kho shop theo min/max', 'Bảng này so tồn từng shop với min/max để xác định hết hàng, dưới min, trên max hoặc bình thường.', 'top', 'center'),
                    self::step('#ss-filter-level, #ss-filter-shop-id, #ss-filter-sku', 'Bộ lọc cảnh báo tồn', 'Lọc theo shop, mức độ hoặc SKU để gom đúng nhóm cần mua, cấp hàng hoặc thu hồi hàng dư.', 'bottom', 'center'),
                    self::step('#btn-refresh-all, #btn-ss-filter, .btn[id*="filter"]', 'Tải lại và lọc dữ liệu', 'Sau khi đổi cấu hình hoặc phát sinh phiếu, tải lại để xem trạng thái cảnh báo mới.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('quet ton thong minh', 'smart stock', 'po goi y'), 'Hiện quét tồn thông minh thể hiện ở Trung tâm Cảnh báo: hệ thống so tồn thực tế với min/max để phát hiện thiếu, dư hoặc hết hàng. Kết quả này là đầu vào cho PO/gợi ý mua hoặc điều chuyển.'),
                    self::knowledge(array('duoi min', 'het hang', 'gap'), 'Dưới min hoặc hết hàng là cảnh báo gấp. Kho cần xem SKU, shop liên quan và quyết định mua thêm hoặc cấp hàng từ nơi còn tồn.'),
                    self::knowledge(array('tren max', 'du hang', 'thu hoi'), 'Trên max nghĩa là shop đang dư so với cấu hình. Có thể cân nhắc chuyển về kho hoặc điều chuyển sang shop thiếu hàng.'),
                    self::knowledge(array('batch sap het', 'cam ket ncc', 'cong no'), 'Ngoài tồn shop, các tab batch/cam kết/công nợ giúp kiểm soát hàng theo lô, tiến độ NCC và thanh toán đến hạn.'),
                ),
                array('sourceSections' => array('5.1 Quét tồn thông minh', '5.2 Danh sách PO theo gợi ý', '6.1 Cấu hình min/max cho từng mặt hàng'))
            ),

            'purchase_po' => self::guide(
                'PO và quét tồn thông minh',
                'Ngữ cảnh này dùng cho danh sách PO theo gợi ý, tạo PO chủ động hoặc các màn hình quét tồn dựa trên min/max khi được bổ sung vào hệ thống.',
                array('PO theo gợi ý là gì?', 'Tạo PO chủ động khi nào?', 'Quét tồn sinh đề xuất thế nào?'),
                array_merge($generic_steps, array(
                    self::step('.kpi-card, .summary-card, .row .card', 'Tổng hợp đề xuất', 'Đọc số SKU thiếu, dư, cần mua, cần cấp hàng hoặc cần điều chuyển trước khi tạo PO.', 'bottom', 'center'),
                    self::step('form, .filter-grid, select, input[type="search"]', 'Bộ lọc quét/PO', 'Lọc theo shop, kho, NCC, SKU hoặc mức cảnh báo để chỉ tạo yêu cầu cho đúng phạm vi.', 'bottom', 'center'),
                    self::step('table, .card-datatable, .table-responsive', 'Danh sách đề xuất', 'Kiểm tra từng SKU, tồn hiện tại, min/max, số lượng đề xuất mua/cấp/chuyển và nguồn xử lý.', 'top', 'center'),
                    self::step('.btn-primary, button[type="submit"], a[href*="po"], a[href*="purchase"]', 'Tạo hoặc xử lý PO', 'Chỉ tạo PO sau khi đã kiểm tra số lượng đề xuất, shop/kho nhận và nguồn hàng/NCC.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('po theo goi y', 'danh sach po quet', 'po quet'), 'PO theo gợi ý được tạo từ kết quả quét tồn thông minh: thiếu ở kho thì gợi ý mua về kho, thiếu ở shop thì gợi ý cấp hàng, dư ở shop thì gợi ý điều chuyển/thu hồi.'),
                    self::knowledge(array('tao po chu dong', 'po chu dong'), 'Tạo PO chủ động dùng khi cần xin hàng gấp giữa shop/kho hoặc không chờ lịch quét tự động. Vẫn cần nhập đúng nơi yêu cầu, nơi cấp, SKU và số lượng.'),
                    self::knowledge(array('min max', 'de xuat so luong'), 'Số lượng đề xuất thường dựa trên tồn hiện tại so với mức max mục tiêu. Dưới min là ưu tiên gấp, dưới max là nhu cầu bổ sung kế hoạch.'),
                    self::knowledge(array('mua', 'cap hang', 'dieu chuyen'), 'Kết quả quét có thể dẫn tới ba hướng: mua thêm từ NCC, cấp hàng từ kho sang shop, hoặc điều chuyển/thu hồi hàng dư từ shop.'),
                )),
                array('sourceSections' => array('5. Quét tồn thông minh và PO', '6. Quản lý mua hàng'))
            ),

            'purchase_stock_config' => self::guide(
                'Cấu hình Min/Max tồn kho',
                'Trang này cấu hình ngưỡng min/max theo SKU và site để hệ thống phát hiện thiếu, dư, hết hàng và sinh gợi ý mua/cấp/chuyển.',
                array('Min và Max dùng thế nào?', 'Shop có cần nhập Min không?', 'Dùng tốc độ bán để gợi ý ra sao?'),
                array(
                    self::step('.scp h4, h4', 'Cấu hình cảnh báo tồn kho', 'Xác nhận đúng trang trước khi chỉnh ngưỡng vì dữ liệu ảnh hưởng cảnh báo mua hàng và PO.', 'bottom', 'start'),
                    self::step('.scp .filter-grid, #scp-filter, form', 'Bộ lọc SKU/site', 'Lọc theo SKU, nhóm hàng, shop/kho hoặc trạng thái để chỉ chỉnh đúng nhóm mặt hàng.', 'bottom', 'center'),
                    self::step('.site-type-pill, .badge', 'Loại site đang cấu hình', 'Kho thường dùng cả min và max; shop thường tập trung max để biết thiếu/dư hàng cần cấp hoặc thu hồi.', 'bottom', 'center'),
                    self::step('table, .scp .table', 'Bảng min/max', 'Cập nhật min, max, tồn hiện tại, tốc độ bán và ghi chú theo từng SKU/site.', 'top', 'center'),
                    self::step('.js-speed-detail, .scp-hint-btn, a[href*="purchase-sell-speed-config"]', 'Gợi ý theo tốc độ bán', 'Dùng dữ liệu tốc độ bán để tham khảo trước khi đặt min/max cho từng mã hàng.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('min max', 'cau hinh ton', 'nguong ton'), 'Min/max là nền cho quét tồn thông minh. Dưới min là cảnh báo gấp, dưới max là nhu cầu bổ sung, trên max là dấu hiệu dư hàng.'),
                    self::knowledge(array('shop', 'cua hang', 'chi nhanh'), 'Với shop, thường chỉ cần max để xác định thiếu/dư so với mức trưng bày hoặc nhu cầu bán; với kho, min giúp cảnh báo thiếu nguồn cung cho toàn hệ thống.'),
                    self::knowledge(array('toc do ban', 'goi y min max'), 'Tốc độ bán theo số ngày giúp gợi ý ngưỡng hợp lý hơn thay vì nhập thủ công theo cảm tính.'),
                    self::knowledge(array('luu', 'cap nhat', 'ghi chu'), 'Sau khi sửa min/max, kiểm tra dòng bị đổi và lưu. Nên ghi chú lý do nếu chỉnh ngưỡng cho SKU quan trọng.'),
                ),
                array('sourceSections' => array('6.1 Cấu hình min/max cho từng mặt hàng', '6.2 Cấu hình min/max theo tốc độ bán'))
            ),

            'purchase_sell_speed_config' => self::guide(
                'Cài đặt tốc độ bán',
                'Trang này cấu hình số ngày lấy tốc độ bán theo SKU để hệ thống gợi ý min/max sát nhu cầu thực tế hơn.',
                array('Tốc độ bán dùng để làm gì?', 'Nên chọn bao nhiêu ngày?', 'Liên quan min/max thế nào?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Cài đặt tốc độ bán', 'Xác định khoảng ngày dùng để tính tốc độ bán của từng SKU hoặc nhóm SKU.', 'bottom', 'start'),
                    self::step('form, select, input[type="number"], input[type="search"]', 'Bộ lọc và cấu hình ngày', 'Tìm SKU và nhập số ngày phù hợp với chu kỳ bán thực tế của mặt hàng.', 'bottom', 'center'),
                    self::step('table, .table-responsive', 'Danh sách SKU', 'Kiểm tra tốc độ bán, min/max gợi ý và các cấu hình đang áp dụng.', 'top', 'center'),
                    self::step('.btn-primary, button[type="submit"]', 'Lưu cấu hình', 'Lưu sau khi kiểm tra SKU và số ngày tính tốc độ bán để tránh gợi ý min/max sai.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('toc do ban', 'so ngay', 'window days'), 'Tốc độ bán dùng để ước lượng nhu cầu thực tế. Ví dụ SKU bán nhanh có thể lấy số ngày ngắn hơn để min/max phản ứng nhanh hơn.'),
                    self::knowledge(array('min max', 'goi y'), 'Cấu hình tốc độ bán là đầu vào cho gợi ý min/max ở kho và shop, từ đó ảnh hưởng cảnh báo thiếu/dư hàng.'),
                )),
                array('sourceSections' => array('6.2 Cấu hình min/max theo tốc độ bán', '6.3 Cài đặt tốc độ bán cho từng mã hàng'))
            ),

            'purchase_contract' => self::guide(
                'Hợp đồng nhà cung cấp',
                'Màn hình hợp đồng NCC lưu điều khoản hợp tác, thời hạn, công nợ, chính sách liên quan và dữ liệu nền cho mua hàng theo lô.',
                array('Hợp đồng NCC lưu gì?', 'Hợp đồng liên quan chính sách thế nào?', 'Khi nào cần xem hợp đồng?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Hợp đồng NCC', 'Kiểm tra danh sách hoặc chi tiết hợp đồng trước khi tạo lô mua/chính sách.', 'bottom', 'start'),
                    self::step('form, input, select, textarea', 'Thông tin hợp đồng', 'Xem NCC, mã hợp đồng, thời hạn, điều khoản thanh toán và ghi chú quan trọng.', 'top', 'center'),
                    self::step('table, .table-responsive, .nav-tabs', 'Dữ liệu liên quan', 'Kiểm tra chính sách, lô mua, công nợ hoặc batch gắn với hợp đồng nếu trang có hỗ trợ.', 'top', 'center'),
                    self::step('.btn-primary, a[href*="purchase-contract-add"], button[type="submit"]', 'Thêm/Lưu hợp đồng', 'Chỉ lưu sau khi đã xác nhận NCC, thời hạn và các điều khoản ảnh hưởng mua hàng.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('hop dong', 'contract', 'ncc'), 'Hợp đồng NCC lưu thỏa thuận lớn như thời hạn, điều khoản thanh toán, cam kết và dữ liệu liên quan tới lô mua/chính sách.'),
                    self::knowledge(array('chinh sach', 'dieu khoan'), 'Chính sách mua có thể bám theo hợp đồng để áp dụng giá, quà tặng, chiết khấu hoặc điều kiện số lượng.'),
                    self::knowledge(array('cong no', 'thanh toan'), 'Điều khoản công nợ trong hợp đồng ảnh hưởng ngày đến hạn và cảnh báo thanh toán NCC.'),
                )),
                array('sourceSections' => array('7.2 Quản lý hợp đồng và điều khoản'))
            ),

            'purchase_contract_form' => self::guide(
                'Thêm/Sửa hợp đồng NCC',
                'Form hợp đồng dùng để nhập NCC, thời hạn, điều khoản và dữ liệu ràng buộc cho chính sách/lô mua.',
                array('Trường nào cần kiểm tra?', 'Lưu hợp đồng xong làm gì?', 'Điều khoản ảnh hưởng gì?'),
                array_merge($generic_steps, array(
                    self::step('form, .card', 'Form hợp đồng', 'Nhập mã, tiêu đề, NCC, thời hạn và điều khoản hợp đồng.', 'top', 'center'),
                    self::step('select[name*="supplier"], input[name*="ledger"], .sku-autocomplete', 'Nhà cung cấp', 'Chọn đúng NCC vì hợp đồng sẽ được dùng khi tạo lô mua, chính sách và công nợ.', 'bottom', 'center'),
                    self::step('textarea, input[name*="term"], input[name*="date"]', 'Thời hạn và điều khoản', 'Kiểm tra ngày hiệu lực, ngày hết hạn, điều khoản thanh toán và ghi chú trước khi lưu.', 'top', 'center'),
                    self::step('button[type="submit"], .btn-primary', 'Lưu hợp đồng', 'Lưu xong nên mở chi tiết để kiểm tra dữ liệu liên quan hoặc tạo chính sách/lô mua.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('truong bat buoc', 'ncc', 'thoi han'), 'Ưu tiên kiểm tra NCC, mã/tên hợp đồng, thời hạn hiệu lực và điều khoản thanh toán trước khi lưu.'),
                    self::knowledge(array('sau khi luu', 'tao chinh sach', 'tao lo'), 'Sau khi có hợp đồng, có thể tạo chính sách mua hoặc lô mua gắn với hợp đồng để theo dõi cam kết.'),
                )),
                array('sourceSections' => array('7.2 Quản lý hợp đồng và điều khoản'))
            ),

            'purchase_lot' => self::guide(
                'Lô mua NCC',
                'Lô mua dùng để gom nhu cầu mua theo từng đợt, theo NCC, theo SKU và theo cam kết nhập hàng.',
                array('Lô mua dùng để làm gì?', 'Theo dõi đã nhập ở đâu?', 'Lô mua liên quan phiếu mua thế nào?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Lô mua', 'Xác nhận đang xem danh sách hoặc chi tiết lô mua trước khi xử lý kế hoạch nhập hàng.', 'bottom', 'start'),
                    self::step('a[href*="purchase-lot-add"], .btn-primary', 'Tạo lô mua', 'Tạo lô khi cần gom nhu cầu mua theo NCC hoặc theo một đợt hàng cụ thể.', 'bottom', 'center'),
                    self::step('table, .table-responsive', 'Danh sách/SKU trong lô', 'Kiểm tra SKU, số lượng cam kết, đã nhập, còn lại và trạng thái xử lý.', 'top', 'center'),
                    self::step('.nav-tabs, .card, form', 'Thông tin liên quan', 'Xem hợp đồng, chính sách, batch hoặc phiếu nhập liên quan tới lô mua nếu có.', 'top', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('lo mua', 'ke hoach mua', 'purchase lot'), 'Lô mua giúp gom nhu cầu mua theo NCC/đợt hàng, theo dõi SKU cần mua, số lượng cam kết, đã nhập và còn lại.'),
                    self::knowledge(array('da nhap', 'con lai', 'committed'), 'Các cột đã nhập/còn lại dùng để biết tiến độ thực hiện lô mua sau khi phát sinh phiếu mua hoặc phiếu nhập.'),
                    self::knowledge(array('hop dong', 'chinh sach'), 'Lô mua có thể gắn hợp đồng hoặc chính sách mua để theo dõi điều khoản NCC trong cùng một đợt hàng.'),
                )),
                array('sourceSections' => array('7.1 Lên kế hoạch mua hàng theo lô mua'))
            ),

            'purchase_lot_form' => self::guide(
                'Tạo lô mua',
                'Form tạo lô mua dùng để chọn NCC/hợp đồng/chính sách và khai báo các SKU cần mua trong một kế hoạch.',
                array('Tạo lô mua bắt đầu ở đâu?', 'Thêm SKU vào lô thế nào?', 'Cần kiểm tra gì trước khi lưu?'),
                array_merge($generic_steps, array(
                    self::step('form, .card', 'Form lô mua', 'Nhập tiêu đề, NCC, hợp đồng/chính sách và thông tin kế hoạch mua.', 'top', 'center'),
                    self::step('.sku-autocomplete, input[name*="sku"], #lot-items, table', 'SKU trong lô', 'Thêm đúng SKU, số lượng cam kết, giá hoặc ghi chú theo nhu cầu mua.', 'top', 'center'),
                    self::step('select[name*="contract"], select[name*="policy"]', 'Hợp đồng/chính sách liên quan', 'Gắn đúng hợp đồng hoặc chính sách để sau này theo dõi cam kết và ưu đãi NCC.', 'bottom', 'center'),
                    self::step('button[type="submit"], .btn-primary', 'Lưu lô mua', 'Lưu sau khi kiểm tra NCC, danh sách SKU, số lượng và điều khoản liên quan.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('them sku', 'san pham trong lo'), 'Thêm SKU vào lô mua kèm số lượng cần mua/cam kết. Sai SKU hoặc số lượng sẽ làm lệch kế hoạch nhập hàng.'),
                    self::knowledge(array('hop dong', 'chinh sach', 'ncc'), 'Nên gắn đúng NCC, hợp đồng và chính sách để lô mua có đủ ngữ cảnh khi tạo phiếu mua và đối soát.'),
                )),
                array('sourceSections' => array('7.1 Lên kế hoạch mua hàng theo lô mua'))
            ),

            'purchase_policy' => self::guide(
                'Chính sách mua hàng',
                'Chính sách mua lưu các điều khoản ưu đãi từ NCC như giá theo số lượng, hàng tặng, combo, chiết khấu, thuế và điều kiện áp dụng.',
                array('Chính sách mua dùng để làm gì?', 'Có những loại điều khoản nào?', 'Áp dụng chính sách vào đâu?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Chính sách mua', 'Kiểm tra danh sách hoặc chi tiết chính sách trước khi áp dụng vào lô mua/phiếu mua.', 'bottom', 'start'),
                    self::step('table, .table-responsive', 'Danh sách điều khoản', 'Xem mã, tiêu đề, loại điều khoản, trạng thái, thời hạn và NCC/hợp đồng liên quan.', 'top', 'center'),
                    self::step('.nav-tabs, .policy-items, #policy-items-tbody', 'Điều khoản chính sách', 'Đọc các dòng áp dụng giá, quà tặng, combo, chiết khấu hoặc điều kiện mua.', 'top', 'center'),
                    self::step('a[href*="purchase-policy-add"], .btn-primary, button[type="submit"]', 'Thêm/Sửa chính sách', 'Chỉ chỉnh khi đã hiểu phạm vi áp dụng vì chính sách có thể ảnh hưởng giá mua và quà tặng.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('chinh sach mua', 'policy'), 'Chính sách mua giúp lưu điều khoản NCC: mua đủ số lượng được giá tốt, được tặng hàng, combo, chiết khấu hoặc điều kiện thanh toán.'),
                    self::knowledge(array('hang tang', 'qua tang', 'giam gia', 'combo'), 'Các điều khoản có thể là giá theo SKU/số lượng, hàng tặng, combo mua kèm hoặc giảm giá theo điều kiện.'),
                    self::knowledge(array('ap dung', 'phieu mua', 'lo mua'), 'Chính sách thường được gắn vào hợp đồng/lô mua/phiếu mua để hệ thống gợi ý giá, quà tặng hoặc điều kiện liên quan.'),
                )),
                array('sourceSections' => array('7.2 Quản lý hợp đồng và điều khoản'))
            ),

            'purchase_policy_form' => self::guide(
                'Thêm/Sửa chính sách mua',
                'Form chính sách mua dùng để khai báo thông tin chính sách và các dòng điều khoản áp dụng theo SKU, số lượng, quà tặng hoặc combo.',
                array('Tạo chính sách mua thế nào?', 'Thêm điều khoản ở đâu?', 'Import điều khoản từ chính sách khác được không?'),
                array_merge($generic_steps, array(
                    self::step('#form-add-policy, form', 'Thông tin chính sách', 'Nhập mã, tiêu đề, NCC, hợp đồng/lô liên quan, thời hạn và trạng thái chính sách.', 'top', 'center'),
                    self::step('#btn-add-policy-item, #policy-items-tbody, .policy-items', 'Điều khoản chính sách', 'Thêm từng dòng điều khoản về giá, hàng tặng, combo, chiết khấu hoặc điều kiện áp dụng.', 'top', 'center'),
                    self::step('#btn-import-from-policy, #modalImportPolicy', 'Import điều khoản', 'Có thể nhập lại điều khoản từ chính sách khác nếu cần tái sử dụng bộ quy tắc cũ.', 'bottom', 'center'),
                    self::step('#policy-prod-search, .sku-autocomplete, input[name*="sku"]', 'Chọn SKU áp dụng', 'Tìm đúng SKU hoặc tạo nhanh sản phẩm khi chưa có trong hệ thống.', 'bottom', 'center'),
                    self::step('button[type="submit"], .btn-primary', 'Lưu chính sách', 'Lưu sau khi kiểm tra mã chính sách, phạm vi áp dụng, thời hạn và từng điều khoản.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('them dieu khoan', 'policy item'), 'Bấm thêm điều khoản để khai báo từng quy tắc: giá theo số lượng, hàng tặng, combo, chiết khấu, thuế hoặc điều kiện thanh toán.'),
                    self::knowledge(array('import dieu khoan', 'sao chep'), 'Import từ chính sách khác sẽ giúp tái sử dụng bộ điều khoản, nhưng cần kiểm tra vì có thể thay thế các dòng đang nhập.'),
                    self::knowledge(array('sku', 'san pham ap dung'), 'SKU áp dụng quyết định chính sách có match với phiếu mua hay không. Chọn sai SKU sẽ làm gợi ý giá/quà tặng sai.'),
                )),
                array('sourceSections' => array('7.2 Quản lý hợp đồng và điều khoản'))
            ),

            'purchase_batch' => self::guide(
                'Quản lý batch/lô hàng nhập',
                'Batch dùng để theo dõi hàng đã nhập theo SKU, nguồn mua, lô mua, hợp đồng, chính sách, số lượng còn lại và giá vốn bình quân.',
                array('Batch là gì?', 'Batch sinh từ đâu?', 'Khi nào cần xem batch?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Batch hàng nhập', 'Xem các batch được sinh từ phiếu mua/nhập để truy xuất nguồn hàng và giá vốn.', 'bottom', 'start'),
                    self::step('table, .table-responsive', 'Danh sách batch', 'Kiểm tra mã batch, SKU, số lượng nhập, còn lại, giá vốn, lô mua và hợp đồng/chính sách liên quan.', 'top', 'center'),
                    self::step('form, select, input[type="search"]', 'Tìm/lọc batch', 'Lọc theo SKU, NCC, lô mua, trạng thái hoặc thời gian để phục vụ đối soát.', 'bottom', 'center'),
                    self::step('.nav-tabs, .card, #movement-table', 'Chi tiết và lịch sử', 'Ở trang chi tiết, xem phân bổ, lịch sử dịch chuyển và dữ liệu nguồn của batch.', 'top', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('batch', 'lo hang nhap'), 'Batch là nhóm hàng nhập theo SKU/nguồn mua, giúp truy xuất giá vốn, số lượng còn lại, lô mua, hợp đồng và chính sách liên quan.'),
                    self::knowledge(array('sinh tu dau', 'phieu mua', 'auto'), 'Batch thường được sinh tự động sau phiếu mua/nhập, gom các dòng cùng SKU và lưu snapshot dữ liệu nguồn.'),
                    self::knowledge(array('gia von', 'so luong con lai'), 'Giá vốn bình quân và số lượng còn lại trong batch phục vụ báo cáo, đối soát và cảnh báo batch sắp hết.'),
                )),
                array('sourceSections' => array('7. Lô mua và hợp đồng'))
            ),

            'purchase_payment' => self::guide(
                'Công nợ nhà cung cấp',
                'Màn hình công nợ NCC theo dõi số tiền phải trả, hạn thanh toán, hợp đồng/lô mua liên quan và trạng thái quá hạn.',
                array('Công nợ NCC xem ở đâu?', 'Nợ quá hạn xử lý thế nào?', 'Liên quan hợp đồng ra sao?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Công nợ NCC', 'Theo dõi công nợ phát sinh từ mua hàng, lô mua, batch và hợp đồng.', 'bottom', 'start'),
                    self::step('form, select, input[type="search"]', 'Bộ lọc công nợ', 'Lọc theo NCC, hợp đồng, trạng thái thanh toán hoặc thời hạn để ưu tiên xử lý.', 'bottom', 'center'),
                    self::step('table, .table-responsive', 'Bảng công nợ', 'Đọc tổng tiền, còn phải trả, ngày đến hạn, hợp đồng/lô mua và trạng thái quá hạn.', 'top', 'center'),
                    self::step('.btn-primary, a[href*="purchase-payment-detail"], button[type="submit"]', 'Chi tiết/thanh toán', 'Mở chi tiết để kiểm tra nguồn phát sinh hoặc ghi nhận thanh toán nếu quy trình hỗ trợ.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('cong no ncc', 'phai tra', 'thanh toan'), 'Công nợ NCC theo dõi khoản phải trả từ phiếu mua/lô mua/batch, gồm tổng tiền, còn lại, ngày đến hạn và trạng thái thanh toán.'),
                    self::knowledge(array('qua han', 'den han'), 'Nợ quá hạn cần ưu tiên xử lý hoặc đối chiếu với hợp đồng/điều khoản thanh toán đã lưu.'),
                    self::knowledge(array('hop dong', 'lo mua'), 'Công nợ có thể gắn hợp đồng, lô mua và batch để kế toán truy ngược nguồn phát sinh.'),
                )),
                array('sourceSections' => array('7.2 Quản lý hợp đồng và điều khoản'))
            ),

            'supplier_migration' => self::guide(
                'Đồng bộ dữ liệu nhà cung cấp',
                'Trang này phục vụ chuyển đổi/đồng bộ dữ liệu NCC giữa cấu trúc cũ và danh sách NCC dùng chung.',
                array('Khi nào chạy đồng bộ NCC?', 'Cần kiểm tra gì trước khi chạy?', 'Đồng bộ ảnh hưởng phiếu không?'),
                array_merge($generic_steps, array(
                    self::step('.alert, .card-body p', 'Cảnh báo đồng bộ', 'Đọc mô tả để hiểu nguồn và đích dữ liệu trước khi chạy.', 'bottom', 'center'),
                    self::step('table, .card', 'Kết quả rà soát', 'Kiểm tra số lượng NCC, dữ liệu trùng và các lỗi cần xử lý.', 'top', 'center'),
                    self::step('.btn-primary, .btn-warning, button[type="submit"]', 'Chạy đồng bộ', 'Chỉ admin hoặc người phụ trách dữ liệu NCC nên chạy thao tác này.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('dong bo', 'migration'), 'Đồng bộ NCC dùng khi cần đưa dữ liệu cũ sang bảng NCC dùng chung hoặc chuẩn hóa lại dữ liệu nhà cung cấp.'),
                    self::knowledge(array('anh huong', 'phieu'), 'NCC liên quan tới phiếu mua, phiếu trả NCC, hợp đồng và công nợ; kiểm tra kỹ trước khi chạy đồng bộ diện rộng.'),
                )),
                array('sourceSections' => array('2.2 Nhà cung cấp'))
            ),

            'ticket_relationships' => self::guide(
                'Sổ nhật ký quan hệ phiếu',
                'Trang này giúp xem mối liên hệ giữa phiếu gốc và phiếu phát sinh như thu/chi, nhập/xuất, hoàn, hủy, trả NCC.',
                array('Quan hệ phiếu nghĩa là gì?', 'Tìm phiếu gốc ở đâu?', 'Dùng khi đối soát thế nào?'),
                array(
                    self::step('.ticket-relationship-page h4, h4', 'Sổ nhật ký phiếu', 'Dùng để hiểu chuỗi chứng từ phát sinh từ một nghiệp vụ.', 'bottom', 'start'),
                    self::step('input[type="search"], .dataTables_filter, form', 'Tìm/lọc phiếu', 'Tra theo mã phiếu, loại phiếu hoặc khoảng thời gian để tìm đúng chuỗi nghiệp vụ.', 'bottom', 'center'),
                    self::step('table, #relationshipTable, .card-datatable', 'Bảng quan hệ', 'Xem phiếu cha, phiếu con, loại quan hệ, trạng thái và đường dẫn chi tiết.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('quan he', 'phieu goc', 'phieu con'), 'Quan hệ phiếu cho biết chứng từ nào sinh ra từ chứng từ nào, ví dụ phiếu bán sinh phiếu xuất và phiếu thu.'),
                    self::knowledge(array('doi soat', 'lich su'), 'Khi số liệu lệch, mở sổ nhật ký để lần theo phiếu gốc, phiếu liên quan và trạng thái duyệt/xử lý.'),
                ),
                array('sourceSections' => array('3. Quản lý giao dịch'))
            ),

            'ticket_list' => self::guide(
                'Danh sách phiếu giao dịch',
                'Trang danh sách phiếu dùng để lọc, tìm, kiểm tra trạng thái và mở chi tiết các chứng từ mua, bán, hoàn, hủy, nhập, xuất, thu, chi, điều chỉnh.',
                array('Lọc phiếu theo ngày thế nào?', 'Thùng rác dùng để làm gì?', 'Làm mới bảng phiếu ở đâu?'),
                array(
                    self::step('.app-ticket-list h4, .app-ticket-list .card-title, h4', 'Loại phiếu hiện tại', 'Tiêu đề cho biết đang xem phiếu mua, bán, hoàn, hủy, nhập, xuất, thu, chi hay điều chỉnh.', 'bottom', 'start'),
                    self::step('#trashToggleGroup, #btnShowActive, #btnShowTrash', 'Danh sách và thùng rác', 'Chuyển giữa phiếu đang hoạt động và phiếu đã xóa tạm để kiểm tra hoặc khôi phục.', 'bottom', 'center'),
                    self::step('#filterWarehouseStatus, #filterDebtStatus, #filterPaymentMethod, .app-ticket-list select', 'Bộ lọc nghiệp vụ', 'Lọc theo trạng thái kho, công nợ, hình thức thanh toán hoặc trạng thái phù hợp từng loại phiếu.', 'bottom', 'center'),
                    self::step('#dateQuickFilter, #filterDateFrom, #filterDateTo', 'Lọc nhanh theo ngày', 'Chọn hôm nay, tuần, tháng, quý, năm hoặc nhập khoảng ngày tùy chỉnh rồi áp dụng lọc.', 'bottom', 'center'),
                    self::step('#btnRefreshTable, .btn[id*="Refresh"]', 'Làm mới bảng', 'Tải lại danh sách sau khi vừa tạo, duyệt, xóa hoặc cập nhật phiếu.', 'left', 'center'),
                    self::step('#ticketTable, table.dataTable', 'Bảng phiếu', 'Mở chi tiết, kiểm tra mã chứng từ, đối tác, tổng tiền, công nợ, trạng thái kho và trạng thái duyệt.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('loc ngay', 'hom nay', 'tuan nay', 'thang nay'), 'Dùng nhóm lọc nhanh ngày hoặc nhập từ ngày/đến ngày rồi bấm lọc để thu hẹp danh sách phiếu.'),
                    self::knowledge(array('thung rac', 'xoa tam'), 'Thùng rác chứa phiếu đã xóa tạm. Dùng để kiểm tra, khôi phục hoặc đối chiếu khi phiếu biến mất khỏi danh sách hoạt động.'),
                    self::knowledge(array('lam moi', 'refresh'), 'Bấm nút làm mới để tải lại DataTable sau khi vừa tạo, duyệt hoặc sửa phiếu.'),
                    self::knowledge(array('cong no', 'da thu', 'da chi', 'debt'), 'Các cột cần thu/cần chi, đã thu/đã chi và còn nợ dùng để theo dõi công nợ phát sinh từ phiếu.'),
                    self::knowledge(array('nhap kho', 'xuat kho', 'trang thai kho'), 'Trạng thái kho cho biết phiếu đã sinh hoặc hoàn tất chứng từ nhập/xuất liên quan chưa.'),
                ),
                array('sourceSections' => array('3. Quản lý giao dịch', '4. Kho hàng'))
            ),

            'ticket_create' => self::guide(
                'Tạo phiếu giao dịch',
                'Form tạo phiếu dùng để lập chứng từ mua, bán, hoàn, trả NCC, hủy hoặc điều chỉnh với thông tin đối tác, sản phẩm, số lượng, phân kho, HSD và thanh toán.',
                array('Tạo phiếu bắt đầu từ đâu?', 'Thêm sản phẩm vào phiếu thế nào?', 'Khi nào nhập HSD/barcode?'),
                array(
                    self::step('.app-ticket-create h4, h4', 'Loại phiếu đang tạo', 'Xác nhận đúng nghiệp vụ: mua, bán, hoàn, trả NCC, hủy hoặc điều chỉnh trước khi nhập dữ liệu.', 'bottom', 'start'),
                    self::step('#ticketCreateForm, form', 'Form tạo phiếu', 'Toàn bộ thông tin đối tác, chứng từ, sản phẩm, thanh toán và ghi chú được lưu trong form này.', 'top', 'center'),
                    self::step('#ticketPersonInfo, .ticket-person-info, .ticket-info-card', 'Thông tin đối tác/chứng từ', 'Chọn khách hàng, nhà cung cấp hoặc phiếu gốc tùy loại nghiệp vụ.', 'bottom', 'center'),
                    self::step('#ticketProductsTableBody, #ticketProductsTable, .ticket-product-list, table', 'Danh sách sản phẩm', 'Thêm sản phẩm, nhập số lượng, giá, chiết khấu, thuế, quà tặng hoặc phân kho nếu có.', 'top', 'center'),
                    self::step('#btnTicketExcelImportMain, #btnTicketSampleLibraryMain, .btn[id*="Product"], .btn[id*="Excel"]', 'Nhập sản phẩm nhanh', 'Có thể thêm sản phẩm từ modal, Excel hoặc thư viện mẫu nếu nghiệp vụ hỗ trợ.', 'bottom', 'center'),
                    self::step('.ticket-submit-bar, #btnSubmitTicket, button[type="submit"], .btn-success', 'Kiểm tra và lưu phiếu', 'Trước khi lưu, kiểm tra số lượng, HSD/mã định danh, phân kho, thanh toán và ghi chú.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('bat dau', 'tao phieu'), 'Bắt đầu bằng xác định đúng loại phiếu, chọn đối tác/phiếu gốc nếu cần, sau đó thêm sản phẩm và kiểm tra số lượng, giá, thuế, chiết khấu.'),
                    self::knowledge(array('them san pham', 'chon san pham', 'excel'), 'Thêm sản phẩm bằng modal chọn sản phẩm, import Excel hoặc thư viện mẫu nếu nút tương ứng xuất hiện trên form.'),
                    self::knowledge(array('hsd', 'barcode', 'ma dinh danh'), 'Với sản phẩm theo dõi HSD/mã định danh, cần nhập hoặc quét đúng barcode/HSD khi hệ thống yêu cầu để tồn kho và lịch sử mã chính xác.'),
                    self::knowledge(array('phan kho', 'zone'), 'Chọn phân kho đúng nếu phiếu liên quan hàng bán, hàng công cụ, hàng livestream hoặc khu kho riêng.'),
                    self::knowledge(array('thanh toan', 'thu', 'chi'), 'Nếu phiếu có tiền, kiểm tra phần thanh toán/công nợ để hệ thống sinh hoặc liên kết phiếu thu/chi đúng nghiệp vụ.'),
                ),
                array('sourceSections' => array('3. Quản lý giao dịch', '4.2 Phiếu nhập kho', '4.3 Phiếu xuất kho'))
            ),

            'transaction_create' => self::guide(
                'Tạo phiếu thu/chi',
                'Trang này chọn các phiếu còn công nợ để tạo phiếu thu hoặc phiếu chi theo đúng nguồn nghiệp vụ.',
                array('Chọn phiếu cần thu/chi ở đâu?', 'Số tiền tối đa là gì?', 'Hình thức thanh toán chọn thế nào?'),
                array(
                    self::step('.app-transaction-create h4, h4', 'Tạo phiếu thu/chi', 'Xác nhận đang tạo phiếu thu hay phiếu chi trước khi chọn chứng từ gốc.', 'bottom', 'start'),
                    self::step('#sourceTypeTabs, .transaction-tabs', 'Tab nguồn phiếu', 'Lọc nhóm phiếu đang chờ thu/chi theo nguồn hoặc loại nghiệp vụ.', 'bottom', 'center'),
                    self::step('#pendingTicketsTable, table.pending-tickets-table', 'Danh sách phiếu chờ xử lý', 'Chọn phiếu còn công nợ để mở modal nhập số tiền thu/chi.', 'top', 'center'),
                    self::step('#paymentModal, #paymentForm', 'Modal thanh toán', 'Nhập số tiền, hình thức thanh toán và ghi chú trước khi xác nhận.', 'left', 'center'),
                    self::step('#paymentTimeline, .ticket-summary-card', 'Lịch sử thanh toán', 'Xem các lần thu/chi trước đó để tránh nhập trùng hoặc vượt số tiền còn lại.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('thu tien', 'chi tien', 'thanh toan'), 'Chọn phiếu trong bảng chờ xử lý, nhập số tiền thu/chi trong modal, chọn hình thức thanh toán rồi xác nhận.'),
                    self::knowledge(array('cong no', 'debt', 'cho thu', 'cho chi'), 'Bảng chính liệt kê các phiếu còn số tiền cần thu hoặc cần chi. Dùng tab nguồn để lọc đúng nhóm.'),
                    self::knowledge(array('toi da', 'so tien'), 'Số tiền tối đa thường là phần công nợ còn lại của phiếu gốc sau khi trừ các lần thu/chi đã ghi nhận.'),
                    self::knowledge(array('hinh thuc thanh toan', 'tien mat', 'chuyen khoan'), 'Chọn đúng tiền mặt, chuyển khoản hoặc hình thức khác để kế toán đối soát chính xác.'),
                ),
                array('sourceSections' => array('3.3 Phiếu thu, phiếu chi'))
            ),

            'ticket_detail' => self::guide(
                'Chi tiết phiếu giao dịch',
                'Trang chi tiết phiếu dùng để kiểm tra thông tin chứng từ, sản phẩm, mã định danh, phân kho, quan hệ phiếu, duyệt, in và xuất Excel.',
                array('Xem sản phẩm trong phiếu ở đâu?', 'Duyệt/từ chối phiếu thế nào?', 'Đổi phân kho trong phiếu ở đâu?'),
                array(
                    self::step('.app-ticket-detail h4, h4', 'Chi tiết phiếu', 'Kiểm tra mã phiếu, loại phiếu, trạng thái và đối tác trước khi thao tác.', 'bottom', 'start'),
                    self::step('#ticketInfoCard, .ticket-info-card, .card:has(#btnEditNote)', 'Thông tin chứng từ', 'Xem mã chứng từ, đối tác, ghi chú, trạng thái, phân kho và các thông tin tổng quan.', 'bottom', 'center'),
                    self::step('#ticketWarehouseZoneView, #btnEditTicketWarehouseZone', 'Phân kho của phiếu', 'Đổi phân kho cho các dòng sản phẩm nếu cần tách hàng bán, công cụ, livestream hoặc khu kho khác.', 'right', 'center'),
                    self::step('#ticketProductsTable, #ticketProductsTableBody, table', 'Sản phẩm trong phiếu', 'Kiểm tra từng dòng sản phẩm, số lượng, giá, HSD/mã định danh và dữ liệu liên quan.', 'top', 'center'),
                    self::step('#lotCodesModal, #btnPrintAllTrackedIdentifiers, #btnGenerateBlankCodes, #btnSmartScanExcel', 'Mã định danh/HSD', 'Quản lý mã định danh, in barcode, sinh mã trống hoặc quét Excel khi phiếu có sản phẩm tracking.', 'left', 'center'),
                    self::step('#stepFlowCard, #btnToggleSidebar, #btnOpenSidebarHeader', 'Trình tự và duyệt phiếu', 'Theo dõi trạng thái xử lý, lịch sử, duyệt hoặc từ chối theo luồng nghiệp vụ.', 'left', 'center'),
                    self::step('#btnTicketFileLibrary, #btnTicketDocLibrary, #btnPrintRelatedTickets, #btnTicketExportExcel', 'File, in và xuất dữ liệu', 'Mở thư viện chứng từ, in phiếu liên quan hoặc xuất danh sách sản phẩm ra Excel/CSV.', 'bottom', 'center'),
                ),
                array(
                    self::knowledge(array('san pham', 'dong hang', 'chi tiet hang'), 'Bảng sản phẩm trong phiếu hiển thị từng dòng hàng, số lượng, giá, thuế/chiết khấu và mã định danh nếu có.'),
                    self::knowledge(array('duyet', 'tu choi', 'approve', 'reject'), 'Dùng khu vực trình tự/duyệt phiếu để duyệt hoặc từ chối. Đọc trạng thái hiện tại trước khi thao tác vì duyệt có thể ảnh hưởng kho/công nợ.'),
                    self::knowledge(array('phan kho', 'doi phan kho'), 'Bấm nút sửa phân kho trong khối thông tin phiếu để đổi phân kho cho các dòng sản phẩm, sau đó xác nhận theo cảnh báo.'),
                    self::knowledge(array('ma dinh danh', 'barcode', 'hsd', 'lot'), 'Mở modal mã định danh từ dòng sản phẩm để xem/in/sửa mã, HSD, lô hoặc gán mã khi nghiệp vụ yêu cầu.'),
                    self::knowledge(array('in', 'xuat excel', 'file chung tu'), 'Dùng các nút file/in/xuất Excel ở đầu trang để lấy chứng từ ra ngoài hoặc kiểm tra tài liệu đã upload.'),
                ),
                array('sourceSections' => array('3. Quản lý giao dịch', '4. Kho hàng'))
            ),

            'ticket_edit' => self::guide(
                'Sửa phiếu giao dịch',
                'Trang sửa phiếu cho phép điều chỉnh chứng từ khi phiếu còn đủ điều kiện sửa, thường trước khi các phiếu kho liên quan đã duyệt.',
                array('Khi nào được sửa phiếu?', 'Sửa sản phẩm ở đâu?', 'Sửa xong cần kiểm tra gì?'),
                array_merge($generic_steps, array(
                    self::step('form, .app-ticket-create, .app-ticket-detail', 'Form sửa phiếu', 'Kiểm tra điều kiện sửa, thông tin đối tác, sản phẩm và trạng thái phiếu trước khi lưu.', 'top', 'center'),
                    self::step('table, #ticketProductsTable, #ticketProductsTableBody', 'Dòng sản phẩm', 'Điều chỉnh sản phẩm, số lượng, giá hoặc dữ liệu liên quan nếu phiếu cho phép.', 'top', 'center'),
                    self::step('button[type="submit"], #btnSubmitTicket, .btn-success', 'Lưu thay đổi', 'Lưu xong nên quay lại chi tiết phiếu để đối chiếu trạng thái, quan hệ phiếu và số liệu kho/công nợ.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('duoc sua', 'dieu kien'), 'Thường chỉ sửa khi phiếu liên quan chưa duyệt hoặc chưa khóa nghiệp vụ. Nếu nút sửa bị vô hiệu, kiểm tra trạng thái kho/duyệt.'),
                    self::knowledge(array('sua san pham', 'so luong', 'gia'), 'Sửa dòng sản phẩm trong bảng sản phẩm của phiếu, sau đó kiểm tra tổng tiền, thuế, chiết khấu và công nợ.'),
                )),
                array('sourceSections' => array('3. Quản lý giao dịch'))
            ),

            'inventory_report' => self::guide(
                'Báo cáo tồn kho',
                'Trang tồn kho cho biết tồn đầu kỳ, nhập trong kỳ, xuất trong kỳ, tồn cuối kỳ và chi tiết theo sản phẩm.',
                array('Chọn kỳ báo cáo thế nào?', 'Tồn đầu kỳ và tồn cuối kỳ nghĩa là gì?', 'Xuất báo cáo Excel ở đâu?'),
                array(
                    self::step('.app-ecommerce-inventory h4, h4', 'Báo cáo tồn kho', 'Dùng màn hình này để theo dõi biến động nhập - xuất - tồn theo kỳ.', 'bottom', 'start'),
                    self::step('.period-filter, #filterPeriodType, #filterPeriodValue, #filterPeriodYear', 'Kỳ báo cáo', 'Chọn tháng, quý hoặc năm rồi áp dụng để tải số liệu đúng kỳ.', 'bottom', 'center'),
                    self::step('.stat-card.opening', 'Tồn đầu kỳ', 'Số lượng và giá trị tồn trước khi phát sinh nhập/xuất trong kỳ đã chọn.', 'bottom', 'center'),
                    self::step('.stat-card.import', 'Nhập trong kỳ', 'Tổng hàng đi vào kho hoặc shop trong kỳ báo cáo.', 'bottom', 'center'),
                    self::step('.stat-card.export', 'Xuất trong kỳ', 'Tổng hàng đi ra khỏi kho hoặc shop trong kỳ báo cáo.', 'bottom', 'center'),
                    self::step('.stat-card.closing', 'Tồn cuối kỳ', 'Số tồn còn lại sau khi tính nhập và xuất trong kỳ.', 'bottom', 'center'),
                    self::step('#inventoryTable, table.inventory-table', 'Bảng tồn theo sản phẩm', 'Kiểm tra từng sản phẩm, số lượng, giá trị và mở chi tiết nếu cần đối soát.', 'top', 'center'),
                    self::step('#btnExportExcel, #btnPrint', 'Xuất/In báo cáo', 'Tải báo cáo ra Excel hoặc in để gửi quản lý, kế toán hoặc lưu hồ sơ đối chiếu.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('ky bao cao', 'thang', 'quy', 'nam'), 'Chọn loại kỳ, giá trị kỳ và năm trong khối kỳ báo cáo, sau đó bấm áp dụng để tải lại số liệu.'),
                    self::knowledge(array('ton dau ky', 'ton cuoi ky'), 'Tồn đầu kỳ là số tồn trước kỳ báo cáo; tồn cuối kỳ là số còn lại sau nhập và xuất trong kỳ.'),
                    self::knowledge(array('nhap trong ky', 'xuat trong ky'), 'Nhập trong kỳ làm tăng tồn; xuất trong kỳ làm giảm tồn. Hai chỉ số này giải thích biến động tồn.'),
                    self::knowledge(array('excel', 'xuat bao cao', 'in bao cao'), 'Dùng nút xuất Excel hoặc in báo cáo ở đầu trang để lấy báo cáo ra ngoài hệ thống.'),
                ),
                array('sourceSections' => array('4. Kho hàng', '15. Nhóm báo cáo'))
            ),

            'inventory_print' => self::guide(
                'In báo cáo tồn kho',
                'Trang in báo cáo tồn kho dùng để xem bản in của số liệu tồn đã lọc trước đó.',
                array('In báo cáo ở đâu?', 'Số liệu lấy từ kỳ nào?', 'Quay lại báo cáo thế nào?'),
                array_merge($generic_steps, array(
                    self::step('.print-toolbar, #btnPrint, button[onclick*="print"], .btn-primary', 'Nút in', 'Dùng để mở hộp thoại in của trình duyệt sau khi kiểm tra bản xem trước.', 'bottom', 'center'),
                    self::step('table, .report-print, .card', 'Bản in tồn kho', 'Kiểm tra kỳ báo cáo, sản phẩm, số lượng và giá trị trước khi in.', 'top', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('in', 'print'), 'Kiểm tra bản xem trước rồi bấm in để mở hộp thoại in của trình duyệt.'),
                    self::knowledge(array('ky nao', 'so lieu'), 'Số liệu bản in phụ thuộc bộ lọc/kỳ báo cáo đã chọn trước đó hoặc tham số trên URL.'),
                )),
                array('sourceSections' => array('4. Kho hàng', '15. Nhóm báo cáo'))
            ),

            'inventory_manual' => self::guide(
                'Tồn kho tự điền',
                'Trang tồn kho tự điền dùng để rà soát/cập nhật số lượng tồn thủ công trong các tình huống cần điều chỉnh dữ liệu.',
                array('Khi nào dùng tồn tự điền?', 'Cập nhật tồn có ảnh hưởng gì?', 'Cần kiểm tra sản phẩm nào?'),
                array_merge($generic_steps, array(
                    self::step('table, #inventoryManualTable, .dataTable', 'Bảng tồn tự điền', 'Tìm sản phẩm và kiểm tra số lượng hiện tại trước khi cập nhật thủ công.', 'top', 'center'),
                    self::step('input[type="number"], .form-control', 'Số lượng tồn', 'Nhập số lượng đúng theo kiểm kê thực tế hoặc nguồn đối soát đã xác nhận.', 'bottom', 'center'),
                    self::step('.btn-success, .btn-primary, button[type="submit"]', 'Lưu cập nhật', 'Chỉ lưu khi đã đối chiếu vì dữ liệu tồn ảnh hưởng báo cáo và thao tác nhập/xuất sau này.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('khi nao', 'tu dien', 'thu cong'), 'Dùng tồn tự điền khi cần cập nhật theo kiểm kê thực tế hoặc xử lý dữ liệu lệch sau đối soát.'),
                    self::knowledge(array('anh huong', 'bao cao', 'ton kho'), 'Cập nhật tồn thủ công ảnh hưởng báo cáo tồn và có thể ảnh hưởng các quyết định mua/cấp hàng. Hãy ghi chú hoặc lưu nguồn đối soát.'),
                )),
                array('sourceSections' => array('4. Kho hàng'))
            ),

            'warehouse_zone' => self::guide(
                'Quản lý phân kho',
                'Trang phân kho tạo các khu quản lý hàng như kho chính, hàng khuyến mại, công cụ hoặc hàng livestream.',
                array('Phân kho dùng để làm gì?', 'Độ ưu tiên là gì?', 'Thêm phân kho ở đâu?'),
                array(
                    self::step('.container-xxl h4, h4', 'Quản lý phân kho', 'Phân kho giúp tách hàng theo mục đích sử dụng hoặc khu vực vận hành.', 'bottom', 'start'),
                    self::step('#btnAddZone', 'Thêm phân kho', 'Tạo mã phân kho mới để dùng khi lập phiếu hoặc tách khu hàng.', 'left', 'center'),
                    self::step('#warehouseZoneTable', 'Danh sách phân kho', 'Bảng hiển thị mã phân kho, tên gợi nhớ, độ ưu tiên và nút sửa/xóa.', 'top', 'center'),
                    self::step('#zoneModal', 'Form phân kho', 'Khi thêm hoặc sửa, nhập mã, tên gợi nhớ và độ ưu tiên rồi lưu.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('phan kho', 'kho chinh', 'khu hang'), 'Phân kho giúp tách hàng hóa theo mục đích quản lý, ví dụ kho chính, hàng khuyến mại, hàng công cụ hoặc hàng livestream.'),
                    self::knowledge(array('do uu tien', 'sort order', 'mac dinh'), 'Độ ưu tiên càng cao thì phân kho càng được ưu tiên khi hệ thống cần chọn mặc định.'),
                    self::knowledge(array('them phan kho', 'tao phan kho'), 'Bấm "Thêm phân kho", nhập mã phân kho, tên gợi nhớ và độ ưu tiên rồi lưu.'),
                ),
                array('sourceSections' => array('4.4 Quản lý phân kho'))
            ),

            'lot_tracking' => self::guide(
                'Tra cứu mã định danh',
                'Trang này dùng để quét hoặc nhập barcode, xem trạng thái sản phẩm, lịch sử phiếu liên quan và thao tác hoàn/hủy nhanh khi phù hợp.',
                array('Quét barcode ở đâu?', 'Phiếu liên quan nghĩa là gì?', 'Khi nào dùng hoàn/hủy nhanh?'),
                array(
                    self::step('#guideSection, h4, .card-title', 'Hướng dẫn tra cứu', 'Màn hình ưu tiên thao tác quét barcode cho nhân viên kho hoặc cửa hàng.', 'bottom', 'center'),
                    self::step('#lotBarcodeInput, input[name*="barcode"], input[type="search"]', 'Ô quét barcode', 'Đặt con trỏ vào đây rồi quét mã hoặc nhập barcode thủ công để tra cứu.', 'bottom', 'center'),
                    self::step('#resultSection, .result-section, .card:has(#ledgersContainer)', 'Kết quả tra cứu', 'Sau khi tìm thấy mã, khu vực này hiển thị thông tin sản phẩm, trạng thái và lịch sử.', 'top', 'center'),
                    self::step('#ledgersContainer, .ledger-list, table', 'Phiếu liên quan', 'Danh sách chứng từ đã tác động đến mã định danh này.', 'top', 'center'),
                    self::step('#quickDamageBtn, #quickReturnBtn, .quick-action', 'Thao tác nhanh', 'Khi điều kiện phù hợp, hệ thống hiện nút hủy hàng hoặc hoàn hàng để xử lý nhanh.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('barcode', 'quet ma', 'ma dinh danh'), 'Đặt con trỏ ở ô barcode, quét mã trên sản phẩm hoặc nhập tay rồi chờ hệ thống trả kết quả.'),
                    self::knowledge(array('phieu lien quan', 'lich su'), 'Phiếu liên quan là lịch sử chứng từ đã làm thay đổi trạng thái hoặc vị trí của mã định danh.'),
                    self::knowledge(array('hoan hang', 'huy hang', 'thao tac nhanh'), 'Chỉ dùng hoàn/hủy nhanh khi đã kiểm tra đúng sản phẩm, đúng trạng thái và đúng nghiệp vụ cần xử lý.'),
                ),
                array('sourceSections' => array('1.5 Quản lý hạn sử dụng', '4. Kho hàng'))
            ),

            'identifier_generate' => self::guide(
                'Chi tiết phiếu sinh mã định danh',
                'Trang này kiểm tra phiếu sinh mã trống/định danh ngược và các mã đã tạo cho sản phẩm tracking.',
                array('Phiếu sinh mã dùng khi nào?', 'Xem mã đã tạo ở đâu?', 'Cần gán mã thế nào?'),
                array_merge($generic_steps, array(
                    self::step('table, #lotCodesTable, .card-datatable', 'Danh sách mã', 'Kiểm tra các mã định danh đã sinh, trạng thái và sản phẩm liên quan.', 'top', 'center'),
                    self::step('.btn, button', 'Thao tác mã', 'In, gán, chọn hoặc cập nhật mã tùy trạng thái phiếu.', 'bottom', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('sinh ma', 'ma trong', 'dinh danh nguoc'), 'Phiếu sinh mã dùng khi cần tạo mã định danh trước hoặc xử lý hàng tracking chưa có mã đầy đủ.'),
                    self::knowledge(array('gan ma', 'barcode'), 'Gán mã cần đúng sản phẩm và đúng phiếu liên quan để lịch sử mã không bị lệch.'),
                )),
                array('sourceSections' => array('1.5 Quản lý hạn sử dụng'))
            ),

            'reports_overview' => self::guide(
                'Nhóm báo cáo trong TGS Shop',
                'Trang báo cáo tổng hợp các nhóm báo cáo phục vụ điều hành, tồn kho, bán hàng, doanh thu, khách hàng và hàng sắp hết hạn.',
                array('Có những nhóm báo cáo nào?', 'Báo cáo tồn kho ở đâu?', 'Xuất Excel báo cáo thế nào?'),
                array_merge($generic_steps, array(
                    self::step('.tgs-report-card, .card, .list-group', 'Nhóm báo cáo', 'Chọn đúng nhóm báo cáo theo nhu cầu: tồn kho, bán hàng, doanh thu, khách hàng, công nợ hoặc mua hàng.', 'bottom', 'center'),
                    self::step('a[href*="report-"], a[href*="inventory"], .tgs-report-list a', 'Mở báo cáo chi tiết', 'Bấm tên báo cáo để mở màn hình lọc và xem dữ liệu chi tiết.', 'right', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('nhom bao cao', 'bao cao nao'), 'Các nhóm chính gồm điều hành, tồn kho, bán hàng, doanh thu, khách hàng, công nợ, mua hàng và hàng sắp hết hạn.'),
                    self::knowledge(array('ton kho', 'hang sap het han'), 'Báo cáo tồn kho và hàng sắp hết hạn phục vụ quản lý kho, kế hoạch xử lý hàng và điều phối bán hàng.'),
                    self::knowledge(array('excel', 'xuat'), 'Trong báo cáo chi tiết thường có nút xuất Excel sau khi chọn bộ lọc.'),
                )),
                array('sourceSections' => array('15. Nhóm báo cáo trong phạm vi note'))
            ),

            'report_dashboard' => self::guide(
                'Dashboard báo cáo',
                'Dashboard báo cáo là nơi chọn nhanh các báo cáo theo nhóm nghiệp vụ.',
                array('Mở báo cáo chi tiết ở đâu?', 'Nhóm báo cáo nghĩa là gì?', 'Báo cáo nào liên quan tồn kho?'),
                array(
                    self::step('.tgs-report-dashboard, .wrap', 'Dashboard báo cáo', 'Các báo cáo được gom theo nhóm để nhân sự chọn đúng nhu cầu điều hành.', 'bottom', 'center'),
                    self::step('.tgs-report-card', 'Thẻ nhóm báo cáo', 'Mỗi thẻ là một nhóm như bán hàng, tồn kho, công nợ, mua hàng hoặc tài chính.', 'bottom', 'center'),
                    self::step('.tgs-report-list a', 'Liên kết báo cáo', 'Bấm tên báo cáo để mở view chi tiết có bộ lọc và bảng dữ liệu.', 'right', 'center'),
                ),
                array(
                    self::knowledge(array('mo bao cao', 'chi tiet'), 'Bấm tên báo cáo trong thẻ nhóm để mở màn hình chi tiết.'),
                    self::knowledge(array('nhom', 'group'), 'Nhóm báo cáo giúp phân loại theo nghiệp vụ để tìm nhanh báo cáo cần dùng.'),
                ),
                array('sourceSections' => array('15. Nhóm báo cáo'))
            ),

            'report_detail' => self::guide(
                'Báo cáo chi tiết',
                'Trang báo cáo chi tiết dùng bộ lọc ngày, chi nhánh, sản phẩm/danh mục và bảng dữ liệu để xuất báo cáo vận hành.',
                array('Chọn khoảng ngày ở đâu?', 'Xuất Excel thế nào?', 'Báo cáo này đọc ra sao?'),
                array(
                    self::step('.tgs-report-wrapper, .wrap, h1, h2, h4', 'Báo cáo đang mở', 'Đọc tiêu đề để xác định báo cáo thuộc bán hàng, tồn kho, mua hàng, công nợ hay doanh thu.', 'bottom', 'start'),
                    self::step('#from_date, #to_date, .tgs-report-datepicker', 'Khoảng thời gian', 'Chọn từ ngày/đến ngày để giới hạn dữ liệu báo cáo.', 'bottom', 'center'),
                    self::step('.tgs-branch-selector, .tgs-category-selector, .tgs-product-selector, select, form', 'Bộ lọc báo cáo', 'Chọn chi nhánh, danh mục, sản phẩm hoặc bộ lọc riêng của báo cáo trước khi xem dữ liệu.', 'bottom', 'center'),
                    self::step('#tgs-report-table, table', 'Bảng kết quả', 'Đọc các cột số liệu chính, mở chi tiết nếu báo cáo có hỗ trợ drill-down.', 'top', 'center'),
                    self::step('.dt-buttons, .button-primary, button[name*="export"], a[href*="export"]', 'Xuất báo cáo', 'Sau khi lọc đúng, xuất Excel/CSV/PDF hoặc in nếu báo cáo hỗ trợ.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('ngay', 'tu ngay', 'den ngay'), 'Chọn từ ngày và đến ngày trước khi chạy báo cáo để số liệu đúng phạm vi cần đối chiếu.'),
                    self::knowledge(array('chi nhanh', 'shop', 'branch'), 'Nếu có bộ lọc chi nhánh, chọn đúng shop/kho hoặc toàn hệ thống tùy nhu cầu quản lý.'),
                    self::knowledge(array('excel', 'xuat', 'export'), 'Dùng nút xuất sau khi đã lọc đúng dữ liệu. File xuất phản ánh bộ lọc hiện tại.'),
                    self::knowledge(array('doc bao cao', 'so lieu'), 'Đọc tiêu đề, bộ lọc đang áp dụng, sau đó đối chiếu các cột chính trong bảng. Với báo cáo có chi tiết, bấm dòng/nút chi tiết để xem sâu.'),
                ),
                array('sourceSections' => array('15. Nhóm báo cáo', '14. Xuất nhập Excel'))
            ),

            'storekeeper_report' => self::guide(
                'Báo cáo thủ kho',
                'Báo cáo thủ kho tập trung vào số liệu tồn và lịch sử nhập/xuất phục vụ nhân viên kho đối soát hàng ngày.',
                array('Báo cáo thủ kho dùng để làm gì?', 'Lọc sản phẩm/chi nhánh ở đâu?', 'Xem lịch sử dòng thế nào?'),
                array_merge($generic_steps, array(
                    self::step('form, .tgs-ssr-filter, .card', 'Bộ lọc thủ kho', 'Chọn chi nhánh, ngày, sản phẩm hoặc trạng thái cần kiểm tra.', 'bottom', 'center'),
                    self::step('table, #tgsSsrTable', 'Bảng tồn thủ kho', 'Đọc số lượng, chênh lệch hoặc tình trạng hàng để xử lý tại kho.', 'top', 'center'),
                    self::step('.btn, button[data-row], .tgs-ssr-history', 'Lịch sử dòng', 'Mở lịch sử để xem phát sinh nhập/xuất liên quan tới dòng hàng.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('thu kho', 'doi soat'), 'Báo cáo thủ kho giúp nhân viên kho đối soát tồn và phát sinh nhập/xuất theo sản phẩm hoặc chi nhánh.'),
                    self::knowledge(array('lich su', 'row history'), 'Mở lịch sử dòng để xem các phát sinh làm thay đổi tồn của sản phẩm.'),
                )),
                array('sourceSections' => array('4. Kho hàng', '15. Nhóm báo cáo'))
            ),

            'settings' => self::guide(
                'Cài đặt hệ thống',
                'Trang cài đặt kiểm soát các feature nhạy cảm theo từng website chi nhánh trong multisite.',
                array('Bật/tắt tính năng có ảnh hưởng gì?', 'Cài đặt lưu theo shop nào?', 'Ai nên dùng trang này?'),
                array(
                    self::step('.admin-settings-page h4, h4', 'Cài đặt đặc biệt', 'Trang này dành cho admin hoặc quản lý có quyền cấu hình nghiệp vụ.', 'bottom', 'start'),
                    self::step('.admin-settings-page .alert-warning, .alert', 'Lưu ý trước khi đổi cài đặt', 'Đọc cảnh báo trước khi bật/tắt feature vì có thể ảnh hưởng thao tác của nhân viên.', 'bottom', 'center'),
                    self::step('.feature-toggle, input[type="checkbox"], .form-switch', 'Công tắc tính năng', 'Mỗi công tắc bật/tắt một luồng nghiệp vụ và thường lưu riêng theo website hiện tại.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('bat tat', 'toggle', 'feature'), 'Các công tắc tính năng nên do admin hoặc quản lý phụ trách. Khi tắt, nhân viên có thể không dùng được chức năng tương ứng.'),
                    self::knowledge(array('multisite', 'shop', 'website chi nhanh'), 'Phần lớn cài đặt ở đây lưu theo website chi nhánh hiện tại, phù hợp mô hình multisite nhiều cửa hàng.'),
                    self::knowledge(array('quyen', 'admin'), 'Trang cài đặt dành cho người có quyền quản trị. Nhân viên thường không nên tự thay đổi các công tắc này.'),
                ),
                array('sourceSections' => array('11. Phân quyền menu', '13. Cài đặt thương hiệu'))
            ),

            'label_print_settings' => self::guide(
                'Cấu hình in tem',
                'Trang cấu hình in tem dùng để thiết lập khổ giấy, số tem mỗi dòng, kích thước tem, khoảng cách, cỡ chữ, barcode và profile dùng chung.',
                array('Số tem mỗi dòng là gì?', 'Xem trước tem ở đâu?', 'Lưu profile in tem thế nào?'),
                array(
                    self::step('#tgs-label-print-settings, h4, h5', 'Cấu hình in tem', 'Thiết lập này ảnh hưởng cách tem/barcode được in cho sản phẩm.', 'bottom', 'start'),
                    self::step('#lbl_labels_per_row, #lbl_paper_width, #lbl_label_height', 'Khổ giấy và kích thước tem', 'Chọn số tem mỗi dòng, chiều rộng giấy và chiều cao tem theo máy in thực tế.', 'bottom', 'center'),
                    self::step('#lbl_padding_top, #lbl_gap_horizontal, #lbl_gap_vertical', 'Padding và khoảng cách', 'Điều chỉnh lề và khoảng cách để tem không bị lệch khi in.', 'bottom', 'center'),
                    self::step('#lbl_font_size_name, #lbl_font_size_info, #lbl_barcode_height', 'Chữ và barcode', 'Cấu hình cỡ chữ tên sản phẩm, thông tin phụ và chiều cao barcode.', 'bottom', 'center'),
                    self::step('#labelPreviewContainer', 'Xem trước bố cục tem', 'Kiểm tra trực quan bố cục tem trước khi lưu hoặc in thử.', 'top', 'center'),
                    self::step('#profileManageTable, #btnSaveCurrentProfile, #btnSaveAsNewProfile', 'Profile cấu hình', 'Lưu cấu hình hiện tại, tạo profile mới hoặc áp dụng profile cho website.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('so tem moi dong', 'labels per row'), 'Số tem mỗi dòng là số ô tem nằm ngang trên một hàng giấy. Chọn theo khổ giấy và máy in đang dùng.'),
                    self::knowledge(array('xem truoc', 'preview'), 'Khung xem trước bố cục tem thay đổi theo cấu hình khổ giấy, khoảng cách và cỡ chữ để bạn kiểm tra trước khi lưu.'),
                    self::knowledge(array('profile', 'luu cau hinh'), 'Dùng profile để lưu nhiều bộ cấu hình in tem và áp dụng cho từng website/chi nhánh khi dùng máy in khác nhau.'),
                ),
                array('sourceSections' => array('12. Cấu hình in tem'))
            ),

            'brand_settings' => self::guide(
                'Cài đặt thương hiệu và in phiếu',
                'Trang thương hiệu quản lý logo, tên website và thông tin dùng khi in các phiếu/chứng từ.',
                array('Logo dùng ở đâu?', 'Thông tin in phiếu gồm gì?', 'Lưu theo site nào?'),
                array_merge($generic_steps, array(
                    self::step('input[type="file"], .media-upload, #brand_logo', 'Logo thương hiệu', 'Chọn logo hiển thị trên phiếu in hoặc giao diện liên quan nếu trang hỗ trợ.', 'bottom', 'center'),
                    self::step('form input, form textarea, .card', 'Thông tin thương hiệu', 'Cập nhật tên website, địa chỉ, thông tin liên hệ hoặc nội dung phục vụ in chứng từ.', 'top', 'center'),
                    self::step('#btnSave, button[type="submit"], .btn-success', 'Lưu cài đặt', 'Lưu sau khi kiểm tra thông tin sẽ xuất hiện trên phiếu in.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('logo', 'thuong hieu'), 'Logo và tên thương hiệu giúp phiếu in có nhận diện rõ ràng theo đơn vị vận hành.'),
                    self::knowledge(array('in phieu', 'phieu in'), 'Thông tin in phiếu thường gồm logo, tên website/cửa hàng, địa chỉ, số điện thoại và ghi chú in ấn.'),
                    self::knowledge(array('site', 'chi nhanh'), 'Cài đặt có thể lưu theo website chi nhánh, nên kiểm tra đúng site trước khi đổi.'),
                )),
                array('sourceSections' => array('13. Cài đặt thương hiệu và in phiếu'))
            ),

            'sync_data' => self::guide(
                'Đồng bộ dữ liệu',
                'Trang đồng bộ dùng để cập nhật sản phẩm hoặc danh mục giữa nguồn dữ liệu và website hiện tại.',
                array('Khi nào cần đồng bộ?', 'Đồng bộ có ghi đè không?', 'Cần kiểm tra gì sau khi chạy?'),
                array_merge($generic_steps, array(
                    self::step('.alert, .card-body p', 'Mô tả đồng bộ', 'Đọc kỹ nguồn, đích và phạm vi dữ liệu trước khi chạy.', 'bottom', 'center'),
                    self::step('table, .card', 'Kết quả/nhật ký đồng bộ', 'Kiểm tra số lượng bản ghi, lỗi và trạng thái cập nhật.', 'top', 'center'),
                    self::step('.btn-primary, .btn-warning, button[type="submit"]', 'Chạy đồng bộ', 'Chỉ chạy khi đã chắc chắn đúng dữ liệu nguồn và đúng website.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('dong bo', 'sync'), 'Đồng bộ dùng khi cần cập nhật sản phẩm/danh mục từ nguồn chung hoặc hệ thống khác về site hiện tại.'),
                    self::knowledge(array('ghi de', 'cap nhat'), 'Một số thao tác đồng bộ có thể cập nhật dữ liệu đã tồn tại. Hãy đọc mô tả/cảnh báo trước khi chạy.'),
                )),
                array('sourceSections' => array('1. Quản lý sản phẩm', '14. Xuất nhập Excel'))
            ),

            'api_list' => self::guide(
                'Quản lý API',
                'Trang API liệt kê endpoint tích hợp để kỹ thuật kiểm tra kết nối với hệ thống bên ngoài.',
                array('API dùng để làm gì?', 'Ai nên cấu hình API?', 'Mở chi tiết endpoint ở đâu?'),
                array(
                    self::step('.app-api-list h4, h4', 'Danh sách API', 'Màn hình này dành cho quản trị/kỹ thuật khi cần kiểm tra endpoint tích hợp.', 'bottom', 'start'),
                    self::step('.api-endpoint-card, .card', 'Endpoint API', 'Mỗi thẻ mô tả một API, method, mục đích và đường dẫn chi tiết.', 'bottom', 'center'),
                    self::step('a[href*="api-detail"], .btn', 'Mở chi tiết API', 'Vào chi tiết để xem tham số, ví dụ request và form test.', 'right', 'center'),
                ),
                array(
                    self::knowledge(array('api', 'ket noi', 'tich hop'), 'API dùng cho luồng tích hợp với hệ thống bên ngoài như POS, đơn hàng, khách hàng hoặc báo cáo.'),
                    self::knowledge(array('ai nen', 'ky thuat', 'admin'), 'Chỉ người phụ trách kỹ thuật hoặc quản trị nên chỉnh/test API vì có thể tạo dữ liệu thật.'),
                    self::knowledge(array('chi tiet', 'endpoint'), 'Bấm vào endpoint để xem tài liệu chi tiết, tham số và form test.'),
                ),
                array('sourceSections' => array('9. Zalo OA', '10. Hóa đơn Viettel'))
            ),

            'api_detail' => self::guide(
                'Chi tiết API',
                'Trang chi tiết API hiển thị thông tin endpoint, tham số, ví dụ request và form test có thể tạo dữ liệu thật.',
                array('Copy request mẫu thế nào?', 'Test API có tạo dữ liệu thật không?', 'Tham số bắt buộc xem ở đâu?'),
                array(
                    self::step('.app-api-detail h4, h4', 'Chi tiết endpoint', 'Xác nhận đúng API trước khi copy mẫu hoặc test request.', 'bottom', 'start'),
                    self::step('.table-bordered, table:first-of-type', 'Thông tin cơ bản', 'Xem method, endpoint, quyền truy cập và mô tả nghiệp vụ.', 'bottom', 'center'),
                    self::step('table.table-striped, .mb-4:has(table)', 'Tham số API', 'Kiểm tra tham số bắt buộc, kiểu dữ liệu, mặc định và mô tả.', 'top', 'center'),
                    self::step('#exampleTabs, .btn-copy-example, pre code', 'Ví dụ request', 'Chọn ví dụ phù hợp rồi copy vào form test nếu cần.', 'bottom', 'center'),
                    self::step('#testRequestData, #testPathParam, #btnTestApi', 'Form test API', 'Nhập JSON/query/path param rồi test. Lưu ý một số API sẽ tạo dữ liệu thật trong hệ thống.', 'top', 'center'),
                    self::step('#testResult, #testResultContent', 'Kết quả test', 'Đọc HTTP status, payload trả về và lỗi để xử lý tích hợp.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('copy', 'mau', 'request'), 'Dùng nút Copy vào Test Form tại ví dụ request để đưa JSON mẫu vào ô test.'),
                    self::knowledge(array('du lieu that', 'test api'), 'Phần test API có thể gửi request thật và tạo dữ liệu thật. Chỉ test khi đã hiểu endpoint và dữ liệu mẫu.'),
                    self::knowledge(array('bat buoc', 'parameter', 'tham so'), 'Bảng tham số cho biết trường nào bắt buộc, kiểu dữ liệu và ý nghĩa. Kiểm tra bảng này trước khi gọi API.'),
                ),
                array('sourceSections' => array('9. Zalo OA', '10. Hóa đơn Viettel'))
            ),

            'selling_dashboard' => self::guide(
                'Dashboard chương trình bán hàng',
                'Dashboard chính sách bán hàng tổng hợp chương trình khuyến mại, nhóm chương trình và các lối tắt cấu hình POS.',
                array('Tạo chương trình khuyến mại ở đâu?', 'Xem tất cả chương trình thế nào?', 'Liên quan POS ra sao?'),
                array_merge($generic_steps, array(
                    self::step('a[href*="selling-policy-group-detail"], .btn-success', 'Tạo nhóm chương trình', 'Dùng để gom các chương trình khuyến mại theo tháng, chiến dịch hoặc shop.', 'bottom', 'center'),
                    self::step('a[href*="selling-policies"], .card', 'Xem chương trình', 'Mở danh sách nhóm/chương trình để kiểm tra hoặc chỉnh chính sách bán hàng.', 'right', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('khuyen mai', 'chuong trinh', 'pos'), 'Chương trình bán hàng được cấu hình để POS áp dụng các chính sách như giảm giá, hàng tặng hoặc khai trương shop.'),
                    self::knowledge(array('tao nhom', 'group'), 'Tạo nhóm chương trình trước nếu cần gom nhiều chính sách theo một đợt triển khai.'),
                )),
                array('sourceSections' => array('8. Quản lý bán hàng ở POS'))
            ),

            'selling_policy' => self::guide(
                'Danh sách chương trình bán hàng',
                'Trang chính sách bán hàng quản lý các nhóm chương trình khuyến mại áp dụng cho POS.',
                array('Tạo nhóm chương trình thế nào?', 'Mở chi tiết chương trình ở đâu?', 'Có những loại khuyến mại nào?'),
                array_merge($generic_steps, array(
                    self::step('a[href*="selling-policy-group-detail"], .btn-success, .btn-primary', 'Tạo nhóm/chương trình', 'Tạo nhóm mới hoặc mở nhóm hiện có để cấu hình chính sách.', 'bottom', 'center'),
                    self::step('table, .card, .selling-policy-list', 'Danh sách chính sách', 'Xem trạng thái, thời gian, phạm vi áp dụng và mở chi tiết để chỉnh sửa.', 'top', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('giam gia', 'hang tang', 'khai truong'), 'Các dạng khuyến mại trong phạm vi note gồm giảm giá hàng, hàng tặng và khuyến mại khai trương shop.'),
                    self::knowledge(array('mo chi tiet', 'sua'), 'Bấm vào nhóm hoặc nút chi tiết để mở màn hình cấu hình từng chương trình.'),
                )),
                array('sourceSections' => array('8. Quản lý bán hàng ở POS'))
            ),

            'selling_policy_form' => self::guide(
                'Thêm chương trình khuyến mại',
                'Form thêm chương trình bán hàng dùng để cấu hình điều kiện, phạm vi áp dụng, sản phẩm, quà tặng hoặc giảm giá cho POS.',
                array('Bắt đầu tạo chương trình thế nào?', 'Chọn sản phẩm áp dụng ở đâu?', 'Cần kiểm tra gì trước khi lưu?'),
                array_merge($generic_steps, array(
                    self::step('form, .selling-policy-form, .card', 'Form chương trình', 'Nhập tên, thời gian, phạm vi shop và loại chính sách trước khi cấu hình chi tiết.', 'top', 'center'),
                    self::step('table, .product-selector, .scope-selector, select', 'Sản phẩm/phạm vi áp dụng', 'Chọn đúng sản phẩm, danh mục, shop hoặc nhóm khách áp dụng chính sách.', 'top', 'center'),
                    self::step('.btn-success, .btn-primary, button[type="submit"]', 'Lưu chương trình', 'Kiểm tra điều kiện, thời gian, hàng tặng/giảm giá và phạm vi trước khi lưu.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('bat dau', 'tao chuong trinh'), 'Bắt đầu bằng tên chương trình, thời gian hiệu lực, phạm vi áp dụng và loại khuyến mại.'),
                    self::knowledge(array('san pham', 'pham vi', 'shop'), 'Chọn đúng sản phẩm/danh mục/shop để POS chỉ áp dụng chính sách cho đúng nhóm hàng và điểm bán.'),
                )),
                array('sourceSections' => array('8. Quản lý bán hàng ở POS'))
            ),

            'selling_policy_detail' => self::guide(
                'Chi tiết chương trình khuyến mại',
                'Trang chi tiết chương trình dùng để kiểm tra, chỉnh sửa, nhân bản hoặc theo dõi chính sách bán hàng đã cấu hình.',
                array('Sửa chương trình ở đâu?', 'Nhân bản chính sách thế nào?', 'Kiểm tra điều kiện áp dụng ra sao?'),
                array_merge($generic_steps, array(
                    self::step('#page-title, h4, h5', 'Chi tiết chương trình', 'Xác nhận đúng chương trình trước khi sửa hoặc nhân bản.', 'bottom', 'start'),
                    self::step('form, .card', 'Thông tin và điều kiện', 'Kiểm tra thời gian, phạm vi, sản phẩm, điều kiện mua và ưu đãi.', 'top', 'center'),
                    self::step('.btn-primary, .btn-success, .btn-warning', 'Lưu/Nhân bản/Thao tác', 'Dùng các nút thao tác sau khi đã kiểm tra điều kiện chính sách.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('sua', 'chinh sach'), 'Chỉnh thông tin trong form chi tiết rồi lưu. Kiểm tra kỹ thời gian và phạm vi áp dụng vì POS sẽ dùng dữ liệu này.'),
                    self::knowledge(array('nhan ban', 'copy'), 'Nếu cần tạo chính sách tương tự, dùng chức năng nhân bản nếu trang hỗ trợ rồi chỉnh phần khác biệt.'),
                )),
                array('sourceSections' => array('8. Quản lý bán hàng ở POS'))
            ),

            'selling_policy_group' => self::guide(
                'Nhóm chương trình khuyến mại',
                'Trang nhóm chương trình gom các chính sách bán hàng theo chiến dịch, tháng hoặc mục tiêu vận hành.',
                array('Nhóm chương trình dùng để làm gì?', 'Thêm chính sách vào nhóm ở đâu?', 'Xóa nhóm có ảnh hưởng gì?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Nhóm chương trình', 'Kiểm tra tên nhóm, trạng thái và thời gian triển khai.', 'bottom', 'start'),
                    self::step('table, .card', 'Danh sách chính sách trong nhóm', 'Xem các chương trình thuộc nhóm và mở chi tiết từng chương trình.', 'top', 'center'),
                    self::step('a[href*="selling-policy-add"], .btn-success, .btn-primary', 'Thêm chương trình', 'Tạo chính sách mới trong nhóm hiện tại.', 'right', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('nhom', 'chien dich'), 'Nhóm chương trình giúp gom nhiều khuyến mại theo một chiến dịch hoặc kỳ vận hành để quản lý dễ hơn.'),
                    self::knowledge(array('them', 'chinh sach'), 'Dùng nút thêm chương trình trong nhóm để tạo chính sách thuộc nhóm hiện tại.'),
                )),
                array('sourceSections' => array('8. Quản lý bán hàng ở POS'))
            ),

            'selling_policy_report' => self::guide(
                'Báo cáo chính sách bán hàng',
                'Báo cáo chính sách bán hàng giúp xem hiệu quả hoặc trạng thái áp dụng các chương trình khuyến mại.',
                array('Báo cáo chính sách đọc thế nào?', 'Lọc theo chương trình ở đâu?', 'Xuất dữ liệu thế nào?'),
                array_merge($generic_steps, array(
                    self::step('form, .card', 'Bộ lọc chính sách', 'Lọc theo thời gian, chương trình, shop hoặc trạng thái nếu báo cáo hỗ trợ.', 'bottom', 'center'),
                    self::step('table, .dataTable', 'Bảng báo cáo', 'Đọc số liệu áp dụng, hiệu quả và thông tin chương trình.', 'top', 'center'),
                    self::step('.dt-buttons, .btn, a[href*="export"]', 'Xuất báo cáo', 'Xuất dữ liệu sau khi bộ lọc đã đúng.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('doc bao cao', 'hieu qua'), 'Đọc theo chương trình, thời gian và số liệu áp dụng để đánh giá chính sách bán hàng.'),
                    self::knowledge(array('xuat', 'excel'), 'Dùng nút xuất nếu cần gửi dữ liệu cho quản lý hoặc kế toán.'),
                )),
                array('sourceSections' => array('8. Quản lý bán hàng ở POS', '15. Nhóm báo cáo'))
            ),

            'loyalty_policy' => self::guide(
                'Tích điểm và chăm sóc khách hàng',
                'Các trang loyalty quản lý chính sách tích điểm, thành viên, điều kiện ưu đãi và mở rộng chăm sóc khách hàng sau bán.',
                array('Chính sách tích điểm dùng để làm gì?', 'Tạo chính sách ở đâu?', 'Liên quan Zalo/POS thế nào?'),
                array_merge($generic_steps, array(
                    self::step('#loyalty-stats, .card, h4', 'Tổng quan loyalty', 'Theo dõi chính sách, thành viên hoặc cài đặt tích điểm tùy view đang mở.', 'bottom', 'center'),
                    self::step('#loyalty-table, table', 'Danh sách/chỉ tiết chính sách', 'Xem chính sách tích điểm, trạng thái, điều kiện và thao tác chi tiết.', 'top', 'center'),
                    self::step('#btn-save-loyalty-settings, .btn-primary, .btn-success', 'Lưu/cập nhật', 'Lưu chính sách hoặc cài đặt sau khi kiểm tra điều kiện áp dụng.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('tich diem', 'loyalty'), 'Chính sách tích điểm hỗ trợ chăm sóc khách hàng sau bán và có thể mở rộng sang Zalo hoặc POS.'),
                    self::knowledge(array('tao chinh sach', 'policy'), 'Tạo chính sách tích điểm từ danh sách hoặc dashboard loyalty, sau đó cấu hình điều kiện và phạm vi áp dụng.'),
                )),
                array('sourceSections' => array('9. Cấu hình Zalo OA'))
            ),

            'zalo_oa' => self::guide(
                'Cấu hình Zalo OA',
                'Trang Zalo OA cấu hình kết nối gửi thông tin đơn hàng qua Zalo, kèm liên kết hóa đơn điện tử và hướng mở rộng chăm sóc khách hàng.',
                array('Zalo OA dùng để làm gì?', 'Kết nối OA ở đâu?', 'Tin nhắn đơn hàng gửi khi nào?'),
                array(
                    self::step('#tgs-zalo-root, .tgs-zalo-admin, h4, h5', 'Zalo OA', 'Màn hình cấu hình kết nối Zalo để gửi thông tin đơn hàng và thông báo cho khách.', 'bottom', 'start'),
                    self::step('.nav-tabs, .nav-pills, [data-bs-toggle="tab"]', 'Các tab cấu hình', 'Chuyển giữa cấu hình OA, mẫu tin, log gửi và các phần kiểm tra kết nối nếu có.', 'bottom', 'center'),
                    self::step('form, input, select, textarea, .card', 'Thông tin kết nối', 'Nhập/cập nhật OA, token, provider trung gian hoặc mẫu nội dung theo cấu hình hệ thống.', 'top', 'center'),
                    self::step('table, .log, .message-log', 'Lịch sử gửi', 'Kiểm tra tin nhắn đã gửi, lỗi kết nối và phản hồi từ provider.', 'top', 'center'),
                    self::step('.btn-primary, .btn-success, button[type="submit"]', 'Lưu/kiểm tra kết nối', 'Lưu cấu hình hoặc kiểm tra trước khi áp dụng vào POS.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('zalo', 'oa', 'don hang'), 'Zalo OA dùng để gửi thông tin đơn hàng sau bán, có thể kèm link tra cứu hóa đơn điện tử cho khách.'),
                    self::knowledge(array('ket noi', 'token', 'provider'), 'Cấu hình kết nối gồm thông tin OA/token/provider trung gian. Chỉ người phụ trách hệ thống nên sửa.'),
                    self::knowledge(array('gui khi nao', 'pos'), 'Tin nhắn thường phát sinh sau luồng bán hàng POS hoặc khi hệ thống gọi sự kiện gửi thông báo đơn hàng.'),
                    self::knowledge(array('mo rong', 'cham soc', 'tich diem'), 'Về sau có thể mở rộng Zalo OA cho tích điểm, chăm sóc khách hàng và tư vấn tự động.'),
                ),
                array('sourceSections' => array('9. Cấu hình Zalo OA'))
            ),

            'viettel_invoice' => self::guide(
                'Hóa đơn điện tử Viettel',
                'Trang Viettel Invoice phục vụ tạo nháp, phát hành, hủy hoặc kiểm tra luồng gửi hóa đơn điện tử từ hệ thống shop.',
                array('Tạo hóa đơn Viettel ở đâu?', 'Gửi phát hành khác gì tạo nháp?', 'Xem response lỗi ở đâu?'),
                array(
                    self::step('#tgs-viettel-create-root, #tgs-viettel-settings-root, h4, h5', 'Viettel Invoice', 'Màn hình tích hợp hóa đơn điện tử Viettel cho luồng bán hàng và kế toán.', 'bottom', 'start'),
                    self::step('#tgs-viettel-tabs, .nav-tabs', 'Chế độ thao tác', 'Chọn tạo nháp, phát hành hoặc hủy hóa đơn tùy nghiệp vụ cần xử lý.', 'bottom', 'center'),
                    self::step('#tgs-viettel-payload-draft, #tgs-viettel-payload-issue, #tgs-viettel-payload-cancel, textarea', 'Payload JSON', 'Kiểm tra dữ liệu hóa đơn trước khi gửi lên Viettel.', 'top', 'center'),
                    self::step('.tgs-viettel-send-btn, #btnTestApi, .btn-primary, .btn-danger', 'Gửi request', 'Chỉ gửi khi đã kiểm tra đúng dữ liệu vì request có thể tạo/phát hành/hủy hóa đơn thật.', 'left', 'center'),
                    self::step('#tgs-viettel-response-status, #tgs-viettel-response-box, table', 'Response và lịch sử', 'Đọc trạng thái, lỗi hoặc hóa đơn gần đây để đối chiếu với kế toán.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('tao nhap', 'draft'), 'Tạo nháp gửi dữ liệu lên Viettel nhưng chưa phải bước phát hành cuối cùng theo luồng kế toán.'),
                    self::knowledge(array('phat hanh', 'gui cqt'), 'Phát hành/gửi CQT là thao tác nhạy cảm, cần kiểm tra dữ liệu khách hàng, sản phẩm, thuế, series và template trước khi gửi.'),
                    self::knowledge(array('huy hoa don', 'cancel'), 'Hủy hóa đơn cần đủ thông tin bắt buộc như mã số thuế, số hóa đơn, template, ngày phát hành và lý do hủy.'),
                    self::knowledge(array('response', 'loi', 'http'), 'Khu response hiển thị kết quả HTTP/payload trả về. Dùng để đọc lỗi cấu hình, lỗi dữ liệu hoặc lỗi API Viettel.'),
                ),
                array('sourceSections' => array('10. Cấu hình hóa đơn điện tử Viettel'))
            ),

            'viettel_invoice_settings' => self::guide(
                'Cấu hình hóa đơn điện tử Viettel',
                'Trang cấu hình Viettel Invoice lưu thông tin kết nối, template, series và chế độ tự động tạo hóa đơn.',
                array('Cấu hình Viettel gồm gì?', 'Series mặc định là gì?', 'Tự động tạo hóa đơn khi nào?'),
                array(
                    self::step('#tgs-viettel-settings-root, h4, h5', 'Cấu hình Viettel Invoice', 'Thiết lập kết nối và thông tin mặc định trước khi phát hành hóa đơn điện tử.', 'bottom', 'start'),
                    self::step('form, input, select, textarea', 'Thông tin kết nối', 'Nhập API, tài khoản, mã số thuế, template code, series và các trường bắt buộc.', 'top', 'center'),
                    self::step('#vi_default_invoice_series, input[id*="series"], input[id*="template"]', 'Template và series', 'Đặt template/series mặc định để payload hóa đơn sinh đúng mẫu phát hành.', 'bottom', 'center'),
                    self::step('#vi_auto_enabled, input[type="checkbox"]', 'Tự động tạo hóa đơn', 'Bật/tắt luồng tự động phát sinh hóa đơn từ đơn bán/POS nếu chính sách vận hành cho phép.', 'left', 'center'),
                    self::step('.btn-primary, .btn-success, button[type="submit"]', 'Lưu cấu hình', 'Lưu sau khi kiểm tra đúng thông tin kết nối và đúng website chi nhánh.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('template', 'series', 'mau hoa don'), 'Template code và invoice series quyết định mẫu hóa đơn phát hành. Sai thông tin có thể làm gửi Viettel thất bại.'),
                    self::knowledge(array('tu dong', 'auto', 'pos'), 'Tự động tạo hóa đơn khi sale completed/POS chỉ nên bật khi đã kiểm tra luồng dữ liệu bán hàng, sản phẩm và thuế.'),
                    self::knowledge(array('ket noi', 'api', 'tai khoan'), 'Thông tin kết nối Viettel là cấu hình nhạy cảm, chỉ admin/kế toán kỹ thuật nên chỉnh.'),
                ),
                array('sourceSections' => array('10. Cấu hình hóa đơn điện tử Viettel'))
            ),

            'permission_settings' => self::guide(
                'Phân quyền menu',
                'Trang phân quyền menu gán quyền theo user, nhóm quyền hoặc tùy chỉnh riêng để mỗi vai trò chỉ thấy phần phù hợp.',
                array('Gán quyền theo user ở đâu?', 'Full và tùy chỉnh khác gì?', 'Nhóm quyền dùng để làm gì?'),
                array(
                    self::step('.wrap h1, h1, h2', 'Phân quyền menu', 'Màn hình này kiểm soát menu người dùng nhìn thấy trong hệ thống.', 'bottom', 'start'),
                    self::step('select, input[type="search"], .user-selector, table', 'Chọn người dùng', 'Tìm user hoặc vai trò cần gán quyền trước khi chỉnh menu.', 'bottom', 'center'),
                    self::step('.permission-tree, .menu-permission, table, form', 'Cây quyền/menu', 'Tích các menu hoặc nhóm quyền phù hợp với công việc thực tế.', 'top', 'center'),
                    self::step('.button-primary, .btn-primary, button[type="submit"]', 'Lưu phân quyền', 'Lưu sau khi kiểm tra đúng user/role vì thay đổi ảnh hưởng quyền thao tác của nhân viên.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('phan quyen', 'menu', 'user'), 'Phân quyền menu giúp mỗi nhóm nhân sự chỉ thấy các phần phù hợp, giảm thao tác nhầm.'),
                    self::knowledge(array('vai tro', 'ke toan', 'kho', 'ban hang', 'admin'), 'Các nhóm vai trò thường gồm kế toán mua hàng, nhân viên kho, nhân viên bán hàng, quản lý kinh doanh và admin.'),
                    self::knowledge(array('nhom quyen', 'role'), 'Nhóm quyền giúp tái sử dụng cùng một bộ menu cho nhiều user thay vì cấu hình từng người.'),
                ),
                array('sourceSections' => array('11. Phân quyền menu'))
            ),

            'permission_roles' => self::guide(
                'Nhóm quyền',
                'Trang nhóm quyền tạo/sửa/xóa các bộ menu dùng chung cho nhiều người dùng.',
                array('Tạo nhóm quyền thế nào?', 'Sửa nhóm có ảnh hưởng ai?', 'Khi nào dùng tùy chỉnh riêng?'),
                array_merge($generic_steps, array(
                    self::step('table, .role-list, form', 'Danh sách nhóm quyền', 'Xem các nhóm quyền đang có và menu được gán cho từng nhóm.', 'top', 'center'),
                    self::step('.button-primary, .btn-primary, button[type="submit"]', 'Lưu nhóm quyền', 'Lưu nhóm sau khi kiểm tra đúng menu cho vai trò vận hành.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('tao nhom', 'role'), 'Tạo nhóm quyền khi nhiều người có cùng phạm vi công việc và menu cần truy cập.'),
                    self::knowledge(array('anh huong', 'user'), 'Sửa nhóm quyền có thể ảnh hưởng tất cả user đang dùng nhóm đó. Kiểm tra danh sách user trước khi đổi lớn.'),
                )),
                array('sourceSections' => array('11. Phân quyền menu'))
            ),

            'merge_guest_persons' => self::guide(
                'Gộp khách lẻ trùng lặp',
                'Công cụ này tìm và gộp các khách lẻ trùng để làm sạch dữ liệu khách hàng và giao dịch.',
                array('Khi nào cần gộp khách?', 'Gộp có mất lịch sử không?', 'Cần kiểm tra gì trước khi chạy?'),
                array_merge($generic_steps, array(
                    self::step('table, .card', 'Danh sách khách trùng', 'Kiểm tra các nhóm khách nghi trùng trước khi chọn gộp.', 'top', 'center'),
                    self::step('.btn-warning, .btn-danger, .btn-primary', 'Thao tác gộp', 'Chỉ chạy khi chắc chắn các bản ghi thuộc cùng một khách hoặc cùng nhóm khách lẻ cần gom.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('khach le', 'trung lap', 'gop'), 'Gộp khách lẻ dùng để làm sạch dữ liệu khách hàng khi có nhiều bản ghi trùng hoặc phát sinh từ POS.'),
                    self::knowledge(array('lich su', 'giao dich'), 'Trước khi gộp, kiểm tra lịch sử giao dịch để tránh gom nhầm hai khách khác nhau.'),
                )),
                array('sourceSections' => array('2.1 Khách hàng'))
            ),

            'legacy_import' => self::guide(
                'Nhập hàng',
                'Trang nhập hàng legacy dùng để ghi nhận hàng vào kho hoặc shop trong các luồng cũ còn được giữ lại.',
                array('Nhập hàng bắt đầu ở đâu?', 'Có cần HSD không?', 'Sau nhập kiểm tra tồn thế nào?'),
                array_merge($generic_steps, array(
                    self::step('form, table', 'Form nhập hàng', 'Nhập thông tin nhà cung cấp, sản phẩm, số lượng, giá và HSD nếu sản phẩm cần theo dõi.', 'top', 'center'),
                    self::step('.btn-primary, .btn-success, button[type="submit"]', 'Lưu nhập hàng', 'Lưu sau khi kiểm tra đúng sản phẩm và số lượng thực nhận.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('nhap hang', 'nhap kho'), 'Nhập hàng làm tăng tồn kho và nên có chứng từ rõ ràng để đối chiếu sau này.'),
                    self::knowledge(array('hsd', 'han su dung'), 'Nếu sản phẩm có HSD, ghi nhận hạn dùng ngay lúc nhập để hệ thống cảnh báo hàng sắp hết hạn.'),
                )),
                array('sourceSections' => array('4.2 Phiếu nhập kho', '1.5 Quản lý hạn sử dụng'))
            ),

            'ledger_view' => self::guide(
                'Sổ cái kho',
                'Sổ cái ghi lại các phát sinh làm thay đổi tồn kho hoặc dòng tiền để phục vụ đối soát.',
                array('Sổ cái dùng để làm gì?', 'Tìm phát sinh ở đâu?', 'Liên quan phiếu nào?'),
                array_merge($generic_steps, array(
                    self::step('input[type="search"], form, select', 'Tìm/lọc phát sinh', 'Lọc theo ngày, loại phiếu, sản phẩm hoặc mã chứng từ nếu trang hỗ trợ.', 'bottom', 'center'),
                    self::step('table, .dataTable', 'Bảng sổ cái', 'Đọc từng phát sinh nhập/xuất/thu/chi và mở phiếu liên quan để đối soát.', 'top', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('so cai', 'ledger'), 'Sổ cái là lịch sử phát sinh dùng để giải thích vì sao tồn kho hoặc công nợ thay đổi.'),
                    self::knowledge(array('phieu lien quan', 'doi soat'), 'Mở mã phiếu liên quan từ dòng sổ cái để kiểm tra chứng từ gốc.'),
                )),
                array('sourceSections' => array('3. Quản lý giao dịch', '4. Kho hàng'))
            ),

            'pos_screen' => self::guide(
                'POS bán hàng',
                'POS dùng cho nhân viên bán hàng tại quầy để tạo đơn, áp dụng khuyến mại, thanh toán, in phiếu và gửi thông tin cho khách.',
                array('Tạo đơn bán thế nào?', 'Khuyến mại POS áp dụng ra sao?', 'Gửi hóa đơn/Zalo ở đâu?'),
                array_merge($generic_steps, array(
                    self::step('#tmdwppos, .pos-root, .pos-wrapper', 'Màn hình POS', 'Đây là khu vực bán hàng tại quầy, ưu tiên thao tác nhanh và chính xác.', 'bottom', 'center'),
                    self::step('input[type="search"], input[name*="barcode"], .barcode', 'Quét/tìm sản phẩm', 'Quét barcode hoặc tìm sản phẩm để thêm vào đơn bán.', 'bottom', 'center'),
                    self::step('.cart, .order-items, table', 'Giỏ hàng/đơn hiện tại', 'Kiểm tra sản phẩm, số lượng, giá, khuyến mại và tổng tiền trước khi thanh toán.', 'top', 'center'),
                    self::step('.payment, .checkout, .btn-primary', 'Thanh toán và hoàn tất', 'Chọn hình thức thanh toán, in phiếu và xử lý gửi thông tin đơn/hóa đơn nếu có.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('tao don', 'ban hang'), 'Quét hoặc tìm sản phẩm, kiểm tra giỏ hàng, chọn khách nếu cần rồi thanh toán để hoàn tất đơn.'),
                    self::knowledge(array('khuyen mai', 'giam gia', 'hang tang'), 'Khuyến mại POS lấy từ chương trình bán hàng đã cấu hình, ví dụ giảm giá, hàng tặng hoặc khai trương shop.'),
                    self::knowledge(array('zalo', 'hoa don', 'viettel'), 'Sau bán hàng, hệ thống có thể gửi thông tin đơn qua Zalo và phát hành/tra cứu hóa đơn điện tử nếu đã cấu hình.'),
                )),
                array('sourceSections' => array('8. Quản lý bán hàng ở POS', '9. Zalo OA', '10. Hóa đơn Viettel'))
            ),

            'transfer_internal' => self::guide(
                'Luân chuyển hàng nội bộ',
                'Nhóm màn hình mua bán/trả hàng nội bộ dùng để điều phối hàng giữa kho và shop, hoặc giữa các điểm trong hệ thống.',
                array('Luân chuyển nội bộ gồm gì?', 'Khi nào bán/mua nội bộ?', 'Trả hàng nội bộ khác gì?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Nghiệp vụ nội bộ', 'Xác định đang thao tác bán nội bộ, mua nội bộ, trả nội bộ, nhận trả hay báo cáo.', 'bottom', 'start'),
                    self::step('form, table, .card', 'Vùng xử lý chính', 'Kiểm tra shop gửi, shop nhận, sản phẩm, số lượng và trạng thái xử lý trước khi lưu.', 'top', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('luan chuyen', 'noi bo', 'mua ban noi bo'), 'Luân chuyển nội bộ gồm bán hàng nội bộ từ nơi gửi, mua/nhận nội bộ ở nơi nhận, và trả/nhận trả khi shop trả hàng về kho hoặc điểm khác.'),
                    self::knowledge(array('kho sang shop', 'shop sang shop'), 'Dùng bán/mua nội bộ khi cần cấp hàng từ kho sang shop hoặc điều phối hàng giữa các điểm.'),
                    self::knowledge(array('tra hang noi bo'), 'Trả hàng nội bộ dùng khi shop trả hàng dư, hàng bán chậm hoặc cần gom về kho để quản lý lại.'),
                )),
                array('sourceSections' => array('4.5 Mua bán nội bộ', '4.6 Trả hàng nội bộ'))
            ),

            'transfer_report' => self::guide(
                'Báo cáo mua bán nội bộ',
                'Báo cáo nội bộ giúp theo dõi luồng hàng đi/đến, trạng thái phiếu, chênh lệch nhận hàng và hiệu quả điều phối giữa các shop/kho.',
                array('Báo cáo nội bộ xem gì?', 'Lọc theo shop ở đâu?', 'Theo dõi phiếu lệch thế nào?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Báo cáo nội bộ', 'Xem tổng quan luân chuyển hàng giữa các site trước khi đi vào phiếu cụ thể.', 'bottom', 'start'),
                    self::step('form, select, input[type="date"], input[type="search"]', 'Bộ lọc báo cáo', 'Lọc theo thời gian, shop gửi, shop nhận, loại phiếu hoặc trạng thái để đối soát.', 'bottom', 'center'),
                    self::step('table, .table-responsive', 'Bảng kết quả', 'Đọc số lượng, giá trị, trạng thái xuất/nhập/trả/nhận trả và mở chi tiết khi cần.', 'top', 'center'),
                    self::step('.btn-primary, button[name*="export"], a[href*="export"]', 'Xuất hoặc tải lại', 'Xuất báo cáo sau khi đã lọc đúng phạm vi cần gửi quản lý/kế toán/kho.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('bao cao noi bo', 'dieu chuyen'), 'Báo cáo nội bộ dùng để đối soát luồng hàng giữa nơi gửi và nơi nhận, theo trạng thái phiếu và thời gian.'),
                    self::knowledge(array('chenh lech', 'mismatch', 'nhan thuc te'), 'Nếu số lượng nhận thực tế khác số xuất, cần mở chi tiết phiếu để kiểm tra xác nhận chênh lệch.'),
                )),
                array('sourceSections' => array('4.5 Mua bán nội bộ', '4.6 Trả hàng nội bộ'))
            ),

            'transfer_export' => self::guide(
                'Tạo phiếu bán nội bộ',
                'Phiếu bán nội bộ ghi nhận nơi gửi xuất hàng cho shop/kho nhận trong luồng điều phối hàng nội bộ.',
                array('Tạo bán nội bộ bắt đầu ở đâu?', 'Chọn shop nhận thế nào?', 'Cần kiểm tra sản phẩm gì?'),
                array(
                    self::step('h4, .card-title', 'Bán hàng nội bộ', 'Xác nhận đúng nghiệp vụ xuất hàng từ nơi gửi sang nơi nhận.', 'bottom', 'start'),
                    self::step('form, #transferExportForm, .card', 'Thông tin phiếu', 'Chọn shop/kho nhận, ghi chú và thông tin chứng từ nội bộ.', 'top', 'center'),
                    self::step('table, #transferProductsTable, .product-list', 'Sản phẩm xuất', 'Thêm sản phẩm, số lượng, mã định danh/HSD nếu có và kiểm tra tồn trước khi lưu.', 'top', 'center'),
                    self::step('button[type="submit"], .btn-primary, .btn-success', 'Lưu phiếu bán nội bộ', 'Lưu sau khi xác nhận nơi nhận, sản phẩm và số lượng thực xuất.', 'left', 'center'),
                ),
                array(
                    self::knowledge(array('ban noi bo', 'xuat noi bo'), 'Bán nội bộ là bước nơi gửi xuất hàng cho nơi nhận. Phiếu này là đầu vào để phía nhận tạo/hoàn tất mua nội bộ.'),
                    self::knowledge(array('shop nhan', 'noi nhan'), 'Chọn đúng shop/kho nhận vì sai điểm nhận sẽ làm lệch luồng hàng và báo cáo điều phối.'),
                    self::knowledge(array('so luong', 'ma dinh danh', 'hsd'), 'Kiểm tra số lượng, HSD/mã định danh nếu sản phẩm tracking trước khi lưu phiếu.'),
                ),
                array('sourceSections' => array('4.5 Mua bán nội bộ'))
            ),

            'transfer_export_list' => self::guide(
                'Danh sách phiếu bán nội bộ',
                'Danh sách phiếu bán nội bộ dùng để tìm, lọc, mở chi tiết và theo dõi trạng thái hàng đã xuất sang điểm nhận.',
                array('Tìm phiếu bán nội bộ thế nào?', 'Trạng thái phiếu nghĩa là gì?', 'Mở chi tiết ở đâu?'),
                array_merge($generic_steps, array(
                    self::step('form, input[type="search"], select', 'Tìm/lọc phiếu', 'Lọc theo thời gian, shop nhận, trạng thái hoặc mã phiếu để tìm đúng chứng từ.', 'bottom', 'center'),
                    self::step('table, .table-responsive', 'Bảng phiếu bán nội bộ', 'Kiểm tra mã phiếu, nơi nhận, số lượng, trạng thái và mở chi tiết.', 'top', 'center'),
                    self::step('a[href*="transfer-export-add"], .btn-primary', 'Tạo phiếu mới', 'Tạo bán nội bộ mới khi cần cấp hàng hoặc điều phối hàng sang điểm khác.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('danh sach ban noi bo', 'phieu ban noi bo'), 'Dùng danh sách bán nội bộ để theo dõi các phiếu đã xuất từ nơi gửi sang nơi nhận và mở chi tiết để đối soát.'),
                    self::knowledge(array('trang thai', 'cho nhan'), 'Trạng thái cho biết phiếu đã được nơi nhận xử lý hay còn chờ mua/nhập nội bộ.'),
                )),
                array('sourceSections' => array('4.5 Mua bán nội bộ'))
            ),

            'transfer_pending_import' => self::guide(
                'Phiếu chờ mua từ shop bán',
                'Màn hình này liệt kê các phiếu bán nội bộ đang chờ bên nhận tạo hoặc hoàn tất phiếu mua nội bộ.',
                array('Phiếu chờ mua là gì?', 'Nhận hàng nội bộ ở đâu?', 'Chênh lệch xử lý sao?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Chờ mua từ shop bán', 'Đây là hàng đã được bên gửi xuất và đang chờ bên nhận xử lý.', 'bottom', 'start'),
                    self::step('table, .table-responsive', 'Danh sách chờ nhận/mua', 'Kiểm tra nơi gửi, mã phiếu gốc, sản phẩm, số lượng và trạng thái chờ xử lý.', 'top', 'center'),
                    self::step('.btn-primary, a[href*="transfer-import-add"], button[data-action]', 'Tạo mua nội bộ', 'Mở luồng mua/nhận nội bộ từ phiếu đang chờ và kiểm tra số lượng thực nhận.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('cho mua', 'pending import', 'shop ban'), 'Phiếu chờ mua là phiếu bán nội bộ đã xuất từ nơi gửi, đang chờ nơi nhận xác nhận mua/nhập nội bộ.'),
                    self::knowledge(array('chenh lech', 'nhan thuc te'), 'Nếu nhận thực tế lệch so với xuất, cần ghi nhận chênh lệch theo modal xác nhận trước khi hoàn tất.'),
                )),
                array('sourceSections' => array('4.5 Mua bán nội bộ'))
            ),

            'transfer_import' => self::guide(
                'Tạo phiếu mua nội bộ',
                'Phiếu mua nội bộ là bước nơi nhận xác nhận hàng từ phiếu bán nội bộ hoặc tạo luồng nhận hàng điều phối.',
                array('Mua nội bộ bắt đầu từ đâu?', 'Nhận từ phiếu bán thế nào?', 'Kiểm tra chênh lệch ra sao?'),
                array_merge($generic_steps, array(
                    self::step('form, .card', 'Thông tin nhận hàng', 'Chọn phiếu nguồn/nơi gửi và kiểm tra thông tin chứng từ.', 'top', 'center'),
                    self::step('table, .product-list, .transfer-items', 'Sản phẩm nhận', 'Kiểm tra số lượng xuất, số lượng nhận thực tế, HSD/mã định danh và ghi chú lệch nếu có.', 'top', 'center'),
                    self::step('#transferMismatchConfirmModal, .tgs-row-mismatch', 'Xác nhận chênh lệch', 'Nếu số lượng nhận khác số xuất, đọc kỹ cảnh báo và xác nhận lý do trước khi lưu.', 'left', 'center'),
                    self::step('button[type="submit"], .btn-primary, .btn-success', 'Hoàn tất mua nội bộ', 'Chỉ lưu khi đã đối chiếu sản phẩm, số lượng và chênh lệch với bên gửi.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('mua noi bo', 'nhan hang'), 'Mua nội bộ là bước nơi nhận xác nhận hàng từ nơi gửi để hoàn tất luồng điều phối.'),
                    self::knowledge(array('chenh lech', 'mismatch'), 'Chênh lệch xảy ra khi số lượng nhận thực tế khác số xuất. Cần ghi nhận rõ để báo cáo và tồn kho không lệch.'),
                )),
                array('sourceSections' => array('4.5 Mua bán nội bộ'))
            ),

            'transfer_import_list' => self::guide(
                'Danh sách phiếu mua nội bộ',
                'Danh sách phiếu mua nội bộ dùng để theo dõi các phiếu đã nhận hàng từ shop/kho gửi và đối soát trạng thái nhập.',
                array('Xem phiếu mua nội bộ ở đâu?', 'Lọc theo nơi gửi thế nào?', 'Mở chi tiết để đối soát gì?'),
                array_merge($generic_steps, array(
                    self::step('form, input[type="search"], select', 'Tìm/lọc phiếu mua nội bộ', 'Lọc theo thời gian, nơi gửi, trạng thái hoặc mã phiếu.', 'bottom', 'center'),
                    self::step('table, .table-responsive', 'Bảng phiếu mua nội bộ', 'Kiểm tra mã phiếu, nơi gửi, số lượng nhận, trạng thái và chi tiết.', 'top', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('danh sach mua noi bo', 'phieu mua noi bo'), 'Dùng danh sách mua nội bộ để đối soát các phiếu đã nhận hàng và liên kết với phiếu bán nội bộ nguồn.'),
                    self::knowledge(array('noi gui', 'nguon hang'), 'Lọc theo nơi gửi giúp kiểm tra shop/kho nào đã cấp hàng và trạng thái nhận của từng phiếu.'),
                )),
                array('sourceSections' => array('4.5 Mua bán nội bộ'))
            ),

            'transfer_return' => self::guide(
                'Tạo phiếu trả hàng nội bộ',
                'Phiếu trả hàng nội bộ dùng khi shop/kho trả hàng dư, hàng bán chậm hoặc hàng cần gom về điểm khác.',
                array('Khi nào trả hàng nội bộ?', 'Chọn nơi nhận trả thế nào?', 'Kiểm tra sản phẩm trả ở đâu?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Trả hàng nội bộ', 'Xác nhận đúng nghiệp vụ trả hàng từ nơi hiện tại về nơi nhận trả.', 'bottom', 'start'),
                    self::step('form, .card', 'Thông tin trả hàng', 'Chọn nơi nhận trả, lý do trả, ghi chú và phiếu liên quan nếu có.', 'top', 'center'),
                    self::step('table, .product-list, .transfer-items', 'Sản phẩm trả', 'Thêm sản phẩm, số lượng, mã định danh/HSD nếu có và kiểm tra lý do trả.', 'top', 'center'),
                    self::step('button[type="submit"], .btn-primary, .btn-success', 'Lưu phiếu trả', 'Lưu sau khi xác nhận nơi nhận, sản phẩm và số lượng trả thực tế.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('tra hang noi bo', 'hang du', 'ban cham'), 'Trả hàng nội bộ dùng khi shop dư hàng, hàng bán chậm hoặc cần gom hàng về kho/điểm khác để điều phối lại.'),
                    self::knowledge(array('noi nhan tra', 'kho nhan'), 'Chọn đúng nơi nhận trả để luồng nhận trả và báo cáo nội bộ khớp.'),
                )),
                array('sourceSections' => array('4.6 Trả hàng nội bộ'))
            ),

            'transfer_return_list' => self::guide(
                'Danh sách phiếu trả nội bộ',
                'Danh sách phiếu trả nội bộ theo dõi các yêu cầu trả hàng đã tạo và trạng thái bên nhận xử lý.',
                array('Theo dõi phiếu trả ở đâu?', 'Phiếu trả chờ nhận là gì?', 'Mở chi tiết thế nào?'),
                array_merge($generic_steps, array(
                    self::step('form, input[type="search"], select', 'Tìm/lọc phiếu trả', 'Lọc theo thời gian, nơi nhận trả, trạng thái hoặc mã phiếu.', 'bottom', 'center'),
                    self::step('table, .table-responsive', 'Bảng phiếu trả nội bộ', 'Kiểm tra mã phiếu, nơi nhận trả, số lượng, trạng thái và mở chi tiết.', 'top', 'center'),
                    self::step('a[href*="transfer-return-add"], .btn-primary', 'Tạo phiếu trả mới', 'Tạo khi cần gom hàng dư hoặc trả hàng từ shop về kho/điểm khác.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('danh sach tra noi bo', 'phieu tra noi bo'), 'Danh sách trả nội bộ giúp theo dõi phiếu đã tạo và biết phiếu nào đang chờ bên nhận xác nhận.'),
                )),
                array('sourceSections' => array('4.6 Trả hàng nội bộ'))
            ),

            'transfer_pending_return' => self::guide(
                'Chờ nhận từ shop trả',
                'Màn hình này liệt kê các phiếu trả nội bộ đang chờ bên nhận xác nhận hàng trả về.',
                array('Chờ nhận trả là gì?', 'Nhận trả nội bộ ở đâu?', 'Có lệch số lượng thì sao?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Chờ nhận trả', 'Đây là các phiếu trả đã tạo và đang chờ nơi nhận xử lý.', 'bottom', 'start'),
                    self::step('table, .table-responsive', 'Danh sách chờ nhận trả', 'Kiểm tra nơi trả, nơi nhận, sản phẩm, số lượng và trạng thái.', 'top', 'center'),
                    self::step('.btn-primary, a[href*="transfer-return-receive-add"], button[data-action]', 'Tạo nhận trả', 'Mở luồng nhận trả để xác nhận hàng thực tế về kho/điểm nhận.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('cho nhan tra', 'pending return'), 'Chờ nhận trả là các phiếu trả nội bộ cần nơi nhận xác nhận số lượng thực nhận.'),
                    self::knowledge(array('lech so luong', 'chenh lech'), 'Nếu số lượng nhận trả khác số trả, cần ghi nhận chênh lệch để đối soát tồn kho.'),
                )),
                array('sourceSections' => array('4.6 Trả hàng nội bộ'))
            ),

            'transfer_return_receive' => self::guide(
                'Nhận trả nội bộ',
                'Nhận trả nội bộ là bước nơi nhận xác nhận hàng được shop/kho khác trả về.',
                array('Nhận trả bắt đầu từ đâu?', 'Kiểm tra hàng thực nhận thế nào?', 'Hoàn tất nhận trả ra sao?'),
                array_merge($generic_steps, array(
                    self::step('form, .card', 'Thông tin nhận trả', 'Chọn phiếu trả nguồn/nơi trả và kiểm tra thông tin chứng từ.', 'top', 'center'),
                    self::step('table, .product-list, .transfer-items', 'Sản phẩm nhận trả', 'Đối chiếu số lượng trả và số lượng nhận thực tế, mã định danh/HSD nếu có.', 'top', 'center'),
                    self::step('#transferMismatchConfirmModal, .tgs-row-mismatch', 'Xác nhận chênh lệch', 'Nếu số nhận trả khác số trả, xác nhận chênh lệch trước khi hoàn tất.', 'left', 'center'),
                    self::step('button[type="submit"], .btn-primary, .btn-success', 'Hoàn tất nhận trả', 'Lưu sau khi đã kiểm tra sản phẩm, số lượng và lý do chênh lệch nếu có.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('nhan tra noi bo', 'hang tra ve'), 'Nhận trả nội bộ giúp nơi nhận xác nhận hàng trả về để tồn kho và báo cáo điều phối được cập nhật đúng.'),
                    self::knowledge(array('so luong thuc nhan', 'chenh lech'), 'Luôn đối chiếu số lượng thực nhận với phiếu trả nguồn, đặc biệt với sản phẩm tracking/HSD.'),
                )),
                array('sourceSections' => array('4.6 Trả hàng nội bộ'))
            ),

            'transfer_return_receive_list' => self::guide(
                'Danh sách phiếu nhận trả nội bộ',
                'Danh sách nhận trả nội bộ dùng để theo dõi các phiếu đã xác nhận hàng trả về và đối soát với phiếu trả nguồn.',
                array('Xem phiếu nhận trả ở đâu?', 'Đối soát với phiếu trả thế nào?', 'Lọc theo nơi trả ra sao?'),
                array_merge($generic_steps, array(
                    self::step('form, input[type="search"], select', 'Tìm/lọc nhận trả', 'Lọc theo thời gian, nơi trả, nơi nhận, trạng thái hoặc mã phiếu.', 'bottom', 'center'),
                    self::step('table, .table-responsive', 'Bảng phiếu nhận trả', 'Kiểm tra mã phiếu, nơi trả, số lượng nhận, trạng thái và mở chi tiết.', 'top', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('danh sach nhan tra', 'phieu nhan tra'), 'Danh sách nhận trả giúp đối soát hàng đã nhận lại với phiếu trả nguồn và phát hiện chênh lệch nếu có.'),
                )),
                array('sourceSections' => array('4.6 Trả hàng nội bộ'))
            ),

            'transfer_detail' => self::guide(
                'Chi tiết phiếu nội bộ',
                'Trang chi tiết phiếu nội bộ dùng để đối soát nơi gửi/nhận, sản phẩm, số lượng, trạng thái, lịch sử và chênh lệch nếu có.',
                array('Chi tiết phiếu nội bộ xem gì?', 'Đối soát số lượng ở đâu?', 'Trạng thái phiếu ảnh hưởng gì?'),
                array_merge($generic_steps, array(
                    self::step('h4, .card-title', 'Chi tiết phiếu', 'Kiểm tra mã phiếu, loại nghiệp vụ, nơi gửi, nơi nhận và trạng thái hiện tại.', 'bottom', 'start'),
                    self::step('.card, .ticket-info-card, form', 'Thông tin chứng từ', 'Đọc thông tin nơi gửi/nhận, ghi chú, ngày tạo và liên kết phiếu nguồn nếu có.', 'bottom', 'center'),
                    self::step('table, .product-list, .transfer-items', 'Sản phẩm trong phiếu', 'Đối chiếu từng SKU, số lượng xuất/trả/nhận, HSD/mã định danh và chênh lệch.', 'top', 'center'),
                    self::step('.timeline, .status, .badge, .nav-tabs', 'Trạng thái và lịch sử', 'Theo dõi tiến độ xử lý, lịch sử cập nhật và các trạng thái liên quan.', 'left', 'center'),
                )),
                array_merge($generic_knowledge, array(
                    self::knowledge(array('chi tiet phieu noi bo', 'doi soat'), 'Mở chi tiết phiếu để đối soát nơi gửi/nhận, số lượng, sản phẩm, trạng thái và chênh lệch phát sinh.'),
                    self::knowledge(array('trang thai', 'lich su'), 'Trạng thái cho biết phiếu đang chờ, đã xuất, đã nhận, đã trả hoặc đã hoàn tất. Lịch sử giúp truy nguyên thao tác.'),
                )),
                array('sourceSections' => array('4.5 Mua bán nội bộ', '4.6 Trả hàng nội bộ'))
            ),

            'guide_settings' => self::guide(
                'Cấu hình AI hướng dẫn',
                'Trang này cho biết plugin hướng dẫn đang hoạt động, coverage theo view và các hook để mở rộng tour hoặc nối AI thật.',
                array('Reset hướng dẫn thế nào?', 'Thêm tour mới ở đâu?', 'Nối AI thật bằng cách nào?'),
                array(
                    self::step('.tgs-ai-guides-admin', 'Trung tâm hướng dẫn', 'Trang này giúp đội triển khai kiểm tra coverage, reset trạng thái và xem cách mở rộng.', 'bottom', 'center'),
                    self::step('[data-tgs-ai-reset-site]', 'Reset hướng dẫn đã xem', 'Dùng để cho tài khoản hiện tại xem lại toàn bộ tour trên website chi nhánh này.', 'left', 'center'),
                    self::step('.tgs-ai-guides-admin table', 'Coverage hiện có', 'Bảng liệt kê guide key, view được map và số bước/câu hỏi nhanh của từng ngữ cảnh.', 'top', 'center'),
                ),
                array(
                    self::knowledge(array('reset', 'xem lai tat ca'), 'Trên trang AI hướng dẫn, bấm reset để xóa lịch sử đã xem của tài khoản hiện tại trên website chi nhánh này.'),
                    self::knowledge(array('them tour', 'mo rong'), 'Lập trình viên có thể mở rộng tour qua filter `tgs_ai_guides_tour` hoặc map view qua filter `tgs_ai_guides_group_for_view`.'),
                    self::knowledge(array('ai that', 'ket noi ai'), 'Để nối AI thật, hook vào filter `tgs_ai_guides_ai_answer` và trả về mảng `answer`, `matched`, `quickQuestions`, có thể dùng `tour.context` làm prompt context.'),
                ),
                array('sourceSections' => array('16. Bảng tóm tắt chức năng'))
            ),

            'generic' => self::guide(
                'Màn hình TGS',
                'Trang này thuộc hệ sinh thái TGS. Tour chung sẽ hướng dẫn thanh điều hướng, tìm kiếm, vùng làm việc và AI hỗ trợ.',
                array('Trang này dùng để làm gì?', 'Tôi cần thao tác bắt đầu ở đâu?', 'Làm sao xem lại hướng dẫn?'),
                $generic_steps,
                $generic_knowledge,
                array('sourceSections' => array('16. Bảng tóm tắt chức năng'))
            ),
        );
    }

    private static function guide($title, $summary, $quick_questions, $steps, $knowledge, $context = array())
    {
        return array(
            'title' => $title,
            'summary' => $summary,
            'quick_questions' => $quick_questions,
            'steps' => $steps,
            'knowledge' => $knowledge,
            'context' => $context,
        );
    }

    private static function step($element, $title, $description, $side = 'bottom', $align = 'center', $meta = array())
    {
        return array_merge(array(
            'element' => $element,
            'title' => $title,
            'description' => $description,
            'side' => $side,
            'align' => $align,
            'scope' => 'page',
        ), $meta);
    }

    private static function knowledge($terms, $answer)
    {
        return array(
            'terms' => $terms,
            'answer' => $answer,
        );
    }

    private static function normalize($value)
    {
        $value = is_string($value) ? $value : '';
        $value = function_exists('remove_accents') ? remove_accents($value) : $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s_-]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }
}
