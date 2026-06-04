<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TGS_AI_Guides_Registry
{
    const VERSION = '2026-06-04-01';

    public static function get_tour($view)
    {
        $view = sanitize_key($view ?: 'dashboard');
        $definitions = self::definitions();
        $group = self::resolve_group($view);
        $page = isset($definitions[$group]) ? $definitions[$group] : $definitions['generic'];
        $base = $definitions['base'];

        $quick_questions = array_values(array_unique(array_merge(
            $page['quick_questions'],
            array('Hướng dẫn lại trang này', 'Trang này dùng để làm gì?')
        )));

        $tour = array(
            'id' => 'tgs-shop-' . $view,
            'version' => self::VERSION,
            'view' => $view,
            'group' => $group,
            'title' => $page['title'],
            'summary' => $page['summary'],
            'quickQuestions' => $quick_questions,
            'steps' => array_values(array_merge($base['steps'], $page['steps'])),
            'knowledge' => array_values(array_merge($base['knowledge'], $page['knowledge'])),
        );

        return apply_filters('tgs_ai_guides_tour', $tour, $view, $group);
    }

    public static function answer_question($view, $question)
    {
        $tour = self::get_tour($view);
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
            );
        }

        return array(
            'answer' => 'Mình đang dựa trên hướng dẫn của màn hình "' . $tour['title'] . '". ' . $tour['summary'] . ' Bạn có thể hỏi cụ thể hơn như: "' . implode('", "', array_slice($tour['quickQuestions'], 0, 3)) . '".',
            'matched' => false,
            'quickQuestions' => $tour['quickQuestions'],
        );
    }

    public static function all_groups()
    {
        $definitions = self::definitions();
        unset($definitions['base']);

        return $definitions;
    }

    private static function resolve_group($view)
    {
        $map = array(
            'dashboard' => 'entry_dashboard',
            'dashboard-info' => 'entry_dashboard',
            'dashboard-global' => 'dashboard_global',
            'products-v2' => 'product_list',
            'milk-under24m' => 'product_list',
            'categories-v2' => 'category_list',
            'inventory' => 'inventory_report',
            'analytics-shop-inventory' => 'inventory_report',
            'inventory-manual-v2' => 'inventory_report',
            'warehouse-zone' => 'warehouse_zone',
            'lot-tracking' => 'lot_tracking',
            'contacts' => 'partner_list',
            'contact-detail' => 'partner_list',
            'suppliers-global' => 'partner_list',
            'supplier-global-detail' => 'partner_list',
            'org-chart-view' => 'partner_list',
            'ticket-relationships' => 'ticket_list',
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
            'admin-settings' => 'settings',
            'label-print-settings' => 'settings',
            'brand-settings' => 'settings',
            'api' => 'api',
            'api-detail' => 'api',
            'ai-guides' => 'guide_settings',
        );

        if (isset($map[$view])) {
            return apply_filters('tgs_ai_guides_group_for_view', $map[$view], $view);
        }

        if (strpos($view, 'product') === 0 || strpos($view, 'categories') === 0 || strpos($view, 'category') === 0) {
            return apply_filters('tgs_ai_guides_group_for_view', 'product_list', $view);
        }

        if (strpos($view, 'ticket-') === 0) {
            return apply_filters('tgs_ai_guides_group_for_view', 'ticket_list', $view);
        }

        return apply_filters('tgs_ai_guides_group_for_view', 'generic', $view);
    }

    private static function definitions()
    {
        return array(
            'base' => array(
                'steps' => array(
                    self::step('#tgs-mega-nav', 'Thanh điều hướng chính', 'Đây là nơi nhân viên chuyển nhanh giữa Dashboard, Sản phẩm, Đối tác, Giao dịch, Kho hàng, Báo cáo, Công cụ và Hệ thống.', 'bottom', 'center'),
                    self::step('#globalSearchWrapper', 'Tìm kiếm toàn hệ thống', 'Dùng ô này để tìm nhanh barcode, sản phẩm hoặc phiếu. Nhân viên có thể bấm Ctrl+K nếu đang thao tác bằng bàn phím.', 'bottom', 'center'),
                    self::step('.tgs-nav-items', 'Các nhóm nghiệp vụ', 'Mỗi nhóm menu bám theo quy trình vận hành thực tế: hàng hóa, đối tác, chứng từ, kho và báo cáo.', 'bottom', 'center'),
                    self::step('.container-xxl.flex-grow-1.container-p-y', 'Vùng làm việc của trang', 'Toàn bộ bảng dữ liệu, form nhập liệu và báo cáo của màn hình hiện tại nằm trong khu vực này.', 'top', 'center'),
                    self::step('.tgs-ai-guide-launcher', 'AI hỗ trợ hướng dẫn', 'Bấm nút này bất cứ lúc nào để xem lại tour hoặc hỏi nhanh về cách dùng màn hình hiện tại.', 'left', 'end'),
                ),
                'knowledge' => array(
                    self::knowledge(array('tim kiem', 'search', 'ctrl k', 'barcode'), 'Dùng thanh tìm kiếm trên đầu trang để tra barcode, sản phẩm hoặc phiếu. Khi cần thao tác nhanh, bấm Ctrl+K rồi nhập từ khóa.'),
                    self::knowledge(array('huong dan lai', 'xem lai', 'driver', 'tour'), 'Bấm nút "AI hỗ trợ" ở góc dưới bên phải, sau đó chọn "Hướng dẫn lại" để chạy lại tour của màn hình hiện tại.'),
                    self::knowledge(array('bo qua', 'khong hien nua', 'an huong dan'), 'Bấm "Bỏ qua trang này" hoặc nút đóng trong tour. Hệ thống sẽ lưu trạng thái theo tài khoản, website chi nhánh và view hiện tại.'),
                    self::knowledge(array('ai that', 'ket noi ai', 'chatgpt', 'mo rong'), 'Hiện tại khung hỗ trợ trả lời theo bộ hướng dẫn nội bộ của từng trang. Plugin đã mở sẵn filter `tgs_ai_guides_ai_answer` để sau này nối sang AI thật cho toàn website.'),
                ),
            ),
            'entry_dashboard' => array(
                'title' => 'Trang chọn khu vực làm việc',
                'summary' => 'Trang này giúp nhân viên chọn vào POS bán hàng hoặc khu vực quản trị kho.',
                'quick_questions' => array('Khi nào vào POS?', 'Khi nào vào quản trị kho?', 'Tôi muốn xem dashboard kho'),
                'steps' => array(
                    self::step('.tgs-entry-hero', 'Chọn đúng khu vực làm việc', 'Màn hình đầu tiên phân tách thao tác bán hàng tại quầy và thao tác quản trị kho.', 'bottom', 'center'),
                    self::step('.tgs-entry-card-pos', 'POS bán hàng', 'Dành cho nhân viên bán hàng tại quầy, ưu tiên tạo đơn nhanh và thanh toán.', 'right', 'center'),
                    self::step('.tgs-entry-card-admin', 'Quản trị kho', 'Dành cho kho, kế toán mua, quản lý cửa hàng và admin khi cần xử lý hàng hóa, phiếu và báo cáo.', 'left', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('pos', 'ban hang', 'tai quay'), 'Vào POS khi nhân viên đang bán hàng trực tiếp tại quầy và cần tạo đơn nhanh cho khách.'),
                    self::knowledge(array('quan tri', 'kho', 'dashboard'), 'Vào quản trị kho khi cần xem tồn, tạo phiếu, quản lý sản phẩm, nhà cung cấp hoặc kiểm tra báo cáo.'),
                ),
            ),
            'dashboard_global' => array(
                'title' => 'Dashboard quản trị kho',
                'summary' => 'Trang này tóm tắt số liệu sản phẩm, tồn kho, đối tác và giao dịch gần đây của website chi nhánh.',
                'quick_questions' => array('Các thẻ số liệu nghĩa là gì?', 'Làm mới dashboard thế nào?', 'Tạo phiếu nhanh ở đâu?'),
                'steps' => array(
                    self::step('.tgs-dash-stats', 'Các chỉ số nhanh', 'Theo dõi số lượng sản phẩm, tồn kho, nhà cung cấp, khách hàng và tình trạng phiếu trong tháng.', 'bottom', 'center'),
                    self::step('#d-refresh', 'Làm mới dữ liệu', 'Bấm nút này khi cần tải lại số liệu mới nhất sau khi vừa nhập phiếu hoặc đồng bộ dữ liệu.', 'left', 'center'),
                    self::step('#d-recent', 'Giao dịch gần đây', 'Khu vực này giúp quản lý kiểm tra các phiếu vừa phát sinh và trạng thái xử lý.', 'top', 'center'),
                    self::step('.tgs-quick-link', 'Truy cập nhanh', 'Các lối tắt thường dùng để tạo phiếu nhập, phiếu xuất, thêm sản phẩm hoặc mở danh sách sản phẩm.', 'left', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('lam moi', 'refresh', 'cap nhat'), 'Bấm "Làm mới" ở góc phải phần tiêu đề dashboard để tải lại số liệu mới nhất.'),
                    self::knowledge(array('giao dich gan day', 'phieu gan day'), 'Bảng giao dịch gần đây cho biết các phiếu mới phát sinh, loại phiếu, đối tác, số lượng và trạng thái.'),
                    self::knowledge(array('truy cap nhanh', 'tao phieu nhanh'), 'Khu "Truy cập nhanh" đưa nhân viên đến các thao tác hay dùng như tạo phiếu nhập, phiếu xuất, thêm sản phẩm hoặc mở danh sách sản phẩm.'),
                ),
            ),
            'product_list' => array(
                'title' => 'Quản lý sản phẩm',
                'summary' => 'Trang sản phẩm dùng để tra cứu hàng hóa, lọc theo danh mục/NCC, import Excel, theo dõi HSD và xử lý mã định danh.',
                'quick_questions' => array('Nhập sản phẩm từ Excel ở đâu?', 'Lọc theo danh mục thế nào?', 'Theo dõi HSD là gì?'),
                'steps' => array(
                    self::step('.products-v2-page h4, .categories-v2-page h4', 'Tiêu đề nghiệp vụ', 'Xác nhận bạn đang ở đúng màn hình sản phẩm hoặc danh mục trước khi thao tác dữ liệu hàng hóa.', 'bottom', 'start'),
                    self::step('.products-v2-page .dropdown, .categories-v2-page .btn', 'Nhóm thao tác dữ liệu', 'Các nút nhập/xuất Excel, cập nhật giá, cập nhật barcode và thao tác hàng loạt nằm ở khu vực đầu trang.', 'bottom', 'end'),
                    self::step('.tgs-stats-row', 'Thống kê nhanh', 'Các thẻ này cho biết tổng sản phẩm, sản phẩm hoạt động, tạm dừng và tổng tồn. Có thể dùng để lọc nhanh.', 'bottom', 'center'),
                    self::step('#categorySidebar', 'Lọc theo danh mục/NCC', 'Dùng cây danh mục và bộ lọc nhà cung cấp để thu hẹp danh sách sản phẩm cần xử lý.', 'right', 'start'),
                    self::step('#productsSmartSearchBlock', 'Tìm sản phẩm trong trang', 'Ô tìm kiếm thông minh giúp lọc theo tên, mã, barcode hoặc thông tin liên quan trong danh sách sản phẩm.', 'bottom', 'center'),
                    self::step('#productsV2Table, #categoriesV2Table', 'Bảng dữ liệu chính', 'Bảng này là nơi kiểm tra thông tin và mở các thao tác sửa, trạng thái, giá hoặc chi tiết sản phẩm/danh mục.', 'top', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('excel', 'import', 'nhap san pham', 'nhap tu excel'), 'Trên trang sản phẩm, mở nhóm "Nhập / Xuất" ở đầu trang rồi chọn chức năng import phù hợp: nhập sản phẩm, danh mục, giá hoặc số lượng.'),
                    self::knowledge(array('danh muc', 'cay danh muc', 'loc danh muc'), 'Dùng khung "Lọc theo danh mục" bên trái. Khi chọn một node, bảng bên phải chỉ còn sản phẩm hoặc danh mục thuộc nhánh đó.'),
                    self::knowledge(array('nha cung cap', 'ncc', 'supplier'), 'Nếu trang có danh sách NCC ở sidebar, chọn NCC để xem các sản phẩm đang gắn với nhà cung cấp đó hoặc nhóm chưa có NCC.'),
                    self::knowledge(array('hsd', 'han su dung', 'tracking'), 'Theo dõi HSD dùng cho sản phẩm cần quản lý hạn sử dụng hoặc mã định danh. Bật/tắt theo dõi phải làm cẩn thận vì ảnh hưởng đến nghiệp vụ nhập, bán và tồn kho.'),
                    self::knowledge(array('barcode', 'ma dinh danh', 'xoa ma'), 'Các thao tác barcode/mã định danh nằm trong nhóm theo dõi sản phẩm hoặc nhóm thao tác khác. Chỉ dùng khi đã hiểu rõ dữ liệu cần xử lý.'),
                ),
            ),
            'category_list' => array(
                'title' => 'Quản lý danh mục',
                'summary' => 'Trang danh mục dùng để chuẩn hóa nhóm hàng, import/export danh mục và thống nhất cây ngành hàng.',
                'quick_questions' => array('Thêm danh mục ở đâu?', 'Thống nhất danh mục là gì?', 'Lọc cây danh mục thế nào?'),
                'steps' => array(
                    self::step('.categories-v2-page h4', 'Danh mục sản phẩm', 'Màn hình này quản lý cây nhóm hàng dùng chung cho lọc sản phẩm, báo cáo và nhập liệu.', 'bottom', 'start'),
                    self::step('#categorySidebar', 'Cây danh mục', 'Chọn node để xem danh mục con hoặc tìm nhanh nhóm hàng trong cây.', 'right', 'start'),
                    self::step('#btnUnifyAll', 'Thống nhất danh mục', 'Chức năng chuẩn hóa danh mục, nên dùng khi cần đồng bộ lại cấu trúc nhóm hàng.', 'left', 'center'),
                    self::step('#categoriesV2Table', 'Bảng danh mục', 'Kiểm tra mã nhóm, tên nhóm, đường dẫn và trạng thái hoạt động của từng danh mục.', 'top', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('them danh muc', 'tao danh muc'), 'Bấm "Thêm danh mục" ở đầu trang để tạo nhóm hàng mới.'),
                    self::knowledge(array('thong nhat', 'chuan hoa'), 'Nút "Thống nhất danh mục" dùng khi cần chuẩn hóa lại dữ liệu danh mục. Nên kiểm tra kỹ trước khi chạy trên dữ liệu thật.'),
                    self::knowledge(array('duong dan', 'path'), 'Cột đường dẫn cho biết vị trí của danh mục trong cây nhóm hàng, giúp tránh nhầm nhóm cha/con.'),
                ),
            ),
            'ticket_list' => array(
                'title' => 'Danh sách phiếu giao dịch',
                'summary' => 'Trang danh sách phiếu dùng để lọc, tìm, kiểm tra trạng thái và mở chi tiết các chứng từ mua, bán, hoàn, hủy, nhập, xuất, thu, chi.',
                'quick_questions' => array('Lọc phiếu theo ngày thế nào?', 'Thùng rác dùng để làm gì?', 'Làm mới bảng phiếu ở đâu?'),
                'steps' => array(
                    self::step('.app-ticket-list h4, .app-ticket-list .card-title', 'Loại phiếu hiện tại', 'Tiêu đề cho biết bạn đang xem danh sách phiếu mua, bán, nhập, xuất, thu, chi hoặc nhóm phiếu khác.', 'bottom', 'start'),
                    self::step('#trashToggleGroup', 'Trạng thái danh sách', 'Chuyển giữa phiếu đang hoạt động và thùng rác để kiểm tra chứng từ đã xóa tạm.', 'bottom', 'center'),
                    self::step('#dateQuickFilter', 'Lọc nhanh theo ngày', 'Chọn hôm nay, tuần này, tháng này hoặc tất cả để thu hẹp danh sách phiếu.', 'bottom', 'center'),
                    self::step('#filterDateFrom', 'Khoảng ngày tùy chọn', 'Dùng ngày bắt đầu/kết thúc khi cần đối soát một giai đoạn cụ thể.', 'top', 'center'),
                    self::step('#ticketTable', 'Bảng phiếu', 'Bấm vào dòng hoặc nút thao tác trong bảng để mở chi tiết, kiểm tra trạng thái và xử lý tiếp.', 'top', 'center'),
                    self::step('#btnRefreshTable', 'Làm mới bảng', 'Dùng nút này sau khi tạo, duyệt hoặc cập nhật phiếu ở tab khác.', 'left', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('loc ngay', 'hom nay', 'thang nay', 'tu ngay', 'den ngay'), 'Dùng cụm "Lọc theo ngày" để chọn nhanh hôm nay/tuần/tháng hoặc nhập khoảng ngày rồi bấm "Lọc".'),
                    self::knowledge(array('thung rac', 'xoa tam'), 'Thùng rác hiển thị các phiếu đã xóa tạm. Đây là nơi kiểm tra trước khi khôi phục hoặc xử lý dữ liệu đã loại khỏi danh sách chính.'),
                    self::knowledge(array('lam moi bang', 'refresh'), 'Bấm nút biểu tượng làm mới ở góc phải card danh sách để tải lại dữ liệu phiếu.'),
                    self::knowledge(array('kho duyet', 'cho duyet', 'duyet kho'), 'Nếu màn hình có bộ lọc "Kho duyệt", dùng nó để xem phiếu chờ duyệt, đã duyệt hoặc từ chối.'),
                ),
            ),
            'ticket_create' => array(
                'title' => 'Tạo phiếu nghiệp vụ',
                'summary' => 'Trang tạo phiếu gồm thông tin chứng từ, đối tượng liên quan, phân kho, danh sách sản phẩm, import Excel/AI nhận diện và thanh lưu phiếu.',
                'quick_questions' => array('Tạo phiếu gồm những bước nào?', 'Chọn nhà cung cấp/khách hàng ở đâu?', 'Thêm sản phẩm vào phiếu thế nào?'),
                'steps' => array(
                    self::step('#ticketInfoCard', 'Thông tin phiếu', 'Kiểm tra mã phiếu, ngày phiếu, hạn thanh toán, mã chứng từ ngoài hệ thống, phân kho và ghi chú.', 'bottom', 'start'),
                    self::step('#ticketPersonCard', 'Đối tượng liên quan', 'Chọn khách hàng, nhà cung cấp hoặc chi nhánh tùy loại phiếu. Đây là dữ liệu quan trọng để đối soát.', 'bottom', 'start'),
                    self::step('#ticket_warehouse_zone', 'Phân kho', 'Nếu nghiệp vụ cần tách khu hàng, chọn phân kho phù hợp để tồn kho và báo cáo rõ ràng hơn.', 'bottom', 'center'),
                    self::step('#ticketProductListCard', 'Danh sách sản phẩm', 'Thêm hàng hóa vào phiếu, nhập số lượng, giá, VAT, chiết khấu, HSD, mã lô hoặc ghi chú theo từng dòng.', 'top', 'center'),
                    self::step('#btnTicketExcelImportMain', 'Nhập sản phẩm từ Excel', 'Dùng khi kế toán mua hoặc kho có file danh sách sản phẩm, giúp giảm nhập tay từng dòng.', 'left', 'center'),
                    self::step('#btnTicketAIImportMain', 'AI nhận diện chứng từ', 'Mở hướng xử lý ảnh/file chứng từ để tự nhận diện sản phẩm, phục vụ mở rộng quy trình AI sau này.', 'left', 'center'),
                    self::step('#btnTicketSubmit', 'Lưu phiếu', 'Sau khi kiểm tra đối tượng, hàng hóa và tổng tiền, bấm nút này để ghi nhận phiếu.', 'top', 'end'),
                ),
                'knowledge' => array(
                    self::knowledge(array('buoc tao phieu', 'quy trinh', 'tao phieu'), 'Quy trình cơ bản: kiểm tra thông tin phiếu, chọn đối tượng, chọn phân kho nếu cần, thêm sản phẩm, kiểm tra tổng tiền rồi bấm lưu phiếu.'),
                    self::knowledge(array('nha cung cap', 'khach hang', 'doi tuong', 'chon doi tuong'), 'Dùng khối "Thông tin đối tượng" để chọn nhà cung cấp, khách hàng hoặc chi nhánh. Không nên lưu phiếu khi đối tượng bắt buộc còn trống.'),
                    self::knowledge(array('them san pham', 'hang hoa', 'bang san pham'), 'Bấm "Thêm sản phẩm" trong khối danh sách sản phẩm, hoặc dùng "Nhập từ Excel" nếu có file sẵn.'),
                    self::knowledge(array('excel', 'nhap tu excel'), 'Bấm "Nhập từ Excel" trong khối danh sách sản phẩm để đưa nhiều dòng hàng vào phiếu nhanh hơn.'),
                    self::knowledge(array('ai nhan dien', 'anh', 'file chung tu'), 'Nút "AI nhận diện" dành cho luồng đọc ảnh/file chứng từ. Đây là điểm nối tự nhiên để mở rộng AI thực tế trong quy trình mua/bán.'),
                    self::knowledge(array('phan kho', 'kho chinh', 'kho khuyen mai'), 'Phân kho giúp tách hàng bán, hàng công cụ, hàng khuyến mại hoặc các khu quản lý riêng. Nếu không chắc, hỏi quản lý kho trước khi chọn.'),
                    self::knowledge(array('luu phieu', 'submit', 'hoan tat'), 'Trước khi lưu phiếu, kiểm tra đối tượng, danh sách sản phẩm, số lượng, giá, VAT/chiết khấu và tổng tiền ở thanh cuối trang.'),
                ),
            ),
            'transaction_create' => array(
                'title' => 'Tạo phiếu thu/chi',
                'summary' => 'Trang này chọn các phiếu còn công nợ, nhập số tiền thu/chi, hình thức thanh toán và ghi nhận giao dịch tiền.',
                'quick_questions' => array('Chọn phiếu cần thu/chi ở đâu?', 'Nhập số tiền thanh toán thế nào?', 'Duyệt giao dịch ở đâu?'),
                'steps' => array(
                    self::step('.app-transaction-create h4', 'Tạo giao dịch tiền', 'Xác nhận bạn đang tạo phiếu thu hoặc phiếu chi đúng nghiệp vụ.', 'bottom', 'start'),
                    self::step('#sourceTypeTabs', 'Lọc nguồn phiếu', 'Các tab giúp lọc nhóm phiếu đang chờ thu/chi theo nguồn hoặc loại nghiệp vụ.', 'bottom', 'center'),
                    self::step('#pendingTicketsTable', 'Danh sách phiếu chờ xử lý', 'Chọn phiếu còn công nợ để mở modal nhập số tiền thu/chi.', 'top', 'center'),
                    self::step('#paymentModal', 'Modal thanh toán', 'Khi chọn phiếu, nhập số tiền, hình thức thanh toán và ghi chú trước khi xác nhận.', 'left', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('thu tien', 'chi tien', 'thanh toan'), 'Chọn phiếu trong bảng chờ xử lý, nhập số tiền thu/chi trong modal, chọn hình thức thanh toán rồi xác nhận.'),
                    self::knowledge(array('cong no', 'debt', 'cho thu', 'cho chi'), 'Bảng chính liệt kê các phiếu còn số tiền cần thu hoặc cần chi. Dùng tab để lọc đúng nhóm.'),
                    self::knowledge(array('hinh thuc thanh toan', 'tien mat', 'chuyen khoan'), 'Trong modal thanh toán, chọn hình thức thanh toán như tiền mặt, chuyển khoản hoặc hình thức khác để đối soát chính xác.'),
                ),
            ),
            'inventory_report' => array(
                'title' => 'Báo cáo tồn kho',
                'summary' => 'Trang tồn kho cho biết tồn đầu kỳ, nhập trong kỳ, xuất trong kỳ, tồn cuối kỳ và chi tiết theo sản phẩm.',
                'quick_questions' => array('Chọn kỳ báo cáo thế nào?', 'Tồn đầu kỳ và tồn cuối kỳ nghĩa là gì?', 'Xuất báo cáo Excel ở đâu?'),
                'steps' => array(
                    self::step('.app-ecommerce-inventory h4', 'Báo cáo tồn kho', 'Dùng màn hình này để theo dõi biến động nhập xuất tồn theo kỳ.', 'bottom', 'start'),
                    self::step('.period-filter', 'Kỳ báo cáo', 'Chọn tháng, quý hoặc năm rồi bấm áp dụng để tải số liệu đúng kỳ.', 'bottom', 'center'),
                    self::step('.stat-card.opening', 'Tồn đầu kỳ', 'Số lượng và giá trị tồn trước khi phát sinh nhập/xuất trong kỳ đã chọn.', 'bottom', 'center'),
                    self::step('.stat-card.import', 'Nhập trong kỳ', 'Tổng hàng đi vào kho hoặc shop trong kỳ báo cáo.', 'bottom', 'center'),
                    self::step('.stat-card.export', 'Xuất trong kỳ', 'Tổng hàng đi ra khỏi kho hoặc shop trong kỳ báo cáo.', 'bottom', 'center'),
                    self::step('.stat-card.closing', 'Tồn cuối kỳ', 'Số tồn còn lại sau khi tính nhập và xuất trong kỳ.', 'bottom', 'center'),
                    self::step('#inventoryTable', 'Bảng tồn theo sản phẩm', 'Kiểm tra từng sản phẩm, số lượng, giá trị và mở chi tiết nếu cần đối soát.', 'top', 'center'),
                    self::step('#btnExportExcel', 'Xuất Excel', 'Tải báo cáo ra file để gửi quản lý, kế toán hoặc lưu hồ sơ đối chiếu.', 'left', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('ky bao cao', 'thang', 'quy', 'nam'), 'Chọn loại kỳ báo cáo, giá trị kỳ và năm trong khối "Kỳ báo cáo", sau đó bấm "Áp dụng".'),
                    self::knowledge(array('ton dau ky', 'ton cuoi ky'), 'Tồn đầu kỳ là số tồn trước kỳ báo cáo; tồn cuối kỳ là số còn lại sau nhập và xuất trong kỳ.'),
                    self::knowledge(array('nhap trong ky', 'xuat trong ky'), 'Nhập trong kỳ là hàng tăng; xuất trong kỳ là hàng giảm. Hai chỉ số này giải thích biến động tồn.'),
                    self::knowledge(array('excel', 'xuat bao cao', 'in bao cao'), 'Dùng nút "Xuất Excel" hoặc "In báo cáo" ở đầu trang để lấy báo cáo ra ngoài hệ thống.'),
                ),
            ),
            'warehouse_zone' => array(
                'title' => 'Quản lý phân kho',
                'summary' => 'Trang phân kho tạo các khu quản lý hàng như kho chính, hàng khuyến mại, công cụ hoặc hàng livestream.',
                'quick_questions' => array('Phân kho dùng để làm gì?', 'Độ ưu tiên là gì?', 'Thêm phân kho ở đâu?'),
                'steps' => array(
                    self::step('#btnAddZone', 'Thêm phân kho', 'Tạo mã phân kho mới để dùng khi lập phiếu hoặc tách khu hàng.', 'left', 'center'),
                    self::step('#warehouseZoneTable', 'Danh sách phân kho', 'Bảng hiển thị mã phân kho, tên gợi nhớ, độ ưu tiên và nút sửa/xóa.', 'top', 'center'),
                    self::step('#zoneModal', 'Form phân kho', 'Khi thêm hoặc sửa, nhập mã, tên gợi nhớ và độ ưu tiên rồi lưu.', 'left', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('phan kho', 'kho chinh', 'khu hang'), 'Phân kho giúp tách hàng hóa theo mục đích quản lý, ví dụ kho chính, hàng khuyến mại, hàng công cụ hoặc hàng livestream.'),
                    self::knowledge(array('do uu tien', 'sort order', 'mac dinh'), 'Độ ưu tiên càng cao thì phân kho càng được ưu tiên khi hệ thống cần chọn mặc định.'),
                    self::knowledge(array('them phan kho', 'tao phan kho'), 'Bấm "Thêm phân kho", nhập mã phân kho, tên gợi nhớ và độ ưu tiên rồi lưu.'),
                ),
            ),
            'partner_list' => array(
                'title' => 'Quản lý đối tác',
                'summary' => 'Trang đối tác quản lý khách hàng, nhà cung cấp và thông tin liên hệ phục vụ bán hàng, mua hàng và đối soát.',
                'quick_questions' => array('Thêm nhà cung cấp ở đâu?', 'Xuất danh sách đối tác thế nào?', 'Tìm khách hàng ở đâu?'),
                'steps' => array(
                    self::step('.app-user-list h4', 'Danh sách đối tác', 'Xác nhận nhóm dữ liệu đang xem: khách hàng, người dùng hoặc nhà cung cấp.', 'bottom', 'start'),
                    self::step('.app-user-list .d-flex.gap-2', 'Thao tác đầu trang', 'Các nút đồng bộ, nhập Excel, xuất Excel hoặc thêm mới nằm ở khu vực này.', 'bottom', 'end'),
                    self::step('#contactsTable, #suppliersTable', 'Bảng đối tác', 'Dùng bảng để tìm kiếm, kiểm tra trạng thái và mở chi tiết đối tác.', 'top', 'center'),
                    self::step('.nav-tabs', 'Tab trạng thái', 'Nếu có tab danh sách/thùng rác, dùng để chuyển giữa dữ liệu đang hoạt động và dữ liệu đã xóa tạm.', 'bottom', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('nha cung cap', 'supplier', 'them nha cung cap'), 'Ở trang nhà cung cấp, bấm "Thêm nhà cung cấp" để tạo mới hoặc mở chi tiết để sửa thông tin.'),
                    self::knowledge(array('khach hang', 'lien he', 'tim khach'), 'Ở trang khách hàng/người dùng, dùng ô tìm kiếm của bảng để tra theo tên, email hoặc số điện thoại.'),
                    self::knowledge(array('excel', 'xuat excel', 'nhap excel'), 'Các nút nhập/xuất Excel nằm ở khu vực đầu trang, dùng khi cần đồng bộ danh sách đối tác hàng loạt.'),
                ),
            ),
            'lot_tracking' => array(
                'title' => 'Tra cứu mã định danh',
                'summary' => 'Trang này dùng để quét hoặc nhập barcode, xem trạng thái sản phẩm, lịch sử phiếu liên quan và thao tác hoàn/hủy nhanh khi phù hợp.',
                'quick_questions' => array('Quét barcode ở đâu?', 'Phiếu liên quan nghĩa là gì?', 'Khi nào dùng hoàn/hủy nhanh?'),
                'steps' => array(
                    self::step('#guideSection', 'Hướng dẫn tra cứu', 'Màn hình này ưu tiên thao tác quét barcode cho nhân viên kho hoặc cửa hàng.', 'bottom', 'center'),
                    self::step('#lotBarcodeInput', 'Ô quét barcode', 'Đặt con trỏ vào đây rồi quét mã hoặc nhập barcode thủ công để tra cứu.', 'bottom', 'center'),
                    self::step('#resultSection', 'Kết quả tra cứu', 'Sau khi tìm thấy mã, khu vực này hiển thị thông tin sản phẩm, trạng thái và lịch sử.', 'top', 'center'),
                    self::step('#ledgersContainer', 'Phiếu liên quan', 'Danh sách các chứng từ đã tác động đến mã định danh này.', 'top', 'center'),
                    self::step('#quickDamageBtn, #quickReturnBtn', 'Thao tác nhanh', 'Khi điều kiện phù hợp, hệ thống hiện nút hủy hàng hoặc hoàn hàng để xử lý nhanh.', 'left', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('barcode', 'quet ma', 'ma dinh danh'), 'Đặt con trỏ ở ô barcode, quét mã trên sản phẩm hoặc nhập tay rồi chờ hệ thống trả kết quả.'),
                    self::knowledge(array('phieu lien quan', 'lich su'), 'Phiếu liên quan là lịch sử chứng từ đã làm thay đổi trạng thái hoặc vị trí của mã định danh.'),
                    self::knowledge(array('hoan hang', 'huy hang', 'thao tac nhanh'), 'Chỉ dùng hoàn/hủy nhanh khi đã kiểm tra đúng sản phẩm, đúng trạng thái và đúng nghiệp vụ cần xử lý.'),
                ),
            ),
            'settings' => array(
                'title' => 'Cài đặt hệ thống',
                'summary' => 'Trang cài đặt kiểm soát các feature nhạy cảm theo từng website chi nhánh trong multisite.',
                'quick_questions' => array('Bật/tắt tính năng có ảnh hưởng gì?', 'Cài đặt lưu theo shop nào?', 'Ai nên dùng trang này?'),
                'steps' => array(
                    self::step('.admin-settings-page h4', 'Cài đặt đặc biệt', 'Trang này dành cho admin hoặc quản lý có quyền cấu hình nghiệp vụ.', 'bottom', 'start'),
                    self::step('.admin-settings-page .alert-warning', 'Lưu ý trước khi đổi cài đặt', 'Đọc cảnh báo trước khi bật/tắt feature vì thay đổi có thể ảnh hưởng thao tác của nhân viên.', 'bottom', 'center'),
                    self::step('.feature-toggle', 'Công tắc tính năng', 'Mỗi công tắc bật/tắt một luồng nghiệp vụ cụ thể và thường lưu riêng theo website hiện tại.', 'left', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('bat tat', 'toggle', 'feature'), 'Các công tắc tính năng nên do admin hoặc quản lý phụ trách. Khi tắt, nhân viên có thể không dùng được chức năng tương ứng.'),
                    self::knowledge(array('multisite', 'shop', 'website chi nhanh'), 'Phần lớn cài đặt ở đây lưu theo website chi nhánh hiện tại, phù hợp mô hình multisite nhiều cửa hàng.'),
                    self::knowledge(array('quyen', 'admin'), 'Trang cài đặt dành cho người có quyền quản trị. Nhân viên thường không nên tự thay đổi các công tắc này.'),
                ),
            ),
            'api' => array(
                'title' => 'Quản lý API',
                'summary' => 'Trang API phục vụ kiểm tra cấu hình tích hợp và kết nối hệ thống bên ngoài.',
                'quick_questions' => array('API dùng để làm gì?', 'Ai nên cấu hình API?', 'Kiểm tra kết nối ở đâu?'),
                'steps' => array(
                    self::step('.card, table', 'Thông tin API', 'Khu vực này chứa danh sách endpoint, cấu hình hoặc chi tiết kết nối tùy màn hình API hiện tại.', 'bottom', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('api', 'ket noi', 'tich hop'), 'API dùng cho luồng tích hợp với hệ thống khác. Chỉ người phụ trách kỹ thuật hoặc quản trị nên chỉnh sửa cấu hình API.'),
                ),
            ),
            'guide_settings' => array(
                'title' => 'Cấu hình AI hướng dẫn',
                'summary' => 'Trang này cho biết plugin hướng dẫn đang hoạt động, các nhóm tour đã có và cách mở rộng thêm hướng dẫn.',
                'quick_questions' => array('Reset hướng dẫn thế nào?', 'Thêm tour mới ở đâu?', 'Nối AI thật bằng cách nào?'),
                'steps' => array(
                    self::step('.tgs-ai-guides-admin', 'Trung tâm hướng dẫn', 'Trang này giúp đội triển khai kiểm tra coverage và reset trạng thái hướng dẫn khi cần đào tạo lại.', 'bottom', 'center'),
                    self::step('[data-tgs-ai-reset-site]', 'Reset hướng dẫn đã xem', 'Dùng nút này để cho tài khoản hiện tại xem lại toàn bộ tour trên website chi nhánh này.', 'left', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('reset', 'xem lai tat ca'), 'Trên trang AI hướng dẫn, bấm nút reset để xóa lịch sử đã xem của tài khoản hiện tại trên website chi nhánh này.'),
                    self::knowledge(array('them tour', 'mo rong'), 'Lập trình viên có thể mở rộng tour qua filter `tgs_ai_guides_tour` hoặc bổ sung nhóm trong registry của plugin.'),
                    self::knowledge(array('ai that', 'ket noi ai'), 'Để nối AI thật, hook vào filter `tgs_ai_guides_ai_answer` và trả về mảng `answer`, `matched`, `quickQuestions`.'),
                ),
            ),
            'generic' => array(
                'title' => 'Màn hình TGS Shop',
                'summary' => 'Trang này thuộc hệ thống TGS Shop Management. Tour chung sẽ hướng dẫn thanh điều hướng, tìm kiếm, vùng làm việc và AI hỗ trợ.',
                'quick_questions' => array('Trang này dùng để làm gì?', 'Tôi cần thao tác bắt đầu ở đâu?', 'Làm sao xem lại hướng dẫn?'),
                'steps' => array(
                    self::step('h4, .card-title', 'Nội dung chính của trang', 'Đọc tiêu đề và các card chính để xác định nghiệp vụ hiện tại trước khi thao tác.', 'bottom', 'start'),
                    self::step('.card, table, form', 'Khối thao tác chính', 'Các bảng, form hoặc card trên trang là nơi nhập liệu, kiểm tra hoặc xử lý dữ liệu nghiệp vụ.', 'top', 'center'),
                ),
                'knowledge' => array(
                    self::knowledge(array('bat dau', 'thao tac'), 'Hãy đọc tiêu đề trang, kiểm tra các nút ở đầu trang, sau đó thao tác trong bảng hoặc form chính. Nếu chưa chắc, bấm "Hướng dẫn lại".'),
                ),
            ),
        );
    }

    private static function step($element, $title, $description, $side = 'bottom', $align = 'center')
    {
        return array(
            'element' => $element,
            'title' => $title,
            'description' => $description,
            'side' => $side,
            'align' => $align,
        );
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
