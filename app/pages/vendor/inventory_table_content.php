<?php
// Table content for AJAX requests
?>
<div class="table-responsive">
    <table class="table table-striped text-nowrap align-middle table-bordered-vertical">
        <thead class="table-light">
            <tr>
                <th>No</th>
                <th>SKU ID</th>
                <th>Product Name</th>
                <th>Category</th>
                <th>Sub Category</th>
                <th>MRP</th>
                <th>Selling Price</th>
                <th>GST</th>
                <th>Stock in Hand</th>
                <th>Last Update</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $index => $product): ?>
                    <?php
                    $productImage = '';
                    if (!empty($product['images'])) {
                        $images = json_decode($product['images'], true);
                        if (is_array($images) && !empty($images[0])) {
                            // Images are stored as "uploads/vendors/businessname/productname/filename.jpg"
                            $productImage = $images[0];
                        }
                    }
                    $stockQuantity = (int) ($product['Inventory'] ?? 0);
                    $stockClass = '';
                    if ($stockQuantity == 0) {
                        $stockClass = 'text-danger fw-bold';
                    } elseif ($stockQuantity < 10) {
                        $stockClass = 'text-warning fw-bold';
                    }
                    ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><b><?php echo htmlspecialchars($product['sku_id'] ?? 'N/A'); ?></b></td>
                        <td>
                            <img src="<?php if (!empty($productImage)) {
                                echo PUBLIC_ASSETS . htmlspecialchars($productImage);
                            } else {
                                echo PUBLIC_ASSETS . 'uploads/vendors/no-product.png';
                            } ?>"
                                alt=""
                                style="width:32px;height:32px;border-radius:6px;margin-right:8px;object-fit:cover;">
                            <?php echo htmlspecialchars($product['product_name']); ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">
                                <?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($product['subcategory_name'] ?? 'N/A'); ?></td>
                        <td class="mrp-cell">₹<?php echo number_format((float) ($product['mrp'] ?? 0), 2); ?></td>
                        <td class="selling-price-cell">₹<?php echo number_format((float) ($product['selling_price'] ?? 0), 2); ?>
                        </td>
                        <td class="gst-cell"><?php echo ($product['gst'] ?? 0); ?>%</td>
                        <td class="stock-cell <?php echo $stockClass; ?>"><?php echo $stockQuantity; ?></td>
                        <td><?php
                        
                        if (!empty($product['updated_date'])) {
                            $dt_updated = new DateTime($product['updated_date'], new DateTimeZone('UTC')); // assuming stored as UTC/server time
                            $dt_updated->setTimezone(new DateTimeZone('Asia/Kolkata'));
                            echo $dt_updated->format('d M Y, h:i A');
                        } else {
                            echo '—';
                        }
                        ?></td>
                        </td>
                        <td class="action-cell">
                            <button class="action-header-btn edit-btn" data-product-id="<?php echo $product['id']; ?>">
                                <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/edit.png" alt="Edit">
                            </button>
                            <a href="./product-details.php?id=<?php echo $product['id']; ?>">
                                <button class="action-header-btn view-btn">
                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/view.png" alt="View">
                                </button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <?php
                // Detect which filter is active for friendly empty state
                $filter = isset($_GET['stock_filter']) ? strtolower(trim($_GET['stock_filter'])) : '';
                $icon = 'fa-box-open';
                $title = 'No products found';
                $subtitle = '';
                if ($filter === 'low') {
                    $icon = 'fa-triangle-exclamation';
                    $title = 'No Low Stock Items';
                    $subtitle = 'Great job! None of your items are running low.';
                } elseif ($filter === 'out') {
                    $icon = 'fa-circle-xmark';
                    $title = 'No Out of Stock Items';
                    $subtitle = 'Everything is in stock right now.';
                } else {
                    $icon = 'fa-cubes';
                    $title = 'No Products Yet';
                    $subtitle = 'Add your first product to see it listed here.';
                }
                ?>
                <tr>
                    <td colspan="12" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fa-solid <?php echo $icon; ?> fa-3x mb-3"></i>
                            <h5 class="mb-1"><?php echo $title; ?></h5>
                            <?php if ($subtitle): ?>
                                <div><?php echo $subtitle; ?></div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center mb-3">
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $base = strtok($_SERVER['REQUEST_URI'], '?');
                $qs = $_GET;
                unset($qs['page']);
                $makeUrl = function ($p) use ($base, $qs) {
                    $qs['page'] = $p;
                    return htmlspecialchars($base . '?' . http_build_query($qs));
                };
                ?>
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $page <= 1 ? '#' : $makeUrl($page - 1); ?>">Previous</a>
                </li>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $makeUrl($p); ?>"><?php echo $p; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $page >= $totalPages ? '#' : $makeUrl($page + 1); ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
<?php endif; ?>