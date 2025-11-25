    </div> 
</main>   


<footer class="py-3 bg-dark mt-4">
    <div class="container text-center text-white-50">
        <small>© <?php echo date("Y"); ?> FoodBond. Đại Học Giao Thông Vận Tải</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var buttons = document.querySelectorAll('.add-to-cart-btn');
    if (!buttons.length) return;

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var id = this.dataset.id;
            if (!id) return;

            fetch('cart.php?action=add&id=' + encodeURIComponent(id) + '&ajax=1')
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.success) {
                        alert(data && data.message ? data.message : 'Có lỗi khi thêm vào giỏ hàng');
                        return;
                    }
                    var cartLink = document.querySelector('.nav-link[href="cart.php"]');
                    if (cartLink) {
                        var badge = cartLink.querySelector('.badge');
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'badge rounded-pill bg-danger ms-1';
                            cartLink.appendChild(badge);
                        }
                        badge.textContent = data.cartCount;
                    }

                    var originalText = btn.textContent.trim();
                    btn.textContent = 'Đã thêm';
                    btn.classList.add('btn-success');
                    btn.classList.remove('btn-danger');

                    setTimeout(function () {
                        btn.textContent = originalText;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-danger');
                    }, 500);
                })
                .catch(function () {
                    alert('Không thể kết nối tới server.');
                });
        });
    });
});

</script>
</body>
</html>
