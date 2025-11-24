<?php
require_once __DIR__ . '/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string)
    {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF_8');
    }
}

$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
$user_id = $is_logged_in ? (int)$_SESSION['user_id'] : 0;
$base_image_url = $vendor_baseurl;
$default_image = $vendor_baseurl . 'uploads/vendors/no-product.png';
$wishlist_products = [];

if ($is_logged_in) {
    $wishlist_query = "
		SELECT w.id as wishlist_id, w.created_date,
		       p.id, p.product_name, p.selling_price, p.mrp, p.images, p.Inventory as stock_quantity, p.coin
		FROM wishlist w
		JOIN products p ON w.product_id = p.id
		WHERE w.user_id = ? AND p.status = 'approved'
		ORDER BY w.created_date DESC
	";
    $stmt = mysqli_prepare($db_connection, $wishlist_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $product_images = [];
            if (!empty($row['images'])) {
                $images_data = json_decode($row['images'], true);
                if (is_array($images_data)) {
                    foreach ($images_data as $image_name) {
                        if (!empty($image_name)) {
                            $product_images[] = $base_image_url . $image_name;
                        }
                    }
                }
            }
            if (empty($product_images)) {
                $product_images[] = $default_image;
            }
            $row['images_array'] = $product_images;
            $wishlist_products[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<div class="table-responsive">
    <table class="shop-table wishlist-table">
        <thead>
            <tr>
                <th class="product-name"><span><b>Product</b></span></th>
                <th></th>
                <th class="product-price text-start"><span><b>Price</b></span></th>
                <th class="product-stock-status"><span><b>Stock Status</b></span></th>
                <th class="wishlist-action"><span><b>Actions</b></span></th>
            </tr>
        </thead>
        <tbody id="account-wishlist-body">
            <?php if ($is_logged_in && !empty($wishlist_products)): ?>
                <?php foreach ($wishlist_products as $product): ?>
                    <tr data-product-id="<?php echo $product['id']; ?>">
                        <td class="product-thumbnail">
                            <div class="p-relative">
                                <a href="product-detail.php?id=<?php echo $product['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($product['images_array'][0], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES); ?>" width="80" height="80" onerror="this.src='<?php echo $default_image; ?>';">
                                </a>
                                <button type="button" class="btn btn-link btn-close btn-remove-wishlist p-absolute top-0 right-0" data-product-id="<?php echo $product['id']; ?>">
                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/trash-icon.svg" alt="">
                                </button>
                            </div>
                        </td>
                        <td class="product-name">
                            <a href="product-detail.php?id=<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['product_name'], ENT_QUOTES); ?></a>
                        </td>
                        <td class="product-price text-start">
                            <ins class="new-price">₹<?php echo number_format($product['selling_price'], 2); ?></ins>
                            <?php if ($product['mrp'] > $product['selling_price']): ?>
                                <del class="old-price ml-2">₹<?php echo number_format($product['mrp'], 2); ?></del>
                            <?php endif; ?>
                            <?php if (!empty($product['coin'])): ?>
                                <div class="small text-muted d-flex">You will earn <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png" class="img-fluid ml-1" alt="" style="width: 20px; height: 20px;">
                                    <?php echo " " . (float)$product['coin']; ?> coins</div>
                            <?php endif; ?>
                        </td>
                        <td class="product-stock-status text-center">
                            <?php if ((int)$product['stock_quantity'] > 0): ?>
                                <span class="wishlist-in-stock">In Stock</span>
                            <?php else: ?>
                                <span class="wishlist-out-of-stock">Out of Stock</span>
                            <?php endif; ?>
                        </td>
                        <td class="wishlist-action">
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
                                <a href="#" class="btn btn-primary btn-rounded btn-sm btn-cart" data-product-id="<?php echo $product['id']; ?>">Add to cart</a>
                            <?php else: ?>
                                <a href="<?php echo PUBLIC_ASSETS; ?>ajax/login.html" class="btn btn-primary btn-rounded btn-sm btn-cart login sign-in">Add to cart</a>
                            <?php endif; ?>
                            <a href="#" class="btn btn-outline btn-rounded btn-sm btn-quickview ml-2" data-product-id="<?php echo $product['id']; ?>">Quick View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php elseif ($is_logged_in): ?>
                <tr>
                    <td colspan="5" class="text-center py-5">Your wishlist is empty.</td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center py-5">Please log in to see your wishlist.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<!-- Remove Wishlist Item Confirmation Modal (scoped to account tab) -->
<div id="removeWishlistItemModalAccount" class="modal cart-clar-modal" style="display:none;">
    <div class="cart-clar-modal1" style="background:#fff; padding:20px; border-radius:8px; width:90%; max-width:400px; text-align:center; position:relative;">
        <span id="closeRemoveWishlistItemModalAccount" style="position:absolute; right:15px; top:5px; font-size:18px; cursor:pointer; z-index: 1;">×</span>
        <div>
            <img src="<?php echo PUBLIC_ASSETS; ?>images/trash-icon.svg" style="width: 40px; height: 40px;" alt="">
        </div>
        <p style="font-size:18px; margin-bottom:20px;">Are you sure you want to remove this item from your wishlist?</p>
        <button class="btn btn-wl-remove-no-account" style="background-color:transparent; color:#E6533C;  border:1px solid #E6533C; border-radius:5px; margin-right:10px; cursor:pointer;">No</button>
        <button class="btn btn-wl-remove-yes-account" style="background-color:#3B4B6B; color:#fff;  border:1px solid #3B4B6B; border-radius:5px; cursor:pointer;">Yes</button>
    </div>


</div>
<script>
    // Scoped Wishlist logic for My Account tab, mirroring wishlist.php behaviors
    (function() {
        const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

        // Add to Cart
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('#account-wishlist-body .btn-cart');
            if (!btn) return;
            e.preventDefault();
            const productId = btn.getAttribute('data-product-id');
            if (!productId) return;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
            if (window.cartManager && typeof window.cartManager.addToCart === 'function') {
                window.cartManager.addToCart(productId, 1).then((res) => {
                    if (window.wishlistPageManager && typeof window.wishlistPageManager.updateHeaderCartCount === 'function') {
                        window.wishlistPageManager.updateHeaderCartCount();
                    }
                }).finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
            } else {
                fetch('cart_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'add',
                            product_id: parseInt(productId),
                            quantity: 1
                        })
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = original;
                    });
            }
        });

        // Remove from Wishlist (with confirmation)
        document.addEventListener('click', function(e) {
            const iconBtn = e.target.closest('#account-wishlist-body .btn-remove-wishlist');
            if (!iconBtn) return;
            e.preventDefault();
            const productId = iconBtn.getAttribute('data-product-id');
            openRemoveWishlistModal(productId, iconBtn);
        });

        function removeFromWishlist(productId, button) {
            if (!productId) return;
            button.style.opacity = '0.6';
            button.style.pointerEvents = 'none';
            if (isLoggedIn) {
                fetch('<?php echo USER_BASEURL; ?>app/handlers/wishlist_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'remove',
                            product_id: parseInt(productId)
                        })
                    })
                    .then(r => r.json()).then(data => {
                        if (data && data.success) {
                            // Remove row and optionally refresh count
                            const row = document.querySelector(`#account-wishlist-body tr[data-product-id="${productId}"]`);
                            if (row) row.remove();
                            if (window.wishlistPageManager && typeof window.wishlistPageManager.updateHeaderCount === 'function') {
                                window.wishlistPageManager.updateHeaderCount();
                            }
                        }
                    }).finally(() => {
                        button.style.opacity = '';
                        button.style.pointerEvents = '';
                    });
            } else {
                let wishlist = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');
                wishlist = wishlist.filter(id => id != parseInt(productId));
                localStorage.setItem('guest_wishlist', JSON.stringify(wishlist));
                const row = document.querySelector(`#account-wishlist-body tr[data-product-id="${productId}"]`);
                if (row) row.remove();
            }
        }

        function openRemoveWishlistModal(productId, button) {
            const modal = document.getElementById('removeWishlistItemModalAccount');
            if (!modal) {
                removeFromWishlist(productId, button);
                return;
            }
            modal.style.display = 'block';
            const yesBtn = modal.querySelector('.btn-wl-remove-yes-account');
            const noBtn = modal.querySelector('.btn-wl-remove-no-account');
            const closeBtn = document.getElementById('closeRemoveWishlistItemModalAccount');
            const cleanup = () => {
                modal.style.display = 'none';
                yesBtn.replaceWith(yesBtn.cloneNode(true));
                noBtn.replaceWith(noBtn.cloneNode(true));
                closeBtn.replaceWith(closeBtn.cloneNode(true));
            };
            modal.querySelector('.btn-wl-remove-yes-account').addEventListener('click', () => {
                cleanup();
                removeFromWishlist(productId, button);
            }, {
                once: true
            });
            modal.querySelector('.btn-wl-remove-no-account').addEventListener('click', cleanup, {
                once: true
            });
            document.getElementById('closeRemoveWishlistItemModalAccount').addEventListener('click', cleanup, {
                once: true
            });
            const outsideHandler = (e) => {
                if (e.target === modal) {
                    cleanup();
                    window.removeEventListener('click', outsideHandler);
                }
            };
            window.addEventListener('click', outsideHandler);
        }

        // Quickview (uses Wolmart popup when available; falls back to AJAX overlay)
        document.addEventListener('click', function(e) {
            const qvBtn = e.target.closest('#account-wishlist-body .btn-quickview');
            if (!qvBtn) return;
            e.preventDefault();
            const productId = qvBtn.getAttribute('data-product-id');
            if (!productId) return;
            qvBtn.style.opacity = '0.6';
            if (typeof Wolmart !== 'undefined' && Wolmart.popup) {
                Wolmart.popup({
                    items: {
                        src: 'quickview.php?id=' + productId,
                        type: 'ajax'
                    },
                    ajax: {
                        settings: {
                            method: 'GET'
                        },
                        success: function() {
                            setTimeout(initAccountQuickviewBindings, 200);
                        }
                    },
                    callbacks: {
                        open: function() {
                            setTimeout(initAccountQuickviewBindings, 250);
                        },
                        close: function() {
                            qvBtn.style.opacity = '';
                        }
                    }
                }, 'quickview');
            } else {
                // Lightweight fallback
                fetch('quickview.php?id=' + productId).then(r => r.text()).then(html => {
                    const overlay = document.createElement('div');
                    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:10000;display:flex;align-items:center;justify-content:center;';
                    const box = document.createElement('div');
                    box.style.cssText = 'background:#fff;border-radius:8px;max-width:90%;max-height:90%;overflow:auto;position:relative;padding:20px;';
                    const close = document.createElement('button');
                    close.textContent = '×';
                    close.style.cssText = 'position:absolute;top:10px;right:15px;background:none;border:none;font-size:24px;cursor:pointer;';
                    close.onclick = () => document.body.removeChild(overlay);
                    box.appendChild(close);
                    box.insertAdjacentHTML('beforeend', html);
                    overlay.appendChild(box);
                    document.body.appendChild(overlay);
                    setTimeout(initAccountQuickviewBindings, 150);
                }).finally(() => {
                    qvBtn.style.opacity = '';
                });
            }
        });

        // Minimal bindings inside quickview (cart/wishlist buttons and simple image nav)
        function initAccountQuickviewBindings() {
            // Cart buttons
            document.querySelectorAll('.mfp-content .btn-cart, .mfp-content .btn-add-cart').forEach(function(btn) {
                if (btn.getAttribute('data-account-qv-cart') === '1') return;
                btn.setAttribute('data-account-qv-cart', '1');
                btn.addEventListener('click', function(ev) {
                    ev.preventDefault();
                    ev.stopImmediatePropagation();
                    const pid = btn.getAttribute('data-product-id');
                    if (!pid) return;
                    const qtyInput = document.querySelector('.mfp-content .quantity input');
                    const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
                    const original = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
                    if (window.cartManager && typeof window.cartManager.addToCart === 'function') {
                        window.cartManager.addToCart(pid, qty).finally(() => {
                            btn.disabled = false;
                            btn.innerHTML = original;
                            if (window.wishlistPageManager) {
                                window.wishlistPageManager.updateHeaderCartCount();
                            }
                        });
                    } else {
                        fetch('cart_handler.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    action: 'add',
                                    product_id: parseInt(pid),
                                    quantity: qty
                                })
                            })
                            .finally(() => {
                                btn.disabled = false;
                                btn.innerHTML = original;
                            });
                    }
                });
            });
            // Simple image navigation (supports multiple DOM variants, no Swiper required)
            const modal = document.querySelector('.mfp-content .product-popup');
            if (modal) {
                // Collect potential slide containers
                const slideSets = [
                    '.swiper-wrapper-quickview .swiper-slide', // custom wrapper used on wishlist.php
                    '.product-single-swiper .swiper-wrapper .swiper-slide',
                    '.swiper-wrapper .swiper-slide'
                ];
                let slides = [];
                for (const sel of slideSets) {
                    slides = modal.querySelectorAll(sel);
                    if (slides && slides.length) break;
                }

                // Find thumbnails (various templates)
                const thumbSets = [
                    '.product-thumb img',
                    '.product-thumbs img',
                    '.product-thumbs .swiper-slide img',
                    '.thumbnail img'
                ];
                let thumbs = [];
                for (const sel of thumbSets) {
                    thumbs = modal.querySelectorAll(sel);
                    if (thumbs && thumbs.length) break;
                }

                if (slides.length) {
                    let currentIndex = 0;
                    const show = (idx) => {
                        currentIndex = Math.max(0, Math.min(idx, slides.length - 1));
                        slides.forEach((s, i) => {
                            s.style.display = i === currentIndex ? 'block' : 'none';
                        });
                        if (thumbs.length) {
                            thumbs.forEach((t, i) => {
                                const p = t.parentElement;
                                if (!p) return;
                                if (i === currentIndex) {
                                    p.classList.add('active');
                                    p.style.borderColor = '#007bff';
                                    p.style.opacity = '1';
                                } else {
                                    p.classList.remove('active');
                                    p.style.borderColor = 'transparent';
                                    p.style.opacity = '0.7';
                                }
                            });
                        }
                    };
                    // initialize
                    slides.forEach((s, i) => {
                        s.style.display = i === 0 ? 'block' : 'none';
                    });
                    show(0);
                    // thumbs click
                    if (thumbs.length) {
                        thumbs.forEach((t, i) => {
                            if (t.getAttribute('data-account-qv-thumb') === '1') return;
                            t.setAttribute('data-account-qv-thumb', '1');
                            t.style.cursor = 'pointer';
                            t.addEventListener('click', function(ev) {
                                ev.preventDefault();
                                ev.stopPropagation();
                                show(i);
                            });
                        });
                    }
                    // next/prev buttons (when present)
                    const nextBtn = modal.querySelector('.swiper-button-next');
                    const prevBtn = modal.querySelector('.swiper-button-prev');
                    if (nextBtn && !nextBtn.getAttribute('data-account-qv-nav')) {
                        nextBtn.setAttribute('data-account-qv-nav', '1');
                        nextBtn.addEventListener('click', function(ev) {
                            ev.preventDefault();
                            show((currentIndex + 1) % slides.length);
                        });
                    }
                    if (prevBtn && !prevBtn.getAttribute('data-account-qv-nav')) {
                        prevBtn.setAttribute('data-account-qv-nav', '1');
                        prevBtn.addEventListener('click', function(ev) {
                            ev.preventDefault();
                            show((currentIndex - 1 + slides.length) % slides.length);
                        });
                    }
                }
            }
        }
    })();
</script>
<style>
    .wishlist-out-of-stock {
        color: #e74c3c;
        font-weight: bold;
    }

    .wishlist-in-stock {
        color: #2ecc71;
        font-weight: bold;
    }

    /* compact positioning for small thumbnail delete icon */
    #account-wishlist-body .product-thumbnail .btn-close {
        position: absolute;
        top: -8px;
        left: 66px;
        width: 22px;
        height: 22px;
        padding: 0;
    }

    /* Heart state classes to match wishlist.php visual states when used inside quickview */
    .btn-wishlist,
    .btn-product-icon.btn-wishlist {
        transition: all .3s ease;
        cursor: pointer;
    }

    .btn-wishlist.w-icon-heart,
    .btn-product-icon.btn-wishlist.w-icon-heart {
        color: #999 !important;
    }

    .btn-wishlist.w-icon-heart-full,
    .btn-product-icon.btn-wishlist.w-icon-heart-full,
    .btn-wishlist.in-wishlist,
    .btn-product-icon.btn-wishlist.in-wishlist {
        color: #ff4757 !important;
        border-color: #ff4757 !important;
    }
</style>