<?php 
session_start();
require_once '../config/database.php';

// Hàm format số thành VND
function formatVND($amount) {
    return number_format((int)$amount, 0, ',', '.') . ' ₫';
}

// Kiểm tra quyền admin
if (!isset($_SESSION['customer_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    header('Location: login.php');
    exit;
}

// Kiểm tra ID đơn hàng
$order_id = $_GET['id'] ?? null;
if (!$order_id || !ctype_digit($order_id)) {
    header('Location: orders.php');
    exit;
}

try {
    // Lấy thông tin đơn hàng
    $stmt = $pdo->prepare("
        SELECT o.*, 
               c.name AS customer_name, 
               c.email AS customer_email, 
               c.phone AS customer_phone, 
               c.address AS customer_address
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header("Location: orders.php");
        exit;
    }

    // Lấy chi tiết sản phẩm trong đơn hàng
    $stmt = $pdo->prepare("
        SELECT oi.*, b.title
        FROM order_items oi
        INNER JOIN books b ON oi.book_id = b.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Lỗi tải đơn hàng: " . $e->getMessage();
    $order = null;
    $items = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng #<?php echo $order_id; ?> - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-page">
<?php include '../includes/header.php'; ?>

<main class="container">
    <div class="admin-dashboard">
        <h1>Chi tiết đơn hàng #<?php echo $order_id; ?></h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>

        <?php elseif ($order): ?>

            <!-- THÔNG TIN ĐƠN HÀNG -->
            <div class="dashboard-section">
                <h2>Thông tin đơn hàng</h2>
                <div class="order-details">
                    <p><strong>Mã đơn hàng:</strong> #<?php echo $order['id']; ?></p>

                    <p><strong>Trạng thái:</strong>
                        <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                            <?php 
                                echo $order['status'] === 'pending' ? 'Chờ xử lý' :
                                     ($order['status'] === 'processing' ? 'Đang xử lý' :
                                     ($order['status'] === 'completed' ? 'Hoàn thành' : 'Không xác định'));
                            ?>
                        </span>
                    </p>

                    <p><strong>Tổng tiền:</strong> 
                        <?php echo formatVND($order['total_amount']); ?>
                    </p>

                    <p><strong>Ngày đặt:</strong>
                        <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                    </p>

                    <?php if (!empty($order['shipping_address'])): ?>
                        <p><strong>Địa chỉ giao hàng:</strong>
                            <?php echo htmlspecialchars($order['shipping_address']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- THÔNG TIN KHÁCH HÀNG -->
            <div class="dashboard-section">
                <h2>Thông tin khách hàng</h2>
                <div class="order-details">
                    <p><strong>Họ tên:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>

                    <?php if (!empty($order['customer_phone'])): ?>
                        <p><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($order['customer_address'])): ?>
                        <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($order['customer_address']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SẢN PHẨM TRONG ĐƠN HÀNG -->
            <div class="dashboard-section">
                <h2>Sản phẩm đã đặt</h2>

                <?php if (empty($items)): ?>
                    <div class="no-data">Không có sản phẩm nào.</div>

                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Sách</th>
                                    <th>Giá</th>
                                    <th>Số lượng</th>
                                    <th>Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                                    <td><?php echo formatVND($item['price']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo formatVND($item['price'] * $item['quantity']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="text-align: right;"><strong>Tổng cộng:</strong></td>
                                    <td><strong><?php echo formatVND($order['total_amount']); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>

            </div>

            <!-- NÚT HÀNH ĐỘNG -->
            <div class="quick-actions">
                <a href="orders.php" class="btn btn-secondary">Quay lại danh sách</a>

                <?php if ($order['status'] === 'pending'): ?>
                    <a href="update_order.php?id=<?php echo $order['id']; ?>&status=processing" 
                       class="btn btn-warning">Chuyển sang Đang xử lý</a>

                <?php elseif ($order['status'] === 'processing'): ?>
                    <a href="update_order.php?id=<?php echo $order['id']; ?>&status=completed" 
                       class="btn btn-primary">Đánh dấu Hoàn thành</a>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
</body>
</html>
