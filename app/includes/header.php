<?php
$categories = mysqli_query($db_connection, "select * from category");
$sub_category = mysqli_query($db_connection, "select * from sub_category");
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$ustatus = 'inactive';

$customer_id = '';
$customer_name = '';
if ($user_id > 0) {
    $ustatus_query = mysqli_query($db_connection, "SELECT * FROM users WHERE id = $user_id");
    if ($ustatus_query && mysqli_num_rows($ustatus_query) > 0) {
        $udata = mysqli_fetch_assoc($ustatus_query);
        // Normalize to lowercase to avoid case mismatches (e.g., 'Active' vs 'active')
        $ustatus = strtolower(trim($udata['status'] ?? 'inactive'));
        $customer_id = $udata['customer_id'] ?? '';
        $customer_name = $udata['first_name'] . ' ' . $udata['last_name'] ?? '';
    }
}

// Make user_id available to JavaScript
echo "<script>window.user_id = " . $user_id . ";</script>";
?>
<!-- Start of Header -->
<link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>css/index.css">
<header class="header">
    <div class="header-top">
        <div class="container">
            <div class="header-left">
                <p class="welcome-msg">Welcome to Hagidy - Cashback on Every Purchase 1.6</p>
            </div>
            <div class="header-right">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
                    <a href="my-account.php#refer-friend" class="d-lg-show account-sell-hover">Referral Link</a>
                    <!-- <a href="/hagidy/contact-us.php" class="d-lg-show account-sell-hover">Contact us</a> -->
                    <a href="my-account.php" class="d-lg-show account-sell-hover">My Account</a>
                    <?php if ($ustatus === 'new'): ?>
                        <a href="#" class="new-header">New</a>
                    <?php elseif ($ustatus === 'inactive'): ?>
                        <a href="#" class="inactive-header">Inactive</a>
                    <?php elseif ($ustatus === 'active'): ?>
                        <a href="#" class="active-header">Active</a>
                    <?php endif ?>

                    <span class="text-white font-weight-bold font-size-md">User ID: #<?php echo $customer_id; ?></span>
                    <span class="text-white font-weight-bold font-size-md"><?php echo $customer_name; ?></span>
                <?php endif ?>
                <?php if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])): ?>
                    <a href="<?php echo PUBLIC_ASSETS; ?>ajax/login.html" class="d-lg-show account-sell-hover login sign-in"><i
                            class="w-icon-account"></i>Sign In</a>
                    <span class="delimiter d-lg-show">/</span>
                    <a href="<?php echo PUBLIC_ASSETS; ?>ajax/login.html" class="ml-0 d-lg-show login register account-sell-hover">Register</a>
                <?php endif ?>


            </div>
        </div>
    </div>
    <!-- End of Header Top -->
    <div class="header-middle sticky-content fix-top sticky-header border-no">
        <div class="container">
            <div class="header-left mr-md-4">
                <a href="#" class="mobile-menu-toggle w-icon-hamburger"></a>
                <a href="index.php" class="logo">
                    <img src="<?php echo PUBLIC_ASSETS; ?>images/demos/demo12/logo.png" alt="logo" width="144" height="45">
                </a>
                <form method="get" action="#" class="input-wrapper header-search hs-expanded hs-round d-none d-md-flex">
                    <div class="select-box bg-white">
                        <select id="category" name="category" onchange="loadSubCategories(this.value)">
                            <option value="">All Categories</option>
                            <?php
                            if ($categories && mysqli_num_rows($categories) > 0) {
                                mysqli_data_seek($categories, 0); // Reset pointer
                                while ($category = mysqli_fetch_assoc($categories)) {
                                    echo '<option value="' . htmlspecialchars($category['id']) . '">' . htmlspecialchars($category['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="select-box bg-white">
                        <select id="sub-category" name="sub-category">
                            <option value="">All Sub Categories</option>
                            <?php
                            // Initially load all sub-categories
                            if ($sub_category && mysqli_num_rows($sub_category) > 0) {
                                mysqli_data_seek($sub_category, 0); // Reset pointer
                                while ($sub_cat = mysqli_fetch_assoc($sub_category)) {
                                    echo '<option value="' . htmlspecialchars($sub_cat['id']) . '">' . htmlspecialchars($sub_cat['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="search-input-wrapper">
                        <input type="text" class="form-control bg-white pt-0 pb-0" name="search" id="search"
                            placeholder="Search in..." required />
                        <div class="search-suggestions" id="searchSuggestions">
                            <!-- Dynamic search results will be populated here -->
                        </div>
                    </div>
                    <button class="btn btn-search" type="submit">
                        <i class="w-icon-search"></i>
                    </button>
                </form>
            </div>
            <div class="header-right ml-4">

                <a class="wishlist label-down link d-xs-show" href="wishlist.php">
                    <i class="w-icon-heart">
                        <span class="wishlist-count" id="wishlist-count" style="display: none; color: white;">0</span>
                    </i>
                    <span class="wishlist-label d-lg-show">Wishlist</span>
                </a>
                <div class="dropdown cart-dropdown cart-offcanvas mr-0 mr-lg-2">
                    <div class="cart-overlay"></div>
                    <a href="#" class="cart-toggle label-down link">
                        <i class="w-icon-cart">
                            <span class="cart-count text-white" id="cart-count" style="display: none;">0</span>
                        </i>
                        <span class="cart-label">Cart</span>
                    </a>
                    <div class="dropdown-box">
                        <div class="cart-header">
                            <span>Shopping Cart</span>
                            <a href="#" class="btn-close"><i class="fa-solid fa-xmark"></i></a>
                        </div>
                        <div class="products" id="header-cart-items">
                            <!-- Dynamic cart items will be loaded here -->
                            <div class="empty-cart text-center py-4" id="empty-cart-message" style="display: none;">
                                <i class="w-icon-cart"
                                    style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
                                <p class="text-muted">Your cart is empty</p>
                                <a href="shop.php" class="btn btn-primary btn-sm btn-rounded">Start Shopping</a>
                            </div>
                        </div>
                        <div class="cart-total" id="cart-total-section">
                            <label>Subtotal:</label>
                            <span class="price" id="cart-subtotal">₹0.00</span>
                        </div>
                        <div class="cart-action">
                            <a href="cart.php" class="btn btn-dark btn-outline btn-rounded">View Cart</a>
                            <a href="cart.php" class="btn btn-primary btn-rounded">Checkout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="containt-sticy2 containt-sticy1 mb-2">
        <div class="container">
            <div class="mobile-search-stick-positon">
                <form method="get" action="#" class="mobile-search-stick header-search d-md-flex">

                    <div class="search-input-wrapper">
                        <input type="text" class="form-control bg-white pt-0 pb-0" name="search" id="mobileSearch"
                            placeholder="Search in..." required="">
                        <div class="search-suggestions" id="mobileSearchSuggestions">
                            <!-- Dynamic search results will be populated here -->
                        </div>
                    </div>
                    <button class="btn btn-search bg-white" type="submit">
                        <i class="w-icon-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <!-- End of Header Middle -->
</header>
<!-- End of Header -->

<!-- Search Suggestions JavaScript -->
<script>
    // Initialize login/register modal
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof jQuery !== 'undefined' && jQuery.magnificPopup) {
            jQuery(document).on('click', 'a.login.sign-in, a.login.register', function(e) {
                e.preventDefault();
                var src = jQuery(this).attr('href') || '<?php echo PUBLIC_ASSETS; ?>ajax/login.html';
                jQuery.magnificPopup.open({
                    items: {
                        src: src,
                        type: 'ajax'
                    },
                    closeOnBgClick: true,
                    removalDelay: 300,
                    mainClass: 'mfp-fade login-modal',
                    callbacks: {
                        ajaxContentAdded: function() {
                            // Ensure first tab active
                            var $popup = jQuery('.mfp-content .login-popup');
                            $popup.find('.nav-link').removeClass('active');
                            $popup.find('.tab-pane').removeClass('active');
                            $popup.find('a[href="#sign-in"]').addClass('active');
                            $popup.find('#sign-in').addClass('active');
                        }
                    }
                });
            });
        }
    });
</script>

<div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
<script>
    window.showToast = function(message, type = 'info', duration = 3000) {
        var container = document.getElementById('toastContainer');
        if (!container) return;
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + (type === 'success' ? 'success' : type === 'error' ? 'error' : 'info');
        toast.innerHTML = '<span>' + (message || '') + '</span>' +
            '<button class="toast-close" aria-label="Close" onclick="this.parentElement.remove()">×</button>';
        container.appendChild(toast);
        setTimeout(function() {
            if (toast && toast.parentElement) {
                toast.remove();
            }
        }, duration);
    };
</script>

<script>
    // Delegated handlers for login/register modal
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof jQuery === 'undefined') return;
        var $doc = jQuery(document);

        // Tab switching (fallback)
        $doc.on('click', '.mfp-content .login-popup .nav-link', function(e) {
            e.preventDefault();
            var $link = jQuery(this);
            var target = $link.attr('href');
            var $container = $link.closest('.login-popup');
            $container.find('.nav-link').removeClass('active');
            $container.find('.tab-pane').removeClass('active');
            $link.addClass('active');
            $container.find(target).addClass('active');
        });

        // Referral choice toggle
        $doc.on('change', '.mfp-content .login-popup input[name="refer_choice"]', function() {
            var $popup = jQuery(this).closest('.login-popup');
            if (jQuery(this).val() === 'yes') {
                $popup.find('#referral-box').show();
            } else {
                $popup.find('#referral-box').hide();
                $popup.find('#ref-success, #ref-error').hide();
                $popup.find('#referral_code').val('');
            }
        });

        // Verify referral
        $doc.on('click', '.mfp-content .login-popup #verify-referral', function() {
            var $popup = jQuery(this).closest('.login-popup');
            var code = $popup.find('#referral_code').val();
            if (!code || code.trim() === '') return;
            jQuery.post('<?php echo USER_BASEURL; ?>/api/check_referral.php', {
                referral_code: code
            }, function(data) {
                try {
                    var res = typeof data === 'string' ? JSON.parse(data) : data;
                    if (res.status === 'success') {
                        $popup.find('#ref-success').text(res.name + "’s referral code has been applied successfully.").show();
                        $popup.find('#ref-error').hide();
                    } else {
                        $popup.find('#ref-error').text(res.message || 'Oops! That referral code doesn’t exist.').show();
                        $popup.find('#ref-success').hide();
                    }
                } catch (e) {
                    $popup.find('#ref-error').text('Unable to verify referral.').show();
                    $popup.find('#ref-success').hide();
                }
            });
        });

        // Login submit
        $doc.on('submit', '.mfp-content #modal-login-form', function(e) {
            e.preventDefault();
            var $form = jQuery(this);
            var $msg = $form.find('#login-message');
            var $btn = $form.find('#login-submit');
            $msg.removeClass('text-red text-green').hide();
            $btn.prop('disabled', true).text('Signing in...');
            jQuery.post('<?php echo USER_BASEURL; ?>/api/login.php', $form.serialize())
                .done(function(res) {
                    var data = typeof res === 'string' ? JSON.parse(res) : res;
                    if (data.status === 'success') {
                        $msg.addClass('text-green').text(data.message || 'Login successful.').show();
                        if (window.showToast) {
                            window.showToast(data.message || 'Login successful.', 'success');
                        }
                        // Safe to migrate HERE because login sets $_SESSION['user_id'] immediately in api/login.php
                        migrateLocalStorageToDatabase().finally(function() {
                            setTimeout(function() {
                                if (data.redirect) {
                                    window.location.href = data.redirect;
                                } else {
                                    window.location.href = 'index.php';
                                }
                            }, 600);
                        });
                    } else {
                        $msg.addClass('text-red').text(data.message || 'Login failed.').show();
                        if (window.showToast) {
                            window.showToast(data.message || 'Login failed.', 'error');
                        }
                        // If there's a redirect in the error response, redirect to OTP page
                        if (data.redirect) {
                            setTimeout(function() {
                                window.location.href = data.redirect;
                            }, 1000);
                        }
                    }
                })
                .fail(function() {
                    $msg.addClass('text-red').text('Network error. Try again.').show();
                    if (window.showToast) {
                        window.showToast('Network error. Try again.', 'error');
                    }
                })
                .always(function() {
                    $btn.prop('disabled', false).text('Sign In');
                });
        });

        // Register submit
        $doc.on('submit', '.mfp-content #modal-register-form', function(e) {
            e.preventDefault();
            var $form = jQuery(this);
            var $msg = $form.find('#register-message');
            var $btn = $form.find('#register-submit');
            $msg.removeClass('text-red text-green').hide();
            $btn.prop('disabled', true).text('Signing up...');
            jQuery.post('<?php echo USER_BASEURL; ?>/api/register.php', $form.serialize())
                .done(function(res) {
                    debugger;
                    (async () => {
                        var data = typeof res === 'string' ? JSON.parse(res) : res;
                        if (data.status === 'success') {
                            $msg.addClass('text-green').text(data.message || 'Registration successful.').show();
                            if (window.showToast) {
                                window.showToast(data.message || 'Registration successful.', 'success');
                            }

                            // Get form data for associate API call
                            var formData = new FormData($form[0]);
                            var firstName = formData.get('first_name') || '';
                            var lastName = formData.get('last_name') || '';
                            var mobile = formData.get('mobile') || '';
                            var referral_code = formData.get('referral_code') || 'H000000001';
                            // var password = formData.get('password') || '';
                            var agree = formData.get('agree') || '';

                            const insertAssociate = {
                                "firstName": firstName,
                                "lastName": lastName,
                                "phoneNumber": mobile,
                                "ReEnterphoneNumber": mobile,
                                "ReferralId": referral_code,
                                "pincode": 0,
                                "username": data.customer_id,
                                // "Password": password,
                                // "ReEnterpassword": password,
                                "acceptTermsNConditions": agree === "on" ? true : false
                            }

                            const response1 = await fetch('<?php echo ADMIN_BASEURL; ?>admins/registerAssociate', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify(insertAssociate)
                            });

                            const result1 = await response1.json();
                            console.log('Billing API Response:', result1);
                            debugger;
                            
                            // IMPORTANT: Do NOT migrate here. User must verify OTP first.
                            console.log('Registration successful, redirecting to:', data.redirect);
                            setTimeout(function() {
                                if (data.redirect) {
                                    console.log('Redirecting to:', data.redirect);
                                    window.location.href = data.redirect;
                                } else {
                                    console.log('No redirect specified, going to otp.php');
                                    window.location.href = 'otp.php';
                                }
                            }, 600);
                        } else {
                            $msg.addClass('text-red').text(data.message || 'Registration failed.').show();
                            if (window.showToast) {
                                window.showToast(data.message || 'Registration failed.', 'error');
                            }
                        }
                    })();
                })
                .fail(function() {
                    $msg.addClass('text-red').text('Network error. Try again.').show();
                    if (window.showToast) {
                        window.showToast('Network error. Try again.', 'error');
                    }
                })
                .always(function() {
                    $btn.prop('disabled', false).text('Sign Up');
                });
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search');
        const mobileSearchInput = document.getElementById('mobileSearch');
        const searchSuggestions = document.getElementById('searchSuggestions');
        const mobileSearchSuggestions = document.getElementById('mobileSearchSuggestions');
        const categorySelect = document.getElementById('category');
        const subCategorySelect = document.getElementById('sub-category');

        let searchTimeout;

        // Function to perform search
        function performSearch(input, suggestionsContainer) {
            const searchTerm = input.value.trim();
            const categoryId = categorySelect ? categorySelect.value : '';
            const subCategoryId = subCategorySelect ? subCategorySelect.value : '';

            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            // If search term is empty, hide suggestions
            if (searchTerm.length === 0) {
                suggestionsContainer.innerHTML = '';
                suggestionsContainer.classList.remove('show');
                return;
            }

            // Debounce search - wait 300ms after user stops typing
            searchTimeout = setTimeout(function() {
                // Build API URL
                let apiUrl = '<?php echo USER_BASEURL; ?>/api/search_products.php?q=' + encodeURIComponent(searchTerm);
                if (categoryId) {
                    apiUrl += '&category=' + encodeURIComponent(categoryId);
                }
                if (subCategoryId) {
                    apiUrl += '&subcategory=' + encodeURIComponent(subCategoryId);
                }

                // Show loading state
                suggestionsContainer.innerHTML = '<div class="suggestion-item"><div class="suggestion-content"><div class="suggestion-title">Searching...</div></div></div>';
                suggestionsContainer.classList.add('show');

                // Fetch search results
                fetch(apiUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            displaySearchResults(data.data, suggestionsContainer, input);
                        } else {
                            suggestionsContainer.innerHTML = '<div class="suggestion-item"><div class="suggestion-content"><div class="suggestion-title">No products found</div></div></div>';
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        suggestionsContainer.innerHTML = '<div class="suggestion-item"><div class="suggestion-content"><div class="suggestion-title">Search error occurred</div></div></div>';
                    });
            }, 300);
        }

        // Function to display search results
        function displaySearchResults(products, container, input) {
            if (products.length === 0) {
                container.innerHTML = '<div class="suggestion-item"><div class="suggestion-content"><div class="suggestion-title">No products found</div></div></div>';
                return;
            }

            let html = '';
            products.forEach(function(product) {
                html += `
                        <div class="suggestion-item" data-url="${product.url}">
                            <div class="suggestion-icon">
                                <img src="${product.image}" alt="${product.name}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                            </div>
                            <div class="suggestion-content">
                                <div class="suggestion-title">${product.name}</div>
                                <div class="suggestion-price">${product.price}${product.coin > 0 ? ' + ' + product.coin + ' coins' : ''}</div>
                            </div>
                        </div>
                    `;
            });

            container.innerHTML = html;

            // Add click handlers to suggestion items
            const suggestionItems = container.querySelectorAll('.suggestion-item[data-url]');
            suggestionItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    const url = this.getAttribute('data-url');
                    window.location.href = url;
                });
            });
        }

        // Desktop search event listeners
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                performSearch(this, searchSuggestions);
            });

            searchInput.addEventListener('focus', function() {
                if (this.value.trim().length > 0) {
                    searchSuggestions.classList.add('show');
                }
            });
        }

        // Mobile search event listeners
        if (mobileSearchInput) {
            mobileSearchInput.addEventListener('input', function() {
                performSearch(this, mobileSearchSuggestions);
            });

            mobileSearchInput.addEventListener('focus', function() {
                if (this.value.trim().length > 0) {
                    mobileSearchSuggestions.classList.add('show');
                }
            });
        }

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(event) {
            const isDesktopSearch = searchInput && (searchInput.contains(event.target) || searchSuggestions.contains(event.target));
            const isMobileSearch = mobileSearchInput && (mobileSearchInput.contains(event.target) || mobileSearchSuggestions.contains(event.target));

            if (!isDesktopSearch) {
                searchSuggestions.classList.remove('show');
            }
            if (!isMobileSearch) {
                mobileSearchSuggestions.classList.remove('show');
            }
        });

        // Hide suggestions on form submit
        const searchForms = document.querySelectorAll('form');
        searchForms.forEach(function(form) {
            form.addEventListener('submit', function() {
                searchSuggestions.classList.remove('show');
                mobileSearchSuggestions.classList.remove('show');
            });
        });

        // Trigger search when category or subcategory changes
        if (categorySelect) {
            categorySelect.addEventListener('change', function() {
                if (searchInput && searchInput.value.trim().length > 0) {
                    performSearch(searchInput, searchSuggestions);
                }
                if (mobileSearchInput && mobileSearchInput.value.trim().length > 0) {
                    performSearch(mobileSearchInput, mobileSearchSuggestions);
                }
            });
        }

        if (subCategorySelect) {
            subCategorySelect.addEventListener('change', function() {
                if (searchInput && searchInput.value.trim().length > 0) {
                    performSearch(searchInput, searchSuggestions);
                }
                if (mobileSearchInput && mobileSearchInput.value.trim().length > 0) {
                    performSearch(mobileSearchInput, mobileSearchSuggestions);
                }
            });
        }
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const stickyElement = document.querySelector('.containt-sticy2');

        window.addEventListener('scroll', function() {
            if (window.scrollY >= 60) {
                stickyElement.classList.add('containt-sticy');
            } else {
                stickyElement.classList.remove('containt-sticy');
            }
        });
    });

    // Function to load sub-categories based on selected category
    // Function to load sub-categories based on selected category
    function loadSubCategories(categoryId) {
        const subCategorySelect = document.getElementById('sub-category');

        // Clear existing options except the first one
        while (subCategorySelect.children.length > 1) {
            subCategorySelect.removeChild(subCategorySelect.lastChild);
        }

        // Show loading state
        subCategorySelect.disabled = true;
        const loadingOption = document.createElement('option');
        loadingOption.textContent = 'Loading...';
        subCategorySelect.appendChild(loadingOption);

        // Prepare API URL
        const apiUrl = categoryId ?
            '<?php echo USER_BASEURL; ?>api/get_subcategories.php?category_id=' + encodeURIComponent(categoryId) :
            '<?php echo USER_BASEURL; ?>/api/get_subcategories.php';

        // Fetch sub-categories via AJAX
        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                // Remove loading option
                subCategorySelect.removeChild(loadingOption);

                if (data.success) {
                    // Add new options
                    data.subcategories.forEach(subcategory => {
                        const option = document.createElement('option');
                        option.value = subcategory.id;
                        option.textContent = subcategory.name;
                        subCategorySelect.appendChild(option);
                    });

                    // If no sub-categories, add a placeholder
                    if (data.subcategories.length === 0) {
                        const noSubOption = document.createElement('option');
                        noSubOption.value = '';
                        noSubOption.textContent = 'No Sub-Categories';
                        subCategorySelect.appendChild(noSubOption);
                    }
                } else {
                    console.error('Failed to load sub-categories:', data.message);
                    const errorOption = document.createElement('option');
                    errorOption.value = '';
                    errorOption.textContent = 'Error loading sub-categories';
                    subCategorySelect.appendChild(errorOption);
                }
            })
            .catch(error => {
                console.error('Error loading sub-categories:', error);
                const errorOption = document.createElement('option');
                errorOption.value = '';
                errorOption.textContent = 'Error loading sub-categories';
                subCategorySelect.appendChild(errorOption);
            })
            .finally(() => {
                // Re-enable the select
                subCategorySelect.disabled = false;
            });
    }
</script>

<!-- Wishlist Count JavaScript -->
<script>
    // Function to update wishlist count in header
    function updateHeaderWishlistCount() {
        const countElement = document.getElementById('wishlist-count');
        if (!countElement) return;

        // Check if user is logged in
        const isLoggedIn = <?php echo (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) ? 'true' : 'false'; ?>;

        if (isLoggedIn) {
            // Logged in user - get count from database
            fetch('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'get_count'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const count = data.count || 0;
                        countElement.textContent = count;
                        countElement.style.display = count > 0 ? 'inline' : 'none';
                        console.log('Wishlist count updated for logged-in user:', count);
                    } else {
                        console.error('Failed to get wishlist count:', data.message);
                        countElement.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error getting wishlist count:', error);
                    countElement.style.display = 'none';
                });
        } else {
            // Guest user - get count from localStorage
            const wishlist = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');
            const count = wishlist.length;
            countElement.textContent = count;
            countElement.style.display = count > 0 ? 'inline' : 'none';
            console.log('Wishlist count updated for guest user:', count);
        }
    }

    // Update count on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Header: DOM loaded, updating wishlist count...');
        updateHeaderWishlistCount();

        // Check if user is logged in and has localStorage data that needs migration
        const isLoggedIn = <?php echo (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) ? 'true' : 'false'; ?>;
        if (isLoggedIn) {
            checkForMigration();
        }
    });

    // Check if localStorage data needs migration
    async function checkForMigration() {
        try {
            const guestCart = JSON.parse(localStorage.getItem('guest_cart') || '[]');
            const guestWishlist = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');

            if (guestCart.length > 0 || guestWishlist.length > 0) {
                console.log('Found localStorage data for logged-in user, offering migration...');

                // Show migration prompt with more details
                const cartCount = guestCart.length;
                const wishlistCount = guestWishlist.length;
                // const message = `We found ${cartCount} cart item(s) and ${wishlistCount} wishlist item(s) from before you logged in. Would you like to save them to your account?`;

                // if (confirm(message)) {
                await migrateLocalStorageToDatabase();
                // } else {
                //     // Show a manual migration option
                //     showManualMigrationOption();
                // }
            }
        } catch (error) {
            console.error('Error checking for migration:', error);
        }
    }

    // Show manual migration option
    function showManualMigrationOption() {
        const notification = document.createElement('div');
        notification.className = 'manual-migration-notification';
        notification.innerHTML = `
                <div style="padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; margin: 10px 0;">
                    <p style="margin: 0 0 10px 0; font-weight: bold;">Save your items to your account</p>
                    <p style="margin: 0 0 10px 0; font-size: 14px; color: #6c757d;">You have items in your cart and wishlist that aren't saved to your account yet.</p>
                    <button onclick="migrateLocalStorageToDatabase()" style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-right: 10px;">Save Now</button>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Later</button>
                </div>
            `;

        // Insert at the top of the page
        const header = document.querySelector('header');
        if (header) {
            header.insertAdjacentElement('afterend', notification);
        }
    }

    // Make the function globally available
    window.updateHeaderWishlistCount = updateHeaderWishlistCount;

    // Global wishlist count update function that can be called from anywhere
    window.updateWishlistCount = function() {
        updateHeaderWishlistCount();
    };
</script>


<!-- Cart Management JavaScript -->
<script>
    // Cart Manager Class
    class CartManager {
        constructor() {
            this.isLoggedIn = <?php echo (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) ? 'true' : 'false'; ?>;
            this.init();
        }

        init() {
            document.addEventListener('DOMContentLoaded', () => {
                this.updateHeaderCart();
                this.initEventListeners();
            });

            // Also update cart on page load if DOM is already ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    this.updateHeaderCart();
                });
            } else {
                // DOM is already ready
                this.updateHeaderCart();
            }
        }

        initEventListeners() {
            // Cart item remove buttons
            document.addEventListener('click', (e) => {
                if (e.target.closest('.cart-item-remove')) {
                    e.preventDefault();
                    const button = e.target.closest('.cart-item-remove');
                    const productId = button.getAttribute('data-product-id');
                    const selectedVariants = button.getAttribute('data-selected-variants');

                    let variants = {};
                    if (selectedVariants && selectedVariants !== 'null') {
                        try {
                            variants = JSON.parse(selectedVariants);
                        } catch (e) {
                            console.warn('Error parsing selected variants:', e);
                        }
                    }

                    this.removeFromCart(productId, variants);
                }
            });

            // Cart toggle
            const cartToggle = document.querySelector('.cart-toggle');
            if (cartToggle) {
                cartToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.updateHeaderCart(); // Refresh cart when opened
                });
            }
        }

        async addToCart(productId, quantity = 1, contextElement = null, selectedVariants = {}, variantPrices = {}) {
            try {
                if (this.isLoggedIn) {
                    const response = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/cart_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'add',
                            product_id: parseInt(productId),
                            quantity: parseInt(quantity),
                            selected_variants: selectedVariants,
                            variant_prices: variantPrices
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        // Show Wolmart-style minipopup
                        this.showMiniPopup({
                            productId,
                            quantity,
                            contextElement
                        });
                        this.updateHeaderCart();
                        return {
                            success: true,
                            data: data
                        };
                    } else {
                        this.showNotification(data.message || 'Failed to add to cart', 'error');
                        return {
                            success: false,
                            message: data.message
                        };
                    }
                } else {
                    // Guest user - use localStorage
                    let cart = JSON.parse(localStorage.getItem('guest_cart') || '[]');

                    // Create a unique key for this product with these variants
                    const variantKey = JSON.stringify(selectedVariants);
                    let existingItem;

                    if (Object.keys(selectedVariants).length === 0) {
                        // No variants - find any existing item for this product without variants
                        existingItem = cart.find(item =>
                            item.product_id === parseInt(productId) &&
                            (!item.selected_variants || Object.keys(item.selected_variants).length === 0)
                        );
                    } else {
                        // Has variants - find exact variant match
                        existingItem = cart.find(item =>
                            item.product_id === parseInt(productId) &&
                            JSON.stringify(item.selected_variants || {}) === variantKey
                        );
                    }

                    if (existingItem) {
                        existingItem.quantity += parseInt(quantity);
                    } else {
                        cart.push({
                            product_id: parseInt(productId),
                            quantity: parseInt(quantity),
                            selected_variants: selectedVariants,
                            variant_prices: variantPrices,
                            added_date: new Date().toISOString()
                        });
                    }

                    localStorage.setItem('guest_cart', JSON.stringify(cart));
                    // Show Wolmart-style minipopup for guest
                    this.showMiniPopup({
                        productId,
                        quantity,
                        contextElement
                    });
                    this.updateHeaderCart();
                    return {
                        success: true
                    };
                }
            } catch (error) {
                console.error('Error adding to cart:', error);
                this.showNotification('An error occurred. Please try again.', 'error');
                return {
                    success: false,
                    message: 'Network error'
                };
            }
        }

        async removeFromCart(productId, selectedVariants = {}) {
            try {
                if (this.isLoggedIn) {
                    const response = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/cart_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'remove',
                            product_id: parseInt(productId),
                            selected_variants: selectedVariants
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.showNotification(data.message, 'info');
                        this.updateHeaderCart();

                        // Refresh the page if we're on cart page
                        if (window.location.pathname.includes('cart.php')) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        this.showNotification(data.message || 'Failed to remove from cart', 'error');
                    }
                } else {
                    // Guest user - remove specific variant from localStorage
                    let cart = JSON.parse(localStorage.getItem('guest_cart') || '[]');

                    if (Object.keys(selectedVariants).length === 0) {
                        // No variants - remove any item for this product without variants
                        cart = cart.filter(item =>
                            !(item.product_id === parseInt(productId) &&
                                (!item.selected_variants || Object.keys(item.selected_variants).length === 0))
                        );
                    } else {
                        // Has variants - remove exact variant match
                        const variantKey = JSON.stringify(selectedVariants);
                        cart = cart.filter(item =>
                            !(item.product_id === parseInt(productId) &&
                                JSON.stringify(item.selected_variants || {}) === variantKey)
                        );
                    }

                    localStorage.setItem('guest_cart', JSON.stringify(cart));
                    this.showNotification('Removed from cart', 'info');
                    this.updateHeaderCart();

                    // Refresh the page if we're on cart page
                    if (window.location.pathname.includes('cart.php')) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                }
            } catch (error) {
                console.error('Error removing from cart:', error);
                this.showNotification('An error occurred. Please try again.', 'error');
            }
        }

        async updateHeaderCart() {
            const countElement = document.getElementById('cart-count');
            const itemsContainer = document.getElementById('header-cart-items');
            const subtotalElement = document.getElementById('cart-subtotal');
            const totalSection = document.getElementById('cart-total-section');
            const emptyMessage = document.getElementById('empty-cart-message');

            if (!countElement || !itemsContainer) return;

            try {
                if (this.isLoggedIn) {
                    // Get cart data from database
                    const response = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/cart_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'get_items'
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.renderCartItems(data.items, data.total, data.count);
                    } else {
                        this.showEmptyCart();
                    }
                } else {
                    // Guest user - get from localStorage and fetch product details
                    const cart = JSON.parse(localStorage.getItem('guest_cart') || '[]');
                    if (cart.length === 0) {
                        this.showEmptyCart();
                        return;
                    }

                    const productIds = cart.map(item => item.product_id).join(',');
                    const response = await fetch('<?php echo USER_BASEURL; ?>/app/Helpers/get_products.php?ids=' + productIds);
                    const data = await response.json();

                    if (data.success && data.products.length > 0) {
                        const cartItems = this.mergeCartWithProducts(cart, data.products);
                        const total = cartItems.reduce((sum, item) => sum + (item.selling_price * item.quantity), 0);
                        const count = cartItems.reduce((sum, item) => sum + item.quantity, 0);
                        this.renderCartItems(cartItems, total, count);
                    } else {
                        this.showEmptyCart();
                    }
                }
            } catch (error) {
                console.error('Error updating cart:', error);
                this.showEmptyCart();
            }
        }

        mergeCartWithProducts(cart, products) {
            const baseImageUrl = '<?php echo $vendor_baseurl; ?>';
            const defaultImage = '<?php echo $vendor_baseurl; ?>uploads/vendors/no-product.png';

            return cart.map(cartItem => {
                const product = products.find(p => p.id == cartItem.product_id);
                if (!product) return null;

                // Process images
                let image = defaultImage;
                if (product.images) {
                    try {
                        const images = JSON.parse(product.images);
                        if (Array.isArray(images) && images[0]) {
                            // image = baseImageUrl + images[0];
                            image = images[0];
                        }
                    } catch (e) {
                        // Keep default image
                    }
                }

                // Use variant price if available, otherwise use base price
                let finalPrice = parseFloat(product.selling_price);
                if (cartItem.variant_prices && cartItem.variant_prices.selling_price) {
                    finalPrice = parseFloat(cartItem.variant_prices.selling_price);
                }

                return {
                    product_id: product.id,
                    product_name: product.product_name,
                    selling_price: finalPrice,
                    base_price: parseFloat(product.selling_price),
                    quantity: cartItem.quantity,
                    subtotal: finalPrice * cartItem.quantity,
                    image: image,
                    inventory: product.Inventory || 0,
                    selected_variants: cartItem.selected_variants || {},
                    variant_prices: cartItem.variant_prices || {}
                };
            }).filter(item => item !== null);
        }

        renderCartItems(items, total, count) {
            const countElement = document.getElementById('cart-count');
            const itemsContainer = document.getElementById('header-cart-items');
            const subtotalElement = document.getElementById('cart-subtotal');
            const totalSection = document.getElementById('cart-total-section');
            const emptyMessage = document.getElementById('empty-cart-message');

            // Update count
            countElement.textContent = count;
            countElement.style.display = count > 0 ? 'inline' : 'none';

            // Also update sticky footer cart count if it exists
            const stickyCartCount = document.getElementById('sticky-cart-count');
            if (stickyCartCount) {
                stickyCartCount.textContent = count;
                stickyCartCount.style.display = count > 0 ? 'inline' : 'none';
            }

            if (items.length === 0) {
                this.showEmptyCart();
                return;
            }

            // Hide empty message
            if (emptyMessage) emptyMessage.style.display = 'none';

            // Show total section
            if (totalSection) totalSection.style.display = 'block';

            // Update subtotal
            if (subtotalElement) {
                subtotalElement.textContent = this.formatINR(total);
            }

            // Render items
            let html = '';
            items.forEach(item => {
                // Build variant display
                let variantDisplay = '';
                if (item.selected_variants && Object.keys(item.selected_variants).length > 0) {
                    variantDisplay = '<div class="cart-variants">';
                    if (item.selected_variants.color) {
                        variantDisplay += `<small class="variant-item">
                                <span class="color-swatch" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: ${this.escapeHtml(item.selected_variants.color)}; margin-right: 4px; vertical-align: middle;"></span>
                                ${this.escapeHtml(item.selected_variants.color)}
                            </small>`;
                    }
                    if (item.selected_variants.size) {
                        variantDisplay += `<small class="variant-item">Size: ${this.escapeHtml(item.selected_variants.size)}</small>`;
                    }
                    variantDisplay += '</div>';
                }

                // Show variant price if different from base price
                let priceDisplay = this.formatINR(item.selling_price);
                // if (item.selling_price !== item.base_price) {
                //     priceDisplay += `<br><small class="text-muted">Base: ₹${item.base_price.toFixed(2)}</small>`;
                // }

                html += `
                        <div class="product product-cart" data-product-id="${item.product_id}">
                            <div class="product-detail">
                                <a href="product-detail.php?id=${item.product_id}" class="product-name">
                                    ${this.escapeHtml(item.product_name)}
                                </a>
                                ${variantDisplay}
                                <div class="price-box">
                                    <span class="product-quantity">${item.quantity}</span>
                                    <span class="product-price">${priceDisplay}</span>
                                </div>
                            </div>
                            <figure class="Shopping Cart">
                                <a href="product-detail.php?id=${item.product_id}">
                                    <img src="${item.image}" alt="product" width="84" height="94"
                                         onerror="this.src='<?php echo $vendor_baseurl; ?>uploads/vendors/no-product.png';">
                                </a>
                            </figure>
                            <button class="btn btn-link btn-close cart-item-remove" 
                                    data-product-id="${item.product_id}"
                                    data-selected-variants="${JSON.stringify(item.selected_variants || {}).replace(/"/g, '&quot;')}">
                                <img src="<?php echo PUBLIC_ASSETS; ?>images/trash-icon.svg" alt="">
                            </button>
                        </div>
                    `;
            });

            itemsContainer.innerHTML = html;
        }

        // Format currency with Indian number system commas and two decimals
        formatINR(amount) {
            try {
                const num = Number(amount) || 0;
                return '₹' + num.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            } catch (e) {
                return '₹' + (Number(amount) || 0).toFixed(2);
            }
        }

        showEmptyCart() {
            const countElement = document.getElementById('cart-count');
            const itemsContainer = document.getElementById('header-cart-items');
            const totalSection = document.getElementById('cart-total-section');
            const emptyMessage = document.getElementById('empty-cart-message');

            // Hide count
            if (countElement) {
                countElement.textContent = '0';
                countElement.style.display = 'none';
            }

            // Also hide sticky footer cart count if it exists
            const stickyCartCount = document.getElementById('sticky-cart-count');
            if (stickyCartCount) {
                stickyCartCount.textContent = '0';
                stickyCartCount.style.display = 'none';
            }

            // Hide total section
            if (totalSection) totalSection.style.display = 'none';

            // Show empty message
            if (emptyMessage) emptyMessage.style.display = 'block';

            // Clear items
            if (itemsContainer) {
                itemsContainer.innerHTML = '<div class="empty-cart text-center py-4"><i class="w-icon-cart" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i><p class="text-muted">Your cart is empty</p><a href="shop.php" class="btn btn-primary btn-sm btn-rounded">Start Shopping</a></div>';
            }
        }

        // Show Wolmart-style minipopup (unified look across site)
        showMiniPopup({
            productId,
            quantity = 1,
            contextElement = null
        }) {
            try {
                const wrapper = contextElement || document.querySelector(`.product a[href*="product-detail.php?id=${productId}"]`)?.closest('.product') || document.querySelector('.product-popup');
                const nameEl = wrapper ? (wrapper.querySelector('.product-name a, .product-title a, .product-title')) : null;
                const imgEl = wrapper ? (wrapper.querySelector('.product-media img, .product-image img')) : null;
                const name = nameEl ? (nameEl.textContent || nameEl.innerText || '').trim() : 'Product';
                const nameLink = nameEl && nameEl.getAttribute ? (nameEl.getAttribute('href') || `product-detail.php?id=${productId}`) : `product-detail.php?id=${productId}`;
                const imageSrc = imgEl ? (imgEl.getAttribute('src') ||
                    imgEl.getAttribute('data-src')) : (window.NOTIFICATION_PLACEHOLDER_IMAGE || '<?php echo $vendor_baseurl; ?>uploads/vendors/no-product.png');
                const imageLink = wrapper ? (wrapper.querySelector('.product-name a')?.getAttribute('href') || nameLink) : nameLink;

                if (window.Wolmart && Wolmart.Minipopup && typeof Wolmart.Minipopup.open === 'function') {
                    Wolmart.Minipopup.open({
                        productClass: ' product-cart',
                        imageSrc: imageSrc,
                        imageLink: imageLink,
                        name: name,
                        nameLink: nameLink,
                        message: '<p>has been added to cart:</p>',
                        actionTemplate: '<a href="cart.php" class="btn btn-rounded btn-sm">View Cart</a><a href="cart.php" class="btn btn-dark btn-rounded btn-sm">Checkout</a>'
                    });
                } else {
                    // Fallback toast if Minipopup is not available
                    this.showNotification('Product added to cart successfully!', 'success');
                }
            } catch (e) {
                console.error('Minipopup error:', e);
                this.showNotification('Product added to cart', 'success');
            }
        }

        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `cart-notification cart-notification-${type}`;

            const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
            notification.innerHTML = `<span style="margin-right: 8px; font-weight: bold;">${icon}</span>${message}`;

            notification.style.cssText = `
                    position: fixed; top: 20px; right: 20px; color: white; padding: 16px 24px;
                    z-index: 10000; font-size: 14px; border-radius: 8px; max-width: 350px;
                    background: ${type === 'success' ? 'linear-gradient(135deg, #2ecc71, #27ae60)' :
                    type === 'error' ? 'linear-gradient(135deg, #e74c3c, #c0392b)' :
                        'linear-gradient(135deg, #3498db, #2980b9)'};
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    animation: slideInRight 0.3s ease;
                `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize cart manager
    const cartManager = new CartManager();

    // Make functions globally available
    window.cartManager = cartManager;
    window.addToCart = (productId, quantity) => cartManager.addToCart(productId, quantity);
    window.updateHeaderCart = () => cartManager.updateHeaderCart();

    // Migration function to move localStorage data to database
    async function migrateLocalStorageToDatabase() {
        try {
            console.log('Starting localStorage to database migration...');

            // Get localStorage data
            const guestCart = JSON.parse(localStorage.getItem('guest_cart') || '[]');
            const guestWishlist = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');

            console.log('Found localStorage data:', {
                cart: guestCart,
                wishlist: guestWishlist
            });

            // Prepare migration data
            const migrationData = {
                cart: guestCart,
                wishlist: guestWishlist
            };

            // Send to server for migration
            const response = await fetch('migrate_localstorage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(migrationData)
            });

            const result = await response.json();
            console.log('Migration response:', result);

            if (result.success) {
                console.log('Migration successful:', result);

                // Clear localStorage after successful migration
                localStorage.removeItem('guest_cart');
                localStorage.removeItem('guest_wishlist');

                // Update UI to reflect migrated data
                if (window.cartManager) {
                    window.cartManager.updateHeaderCart();
                }
                if (window.updateHeaderWishlistCount) {
                    window.updateHeaderWishlistCount();
                }

                // Show success notification with details
                // const message = `Your cart and wishlist have been saved! (${result.migrated_cart} cart items, ${result.migrated_wishlist} wishlist items)`;
                // showMigrationNotification(message, 'success');
            } else {
                console.error('Migration failed:', result);
                const errorMessage = result.message || 'Failed to save your cart and wishlist. Please try again.';
                showMigrationNotification(errorMessage, 'error');

                // Log detailed error information
                if (result.debug_info) {
                    console.error('Migration debug info:', result.debug_info);
                }
            }

            return result;

        } catch (error) {
            console.error('Migration error:', error);

            let errorMessage = 'An error occurred while saving your data.';

            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                errorMessage = 'Network error. Please check your connection and try again.';
            } else if (error.message) {
                errorMessage = `Error: ${error.message}`;
            }

            showMigrationNotification(errorMessage, 'error');
            throw error;
        }
    }

    // Show migration notification
    function showMigrationNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `migration-notification migration-notification-${type}`;

        const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
        notification.innerHTML = `<span style="margin-right: 8px; font-weight: bold;">${icon}</span>${message}`;

        notification.style.cssText = `
                position: fixed; top: 80px; right: 20px; color: white; padding: 16px 24px;
                z-index: 10001; font-size: 14px; border-radius: 8px; max-width: 350px;
                background: ${type === 'success' ? 'linear-gradient(135deg, #2ecc71, #27ae60)' :
                type === 'error' ? 'linear-gradient(135deg, #e74c3c, #c0392b)' :
                    'linear-gradient(135deg, #3498db, #2980b9)'};
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                animation: slideInRight 0.3s ease;
            `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    // Make migration function globally available
    window.migrateLocalStorageToDatabase = migrateLocalStorageToDatabase;
</script>

<!-- Login Required Modal -->
<div id="loginRequiredModal" class="login-required-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-icon">
            <i class="fas fa-user-lock"></i>
        </div>
        <h3 class="modal-title">Login Required</h3>
        <p class="modal-message">Please login to your account to proceed with checkout, add addresses and manage your
            cart.</p>
        <button class="modal-login-btn" onclick="window.location.href='login.php'">Login Now</button>
    </div>
</div>


<script>
    // Login Required Modal Functionality
    class LoginRequiredModal {
        constructor() {
            this.modal = document.getElementById('loginRequiredModal');
            this.init();
        }

        init() {
            // Close modal when clicking overlay
            if (this.modal) {
                const overlay = this.modal.querySelector('.modal-overlay');
                if (overlay) {
                    overlay.addEventListener('click', () => this.hide());
                }
            }
        }

        show() {
            if (this.modal) {
                this.modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';

                // Add animation
                setTimeout(() => {
                    this.modal.style.opacity = '1';
                }, 10);
            }
        }

        hide() {
            if (this.modal) {
                this.modal.style.opacity = '0';
                setTimeout(() => {
                    this.modal.style.display = 'none';
                    document.body.style.overflow = '';
                }, 300);
            }
        }
    }

    // Initialize modal
    const loginRequiredModal = new LoginRequiredModal();

    // Make it globally available
    window.loginRequiredModal = loginRequiredModal;
</script>
<!-- defensive-header.js (put in <head>) -->

<script>
    // document.addEventListener('keydown', function (e) {
    //     // Disable F12
    //     if (e.key === "F12") {
    //         e.preventDefault();
    //         return false;
    //     }

    //     // Disable Ctrl+Shift+I
    //     if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'i') {
    //         e.preventDefault();
    //         return false;
    //     }

    //     // Disable Ctrl+Shift+C
    //     if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'c') {
    //         e.preventDefault();
    //         return false;
    //     }

    //     // Disable Ctrl+U
    //     if (e.ctrlKey && e.key.toLowerCase() === 'u') {
    //         e.preventDefault();
    //         return false;
    //     }
    // });

    // // Optional: Disable right-click
    // document.addEventListener('contextmenu', function (e) {
    //     e.preventDefault();
    // });
</script>
<!-- Input Sanitization Script -->
<script src="<?php echo USER_BASEURL; ?>app/includes/security.js"></script>