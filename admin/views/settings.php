<?php

if (!defined('ABSPATH')) {
    exit;
}

$groups = TGS_AI_Guides_Registry::all_groups();
$current_view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'ai-guides';
$site_id = get_current_blog_id();
?>

<div class="tgs-ai-guides-admin">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1">AI hướng dẫn sử dụng</h4>
            <p class="text-muted mb-0">Plugin độc lập cho tour driver.js, lưu trạng thái theo tài khoản và website chi nhánh.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-secondary" data-tgs-ai-reset-site>
                <i class="bx bx-refresh me-1"></i>Reset hướng dẫn đã xem
            </button>
            <button type="button" class="btn btn-primary" data-tgs-ai-replay>
                <i class="bx bx-play me-1"></i>Chạy hướng dẫn trang này
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">Website hiện tại</div>
                    <div class="fs-4 fw-bold">#<?php echo esc_html($site_id); ?></div>
                    <div class="text-muted small">Dùng riêng lịch sử hướng dẫn cho từng site trong multisite.</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">Nhóm tour</div>
                    <div class="fs-4 fw-bold"><?php echo esc_html(count($groups)); ?></div>
                    <div class="text-muted small">Có tour riêng cho dashboard, sản phẩm, phiếu, kho, báo cáo và cài đặt.</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">driver.js</div>
                    <div class="fs-4 fw-bold">1.4.0</div>
                    <div class="text-muted small">Thư viện được đóng gói trong plugin để tránh phụ thuộc CDN khi chạy thực tế.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Cách mở rộng</h5>
        </div>
        <div class="card-body">
            <p class="mb-2">Các plugin khác có thể mở rộng hướng dẫn mà không sửa plugin chính:</p>
            <ul class="mb-0">
                <li><code>tgs_ai_guides_tour</code>: thêm/sửa steps, quick questions hoặc knowledge theo view.</li>
                <li><code>tgs_ai_guides_group_for_view</code>: ánh xạ view mới về một nhóm tour có sẵn.</li>
                <li><code>tgs_ai_guides_ai_answer</code>: nối sang AI thật sau này và trả lời theo toàn website.</li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Coverage hiện có</h5>
            <span class="badge bg-primary"><?php echo esc_html(count($groups)); ?> nhóm</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Nhóm</th>
                        <th>Tiêu đề</th>
                        <th>Số bước</th>
                        <th>Câu hỏi nhanh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $key => $group): ?>
                        <tr>
                            <td><code><?php echo esc_html($key); ?></code></td>
                            <td><?php echo esc_html($group['title']); ?></td>
                            <td><?php echo esc_html(count($group['steps'])); ?></td>
                            <td><?php echo esc_html(implode(', ', array_slice($group['quick_questions'], 0, 3))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
