<?php

session_start();

require_once 'config/database.php';

$customer_id = $_SESSION['customer_id'] ?? null;

if (!$customer_id) {
    header('Location: login.php');
    exit;
}


try {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
} catch (PDOException $e) {
    die("Lỗi: " . $e->getMessage());
}

$message = '';
$error = '';

// Nếu có dữ liệu POST thì xử lý đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_address = $_POST['shipping_address'] ?? '';
    
    if (empty($shipping_address)) {
        $error = "Vui lòng nhập địa chỉ giao hàng.";
    } else {
        $cart_json = $_POST['cart_data'] ?? '[]';
        $cart = json_decode($cart_json, true);
        
        if (empty($cart)) {
            $error = "Giỏ hàng của bạn đang trống.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Tính tổng tiền
                $total = 0;
                foreach ($cart as $item) {
                    $total += $item['price'] * $item['quantity'];
                }
                // Tạo đơn hàng
                $stmt = $pdo->prepare("
                    INSERT INTO orders (customer_id, total_amount, status, shipping_address) 
                    VALUES (?, ?, 'pending', ?)
                ");
                $stmt->execute([$customer_id, $total, $shipping_address]);
                $order_id = $pdo->lastInsertId();
                // Tạo chi tiết đơn hàng
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, book_id, quantity, price) 
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($cart as $item) {
                    $stmt->execute([$order_id, $item['id'], $item['quantity'], $item['price']]);
                    
                    // Cập nhật số lượng sách
                    $updateStmt = $pdo->prepare("UPDATE books SET stock = stock - ? WHERE id = ?");
                    $updateStmt->execute([$item['quantity'], $item['id']]);
                }
                
                $pdo->commit();
                
                // Xóa giỏ hàng trên client
                echo "<script>localStorage.removeItem('book_store_cart');</script>";
                
                $message = "Đặt hàng thành công! Mã đơn hàng: #" . $order_id;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Lỗi khi đặt hàng: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Toán - Cửa Hàng Sách</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <section class="form-section">
            <h2>Thanh Toán</h2>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <a href="orders.php" class="btn btn-primary">Xem Đơn Hàng</a>
            <?php elseif ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php else: ?>
                <div class="checkout-info">
                    <h3>Thông tin khách hàng</h3>
                    <p><strong>Họ tên:</strong> <?php echo htmlspecialchars($customer['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
                </div>

                <div id="checkout-cart-summary"></div>

                <form method="POST" action="checkout.php" id="checkout-form" onsubmit="return submitCheckout(event)">
                    <div class="form-group">
                        <label for="shipping_address">Địa chỉ giao hàng *</label>
                        <textarea id="shipping_address" name="shipping_address" rows="4" required><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <input type="hidden" name="cart_data" id="cart-data">
                    
                    <div class="form-actions">
                        <a href="cart.php" class="btn btn-secondary">Quay Lại Giỏ Hàng</a>
                        <button type="submit" class="btn btn-primary">Đặt Hàng</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/cart.js"></script>
    <script>
        // Hàm format số thành VND
        function formatVND(amount) {
            return amount.toLocaleString('vi-VN', { style: 'currency', currency: 'VND' });
        }
        
        // Load tóm tắt đơn hàng
        function loadCheckoutSummary() {
            const cart = getCart();
            const summaryDiv = document.getElementById('checkout-cart-summary');
            const cartDataInput = document.getElementById('cart-data');
        // Nếu giỏ hàng trống, chuyển hướng về trang giỏ hàng
            if (cart.length === 0) {
                summaryDiv.innerHTML = '<div class="alert alert-error">Giỏ hàng trống!</div>';
                window.location.href = 'cart.php';
                return;
            }
            
            let html = '<h3>Tóm tắt đơn hàng</h3><table class="cart-table"><thead><tr><th>Sách</th><th>Giá</th><th>Số lượng</th><th>Thành tiền</th></tr></thead><tbody>';
            let total = 0;

            cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                total += itemTotal;
                html += `
                    <tr>
                        <td>${item.title}</td>
                        <td>${formatVND(item.price)}</td>
                        <td>${item.quantity}</td>
                        <td>${formatVND(itemTotal)}</td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            html += `<div class="cart-total"><strong>Tổng cộng: ${formatVND(total)}</strong></div>`;
            summaryDiv.innerHTML = html;

            // Gửi dữ liệu giỏ hàng sang server
            cartDataInput.value = JSON.stringify(cart);
        }
        function submitCheckout(event) {
            const cart = getCart();
            if (cart.length === 0) {
                alert('Giỏ hàng trống!');
                event.preventDefault();
                return false;
            }
            return true;
        } json_decode
        document.addEventListener('DOMContentLoaded', function() {

            loadCheckoutSummary();
        });
    </script>
</body>
</html>
