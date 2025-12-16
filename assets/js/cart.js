// Giỏ hàng được lưu trữ trong localStorage với key 'book_store_cart'
const CART_KEY = 'book_store_cart';

// Cập nhật số lượng giỏ hàng khi trang được tải
document.addEventListener('DOMContentLoaded', function() {
    updateCartCount();
});

// Lấy giỏ hàng từ localStorage
function getCart() {
    const cart = localStorage.getItem(CART_KEY);
    return cart ? JSON.parse(cart) : [];
}

// Lưu giỏ hàng ở  localStorage
function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCartCount();
}

// Cập nhật số lượng hiển thị trên header
function updateCartCount() {
    const cart = getCart();
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    const cartCountElement = document.getElementById('cart-count');
    if (cartCountElement) {
        cartCountElement.textContent = totalItems;
    }
}

// Thêm sản phẩm vào giỏ hàng
function addToCart(bookId, title, price, stock) {
    const cart = getCart();
    const existingItem = cart.find(item => item.id === bookId);
    
    if (existingItem) {
        if (existingItem.quantity >= stock) {
            alert('Cannot add more. Stock limit reached!');
            return;
        }
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: bookId,
            title: title,
            price: price,
            quantity: 1,
            stock: stock
        });
    }
    
    saveCart(cart);
    alert('Book added to cart!');
}

// Xóa sản phẩm 
function removeFromCart(bookId) {
    const cart = getCart();
    const newCart = cart.filter(item => item.id !== bookId);
    saveCart(newCart);
    location.reload(); 
}

// Cập nhật số lượng sản phẩm
function updateQuantity(bookId, newQuantity) {
    const cart = getCart();
    const item = cart.find(item => item.id === bookId);
    
    if (item) {
        if (newQuantity <= 0) {
            removeFromCart(bookId);
            return;
        }
        if (newQuantity > item.stock) {
            alert('Cannot add more. Stock limit reached!');
            return;
        }
        item.quantity = newQuantity;
        saveCart(cart);
        location.reload(); // Reload to update display
    }
}

// Xóa toàn bộ giỏ hàng
function clearCart() {
    if (confirm('Are you sure you want to clear the cart?')) {
        localStorage.removeItem(CART_KEY);
        location.reload();
    }
}

// Tính tổng giá trị giỏ hàng
function getCartTotal() {
    const cart = getCart();
    return cart.reduce((total, item) => total + (item.price * item.quantity), 0);
}

// Xuất các hàm để sử dụng trong các module khác (nếu cần)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        getCart,
        saveCart,
        addToCart,
        removeFromCart,
        updateQuantity,
        clearCart,
        getCartTotal
    };
}

