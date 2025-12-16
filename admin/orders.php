<?php
session_start();
require_once '../config/database.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['customer_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    header('Location: login.php');
    exit;
}

// Lấy tham số lọc
$status = $_GET['status'] ?? null;

// Tạo truy vấn
$query = "SELECT o.*, c.name as customer_name, c.email as customer_email 
          FROM orders o 
          LEFT JOIN customers c ON o.customer_id = c.id 
          WHERE 1=1";
$params = [];

if ($status) {
    $query .= " AND o.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY o.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
    $error = "Lỗi tải đơn hàng: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Đơn hàng - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-page">
    <?php include '../includes/header.php'; ?>

    <main class="container">
        <div class="admin-dashboard">
            <h1>Quản lý Đơn hàng</h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Bộ lọc -->
            <div class="filters">
                <h3>Lọc đơn hàng</h3>
                <form method="GET" action="orders.php" class="filter-form">
                    <div class="filter-group">
                        <label for="status">Trạng thái:</label>
                        <select id="status" name="status">
                            <option value="">Tất cả trạng thái</option>
                            <option value="pending" <?php echo ($status === 'pending') ? 'selected' : ''; ?>>Chờ xử lý</option>
                            <option value="processing" <?php echo ($status === 'processing') ? 'selected' : ''; ?>>Đang xử lý</option>
                            <option value="completed" <?php echo ($status === 'completed') ? 'selected' : ''; ?>>Hoàn thành</option>
                            <option value="cancelled" <?php echo ($status === 'cancelled') ? 'selected' : ''; ?>>Đã hủy</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Lọc</button>
                    <a href="orders.php" class="btn btn-secondary">Xóa lọc</a>
                </form>
            </div>

            <!-- Danh sách đơn hàng -->
            <div class="dashboard-section">
                <?php if (empty($orders)): ?>
                    <div class="no-data">Không tìm thấy đơn hàng nào.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Email</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày tạo</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Không rõ'); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_email'] ?? 'Không rõ'); ?></td>
                                        <td><?php echo number_format($order['total_amount'], 0, ',', '.'); ?> ₫</td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php 
                                                    echo $order['status'] === 'pending' ? 'Chờ xử lý' :
                                                         ($order['status'] === 'processing' ? 'Đang xử lý' :
                                                         ($order['status'] === 'completed' ? 'Hoàn thành' :
                                                         'Đã hủy'));
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <a href="order_detail.php?id=<?php echo $order['id']; ?>" 
                                               class="btn btn-sm btn-primary">Xem</a>

                                            <?php if ($order['status'] === 'pending'): ?>
                                                <a href="update_order.php?id=<?php echo $order['id']; ?>&status=processing" 
                                                   class="btn btn-sm btn-warning">Xử lý</a>

                                            <?php elseif ($order['status'] === 'processing'): ?>
                                                <a href="update_order.php?id=<?php echo $order['id']; ?>&status=completed" 
                                                   class="btn btn-sm btn-success">Hoàn thành</a>

                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="quick-actions">
                <a href="index.php" class="btn btn-secondary">Quay lại Trang chủ Admin</a>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
