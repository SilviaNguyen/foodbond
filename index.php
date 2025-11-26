<?php
include 'header.php';


$sqlCategories = "SELECT * FROM categories ORDER BY category_name";
$rsCategories = mysqli_query($conn, $sqlCategories);

$catFilter = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

$sqlProducts = ($catFilter > 0) ? "
        SELECT p.*, c.category_name
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        WHERE p.category_id = $catFilter
        ORDER BY p.created_at DESC
    "
    : 
    "
        SELECT p.*, c.category_name
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        ORDER BY p.created_at DESC
    ";
$rsProducts = mysqli_query($conn, $sqlProducts);
?>

<h2 class="h4 mb-3">Menu FoodBond</h2>

<div class="d-flex flex-wrap mb-3">

    <a href="index.php"
       class="btn btn-sm <?php echo $catFilter ? 'btn-outline-secondary' : 'btn-secondary'; ?> me-2 mb-2">
        Tất cả
    </a>

    <?php while ($cat = mysqli_fetch_assoc($rsCategories)) : ?>
        <a href="index.php?cat=<?php echo $cat['category_id']; ?>"
           class="btn btn-sm <?php echo ($catFilter == $cat['category_id']) ? 'btn-secondary' : 'btn-outline-secondary'; ?> me-2 mb-2">
            <?php echo htmlspecialchars($cat['category_name']); ?>
        </a>
    <?php endwhile; ?>

</div>

<div class="row g-3">
    <?php if (mysqli_num_rows($rsProducts) > 0): ?>
        <?php while ($p = mysqli_fetch_assoc($rsProducts)) : ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card h-100">

                    <?php if (!empty($p['image'])): ?>
                        <img src="images/<?php echo htmlspecialchars($p['image']); ?>" class="card-img-top" style="height:180px;object-fit:cover;">
                    <?php else: ?>
                        <img src="https://via.placeholder.com/300x180?text=FoodBond" 
                             class="card-img-top">
                    <?php endif; ?>

                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title mb-1" style="font-size:1rem;">
                            <?php echo htmlspecialchars($p['product_name']); ?>
                        </h5>
                        <p class="text-muted mb-1" style="font-size:0.85rem;">
                            <?php echo htmlspecialchars($p['category_name']); ?>
                        </p>

                        <p class="fw-bold text-danger mb-2">
                            <?php echo number_format($p['price'], 0, ',', '.'); ?>đ
                        </p>

                        <p class="mb-3" style="font-size:0.85rem; flex-grow:1;">
                            <?php echo htmlspecialchars(mb_strimwidth($p['description'], 0, 60, "...")); ?>
                        </p>

                        <a href="cart.php?action=add&id=<?php echo $p['product_id']; ?>"
                        class="btn btn-sm btn-danger mt-auto add-to-cart-btn"
                        data-id="<?php echo $p['product_id']; ?>">
                            Thêm vào giỏ
                        </a>


                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info">Không có món nào trong danh mục này.</div>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
