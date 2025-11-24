<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}
$editing_product_id = isset($_GET['id']) ? trim($_GET['id']) : '';
$existing_product = null;

// Clear session data for OTHER products when switching to edit a different product
if (!empty($editing_product_id)) {
    // Clear session data for all other products (keep only current product's data)
    $current_product_session_keys = [
        'pending_product_images_admin_' . $editing_product_id,
        'pending_product_videos_admin_' . $editing_product_id,
        'pending_product_video_admin_' . $editing_product_id,
        'pending_product_path_admin_' . $editing_product_id,
        'pending_product_payload_admin_' . $editing_product_id,
        'existing_specifications_admin_' . $editing_product_id
    ];
    
    // Clear old non-product-specific session keys (backward compatibility)
    if (!isset($_SESSION['editing_product_id']) || $_SESSION['editing_product_id'] !== $editing_product_id) {
        // We're switching to a different product, clear old session data
        unset($_SESSION['pending_product_images']);
        unset($_SESSION['pending_product_videos']);
        unset($_SESSION['pending_product_video']);
        unset($_SESSION['pending_product_path']);
        unset($_SESSION['existing_specifications']);
    }
    
    // Clear session data for other product IDs (admin-specific keys)
    foreach ($_SESSION as $key => $value) {
        if (preg_match('/^pending_product_(images|videos|video|path)_admin_(\d+)$/', $key, $matches)) {
            $other_product_id = $matches[2];
            if ($other_product_id !== $editing_product_id) {
                unset($_SESSION[$key]);
            }
        }
        if (preg_match('/^existing_specifications_admin_(\d+)$/', $key, $matches)) {
            $other_product_id = $matches[1];
            if ($other_product_id !== $editing_product_id) {
                unset($_SESSION[$key]);
            }
        }
    }
    
    $_SESSION['editing_product_id'] = $editing_product_id;
    
    $rs_prefill = mysqli_query($con, "SELECT * FROM products WHERE id={$editing_product_id} LIMIT 1");
    if ($rs_prefill && mysqli_num_rows($rs_prefill) > 0) {
        $existing_product = mysqli_fetch_assoc($rs_prefill);
        $vendor_reg_id = $existing_product['vendor_id'];
    }
}
// Inline AJAX: load subcategories without page refresh
if (isset($_GET['ajax']) && $_GET['ajax'] === 'subcategories') {
    header('Content-Type: application/json');
    $catId = isset($_GET['category_id']) ? mysqli_real_escape_string($con, $_GET['category_id']) : '';
    $items = [];
    if (!empty($catId)) {
        $rs = mysqli_query($con, "SELECT id, name FROM sub_category WHERE category_id = '$catId' ORDER BY name");
        if ($rs) {
            while ($r = mysqli_fetch_assoc($rs)) {
                $items[] = $r;
            }
        }
    }
    echo json_encode(['success' => true, 'subcategories' => $items]);
    exit;
}
// Fetch vendor business name (for folder path)
$vendor_business_name = '';
if (!empty($vendor_reg_id)) {
    $vd = mysqli_query($con, "SELECT business_name FROM vendor_registration WHERE id = '" . mysqli_real_escape_string($con, $vendor_reg_id) . "'");
    if ($vd && mysqli_num_rows($vd) > 0) {
        $vendor_data = mysqli_fetch_assoc($vd);
        $vendor_business_name = $vendor_data['business_name'];
    }
    // If business name is still empty, use a fallback
    if (empty($vendor_business_name)) {
        $vendor_business_name = 'vendor_' . $vendor_reg_id;
    }
} else {
    // If vendor_reg_id is empty, try to get it from the product being edited
    if (!empty($editing_product_id)) {
        $product_query = mysqli_query($con, "SELECT vendor_id FROM products WHERE id = '" . mysqli_real_escape_string($con, $editing_product_id) . "' LIMIT 1");
        if ($product_query && mysqli_num_rows($product_query) > 0) {
            $product_data = mysqli_fetch_assoc($product_query);
            $vendor_reg_id = $product_data['vendor_id'];

            // Now get the business name
            if (!empty($vendor_reg_id)) {
                $vd = mysqli_query($con, "SELECT business_name FROM vendor_registration WHERE id = '" . mysqli_real_escape_string($con, $vendor_reg_id) . "'");
                if ($vd && mysqli_num_rows($vd) > 0) {
                    $vendor_data = mysqli_fetch_assoc($vd);
                    $vendor_business_name = $vendor_data['business_name'];
                }
                // If business name is still empty, use a fallback
                if (empty($vendor_business_name)) {
                    $vendor_business_name = 'vendor_' . $vendor_reg_id;
                }
            }
        }
    }
}
// Pre-fill form data from existing product
// Priority: Session data (updated form data) > Database data (original data)
$form_data = [];
$session_payload_key = !empty($editing_product_id) ? 'pending_product_payload_admin_' . $editing_product_id : 'pending_product_payload';
$session_payload_json = isset($_SESSION[$session_payload_key]) ? $_SESSION[$session_payload_key] : '';
$decoded_session_data = null; // Initialize to track if we loaded from session

// First, try to load from session (updated form data)
if (!empty($session_payload_json)) {
    $decoded_session_data = json_decode($session_payload_json, true);
    if (is_array($decoded_session_data) && !empty($decoded_session_data)) {
        $form_data = $decoded_session_data;
        error_log("Loaded form data from session for product " . ($editing_product_id ?? 'new'));
    }
}

// If no session data or if we have existing product, merge with database data
if ($existing_product) {
    // If we have session data, merge it with database data (session takes priority)
    // If no session data, use database data as base
    if (empty($form_data)) {
        $form_data = [
            'product_name' => $existing_product['product_name'] ?? '',
            'category_id' => $existing_product['category_id'] ?? '',
            'sub_category_id' => $existing_product['sub_category_id'] ?? '',
            'mrp' => $existing_product['mrp'] ?? '',
            'selling_price' => $existing_product['selling_price'] ?? '',
            'discount' => $existing_product['discount'] ?? '',
            'description' => $existing_product['description'] ?? '',
            'brand' => $existing_product['brand'] ?? '',
            'gst' => $existing_product['gst'] ?? '',
            'hsn_id' => $existing_product['hsn_id'] ?? '',
            'manufacture_details' => $existing_product['manufacture_details'] ?? '',
            'packaging_details' => $existing_product['packaging_details'] ?? '',
            'sku_id' => $existing_product['sku_id'] ?? '',
            'group_id' => $existing_product['group_id'] ?? '',
            'style_id' => $existing_product['style_id'] ?? '',
            'inventory' => $existing_product['Inventory'] ?? '',
            'product_brand' => $existing_product['product_brand'] ?? '',
            'video' => $existing_product['video'] ?? '',
        ];
    } else {
        // Merge session data with database data to ensure all fields are present
        // Session data takes priority, but fill missing fields from database
        $form_data = array_merge([
            'product_name' => $existing_product['product_name'] ?? '',
            'category_id' => $existing_product['category_id'] ?? '',
            'sub_category_id' => $existing_product['sub_category_id'] ?? '',
            'mrp' => $existing_product['mrp'] ?? '',
            'selling_price' => $existing_product['selling_price'] ?? '',
            'discount' => $existing_product['discount'] ?? '',
            'description' => $existing_product['description'] ?? '',
            'brand' => $existing_product['brand'] ?? '',
            'gst' => $existing_product['gst'] ?? '',
            'hsn_id' => $existing_product['hsn_id'] ?? '',
            'manufacture_details' => $existing_product['manufacture_details'] ?? '',
            'packaging_details' => $existing_product['packaging_details'] ?? '',
            'sku_id' => $existing_product['sku_id'] ?? '',
            'group_id' => $existing_product['group_id'] ?? '',
            'style_id' => $existing_product['style_id'] ?? '',
            'inventory' => $existing_product['Inventory'] ?? '',
            'product_brand' => $existing_product['product_brand'] ?? '',
            'video' => $existing_product['video'] ?? '',
        ], $form_data); // Session data (second parameter) overrides database data
    }
    // Handle existing images
    $existing_images = $existing_product['images'] ?? '';
    $existing_images_array = [];
    $database_images = []; // Track which images are in database
    if (!empty($existing_images)) {
        $decoded_images = json_decode($existing_images, true);
        if (is_array($decoded_images)) {
            $existing_images_array = $decoded_images;
            $database_images = $decoded_images; // Store original database images
        }
    }
    // Merge with session images (newly uploaded but not yet saved to DB)
    // This ensures newly uploaded images are displayed when returning from addProduct.php
    // Use product ID-specific session key (admin-specific)
    $session_images_key = 'pending_product_images_admin_' . $editing_product_id;
    $session_path_key = 'pending_product_path_admin_' . $editing_product_id;
    
    if (isset($_SESSION[$session_images_key]) && is_array($_SESSION[$session_images_key])) {
        $session_images = $_SESSION[$session_images_key];
        $pending_path = isset($_SESSION[$session_path_key]) ? $_SESSION[$session_path_key] : '';

        // Check if session images contain new files (filenames only) vs full paths
        foreach ($session_images as $img) {
            $img = trim($img);
            if (empty($img)) continue;

            // If it's a filename only (not a full path starting with 'uploads/'), it's a newly uploaded file
            if (!preg_match('/^uploads\//', $img)) {
                // This is a filename, need to build full path
                if (!empty($pending_path)) {
                    $full_path = $pending_path . '/' . ltrim($img, '/');
                    // Only add if not already in existing_images_array (check both full path and filename)
                    $already_exists = false;
                    foreach ($existing_images_array as $existing) {
                        if ($existing === $full_path || basename($existing) === $img) {
                            $already_exists = true;
                            break;
                        }
                    }
                    if (!$already_exists) {
                        $existing_images_array[] = $full_path;
                    }
                }
            } else {
                // It's already a full path, check if it's not a duplicate
                $already_exists = false;
                foreach ($existing_images_array as $existing) {
                    if ($existing === $img || basename($existing) === basename($img)) {
                        $already_exists = true;
                        break;
                    }
                }
                if (!$already_exists) {
                    $existing_images_array[] = $img;
                }
            }
        }
    }
    // Update session with merged images for consistency (using product ID-specific key)
    if (!empty($existing_images_array)) {
        $_SESSION[$session_images_key] = $existing_images_array;
    }
    // Handle existing specifications - preserve them in session for when user goes to next step
    // Use product ID-specific session key (admin-specific)
    $session_specs_key = 'existing_specifications_admin_' . $editing_product_id;
    $existing_specifications = $existing_product['specifications'] ?? '';
    if (!empty($existing_specifications)) {
        $decoded_specs = json_decode($existing_specifications, true);
        if (is_array($decoded_specs)) {
            $_SESSION[$session_specs_key] = $decoded_specs;
        }
    }
    // Store the product data in session for the next step (using product ID-specific key)
    // Only update session if we loaded from database (not from session), or if editing_product_id is missing
    $form_data['editing_product_id'] = $editing_product_id;
    $session_payload_key = 'pending_product_payload_admin_' . $editing_product_id;
    
    // Only update session if:
    // 1. We didn't load from session (empty($session_payload_json) or $decoded_session_data is null), OR
    // 2. The editing_product_id is missing from the session data
    if (empty($session_payload_json) || $decoded_session_data === null || !isset($decoded_session_data['editing_product_id'])) {
        $_SESSION[$session_payload_key] = json_encode($form_data);
        error_log("Updated session with form data for product " . $editing_product_id);
    } else {
        // Just ensure editing_product_id is set in the existing session data
        // Use the already decoded session data that we loaded earlier
        $decoded_session_data['editing_product_id'] = $editing_product_id;
        $_SESSION[$session_payload_key] = json_encode($decoded_session_data);
        error_log("Preserved existing session data and added editing_product_id for product " . $editing_product_id);
    }
}
// Selected filters from URL for dependent dropdowns or from existing product
$selected_category = isset($_GET['category']) ? $_GET['category'] : ($form_data['category_id'] ?? '');
$selected_subcategory = isset($_GET['subcategory']) ? $_GET['subcategory'] : ($form_data['sub_category_id'] ?? '');
// Handle image removal first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_image') {
    $image_path = isset($_POST['image_path']) ? trim($_POST['image_path']) : '';
    $product_id = isset($_POST['product_id']) ? trim($_POST['product_id']) : '';
    if (!empty($image_path) && !empty($product_id)) {
        $image_removed = false;
        // Get current images from database
        // In admin context, don't require vendor_id in WHERE clause
        $rs = mysqli_query($con, "SELECT images, vendor_id FROM products WHERE id='" . mysqli_real_escape_string($con, $product_id) . "' LIMIT 1");
        if ($rs && mysqli_num_rows($rs) > 0) {
            $row = mysqli_fetch_assoc($rs);
            $current_images = json_decode($row['images'], true);
            if (is_array($current_images)) {
                // Normalize paths for comparison
                $normalized_image_path = trim($image_path, '/');
                // Check if image exists in database (match by full path or basename)
                $image_in_db = false;
                foreach ($current_images as $db_img) {
                    $normalized_db_img = trim($db_img, '/');
                    if (
                        $normalized_db_img === $normalized_image_path ||
                        basename($normalized_db_img) === basename($normalized_image_path)
                    ) {
                        $image_in_db = true;
                        break;
                    }
                }
                if ($image_in_db) {
                    // Remove the image from the database array (match by full path or basename)
                    $updated_images = array_filter($current_images, function ($img) use ($image_path, $normalized_image_path) {
                        $normalized_img = trim($img, '/');
                        return $normalized_img !== $normalized_image_path &&
                            basename($normalized_img) !== basename($normalized_image_path);
                    });
                    // Update database with new image array
                    // Get vendor_id from the query result for the UPDATE
                    $product_vendor_id = isset($row['vendor_id']) ? $row['vendor_id'] : '';
                    $images_json = json_encode(array_values($updated_images), JSON_UNESCAPED_SLASHES);
                    if (!empty($product_vendor_id)) {
                        $update_sql = "UPDATE products SET images = '" . mysqli_real_escape_string($con, $images_json) . "' WHERE id = '" . mysqli_real_escape_string($con, $product_id) . "' AND vendor_id = '" . mysqli_real_escape_string($con, $product_vendor_id) . "'";
                    } else {
                        $update_sql = "UPDATE products SET images = '" . mysqli_real_escape_string($con, $images_json) . "' WHERE id = '" . mysqli_real_escape_string($con, $product_id) . "'";
                    }
                    if (mysqli_query($con, $update_sql)) {
                        $image_removed = true;
                    }
                }
            }
        }
        // Also remove from session if it exists there (for newly uploaded images not yet in DB)
        // Use product ID-specific session key (admin-specific)
        $session_images_key = 'pending_product_images_admin_' . $product_id;
        if (isset($_SESSION[$session_images_key]) && is_array($_SESSION[$session_images_key])) {
            $session_images = $_SESSION[$session_images_key];
            // Normalize paths for comparison (remove leading/trailing slashes)
            $normalized_image_path = trim($image_path, '/');
            $updated_session_images = array_filter($session_images, function ($img) use ($image_path, $normalized_image_path) {
                $normalized_img = trim($img, '/');
                // Match by full path (normalized) or by basename (filename)
                return $normalized_img !== $normalized_image_path &&
                    basename($normalized_img) !== basename($normalized_image_path);
            });
            $_SESSION[$session_images_key] = array_values($updated_session_images);
            $image_removed = true;
        }
        // Check if this is an AJAX request
        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        if ($image_removed) {
            if ($is_ajax) {
                // Return JSON response for AJAX requests
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Image removed successfully',
                    'image_path' => $image_path
                ]);
                exit;
            } else {
                // Success - redirect back to the same page (for non-AJAX requests)
                $redirect_url = 'first-edit-product.php?id=' . urlencode($product_id);
                header('Location: ' . $redirect_url);
                exit;
            }
        } else {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to remove image'
                ]);
                exit;
            }
        }
    }
}
// Handle video removal (supports comma-separated list)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_video') {
    $product_id = isset($_POST['product_id']) ? trim($_POST['product_id']) : '';
    $video_name_to_remove = isset($_POST['video_name']) ? trim($_POST['video_name']) : '';
    if (!empty($product_id)) {
        $rs = mysqli_query($con, "SELECT video, product_name FROM products WHERE id='" . mysqli_real_escape_string($con, $product_id) . "' AND vendor_id='" . mysqli_real_escape_string($con, $vendor_reg_id) . "' LIMIT 1");
        if ($rs && mysqli_num_rows($rs) > 0) {
            $row = mysqli_fetch_assoc($rs);
            $current_video_field = (string) ($row['video'] ?? '');
            $product_name = (string) ($row['product_name'] ?? '');
            // Parse videos using regex to handle commas within folder names
            $videos = [];
            if (!empty($current_video_field)) {
                // Match video file paths ending with common video extensions
                // Pattern: matches everything (including commas) until video extension, then comma or end
                preg_match_all('/(.*?\.(?:mp4|mov|avi|webm|mkv|flv|wmv|m4v)(?:\?.*)?)(?:,|$)/i', $current_video_field, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $vp) {
                        $vp = trim($vp);
                        if (!empty($vp)) {
                            $videos[] = $vp;
                        }
                    }
                } else {
                    // Fallback: if no video extension found, treat entire string as single video
                    $vp = trim($current_video_field);
                    if (!empty($vp)) {
                        $videos[] = $vp;
                    }
                }
            }
            // Compute vendor/product folder
            // $safe_vendor = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $vendor_business_name ?: ('vendor-' . $vendor_reg_id));
            // $safe_product = preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($product_name));
            $safe_vendor = $vendor_business_name ?: ('vendor-' . $vendor_reg_id);
            $safe_product = strtolower($product_name);
            
            // Remove requested or if only one provided, remove that
            $remaining = [];
            foreach ($videos as $vp) {
                $basename = basename($vp);
                if ($video_name_to_remove !== '' && $basename === $video_name_to_remove) {
                    // Build absolute path under public/uploads/vendors
                    $public_root = realpath(__DIR__ . '/../../../public');
                    if ($public_root === false) {
                        $public_root = __DIR__ . '/../../../public';
                    }
                    $video_path = rtrim($public_root, '/\\') . '/uploads/vendors/' . $safe_vendor . '/' . $safe_product . '/' . $basename;
                    if (file_exists($video_path)) {
                        @unlink($video_path);
                    }
                    continue;
                }
                if ($video_name_to_remove === '' && count($videos) === 1) {
                    $public_root = realpath(__DIR__ . '/../../../public');
                    if ($public_root === false) {
                        $public_root = __DIR__ . '/../../../public';
                    }
                    $video_path = rtrim($public_root, '/\\') . '/uploads/vendors/' . $safe_vendor . '/' . $safe_product . '/' . $basename;
                    if (file_exists($video_path)) {
                        @unlink($video_path);
                    }
                    continue;
                }
                $remaining[] = $vp;
            }
            $new_field = empty($remaining) ? NULL : implode(',', $remaining);
            $update_sql = "UPDATE products SET video = " . ($new_field === NULL ? "NULL" : "'" . mysqli_real_escape_string($con, $new_field) . "'") . " WHERE id='" . mysqli_real_escape_string($con, $product_id) . "' AND vendor_id='" . mysqli_real_escape_string($con, $vendor_reg_id) . "'";
            if (mysqli_query($con, $update_sql)) {
                $redirect_url = 'first-edit-product.php?id=' . urlencode($product_id);
                header('Location: ' . $redirect_url);
                exit;
            }
        }
    }
}
// Handle image upload (store to disk now, save to DB later in next step)
$upload_errors = [];
// Use product ID-specific session keys for edit mode, or regular keys for new products
if (!empty($editing_product_id)) {
    $session_images_key = 'pending_product_images_admin_' . $editing_product_id;
    $session_video_key = 'pending_product_video_admin_' . $editing_product_id;
    $session_videos_key = 'pending_product_videos_admin_' . $editing_product_id;
    $uploaded_file_names = isset($_SESSION[$session_images_key]) ? $_SESSION[$session_images_key] : [];
    $uploaded_video_name = isset($_SESSION[$session_video_key]) ? $_SESSION[$session_video_key] : '';
    $uploaded_video_names = isset($_SESSION[$session_videos_key]) && is_array($_SESSION[$session_videos_key]) ? $_SESSION[$session_videos_key] : [];
} else {
    $uploaded_file_names = isset($_SESSION['pending_product_images']) ? $_SESSION['pending_product_images'] : [];
    $uploaded_video_name = isset($_SESSION['pending_product_video']) ? $_SESSION['pending_product_video'] : '';
    $uploaded_video_names = isset($_SESSION['pending_product_videos']) && is_array($_SESSION['pending_product_videos']) ? $_SESSION['pending_product_videos'] : [];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_images') {
    // Basic server-side validations
    $product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
    if (strlen($product_name) === 0 || strlen($product_name) > 100) {
        $upload_errors[] = 'Product name is required and must be 100 characters or fewer.';
    }
    // Validate pricing from payload (Selling Price must be less than or equal to MRP)
    if (isset($_POST['form_payload']) && $_POST['form_payload'] !== '') {
        $payload_for_validation = json_decode($_POST['form_payload'], true);
        if (is_array($payload_for_validation)) {
            $mrp_raw = isset($payload_for_validation['mrp']) ? $payload_for_validation['mrp'] : '';
            $sp_raw = isset($payload_for_validation['selling_price']) ? $payload_for_validation['selling_price'] : '';
            $mrp_val = (float)preg_replace('/[^0-9.]/', '', (string)$mrp_raw);
            $sp_val = (float)preg_replace('/[^0-9.]/', '', (string)$sp_raw);
            if ($mrp_val > 0 && $sp_val > 0 && $sp_val > $mrp_val) {
                $upload_errors[] = 'Selling Price must be less than MRP.';
            }
        }
    }
    // Capture other form fields payload into session for next step
    if (isset($_POST['form_payload']) && $_POST['form_payload'] !== '') {
        // Use product ID-specific session key for edit mode
        if (!empty($editing_product_id)) {
            $session_payload_key = 'pending_product_payload_admin_' . $editing_product_id;
            $_SESSION[$session_payload_key] = $_POST['form_payload'];
            $_SESSION['editing_product_id'] = $editing_product_id;
        } else {
            $_SESSION['pending_product_payload'] = $_POST['form_payload'];
        }
    }
    // Ensure existing_images_array is loaded for image upload processing
    // If not already loaded, load from database or session
    if (empty($existing_images_array) && !empty($editing_product_id)) {
        // Try to load from session first (admin-specific key)
        $session_images_key_temp = 'pending_product_images_admin_' . $editing_product_id;
        if (isset($_SESSION[$session_images_key_temp]) && is_array($_SESSION[$session_images_key_temp])) {
            $existing_images_array = $_SESSION[$session_images_key_temp];
        } else {
            // Load from database
            $rs_img = mysqli_query($con, "SELECT images FROM products WHERE id='" . mysqli_real_escape_string($con, $editing_product_id) . "' LIMIT 1");
            if ($rs_img && mysqli_num_rows($rs_img) > 0) {
                $row_img = mysqli_fetch_assoc($rs_img);
                $existing_images = $row_img['images'] ?? '';
                if (!empty($existing_images)) {
                    $decoded_images = json_decode($existing_images, true);
                    if (is_array($decoded_images)) {
                        $existing_images_array = $decoded_images;
                    }
                }
            }
        }
    }
    
    // Check if we have existing images or new images
    $existing_image_count = 0;
    if (!empty($existing_images_array)) {
        $existing_image_count = count($existing_images_array);
    }
    $new_image_count = 0;
    $no_new_files = isset($_POST['no_new_files']) && $_POST['no_new_files'] === '1';
    if (!$no_new_files && isset($_FILES['product_images']) && is_array($_FILES['product_images']['name'])) {
        foreach ($_FILES['product_images']['name'] as $n) {
            if (!empty($n)) {
                $new_image_count++;
            }
        }
    }
    $total_images = $existing_image_count + $new_image_count;
    if ($total_images < 3) {
        $upload_errors[] = 'Minimum of 3 images required (existing + new).';
    }
    if (empty($upload_errors)) {
      
        $safe_vendor = $vendor_business_name ?: ('vendor-' . $vendor_reg_id);
        $safe_product = strtolower($product_name);
        // Ensure uploads are stored under public/uploads/vendors/{vendor}/{product}
        $public_root = realpath(__DIR__ . '/../../../public');
        if ($public_root === false) {
            $public_root = __DIR__ . '/../../../public';
        }
        $base_dir = rtrim($public_root, '/\\') . '/uploads/vendors/' . $safe_vendor . '/' . $safe_product;
        if (!is_dir($base_dir)) {
            @mkdir($base_dir, 0777, true);
        }
        // Uniform dimensions check removed by request
        // Generate a batch token for filenames
        $batch = date('YmdHis');
        $new_files = [];
        // Only process new files if they exist and we're not in no_new_files mode
        if (!$no_new_files && isset($_FILES['product_images']['name']) && is_array($_FILES['product_images']['name'])) {
            for ($i = 0; $i < count($_FILES['product_images']['name']); $i++) {
                $name = $_FILES['product_images']['name'][$i];
                $tmp = $_FILES['product_images']['tmp_name'][$i];
                $size = $_FILES['product_images']['size'][$i];
                $err = $_FILES['product_images']['error'][$i];
                if ($err !== UPLOAD_ERR_OK || empty($name)) {
                    continue;
                }
                // Validate type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmp);
                finfo_close($finfo);
                if (!in_array($mime, ['image/jpeg', 'image/png'])) {
                    $upload_errors[] = 'Allowed formats: JPEG, PNG.';
                    break;
                }
                // Validate size
                if ($size > 2 * 1024 * 1024) {
                    $upload_errors[] = 'Maximum file size is 2 MB per image.';
                    break;
                }
                // Validate dimensions and uniformity (ratio check removed as requested)
                $dim = @getimagesize($tmp);
                if (!$dim) {
                    $upload_errors[] = 'Invalid image file.';
                    break;
                }
                list($w, $h) = $dim;
                if ($w <= 0 || $h <= 0) {
                    $upload_errors[] = 'Invalid image dimensions.';
                    break;
                }
                // No identical dimension enforcement
                // Build filename pattern like "{vendorId}-{batch}-{index}.ext"
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
                $new_name = $vendor_reg_id . '-' . $batch . '-' . ($i + 1) . '.' . $ext;
                $dest = $base_dir . '/' . $new_name;
                if (!@move_uploaded_file($tmp, $dest)) {
                    $upload_errors[] = 'Failed to store uploaded image.';
                    break;
                }
                $new_files[] = $new_name;
            }
        }
        if (empty($upload_errors)) {
            // Handle removed images
            $removed_images = [];
            if (isset($_POST['removed_images']) && !empty($_POST['removed_images'])) {
                $removed_images = json_decode($_POST['removed_images'], true);
                if (!is_array($removed_images)) {
                    $removed_images = [];
                }
            }
            // Filter out removed images from existing images
            // Handle both full paths and filenames in removed_images
            $filtered_existing_images = array_filter($existing_images_array, function ($img) use ($removed_images) {
                if (empty($removed_images) || !is_array($removed_images)) {
                    return true; // Keep all images if no removed images
                }
                $img_basename = basename($img);
                $img_normalized = trim($img, '/');
                foreach ($removed_images as $removed) {
                    $removed_normalized = trim($removed, '/');
                    $removed_basename = basename($removed);
                    // Match by full path or basename
                    if ($img === $removed || 
                        $img_normalized === $removed_normalized || 
                        $img_basename === $removed_basename ||
                        basename($img_normalized) === basename($removed_normalized)) {
                        return false; // Remove this image
                    }
                }
                return true; // Keep this image
            });
            // Keep list in session for next step (DB save later)
            // Combine filtered existing images with new files
            $all_images = array_merge(array_values($filtered_existing_images), $new_files);
            
            // Use product ID-specific session keys for edit mode
            if (!empty($editing_product_id)) {
                $session_images_key = 'pending_product_images_admin_' . $editing_product_id;
                $session_path_key = 'pending_product_path_admin_' . $editing_product_id;
                $_SESSION[$session_images_key] = $uploaded_file_names = $all_images;
                $_SESSION[$session_path_key] = 'uploads/vendors/' . $safe_vendor . '/' . $safe_product;
                
                // Debug: Log what's being stored in session
                error_log("Storing in session - {$session_images_key}: " . print_r($all_images, true));
                error_log("Storing in session - {$session_path_key}: " . $_SESSION[$session_path_key]);
            } else {
                $_SESSION['pending_product_images'] = $uploaded_file_names = $all_images;
                $_SESSION['pending_product_path'] = 'uploads/vendors/' . $safe_vendor . '/' . $safe_product;
                
                // Debug: Log what's being stored in session
                error_log("Storing in session - pending_product_images: " . print_r($all_images, true));
                error_log("Storing in session - pending_product_path: " . $_SESSION['pending_product_path']);
            }
            // Handle multiple video uploads
            $uploaded_videos = [];
            if (isset($_FILES['product_videos']) && is_array($_FILES['product_videos']['name'])) {
                $allowed_video_types = ['video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/webm'];
                for ($i = 0; $i < count($_FILES['product_videos']['name']); $i++) {
                    $vname = $_FILES['product_videos']['name'][$i];
                    $vtmp = $_FILES['product_videos']['tmp_name'][$i];
                    $vsize = $_FILES['product_videos']['size'][$i];
                    $verr = $_FILES['product_videos']['error'][$i];
                    if ($verr !== UPLOAD_ERR_OK || empty($vname)) {
                        continue;
                    }
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $vmime = finfo_file($finfo, $vtmp);
                    finfo_close($finfo);
                    if (!in_array($vmime, $allowed_video_types)) {
                        $upload_errors[] = 'Invalid video format. Allowed: MP4, AVI, MOV, WMV, WebM.';
                        break;
                    }
                    if ($vsize > 50 * 1024 * 1024) {
                        $upload_errors[] = 'Video file size must be less than 50 MB.';
                        break;
                    }
                    $vext = strtolower(pathinfo($vname, PATHINFO_EXTENSION));
                    $video_new_name = $vendor_reg_id . '-' . time() . '-' . ($i + 1) . '-video.' . $vext;
                    $video_dest = $base_dir . '/' . $video_new_name;
                    if (!@move_uploaded_file($vtmp, $video_dest)) {
                        $upload_errors[] = 'Failed to upload a video file.';
                        break;
                    }
                    $uploaded_videos[] = $video_new_name;
                }
                if (empty($upload_errors) && !empty($uploaded_videos)) {
                    // Merge with any existing session videos instead of overwriting
                    // Use product ID-specific session key for edit mode
                    if (!empty($editing_product_id)) {
                        $session_videos_key = 'pending_product_videos_admin_' . $editing_product_id;
                        $existing_videos = isset($_SESSION[$session_videos_key]) && is_array($_SESSION[$session_videos_key]) ? $_SESSION[$session_videos_key] : [];
                        $_SESSION[$session_videos_key] = array_values(array_merge($existing_videos, $uploaded_videos));
                    } else {
                        $existing_videos = isset($_SESSION['pending_product_videos']) && is_array($_SESSION['pending_product_videos']) ? $_SESSION['pending_product_videos'] : [];
                        $_SESSION['pending_product_videos'] = array_values(array_merge($existing_videos, $uploaded_videos));
                    }
                }
            }
            // Redirect to second page immediately after successful upload
            $redirect_url = 'addProduct.php';
            if (!empty($editing_product_id)) {
                $redirect_url .= '?id=' . urlencode($editing_product_id);
            }
            header('Location: ' . $redirect_url);
            exit;
        } else {
            // Cleanup partial files when an error occurs
            if (!empty($base_dir) && !empty($new_files)) {
                foreach ($new_files as $f) {
                    @unlink($base_dir . '/' . $f);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="dark" data-toggled="close">

<head>
    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="hagidy website" content="hagidy website">
    <title><?php echo !empty($editing_product_id) ? 'EDIT - PRODUCT' : 'ADD - PRODUCT'; ?> | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords"
        content="blazor bootstrap, c# blazor, admin panel, blazor c#, template setting, admin, bootstrap admin template, blazor, blazorbootstrap, bootstrap 5 templates, setting, setting template bootstrap, admin setting bootstrap.">
    <!-- Favicon -->
    <link rel="icon" href="<?php echo PUBLIC_ASSETS; ?>images/vendor/brand-logos/favicon.ico" type="image/x-icon">
    <!-- Choices JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>
    <!-- Main Theme Js -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/main.js"></script>
    <!-- Bootstrap Css -->
    <link id="style" href="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Style Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/styles.min.css" rel="stylesheet">
    <!-- Icons Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/icons.css" rel="stylesheet">
    <!-- Node Waves Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.css" rel="stylesheet">
    <!-- Simplebar Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.css" rel="stylesheet">
    <!-- Color Picker Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/themes/nano.min.css">
    <!-- Choices Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/styles/choices.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Style for disabled category/subcategory fields in edit mode */
        select.form-select:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
            opacity: 0.7;
        }
        select.form-select:disabled + small {
            color: #6c757d;
        }
    </style>
</head>

<body>

    <!-- Loader -->
    <div id="loader">
        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/media/loader.svg" alt="">
    </div>
    <!-- Loader -->
    <div class="page">
        <!-- app-header -->
        <?php include './include/header.php'; ?>
        <!-- /app-header -->
        <!-- Start::app-sidebar -->
        <?php include './include/sidebar.php'; ?>
        <!-- End::app-sidebar -->
        <!-- Start::app-content -->
        <div class="main-content app-content">
            <!-- Start:: row-2 -->
            <div class="row g-3 p-3">
                <div class="col-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                    <div
                        class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb flex-wrap g-3">
                        <h1 class="page-title fw-semibold fs-18 mb-0">
                            <?php echo !empty($editing_product_id) ? 'Edit Product' : 'Add Product'; ?>
                        </h1>
                        <div class="ms-md-1 ms-0">
                            <nav>
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="product-management.php">Accessories Product </a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">
                                        <?php echo !empty($editing_product_id) ? 'Edit Product' : 'Add Product'; ?>
                                    </li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xl-6 col-lg-12 col-md-12 col-sm-12 col-12">
                                    <div class="row g-3">
                                        <div class="col-12 ">
                                            <label for="Light-blue" class="form-label text-default">Product Name
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-blue"
                                                name="product_name" maxlength="100" placeholder="Light Blue Sweat Shirt"
                                                value="<?php echo htmlspecialchars($form_data['product_name'] ?? ''); ?>"
                                                required>
                                            <div class="product-add-text">
                                                <h3>*Product Name should not exceed 100 characters</h3>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Category" class="form-label text-default">Category
                                                <span class="text-danger">*</span></label>
                                            <select id="Light-Category" name="category"
                                                class="form-select form-select-lg selecton-order w-100" required
                                                onchange="onCategoryChange(this.value)"
                                                <?php echo !empty($editing_product_id) ? 'disabled' : ''; ?>>
                                                <option value="">Category</option>
                                                <?php

                                                $categories = mysqli_query($con, "SELECT * FROM category ORDER BY name");

                                                while ($category = mysqli_fetch_assoc($categories)) {
                                                    $sel = ($selected_category == $category['id']) ? 'selected' : '';
                                                    echo "<option value='" . $category['id'] . "' $sel>" . $category['name'] . "</option>";
                                                }
                                                ?>
                                            </select>
                                            <?php if (!empty($editing_product_id)): ?>
                                                <!-- Hidden input to preserve category value when disabled -->
                                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($selected_category); ?>">
                                                <small class="text-muted d-block mt-1">
                                                    <i class="fa fa-info-circle"></i> Category cannot be changed after product creation
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Category" class="form-label text-default">Sub Category
                                                <span class="text-danger">*</span></label>
                                            <select id="Light-SubCategory" name="subcategory" required
                                                class="form-select form-select-lg selecton-order w-100" required
                                                onchange="onSubCategoryChange(this.value)"
                                                <?php echo !empty($editing_product_id) ? 'disabled' : ''; ?>>
                                                <option value="">Sub Category</option>
                                                <?php
                                                if (!empty($selected_category)) {
                                                    $subcategories = mysqli_query($con, "SELECT * FROM sub_category WHERE category_id='" . mysqli_real_escape_string($con, $selected_category) . "' ORDER BY name");
                                                    while ($subcategory = mysqli_fetch_assoc($subcategories)) {
                                                        $sel = ($selected_subcategory == $subcategory['id']) ? 'selected' : '';
                                                        echo "<option value='" . $subcategory['id'] . "' $sel>" . $subcategory['name'] . "</option>";
                                                    }
                                                }
                                                ?>
                                            </select>
                                            <?php if (!empty($editing_product_id)): ?>
                                                <!-- Hidden input to preserve subcategory value when disabled -->
                                                <input type="hidden" name="subcategory" value="<?php echo htmlspecialchars($selected_subcategory); ?>">
                                                <small class="text-muted d-block mt-1">
                                                    <i class="fa fa-info-circle"></i> Sub Category cannot be changed after product creation
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-12 ">
                                            <label for="Light-MRP" class="form-label text-default">MRP
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-MRP"
                                                placeholder="₹1,499.90"
                                                value="<?php echo htmlspecialchars($form_data['mrp'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-12 ">
                                            <label for="Light-Selling" class="form-label text-default">Selling Price
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Selling"
                                                placeholder="₹1,299.99"
                                                value="<?php echo htmlspecialchars($form_data['selling_price'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-12 ">
                                            <label for="Light-Discount" class="form-label text-default">Discount (%)
                                            </label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Discount"
                                                disabled placeholder="5.0 %"
                                                value="<?php echo htmlspecialchars($form_data['discount'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12">
                                            <label for="Light-MRP" class="form-label text-default">Product Description
                                                <span class="text-danger">*</span></label>
                                            <div class="cart-product-li">
                                                <textarea name="" id="Light-Description"
                                                    class="form-control form-control-lg" required
                                                    placeholder="Enter Product Description "><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Brand" class="form-label text-default">Brand Name
                                            </label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Brand"
                                                placeholder="Enter Brand Name "
                                                value="<?php echo htmlspecialchars($form_data['brand'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Brand" class="form-label text-default">GST (%)
                                                <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control form-control-lg" id="Light-GST"
                                                placeholder="1.50%"
                                                value="<?php echo htmlspecialchars($form_data['gst'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Brand" class="form-label text-default">HSN ID
                                                <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control form-control-lg" id="Light-HSN"
                                                placeholder="Enter HSN ID "
                                                value="<?php echo htmlspecialchars($form_data['hsn_id'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-12">
                                            <label for="Light-MRP" class="form-label text-default">Manufacturer Details
                                                <span class="text-danger">*</span></label>
                                            <div class="cart-product-li">
                                                <textarea name="" id="Light-Manufacturer"
                                                    class="form-control form-control-lg" required
                                                    placeholder="Enter Manufacturer Details "><?php echo htmlspecialchars($form_data['manufacture_details'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label for="Light-MRP" class="form-label text-default">Packer Details
                                                <span class="text-danger">*</span></label>
                                            <div class="cart-product-li">
                                                <textarea name="" id="Light-Packer" class="form-control form-control-lg"
                                                    required
                                                    placeholder="Enter Packer Details "><?php echo htmlspecialchars($form_data['packaging_details'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-12 col-md-12 col-sm-12 col-12">
                                    <div class="row g-3">
                                        <div class="col-12 ">
                                            <label for="Light-SKU" class="form-label text-default">SKU ID
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-SKU"
                                                placeholder="Enter SKU ID  "
                                                value="<?php echo htmlspecialchars($form_data['sku_id'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Group" class="form-label text-default">Group ID</label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Group"
                                                placeholder="Enter Group ID "
                                                value="<?php echo htmlspecialchars($form_data['group_id'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Style" class="form-label text-default">Product ID / Style
                                                ID</label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Style"
                                                placeholder="Enter Product ID / Style ID "
                                                value="<?php echo htmlspecialchars($form_data['style_id'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Inventory" class="form-label text-default">Inventory
                                                <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control form-control-lg"
                                                id="Light-Inventory" placeholder="Enter Inventory   "
                                                value="<?php echo htmlspecialchars($form_data['inventory'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Brand" class="form-label text-default">Product Brand
                                            </label>
                                            <input type="text" class="form-control form-control-lg"
                                                id="Light-ProductBrand" placeholder="Enter Product Brand  "
                                                value="<?php echo htmlspecialchars($form_data['product_brand'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12  ">
                                            <label for="Light-Images" class="form-label text-default">Product Images
                                                <span class="text-danger">*</span></label>
                                            <div class="drop-zone">
                                                <span class="drop-zone__prompt">Drag &amp; Drop your files or
                                                    Browse</span>
                                                <form id="imageUploadForm" method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="action" value="upload_images">
                                                    <input type="text" name="product_name" class="d-none"
                                                        id="hiddenProductName">
                                                    <input type="hidden" name="form_payload" id="form_payload">
                                                    <input type="hidden" name="removed_images" id="removed_images"
                                                        value="">
                                                    <input type="file" id="product_images" name="product_images[]"
                                                        class="drop-zone__input" accept="image/jpeg,image/png" multiple
                                                        required>
                                                    <input type="file" id="product_videos" name="product_videos[]"
                                                        class="d-none"
                                                        accept="video/mp4,video/avi,video/mov,video/wmv,video/webm"
                                                        multiple>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="col-12  ">
                                            <div class="minimum-contaiten">
                                                <ul>
                                                    <li>Minimum of 3 images must be uploaded.</li>
                                                    <li>Image dimensions: 30 cm (Height) × 60 cm (Width).</li>
                                                    <li>Allowed formats: JPEG, PNG.</li>
                                                    <li>Maximum file size: 2 MB per image.</li>
                                                    <li>Graphic, inverted, or pixelated images are not accepted.</li>
                                                    <li>Images with text or watermark are not acceptable in primary
                                                        images.</li>
                                                    <li>Blurry or cluttered images are not accepted.</li>
                                                    <li>Images must not contain price or brand logos.</li>
                                                    <li>Product images must not be shrunk, elongated, or stretched.</li>
                                                    <li>Partial product images are not allowed.</li>
                                                    <li>Offensive or objectionable images/products are not acceptable.
                                                    </li>
                                                    <li>Once uploaded, to change an image you must wait a minimum of 24
                                                        hours.</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-12  ">
                                            <?php if (!empty($upload_errors)): ?>
                                                <div class="alert alert-danger py-2">
                                                    <?php foreach ($upload_errors as $ue): ?>
                                                        <div><?php echo htmlspecialchars($ue); ?></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-12  ">
                                            <label for="product_video_display" class="form-label text-default">Product
                                                Video
                                            </label>
                                            <?php if (!empty($existing_product['video'])): ?>
                                                <div class="mb-2">
                                                    <div class="alert alert-info">
                                                        <strong>Current Videos:</strong>
                                                        <?php
                                                        // Parse videos using regex to handle commas within folder names
                                                        $vids = [];
                                                        if (!empty($existing_product['video'])) {
                                                            $videoString = (string) $existing_product['video'];
                                                            // Match video file paths ending with common video extensions
                                                            preg_match_all('/(.*?\.(?:mp4|mov|avi|webm|mkv|flv|wmv|m4v)(?:\?.*)?)(?:,|$)/i', $videoString, $matches);
                                                            if (!empty($matches[1])) {
                                                                foreach ($matches[1] as $vp) {
                                                                    $vp = trim($vp);
                                                                    if (!empty($vp)) {
                                                                        $vids[] = $vp;
                                                                    }
                                                                }
                                                            } else {
                                                                // Fallback: if no video extension found, treat entire string as single video
                                                                $vp = trim($videoString);
                                                                if (!empty($vp)) {
                                                                    $vids[] = $vp;
                                                                }
                                                            }
                                                        }
                                                        if (!empty($vids)):
                                                        ?>
                                                            <div class="d-flex flex-wrap gap-2 mt-2">
                                                                <?php foreach ($vids as $v):
                                                                    $bn = basename($v); ?>
                                                                    <div
                                                                        class="d-flex align-items-center gap-2 border rounded px-2 py-1">
                                                                        <span
                                                                            class="small"><?php echo htmlspecialchars($bn); ?></span>
                                                                        <form method="POST" class="m-0 p-0"
                                                                            onsubmit="return confirm('Remove this video?');">
                                                                            <input type="hidden" name="action" value="remove_video">
                                                                            <input type="hidden" name="product_id"
                                                                                value="<?php echo htmlspecialchars($editing_product_id ?? ''); ?>">
                                                                            <input type="hidden" name="video_name"
                                                                                value="<?php echo htmlspecialchars($bn); ?>">
                                                                            <button type="submit"
                                                                                class="btn btn-sm btn-outline-danger">Remove</button>
                                                                        </form>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php
                                            // Display session-pending videos (uploaded but not yet saved to DB)
                                            // Use product ID-specific session key for edit mode (admin-specific)
                                            if (!empty($editing_product_id)) {
                                                $session_videos_key = 'pending_product_videos_admin_' . $editing_product_id;
                                                $pending_videos_session = isset($_SESSION[$session_videos_key]) && is_array($_SESSION[$session_videos_key]) ? $_SESSION[$session_videos_key] : [];
                                            } else {
                                                $pending_videos_session = isset($_SESSION['pending_product_videos']) && is_array($_SESSION['pending_product_videos']) ? $_SESSION['pending_product_videos'] : [];
                                            }
                                            if (!empty($pending_videos_session)):
                                                // Build vendor/product path similar to upload logic
                                           
                                                $safe_vendor = $vendor_business_name ?: ('vendor-' . $vendor_reg_id);
                                                $safe_product = strtolower(isset($form_data['product_name']) ? $form_data['product_name'] : ($existing_product['product_name'] ?? ''));
                                            ?>
                                                <div class="mb-2">
                                                    <div class="alert alert-secondary">
                                                        <strong>Pending Videos (not yet saved):</strong>
                                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                                            <?php foreach ($pending_videos_session as $sv): $bn = basename($sv); ?>
                                                                <div class="d-flex align-items-center gap-2 border rounded px-2 py-1">
                                                                    <span class="small"><?php echo htmlspecialchars($bn); ?></span>
                                                                    <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?php echo htmlspecialchars(USER_BASEURL . 'public/uploads/vendors/' . $safe_vendor . '/' . $safe_product . '/' . ltrim($bn, '/')); ?>">View</a>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="drop-zone">
                                                <span class="drop-zone__prompt">Drag &amp; Drop your video files or
                                                    Browse</span>
                                                <input type="file" id="product_video_display" class="drop-zone__input"
                                                    accept="video/mp4,video/avi,video/mov,video/wmv,video/webm"
                                                    multiple>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <i class="fe fe-info me-1"></i>
                                                    Supported formats: MP4, AVI, MOV, WMV, WebM<br>
                                                    Maximum file size: 50 MB
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="text-end mt-5">
                                        <button type="button" id="saveNextBtn"
                                            class="btn btn-light btn-wave waves-effect waves-light"
                                            style="color: white; background-color: #3B4B6B; width: fit-content;">
                                            <?php echo !empty($editing_product_id) ? 'Update & Next' : 'Save & Next'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer Start -->
    <footer class="footer mt-auto py-3 bg-white text-center">
        <div class="container">
            <span class="text-muted"> Copyright © 2025 <span id="year"></span> <a href="#"
                    class="text-primary fw-semibold"> Hagidy </a>.
                Designed with <span class="bi bi-heart-fill text-danger"></span> by <a href="javascript:void(0);">
                    <span class="fw-semibold text-sky-blue text-decoration-underline">Mechodal Technology </span>
                </a>
            </span>
        </div>
    </footer>
    <!-- Footer End -->
    </div>
    <!-- Scroll To Top -->
    <div class="scrollToTop">
        <span class="arrow"><i class="ri-arrow-up-s-fill fs-20"></i></span>
    </div>
    <div id="responsive-overlay"></div>
    <!-- Scroll To Top -->
    <!-- Popper JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/@popperjs/core/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- Defaultmenu JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/defaultmenu.min.js"></script>
    <!-- Node Waves JS-->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.js"></script>
    <!-- Sticky JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/sticky.js"></script>
    <!-- Simplebar JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/simplebar.js"></script>
    <!-- Color Picker JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/pickr.es5.min.js"></script>
    <!-- Apex Charts JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/apexcharts/apexcharts.min.js"></script>
    <!-- Ecommerce-Dashboard JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/ecommerce-dashboard.js"></script>
    <!-- Custom-Switcher JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom-switcher.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom.js"></script>
    <script>
        async function onCategoryChange(value) {
            const categorySelect = document.getElementById('Light-Category');
            // Don't process if category is disabled (edit mode)
            if (categorySelect && categorySelect.disabled) {
                return;
            }
            const subSel = document.getElementById('Light-SubCategory');
            subSel.innerHTML = '<option value="">Loading...</option>';
            if (!value) {
                subSel.innerHTML = '<option value="">Sub Category</option>';
                return;
            }
            try {
                const res = await fetch('?ajax=subcategories&category_id=' + encodeURIComponent(value), {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                subSel.innerHTML = '<option value="">Sub Category</option>';
                if (data && data.success) {
                    data.subcategories.forEach(sc => {
                        const opt = document.createElement('option');
                        opt.value = sc.id;
                        opt.textContent = sc.name;
                        subSel.appendChild(opt);
                    });
                }
                // Load mandatory attributes for the selected category
                // Note: This will be overridden when subcategory is selected
                await loadMandatoryAttributes(value);
                // Store category for mandatory attributes in next step
                try {
                    await fetch('<?php echo USER_BASEURL; ?>app/api/store_category_for_mandatory.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'category_id=' + encodeURIComponent(value)
                    });
                } catch (e) {}
            } catch (e) {
                subSel.innerHTML = '<option value="">Sub Category</option>';
            }
        }
        // Handle subcategory change to load mandatory attributes based on subcategory
        async function onSubCategoryChange(subcategoryId) {
            const subcategorySelect = document.getElementById('Light-SubCategory');
            // Don't process if subcategory is disabled (edit mode)
            if (subcategorySelect && subcategorySelect.disabled) {
                return;
            }
            if (!subcategoryId) {
                return;
            }
            try {
                // Load mandatory attributes for the selected subcategory
                await loadMandatoryAttributesForSubcategory(subcategoryId);
                // Store the subcategory ID in session for the next step
                try {
                    await fetch('<?php echo USER_BASEURL; ?>app/api/store_category_for_mandatory.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'sub_category_id=' + encodeURIComponent(subcategoryId)
                    });
                } catch (e) {}
                // Show a notice to the user about mandatory attributes
                showMandatoryAttributesNotice(subcategoryId);
            } catch (e) {
                console.error('Error loading mandatory attributes for subcategory:', e);
            }
        }
        // Show notice about mandatory attributes when subcategory changes
        async function showMandatoryAttributesNotice(subcategoryId) {
            try {
                const resp = await fetch('<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?sub_category_id=' + encodeURIComponent(subcategoryId));
                const data = await resp.json();
                if (data && data.mandatory && data.mandatory.length > 0) {
                    // Get attribute names
                    const attrIds = data.mandatory.join(',');
                    const attrResp = await fetch('<?php echo USER_BASEURL; ?>app/api/get_attribute_names.php?ids=' + encodeURIComponent(attrIds));
                    const attrData = await attrResp.json();
                    if (attrData && attrData.attributes && attrData.attributes.length > 0) {
                        const attributeNames = attrData.attributes.map(attr => attr.name).join(', ');
                        // Show a notice to the user
                        const notice = document.createElement('div');
                        notice.className = 'alert alert-info mt-3';
                        notice.innerHTML = `
                            <strong>Mandatory Attributes Required:</strong> 
                            When you proceed to the next step, you'll need to add specifications for: 
                            <strong>${attributeNames}</strong>
                        `;
                        // Insert the notice after the subcategory field
                        const subcategoryField = document.getElementById('Light-SubCategory');
                        if (subcategoryField && subcategoryField.parentNode) {
                            subcategoryField.parentNode.parentNode.appendChild(notice);
                        }
                    }
                }
            } catch (e) {
                console.error('Error showing mandatory attributes notice:', e);
            }
        }
        // Fetch mandatory attribute ids for subcategory and store in window for validation
        async function loadMandatoryAttributesForSubcategory(subcategoryId) {
            try {
                const resp = await fetch('<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?sub_category_id=' + encodeURIComponent(subcategoryId), {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const json = await resp.json();
                window.__MANDATORY_ATTR_IDS__ = Array.isArray(json.mandatory) ? json.mandatory : [];
            } catch (_) {
                window.__MANDATORY_ATTR_IDS__ = [];
            }
        }
        // Fetch mandatory attribute ids for category and store in window for validation
        async function loadMandatoryAttributes(categoryId) {
            try {
                const resp = await fetch('<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?category_id=' + encodeURIComponent(categoryId), {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const json = await resp.json();
                window.__MANDATORY_ATTR_IDS__ = Array.isArray(json.mandatory) ? json.mandatory : [];
            } catch (_) {
                window.__MANDATORY_ATTR_IDS__ = [];
            }
        }
        // Helper functions for error display
        function ensureFeedback(el) {
            let fb = el.parentElement?.querySelector('.invalid-feedback');
            if (!fb) {
                fb = document.createElement('div');
                fb.className = 'invalid-feedback';
                if (el.nextSibling) {
                    el.parentElement.insertBefore(fb, el.nextSibling);
                } else {
                    el.parentElement.appendChild(fb);
                }
            }
            return fb;
        }

        function setError(id, msg) {
            const el = document.getElementById(id);
            if (!el) return null;
            el.classList.add('is-invalid');
            const fb = ensureFeedback(el);
            fb.textContent = msg;
            return el;
        }

        function clearErrors() {
            document.querySelectorAll('.is-invalid').forEach(x => x.classList.remove('is-invalid'));
            document.querySelectorAll('.invalid-feedback').forEach(x => x.textContent = '');
            // Clear image validation error
            const existingError = document.querySelector('.image-validation-error');
            if (existingError) existingError.remove();
        }
        // Add real-time validation - clear errors when user starts typing
        function addRealTimeValidation() {
            const requiredFields = [
                'Light-blue', 'Light-Category', 'Light-SubCategory', 'Light-MRP',
                'Light-Selling', 'Light-Description', 'Light-GST', 'Light-HSN',
                'Light-Manufacturer', 'Light-Packer', 'Light-SKU', 'Light-Inventory'
            ];
            requiredFields.forEach(fieldId => {
                const element = document.getElementById(fieldId);
                if (element) {
                    element.addEventListener('input', function() {
                        if (this.classList.contains('is-invalid')) {
                            this.classList.remove('is-invalid');
                            const feedback = this.parentElement?.querySelector('.invalid-feedback');
                            if (feedback) feedback.textContent = '';
                        }
                    });
                }
            });
        }
        // Initialize real-time validation when page loads
        document.addEventListener('DOMContentLoaded', function() {
            addRealTimeValidation();
        });
        // Hook Save & Next to validate and upload images immediately (store on disk), DB save will happen in next step
        document.getElementById('saveNextBtn')?.addEventListener('click', function(e) {
            e.preventDefault();
            // Clear previous errors
            clearErrors();
            // Check all required fields
            const requiredFields = [{
                    id: 'Light-blue',
                    name: 'Product Name'
                },
                {
                    id: 'Light-Category',
                    name: 'Category'
                },
                {
                    id: 'Light-SubCategory',
                    name: 'Sub Category'
                },
                {
                    id: 'Light-MRP',
                    name: 'MRP'
                },
                {
                    id: 'Light-Selling',
                    name: 'Selling Price'
                },
                {
                    id: 'Light-Description',
                    name: 'Description'
                },
                {
                    id: 'Light-GST',
                    name: 'GST'
                },
                {
                    id: 'Light-HSN',
                    name: 'HSN ID'
                },
                {
                    id: 'Light-Manufacturer',
                    name: 'Manufacturer Details'
                },
                {
                    id: 'Light-Packer',
                    name: 'Packer Details'
                },
                {
                    id: 'Light-SKU',
                    name: 'SKU ID'
                },
                {
                    id: 'Light-Inventory',
                    name: 'Inventory'
                }
                // { id: 'Light-ProductBrand', name: 'Product Brand' }
            ];
            let firstError = null;
            for (let field of requiredFields) {
                const element = document.getElementById(field.id);
                // Skip validation for disabled fields (they have hidden inputs with values)
                if (element && element.disabled) {
                    continue;
                }
                if (!element || !element.value.trim()) {
                    setError(field.id, `${field.name} is required.`);
                    if (!firstError) firstError = element;
                }
            }
            const nameInput = document.getElementById('Light-blue');
            const productName = (nameInput?.value || '').trim();
            if (productName.length > 100) {
                setError('Light-blue', 'Product Name must be 100 characters or fewer.');
                if (!firstError) firstError = nameInput;
            }
            // Relationship: Selling Price must be <= MRP
            (function() {
                const mrpEl = document.getElementById('Light-MRP');
                const spEl = document.getElementById('Light-Selling');
                const mrp = parseFloat((mrpEl?.value || '').replace(/[^0-9.]/g, '')) || 0;
                const sp = parseFloat((spEl?.value || '').replace(/[^0-9.]/g, '')) || 0;
                if (mrp > 0 && sp > 0 && sp > mrp) {
                    setError('Light-Selling', 'Selling Price must be less than MRP.');
                    if (!firstError) firstError = spEl;
                }
            })();
            // If there are validation errors, focus on first error and return
            if (firstError) {
                firstError.focus();
                firstError.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return;
            }
            const files = document.getElementById('product_images')?.files || [];
            const existingImages = document.querySelectorAll('.variant-previews .add-productbu[data-file-type="existing"]');
            const newImages = document.querySelectorAll('.variant-previews .add-productbu[data-file-type="new"]');
            const totalImages = existingImages.length + newImages.length;
            if (totalImages < 3) {
                // Show error message in a more user-friendly way
                const imageErrorDiv = document.createElement('div');
                imageErrorDiv.className = 'alert alert-danger mt-3';
                imageErrorDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Please ensure you have at least 3 images (existing + new).';
                // Remove any existing image error
                const existingError = document.querySelector('.image-validation-error');
                if (existingError) existingError.remove();
                imageErrorDiv.classList.add('image-validation-error');
                // Insert after the image upload section
                const imageSection = document.querySelector('.variant-previews') || document.getElementById('product_images')?.parentElement;
                if (imageSection) {
                    imageSection.parentElement.insertBefore(imageErrorDiv, imageSection.nextSibling);
                }
                // Scroll to the error
                imageErrorDiv.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return;
            }
            // collect key fields to persist in session for next step
            // For disabled fields (edit mode), get value from hidden input or the select itself
            const categorySelect = document.getElementById('Light-Category');
            const subcategorySelect = document.getElementById('Light-SubCategory');
            const categoryValue = categorySelect?.disabled 
                ? (document.querySelector('input[name="category"]')?.value || categorySelect?.value || '')
                : (categorySelect?.value || '');
            const subcategoryValue = subcategorySelect?.disabled
                ? (document.querySelector('input[name="subcategory"]')?.value || subcategorySelect?.value || '')
                : (subcategorySelect?.value || '');
            
            const payload = {
                product_name: productName,
                category_id: categoryValue,
                sub_category_id: subcategoryValue,
                mrp: document.getElementById('Light-MRP')?.value || '',
                selling_price: document.getElementById('Light-Selling')?.value || '',
                discount: document.getElementById('Light-Discount')?.value || '',
                description: document.getElementById('Light-Description')?.value || '',
                brand: document.getElementById('Light-Brand')?.value || '',
                gst: document.getElementById('Light-GST')?.value || '',
                hsn_id: document.getElementById('Light-HSN')?.value || '',
                manufacture_details: document.getElementById('Light-Manufacturer')?.value || '',
                packaging_details: document.getElementById('Light-Packer')?.value || '',
                sku_id: document.getElementById('Light-SKU')?.value || '',
                group_id: document.getElementById('Light-Group')?.value || '',
                style_id: document.getElementById('Light-Style')?.value || '',
                inventory: document.getElementById('Light-Inventory')?.value || '',
                product_brand: document.getElementById('Light-ProductBrand')?.value || '',
                // collect multiple selected video names for preview/save step (handled server-side later)
                video: Array.from(document.getElementById('product_video_display')?.files || []).map(f => f.name).join(',') || '',
                editing_product_id: '<?php echo htmlspecialchars($editing_product_id ?? ''); ?>',
                vendor_id: '<?php echo htmlspecialchars($vendor_reg_id ?? ''); ?>',
                update_status: document.getElementById('update_status')?.value || '',
            };
            document.getElementById('form_payload').value = JSON.stringify(payload);
            // set hidden product name and submit upload form
            document.getElementById('hiddenProductName').value = productName;
            // If we have existing images and no new files, we can skip the upload step
            if (totalImages >= 3 && files.length === 0) {
                // Still need to submit the form to save the payload, but with no files
                // Create a hidden input to submit the form without files
                const form = document.getElementById('imageUploadForm');
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'no_new_files';
                hiddenInput.value = '1';
                form.appendChild(hiddenInput);
                form.submit();
            } else {
                // Submit the form to upload new images
                document.getElementById('imageUploadForm').submit();
            }
        });
        // Handle video input separately (enable click and drag-drop on drop zone)
        const videoInput = document.getElementById('product_video_display');
        if (videoInput) {
            const videoDropZone = videoInput.closest(".drop-zone");
            if (videoDropZone) {
                // Enable click on drop zone to open file picker
                videoDropZone.addEventListener("click", (e) => {
                    videoInput.click();
                });
                // Enable drag and drop for videos
                videoDropZone.addEventListener("dragover", (e) => {
                    e.preventDefault();
                    videoDropZone.classList.add("drop-zone--over");
                });
                ["dragleave", "dragend"].forEach((type) => {
                    videoDropZone.addEventListener(type, (e) => {
                        videoDropZone.classList.remove("drop-zone--over");
                    });
                });
                videoDropZone.addEventListener("drop", (e) => {
                    e.preventDefault();
                    const dropped = Array.from(e.dataTransfer.files || []);
                    if (dropped.length) {
                        // Filter only video files
                        const videoFiles = dropped.filter(f => f.type && f.type.startsWith('video/'));
                        if (videoFiles.length > 0) {
                            const dt = new DataTransfer();
                            videoFiles.forEach(f => dt.items.add(f));
                            videoInput.files = dt.files;
                            // Trigger change event to show preview
                            videoInput.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                    videoDropZone.classList.remove("drop-zone--over");
                });
            }
        }
        
        document.querySelectorAll(".drop-zone__input").forEach((inputElement) => {
            // Skip video inputs - only process image inputs
            if (inputElement.id === 'product_video_display' || 
                (inputElement.accept && inputElement.accept.includes('video/'))) {
                return; // Skip this input, it's for videos (handled separately above)
            }
            const dropZoneElement = inputElement.closest(".drop-zone");
            // Maintain a mutable selection of files before upload
            let selectedFiles = [];

            function syncInputFiles() {
                const dt = new DataTransfer();
                selectedFiles.forEach(f => dt.items.add(f));
                inputElement.files = dt.files;
            }
            // Function to show delete confirmation modal for new images
            function showNewImageDeleteModal(item, index) {
                // Create modal if it doesn't exist
                let modal = document.getElementById('deleteNewImageModal');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'deleteNewImageModal';
                    modal.className = 'modal fade';
                    modal.setAttribute('tabindex', '-1');
                    modal.setAttribute('aria-labelledby', 'deleteNewImageModalLabel');
                    modal.setAttribute('aria-hidden', 'true');
                    modal.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title text-black" id="deleteNewImageModalLabel">Confirm Delete</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to delete this image?</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger" id="confirmNewImageDeleteBtn">Delete Image</button>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(modal);
                }
                // Set image preview (if element exists)
                const previewImg = modal.querySelector('#modalNewImagePreview');
                if (previewImg) {
                    const img = item.querySelector('img');
                    previewImg.src = img ? img.src : '';
                }
                // Show modal
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
                // Handle confirm delete
                const confirmBtn = modal.querySelector('#confirmNewImageDeleteBtn');
                confirmBtn.onclick = () => {
                    // Remove this file and re-render
                    selectedFiles.splice(index, 1);
                    syncInputFiles();
                    renderPreviews();
                    bsModal.hide();
                };
            }

            function ensurePreviewWrap() {
                let previewWrap = document.querySelector('.variant-previews');
                if (!previewWrap) {
                    previewWrap = document.createElement('div');
                    previewWrap.className = 'variant-previews d-flex align-items-center gap-3 flex-wrap mt-4';
                    dropZoneElement.parentElement.appendChild(previewWrap);
                }
                return previewWrap;
            }

            function renderPreviews() {
                const previewWrap = ensurePreviewWrap();
                // Store existing images (non-file objects) before clearing
                const existingImageItems = Array.from(previewWrap.querySelectorAll('.add-productbu')).filter(item => {
                    // Check if this is an existing image (not a new file)
                    const img = item.querySelector('img');
                    return img && !img.src.startsWith('blob:'); // blob: URLs are for new files
                });
                // Clear only new file previews, keep existing images
                const newFileItems = Array.from(previewWrap.querySelectorAll('.add-productbu')).filter(item => {
                    const img = item.querySelector('img');
                    return img && img.src.startsWith('blob:'); // blob: URLs are for new files
                });
                newFileItems.forEach(item => item.remove());
                // Render new files - only render image files
                selectedFiles.forEach((file, idx) => {
                    // Skip video files - only process image files
                    if (file.type && file.type.startsWith('video/')) {
                        return; // Skip video files
                    }
                    // Validate it's an image file
                    if (!file.type || !file.type.startsWith('image/')) {
                        return; // Skip non-image files
                    }
                    const url = URL.createObjectURL(file);
                    const item = document.createElement('div');
                    item.className = 'add-productbu position-relative';
                    item.style.position = 'relative';
                    item.draggable = true;
                    item.dataset.index = String(idx);
                    item.dataset.fileType = 'new'; // Mark as new file
                    item.innerHTML = `
                        <button type="button" class="btn-delet-icon" style="position:absolute;top:-10px;right:-10px;background:#fff;border:0;">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/delet-icon.png" alt="delete">
                        </button>
                        <div class="add-product-img"><img src="${url}" style="max-height:80px;object-fit:cover"></div>
                        <div class="small text-muted text-center mt-1" style="width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${file.name}</div>
                    `;
                    item.querySelector('.btn-delet-icon').addEventListener('click', () => {
                        // Show confirmation modal for new files
                        showNewImageDeleteModal(item, Number(item.dataset.index));
                    });
                    // Drag & drop reorder handlers (only for new files)
                    item.addEventListener('dragstart', (ev) => {
                        item.classList.add('dragging');
                        ev.dataTransfer.effectAllowed = 'move';
                        ev.dataTransfer.setData('text/plain', item.dataset.index);
                    });
                    item.addEventListener('dragend', () => {
                        item.classList.remove('dragging');
                        previewWrap.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
                    });
                    item.addEventListener('dragover', (ev) => {
                        ev.preventDefault();
                        ev.dataTransfer.dropEffect = 'move';
                        item.classList.add('drag-over');
                    });
                    item.addEventListener('dragleave', () => {
                        item.classList.remove('drag-over');
                    });
                    item.addEventListener('drop', (ev) => {
                        ev.preventDefault();
                        item.classList.remove('drag-over');
                        const fromIdx = Number(ev.dataTransfer.getData('text/plain'));
                        const toIdx = Number(item.dataset.index);
                        if (!Number.isNaN(fromIdx) && !Number.isNaN(toIdx) && fromIdx !== toIdx) {
                            const moved = selectedFiles.splice(fromIdx, 1)[0];
                            selectedFiles.splice(toIdx, 0, moved);
                            syncInputFiles();
                            renderPreviews();
                        }
                    });
                    previewWrap.appendChild(item);
                });
            }
            dropZoneElement.addEventListener("click", (e) => {
                inputElement.click();
            });
            inputElement.addEventListener("change", (e) => {
                const newFiles = Array.from(inputElement.files || []);
                // Append new files to existing selection (avoid duplicates by name+size)
                // Only add image files, skip video files
                newFiles.forEach(f => {
                    if (f.type && f.type.startsWith('image/') && 
                        !selectedFiles.some(sf => sf.name === f.name && sf.size === f.size)) {
                        selectedFiles.push(f);
                    }
                });
                syncInputFiles();
                renderPreviews();
            });
            dropZoneElement.addEventListener("dragover", (e) => {
                e.preventDefault();
                dropZoneElement.classList.add("drop-zone--over");
            });
            ["dragleave", "dragend"].forEach((type) => {
                dropZoneElement.addEventListener(type, (e) => {
                    dropZoneElement.classList.remove("drop-zone--over");
                });
            });
            dropZoneElement.addEventListener("drop", (e) => {
                e.preventDefault();
                const dropped = Array.from(e.dataTransfer.files || []);
                if (dropped.length) {
                    dropped.forEach(f => {
                        // Only add image files, skip video files
                        if (f.type && f.type.startsWith('image/') && 
                            !selectedFiles.some(sf => sf.name === f.name && sf.size === f.size)) {
                            selectedFiles.push(f);
                        }
                    });
                    syncInputFiles();
                    renderPreviews();
                }
                dropZoneElement.classList.remove("drop-zone--over");
            });
        });
        // Function to update the list of removed images
        function updateRemovedImagesList() {
            const existingImages = <?php echo json_encode($existing_images_array); ?>;
            const remainingImages = Array.from(document.querySelectorAll('.variant-previews .add-productbu[data-file-type="existing"]'));
            const remainingPaths = remainingImages.map(item => {
                // Get the image path from dataset first (most reliable)
                if (item.dataset.imagePath) {
                    return item.dataset.imagePath;
                }
                // Fallback to extracting from img src
                const img = item.querySelector('img');
                return img ? img.src.replace(window.location.origin + '/', '') : '';
            }).filter(path => path);
            
            // Normalize paths for comparison (remove leading/trailing slashes)
            const normalizePath = (p) => String(p).replace(/^\/+|\/+$/g, '');
            const normalizedRemaining = remainingPaths.map(normalizePath);
            
            // Find removed images by comparing normalized paths
            const removedImages = existingImages.filter(img => {
                const normalizedImg = normalizePath(img);
                return !normalizedRemaining.some(remaining => {
                    const normalizedRemaining = normalizePath(remaining);
                    const imgBasename = normalizedImg.split('/').pop() || '';
                    const remainingBasename = normalizedRemaining.split('/').pop() || '';
                    return normalizedImg === normalizedRemaining || 
                           imgBasename === remainingBasename;
                });
            });
            
            document.getElementById('removed_images').value = JSON.stringify(removedImages);
        }
        // Function to calculate discount dynamically
        function calculateDiscount() {
            const mrpInput = document.getElementById('Light-MRP');
            const sellingInput = document.getElementById('Light-Selling');
            const discountInput = document.getElementById('Light-Discount');
            if (mrpInput && sellingInput && discountInput) {
                const mrp = parseFloat(mrpInput.value.replace(/[^0-9.]/g, '')) || 0;
                const selling = parseFloat(sellingInput.value.replace(/[^0-9.]/g, '')) || 0;
                if (mrp > 0 && selling > 0) {
                    if (selling > mrp) {
                        sellingInput.classList.add('is-invalid');
                        const fb = (function() {
                            let el = sellingInput.parentElement?.querySelector('.invalid-feedback');
                            if (!el) {
                                el = document.createElement('div');
                                el.className = 'invalid-feedback';
                                sellingInput.parentElement.appendChild(el);
                            }
                            return el;
                        })();
                        fb.textContent = 'Selling Price must be less than MRP.';
                        discountInput.value = '0%';
                    } else {
                        sellingInput.classList.remove('is-invalid');
                        const discount = ((mrp - selling) / mrp) * 100;
                        const roundedDiscount = Math.round(discount);
                        discountInput.value = roundedDiscount + '%';
                    }
                } else {
                    discountInput.value = '0%';
                }
            }
        }
        // Track field changes for status update
        let originalValues = {};
        let fieldsToTrack = [
            'Light-blue', 'Light-Category', 'Light-SubCategory', 'Light-Description',
            'Light-Brand', 'Light-GST', 'Light-HSN', 'Light-Manufacturer',
            'Light-Packer', 'Light-SKU', 'Light-Group', 'Light-Style', 'Light-ProductBrand'
        ];

        function initializeFieldTracking() {
            // Store original values (skip disabled fields)
            fieldsToTrack.forEach(fieldId => {
                const element = document.getElementById(fieldId);
                if (element && !element.disabled) {
                    originalValues[fieldId] = element.value;
                }
            });
        }

        function checkForStatusUpdate() {
            let hasChanges = false;
            fieldsToTrack.forEach(fieldId => {
                const element = document.getElementById(fieldId);
                // Skip disabled fields (category/subcategory in edit mode)
                if (element && !element.disabled && element.value !== originalValues[fieldId]) {
                    hasChanges = true;
                }
            });
            if (hasChanges) {
                // Add hidden input to indicate status should be updated to "under review"
                let statusInput = document.getElementById('update_status');
                if (!statusInput) {
                    statusInput = document.createElement('input');
                    statusInput.type = 'hidden';
                    statusInput.id = 'update_status';
                    statusInput.name = 'update_status';
                    statusInput.value = 'under_review';
                    document.getElementById('imageUploadForm').appendChild(statusInput);
                }
            }
        }
        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize field tracking
            initializeFieldTracking();
            // Calculate initial discount
            calculateDiscount();
            // Add event listeners for MRP and Selling Price to calculate discount
            const mrpInput = document.getElementById('Light-MRP');
            const sellingInput = document.getElementById('Light-Selling');
            if (mrpInput) {
                mrpInput.addEventListener('input', calculateDiscount);
                mrpInput.addEventListener('blur', calculateDiscount);
            }
            if (sellingInput) {
                sellingInput.addEventListener('input', calculateDiscount);
                sellingInput.addEventListener('blur', calculateDiscount);
            }
            // Add event listeners to track changes for status update
            fieldsToTrack.forEach(fieldId => {
                const element = document.getElementById(fieldId);
                if (element) {
                    element.addEventListener('input', checkForStatusUpdate);
                    element.addEventListener('change', checkForStatusUpdate);
                }
            });
        });
        // Display existing images if editing
        <?php if (!empty($existing_images_array)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const existingImages = <?php echo json_encode($existing_images_array); ?>;
                // console.log('Existing images from database:', existingImages);

                if (existingImages && existingImages.length > 0) {
                    // Find the first drop zone (product images)
                    const dropZone = document.querySelector('.drop-zone');
                    if (dropZone) {
                        // Create preview container if it doesn't exist
                        let previewWrap = document.querySelector('.variant-previews');
                        if (!previewWrap) {
                            previewWrap = document.createElement('div');
                            previewWrap.className = 'variant-previews d-flex align-items-center gap-3 flex-wrap mt-4';
                            dropZone.parentElement.appendChild(previewWrap);
                        }
                        // Clear existing previews
                        previewWrap.innerHTML = '';
                        // Display each existing image
                        existingImages.forEach((imagePath, index) => {
                            // Clean and validate image path
                            let cleanPath = imagePath;
                            if (cleanPath.startsWith('uploads/')) {
                                cleanPath = cleanPath; // Keep as is
                            } else if (cleanPath.startsWith('/')) {
                                cleanPath = cleanPath.substring(1); // Remove leading slash
                            }

                            // Build correct image URL - use USER_BASEURL + public/ for uploads
                            const baseurl = '<?php echo USER_BASEURL; ?>' + 'public/';
                            const imageUrl = baseurl + cleanPath.replace(/^\/+/, '');

                            // console.log('Processing image:', cleanPath, 'URL:', imageUrl);

                            const item = document.createElement('div');
                            item.className = 'add-productbu position-relative';
                            item.style.position = 'relative';
                            item.dataset.index = String(index);
                            item.dataset.fileType = 'existing'; // Mark as existing image
                            item.dataset.imagePath = cleanPath; // Store the original path for deletion
                            item.innerHTML = `
                            <button type="button" class="btn-delet-icon" style="position:absolute;top:-10px;right:-10px;background:#fff;border:0;">
                                <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/delet-icon.png" alt="delete">
                            </button>
                            <div class="add-product-img">
                                <img src="${imageUrl}" 
                                     style="max-height:80px;object-fit:cover" 
                                     onerror="console.log('Image failed to load:', '${imageUrl}'); this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div style="display:none; width:80px; height:80px; background:#f8f9fa; border:1px dashed #dee2e6; align-items:center; justify-content:center; color:#6c757d; font-size:12px; text-align:center;">
                                    <div>Image<br>Not Found</div>
                                </div>
                            </div>
                            <div class="small text-muted text-center mt-1" style="width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${cleanPath.split('/').pop()}</div>
                        `;
                            // Add delete functionality with modal confirmation
                            item.querySelector('.btn-delet-icon').addEventListener('click', () => {
                                const pathToDelete = item.dataset.imagePath || imagePath;
                                showDeleteConfirmationModal(pathToDelete, item);
                            });
                            previewWrap.appendChild(item);
                        });
                    }
                }
            });
        <?php endif; ?>
        // Function to show delete confirmation modal
        function showDeleteConfirmationModal(imagePath, item) {
            // Create modal if it doesn't exist
            let modal = document.getElementById('deleteImageModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'deleteImageModal';
                modal.className = 'modal fade';
                modal.setAttribute('tabindex', '-1');
                modal.setAttribute('aria-labelledby', 'deleteImageModalLabel');
                modal.setAttribute('aria-hidden', 'true');
                modal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title text-black" id="deleteImageModalLabel">Confirm Delete</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete this image?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Image</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }
            
            // Store current imagePath and item reference in modal data attributes
            modal.dataset.currentImagePath = imagePath;
            if (item) {
                modal.dataset.currentItemId = item.dataset.imagePath || imagePath;
            }
            
            // Get or create modal instance
            let bsModal = bootstrap.Modal.getInstance(modal);
            if (!bsModal) {
                bsModal = new bootstrap.Modal(modal);
            }
            
            // Reset button state every time modal is opened
            const confirmBtn = modal.querySelector('#confirmDeleteBtn');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Delete Image';
            
            // Remove any existing click handlers by replacing the button
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            
            // Set up the delete handler
            newConfirmBtn.addEventListener('click', async function handleDelete() {
                // Get current values from modal data
                const currentImagePath = modal.dataset.currentImagePath;
                const currentItemId = modal.dataset.currentItemId;
                
                // Find the item element
                let currentItem = item;
                if (!currentItem && currentItemId) {
                    // Try to find the item by its data attribute
                    currentItem = document.querySelector(`.add-productbu[data-image-path="${currentItemId}"]`) ||
                                 document.querySelector(`.add-productbu[data-file-type="existing"][data-image-path*="${currentItemId.split('/').pop()}"]`);
                }
                
                // Disable button to prevent double submission
                newConfirmBtn.disabled = true;
                newConfirmBtn.textContent = 'Deleting...';
                
                try {
                    // Create FormData for AJAX request
                    const formData = new FormData();
                    formData.append('action', 'remove_image');
                    formData.append('image_path', currentImagePath);
                    formData.append('product_id', '<?php echo htmlspecialchars($editing_product_id ?? ''); ?>');
                    
                    // Send AJAX request
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Close modal
                        bsModal.hide();
                        
                        // Remove the image element from DOM
                        if (currentItem && currentItem.parentNode) {
                            currentItem.remove();
                        } else {
                            // Fallback: try to find and remove by image path
                            const allItems = document.querySelectorAll('.add-productbu[data-file-type="existing"]');
                            allItems.forEach(imgItem => {
                                const itemPath = imgItem.dataset.imagePath || '';
                                if (itemPath === currentImagePath || 
                                    itemPath === currentItemId ||
                                    itemPath.includes(currentImagePath.split('/').pop()) ||
                                    currentImagePath.includes(itemPath.split('/').pop())) {
                                    imgItem.remove();
                                }
                            });
                        }
                        
                        // Update the removed_images hidden field
                        updateRemovedImagesList();
                    } else {
                        alert('Failed to remove image: ' + (result.message || 'Unknown error'));
                        newConfirmBtn.disabled = false;
                        newConfirmBtn.textContent = 'Delete Image';
                    }
                } catch (error) {
                    console.error('Error removing image:', error);
                    alert('An error occurred while removing the image. Please try again.');
                    newConfirmBtn.disabled = false;
                    newConfirmBtn.textContent = 'Delete Image';
                }
            });
            
            // Show the modal
            bsModal.show();
        }
        // Function to remove video
        function removeVideo() {
            if (confirm('Are you sure you want to remove this video?')) {
                // Create and submit form to remove video from database
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'remove_video';
                const productIdInput = document.createElement('input');
                productIdInput.type = 'hidden';
                productIdInput.name = 'product_id';
                productIdInput.value = '<?php echo htmlspecialchars($editing_product_id ?? ''); ?>';
                form.appendChild(actionInput);
                form.appendChild(productIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        // Multiple video preview and sync to hidden file input (server picks up from upload form)
        document.getElementById('product_video_display')?.addEventListener('change', function(e) {
            const files = Array.from(e.target.files || []);
            const hiddenInput = document.getElementById('product_videos'); // sync to hidden multi-file input
            if (hiddenInput) {
                const dt = new DataTransfer();
                files.forEach(f => dt.items.add(f));
                hiddenInput.files = dt.files;
            }
            const videoContainer = e.target.closest('.col-12');
            let preview = document.querySelector('.video-preview');
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'video-preview mt-2';
                videoContainer.appendChild(preview);
            }
            if (!files.length) {
                preview.innerHTML = '';
                return;
            }
            let html = '';
            files.forEach((file) => {
                const url = URL.createObjectURL(file);
                html += `
                    <div class="card mb-2">
                        <div class="card-body d-flex align-items-center gap-3">
                            <video controls style="width:180px;height:auto" class="rounded">
                                <source src="${url}" type="${file.type}">
                            </video>
                            <div class="small text-muted">
                                <div><strong>File:</strong> ${file.name}</div>
                                <div><strong>Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                            </div>
                        </div>
                    </div>`;
            });
            preview.innerHTML = html;
        });
        // Function to preserve specifications when navigating between steps
        function preserveSpecifications() {
            // Check if we have existing specifications in session
            <?php if (!empty($editing_product_id) && isset($_SESSION['existing_specifications'])): ?>
                const existingSpecs = <?php echo json_encode($_SESSION['existing_specifications']); ?>;
                if (existingSpecs && Array.isArray(existingSpecs) && existingSpecs.length > 0) {
                    // Store in a global variable for the next step
                    window.preservedSpecifications = existingSpecs;
                }
            <?php endif; ?>
        }
        // Function to validate and test image paths
        function validateImagePath(imagePath) {
            return new Promise((resolve) => {
                const img = new Image();
                img.onload = () => resolve(true);
                img.onerror = () => resolve(false);
                img.src = '<?php echo PUBLIC_ASSETS; ?>' + imagePath;
            });
        }

        // Function to test all existing image paths
        async function testExistingImagePaths() {
            const existingImages = <?php echo json_encode($existing_images_array); ?>;
            if (existingImages && existingImages.length > 0) {
                // console.log('Testing existing image paths...');
                for (let i = 0; i < existingImages.length; i++) {
                    const path = existingImages[i];
                    const isValid = await validateImagePath(path);
                    // console.log(`Image ${i + 1}: ${path} - ${isValid ? 'Valid' : 'Invalid'}`);
                }
            }
        }

        // Call preserveSpecifications when page loads
        document.addEventListener('DOMContentLoaded', function() {
            preserveSpecifications();
            // Test image paths after a short delay to allow page to load
            setTimeout(testExistingImagePaths, 1000);
        });
    </script>
</body>

</html>