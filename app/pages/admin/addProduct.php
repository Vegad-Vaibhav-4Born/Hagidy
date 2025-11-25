<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}

$superadmin_id = $_SESSION['superadmin_id'];
$editing_product_id = isset($_GET['id']) ? trim($_GET['id']) : '';
$existing_product = null;
$vendor_id = '';
if (!empty($editing_product_id)) {
    $rs_prefill = mysqli_query($con, "SELECT * FROM products WHERE id='" . mysqli_real_escape_string($con, $editing_product_id) . "' LIMIT 1");
    if ($rs_prefill && mysqli_num_rows($rs_prefill) > 0) {
        $existing_product = mysqli_fetch_assoc($rs_prefill);
        $vendor_id = isset($existing_product['vendor_id']) ? $existing_product['vendor_id'] : '';
    }
}

// Inline AJAX: return attribute values for a given attribute id
if (isset($_GET['ajax']) && $_GET['ajax'] === 'attribute_values') {
    header('Content-Type: application/json');
    $attrId = isset($_GET['id']) ? mysqli_real_escape_string($con, $_GET['id']) : '';
    $items = [];
    $attribute_type = 'multi'; // default
    if (!empty($attrId)) {
        $rs = mysqli_query($con, "SELECT attribute_values, attribute_type FROM attributes WHERE id = '$attrId' LIMIT 1");
        if ($rs && mysqli_num_rows($rs) > 0) {
            $row = mysqli_fetch_assoc($rs);
            $json = $row['attribute_values'];
            $attribute_type = $row['attribute_type'] ?? 'multi';
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                foreach ($decoded as $v) {
                    if (isset($v['value_id']) && isset($v['value_name'])) {
                        $items[] = ['id' => $v['value_id'], 'name' => $v['value_name']];
                    }
                }
            }
        }
    }
    echo json_encode(['success' => true, 'values' => $items, 'attribute_type' => $attribute_type]);
    exit;
}
// Check if we're editing an existing product first (to determine which session keys to use)
$editing_product_id_temp = isset($_GET['id']) ? trim($_GET['id']) : '';
if (empty($editing_product_id_temp) && isset($_SESSION['editing_product_id'])) {
    $editing_product_id_temp = $_SESSION['editing_product_id'];
}

// Pull pending payload and images from previous step
// Use product ID-specific session keys for edit mode, or regular keys for new products
// Check for admin-specific keys first (from first-edit-product.php), then fallback to regular keys
if (!empty($editing_product_id_temp)) {
    // Try admin-specific keys first (used by first-edit-product.php)
    $session_images_key_admin = 'pending_product_images_admin_' . $editing_product_id_temp;
    $session_video_key_admin = 'pending_product_video_admin_' . $editing_product_id_temp;
    $session_videos_key_admin = 'pending_product_videos_admin_' . $editing_product_id_temp;
    $session_path_key_admin = 'pending_product_path_admin_' . $editing_product_id_temp;
    $session_payload_key_admin = 'pending_product_payload_admin_' . $editing_product_id_temp;
    
    // Fallback to regular keys (for backward compatibility)
    $session_images_key = 'pending_product_images_' . $editing_product_id_temp;
    $session_video_key = 'pending_product_video_' . $editing_product_id_temp;
    $session_videos_key = 'pending_product_videos_' . $editing_product_id_temp;
    $session_path_key = 'pending_product_path_' . $editing_product_id_temp;
    $session_payload_key = 'pending_product_payload_' . $editing_product_id_temp;
    
    // Use admin keys if they exist, otherwise use regular keys
    $pending_images = isset($_SESSION[$session_images_key_admin]) ? $_SESSION[$session_images_key_admin] : (isset($_SESSION[$session_images_key]) ? $_SESSION[$session_images_key] : []);
    $pending_video = isset($_SESSION[$session_video_key_admin]) ? $_SESSION[$session_video_key_admin] : (isset($_SESSION[$session_video_key]) ? $_SESSION[$session_video_key] : '');
    $pending_path = isset($_SESSION[$session_path_key_admin]) ? $_SESSION[$session_path_key_admin] : (isset($_SESSION[$session_path_key]) ? $_SESSION[$session_path_key] : null);
    $pending_payload_json = isset($_SESSION[$session_payload_key_admin]) ? $_SESSION[$session_payload_key_admin] : (isset($_SESSION[$session_payload_key]) ? $_SESSION[$session_payload_key] : '');
    
    // Also check for videos array (admin version)
    $pending_videos = isset($_SESSION[$session_videos_key_admin]) ? $_SESSION[$session_videos_key_admin] : (isset($_SESSION[$session_videos_key]) ? $_SESSION[$session_videos_key] : []);
} else {
    $pending_images = isset($_SESSION['pending_product_images']) ? $_SESSION['pending_product_images'] : [];
    $pending_video = isset($_SESSION['pending_product_video']) ? $_SESSION['pending_product_video'] : '';
    $pending_path = isset($_SESSION['pending_product_path']) ? $_SESSION['pending_product_path'] : null;
    $pending_payload_json = isset($_SESSION['pending_product_payload']) ? $_SESSION['pending_product_payload'] : '';
    $pending_videos = isset($_SESSION['pending_product_videos']) ? $_SESSION['pending_product_videos'] : [];
}

$pending_payload = [];
if (!empty($pending_payload_json)) {
    $tmp = json_decode($pending_payload_json, true);
    if (is_array($tmp)) {
        $pending_payload = $tmp;
    }
}

// Debug: Log session data for troubleshooting
error_log("Session data in addProduct.php:");
error_log("pending_images: " . print_r($pending_images, true));
error_log("pending_video: " . $pending_video);
error_log("pending_path: " . $pending_path);
error_log("pending_payload: " . print_r($pending_payload, true));
// Check if we're editing an existing product
$editing_product_id = isset($_GET['id']) ? trim($_GET['id']) : '';
// If not in URL, check session (for when coming back from first step)
if (empty($editing_product_id) && isset($_SESSION['editing_product_id'])) {
    $editing_product_id = $_SESSION['editing_product_id'];
}
// Also check in pending payload
if (empty($editing_product_id) && !empty($pending_payload) && isset($pending_payload['editing_product_id'])) {
    $editing_product_id = $pending_payload['editing_product_id'];
}
// Debug: Log the editing product ID for troubleshooting
error_log("Editing product ID determined: " . $editing_product_id);
$existing_product = null;
// Only clear stale session data if we're not just viewing the page
if (!empty($editing_product_id) && !isset($_GET['view_only'])) {
    // Use product ID-specific session key (check both admin and regular versions)
    $session_specs_key = 'existing_specifications_' . $editing_product_id;
    $session_specs_key_admin = 'existing_specifications_admin_' . $editing_product_id;
    // Only clear if we're starting a fresh edit, not when navigating back
    if (!isset($_SESSION[$session_specs_key]) && !isset($_SESSION[$session_specs_key_admin])) {
        unset($_SESSION[$session_specs_key], $_SESSION[$session_specs_key_admin]);
        error_log("Cleared stale session data for product edit: " . $editing_product_id);
    }
}
if (!empty($editing_product_id)) {
    // Admin can edit any product, so don't filter by vendor_id
    // If existing_product wasn't loaded earlier, load it now
    if (empty($existing_product)) {
        $rs_prefill = mysqli_query($con, "SELECT * FROM products WHERE id='" . mysqli_real_escape_string($con, $editing_product_id) . "' LIMIT 1");
    if ($rs_prefill && mysqli_num_rows($rs_prefill) > 0) {
        $existing_product = mysqli_fetch_assoc($rs_prefill);
            // Ensure vendor_id is set from existing product
            if (empty($vendor_id) && isset($existing_product['vendor_id'])) {
                $vendor_id = $existing_product['vendor_id'];
            }
        }
    }
    if (!empty($existing_product)) {
        // If no pending payload from session, use existing product data
        if (empty($pending_payload)) {
            $pending_payload = [
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
                'editing_product_id' => $editing_product_id,
            ];
        }
        // Handle existing images if no new ones uploaded
        if (empty($pending_images)) {
            $existing_images = $existing_product['images'] ?? '';
            if (!empty($existing_images)) {
                $decoded_images = json_decode($existing_images, true);
                if (is_array($decoded_images)) {
                    $pending_images = $decoded_images;
                }
            }
        }
        // Handle existing specifications - load from database ONLY if not already loaded before
        // Use product ID-specific session key (check both admin and regular versions)
        $session_specs_key = 'existing_specifications_' . $editing_product_id;
        $session_specs_key_admin = 'existing_specifications_admin_' . $editing_product_id;
        $session_loaded_flag_key = 'db_specs_loaded_' . $editing_product_id;
        
        // Check if specs exist in either admin or regular session key
        $existing_specs_in_session = isset($_SESSION[$session_specs_key_admin]) ? $_SESSION[$session_specs_key_admin] : (isset($_SESSION[$session_specs_key]) ? $_SESSION[$session_specs_key] : null);
        
        // Only load from database if we haven't loaded it before (first visit)
        // This prevents overwriting localStorage data on page refresh
        if (!isset($_SESSION[$session_loaded_flag_key]) || $_SESSION[$session_loaded_flag_key] !== true) {
            if (empty($existing_specs_in_session)) {
                $existing_specifications = $existing_product['specifications'] ?? '';
                if (!empty($existing_specifications)) {
                    $decoded_specs = json_decode($existing_specifications, true);
                    if (is_array($decoded_specs)) {
                        // Set fresh data from database (first time only) - use admin key if we're in admin context
                        $key_to_use = isset($_SESSION['superadmin_id']) ? $session_specs_key_admin : $session_specs_key;
                        $_SESSION[$key_to_use] = $decoded_specs;
                        $_SESSION[$session_loaded_flag_key] = true; // Mark as loaded
                        // Debug: Log the specifications for troubleshooting
                        error_log("Existing specifications loaded from database for product " . $editing_product_id . " (FIRST VISIT): " . print_r($decoded_specs, true));
                    }
                } else {
                    // Clear any stale session data if no specifications in database
                    unset($_SESSION[$session_specs_key]);
                    $_SESSION[$session_loaded_flag_key] = true; // Mark as loaded even if empty
                    error_log("No existing specifications found in database, cleared session");
                }
            } else {
                // Session already has data, mark as loaded
                $_SESSION[$session_loaded_flag_key] = true;
                error_log("Using existing specifications from session for product " . $editing_product_id);
            }
        } else {
            // Already loaded from database before, don't reload (preserve localStorage changes)
            error_log("Skipping database load for product " . $editing_product_id . " - already loaded before, using localStorage");
        }
    }
}
// Determine category id for edit flow to drive mandatory attributes
$edit_category_id = '';
$edit_sub_category_id = '';
if (!empty($editing_product_id)) {
    if (!empty($pending_payload)) {
        $edit_category_id = $pending_payload['category_id'] ?? '';
        $edit_sub_category_id = $pending_payload['sub_category_id'] ?? '';
    }
    if (empty($edit_category_id) && !empty($existing_product)) {
        $edit_category_id = $existing_product['category_id'] ?? '';
    }
    if (empty($edit_sub_category_id) && !empty($existing_product)) {
        $edit_sub_category_id = $existing_product['sub_category_id'] ?? '';
    }
} else {
    // For new products, check session for stored subcategory from first step
    $stored_subcategory = isset($_SESSION['selected_subcategory_for_mandatory']) ? $_SESSION['selected_subcategory_for_mandatory'] : '';
    if (!empty($stored_subcategory)) {
        $edit_sub_category_id = $stored_subcategory;
        // Also get the parent category ID for the subcategory
        $subcat_query = mysqli_query($con, "SELECT category_id FROM sub_category WHERE id='" . mysqli_real_escape_string($con, $stored_subcategory) . "' LIMIT 1");
        if ($subcat_query && mysqli_num_rows($subcat_query) > 0) {
            $subcat_row = mysqli_fetch_assoc($subcat_query);
            $edit_category_id = $subcat_row['category_id'] ?? '';
        }
    }
}
// Debug: Log the category ID and existing product data
error_log("Edit category ID: " . $edit_category_id);
if (!empty($existing_product)) {
    error_log("Existing product category_id: " . ($existing_product['category_id'] ?? 'not set'));
}
// Preload mandatory & optional attributes for the resolved subcategory (prefer subcategory over category)
$preloaded_mandatory_ids = [];
$preloaded_optional_ids = [];
if (!empty($edit_sub_category_id)) {
    $mand_opt_rs = mysqli_query($con, "SELECT mandatory_attributes, optional_attributes FROM sub_category WHERE id='" . mysqli_real_escape_string($con, $edit_sub_category_id) . "' LIMIT 1");
    if ($mand_opt_rs && mysqli_num_rows($mand_opt_rs) > 0) {
        $mand_opt_row = mysqli_fetch_assoc($mand_opt_rs);
        $mand_csv = trim((string)($mand_opt_row['mandatory_attributes'] ?? ''));
        if ($mand_csv !== '' && strtolower($mand_csv) !== 'null') {
            $preloaded_mandatory_ids = array_values(array_filter(array_map(function ($v) { return (int)trim($v); }, explode(',', $mand_csv))));
        }
        $opt_csv = trim((string)($mand_opt_row['optional_attributes'] ?? ''));
        if ($opt_csv !== '' && strtolower($opt_csv) !== 'null') {
            $preloaded_optional_ids = array_values(array_filter(array_map(function ($v) { return (int)trim($v); }, explode(',', $opt_csv))));
        }
    }
}
// If no subcategory-specific data but category is available, try to get any representative subcategory under the category (fallback)
if (empty($preloaded_mandatory_ids) && !empty($edit_category_id)) {
    $fallback_rs = mysqli_query($con, "SELECT mandatory_attributes, optional_attributes FROM sub_category WHERE category_id='" . mysqli_real_escape_string($con, $edit_category_id) . "' AND (mandatory_attributes IS NOT NULL OR optional_attributes IS NOT NULL) ORDER BY id LIMIT 1");
    if ($fallback_rs && mysqli_num_rows($fallback_rs) > 0) {
        $fallback_row = mysqli_fetch_assoc($fallback_rs);
        $mand_csv = trim((string)($fallback_row['mandatory_attributes'] ?? ''));
        if ($mand_csv !== '' && strtolower($mand_csv) !== 'null') {
            $preloaded_mandatory_ids = array_values(array_filter(array_map(function ($v) { return (int)trim($v); }, explode(',', $mand_csv))));
        }
        $opt_csv = trim((string)($fallback_row['optional_attributes'] ?? ''));
        if ($opt_csv !== '' && strtolower($opt_csv) !== 'null') {
            $preloaded_optional_ids = array_values(array_filter(array_map(function ($v) { return (int)trim($v); }, explode(',', $opt_csv))));
        }
    }
}
// Handle final submit to DB
$success_msg = '';
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['final_submit']) && $_POST['final_submit'] === '1') {
    // Read specifications JSON from hidden field
    $specifications_json = isset($_POST['specifications_json']) ? trim($_POST['specifications_json']) : '';
    $decoded_specs = [];
    if (!empty($specifications_json)) {
        $tmpSpecs = json_decode($specifications_json, true);
        if (is_array($tmpSpecs)) {
            $decoded_specs = $tmpSpecs;
        }
    }
    // Validate and set default value for specifications
    if (empty($specifications_json) || $specifications_json === '[]' || $specifications_json === 'null') {
        $specifications_json = '[]'; // Default to empty array
        error_log("Specifications JSON was empty or invalid, setting to empty array");
    }
    // Debug: Log the specifications JSON received
    error_log("Specifications JSON received: " . $specifications_json);
    // Validate mandatory attributes for selected category (server-side enforcement)
    $validation_errors = [];
    $selected_category_id = '';
    $selected_subcategory_id = '';
    
    // Get category and subcategory from pending_payload first (for new products)
    if (!empty($pending_payload)) {
        $selected_category_id = $pending_payload['category_id'] ?? '';
        $selected_subcategory_id = $pending_payload['sub_category_id'] ?? '';
    }
    
    // Fallback to existing_product for edit mode
    if (empty($selected_category_id) && !empty($existing_product)) {
        $selected_category_id = $existing_product['category_id'] ?? '';
    }
    if (empty($selected_subcategory_id) && !empty($existing_product)) {
        $selected_subcategory_id = $existing_product['sub_category_id'] ?? '';
    }
    
    if (!empty($selected_category_id)) {
        // Prioritize subcategory over category for mandatory attributes
        if (!empty($selected_subcategory_id)) {
            // Query sub_category table for mandatory attributes using the specific subcategory
            error_log("Validating mandatory attributes for subcategory ID: " . $selected_subcategory_id);
            $cat_q = mysqli_query($con, "SELECT mandatory_attributes FROM sub_category WHERE id='" . mysqli_real_escape_string($con, $selected_subcategory_id) . "' LIMIT 1");
        } else {
            // Fallback to category-based mandatory attributes (if any subcategory under this category has them)
            error_log("No subcategory found, validating mandatory attributes for category ID: " . $selected_category_id);
            $cat_q = mysqli_query($con, "SELECT mandatory_attributes FROM sub_category WHERE category_id='" . mysqli_real_escape_string($con, $selected_category_id) . "' AND mandatory_attributes IS NOT NULL AND mandatory_attributes <> '' ORDER BY id LIMIT 1");
        }
        // Validate that each mandatory attribute has at least one variant selected
        $mandatory_ids = [];
        if ($cat_q && mysqli_num_rows($cat_q) > 0) {
            $cat_row = mysqli_fetch_assoc($cat_q);
            $mand_csv = trim((string) ($cat_row['mandatory_attributes'] ?? ''));
            if ($mand_csv !== '') {
                $mandatory_ids = array_filter(array_map(function ($v) {
                    return (int) trim($v); }, explode(',', $mand_csv)));
                error_log("Mandatory attribute IDs found: " . implode(', ', $mandatory_ids));
            } else {
                error_log("No mandatory attributes found in sub_category table");
            }
        } else {
            error_log("No sub_category record found for validation");
        }
        if (!empty($mandatory_ids)) {
            // Build a map of provided attribute_id => number of selected variants
            $provided = [];
            if (is_array($decoded_specs)) {
                foreach ($decoded_specs as $spec) {
                    $aid = isset($spec['attribute_id']) ? (int) $spec['attribute_id'] : 0;
                    $variants = isset($spec['variants']) && is_array($spec['variants']) ? $spec['variants'] : [];
                    if ($aid > 0) {
                        $provided[$aid] = count($variants);
                    }
                }
            }
            error_log("Provided attribute IDs with variants: " . json_encode($provided));
            // Fetch attribute names for friendly error messages
            $attrNames = [];
            $idsForSql = array_map('intval', $mandatory_ids);
            $idsForSql = array_unique(array_filter($idsForSql));
            if (!empty($idsForSql)) {
                $idList = implode(',', $idsForSql);
                $attr_rs = mysqli_query($con, "SELECT id, name FROM attributes WHERE id IN ($idList)");
                if ($attr_rs && mysqli_num_rows($attr_rs) > 0) {
                    while ($ar = mysqli_fetch_assoc($attr_rs)) {
                        $attrNames[(int) $ar['id']] = $ar['name'];
                    }
                }
            }
            foreach ($mandatory_ids as $mid) {
                if (!isset($provided[$mid]) || (int) $provided[$mid] < 1) {
                    $label = isset($attrNames[$mid]) && $attrNames[$mid] !== '' ? $attrNames[$mid] : ("ID: " . $mid);
                    $validation_errors[] = "Please select at least one value for the required attribute: " . $label . ".";
                }
            }
        }
    }
    if (!empty($validation_errors)) {
        $error_msg = implode('\n', $validation_errors);
    }
    // Merge with pending payload
    $data = $pending_payload;
    // If discount is missing (common when the input is disabled in previous step),
    // compute it from MRP and Selling Price server-side so it is always saved.
    if (
        (!
            isset($data['discount']) || $data['discount'] === '' || $data['discount'] === null
        ) && isset($data['mrp'], $data['selling_price']) && is_numeric($data['mrp']) && is_numeric($data['selling_price'])
    ) {
        $mrpVal = (float) $data['mrp'];
        $spVal = (float) $data['selling_price'];
        if ($mrpVal > 0 && $spVal >= 0 && $spVal <= $mrpVal) {
            $computedDiscount = round((($mrpVal - $spVal) / $mrpVal) * 100, 2);
            $data['discount'] = (string) $computedDiscount;
            error_log('Computed discount server-side: ' . $data['discount']);
        }
    }
    // If discount exists but contains a percent sign or other characters, normalize to numeric
    if (isset($data['discount']) && $data['discount'] !== '' && $data['discount'] !== null) {
        $cleanDiscount = preg_replace('/[^0-9.]+/', '', (string) $data['discount']);
        if ($cleanDiscount !== '') {
            $data['discount'] = $cleanDiscount;
        }
    }
    // Normalize keys coming from previous steps
    if (isset($data['inventory']) && !isset($data['Inventory'])) {
        $data['Inventory'] = $data['inventory'];
    }
    // Ensure NOT NULL numeric fields have default values if empty
    $notNullNumericFields = ['mrp', 'selling_price', 'gst', 'hsn_id', 'Inventory'];
    foreach ($notNullNumericFields as $field) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            $data[$field] = 0;
        }
    }
    $data['specifications'] = $specifications_json;
    // Ensure specifications is never null or empty
    if (empty($data['specifications'])) {
        $data['specifications'] = '[]';
    }
    // Server-side price validation: Selling Price must be <= MRP for product and variants
    if ($error_msg === '') {
        // Validate base product prices if present
        if (isset($data['mrp'], $data['selling_price']) && is_numeric($data['mrp']) && is_numeric($data['selling_price'])) {
            $baseMrp = (float) $data['mrp'];
            $baseSp = (float) $data['selling_price'];
            if ($baseSp > $baseMrp) {
                $error_msg = 'Selling Price must be less than or equal to MRP.';
            }
        }
        // Validate variant-level prices inside specifications (if provided)
        if ($error_msg === '' && is_array($decoded_specs) && !empty($decoded_specs)) {
            foreach ($decoded_specs as $spec) {
                if (!isset($spec['variants']) || !is_array($spec['variants'])) {
                    continue;
                }
                foreach ($spec['variants'] as $variant) {
                    $vMrp = isset($variant['mrp']) && is_numeric($variant['mrp']) ? (float) $variant['mrp'] : null;
                    $vSp = isset($variant['selling_price']) && is_numeric($variant['selling_price']) ? (float) $variant['selling_price'] : null;
                    if ($vMrp !== null && $vSp !== null && $vSp > $vMrp) {
                        $variantLabel = isset($variant['id']) ? (string) $variant['id'] : 'one of the variants';
                        $error_msg = 'Selling Price must be less than or equal to MRP ';
                        break 2;
                    }
                }
            }
        }
    }
    // Build full image paths using proper folder structure: uploads/vendors/businessname/productname/
    // Get business name from vendor details first
    $businessName = '';
    // In admin context, get vendor_id from existing product or payload
    $vendorIdForPath = '';
    if (!empty($vendor_id)) {
        $vendorIdForPath = $vendor_id;
    } elseif (!empty($pending_payload) && isset($pending_payload['vendor_id'])) {
        $vendorIdForPath = $pending_payload['vendor_id'];
    } elseif (isset($_SESSION['vendor_reg_id'])) {
        $vendorIdForPath = $_SESSION['vendor_reg_id'];
    }
    
    if (isset($con) && $con && !empty($vendorIdForPath)) {
        $vendorId = mysqli_real_escape_string($con, $vendorIdForPath);
        $rs = mysqli_query($con, "SELECT business_name FROM vendor_registration WHERE id = '$vendorId' LIMIT 1");
        if ($rs && mysqli_num_rows($rs) > 0) {
            $vendorData = mysqli_fetch_assoc($rs);
            $businessName = $vendorData['business_name'];
        }
    }
    // Clean business name and product name for folder structure
    // Use exact business name from database to match existing folder structure
    // $cleanBusinessName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $businessName);
    // $cleanProductName = isset($data['product_name']) ? strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $data['product_name'])) : 'product';
    $cleanBusinessName = $businessName;
    $cleanProductName = isset($data['product_name']) ? strtolower($data['product_name']) : 'product';

    // Build base path: uploads/vendors/businessname/productname/
    $basePath = "uploads/vendors/{$cleanBusinessName}/{$cleanProductName}";

    if (is_array($pending_images)) {
        $fullImages = [];
        foreach ($pending_images as $img) {
            $img = trim($img);
            if ($img === '')
                continue;
            // If already a full path starting with uploads/, keep as is
            if (preg_match('/^uploads\//i', $img)) {
                $fullImages[] = $img;
            } else {
                // Build full path: uploads/vendors/businessname/productname/filename
                $fullImages[] = $basePath . '/' . ltrim($img, '/');
            }
        }
        $data['images'] = json_encode($fullImages, JSON_UNESCAPED_SLASHES);
        error_log("Images data prepared for DB: " . $data['images']);
    } else {
        $data['images'] = json_encode([], JSON_UNESCAPED_SLASHES);
        error_log("No pending images found, setting empty array");
    }
    // Handle video
// First, load existing videos from database if editing
    $existing_videos = [];
    if (!empty($editing_product_id) && !empty($existing_product['video'])) {
        // Parse videos using regex to handle commas within folder names
        $videoString = (string) $existing_product['video'];
        if (!empty($videoString)) {
            preg_match_all('/(.*?\.(?:mp4|mov|avi|webm|mkv|flv|wmv|m4v)(?:\?.*)?)(?:,|$)/i', $videoString, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $vp) {
                    $vp = trim($vp);
                    if (!empty($vp)) {
                        $existing_videos[] = $vp;
                    }
                }
            } else {
                // Fallback: if no video extension found, treat entire string as single video
                $vp = trim($videoString);
                if (!empty($vp)) {
                    $existing_videos[] = $vp;
                }
            }
        }
        error_log("Existing videos loaded from database: " . print_r($existing_videos, true));
    }

    // Support multiple videos: read from session array and store as comma-separated list
    // Use product ID-specific session key for edit mode (check admin version first)
    // Note: $pending_videos may have been set earlier, but we check again here to ensure we have the latest
    if (!empty($editing_product_id)) {
        $session_videos_key_admin = 'pending_product_videos_admin_' . $editing_product_id;
        $session_videos_key = 'pending_product_videos_' . $editing_product_id;
        // Use admin key if it exists, otherwise use regular key
        if (isset($_SESSION[$session_videos_key_admin]) && is_array($_SESSION[$session_videos_key_admin])) {
            $pending_videos = $_SESSION[$session_videos_key_admin];
        } elseif (isset($_SESSION[$session_videos_key]) && is_array($_SESSION[$session_videos_key])) {
            $pending_videos = $_SESSION[$session_videos_key];
        } else {
            $pending_videos = isset($pending_videos) ? $pending_videos : [];
        }
    } else {
        $pending_videos = isset($_SESSION['pending_product_videos']) && is_array($_SESSION['pending_product_videos']) ? $_SESSION['pending_product_videos'] : [];
    }
    
    if (!empty($pending_videos) && count($pending_videos) > 0) {
        $fullVideos = [];
        foreach ($pending_videos as $v) {
            $fullVideos[] = $basePath . '/' . ltrim($v, '/');
        }
        // Merge with existing videos if editing
        $allVideos = array_merge($existing_videos, $fullVideos);
        $data['video'] = implode(',', $allVideos);
        error_log("Video paths constructed (merged with existing): " . $data['video']);
    } elseif (!empty($pending_video)) { // backward compatibility single video name
        $new_video_path = $basePath . '/' . ltrim($pending_video, '/');
        $allVideos = array_merge($existing_videos, [$new_video_path]);
        $data['video'] = implode(',', $allVideos);
        error_log("Video path constructed (merged with existing): " . $data['video']);
    } else {
        // No pending videos: keep existing if editing, else null
        if (!empty($existing_videos)) {
            $data['video'] = implode(',', $existing_videos);
            error_log("No pending videos, keeping existing: " . $data['video']);
        } else {
            $data['video'] = null;
            error_log("No pending video found");
        }
    }
    // Ensure vendor_id is always set - get from existing product if not already set
    if (empty($vendor_id) && !empty($existing_product) && isset($existing_product['vendor_id'])) {
        $vendor_id = $existing_product['vendor_id'];
    }
    // Also check in pending_payload (from first-edit-product.php)
    if (empty($vendor_id) && !empty($pending_payload) && isset($pending_payload['vendor_id'])) {
        $vendor_id = $pending_payload['vendor_id'];
    }
    // Ensure vendor_id is set in data array
    if (!empty($vendor_id)) {
    $data['vendor_id'] = $vendor_id;
    } else {
        error_log("WARNING: vendor_id is empty when trying to update product " . $editing_product_id);
    }
    // Handle status for edit vs new

    if (!empty($editing_product_id)) {
        $status_changed_to_under_review = false;
        $only_inventory_changed = true; // Assume only inventory changed until proven otherwise
        
        // Fields to compare (excluding status, inventory, vendor_id, updated_date, created_date, seen)
        $fields_to_compare = [
            'product_name', 'category_id', 'sub_category_id', 'mrp', 'selling_price', 
            'discount', 'description', 'brand', 'gst', 'hsn_id', 
            'manufacture_details', 'packaging_details', 'sku_id', 'group_id', 
            'style_id', 'product_brand', 'images', 'video'
        ];
        
        // Compare each field (excluding inventory)
        foreach ($fields_to_compare as $field) {
            $existing_value = $existing_product[$field] ?? null;
            $new_value = $data[$field] ?? null;
            
            // Normalize values for comparison
            if ($field === 'images') {
                // Compare JSON arrays for images
                $existing_images = is_string($existing_value) ? json_decode($existing_value, true) : $existing_value;
                $new_images = is_string($new_value) ? json_decode($new_value, true) : $new_value;
                if (json_encode($existing_images) !== json_encode($new_images)) {
                    $only_inventory_changed = false;
                    error_log("Field '$field' changed for product " . $editing_product_id);
                    break;
                }
            } else {
                // String comparison for other fields
                $existing_str = (string) ($existing_value ?? '');
                $new_str = (string) ($new_value ?? '');
                if ($existing_str !== $new_str) {
                    $only_inventory_changed = false;
                    error_log("Field '$field' changed for product " . $editing_product_id);
                    break;
                }
            }
        }
        
        // Check if specifications have changed
        if ($only_inventory_changed) {
            if (!empty($existing_product['specifications'])) {
                $existing_specs = json_decode($existing_product['specifications'], true);
                $new_specs = $decoded_specs;
                // Normalize both arrays for comparison (sort by attribute_id and variant id)
                $normalize_specs = function ($specs) {
                    if (!is_array($specs))
                        return [];
                    $normalized = [];
                    foreach ($specs as $spec) {
                        if (!isset($spec['attribute_id']))
                            continue;
                        $attr_id = (int) $spec['attribute_id'];
                        $variants = [];
                        if (isset($spec['variants']) && is_array($spec['variants'])) {
                            foreach ($spec['variants'] as $variant) {
                                $variant_id = isset($variant['id']) ? (string) $variant['id'] : '';
                                $variant_data = ['id' => $variant_id];
                                if (isset($variant['mrp']))
                                    $variant_data['mrp'] = (float) $variant['mrp'];
                                if (isset($variant['selling_price']))
                                    $variant_data['selling_price'] = (float) $variant['selling_price'];
                                $variants[] = $variant_data;
                            }
                            // Sort variants by id for consistent comparison
                            usort($variants, function ($a, $b) {
                                return strcmp($a['id'], $b['id']); });
                        }
                        $normalized[] = [
                            'attribute_id' => $attr_id,
                            'variants' => $variants
                        ];
                    }
                    // Sort by attribute_id for consistent comparison
                    usort($normalized, function ($a, $b) {
                        return $a['attribute_id'] - $b['attribute_id']; });
                    return $normalized;
                };
                $existing_normalized = $normalize_specs($existing_specs);
                $new_normalized = $normalize_specs($new_specs);
                // Compare normalized specifications to detect changes
                if (json_encode($existing_normalized) !== json_encode($new_normalized)) {
                    $only_inventory_changed = false;
                    error_log("Specifications changed for product " . $editing_product_id);
                }
            } else if (!empty($decoded_specs)) {
                // If there were no existing specifications but new ones are being added
                $only_inventory_changed = false;
                error_log("New specifications added for product " . $editing_product_id);
            }
        }
        
        // Check if inventory changed
        $existing_inventory = (string) ($existing_product['Inventory'] ?? '');
        $new_inventory = (string) ($data['Inventory'] ?? '');
        $inventory_changed = ($existing_inventory !== $new_inventory);
        
        // Determine status based on what changed
        // Admin edits should NOT change status to "under_review" - preserve existing status
        if (isset($_SESSION['superadmin_id']) && !empty($_SESSION['superadmin_id'])) {
            // Admin is editing - preserve existing status, don't change to under_review
            $data['status'] = $existing_product['status'] ?? 'pending';
            $data['seen'] = 0;
            error_log("Admin edited product " . $editing_product_id . ", preserving status: " . $data['status']);
        } else if ($only_inventory_changed && $inventory_changed) {
            // Only inventory changed - preserve existing status
            $data['status'] = $existing_product['status'] ?? 'pending';
            $data['seen'] = 0;
            error_log("Only inventory changed for product " . $editing_product_id . ", preserving status: " . $data['status']);
        } else if (!$only_inventory_changed) {
            // Some other field changed - set to under_review (only for vendor edits)
            $data['status'] = 'under_review';
            $data['seen'] = 0;
            $status_changed_to_under_review = true;
            error_log("Fields other than inventory changed for product " . $editing_product_id . ", setting status to under_review");
        } else {
            // No changes at all, or only status field changed - preserve existing status
            if (isset($pending_payload['update_status']) && $pending_payload['update_status'] === 'under_review') {
                $data['status'] = 'under_review';
                $data['seen'] = 0;
            } else {
                $data['seen'] = 0;
                $data['status'] = $existing_product['status'] ?? 'pending';
            }
        }
    } else {
        $data['seen'] = 0;
        $data['status'] = 'pending';
    }
    $data['updated_date'] = date('Y-m-d H:i:s');
    // Handle created_date - only set for new products
    if (empty($editing_product_id)) {
        $data['created_date'] = date('Y-m-d');
    }
    //print_r($data); 
    // Sanitize/prepare fields
    $fields = [
        'vendor_id',
        'product_name',
        'category_id',
        'sub_category_id',
        'mrp',
        'selling_price',
        'discount',
        'description',
        'brand',
        'gst',
        'hsn_id',
        'manufacture_details',
        'packaging_details',
        'sku_id',
        'group_id',
        'style_id',
        'Inventory',
        'product_brand',
        'images',
        'video',
        'specifications',
        'status',
        'seen',
        'updated_date'
    ];
    // Add created_date only for new products
    if (empty($editing_product_id)) {
        $fields[] = 'created_date';
    }
    if (empty($error_msg) && !empty($editing_product_id)) {
        // UPDATE existing product
        $setParts = [];
        $numericFields = ['mrp', 'selling_price', 'discount', 'gst', 'Inventory'];
        foreach ($fields as $f) {
            if (!isset($data[$f]) || $data[$f] === '' || $data[$f] === null) {
                // For NOT NULL numeric fields, use 0 as default instead of NULL
                if (in_array($f, ['mrp', 'selling_price', 'discount', 'gst', 'hsn_id', 'Inventory'], true)) {
                    $setParts[] = "$f = 0";
                } else {
                    $setParts[] = "$f = NULL";
                }
                continue;
            }
            if (in_array($f, $numericFields, true)) {
                $val = $data[$f];
                // For NOT NULL numeric fields, ensure they're never NULL
                if (in_array($f, ['mrp', 'selling_price', 'discount', 'gst', 'hsn_id', 'Inventory'], true)) {
                    $setParts[] = is_numeric($val) ? "$f = '" . mysqli_real_escape_string($con, $val) . "'" : "$f = 0";
                } else {
                    $setParts[] = is_numeric($val) ? "$f = '" . mysqli_real_escape_string($con, $val) . "'" : "$f = NULL";
                }
            } else {
                $setParts[] = "$f = '" . mysqli_real_escape_string($con, $data[$f]) . "'";
            }
        }
        // Admin can update any product, so don't require vendor_id in WHERE clause
        // But still include it in the SET clause if available
        $where_clause = "WHERE id = '" . mysqli_real_escape_string($con, $editing_product_id) . "'";
        if (!empty($vendor_id)) {
            // Include vendor_id in WHERE clause for safety, but don't fail if it's missing
            $where_clause .= " AND vendor_id = '" . mysqli_real_escape_string($con, $vendor_id) . "'";
        }
        $sql = "UPDATE products SET " . implode(', ', $setParts) . " " . $where_clause;
        // Debug: Log the UPDATE query and data
        error_log("UPDATE Query: " . $sql);
        error_log("UPDATE Data: " . print_r($data, true));
    } else if (empty($error_msg)) {
        // INSERT new product
        $cols = [];
        $vals = [];
        $numericFields = ['mrp', 'selling_price', 'discount', 'gst', 'Inventory'];
        foreach ($fields as $f) {
            $cols[] = $f;
            if (!isset($data[$f]) || $data[$f] === '' || $data[$f] === null) {
                // For NOT NULL numeric fields, use 0 as default instead of NULL
                if (in_array($f, ['mrp', 'selling_price', 'discount', 'gst', 'hsn_id', 'Inventory'], true)) {
                    $vals[] = '0';
                } else {
                    $vals[] = 'NULL';
                }
                continue;
            }
            if (in_array($f, $numericFields, true)) {
                $val = $data[$f];
                // For NOT NULL numeric fields, ensure they're never NULL
                if (in_array($f, ['mrp', 'selling_price', 'discount', 'gst', 'hsn_id', 'Inventory'], true)) {
                    $vals[] = is_numeric($val) ? "'" . mysqli_real_escape_string($con, $val) . "'" : '0';
                } else {
                    $vals[] = is_numeric($val) ? "'" . mysqli_real_escape_string($con, $val) . "'" : 'NULL';
                }
            } else {
                $vals[] = "'" . mysqli_real_escape_string($con, $data[$f]) . "'";
            }
        }
        $sql = "INSERT INTO products (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
        // Debug: Log the INSERT query and data
        error_log("INSERT Query: " . $sql);
        error_log("INSERT Data: " . print_r($data, true));
    }
    if (empty($error_msg) && mysqli_query($con, $sql)) {
        $success_msg = !empty($editing_product_id) ? 'Product updated successfully.' : 'Product saved successfully.';
        // Add status change notification if specifications were modified (only for vendor edits, not admin)
        if (!empty($editing_product_id) && isset($status_changed_to_under_review) && $status_changed_to_under_review && !isset($_SESSION['superadmin_id'])) {
            $success_msg .= ' The product status has been set to "Under Review" due to specification changes.';
        }
        // Debug: Show the specifications that were saved
        // if (!empty($specifications_json)) {
        //     $decoded_specs = json_decode($specifications_json, true);
        //     $success_msg .= '<br><strong>Specifications saved:</strong><br>';
        //     $success_msg .= '<pre>' . print_r($decoded_specs, true) . '</pre>';
        // }
        // Clear session buffers only after successful save
        // Clear both product ID-specific keys and regular keys (for backward compatibility)
        if (!empty($editing_product_id)) {
            // Clear both admin and regular session keys
            $session_images_key = 'pending_product_images_' . $editing_product_id;
            $session_images_key_admin = 'pending_product_images_admin_' . $editing_product_id;
            $session_video_key = 'pending_product_video_' . $editing_product_id;
            $session_video_key_admin = 'pending_product_video_admin_' . $editing_product_id;
            $session_videos_key = 'pending_product_videos_' . $editing_product_id;
            $session_videos_key_admin = 'pending_product_videos_admin_' . $editing_product_id;
            $session_path_key = 'pending_product_path_' . $editing_product_id;
            $session_path_key_admin = 'pending_product_path_admin_' . $editing_product_id;
            $session_payload_key = 'pending_product_payload_' . $editing_product_id;
            $session_payload_key_admin = 'pending_product_payload_admin_' . $editing_product_id;
            $session_specs_key = 'existing_specifications_' . $editing_product_id;
            $session_specs_key_admin = 'existing_specifications_admin_' . $editing_product_id;
            $session_loaded_flag_key = 'db_specs_loaded_' . $editing_product_id;
            // Clear all session data including the database load flag (both admin and regular versions)
            // This ensures next visit will load fresh from database
            unset($_SESSION[$session_images_key], $_SESSION[$session_images_key_admin], 
                  $_SESSION[$session_video_key], $_SESSION[$session_video_key_admin], 
                  $_SESSION[$session_videos_key], $_SESSION[$session_videos_key_admin], 
                  $_SESSION[$session_path_key], $_SESSION[$session_path_key_admin], 
                  $_SESSION[$session_payload_key], $_SESSION[$session_payload_key_admin], 
                  $_SESSION[$session_specs_key], $_SESSION[$session_specs_key_admin], 
                  $_SESSION[$session_loaded_flag_key]);
        }
        // Also clear regular session keys (for new products)
        unset($_SESSION['pending_product_images'], $_SESSION['pending_product_video'], $_SESSION['pending_product_videos'], $_SESSION['pending_product_payload'], $_SESSION['pending_product_path'], $_SESSION['editing_product_id']);
        // Flash success message for next page - clear any error messages first
        unset($_SESSION['flash_error'], $_SESSION['error_message']);
        $_SESSION['flash_success'] = $success_msg;
        // Set flag to clear localStorage after successful save
        if (!empty($editing_product_id)) {
            // For edit mode: clear product_specs_{product_id}
            $_SESSION['clear_localStorage_key'] = 'product_specs_' . $editing_product_id;
        } else {
            // For new product: clear product_specs_new_{product_name_key}
            $product_name = isset($data['product_name']) ? $data['product_name'] : '';
            if (!empty($product_name)) {
                $product_name_key = strtolower(preg_replace('/[^a-z0-9]/', '_', $product_name));
                $_SESSION['clear_localStorage_key'] = 'product_specs_new_' . $product_name_key;
            }
        }
        // Redirect to product management
        header('Location: product-management.php');
        exit;
    } else {
        if (empty($error_msg)) {
            $error_msg = 'Failed to save product: ' . mysqli_error($con);
        }
        // Also set flash error if you decide to redirect later - clear any success messages first
        unset($_SESSION['flash_success'], $_SESSION['success_message']);
        $_SESSION['flash_error'] = $error_msg;
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
    <title><?php echo !empty($editing_product_id) ? 'EDIT-PRODUCT' : 'ADD-PRODUCT'; ?> | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords" content="hagidy website">
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
    <!-- FlatPickr CSS -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.css">
    <!-- Choices Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/styles/choices.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
    <!-- Start Switcher -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="switcher-canvas" aria-labelledby="offcanvasRightLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title text-default" id="offcanvasRightLabel">Switcher</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="border-bottom border-block-end-dashed">
                <div class="nav nav-tabs nav-justified" id="switcher-main-tab" role="tablist">
                    <button class="nav-link active" id="switcher-home-tab" data-bs-toggle="tab"
                        data-bs-target="#switcher-home" type="button" role="tab" aria-controls="switcher-home"
                        aria-selected="true">Theme Styles</button>
                    <button class="nav-link" id="switcher-profile-tab" data-bs-toggle="tab"
                        data-bs-target="#switcher-profile" type="button" role="tab" aria-controls="switcher-profile"
                        aria-selected="false">Theme Colors</button>
                </div>
            </nav>
            <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade show active border-0" id="switcher-home" role="tabpanel"
                    aria-labelledby="switcher-home-tab" tabindex="0">
                    <div class="">
                        <p class="switcher-style-head">Theme Color Mode:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-light-theme">
                                        Light
                                    </label>
                                    <input class="form-check-input" type="radio" name="theme-style"
                                        id="switcher-light-theme" checked>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-dark-theme">
                                        Dark
                                    </label>
                                    <input class="form-check-input" type="radio" name="theme-style"
                                        id="switcher-dark-theme">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Directions:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-ltr">
                                        LTR
                                    </label>
                                    <input class="form-check-input" type="radio" name="direction" id="switcher-ltr"
                                        checked>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-rtl">
                                        RTL
                                    </label>
                                    <input class="form-check-input" type="radio" name="direction" id="switcher-rtl">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Navigation Styles:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-vertical">
                                        Vertical
                                    </label>
                                    <input class="form-check-input" type="radio" name="navigation-style"
                                        id="switcher-vertical" checked>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-horizontal">
                                        Horizontal
                                    </label>
                                    <input class="form-check-input" type="radio" name="navigation-style"
                                        id="switcher-horizontal">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="navigation-menu-styles">
                        <p class="switcher-style-head">Vertical & Horizontal Menu Styles:</p>
                        <div class="row switcher-style gx-0 pb-2 gy-2">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-menu-click">
                                        Menu Click
                                    </label>
                                    <input class="form-check-input" type="radio" name="navigation-menu-styles"
                                        id="switcher-menu-click">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-menu-hover">
                                        Menu Hover
                                    </label>
                                    <input class="form-check-input" type="radio" name="navigation-menu-styles"
                                        id="switcher-menu-hover">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-icon-click">
                                        Icon Click
                                    </label>
                                    <input class="form-check-input" type="radio" name="navigation-menu-styles"
                                        id="switcher-icon-click">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-icon-hover">
                                        Icon Hover
                                    </label>
                                    <input class="form-check-input" type="radio" name="navigation-menu-styles"
                                        id="switcher-icon-hover">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="sidemenu-layout-styles">
                        <p class="switcher-style-head">Sidemenu Layout Styles:</p>
                        <div class="row switcher-style gx-0 pb-2 gy-2">
                            <div class="col-sm-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-default-menu">
                                        Default Menu
                                    </label>
                                    <input class="form-check-input" type="radio" name="sidemenu-layout-styles"
                                        id="switcher-default-menu" checked>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-closed-menu">
                                        Closed Menu
                                    </label>
                                    <input class="form-check-input" type="radio" name="sidemenu-layout-styles"
                                        id="switcher-closed-menu">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-icontext-menu">
                                        Icon Text
                                    </label>
                                    <input class="form-check-input" type="radio" name="sidemenu-layout-styles"
                                        id="switcher-icontext-menu">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-icon-overlay">
                                        Icon Overlay
                                    </label>
                                    <input class="form-check-input" type="radio" name="sidemenu-layout-styles"
                                        id="switcher-icon-overlay">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-detached">
                                        Detached
                                    </label>
                                    <input class="form-check-input" type="radio" name="sidemenu-layout-styles"
                                        id="switcher-detached">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-double-menu">
                                        Double Menu
                                    </label>
                                    <input class="form-check-input" type="radio" name="sidemenu-layout-styles"
                                        id="switcher-double-menu">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Page Styles:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-regular">
                                        Regular
                                    </label>
                                    <input class="form-check-input" type="radio" name="page-styles"
                                        id="switcher-regular" checked>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-classic">
                                        Classic
                                    </label>
                                    <input class="form-check-input" type="radio" name="page-styles"
                                        id="switcher-classic">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-modern">
                                        Modern
                                    </label>
                                    <input class="form-check-input" type="radio" name="page-styles"
                                        id="switcher-modern">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Layout Width Styles:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-full-width">
                                        Full Width
                                    </label>
                                    <input class="form-check-input" type="radio" name="layout-width"
                                        id="switcher-full-width" checked>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-boxed">
                                        Boxed
                                    </label>
                                    <input class="form-check-input" type="radio" name="layout-width"
                                        id="switcher-boxed">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Menu Positions:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-menu-fixed">
                                        Fixed
                                    </label>
                                    <input class="form-check-input" type="radio" name="menu-positions"
                                        id="switcher-menu-fixed" checked>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-menu-scroll">
                                        Scrollable
                                    </label>
                                    <input class="form-check-input" type="radio" name="menu-positions"
                                        id="switcher-menu-scroll">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Header Positions:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-header-fixed">
                                        Fixed
                                    </label>
                                    <input class="form-check-input" type="radio" name="header-positions"
                                        id="switcher-header-fixed" checked>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-header-scroll">
                                        Scrollable
                                    </label>
                                    <input class="form-check-input" type="radio" name="header-positions"
                                        id="switcher-header-scroll">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Loader:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-loader-enable">
                                        Enable
                                    </label>
                                    <input class="form-check-input" type="radio" name="page-loader"
                                        id="switcher-loader-enable">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-loader-disable">
                                        Disable
                                    </label>
                                    <input class="form-check-input" type="radio" name="page-loader"
                                        id="switcher-loader-disable" checked>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade border-0" id="switcher-profile" role="tabpanel"
                    aria-labelledby="switcher-profile-tab" tabindex="0">
                    <div>
                        <div class="theme-colors">
                            <p class="switcher-style-head">Menu Colors:</p>
                            <div class="d-flex switcher-style pb-2">
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-white" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Light Menu" type="radio" name="menu-colors"
                                        id="switcher-menu-light">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-dark" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Dark Menu" type="radio" name="menu-colors"
                                        id="switcher-menu-dark" checked>
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-primary" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Color Menu" type="radio" name="menu-colors"
                                        id="switcher-menu-primary">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-gradient" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Gradient Menu" type="radio" name="menu-colors"
                                        id="switcher-menu-gradient">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-transparent"
                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Transparent Menu"
                                        type="radio" name="menu-colors" id="switcher-menu-transparent">
                                </div>
                            </div>
                            <div class="px-4 pb-3 text-muted fs-11">Note:If you want to change color Menu dynamically
                                change from below Theme Primary color picker</div>
                        </div>
                        <div class="theme-colors">
                            <p class="switcher-style-head">Header Colors:</p>
                            <div class="d-flex switcher-style pb-2">
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-white" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Light Header" type="radio" name="header-colors"
                                        id="switcher-header-light" checked>
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-dark" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Dark Header" type="radio" name="header-colors"
                                        id="switcher-header-dark">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-primary" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Color Header" type="radio" name="header-colors"
                                        id="switcher-header-primary">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-gradient" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Gradient Header" type="radio"
                                        name="header-colors" id="switcher-header-gradient">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-transparent"
                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Transparent Header"
                                        type="radio" name="header-colors" id="switcher-header-transparent">
                                </div>
                            </div>
                            <div class="px-4 pb-3 text-muted fs-11">Note:If you want to change color Header dynamically
                                change from below Theme Primary color picker</div>
                        </div>
                        <div class="theme-colors">
                            <p class="switcher-style-head">Theme Primary:</p>
                            <div class="d-flex flex-wrap align-items-center switcher-style">
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-primary-1" type="radio"
                                        name="theme-primary" id="switcher-primary">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-primary-2" type="radio"
                                        name="theme-primary" id="switcher-primary1">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-primary-3" type="radio"
                                        name="theme-primary" id="switcher-primary2">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-primary-4" type="radio"
                                        name="theme-primary" id="switcher-primary3">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-primary-5" type="radio"
                                        name="theme-primary" id="switcher-primary4">
                                </div>
                                <div class="form-check switch-select ps-0 mt-1 color-primary-light">
                                    <div class="theme-container-primary"></div>
                                    <div class="pickr-container-primary"></div>
                                </div>
                            </div>
                        </div>
                        <div class="theme-colors">
                            <p class="switcher-style-head">Theme Background:</p>
                            <div class="d-flex flex-wrap align-items-center switcher-style">
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-bg-1" type="radio"
                                        name="theme-background" id="switcher-background">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-bg-2" type="radio"
                                        name="theme-background" id="switcher-background1">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-bg-3" type="radio"
                                        name="theme-background" id="switcher-background2">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-bg-4" type="radio"
                                        name="theme-background" id="switcher-background3">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-bg-5" type="radio"
                                        name="theme-background" id="switcher-background4">
                                </div>
                                <div
                                    class="form-check switch-select ps-0 mt-1 tooltip-static-demo color-bg-transparent">
                                    <div class="theme-container-background"></div>
                                    <div class="pickr-container-background"></div>
                                </div>
                            </div>
                        </div>
                        <div class="menu-image mb-3">
                            <p class="switcher-style-head">Menu With Background Image:</p>
                            <div class="d-flex flex-wrap align-items-center switcher-style">
                                <div class="form-check switch-select m-2">
                                    <input class="form-check-input bgimage-input bg-img1" type="radio"
                                        name="theme-background" id="switcher-bg-img">
                                </div>
                                <div class="form-check switch-select m-2">
                                    <input class="form-check-input bgimage-input bg-img2" type="radio"
                                        name="theme-background" id="switcher-bg-img1">
                                </div>
                                <div class="form-check switch-select m-2">
                                    <input class="form-check-input bgimage-input bg-img3" type="radio"
                                        name="theme-background" id="switcher-bg-img2">
                                </div>
                                <div class="form-check switch-select m-2">
                                    <input class="form-check-input bgimage-input bg-img4" type="radio"
                                        name="theme-background" id="switcher-bg-img3">
                                </div>
                                <div class="form-check switch-select m-2">
                                    <input class="form-check-input bgimage-input bg-img5" type="radio"
                                        name="theme-background" id="switcher-bg-img4">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between canvas-footer flex-wrap">
                    <a href="https://themeforest.net/user/spruko/portfolio" class="btn btn-primary m-1">Buy Now</a>
                    <a href="https://themeforest.net/user/spruko/portfolio" class="btn btn-secondary m-1">Our
                        Portfolio</a>
                    <a href="javascript:void(0);" id="reset-all" class="btn btn-danger m-1">Reset</a>
                </div>
            </div>
        </div>
    </div>
    <!-- End Switcher -->
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
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
                    <div class="d-flex flex-column gap-2">
                        <a href="<?php echo !empty($editing_product_id) ? './first-edit-product.php?id=' . $editing_product_id : './first-add-product.php'; ?>"
                            class="btn btn-outline-primary me-3" id="backToFirstStep"
                            style="padding: 5px;width: fit-content;">
                            <i class="ri-arrow-left-line me-1"></i>Back to Product Details
                        </a>
                        <h1 class="page-title fw-semibold fs-18 mb-0">
                            <?php echo !empty($editing_product_id) ? 'Edit Product Specifications' : 'Add Specification'; ?>
                        </h1>
                    </div>
                    <div>
                        <!-- <div class="input-group">
                            <div class="input-group-text text-muted"> <i class="ri-calendar-line"></i> </div> <input
                                type="text" class="form-control flatpickr-input" id="daterange"
                                placeholder="Date range picker" readonly="readonly">
                        </div> -->
                    </div>
                </div>
                <!-- Page Header Close -->
                <!-- Page Header -->
                <!-- Page Header Close -->
                <!-- Start:: row-2 -->
                <div class="row" id="spec-section-1">
                    <div class="col-12 col-xl-7 col-lg-12 col-md-12 col-sm-12">
                        <?php if (!empty($success_msg)): ?>
                            <div class="alert alert-success alert-dismissible fade show" id="auto-hide-success" role="alert">
                                <?php echo htmlspecialchars($success_msg); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($error_msg)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" id="auto-hide-error" role="alert">
                                <?php echo htmlspecialchars($error_msg); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <div class="card custom-card p-4" style="position: relative;">
                            <div id="spec-loading-overlay"
                                style="position:absolute; inset:0; display:none; align-items:center; justify-content:center; background:rgba(255,255,255,0.92); z-index:25;">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <div class="mt-2 fw-semibold text-primary">Loading specifications…</div>
                                </div>
                            </div>
                            <!-- <div class="card-header justify-content-between">
                                <div class="card-title1">
                                    Order Request
                                </div>
                            </div> -->
                            
                            <!-- Skeleton loader for specifications -->
                            <div id="spec-skeleton" class="spec-skeleton mb-3" style="display: none;">
                                <div class="placeholder-glow">
                                    <div class="row g-3 mb-3">
                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12 mb-1">
                                            <span class="placeholder col-4"></span>
                                            <div class="placeholder col-12 mt-2" style="height: 40px;"></div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12 mb-1">
                                            <span class="placeholder col-4"></span>
                                            <div class="placeholder col-12 mt-2" style="height: 40px;"></div>
                                        </div>
                                    </div>
                                    <div class="d-flex px-3 gap-2 mb-3">
                                        <span class="placeholder col-3"></span>
                                        <span class="placeholder col-2"></span>
                                        <span class="placeholder col-2"></span>
                                    </div>
                                </div>
                                <div class="placeholder-glow">
                                    <div class="row g-3 mb-3">
                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12 mb-1">
                                            <span class="placeholder col-4"></span>
                                            <div class="placeholder col-12 mt-2" style="height: 40px;"></div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12 mb-1">
                                            <span class="placeholder col-4"></span>
                                            <div class="placeholder col-12 mt-2" style="height: 40px;"></div>
                                        </div>
                                    </div>
                                    <div class="d-flex px-3 gap-2 mb-3">
                                        <span class="placeholder col-3"></span>
                                        <span class="placeholder col-2"></span>
                                        <span class="placeholder col-2"></span>
                                    </div>
                                </div>
                            </div>
                            <div id="add-more-item" class="spec-block" <?php if (!empty($editing_product_id)): ?>
                                    style="display: none;" <?php endif; ?>>
                                <div class="row g-3 mb-3">
                                    <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12 mb-1">
                                        <label class="form-label">Attribute<span class="text-danger">*</span></label>
                                        <select id="Default-sorting" class="form-select form-select-lg"
                                            id="attribute-select-1">
                                            <option>select attribute</option>
                                            <?php
                                            // Get optional attributes from sub_category table
                                            $optional_attribute_ids = [];
                                            $selected_subcategory_id_for_optional = '';
                                            
                                            // Determine subcategory ID for optional attributes
                                            if (!empty($editing_product_id) && !empty($existing_product)) {
                                                $selected_subcategory_id_for_optional = $existing_product['sub_category_id'] ?? '';
                                            } elseif (!empty($edit_sub_category_id)) {
                                                $selected_subcategory_id_for_optional = $edit_sub_category_id;
                                            } elseif (!empty($pending_payload['sub_category_id'])) {
                                                $selected_subcategory_id_for_optional = $pending_payload['sub_category_id'];
                                            } elseif (isset($_SESSION['selected_subcategory_for_mandatory'])) {
                                                $selected_subcategory_id_for_optional = $_SESSION['selected_subcategory_for_mandatory'];
                                            }
                                            
                                            // Query sub_category table for optional_attributes
                                            if (!empty($selected_subcategory_id_for_optional)) {
                                                $optional_q = mysqli_query($con, "SELECT optional_attributes FROM sub_category WHERE id='" . mysqli_real_escape_string($con, $selected_subcategory_id_for_optional) . "' LIMIT 1");
                                                if ($optional_q && mysqli_num_rows($optional_q) > 0) {
                                                    $optional_row = mysqli_fetch_assoc($optional_q);
                                                    $optional_csv = trim((string) ($optional_row['optional_attributes'] ?? ''));
                                                    if ($optional_csv !== '' && $optional_csv !== 'null') {
                                                        $optional_attribute_ids = array_filter(array_map(function ($v) {
                                                            return (int) trim($v); 
                                                        }, explode(',', $optional_csv)));
                                                    }
                                                }
                                            }
                                            
                                            // Get mandatory attributes to exclude from dropdown (for safety)
                                            $mandatory_ids = [];
                                            if (!empty($selected_category_id)) {
                                                // Check if we have a subcategory selected first
                                                $selected_subcategory_id = $existing_product['sub_category_id'] ?? '';
                                                if (!empty($selected_subcategory_id)) {
                                                    // Query sub_category table for mandatory attributes
                                                    $cat_q = mysqli_query($con, "SELECT mandatory_attributes FROM sub_category WHERE id='" . mysqli_real_escape_string($con, $selected_subcategory_id) . "' LIMIT 1");
                                                } else {
                                                    // Fallback to category-based mandatory attributes (if any subcategory under this category has them)
                                                    $cat_q = mysqli_query($con, "SELECT mandatory_attributes FROM sub_category WHERE category_id='" . mysqli_real_escape_string($con, $selected_category_id) . "' AND mandatory_attributes IS NOT NULL AND mandatory_attributes <> '' ORDER BY id LIMIT 1");
                                                }
                                                if ($cat_q && mysqli_num_rows($cat_q) > 0) {
                                                    $cat_row = mysqli_fetch_assoc($cat_q);
                                                    $mand_csv = trim((string) ($cat_row['mandatory_attributes'] ?? ''));
                                                    if ($mand_csv !== '') {
                                                        $mandatory_ids = array_filter(array_map(function ($v) {
                                                            return (int) trim($v); }, explode(',', $mand_csv)));
                                                    }
                                                }
                                            }
                                            
                                            // Only show attributes that are in optional_attributes list
                                            if (!empty($optional_attribute_ids)) {
                                                $ids_list = implode(',', array_map('intval', $optional_attribute_ids));
                                                $attributes = mysqli_query($con, "SELECT * FROM attributes WHERE id IN ($ids_list) ORDER BY name");
                                                while ($attribute = mysqli_fetch_assoc($attributes)) {
                                                    // Skip mandatory attributes from dropdown (safety check)
                                                    if (!in_array((int) $attribute['id'], $mandatory_ids)) {
                                                        echo "<option value='" . $attribute['id'] . "'>" . $attribute['name'] . "</option>";
                                                    }
                                                }
                                            } else {
                                                // If no optional attributes found, show empty dropdown with a message
                                                echo "<option value=''>No optional attributes available</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6 col-12 mb-1">
                                        <label class="form-label ">Variations<span class="text-danger">*</span></label>
                                        <select class="form-control" name="signin-Products" id="product-s" multiple
                                            id="variants-select-1">
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label mb-1" id="price-change-label-1">Price change based on this
                                        attribute ?</label>
                                    <div class=" d-flex px-3 gap-2">
                                        <div class="form-check me-2 px-0">
                                            <input class="form-check-input" type="radio" name="flexRadioDefault"
                                                id="flexRadioDefault1">
                                            <label class="form-check-label" for="flexRadioDefault1"> Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="flexRadioDefault"
                                                id="flexRadioDefault2" checked="" id="price-change-radio-2">
                                            <label class="form-check-label" for="flexRadioDefault2"> No </label>
                                        </div>
                                    </div>
                                </div>
                                <!-- Dynamic price rows will render below based on selected variants when Yes is chosen -->
                                <div class="variant-price-rows mt-2"></div>
                            </div>
                            <hr class="mt-0" style="border-top: 1px dashed #d1d5db; margin: 32px 0;">
                            <button type="button" class="btn btn-light btn-wave waves-effect waves-light mb-2"
                                style="color: #3B4B6B; width: fit-content;">Add More +</button>
                            <form method="POST" onsubmit="clearSessionCategory(); return attachSpecsAndSubmit(this);" data-localstorage-key="<?php 
                                // Determine the localStorage key to clear
                                if (!empty($editing_product_id)) {
                                    echo 'product_specs_' . htmlspecialchars($editing_product_id);
                                } else {
                                    $product_name = isset($pending_payload['product_name']) ? $pending_payload['product_name'] : '';
                                    if (!empty($product_name)) {
                                        $product_name_key = strtolower(preg_replace('/[^a-z0-9]/', '_', $product_name));
                                        echo 'product_specs_new_' . htmlspecialchars($product_name_key);
                                    }
                                }
                            ?>">
                                <input type="hidden" name="final_submit" value="1">
                                <input type="hidden" name="product_id"
                                    value="<?php echo htmlspecialchars($editing_product_id ?? ''); ?>">
                                <input type="hidden" name="specifications_json" id="specifications_json" value="[]">
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-light btn-wave waves-effect waves-light col-2"
                                        style="color: white; background-color: #3B4B6B; width: fit-content;">
                                        <?php echo !empty($editing_product_id) ? 'Update Product' : 'Submit Product'; ?>
                                    </button>
                                </div>
                            </form>
                            <!-- <div class="d-flex">
                            <div></div>
                            <select class="form-control" name="signin-Products" id="product-MRP" multiple>
                            <option value="S" selected>S</option>
                            <option value="M" >M</option>
                            <option value="L" >L</option>
                            <option value="XL" >XL</option>
                            <option value="XXL">XXL</option>
                            </select>
                            <div class="flex-grow-1">
                               <label class="form-label mb-1">SKU<span class="text-danger">*</span></label>
                               <input type="text" class="form-control" placeholder="Enter SKU">
                           </div>
                            </div> -->
                        </div>
                    </div>
                    <div class="col-12 col-xl-5 col-lg-12 col-md-12 col-sm-12">
                        <div class="card custom-card p-4">
                            <h5 class="fw-bold mb-4">Preview</h5>
                            <div id="spec-preview"></div>
                            <!-- Skeleton for preview -->
                            <div id="preview-skeleton" style="display: none;">
                                <div class="placeholder-glow">
                                    <div class="mb-3">
                                        <span class="placeholder col-3"></span>
                                        <div class="d-flex gap-2 mt-2">
                                            <span class="placeholder col-2"></span>
                                            <span class="placeholder col-2"></span>
                                            <span class="placeholder col-2"></span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <span class="placeholder col-4"></span>
                                        <div class="d-flex gap-2 mt-2">
                                            <span class="placeholder col-2"></span>
                                            <span class="placeholder col-2"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End:: row-2 -->
                    </div>
                </div>
                <!-- End::app-content -->
                <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModal" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-body">
                                <div class="input-group">
                                    <a href="javascript:void(0);" class="input-group-text" id="Search-Grid"><i
                                            class="fe fe-search header-link-icon fs-18"></i></a>
                                    <input type="search" class="form-control border-0 px-2" placeholder="Search"
                                        aria-label="Username">
                                    <a href="javascript:void(0);" class="input-group-text" id="voice-search"><i
                                            class="fe fe-mic header-link-icon"></i></a>
                                    <a href="javascript:void(0);" class="btn btn-light btn-icon"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fe fe-more-vertical"></i>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="javascript:void(0);">Action</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">Another action</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">Something else here</a>
                                        </li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">Separated link</a></li>
                                    </ul>
                                </div>
                                <div class="mt-4">
                                    <p class="font-weight-semibold text-muted mb-2">Are You Looking For...</p>
                                    <span class="search-tags"><i class="fe fe-user me-2"></i>People<a
                                            href="javascript:void(0)" class="tag-addon"><i
                                                class="fe fe-x"></i></a></span>
                                    <span class="search-tags"><i class="fe fe-file-text me-2"></i>Pages<a
                                            href="javascript:void(0)" class="tag-addon"><i
                                                class="fe fe-x"></i></a></span>
                                    <span class="search-tags"><i class="fe fe-align-left me-2"></i>Articles<a
                                            href="javascript:void(0)" class="tag-addon"><i
                                                class="fe fe-x"></i></a></span>
                                    <span class="search-tags"><i class="fe fe-server me-2"></i>Tags<a
                                            href="javascript:void(0)" class="tag-addon"><i
                                                class="fe fe-x"></i></a></span>
                                </div>
                                <div class="my-4">
                                    <p class="font-weight-semibold text-muted mb-2">Recent Search :</p>
                                    <div class="p-2 border br-5 d-flex align-items-center text-muted mb-2 alert">
                                        <a href="notifications.php"><span>Notifications</span></a>
                                        <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                            aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                                    </div>
                                    <div class="p-2 border br-5 d-flex align-items-center text-muted mb-2 alert">
                                        <a href="alerts.php"><span>Alerts</span></a>
                                        <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                            aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                                    </div>
                                    <div class="p-2 border br-5 d-flex align-items-center text-muted mb-0 alert">
                                        <a href="mail.php"><span>Mail</span></a>
                                        <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                            aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="btn-group ms-auto">
                                    <button class="btn btn-sm btn-primary-light">Search</button>
                                    <button class="btn btn-sm btn-primary">Clear Recents</button>
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
                            Designed with <span class="bi bi-heart-fill text-danger"></span> by <a
                                href="javascript:void(0);">
                                <span class="fw-semibold text-sky-blue text-decoration-underline">Mechodal Technology
                                </span>
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
            <!-- Date & Time Picker JS -->
            <script src="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.js"></script>
            <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/date&time_pickers.js"></script>
            <!-- Custom JS -->
            <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom.js"></script>
            <script>
                // Auto-hide success/error messages after 5 seconds
                document.addEventListener('DOMContentLoaded', function() {
                    const successAlert = document.getElementById('auto-hide-success');
                    const errorAlert = document.getElementById('auto-hide-error');
                    
                    if (successAlert) {
                        setTimeout(function() {
                            const alert = bootstrap.Alert.getOrCreateInstance(successAlert);
                            alert.close();
                        }, 5000);
                    }
                    
                    if (errorAlert) {
                        setTimeout(function() {
                            const alert = bootstrap.Alert.getOrCreateInstance(errorAlert);
                            alert.close();
                        }, 5000);
                    }
                });
            </script>
            <script>
                // === LOADING STATE MANAGEMENT ===
                let specLoadingOverlay = null;
                let submitBtnEl = null;
                let addMoreBtnEl = null;
                let loadingInitialised = false;
                
                function setLoadingState(isLoading) {
                    if (!loadingInitialised) {
                        specLoadingOverlay = document.getElementById('spec-loading-overlay');
                        submitBtnEl = document.querySelector('form[method="POST"] button[type="submit"]');
                        addMoreBtnEl = document.querySelector('button.btn-light.btn-wave.mb-2');
                        if (submitBtnEl && !submitBtnEl.dataset.originalText) {
                            submitBtnEl.dataset.originalText = submitBtnEl.textContent.trim();
                        }
                        loadingInitialised = true;
                    }
                    if (specLoadingOverlay) {
                        specLoadingOverlay.style.display = isLoading ? 'flex' : 'none';
                    }
                    if (submitBtnEl) {
                        submitBtnEl.disabled = !!isLoading;
                        if (isLoading) {
                            submitBtnEl.textContent = 'Loading…';
                        } else if (submitBtnEl.dataset.originalText) {
                            submitBtnEl.textContent = submitBtnEl.dataset.originalText;
                        }
                    }
                    if (addMoreBtnEl) {
                        addMoreBtnEl.disabled = !!isLoading;
                    }
                    // Show/hide preview skeleton
                    const previewSkeleton = document.getElementById('preview-skeleton');
                    const previewContent = document.getElementById('spec-preview');
                    if (isLoading) {
                        if (previewSkeleton) {
                            previewSkeleton.style.display = 'block';
                        }
                        if (previewContent) {
                            previewContent.style.display = 'none';
                        }
                    } else {
                        if (previewSkeleton) {
                            previewSkeleton.style.display = 'none';
                        }
                        if (previewContent) {
                            previewContent.style.display = 'block';
                        }
                    }
                }
                
                // Function to check if all sections are fully loaded
                async function checkAllSectionsLoaded() {
                    // Wait a bit for any pending operations
                    await new Promise(resolve => setTimeout(resolve, 500));
                    
                    // Check if mandatory sections are still being created
                    if (CREATING_MANDATORY_SECTIONS) {
                        console.log('Mandatory sections still being created, waiting...');
                        return false;
                    }
                    
                    // Check if we need to wait for mandatory sections
                    const categoryId = '<?php echo htmlspecialchars($edit_category_id ?? ''); ?>';
                    const subcategoryId = '<?php echo htmlspecialchars($edit_sub_category_id ?? ''); ?>';
                    const storedSubcategory = '<?php echo htmlspecialchars(isset($_SESSION['selected_subcategory_for_mandatory']) ? $_SESSION['selected_subcategory_for_mandatory'] : ''); ?>';
                    const finalSubcategoryId = subcategoryId || storedSubcategory;
                    
                    if ((categoryId || finalSubcategoryId) && MANDATORY_ATTRIBUTES.length > 0 && !MANDATORY_SECTIONS_CREATED) {
                        console.log('Mandatory sections need to be created, waiting...');
                        return false;
                    }
                    
                    // Check if all mandatory sections exist
                    if (MANDATORY_ATTRIBUTES.length > 0) {
                        const existingMandatorySections = new Set();
                        document.querySelectorAll('.spec-section.mandatory-section').forEach(section => {
                            const attrSel = getAttrSelect(section);
                            if (attrSel && attrSel.value) {
                                existingMandatorySections.add(parseInt(attrSel.value, 10));
                            }
                        });
                        
                        const missingMandatory = MANDATORY_ATTRIBUTES.filter(id => !existingMandatorySections.has(parseInt(id, 10)));
                        if (missingMandatory.length > 0) {
                            console.log('Some mandatory sections are missing:', missingMandatory);
                            return false;
                        }
                    }
                    
                    // All sections are loaded
                    console.log('All sections are fully loaded');
                    return true;
                }
                
                function markSpecsInitialized() {
                    if (window.__specInitializationDone) {
                        return;
                    }
                    
                    // Check if all sections are loaded before stopping loader
                    checkAllSectionsLoaded().then(allLoaded => {
                        if (allLoaded || ALL_SECTIONS_LOADED) {
                            window.__specInitializationDone = true;
                            ALL_SECTIONS_LOADED = true;
                            setLoadingState(false);
                            // Show preview and hide skeleton
                            const previewSkeleton = document.getElementById('preview-skeleton');
                            const previewContent = document.getElementById('spec-preview');
                            if (previewSkeleton) {
                                previewSkeleton.style.display = 'none';
                            }
                            if (previewContent) {
                                previewContent.style.display = 'block';
                                // Render preview if not already rendered
                                if (typeof renderPreview === 'function') {
                                    renderPreview();
                                }
                            }
                            console.log('Loading stopped - all sections are ready');
                        } else {
                            // Not all sections loaded yet, check again after a delay
                            console.log('Not all sections loaded yet, will check again...');
                            setTimeout(() => {
                                if (!window.__specInitializationDone) {
                                    markSpecsInitialized();
                                }
                            }, 1000);
                        }
                    });
                }
                
                // Function to show optional attribute loader spanning entire card
                function showOptionalAttributeLoader() {
                    // Remove existing optional loader if any
                    const existingLoader = document.getElementById('optional-attribute-loader');
                    if (existingLoader) {
                        existingLoader.remove();
                    }
                    
                    // Find the card container with class "card custom-card p-4"
                    const cardContainer = document.querySelector('.card.custom-card.p-4');
                    if (!cardContainer) {
                        console.warn('Card container not found for optional attribute loader');
                        return;
                    }
                    
                    // Ensure card has relative positioning
                    if (getComputedStyle(cardContainer).position === 'static') {
                        cardContainer.style.position = 'relative';
                    }
                    
                    // Create loader overlay covering the entire card
                    const loader = document.createElement('div');
                    loader.id = 'optional-attribute-loader';
                    loader.style.cssText = `
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(255, 255, 255, 0.95);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 30;
                        border-radius: 8px;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    `;
                    loader.innerHTML = `
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-3 fw-semibold text-primary">Loading optional attributes...</div>
                        </div>
                    `;
                    
                    // Append loader to card container
                    cardContainer.appendChild(loader);
                    
                    // Get all optional sections for logging
                    const allSections = Array.from(document.querySelectorAll('.spec-section'));
                    const optionalSections = allSections.filter(section => !section.classList.contains('mandatory-section'));
                    console.log('Optional attribute loader shown for', optionalSections.length, 'sections');
                }
                
                // Function to hide optional attribute loader
                function hideOptionalAttributeLoader() {
                    const loader = document.getElementById('optional-attribute-loader');
                    if (loader) {
                        loader.remove();
                        console.log('Optional attribute loader hidden');
                    }
                }
                
                // Function to update optional attribute loader (ensures it exists and covers entire card)
                function updateOptionalAttributeLoader() {
                    const loader = document.getElementById('optional-attribute-loader');
                    
                    // If loader doesn't exist yet, create it
                    if (!loader) {
                        showOptionalAttributeLoader();
                        return;
                    }
                    
                    // Loader already exists and covers entire card, no position update needed
                    // The loader is already set to cover the entire card (top: 0, left: 0, width: 100%, height: 100%)
                }
                
                // === LOCALSTORAGE MANAGEMENT ===
                // Get the storage key based on edit mode or new product
                function getLocalStorageKey() {
                    <?php if (!empty($editing_product_id)): ?>
                        return 'product_specs_<?php echo htmlspecialchars($editing_product_id); ?>';
                    <?php else: ?>
                        // For new products, use product name from pending payload or session
                        let productName = '<?php 
                            if (isset($pending_payload['product_name']) && !empty($pending_payload['product_name'])) {
                                echo htmlspecialchars($pending_payload['product_name']);
                            } elseif (isset($_SESSION['pending_product_payload']) && !empty($_SESSION['pending_product_payload'])) {
                                $decoded = json_decode($_SESSION['pending_product_payload'], true);
                                echo isset($decoded['product_name']) && !empty($decoded['product_name']) ? htmlspecialchars($decoded['product_name']) : 'new_product';
                            } else {
                                echo 'new_product';
                            }
                        ?>';
                        // Create a safe key from product name
                        const safeKey = productName.trim() !== '' ? productName.toLowerCase().replace(/[^a-z0-9]/g, '_') : 'new_product';
                        // Use a consistent key for new products (without timestamp)
                        return 'product_specs_new_' + safeKey;
                    <?php endif; ?>
                }
                
                // Save specifications to localStorage
                function saveSpecsToLocalStorage() {
                    try {
                        const specs = collectSpecifications();
                        const key = getLocalStorageKey();
                        const dataToStore = {
                            specifications: specs,
                            timestamp: Date.now(),
                            productId: '<?php echo htmlspecialchars($editing_product_id ?? ''); ?>',
                            productName: '<?php echo isset($pending_payload['product_name']) ? htmlspecialchars($pending_payload['product_name']) : ''; ?>'
                        };
                        localStorage.setItem(key, JSON.stringify(dataToStore));
                        console.log('Specifications saved to localStorage with key:', key);
                    } catch (e) {
                        console.error('Error saving to localStorage:', e);
                    }
                }
                
                // Load specifications from localStorage
                function loadSpecsFromLocalStorage() {
                    try {
                        const key = getLocalStorageKey();
                        const stored = localStorage.getItem(key);
                        if (stored) {
                            const data = JSON.parse(stored);
                            if (data && data.specifications && Array.isArray(data.specifications)) {
                                console.log('Found specifications in localStorage:', data.specifications);
                                return data.specifications;
                            }
                        }
                    } catch (e) {
                        console.error('Error loading from localStorage:', e);
                    }
                    return null;
                }
                
                // Clear specifications from localStorage
                function clearSpecsFromLocalStorage() {
                    try {
                        const key = getLocalStorageKey();
                        localStorage.removeItem(key);
                        console.log('Cleared specifications from localStorage with key:', key);
                        // Also clear any old new product entries (cleanup)
                        if (!'<?php echo !empty($editing_product_id) ? 'true' : 'false'; ?>') {
                            const keys = Object.keys(localStorage);
                            keys.forEach(k => {
                                if (k.startsWith('product_specs_new_')) {
                                    localStorage.removeItem(k);
                                }
                            });
                        }
                    } catch (e) {
                        console.error('Error clearing localStorage:', e);
                    }
                }
                
                // Debounce function to prevent too frequent saves
                let saveTimeout = null;
                function debouncedSaveToLocalStorage() {
                    if (saveTimeout) {
                        clearTimeout(saveTimeout);
                    }
                    saveTimeout = setTimeout(() => {
                        saveSpecsToLocalStorage();
                    }, 500); // Save 500ms after last change
                }
                
                // Load and apply specifications from localStorage
                async function loadAndApplySpecsFromLocalStorage() {
                    if (LOCALSTORAGE_LOADED) {
                        return; // Already loaded
                    }
                    try {
                        const storedSpecs = loadSpecsFromLocalStorage();
                        if (!storedSpecs || !Array.isArray(storedSpecs) || storedSpecs.length === 0) {
                            console.log('No specifications found in localStorage');
                            return;
                        }
                        console.log('Loading specifications from localStorage:', storedSpecs);
                        LOCALSTORAGE_LOADED = true;
                        
                        // Store the specs globally for later use
                        window.localStorageSpecs = storedSpecs;
                        
                        // Initialize existingPriceData from localStorage to preserve prices
                        if (!window.existingPriceData) {
                            window.existingPriceData = {};
                        }
                        storedSpecs.forEach(spec => {
                            if (spec.variants && Array.isArray(spec.variants)) {
                                spec.variants.forEach(variant => {
                                    if (variant.id && (variant.mrp || variant.selling_price)) {
                                        window.existingPriceData[variant.id] = {
                                            mrp: variant.mrp || null,
                                            selling_price: variant.selling_price || null
                                        };
                                    }
                                });
                            }
                        });
                        console.log('Initialized existingPriceData from localStorage:', window.existingPriceData);
                        
                        // Mark that we should use localStorage data instead of session/database
                        window.useLocalStorageData = true;
                        
                        return storedSpecs;
                    } catch (e) {
                        console.error('Error loading specifications from localStorage:', e);
                        return null;
                    }
                }
                
                // === SPEC BUILDER CORE ===
                // Robust, section-scoped logic for Attribute -> Variations -> Per-variant prices
                let SPEC_SECTION_UID = 0;
                let MANDATORY_ATTRIBUTES = <?php echo json_encode($preloaded_mandatory_ids ?? []); ?>; // Preloaded mandatory IDs for the current subcategory
                let OPTIONAL_ATTRIBUTES = <?php echo json_encode($preloaded_optional_ids ?? []); ?>; // Preloaded optional IDs for the current subcategory
                let MANDATORY_SECTIONS_CREATED = false; // Track if mandatory sections are created
                let CREATING_MANDATORY_SECTIONS = false; // Prevent multiple simultaneous creation calls
                let LOCALSTORAGE_LOADED = false; // Track if localStorage data has been loaded
                let MANDATORY_SECTIONS_PROMISE = null; // Track promise for mandatory sections creation
                let ALL_SECTIONS_LOADED = false; // Track if all sections are fully loaded
                function getAttrSelect(section) {
                    // Locate the attribute select by label context (avoid relying on duplicate ids)
                    let select = null;
                    const attrCol = Array.from(section.querySelectorAll('label'))
                        .find(l => (l.textContent || '').toLowerCase().includes('attribute'));
                    if (attrCol) {
                        const wrap = attrCol.closest('.col-12, .col-xl-6, .col-lg-6, .col-md-6, .col-sm-6, .col-12.mb-1');
                        select = wrap ? wrap.querySelector('select') : null;
                    }
                    // Fallback to first select if not found
                    return select || section.querySelector('select');
                }
                function getVariantsSelect(section) {
                    // Check if this is a text attribute - return text variants container instead
                    const attributeType = section.dataset.attributeType || 'multi';
                    if (attributeType === 'text') {
                        // Return the text variants container for text attributes
                        return section.querySelector('.text-variants-container') || null;
                    }
                    // Prefer the MULTI select in this section
                    let sel = section.querySelector('select[multiple]');
                    if (!sel) {
                        // fallback by label text
                        const varCol = Array.from(section.querySelectorAll('label'))
                            .find(l => (l.textContent || '').toLowerCase().includes('variation'));
                        if (varCol) {
                            const wrap = varCol.closest('.col-12, .col-xl-6, .col-lg-6, .col-md-6, .col-sm-6, .col-12.mb-1');
                            sel = wrap ? wrap.querySelector('select') : null;
                        }
                    }
                    // Enhance with Choices once per element
                    if (sel && window.Choices && !sel._choices) {
                        try {
                            sel._choices = new Choices(sel, { removeItemButton: true, searchEnabled: true, allowHTML: true });
                        } catch (e) { }
                    }
                    return sel || null;
                }
                // Generate unique ID for text variants
                function generateTextVariantId(attributeId) {
                    return `text_${attributeId}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
                }
                // Get text variants from a section
                function getTextVariants(section) {
                    const container = section.querySelector('.text-variants-container');
                    if (!container) return [];
                    const variants = [];
                    container.querySelectorAll('.text-variant-tag').forEach(tag => {
                        const variantId = tag.dataset.variantId;
                        const variantName = tag.dataset.variantName || tag.textContent.trim().replace(/\s*×\s*$/, '');
                        if (variantId && variantName) {
                            variants.push({
                                id: variantId,
                                name: variantName,
                                is_text_variant: true
                            });
                        }
                    });
                    return variants;
                }
                // Create text variant UI
                function createTextVariantTag(variantId, variantName, section) {
                    const tag = document.createElement('span');
                    tag.className = 'badge  text-variant-tag me-2 mb-2';
                    tag.style.cssText = 'font-size: 14px; padding: 6px 12px; cursor: pointer; display: inline-block; background-color: #3B4B6B !important;';
                    tag.dataset.variantId = variantId;
                    tag.dataset.variantName = variantName;
                    tag.innerHTML = `${variantName} <span class="ms-2" style="cursor: pointer;">×</span>`;
                    
                    // Add click handler to remove variant
                    tag.addEventListener('click', function(e) {
                        if (e.target.tagName === 'SPAN' || e.target.textContent === '×') {
                            e.stopPropagation();
                            removeTextVariant(section, variantId);
                        }
                    });
                    
                    return tag;
                }
                // Remove text variant
                function removeTextVariant(section, variantId) {
                    const container = section.querySelector('.text-variants-container');
                    if (!container) return;
                    
                    const tag = container.querySelector(`.text-variant-tag[data-variant-id="${variantId}"]`);
                    if (tag) {
                        tag.remove();
                        
                        // Update price rows if price change is Yes
                        const radios = getYesNoRadios(section);
                        if (radios && radios.yes && radios.yes.checked) {
                            renderPriceRows(section);
                        }
                        
                        // Save to localStorage
                        debouncedSaveToLocalStorage();
                        // Update preview
                        renderPreview();
                    }
                }
                // Add text variant
                function addTextVariant(section, variantName, variantId = null) {
                    if (!variantName || variantName.trim() === '') {
                        return false;
                    }
                    
                    variantName = variantName.trim();
                    
                    // Limit to 30 characters
                    if (variantName.length > 30) {
                        variantName = variantName.substring(0, 30);
                        if (!variantId) {
                            alert('Text variant is limited to 30 characters.');
                        }
                    }
                    
                    const attrSelect = getAttrSelect(section);
                    if (!attrSelect || !attrSelect.value) return false;
                    
                    const attributeId = parseInt(attrSelect.value, 10);
                    if (!attributeId) return false;
                    
                    // For text type, only one variant is allowed - remove existing ones
                    const existingVariants = getTextVariants(section);
                    if (existingVariants.length > 0) {
                        // Remove all existing variants (only one allowed)
                        existingVariants.forEach(v => {
                            removeTextVariant(section, v.id);
                        });
                    }
                    
                    // Use provided ID or generate new one
                    if (!variantId) {
                        variantId = generateTextVariantId(attributeId);
                    }
                    
                    // Get or create text variants container
                    let container = section.querySelector('.text-variants-container');
                    if (!container) {
                        // Find the variations column
                        const varLabel = Array.from(section.querySelectorAll('label'))
                            .find(l => (l.textContent || '').toLowerCase().includes('variation'));
                        const varWrap = varLabel ? varLabel.closest('.col-12, .col-xl-6, .col-lg-6, .col-md-6, .col-sm-6, .col-12.mb-1') : null;
                        if (varWrap) {
                            // Hide the select if it exists
                            const select = varWrap.querySelector('select');
                            if (select) {
                                select.style.display = 'none';
                            }
                            
                            // Create container
                            container = document.createElement('div');
                            container.className = 'text-variants-container mt-2';
                            container.style.cssText = 'min-height: 40px; padding: 8px; border: 1px solid #dee2e6; border-radius: 4px;';
                            
                            // Create text input
                            const textInput = document.createElement('input');
                            textInput.type = 'text';
                            textInput.className = 'form-control mb-2 text-variant-input';
                            textInput.placeholder = 'Type variant name (max 30 characters)';
                            textInput.maxLength = 30;
                            textInput.style.cssText = 'width: 100%;';
                            
                            // Auto-add on blur (when user leaves the input)
                            textInput.addEventListener('blur', function() {
                                const value = this.value.trim();
                                if (value) {
                                    addTextVariant(section, value);
                                    this.value = '';
                                }
                            });
                            
                            // Also allow Enter key for convenience
                            textInput.addEventListener('keydown', function(e) {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    this.blur(); // Trigger blur to add variant
                                }
                            });
                            
                            container.appendChild(textInput);
                            varWrap.appendChild(container);
                        } else {
                            return false;
                        }
                    }
                    
                    // Create and add tag
                    const tag = createTextVariantTag(variantId, variantName, section);
                    const textInput = container.querySelector('.text-variant-input');
                    if (textInput && textInput.parentNode === container) {
                        container.insertBefore(tag, textInput);
                    } else {
                        // If no text input found, append to container
                        container.appendChild(tag);
                        // Ensure text input exists
                        if (!textInput) {
                            const newTextInput = document.createElement('input');
                            newTextInput.type = 'text';
                            newTextInput.className = 'form-control mb-2 text-variant-input';
                            newTextInput.placeholder = 'Type variant name (max 30 characters)';
                            newTextInput.maxLength = 30;
                            newTextInput.style.cssText = 'width: 100%;';
                            
                            // Auto-add on blur (when user leaves the input)
                            newTextInput.addEventListener('blur', function() {
                                const value = this.value.trim();
                                if (value) {
                                    addTextVariant(section, value);
                                    this.value = '';
                                }
                            });
                            
                            // Also allow Enter key for convenience
                            newTextInput.addEventListener('keydown', function(e) {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    this.blur(); // Trigger blur to add variant
                                }
                            });
                            container.appendChild(newTextInput);
                        }
                    }
                    
                    // Update price rows if price change is Yes
                    const radios = getYesNoRadios(section);
                    if (radios && radios.yes && radios.yes.checked) {
                        renderPriceRows(section);
                    }
                    
                    // Save to localStorage
                    debouncedSaveToLocalStorage();
                    // Update preview
                    renderPreview();
                    
                    return true;
                }
                function getYesNoRadios(section) {
                    const radios = Array.from(section.querySelectorAll('input[type="radio"]'));
                    ////console.log(`Found ${radios.length} radio buttons in section:`, radios);
                    ////console.log('Section UID:', section.dataset.specUid);
                    // Find Yes/No radios by looking at their labels first
                    let yesRadio = null, noRadio = null;
                    radios.forEach((radio) => {
                        const label = section.querySelector(`label[for="${radio.id}"]`);
                        const labelText = label ? label.textContent.toLowerCase().trim() : '';
                        ////console.log(`Radio ${radio.id}: label="${labelText}", checked=${radio.checked}`);
                        if (labelText.includes('yes')) {
                            yesRadio = radio;
                        } else if (labelText.includes('no')) {
                            noRadio = radio;
                        }
                    });
                    // If not found by labels, try by ID pattern (price-change-yes-uid or price-change-no-uid)
                    if (!yesRadio && !noRadio && radios.length >= 2) {
                        radios.forEach((radio) => {
                            if (radio.id.includes('price-change-yes-')) {
                                yesRadio = radio;
                            } else if (radio.id.includes('price-change-no-')) {
                                noRadio = radio;
                            }
                        });
                        ////console.log('Using ID pattern-based radio selection');
                    }
                    // If still not found, try by position (first = yes, second = no)
                    if (!yesRadio && !noRadio && radios.length >= 2) {
                        yesRadio = radios[0];
                        noRadio = radios[1];
                        ////console.log('Using position-based radio selection');
                    }
                    ////console.log('Final radio selection:', {yes: yesRadio, no: noRadio, yesChecked: yesRadio?.checked, noChecked: noRadio?.checked});
                    return { yes: yesRadio, no: noRadio };
                }
                function ensurePriceRowsContainer(section) {
                    let cont = section.querySelector('.variant-price-rows');
                    if (!cont) {
                        cont = document.createElement('div');
                        cont.className = 'variant-price-rows mt-2';
                        section.appendChild(cont);
                    }
                    return cont;
                }
                async function populateVariants(selectEl, attributeId, section = null) {
                    if (!selectEl) return [];
                    if (!section) {
                        // Try to find section from selectEl
                        section = selectEl.closest('.spec-section');
                        if (!section) return [];
                    }
                    
                    try {
                        const res = await fetch('?ajax=attribute_values&id=' + encodeURIComponent(attributeId), { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        const values = (data && data.success && Array.isArray(data.values)) ? data.values : [];
                        const attribute_type = (data && data.attribute_type) ? data.attribute_type : 'multi';
                        
                        // Store attribute_type in section dataset
                        if (section) {
                            section.dataset.attributeType = attribute_type;
                        }
                        
                        // Handle text type - show text input instead of select
                        if (attribute_type === 'text') {
                            // Hide the select element
                            if (selectEl.tagName === 'SELECT') {
                                selectEl.style.display = 'none';
                                // Destroy Choices if exists
                                if (selectEl._choices) {
                                    selectEl._choices.destroy();
                                    selectEl._choices = null;
                                }
                            }
                            
                            // Find the variations column
                            const varLabel = Array.from(section.querySelectorAll('label'))
                                .find(l => (l.textContent || '').toLowerCase().includes('variation'));
                            const varWrap = varLabel ? varLabel.closest('.col-12, .col-xl-6, .col-lg-6, .col-md-6, .col-sm-6, .col-12.mb-1') : null;
                            
                            if (varWrap) {
                                // Hide existing select
                                const existingSelect = varWrap.querySelector('select');
                                if (existingSelect) {
                                    existingSelect.style.display = 'none';
                                }
                                
                                // Check if text container already exists
                                let container = section.querySelector('.text-variants-container');
                                if (!container) {
                                    // Create container
                                    container = document.createElement('div');
                                    container.className = 'text-variants-container mt-2';
                                    container.style.cssText = 'min-height: 40px; padding: 8px; border: 1px solid #dee2e6; border-radius: 4px;';
                                    
                                    // Create text input
                                    const textInput = document.createElement('input');
                                    textInput.type = 'text';
                                    textInput.className = 'form-control mb-2 text-variant-input';
                                    textInput.placeholder = 'Type variant name (max 30 characters)';
                                    textInput.maxLength = 30;
                                    textInput.style.cssText = 'width: 100%;';
                                    
                                    // Auto-add on blur (when user leaves the input)
                                    textInput.addEventListener('blur', function() {
                                        const value = this.value.trim();
                                        if (value) {
                                            addTextVariant(section, value);
                                            this.value = '';
                                        }
                                    });
                                    
                                    // Also allow Enter key for convenience
                                    textInput.addEventListener('keydown', function(e) {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            this.blur(); // Trigger blur to add variant
                                        }
                                    });
                                    
                                    container.appendChild(textInput);
                                    varWrap.appendChild(container);
                                }
                            }
                            
                            return []; // Text attributes don't have predefined values
                        }
                        
                        // For single and multi types, use the select element
                        // Reset options
                        if (selectEl.tagName === 'SELECT') {
                            selectEl.innerHTML = '';
                            selectEl.style.display = '';
                            
                            // Configure select element based on attribute_type
                            if (attribute_type === 'single') {
                                // Remove multiple attribute for single select
                                selectEl.removeAttribute('multiple');
                                // Destroy existing Choices instance if it exists
                                if (selectEl._choices) {
                                    selectEl._choices.destroy();
                                    selectEl._choices = null;
                                }
                                // Initialize Choices.js for single select
                                if (window.Choices) {
                                    try {
                                        selectEl._choices = new Choices(selectEl, { 
                                            removeItemButton: false, 
                                            searchEnabled: true, 
                                            allowHTML: true,
                                            shouldSort: false
                                        });
                                    } catch (e) {
                                        console.error('Error initializing Choices.js for single select:', e);
                                    }
                                }
                            } else {
                                // Ensure multiple attribute exists for multi select
                                selectEl.setAttribute('multiple', 'multiple');
                                // Destroy existing Choices instance if it exists
                                if (selectEl._choices) {
                                    selectEl._choices.destroy();
                                    selectEl._choices = null;
                                }
                                // Initialize Choices.js for multi select
                                if (window.Choices) {
                                    try {
                                        selectEl._choices = new Choices(selectEl, { 
                                            removeItemButton: true, 
                                            searchEnabled: true, 
                                            allowHTML: true 
                                        });
                                    } catch (e) {
                                        console.error('Error initializing Choices.js for multi select:', e);
                                    }
                                }
                            }
                            
                            // Populate options
                            if (selectEl._choices) {
                                selectEl._choices.clearStore();
                                selectEl._choices.setChoices(values.map(v => ({ value: String(v.id), label: v.name })), 'value', 'label', true);
                            } else {
                                values.forEach(v => {
                                    const opt = document.createElement('option');
                                    opt.value = String(v.id);
                                    opt.textContent = v.name;
                                    selectEl.appendChild(opt);
                                });
                            }
                        }
                        
                        return values; // Return values for further processing
                    } catch (e) {
                        console.error('Error populating variants:', e);
                        return [];
                    }
                }
                function renderPriceRows(section) {
                    const attrSelect = getAttrSelect(section);
                    const variantsSelect = getVariantsSelect(section);
                    if (!attrSelect || !variantsSelect) return;
                    const container = ensurePriceRowsContainer(section);
                    
                    // Get selected variants - handle text variants differently
                    const attributeType = section.dataset.attributeType || 'multi';
                    let selectedVariants = [];
                    
                    if (attributeType === 'text') {
                        // Get text variants
                        selectedVariants = getTextVariants(section).map(v => ({
                            value: v.id,
                            textContent: v.name
                        }));
                    } else {
                        // Get selected options from select element
                        selectedVariants = Array.from(variantsSelect.selectedOptions || []);
                    }
                    // Store existing price data before clearing
                    const existingPrices = {};
                    const currentInputs = container.querySelectorAll('input[data-variant-id]');
                    currentInputs.forEach(input => {
                        const variantId = input.dataset.variantId;
                        const field = input.dataset.field;
                        const value = input.value.trim();
                        if (variantId && field && value) {
                            if (!existingPrices[variantId]) {
                                existingPrices[variantId] = {};
                            }
                            existingPrices[variantId][field] = value;
                        }
                    });
                    ////console.log('Stored existing prices before rebuild:', existingPrices);
                    container.innerHTML = '';
                    selectedVariants.forEach(opt => {
                        const variantId = String(opt.value);
                        const variantName = (opt.textContent || variantId).trim();
                        // Get existing price data for this variant
                        const existingMrp = existingPrices[variantId]?.mrp || window.existingPriceData?.[variantId]?.mrp || '';
                        const existingSellingPrice = existingPrices[variantId]?.selling_price || window.existingPriceData?.[variantId]?.selling_price || '';
                        // ////console.log(`Rendering price row for variant ${variantId} (${variantName}):`, {
                        //     existingMrp, 
                        //     existingSellingPrice,
                        //     fromStored: existingPrices[variantId],
                        //     fromGlobal: window.existingPriceData?.[variantId]
                        // });
                        const row = document.createElement('div');
                        row.className = 'row g-2 align-items-end mb-2';
                        row.innerHTML = `
                    <div class="col-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                        <label class="form-label mb-1">${variantName}</label>
                    </div>
                    <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12">
                    
                        <label class="form-label mb-1">MRP<span class="text-danger">*</span></label>
                        <input type="text" class="form-control" data-variant-id="${variantId}" data-field="mrp" placeholder="₹1,499.90" value="${existingMrp}" required>
                    </div>
                    <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12">
                        <label class="form-label mb-1">Selling Price<span class="text-danger">*</span></label>
                        <input type="text" class="form-control" data-variant-id="${variantId}" data-field="selling_price" placeholder="₹1,299.99" value="${existingSellingPrice}" required>
                        <div class="invalid-feedback"></div>
                    </div>
                `;
                        container.appendChild(row);
                        // Real-time validation: show invalid only after user interaction or submit
                        const mrpInput = row.querySelector(`input[data-variant-id="${variantId}"][data-field="mrp"]`);
                        const spInput = row.querySelector(`input[data-variant-id="${variantId}"][data-field="selling_price"]`);
                        const fb = row.querySelector('.invalid-feedback');
                        
                        // Restrict input to only numeric values and decimal point
                        const restrictToNumeric = function(input) {
                            input.addEventListener('input', function(e) {
                                // Get current value
                                let value = e.target.value;
                                // Remove any character that is not a digit or decimal point
                                value = value.replace(/[^0-9.]/g, '');
                                // Prevent multiple decimal points
                                const parts = value.split('.');
                                if (parts.length > 2) {
                                    value = parts[0] + '.' + parts.slice(1).join('');
                                }
                                // Update the input value
                                if (e.target.value !== value) {
                                    e.target.value = value;
                                }
                            });
                            
                            // Also handle paste events
                            input.addEventListener('paste', function(e) {
                                e.preventDefault();
                                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                                // Filter to only numeric and decimal point
                                let filtered = pastedText.replace(/[^0-9.]/g, '');
                                // Prevent multiple decimal points
                                const parts = filtered.split('.');
                                if (parts.length > 2) {
                                    filtered = parts[0] + '.' + parts.slice(1).join('');
                                }
                                // Insert at cursor position
                                const start = input.selectionStart;
                                const end = input.selectionEnd;
                                const currentValue = input.value;
                                input.value = currentValue.substring(0, start) + filtered + currentValue.substring(end);
                                input.setSelectionRange(start + filtered.length, start + filtered.length);
                            });
                            
                            // Prevent non-numeric keys
                            input.addEventListener('keypress', function(e) {
                                const char = String.fromCharCode(e.which || e.keyCode);
                                // Allow: numbers (0-9), decimal point (.), backspace, delete, tab, escape, enter
                                if (!/[0-9.]/.test(char) && !e.ctrlKey && !e.metaKey && 
                                    e.keyCode !== 8 && e.keyCode !== 9 && e.keyCode !== 27 && e.keyCode !== 13 && 
                                    e.keyCode !== 46 && e.keyCode !== 37 && e.keyCode !== 39) {
                                    e.preventDefault();
                                }
                                // Prevent multiple decimal points
                                if (char === '.' && input.value.indexOf('.') !== -1) {
                                    e.preventDefault();
                                }
                            });
                        };
                        
                        // Apply restrictions to both inputs
                        if (mrpInput) {
                            restrictToNumeric(mrpInput);
                        }
                        if (spInput) {
                            restrictToNumeric(spInput);
                        }
                        
                        const validate = function () {
                            const mrpVal = parseFloat(String(mrpInput.value || '').replace(/[^0-9.]/g, ''));
                            const spVal = parseFloat(String(spInput.value || '').replace(/[^0-9.]/g, ''));
                            const shouldShow = (window.__specSubmitAttempted__ === true) || mrpInput.dataset.touched === '1' || spInput.dataset.touched === '1';
                            // Required checks
                            const mrpMissing = String(mrpInput.value || '').trim() === '' || isNaN(mrpVal);
                            const spMissing = String(spInput.value || '').trim() === '' || isNaN(spVal);
                            if (shouldShow && mrpMissing) { mrpInput.classList.add('is-invalid'); } else { mrpInput.classList.remove('is-invalid'); }
                            if (shouldShow && spMissing) { spInput.classList.add('is-invalid'); if (fb) fb.textContent = ''; return; } else { spInput.classList.remove('is-invalid'); }
                            if (shouldShow && !mrpMissing && !spMissing && spVal > mrpVal) { spInput.classList.add('is-invalid'); if (fb) fb.textContent = 'Selling Price must be less than or equal to MRP.'; } else { spInput.classList.remove('is-invalid'); if (fb) fb.textContent = ''; }
                        };
                        if (mrpInput) { mrpInput.addEventListener('input', () => { mrpInput.dataset.touched = '1'; validate(); }); mrpInput.addEventListener('blur', () => { mrpInput.dataset.touched = '1'; validate(); }); }
                        if (spInput) { spInput.addEventListener('input', () => { spInput.dataset.touched = '1'; validate(); }); spInput.addEventListener('blur', () => { spInput.dataset.touched = '1'; validate(); }); }
                        // Initial check with prefilled values
                        validate();
                    });
                }
                function initOneSection(section, isMandatory = false, mandatoryAttrId = null) {
                    //console.log('initOneSection called with isMandatory:', isMandatory, 'mandatoryAttrId:', mandatoryAttrId);
                    if (!section.dataset.specUid) section.dataset.specUid = String(++SPEC_SECTION_UID);
                    section.classList.add('spec-section');
                    if (isMandatory) {
                        //console.log('Adding mandatory-section class');
                        section.classList.add('mandatory-section');
                        section.dataset.mandatoryAttrId = mandatoryAttrId;
                    } else {
                        //console.log('Section is non-mandatory, ensuring no mandatory classes');
                        section.classList.remove('mandatory-section');
                    }
                    // Ensure section is visible if it had been hidden by template
                    try {
                        if (section.hasAttribute('hidden')) section.removeAttribute('hidden');
                        if (section.style && section.style.display === 'none') section.style.display = '';
                    } catch (e) { }
                    const attrSelect = getAttrSelect(section);
                    let variantsSelect = getVariantsSelect(section);
                    const radios = getYesNoRadios(section);
                    const priceRows = ensurePriceRowsContainer(section);
                    if (!attrSelect) return;
                    // Default selection: No (no price change) by default on new sections
                    try {
                        if (radios && radios.no) {
                            radios.no.checked = true;
                        }
                    } catch (e) { }
                    // Ensure a real multi select exists under the Variations label
                    if (!variantsSelect) {
                        const varLabel = Array.from(section.querySelectorAll('label')).find(l => (l.textContent || '').toLowerCase().includes('variation'));
                        const varWrap = varLabel ? varLabel.closest('.col-12, .col-xl-6, .col-lg-6, .col-md-6, .col-sm-6, .col-12.mb-1') : null;
                        if (varWrap) {
                            variantsSelect = document.createElement('select');
                            variantsSelect.className = 'form-control';
                            variantsSelect.setAttribute('multiple', '');
                            varWrap.appendChild(variantsSelect);
                        }
                    }
                    if (!variantsSelect) return;
                    // Ensure unique IDs per section
                    const uid = section.dataset.specUid;
                    try {
                        attrSelect.id = 'attribute-select-' + uid;
                    } catch (e) { }
                    try {
                        variantsSelect.id = 'variants-select-' + uid;
                    } catch (e) { }
                    // Make radio buttons unique per section to avoid conflicts
                    const radioButtons = section.querySelectorAll('input[type="radio"]');
                    radioButtons.forEach((radio, index) => {
                        const isYes = index === 0; // First radio is Yes, second is No
                        radio.id = `price-change-${isYes ? 'yes' : 'no'}-${uid}`;
                        radio.name = `price-change-${uid}`;
                        // Update the corresponding label
                        const label = section.querySelector(`label[for="${isYes ? 'flexRadioDefault1' : 'flexRadioDefault2'}"]`);
                        if (label) {
                            label.setAttribute('for', radio.id);
                        }
                    });
                    // Initialize Choices on the variants select if not already
                    // Clean any stray attributes that keep it hidden before initializing Choices
                    try {
                        // Clean stale attributes before initializing Choices
                        variantsSelect.removeAttribute('hidden');
                        variantsSelect.removeAttribute('data-choice');
                        variantsSelect.tabIndex = 0;
                    } catch (e) { }
                    if (window.Choices && !variantsSelect._choices) {
                        try { variantsSelect._choices = new Choices(variantsSelect, { removeItemButton: true, searchEnabled: true, allowHTML: true }); } catch (e) { }
                    }
                    // Ensure native select is hidden when Choices UI is present
                    try {
                        if (variantsSelect._choices) {
                            variantsSelect.hidden = true;
                            variantsSelect.setAttribute('data-choice', 'active');
                        }
                    } catch (e) { }
                    // Handle mandatory sections - always disabled in both add and edit mode
                    if (isMandatory && mandatoryAttrId) {
                        //console.log('Processing mandatory section with attrId:', mandatoryAttrId);
                        attrSelect.value = mandatoryAttrId;
                        // Check if we're in edit mode
                        const isEditMode = <?php echo !empty($editing_product_id) ? 'true' : 'false'; ?>;
                        // Always disable dropdown for mandatory attributes in both modes
                        attrSelect.disabled = true;
                        attrSelect.style.backgroundColor = '#f8f9fa';
                        attrSelect.style.cursor = 'not-allowed';
                        // Add a small indicator
                        const label = section.querySelector('label');
                        if (label && !label.querySelector('.mandatory-indicator')) {
                            //console.log('Adding lock icon to mandatory section');
                            const indicator = document.createElement('span');
                            indicator.className = 'mandatory-indicator text-warning ms-1';
                            indicator.innerHTML = '<i class="fa fa-lock"></i>';
                            indicator.title = 'Mandatory attribute';
                            label.appendChild(indicator);
                        }
                    } else {
                        //console.log('Section is non-mandatory, ensuring no lock icon');
                        // Remove any existing lock icons from non-mandatory sections
                        const label = section.querySelector('label');
                        if (label) {
                            const existingIndicator = label.querySelector('.mandatory-indicator');
                            if (existingIndicator) {
                                //console.log('Removing lock icon from non-mandatory section');
                                existingIndicator.remove();
                            }
                        }
                    }
                    // Handle delete for mandatory sections - hide delete button completely
                    try {
                        if (section.classList.contains('mandatory-section')) {
                            //console.log('Hiding delete button for mandatory section');
                            const del = section.querySelector('.btn-delete-section');
                            if (del) {
                                del.style.display = 'none'; // Hide delete button for mandatory sections
                                del.disabled = true; // Disable delete button
                            }
                        } else {
                            //console.log('Ensuring delete button is visible for non-mandatory section');
                            const del = section.querySelector('.btn-delete-section');
                            if (del) {
                                del.style.display = ''; // Show delete button for non-mandatory sections
                                del.disabled = false; // Enable delete button
                            }
                        }
                    } catch (e) { }
                    // Attribute -> load values
                    attrSelect.addEventListener('change', async () => {
                        const id = attrSelect.value;
                        
                        // Clean up text variants container if switching from text to another type
                        const textContainer = section.querySelector('.text-variants-container');
                        if (textContainer) {
                            textContainer.remove();
                        }
                        // Show the select element if it was hidden
                        if (variantsSelect && variantsSelect.tagName === 'SELECT') {
                            variantsSelect.style.display = '';
                        }
                        
                        await populateVariants(variantsSelect, id, section);
                        priceRows.innerHTML = '';
                        if (radios.no) radios.no.checked = true;
                        // Remove selected attributes from other section dropdowns
                        removeSelectedAttributesFromOtherDropdowns();
                        // Save to localStorage
                        debouncedSaveToLocalStorage();
                    });
                    // Variants changed while YES -> rebuild rows
                    // Only attach to select elements, not text containers
                    if (variantsSelect && variantsSelect.tagName === 'SELECT') {
                        variantsSelect.addEventListener('change', () => {
                            if (radios.yes && radios.yes.checked) {
                                renderPriceRows(section);
                            }
                            // Save to localStorage
                            debouncedSaveToLocalStorage();
                        });
                    }
                    // Yes/No handlers
                    if (radios.yes) {
                        radios.yes.addEventListener('change', () => {
                            if (radios.yes.checked) {
                                renderPriceRows(section);
                            }
                            // Save to localStorage
                            debouncedSaveToLocalStorage();
                        });
                        // Also handle click event to ensure it works
                        radios.yes.addEventListener('click', () => {
                            setTimeout(() => {
                                if (radios.yes.checked) {
                                    renderPriceRows(section);
                                    debouncedSaveToLocalStorage();
                                }
                            }, 10);
                        });
                    }
                    if (radios.no) {
                        radios.no.addEventListener('change', () => {
                            if (radios.no.checked) priceRows.innerHTML = '';
                            // Save to localStorage
                            debouncedSaveToLocalStorage();
                        });
                        // Also handle click event to ensure it works
                        radios.no.addEventListener('click', () => {
                            setTimeout(() => {
                                if (radios.no.checked) {
                                    priceRows.innerHTML = '';
                                    debouncedSaveToLocalStorage();
                                }
                            }, 10);
                        });
                    }
                    // Add event listeners for price inputs
                    const priceContainer = section.querySelector('.variant-price-rows');
                    if (priceContainer) {
                        // Use event delegation for dynamically added price inputs
                        priceContainer.addEventListener('input', (e) => {
                            if (e.target.matches('input[data-variant-id][data-field]')) {
                                debouncedSaveToLocalStorage();
                            }
                        });
                        priceContainer.addEventListener('blur', (e) => {
                            if (e.target.matches('input[data-variant-id][data-field]')) {
                                debouncedSaveToLocalStorage();
                            }
                        }, true);
                    }
                }
                // Collect JSON per the required schema
                function collectSpecifications() {
                    ////console.log('Starting to collect specifications...');
                    const specs = [];
                    const sections = document.querySelectorAll('.spec-section');
                    ////console.log(`Found ${sections.length} spec sections`);
                    sections.forEach((section, index) => {
                        ////console.log(`Processing section ${index + 1}:`, section);
                        const attrSelect = getAttrSelect(section);
                        const variantsSelect = getVariantsSelect(section);
                        if (!attrSelect || !variantsSelect) return;
                        const attributeId = parseInt(attrSelect.value, 10);
                        if (!attributeId) return;
                        
                        // Get variants based on attribute type
                        const attributeType = section.dataset.attributeType || 'multi';
                        let variants = [];
                        
                        if (attributeType === 'text') {
                            // Get text variants
                            const textVariants = getTextVariants(section);
                            variants = textVariants.map(v => ({
                                id: v.id,
                                name: v.name,
                                is_text_variant: true
                            }));
                        } else {
                            // Get selected options from select element
                            variants = Array.from(variantsSelect.selectedOptions || [])
                                .map(o => {
                                    const id = o.value; // Keep as string since variant IDs are strings like "val_1758773975_0"
                                    //console.log(`Processing variant option: value="${o.value}", text="${o.text}"`);
                                    return { id: id || null };
                                })
                                .filter(v => v.id !== null && v.id !== ''); // Filter out variants with null or empty IDs
                        }
                        
                        //console.log('Final variants array:', variants);
                        if (!variants.length) return;
                        // Get existing price data from localStorage/stored data
                        const existingPriceData = window.existingPriceData || {};
                        
                        // If YES checked, read price fields for selected variants
                        const radios = getYesNoRadios(section);
                        ////console.log('Checking radios for section:', {yes: radios.yes, no: radios.no, yesChecked: radios.yes?.checked});
                        if (radios.yes && radios.yes.checked) {
                            ////console.log('Yes radio is checked, looking for price inputs');
                            // Look for price inputs in the variant-price-rows container
                            const priceContainer = section.querySelector('.variant-price-rows');
                            ////console.log('Price container found:', priceContainer);
                            if (priceContainer) {
                                const inputs = Array.from(priceContainer.querySelectorAll('input[data-variant-id]'));
                                ////console.log('Price inputs found:', inputs.length);
                                variants.forEach(v => {
                                    const mrpEl = inputs.find(i => i.dataset.variantId === String(v.id) && i.dataset.field === 'mrp');
                                    const spEl = inputs.find(i => i.dataset.variantId === String(v.id) && i.dataset.field === 'selling_price');
                                    ////console.log(`Processing variant ${v.id}:`, {mrpEl, spEl, mrpValue: mrpEl?.value, spValue: spEl?.value});
                                    // Try to get price from current inputs first
                                    if (mrpEl && mrpEl.value.trim() !== '') {
                                        // Remove commas and other non-numeric characters except decimal point
                                        const cleanValue = String(mrpEl.value).replace(/[^0-9.]/g, '');
                                        const mrp = parseFloat(cleanValue);
                                        if (!isNaN(mrp) && mrp > 0) {
                                            v.mrp = mrp;
                                            //console.log(`Set MRP for variant ${v.id} from input: ${mrpEl.value} -> ${mrp}`);
                                        } else {
                                            console.warn(`Invalid MRP value for variant ${v.id}: ${mrpEl.value} -> ${cleanValue} -> ${mrp}`);
                                        }
                                    }
                                    if (spEl && spEl.value.trim() !== '') {
                                        // Remove commas and other non-numeric characters except decimal point
                                        const cleanValue = String(spEl.value).replace(/[^0-9.]/g, '');
                                        const sellingPrice = parseFloat(cleanValue);
                                        if (!isNaN(sellingPrice) && sellingPrice > 0) {
                                            v.selling_price = sellingPrice;
                                            //console.log(`Set Selling Price for variant ${v.id} from input: ${spEl.value} -> ${sellingPrice}`);
                                        } else {
                                            // console.warn(`Invalid Selling Price value for variant ${v.id}: ${spEl.value} -> ${cleanValue} -> ${sellingPrice}`);
                                        }
                                    }
                                    // If no price found in inputs, try to get from existing data
                                    if (!v.mrp && existingPriceData[v.id] && existingPriceData[v.id].mrp) {
                                        v.mrp = existingPriceData[v.id].mrp;
                                        //console.log(`Set MRP for variant ${v.id} from existing data: ${v.mrp}`);
                                    }
                                    if (!v.selling_price && existingPriceData[v.id] && existingPriceData[v.id].selling_price) {
                                        v.selling_price = existingPriceData[v.id].selling_price;
                                        //console.log(`Set Selling Price for variant ${v.id} from existing data: ${v.selling_price}`);
                                    }
                                    
                                    // Update the global price data with current values (preserve what we have)
                                    if (!window.existingPriceData) {
                                        window.existingPriceData = {};
                                    }
                                    // Always preserve price data, even if it's from stored data
                                    if (v.mrp || v.selling_price) {
                                        window.existingPriceData[v.id] = {
                                            mrp: v.mrp || (existingPriceData[v.id]?.mrp || null),
                                            selling_price: v.selling_price || (existingPriceData[v.id]?.selling_price || null)
                                        };
                                        //console.log(`Updated global price data for variant ${v.id}:`, window.existingPriceData[v.id]);
                                    } else if (existingPriceData[v.id]) {
                                        // Preserve existing data even if not in current variant object
                                        window.existingPriceData[v.id] = existingPriceData[v.id];
                                    }
                                });
                            }
                        } else {
                            // When "No" is checked, DO NOT include any price data
                            // Clear any price data that might have been set previously
                            variants.forEach(v => {
                                // Explicitly remove price data when "No" is selected
                                delete v.mrp;
                                delete v.selling_price;
                            });
                        }
                        specs.push({ attribute_id: attributeId, variants });
                        //console.log(`Added spec for attribute ${attributeId} with ${variants.length} variants:`, variants);
                    });
                    //console.log('Final collected specifications:', specs);
                    return specs;
                }
                // Function to create mandatory attribute sections
                async function createMandatorySections(categoryOrSubcategoryId, existingSpecsForPopulate = null) {
                    if (MANDATORY_SECTIONS_CREATED || CREATING_MANDATORY_SECTIONS) {
                        console.log('Mandatory sections already created or being created, skipping');
                        return;
                    }
                    CREATING_MANDATORY_SECTIONS = true;
                    try {
                        // If we already have mandatory attributes preloaded from PHP, use them directly
                        if (Array.isArray(MANDATORY_ATTRIBUTES) && MANDATORY_ATTRIBUTES.length > 0) {
                            const attrIds = MANDATORY_ATTRIBUTES.join(',');
                            try {
                                const attrResponse = await fetch('<?php echo USER_BASEURL; ?>app/api/get_attribute_names.php?ids=' + encodeURIComponent(attrIds));
                                const attrData = await attrResponse.json();
                                if (attrData.success && Array.isArray(attrData.attributes)) {
                                    // Create sections for each mandatory attribute
                                    for (let i = 0; i < attrData.attributes.length; i++) {
                                        const attr = attrData.attributes[i];
                                        const section = await createMandatorySection(attr.id, attr.name, i === 0);
                                        if (section) {
                                            // Populate variants from existingSpecs if provided (for edit mode)
                                            if (existingSpecsForPopulate && Array.isArray(existingSpecsForPopulate)) {
                                                const specForAttr = existingSpecsForPopulate.find(s => parseInt(s.attribute_id, 10) === attr.id);
                                                if (specForAttr && specForAttr.variants && Array.isArray(specForAttr.variants) && specForAttr.variants.length > 0) {
                                                    const varSel = getVariantsSelect(section);
                                                    if (varSel) {
                                                        // Wait for variants to be populated
                                                        await new Promise(r => setTimeout(r, 300));
                                                        
                                                        // Wait for Choices.js to initialize
                                                        if (varSel._choices) {
                                                            await new Promise(r => setTimeout(r, 200));
                                                        } else if (window.Choices && !varSel._choices) {
                                                            try {
                                                                varSel._choices = new Choices(varSel, { removeItemButton: true, searchEnabled: true, allowHTML: true });
                                                                await new Promise(r => setTimeout(r, 200));
                                                            } catch (e) {
                                                                console.error('Error initializing Choices.js:', e);
                                                            }
                                                        }
                                                        
                                                        // Set selected variants
                                                        const variantIds = specForAttr.variants.map(v => String(v.id));
                                                        console.log(`Setting variants for mandatory section ${attr.id} from existingSpecs:`, variantIds);
                                                        
                                                        if (varSel._choices) {
                                                            try {
                                                                varSel._choices.setChoiceByValue(variantIds);
                                                            } catch (e) {
                                                                console.error('Error setting Choices.js values:', e);
                                                                variantIds.forEach(vid => {
                                                                    const option = varSel.querySelector(`option[value="${vid}"]`);
                                                                    if (option) option.selected = true;
                                                                });
                                                            }
                                                        } else {
                                                            variantIds.forEach(vid => {
                                                                const option = varSel.querySelector(`option[value="${vid}"]`);
                                                                if (option) option.selected = true;
                                                            });
                                                        }
                                                        
                                                        varSel.dispatchEvent(new Event('change'));
                                                        
                                                        // Handle price data
                                                        if (specForAttr.variants.some(v => v.mrp || v.selling_price)) {
                                                            const radios = getYesNoRadios(section);
                                                            if (radios.yes) {
                                                                if (radios.no) radios.no.checked = false;
                                                                radios.yes.checked = true;
                                                                radios.yes.dispatchEvent(new Event('change'));
                                                                await new Promise(r => setTimeout(r, 300));
                                                                specForAttr.variants.forEach(variant => {
                                                                    if (variant.mrp || variant.selling_price) {
                                                                        const mrpInput = section.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                        const spInput = section.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                        if (mrpInput && variant.mrp) mrpInput.value = variant.mrp;
                                                                        if (spInput && variant.selling_price) spInput.value = variant.selling_price;
                                                                    }
                                                                });
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    // Wait a bit for all sections to be fully rendered
                                    await new Promise(resolve => setTimeout(resolve, 500));
                                    MANDATORY_SECTIONS_CREATED = true;
                                    
                                    // Check if optional attributes exist and show loader immediately to prevent gap
                                    let hasOptionalAttrs = false;
                                    if (existingSpecsForPopulate && Array.isArray(existingSpecsForPopulate) && existingSpecsForPopulate.length > 0) {
                                        // Check if there are any optional attributes (not in mandatory list)
                                        hasOptionalAttrs = existingSpecsForPopulate.some(spec => {
                                            const attrId = parseInt(spec.attribute_id, 10);
                                            return !MANDATORY_ATTRIBUTES.includes(attrId);
                                        });
                                    } else if (window.localStorageSpecs && Array.isArray(window.localStorageSpecs) && window.localStorageSpecs.length > 0) {
                                        // Check if there are any optional attributes (not in mandatory list)
                                        hasOptionalAttrs = window.localStorageSpecs.some(spec => {
                                            const attrId = parseInt(spec.attribute_id, 10);
                                            return !MANDATORY_ATTRIBUTES.includes(attrId);
                                        });
                                    } else if (OPTIONAL_ATTRIBUTES && OPTIONAL_ATTRIBUTES.length > 0) {
                                        // If optional attributes are preloaded, show loader
                                        hasOptionalAttrs = true;
                                    }
                                    
                                    if (hasOptionalAttrs) {
                                        // Show optional loader immediately to prevent gap
                                        showOptionalAttributeLoader();
                                    }
                                    
                                    // Hide section 1 when mandatory sections are created
                                    hideSection1WhenMandatoryLoaded();
                                    // Also remove mandatory attributes from section 1 dropdown
                                    const section1 = document.getElementById('add-more-item');
                                    if (section1) {
                                        removeMandatoryAttributesFromDropdown(section1);
                                    }
                                    // Trigger check for all sections loaded
                                    if (typeof markSpecsInitialized === 'function') {
                                        setTimeout(() => {
                                            markSpecsInitialized();
                                        }, 500);
                                    }
                                    return;
                                }
                            } catch (e) {
                                console.error('Error creating mandatory sections from preloaded data:', e);
                            }
                        }
                        // Prioritize subcategory over category for mandatory attributes
                        let apiUrl = '<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?';
                        const subcategoryId = document.getElementById('Light-SubCategory')?.value;
                        // Check if the passed ID is a subcategory ID (from session) or if we have a subcategory selected
                        if (subcategoryId || (categoryOrSubcategoryId && !document.getElementById('Light-SubCategory'))) {
                            // If we have a subcategory selected or if this is a subcategory ID from session
                            const finalSubcategoryId = subcategoryId || categoryOrSubcategoryId;
                            apiUrl += 'sub_category_id=' + encodeURIComponent(finalSubcategoryId);
                            console.log('Using subcategory ID for mandatory attributes:', finalSubcategoryId);
                        } else {
                            apiUrl += 'category_id=' + encodeURIComponent(categoryOrSubcategoryId);
                            console.log('Using category ID for mandatory attributes:', categoryOrSubcategoryId);
                        }
                        const response = await fetch(apiUrl);
                        const data = await response.json();
                        if (data.success && Array.isArray(data.mandatory) && data.mandatory.length > 0) {
                            MANDATORY_ATTRIBUTES = data.mandatory;
                            // Get attribute names for display
                            const attrIds = data.mandatory.join(',');
                            const attrResponse = await fetch('<?php echo USER_BASEURL; ?>app/api/get_attribute_names.php?ids=' + encodeURIComponent(attrIds));
                            const attrData = await attrResponse.json();
                            if (attrData.success && Array.isArray(attrData.attributes)) {
                                // Prioritize existingSpecsForPopulate over localStorage
                                const specsToUse = existingSpecsForPopulate || loadSpecsFromLocalStorage();
                                
                                // Create sections for each mandatory attribute
                                console.log(`Creating ${attrData.attributes.length} mandatory sections`);
                                for (let i = 0; i < attrData.attributes.length; i++) {
                                    const attr = attrData.attributes[i];
                                    console.log(`Creating mandatory section ${i + 1}/${attrData.attributes.length} for attribute ${attr.id} (${attr.name})`);
                                    const section = await createMandatorySection(attr.id, attr.name, i === 0);
                                    if (section) {
                                        console.log(`Successfully created mandatory section for attribute ${attr.id}`);
                                        
                                        // Populate variants from existingSpecsForPopulate or localStorage if available
                                        if (specsToUse && Array.isArray(specsToUse)) {
                                            const specForAttr = specsToUse.find(s => parseInt(s.attribute_id, 10) === attr.id);
                                            if (specForAttr && specForAttr.variants && Array.isArray(specForAttr.variants) && specForAttr.variants.length > 0) {
                                                const source = existingSpecsForPopulate ? 'existingSpecs' : 'localStorage';
                                                console.log(`Populating mandatory section ${attr.id} with variants from ${source}`);
                                                const varSel = getVariantsSelect(section);
                                                if (varSel) {
                                                    // Wait for variants to be populated (createMandatorySection already calls populateVariants)
                                                    await new Promise(r => setTimeout(r, 300));
                                                    
                                                    // Wait for Choices.js to initialize if it exists
                                                    if (varSel._choices) {
                                                        await new Promise(r => setTimeout(r, 200));
                                                    } else if (window.Choices && !varSel._choices) {
                                                        try {
                                                            varSel._choices = new Choices(varSel, { removeItemButton: true, searchEnabled: true, allowHTML: true });
                                                            await new Promise(r => setTimeout(r, 200));
                                                        } catch (e) {
                                                            console.error('Error initializing Choices.js for mandatory section:', e);
                                                        }
                                                    }
                                                    
                                                    // Set selected variants
                                                    const variantIds = specForAttr.variants.map(v => String(v.id));
                                                    console.log(`Setting variants for mandatory section ${attr.id}:`, variantIds);
                                                    
                                                    if (varSel._choices) {
                                                        try {
                                                            varSel._choices.setChoiceByValue(variantIds);
                                                            console.log('Variants set using Choices.js');
                                                        } catch (e) {
                                                            console.error('Error setting Choices.js values:', e);
                                                            // Fallback to native select
                                                            variantIds.forEach(vid => {
                                                                const option = varSel.querySelector(`option[value="${vid}"]`);
                                                                if (option) option.selected = true;
                                                            });
                                                        }
                                                    } else {
                                                        // Fallback to native select
                                                        variantIds.forEach(vid => {
                                                            const option = varSel.querySelector(`option[value="${vid}"]`);
                                                            if (option) option.selected = true;
                                                        });
                                                    }
                                                    
                                                    // Trigger change event
                                                    varSel.dispatchEvent(new Event('change'));
                                                    
                                                    // Handle price data if it exists
                                                    const radios = getYesNoRadios(section);
                                                    const hasPriceInSpec = specForAttr.variants.some(v => v.mrp || v.selling_price);
                                                    const hasPriceInStored = specForAttr.variants.some(v => {
                                                        const variantId = v.id;
                                                        return window.existingPriceData && window.existingPriceData[variantId] && 
                                                               (window.existingPriceData[variantId].mrp || window.existingPriceData[variantId].selling_price);
                                                    });
                                                    const hasPriceData = hasPriceInSpec || hasPriceInStored;
                                                    
                                                    if (hasPriceData && radios.yes) {
                                                        console.log(`Price data found for mandatory section ${attr.id}, selecting Yes radio`);
                                                        if (radios.no) radios.no.checked = false;
                                                        radios.yes.checked = true;
                                                        radios.yes.dispatchEvent(new Event('change'));
                                                        
                                                        // Wait for price rows to render
                                                        let attempts = 0;
                                                        const maxAttempts = 30;
                                                        const waitForPriceRows = () => {
                                                            return new Promise((resolve) => {
                                                                const checkPriceRows = () => {
                                                                    attempts++;
                                                                    const priceContainer = section.querySelector('.variant-price-rows');
                                                                    const priceInputs = priceContainer ? priceContainer.querySelectorAll('input[data-variant-id]') : [];
                                                                    if (priceInputs.length > 0 || attempts >= maxAttempts) {
                                                                        resolve();
                                                                    } else {
                                                                        setTimeout(checkPriceRows, 100);
                                                                    }
                                                                };
                                                                checkPriceRows();
                                                            });
                                                        };
                                                        await waitForPriceRows();
                                                        
                                                        // Fill in prices
                                                        specForAttr.variants.forEach(variant => {
                                                            const variantId = variant.id;
                                                            const mrp = variant.mrp || (window.existingPriceData && window.existingPriceData[variantId]?.mrp);
                                                            const sellingPrice = variant.selling_price || (window.existingPriceData && window.existingPriceData[variantId]?.selling_price);
                                                            
                                                            if (mrp || sellingPrice) {
                                                                const mrpInput = section.querySelector(`input[data-variant-id="${variantId}"][data-field="mrp"]`);
                                                                const spInput = section.querySelector(`input[data-variant-id="${variantId}"][data-field="selling_price"]`);
                                                                if (mrpInput && mrp) {
                                                                    mrpInput.value = mrp;
                                                                    mrpInput.dispatchEvent(new Event('input'));
                                                                }
                                                                if (spInput && sellingPrice) {
                                                                    spInput.value = sellingPrice;
                                                                    spInput.dispatchEvent(new Event('input'));
                                                                }
                                                            }
                                                        });
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        console.error(`Failed to create mandatory section for attribute ${attr.id}`);
                                    }
                                }
                                // Wait a bit for all sections to be fully rendered
                                await new Promise(resolve => setTimeout(resolve, 500));
                                MANDATORY_SECTIONS_CREATED = true;
                                
                                // Check if optional attributes exist and show loader immediately to prevent gap
                                let hasOptionalAttrs = false;
                                if (existingSpecsForPopulate && Array.isArray(existingSpecsForPopulate) && existingSpecsForPopulate.length > 0) {
                                    // Check if there are any optional attributes (not in mandatory list)
                                    hasOptionalAttrs = existingSpecsForPopulate.some(spec => {
                                        const attrId = parseInt(spec.attribute_id, 10);
                                        return !MANDATORY_ATTRIBUTES.includes(attrId);
                                    });
                                } else if (window.localStorageSpecs && Array.isArray(window.localStorageSpecs) && window.localStorageSpecs.length > 0) {
                                    // Check if there are any optional attributes (not in mandatory list)
                                    hasOptionalAttrs = window.localStorageSpecs.some(spec => {
                                        const attrId = parseInt(spec.attribute_id, 10);
                                        return !MANDATORY_ATTRIBUTES.includes(attrId);
                                    });
                                } else if (OPTIONAL_ATTRIBUTES && OPTIONAL_ATTRIBUTES.length > 0) {
                                    // If optional attributes are preloaded, show loader
                                    hasOptionalAttrs = true;
                                }
                                
                                if (hasOptionalAttrs) {
                                    // Show optional loader immediately to prevent gap
                                    showOptionalAttributeLoader();
                                }
                                
                                // Hide section 1 when mandatory sections are created
                                hideSection1WhenMandatoryLoaded();
                                // Also remove mandatory attributes from section 1 dropdown
                                const section1 = document.getElementById('add-more-item');
                                if (section1) {
                                    removeMandatoryAttributesFromDropdown(section1);
                                }
                                console.log('All mandatory sections created and populated');
                                // Trigger check for all sections loaded
                                if (typeof markSpecsInitialized === 'function') {
                                    setTimeout(() => {
                                        markSpecsInitialized();
                                    }, 500);
                                }
                            } else {
                                console.log('No mandatory attributes found for this category/subcategory');
                            }
                        } else {
                            console.log('No mandatory attributes found for this category/subcategory');
                        }
                    } catch (error) {
                        console.error('Error creating mandatory sections:', error);
                    } finally {
                        CREATING_MANDATORY_SECTIONS = false;
                    }
                }
                // Function to create a single mandatory section
                function findSectionByAttrId(attrId) {
                    //console.log(`findSectionByAttrId called for attribute ${attrId}`);
                    const sections = document.querySelectorAll('.spec-section');
                    //console.log(`Found ${sections.length} existing sections`);
                    for (const section of sections) {
                        const sel = getAttrSelect(section);
                        const currentValue = sel ? parseInt(sel.value, 10) : 0;
                        //console.log(`Checking section with attribute value: ${currentValue}`);
                        if (sel && currentValue === parseInt(attrId, 10)) {
                            //console.log(`Found existing section for attribute ${attrId}`);
                            return section;
                        }
                    }
                    //console.log(`No existing section found for attribute ${attrId}`);
                    return null;
                }
                // Function to hide section 1 when mandatory attributes are loaded
                function hideSection1WhenMandatoryLoaded() {
                    const section1 = document.getElementById('add-more-item');
                    // Only hide if mandatory sections are actually created (not just if attributes exist)
                    if (section1 && MANDATORY_ATTRIBUTES.length > 0 && MANDATORY_SECTIONS_CREATED) {
                        // Check if mandatory sections actually exist in DOM
                        const mandatorySections = document.querySelectorAll('.spec-section.mandatory-section');
                        if (mandatorySections.length > 0) {
                        section1.style.display = 'none';
                            console.log('Section 1 hidden because mandatory sections are created');
                        } else {
                            // If no mandatory sections in DOM yet, keep section 1 visible
                            section1.style.display = '';
                            console.log('Section 1 kept visible - mandatory sections not yet created in DOM');
                        }
                    } else {
                        // If no mandatory attributes or sections not created, keep section 1 visible
                        if (section1) {
                            section1.style.display = '';
                        }
                    }
                }
                // Function to check if mandatory attributes exist and hide section 1
                async function checkAndHideSection1IfMandatoryExists() {
                    try {
                        // If preloaded mandatory attributes exist, prefer them and skip API
                        if (Array.isArray(MANDATORY_ATTRIBUTES) && MANDATORY_ATTRIBUTES.length > 0) {
                            const section1 = document.getElementById('add-more-item');
                            if (section1) {
                                section1.style.display = 'none';
                                removeMandatoryAttributesFromDropdown(section1);
                            }
                            if (!MANDATORY_SECTIONS_CREATED) {
                                await createMandatorySections();
                            }
                            return;
                        }
                        // Check if we have a category selected
                        const categorySelect = document.querySelector('select[name="category"]');
                        let categoryId = '';
                        if (categorySelect && categorySelect.value) {
                            categoryId = categorySelect.value;
                        } else {
                            // Check session for stored category from previous step
                            const sessionCategory = '<?php echo htmlspecialchars($selected_category_id ?? ''); ?>';
                            const storedCategory = '<?php echo htmlspecialchars(isset($_SESSION['selected_category_for_mandatory']) ? $_SESSION['selected_category_for_mandatory'] : ''); ?>';
                            if (sessionCategory) {
                                categoryId = sessionCategory;
                            } else if (storedCategory) {
                                categoryId = storedCategory;
                            }
                        }
                        // Check for stored subcategory from first step
                        const storedSubcategory = '<?php echo htmlspecialchars(isset($_SESSION['selected_subcategory_for_mandatory']) ? $_SESSION['selected_subcategory_for_mandatory'] : ''); ?>';
                        let subcategoryId = '';
                        if (storedSubcategory) {
                            subcategoryId = storedSubcategory;
                            //console.log('Found stored subcategory from first step:', subcategoryId);
                        }
                        if (categoryId || subcategoryId) {
                            //console.log('Checking for mandatory attributes for category:', categoryId, 'subcategory:', subcategoryId);
                            // Prioritize subcategory over category for mandatory attributes
                            let apiUrl = '<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?';
                            if (subcategoryId) {
                                apiUrl += 'sub_category_id=' + encodeURIComponent(subcategoryId);
                            } else {
                                apiUrl += 'category_id=' + encodeURIComponent(categoryId);
                            }
                            const response = await fetch(apiUrl);
                            const data = await response.json();
                            //console.log('Mandatory attributes response:', data);
                            if (data && data.mandatory && data.mandatory.length > 0) {
                                //console.log('Mandatory attributes found, hiding section 1');
                                MANDATORY_ATTRIBUTES = data.mandatory;
                                const section1 = document.getElementById('add-more-item');
                                if (section1) {
                                    section1.style.display = 'none';
                                    // Also remove mandatory attributes from section 1 dropdown
                                    removeMandatoryAttributesFromDropdown(section1);
                                }
                                // Create mandatory sections if not already created
                                if (!MANDATORY_SECTIONS_CREATED) {
                                    //console.log('Creating mandatory sections from checkAndHideSection1IfMandatoryExists');
                                    // Use subcategory ID if available, otherwise fall back to category ID
                                    const subcategoryId = document.getElementById('Light-SubCategory')?.value;
                                    const idToUse = subcategoryId || categoryId;
                                    //console.log('Using ID for mandatory sections:', idToUse, 'Type:', subcategoryId ? 'subcategory' : 'category');
                                    await createMandatorySections(idToUse);
                                }
                            } else {
                                //console.log('No mandatory attributes found, showing section 1');
                                const section1 = document.getElementById('add-more-item');
                                if (section1) {
                                    section1.style.display = 'block';
                                }
                            }
                        }
                    } catch (error) {
                        //console.error('Error checking mandatory attributes:', error);
                    }
                }
                // Function to create mandatory sections if they exist
                async function createMandatorySectionsIfNeeded() {
                    try {
                        // Check if we have a category selected
                        const categorySelect = document.querySelector('select[name="category"]');
                        let categoryId = '';
                        if (categorySelect && categorySelect.value) {
                            categoryId = categorySelect.value;
                        } else {
                            // Check session for stored category from previous step
                            const sessionCategory = '<?php echo htmlspecialchars($selected_category_id ?? ''); ?>';
                            const storedCategory = '<?php echo htmlspecialchars(isset($_SESSION['selected_category_for_mandatory']) ? $_SESSION['selected_category_for_mandatory'] : ''); ?>';
                            if (sessionCategory) {
                                categoryId = sessionCategory;
                            } else if (storedCategory) {
                                categoryId = storedCategory;
                            }
                        }
                        if (categoryId && !MANDATORY_SECTIONS_CREATED) {
                            //console.log('Creating mandatory sections for category:', categoryId);
                            await createMandatorySections(categoryId);
                        } else {
                            //console.log('Skipping mandatory sections creation. CategoryId:', categoryId, 'Already created:', MANDATORY_SECTIONS_CREATED);
                        }
                    } catch (error) {
                        //console.error('Error creating mandatory sections:', error);
                    }
                }
                async function createMandatorySection(attrId, attrName, isFirst = false) {
                    //console.log`createMandatorySection called for attribute ${attrId} (${attrName}), isFirst: ${isFirst}`);
                    // Avoid duplicates - check if section already exists
                    const already = findSectionByAttrId(attrId);
                    if (already) {
                        //console.log`Mandatory section for attribute ${attrId} already exists, skipping creation`);
                        // Make sure it's locked and marked mandatory
                        already.classList.add('mandatory-section');
                        const sel = getAttrSelect(already);
                        if (sel) { sel.disabled = true; sel.style.backgroundColor = '#f8f9fa'; sel.style.cursor = 'not-allowed'; }
                        const label = already.querySelector('label');
                        if (label && !label.querySelector('.mandatory-indicator')) {
                            const indicator = document.createElement('span');
                            indicator.className = 'mandatory-indicator text-warning ms-1';
                            indicator.innerHTML = '<i class="fa fa-lock"></i>';
                            indicator.title = 'Mandatory attribute';
                            label.appendChild(indicator);
                        }
                        // Hide delete button for mandatory sections
                        const delBtn = already.querySelector('.btn-delete-section');
                        if (delBtn) { delBtn.style.display = 'none'; delBtn.disabled = true; }
                        return already;
                    }
                    const addMoreBtn = document.querySelector('button.btn-light.btn-wave.mb-2');
                    if (!addMoreBtn) return;
                    let firstSection = document.getElementById('add-more-item');
                    // If first section doesn't exist, try to find any existing section to use as template
                    if (!firstSection) {
                        const existingSections = document.querySelectorAll('.spec-section');
                        if (existingSections.length > 0) {
                            firstSection = existingSections[0];
                        } else {
                            console.error('Cannot create mandatory section: no template section available');
                            return;
                        }
                    }
                    // Clone the first section
                    const clone = firstSection.cloneNode(true);
                    clone.id = '';
                    clone.classList.add('mandatory-section');
                    clone.dataset.mandatoryAttrId = attrId;
                    // Ensure the cloned section is visible
                    try {
                        if (clone.hasAttribute('hidden')) clone.removeAttribute('hidden');
                        if (clone.style && clone.style.display === 'none') clone.style.display = '';
                    } catch (e) { }
                    // Remove all * marks
                    clone.querySelectorAll('span.text-danger').forEach(function (span) { span.remove(); });
                    // Remove any existing price rows in the clone
                    clone.querySelectorAll('.variant-price-rows').forEach(function (x) { x.innerHTML = ''; });
                    // Remove any Choices.js wrapper markup
                    clone.querySelectorAll('.choices').forEach(function (wrapper) {
                        const sel = wrapper.querySelector('select');
                        if (sel) {
                            wrapper.parentNode.insertBefore(sel, wrapper);
                            try {
                                sel.removeAttribute('hidden');
                                sel.removeAttribute('data-choice');
                                sel.classList.remove('choices__input');
                                sel.tabIndex = 0;
                            } catch (e) { }
                        }
                        wrapper.remove();
                    });
                    // Reset inputs/select values
                    clone.querySelectorAll('select').forEach(function (sel) {
                        try { if (sel._choices) { sel._choices.destroy(); sel._choices = null; } } catch (e) { }
                        if (sel.hasAttribute('multiple')) {
                            sel.innerHTML = '';
                        } else {
                            if (sel.options && sel.options.length) { sel.selectedIndex = 0; }
                        }
                    });
                    clone.querySelectorAll('input').forEach(function (inp) {
                        if (inp.type === 'radio' || inp.type === 'checkbox') {
                            inp.checked = false;
                            if (inp.type === 'radio') {
                                if (inp.id.includes('price-change-yes-')) {
                                    inp.id = 'flexRadioDefault1';
                                } else if (inp.id.includes('price-change-no-')) {
                                    inp.id = 'flexRadioDefault2';
                                }
                                inp.name = 'flexRadioDefault';
                            }
                        }
                        if (inp.type === 'text') { inp.value = ''; }
                    });
                    // Ensure attribute option exists and preselect
                    const attrSelectInClone = (function () {
                        const lab = Array.from(clone.querySelectorAll('label')).find(l => (l.textContent || '').toLowerCase().includes('attribute'));
                        const wrap = lab ? lab.closest('.col-12, .col-xl-6, .col-lg-6, .col-md-6, .col-sm-6, .col-12.mb-1') : null;
                        return wrap ? wrap.querySelector('select') : null;
                    })();
                    if (attrSelectInClone) {
                        let opt = attrSelectInClone.querySelector('option[value="' + String(attrId) + '"]');
                        if (!opt) {
                            try {
                                // Try to fetch name and append
                                const r = await fetch('<?php echo USER_BASEURL; ?>app/api/get_attribute_names.php?ids=' + encodeURIComponent(String(attrId)));
                                const j = await r.json();
                                const name = (j && j.attributes && j.attributes[0] && j.attributes[0].name) ? j.attributes[0].name : String(attrId);
                                opt = document.createElement('option');
                                opt.value = String(attrId);
                                opt.textContent = name;
                                attrSelectInClone.appendChild(opt);
                            } catch (_) { }
                        }
                        attrSelectInClone.value = String(attrId);
                        try { attrSelectInClone.disabled = true; attrSelectInClone.setAttribute('disabled', 'disabled'); } catch (e) { }
                    }
                    // Insert the clone
                    //console.log`Inserting mandatory section for attribute ${attrId} before Add More button`);
                    firstSection.parentNode.insertBefore(clone, addMoreBtn);
                    //console.log`Successfully inserted mandatory section for attribute ${attrId}`);
                    // Add dashed divider after the section
                    const hr = document.createElement('hr');
                    hr.className = 'mt-0';
                    hr.setAttribute('style', 'border-top: 1px dashed #d1d5db; margin: 32px 0;');
                    clone.parentNode.insertBefore(hr, addMoreBtn);
                    // Initialize the section as mandatory
                    delete clone.dataset.specUid;
                    initOneSection(clone, true, attrId);
                    // Re-assert mandatory lock after initialization
                    try {
                        const selLock = getAttrSelect(clone);
                        if (selLock) { selLock.disabled = true; selLock.setAttribute('disabled', 'disabled'); selLock.style.backgroundColor = '#f8f9fa'; selLock.style.cursor = 'not-allowed'; }
                        const delBtn = clone.querySelector('.btn-delete-section');
                        if (delBtn) { delBtn.style.display = 'none'; delBtn.disabled = true; }
                    } catch (e) { }
                    // Populate variants for this attribute
                    const variantsSelect = getVariantsSelect(clone);
                    if (variantsSelect) {
                        await populateVariants(variantsSelect, attrId);
                    }
                    return clone;
                }
                // Function to remove mandatory attributes from dropdown in new sections
                function removeMandatoryAttributesFromDropdown(section) {
                    if (MANDATORY_ATTRIBUTES.length === 0) return;
                    const attrSelect = getAttrSelect(section);
                    if (!attrSelect) return;
                    //console.log'Removing mandatory attributes from dropdown:', MANDATORY_ATTRIBUTES);
                    // Remove options for mandatory attributes
                    MANDATORY_ATTRIBUTES.forEach(mandatoryId => {
                        const option = attrSelect.querySelector(`option[value="${mandatoryId}"]`);
                        if (option) {
                            //console.log`Removing mandatory attribute ${mandatoryId} from dropdown`);
                            option.remove();
                        }
                    });
                }
                // Function to ensure mandatory sections exist (recreate if missing)
                async function ensureMandatorySectionsExist() {
                    if (CREATING_MANDATORY_SECTIONS) return;
                    
                    // If mandatory attributes not loaded yet, try to load them
                    if (MANDATORY_ATTRIBUTES.length === 0) {
                        try {
                            const categoryId = '<?php echo htmlspecialchars($edit_category_id ?? ''); ?>';
                            const subcategoryId = '<?php echo htmlspecialchars($edit_sub_category_id ?? ''); ?>';
                            const storedSubcategory = '<?php echo htmlspecialchars(isset($_SESSION['selected_subcategory_for_mandatory']) ? $_SESSION['selected_subcategory_for_mandatory'] : ''); ?>';
                            const finalSubcategoryId = subcategoryId || storedSubcategory;
                            
                            if (categoryId || finalSubcategoryId) {
                                let apiUrl = '<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?';
                                if (finalSubcategoryId) {
                                    apiUrl += 'sub_category_id=' + encodeURIComponent(finalSubcategoryId);
                                } else {
                                    apiUrl += 'category_id=' + encodeURIComponent(categoryId);
                                }
                                const response = await fetch(apiUrl);
                                const data = await response.json();
                                if (data && data.success && Array.isArray(data.mandatory) && data.mandatory.length > 0) {
                                    MANDATORY_ATTRIBUTES = data.mandatory;
                                } else {
                                    return; // No mandatory attributes for this category
                                }
                            } else {
                                return; // No category/subcategory available
                            }
                        } catch (e) {
                            console.error('Error loading mandatory attributes:', e);
                            return;
                        }
                    }
                    
                    // Check which mandatory sections are missing
                    const existingMandatorySections = new Set();
                    document.querySelectorAll('.spec-section.mandatory-section').forEach(section => {
                        const attrSel = getAttrSelect(section);
                        if (attrSel && attrSel.value) {
                            existingMandatorySections.add(parseInt(attrSel.value, 10));
                        }
                    });
                    
                    // Find missing mandatory attributes
                    const missingMandatory = MANDATORY_ATTRIBUTES.filter(id => !existingMandatorySections.has(parseInt(id, 10)));
                    
                    if (missingMandatory.length > 0) {
                        console.log('Missing mandatory sections detected, recreating:', missingMandatory);
                        // Get attribute names for missing mandatory attributes
                        const attrIds = missingMandatory.join(',');
                        try {
                            const attrResponse = await fetch('<?php echo USER_BASEURL; ?>app/api/get_attribute_names.php?ids=' + encodeURIComponent(attrIds));
                            const attrData = await attrResponse.json();
                            if (attrData.success && Array.isArray(attrData.attributes)) {
                                for (const attr of attrData.attributes) {
                                    await createMandatorySection(attr.id, attr.name, false);
                                }
                            }
                        } catch (e) {
                            console.error('Error recreating mandatory sections:', e);
                        }
                    }
                }
                // Helper function to get attribute name by ID
                function getAttributeName(attributeId) {
                    const attributeNames = {
                        1: 'Color',
                        2: 'Size',
                        3: 'Material',
                        4: 'Shape',
                        5: 'Warranty',
                        6: 'Base Metal',
                        7: 'Net Quantity (N)',
                        8: 'Net Weight (gms)',
                        9: 'Occasion',
                        10: 'Plating',
                        11: 'Sizing',
                        12: 'Stone Type',
                        13: 'Trend',
                        14: 'Group ID',
                        15: 'Type',
                        16: 'sanjay'
                    };
                    return attributeNames[attributeId] || `Attribute ${attributeId}`;
                }
                // Function to ensure attribute name is available in dropdown
                async function ensureAttributeInDropdown(attrSelect, attributeId) {
                    if (!attrSelect || !attributeId) return;
                    
                    const attrIdInt = parseInt(attributeId, 10);
                    if (isNaN(attrIdInt)) {
                        console.error('Invalid attribute ID:', attributeId);
                        return;
                    }
                    
                    // Check if the attribute option already exists
                    const existingOption = attrSelect.querySelector(`option[value="${attrIdInt}"]`);
                    if (existingOption) {
                        //console.log(`Attribute ${attributeId} already exists in dropdown`);
                        return;
                    }
                    
                    // For localStorage loading, we should allow any attribute to be added
                    // Don't restrict to optional/mandatory only when loading from localStorage
                    const isOptional = OPTIONAL_ATTRIBUTES.length > 0 && OPTIONAL_ATTRIBUTES.includes(attrIdInt);
                    const isMandatory = MANDATORY_ATTRIBUTES.includes(attrIdInt);
                    const isFromLocalStorage = window.useLocalStorageData === true;
                    
                    // Allow if it's optional, mandatory, or being loaded from localStorage
                    if (!isOptional && !isMandatory && !isFromLocalStorage) {
                        console.warn(`Attribute ${attributeId} is not in optional/mandatory attributes list`);
                        // Still try to add it for localStorage restoration
                    }
                    
                    try {
                        //console.log(`Fetching attribute name for ID ${attributeId}`);
                        // Fetch attribute name
                        const resp = await fetch('<?php echo USER_BASEURL; ?>app/api/get_attribute_names.php?ids=' + encodeURIComponent(attrIdInt));
                        const data = await resp.json();
                        //console.log('Attribute API response:', data);
                        if (data && data.attributes && data.attributes.length > 0) {
                            const attribute = data.attributes[0];
                            // Create and add the option
                            const option = document.createElement('option');
                            option.value = String(attrIdInt);
                            option.textContent = attribute.name;
                            attrSelect.appendChild(option);
                            //console.log(`Added attribute ${attributeId} (${attribute.name}) to dropdown`);
                            
                            // If Choices.js is initialized, update it
                            if (attrSelect._choices) {
                                try {
                                    attrSelect._choices.setChoices([{ value: String(attrIdInt), label: attribute.name }], 'value', 'label', false);
                                } catch (e) {
                                    console.error('Error updating Choices.js:', e);
                                }
                            }
                        } else {
                            console.error(`No attribute found for ID ${attributeId}`);
                        }
                    } catch (e) {
                        console.error('Error ensuring attribute in dropdown:', e);
                    }
                }
                // Function to handle subcategory changes in edit mode
                async function handleSubcategoryChangeInEditMode() {
                    const subcategorySelect = document.getElementById('Light-SubCategory');
                    if (!subcategorySelect) return;
                    subcategorySelect.addEventListener('change', async function () {
                        const newSubcategoryId = this.value;
                        if (!newSubcategoryId) return;
                        //console.log'Subcategory changed in edit mode to:', newSubcategoryId);
                        try {
                            // Fetch optional attributes for the new subcategory
                            const optionalResp = await fetch('<?php echo USER_BASEURL; ?>app/api/get_category_optional.php?sub_category_id=' + encodeURIComponent(newSubcategoryId));
                            const optionalData = await optionalResp.json();
                            if (optionalData && optionalData.optional && Array.isArray(optionalData.optional)) {
                                OPTIONAL_ATTRIBUTES = optionalData.optional.map(id => parseInt(id, 10));
                                //console.log'Updated optional attributes for subcategory:', OPTIONAL_ATTRIBUTES);
                                // Update all attribute dropdowns to only show optional attributes
                                document.querySelectorAll('.spec-section').forEach(section => {
                                    const attrSelect = getAttrSelect(section);
                                    if (!attrSelect) return;
                                    // Remove all options that are not in optional attributes list
                                    const optionalSet = new Set(OPTIONAL_ATTRIBUTES);
                                    const mandatorySet = new Set(MANDATORY_ATTRIBUTES);
                                    Array.from(attrSelect.options).forEach(option => {
                                        if (option.value && option.value !== '') {
                                            const optionValue = parseInt(option.value, 10);
                                            // Keep if it's optional, mandatory, or currently selected
                                            const isOptional = optionalSet.has(optionValue);
                                            const isMandatory = mandatorySet.has(optionValue);
                                            const isSelected = attrSelect.value === option.value;
                                            if (!isOptional && !isMandatory && !isSelected) {
                                                option.remove();
                                            }
                                        }
                                    });
                                });
                            }
                            // Fetch mandatory attributes for the new subcategory
                            const resp = await fetch('<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?sub_category_id=' + encodeURIComponent(newSubcategoryId));
                            const data = await resp.json();
                            if (data && data.mandatory && data.mandatory.length > 0) {
                                //console.log'New mandatory attributes for subcategory:', data.mandatory);
                                // Update MANDATORY_ATTRIBUTES
                                MANDATORY_ATTRIBUTES = data.mandatory;
                                // Get existing attribute IDs
                                const existingAttrIds = new Set();
                                document.querySelectorAll('.spec-section').forEach(section => {
                                    const attrSel = getAttrSelect(section);
                                    if (attrSel && attrSel.value) {
                                        existingAttrIds.add(parseInt(attrSel.value, 10));
                                    }
                                });
                                // Create missing mandatory sections
                                for (const mandatoryId of data.mandatory) {
                                    if (!existingAttrIds.has(parseInt(mandatoryId, 10))) {
                                        //console.log`Creating new mandatory section for attribute ${mandatoryId}`);
                                        await createMandatorySection(mandatoryId, null, false);
                                    }
                                }
                                // Update all sections to reflect new mandatory attributes
                                document.querySelectorAll('.spec-section').forEach(section => {
                                    const attrSel = getAttrSelect(section);
                                    const attrId = attrSel ? parseInt(attrSel.value, 10) : 0;
                                    if (attrId && data.mandatory.includes(attrId.toString())) {
                                        // This is a mandatory attribute
                                        section.classList.add('mandatory-section');
                                        attrSel.disabled = true;
                                        attrSel.style.backgroundColor = '#f8f9fa';
                                        attrSel.style.cursor = 'not-allowed';
                                        // Add lock icon
                                        const label = section.querySelector('label');
                                        if (label && !label.querySelector('.mandatory-indicator')) {
                                            const indicator = document.createElement('span');
                                            indicator.className = 'mandatory-indicator text-warning ms-1';
                                            indicator.innerHTML = '<i class="fa fa-lock"></i>';
                                            indicator.title = 'Mandatory attribute';
                                            label.appendChild(indicator);
                                        }
                                        // Hide delete button
                                        const delBtn = section.querySelector('.btn-delete-section');
                                        if (delBtn) {
                                            delBtn.style.display = 'none';
                                            delBtn.disabled = true;
                                        }
                                    }
                                });
                            }
                        } catch (e) {
                            console.error('Error handling subcategory change:', e);
                        }
                    });
                }
                // Function to remove already selected attributes from all other section dropdowns
                function removeSelectedAttributesFromOtherDropdowns() {
                    const allSections = document.querySelectorAll('.spec-section');
                    const selectedAttributes = new Set();
                    // Collect all currently selected attribute IDs
                    allSections.forEach(section => {
                        const attrSelect = getAttrSelect(section);
                        if (attrSelect && attrSelect.value && attrSelect.value !== '') {
                            selectedAttributes.add(parseInt(attrSelect.value));
                        }
                    });
                    //console.log'Selected attributes to exclude:', Array.from(selectedAttributes));
                    // Process each section dropdown
                    allSections.forEach(section => {
                        const attrSelect = getAttrSelect(section);
                        if (!attrSelect) return;
                        const currentValue = attrSelect.value;
                        // Remove selected attributes from this dropdown (except the current selection)
                        selectedAttributes.forEach(selectedId => {
                            const option = attrSelect.querySelector(`option[value="${selectedId}"]`);
                            if (option && currentValue !== selectedId.toString()) {
                                //console.log`Removing selected attribute ${selectedId} from dropdown`);
                                option.remove();
                            }
                        });
                        // If this section has no selection, we need to restore all available options
                        if (!currentValue || currentValue === '') {
                            restoreAvailableAttributesToDropdown(attrSelect);
                        }
                    });
                }
                // Function to restore available attributes to a dropdown
                function restoreAvailableAttributesToDropdown(attrSelect) {
                    // Get all available attributes from the original template
                    const originalSelect = document.querySelector('#add-more-item select');
                    if (!originalSelect) return;
                    // Get all options from the original select
                    const originalOptions = Array.from(originalSelect.querySelectorAll('option'));
                    // Get currently selected attributes in other sections
                    const allSections = document.querySelectorAll('.spec-section');
                    const selectedAttributes = new Set();
                    allSections.forEach(section => {
                        const sectionSelect = getAttrSelect(section);
                        if (sectionSelect && sectionSelect.value && sectionSelect.value !== '') {
                            selectedAttributes.add(parseInt(sectionSelect.value));
                        }
                    });
                    // Get mandatory attributes to exclude
                    const mandatoryAttributes = new Set(MANDATORY_ATTRIBUTES);
                    // Get optional attributes set for filtering
                    const optionalAttributesSet = new Set(OPTIONAL_ATTRIBUTES);
                    // Restore options that are not selected, not mandatory, and are in optional attributes list
                    originalOptions.forEach(originalOption => {
                        const optionValue = parseInt(originalOption.value);
                        const isSelected = selectedAttributes.has(optionValue);
                        const isMandatory = mandatoryAttributes.has(optionValue);
                        // Only restore if it's in optional attributes list (must have optional attributes defined)
                        const isOptional = OPTIONAL_ATTRIBUTES.length > 0 && optionalAttributesSet.has(optionValue);
                        if (!isSelected && !isMandatory && isOptional && optionValue > 0) {
                            // Check if option doesn't already exist
                            const existingOption = attrSelect.querySelector(`option[value="${optionValue}"]`);
                            if (!existingOption) {
                                //console.log`Restoring attribute ${optionValue} to dropdown`);
                                const newOption = originalOption.cloneNode(true);
                                attrSelect.appendChild(newOption);
                            }
                        }
                    });
                }
                // Function to clear session category after successful submission
                function clearSessionCategory() {
                    // This will be called after successful form submission
                    fetch('<?php echo USER_BASEURL; ?>app/api/clear_category_session.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'clear=1'
                    }).catch(e => console.log('Session clear failed:', e));
                }
                function attachSpecsAndSubmit(form) {
                    // Mark that a submit attempt occurred to enable showing validation styles
                    window.__specSubmitAttempted__ = true;
                    // Enforce mandatory MRP and Selling Price when price-change is Yes
                    try {
                        const sections = document.querySelectorAll('.spec-section');
                        let firstInvalid = null;
                        sections.forEach(section => {
                            const radios = getYesNoRadios(section);
                            if (radios && radios.yes && radios.yes.checked) {
                                // For each selected variant, ensure MRP and SP are provided and valid
                                const priceInputs = section.querySelectorAll('input[data-variant-id][data-field]');
                                const container = section.querySelector('.variant-price-rows');
                                const fbMap = new Map();
                                if (container) {
                                    container.querySelectorAll('.invalid-feedback').forEach(fb => {
                                        fbMap.set(fb.closest('.col-12') || fb.parentElement, fb);
                                    });
                                }
                                // Build map by variant id
                                const byVariant = {};
                                priceInputs.forEach(inp => {
                                    const vid = inp.dataset.variantId;
                                    const field = inp.dataset.field;
                                    if (!byVariant[vid]) byVariant[vid] = {};
                                    byVariant[vid][field] = inp;
                                });
                                Object.values(byVariant).forEach(({ mrp, selling_price }) => {
                                    const mrpVal = mrp ? parseFloat(String(mrp.value || '').replace(/[^0-9.]/g, '')) : NaN;
                                    const spVal = selling_price ? parseFloat(String(selling_price.value || '').replace(/[^0-9.]/g, '')) : NaN;
                                    // Required checks
                                    const mrpMissing = !mrp || String(mrp.value || '').trim() === '' || isNaN(mrpVal);
                                    const spMissing = !selling_price || String(selling_price.value || '').trim() === '' || isNaN(spVal);
                                    if (mrpMissing) { mrp?.classList.add('is-invalid'); if (!firstInvalid) firstInvalid = mrp; }
                                    if (spMissing) { selling_price?.classList.add('is-invalid'); if (!firstInvalid) firstInvalid = selling_price; }
                                    // Relation check
                                    if (!mrpMissing && !spMissing && spVal > mrpVal) {
                                        selling_price.classList.add('is-invalid');
                                        if (!firstInvalid) firstInvalid = selling_price;
                                    }
                                });
                            }
                        });
                        if (firstInvalid) {
                            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            firstInvalid.focus();
                            alert('Please fill MRP and Selling Price for all selected variations (and ensure Selling Price ≤ MRP).');
                            return false;
                        }
                    } catch (e) { }
                    const spec = collectSpecifications();
                    //console.log('Collected specifications for submission:', spec);
                    // Ensure we always have a valid array
                    const validSpec = Array.isArray(spec) ? spec : [];
                    const specJson = JSON.stringify(validSpec);
                    //console.log('Specifications JSON:', specJson);
                    // Set the hidden field value
                    const hiddenField = document.getElementById('specifications_json');
                    if (hiddenField) {
                        hiddenField.value = specJson;
                    } else {
                        console.error('specifications_json hidden field not found!');
                        return false;
                    }
                    // Mark that form is being submitted (for localStorage clearing after successful save)
                    const key = getLocalStorageKey();
                    try {
                        sessionStorage.setItem('product_form_submitted_' + key, '1');
                    } catch (e) {
                        console.error('Error setting sessionStorage:', e);
                    }
                    // DO NOT clear localStorage before form submission
                    // If validation fails on server, we need to preserve user changes
                    // localStorage will be cleared by server-side code ONLY after successful save
                    console.log('Form submitted - localStorage will be preserved until successful save');
                    return true;
                }
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', async function () {
                    // Check if we need to clear localStorage after successful save
                    <?php if (isset($_SESSION['clear_localStorage_key']) && !empty($_SESSION['clear_localStorage_key'])): ?>
                    try {
                        const keyToClear = '<?php echo htmlspecialchars($_SESSION['clear_localStorage_key']); ?>';
                        localStorage.removeItem(keyToClear);
                        console.log('✓ Cleared localStorage after successful save:', keyToClear);
                        // Clear the session flag
                        <?php unset($_SESSION['clear_localStorage_key']); ?>
                    } catch (e) {
                        console.error('Error clearing localStorage after successful save:', e);
                    }
                    <?php endif; ?>
                    
                    setLoadingState(true);
                    
                    // Initialize the default section using the spec-block wrapper
                    const firstSection = document.getElementById('add-more-item') || document.querySelector('.spec-block');
                    if (firstSection) {
                        initOneSection(firstSection);
                        const attrSel = getAttrSelect(firstSection);
                        const varSel = getVariantsSelect(firstSection);
                        if (attrSel && attrSel.value) { populateVariants(varSel, attrSel.value, firstSection); }
                    }
                    
                        <?php if (!empty($editing_product_id)): ?>
                        // ===== EDIT MODE: Simplified Flow =====
                        // PRIORITY: localStorage FIRST, then database/session (only if localStorage is empty)
                        
                        // Step 1: Check localStorage FIRST (preserves user changes on refresh)
                        const localStorageKey = getLocalStorageKey();
                        let existingSpecs = null;
                        let specsFromLocalStorage = false;
                        
                        // Try to load from localStorage first
                        try {
                            const stored = localStorage.getItem(localStorageKey);
                            if (stored) {
                                const data = JSON.parse(stored);
                                if (data && data.specifications && Array.isArray(data.specifications) && data.specifications.length > 0) {
                                    existingSpecs = data.specifications;
                                    specsFromLocalStorage = true;
                                    console.log('✓ Loaded specifications from localStorage (preserving user changes):', existingSpecs);
                                    
                                    // Initialize price data from localStorage
                                    if (!window.existingPriceData) {
                                        window.existingPriceData = {};
                                    }
                                    existingSpecs.forEach(spec => {
                                        if (spec.variants && Array.isArray(spec.variants)) {
                                            spec.variants.forEach(variant => {
                                                if (variant.id && (variant.mrp || variant.selling_price)) {
                                                    window.existingPriceData[variant.id] = {
                                                        mrp: variant.mrp || null,
                                                        selling_price: variant.selling_price || null
                                                    };
                                                }
                                            });
                                        }
                                    });
                                }
                            }
                        } catch (e) {
                            console.error('Error loading from localStorage:', e);
                        }
                        
                        // Step 2: Only load from database/session if localStorage is empty (first visit)
                        if (!specsFromLocalStorage) {
                            <?php 
                            $session_specs_key = 'existing_specifications_' . $editing_product_id;
                            $session_specs_key_admin = 'existing_specifications_admin_' . $editing_product_id;
                            // Check admin key first, then regular key
                            $specs_data = isset($_SESSION[$session_specs_key_admin]) ? $_SESSION[$session_specs_key_admin] : (isset($_SESSION[$session_specs_key]) ? $_SESSION[$session_specs_key] : null);
                            if (!empty($specs_data)) {
                                echo "existingSpecs = " . json_encode($specs_data) . ";";
                            } else {
                                echo "existingSpecs = null;";
                            }
                            ?>
                            
                            // If we have specs from database, save them to localStorage (first visit only)
                            if (existingSpecs && Array.isArray(existingSpecs) && existingSpecs.length > 0) {
                                try {
                                    const dataToStore = {
                                        specifications: existingSpecs,
                                        timestamp: Date.now(),
                                        productId: '<?php echo htmlspecialchars($editing_product_id); ?>'
                                    };
                                    localStorage.setItem(localStorageKey, JSON.stringify(dataToStore));
                                    console.log('✓ Loaded from database (FIRST VISIT) and saved to localStorage:', localStorageKey);
                                    
                                    // Initialize price data from specs
                                    if (!window.existingPriceData) {
                                        window.existingPriceData = {};
                                    }
                                    existingSpecs.forEach(spec => {
                                        if (spec.variants && Array.isArray(spec.variants)) {
                                            spec.variants.forEach(variant => {
                                                if (variant.id) {
                                                    window.existingPriceData[variant.id] = {
                                                        mrp: variant.mrp || null,
                                                        selling_price: variant.selling_price || null
                                                    };
                                                }
                                            });
                                        }
                                    });
                                } catch (e) {
                                    console.error('Error saving to localStorage:', e);
                                }
                            } else {
                                console.log('No specifications found in database for product <?php echo htmlspecialchars($editing_product_id); ?>');
                            }
                        }
                        
                        // Step 3: Create mandatory sections first (if needed) - pass existingSpecs to populate variants
                        const categoryId = '<?php echo htmlspecialchars($edit_category_id ?? ''); ?>';
                        const subcategoryId = '<?php echo htmlspecialchars($edit_sub_category_id ?? ''); ?>';
                        const finalSubcategoryId = subcategoryId || '<?php echo htmlspecialchars(isset($_SESSION['selected_subcategory_for_mandatory']) ? $_SESSION['selected_subcategory_for_mandatory'] : ''); ?>';
                        
                        if (finalSubcategoryId || categoryId) {
                            await createMandatorySections(finalSubcategoryId || categoryId, existingSpecs);
                        }
                        
                        // Step 4: Mark that we're using localStorage data (if available)
                        // IMPORTANT: Always prioritize localStorage data to preserve newly added optional attributes
                        if (specsFromLocalStorage && existingSpecs && Array.isArray(existingSpecs) && existingSpecs.length > 0) {
                            window.localStorageSpecs = existingSpecs;
                            window.useLocalStorageData = true;
                            console.log('✓ Using localStorage data (user changes preserved, including newly added optional attributes)');
                        } else if (existingSpecs && Array.isArray(existingSpecs) && existingSpecs.length > 0) {
                            // Even if not from localStorage, still mark it for consistency
                            window.localStorageSpecs = existingSpecs;
                            console.log('✓ Using database/session data (first visit)');
                        }
                        
                        // Step 5: Load existing specs into UI
                        // IMPORTANT: Separate mandatory and optional attributes to prevent conflicts
                        if (existingSpecs && Array.isArray(existingSpecs) && existingSpecs.length > 0) {
                            if (window.specificationsLoaded) {
                                markSpecsInitialized();
                                return;
                            }
                            window.specificationsLoaded = true;
                            
                            // Separate mandatory and optional attributes
                            const mandatorySpecs = [];
                            const optionalSpecs = [];
                            
                            existingSpecs.forEach(spec => {
                                if (!spec || !spec.attribute_id) return;
                                const attributeId = parseInt(spec.attribute_id, 10);
                                if (isNaN(attributeId)) return;
                                
                                if (MANDATORY_ATTRIBUTES.includes(attributeId)) {
                                    mandatorySpecs.push(spec);
                                } else {
                                    optionalSpecs.push(spec);
                                }
                            });
                            
                            console.log(`Separated specs: ${mandatorySpecs.length} mandatory, ${optionalSpecs.length} optional`);
                            
                            // Load all specifications sequentially - MANDATORY FIRST, then OPTIONAL
                            const loadAllSpecs = async () => {
                                // If optional attributes exist, show loader immediately after mandatory sections finish
                                const hasOptionalAttrs = optionalSpecs.length > 0;
                                
                                // First, load mandatory attributes (they should already be in mandatory sections)
                                for (let i = 0; i < mandatorySpecs.length; i++) {
                                    const spec = mandatorySpecs[i];
                                    if (!spec || !spec.attribute_id) continue;
                                    
                                    const attributeId = parseInt(spec.attribute_id, 10);
                                    if (isNaN(attributeId)) continue;
                                    
                                    const isMandatory = true; // Always true for mandatorySpecs
                                    
                                    // Check if a section already exists for this attribute (e.g., from mandatory section creation)
                                    const existingSectionForAttr = findSectionByAttrId(attributeId);
                                    
                                    if (i === 0 && !existingSectionForAttr) {
                                        // Use first section only if no section exists for this attribute
                                        const attrSel = getAttrSelect(firstSection);
                                        const varSel = getVariantsSelect(firstSection);
                                        if (attrSel && attributeId) {
                                            await ensureAttributeInDropdown(attrSel, attributeId);
                                            attrSel.value = attributeId;
                                            if (attrSel._choices) {
                                                attrSel._choices.setChoiceByValue(attributeId);
                                            }
                                            // Wait for variants to be populated
                                            await populateVariants(varSel, attributeId, firstSection);
                                            
                                            // Wait for Choices.js to initialize if it exists
                                            if (varSel._choices) {
                                                // Wait a bit more for Choices.js to be fully ready
                                                await new Promise(resolve => setTimeout(resolve, 300));
                                            } else {
                                                // If Choices.js not initialized, wait a bit and try to initialize it
                                                await new Promise(resolve => setTimeout(resolve, 200));
                                                if (window.Choices && !varSel._choices) {
                                                    try {
                                                        varSel._choices = new Choices(varSel, { removeItemButton: true, searchEnabled: true, allowHTML: true });
                                                        await new Promise(resolve => setTimeout(resolve, 200));
                                                    } catch (e) {
                                                        console.error('Error initializing Choices.js:', e);
                                                    }
                                                }
                                            }
                                            
                                            if (spec.variants && Array.isArray(spec.variants) && spec.variants.length > 0) {
                                                const attributeType = firstSection.dataset.attributeType || 'multi';
                                                
                                                if (attributeType === 'text') {
                                                    // For text attributes, only add variants that are text variants
                                                    // Skip old single/multi variants (they don't have is_text_variant flag)
                                                    for (const variant of spec.variants) {
                                                        // Only process if it's a text variant
                                                        if (variant.is_text_variant === true) {
                                                            const variantName = variant.name;
                                                            const variantId = variant.id;
                                                            if (variantName) {
                                                                await addTextVariant(firstSection, variantName, variantId);
                                                                await new Promise(r => setTimeout(r, 50));
                                                            }
                                                        } else {
                                                            // Old single/multi variant - skip it
                                                            console.log('Skipping non-text variant for text attribute:', variant.id);
                                                        }
                                                    }
                                                } else {
                                                    // Set selected variants
                                                    const variantIds = spec.variants.map(v => String(v.id));
                                                    console.log('Setting variants for first section:', variantIds);
                                                    
                                                    // For single-select, only use the first variant
                                                    const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                        ? [spec.variants[0].id] 
                                                        : variantIds;
                                                    
                                                    if (varSel._choices) {
                                                        try {
                                                            varSel._choices.setChoiceByValue(variantsToSet);
                                                            console.log('Variants set using Choices.js');
                                                        } catch (e) {
                                                            console.error('Error setting Choices.js values:', e);
                                                            // Fallback to native select
                                                            variantsToSet.forEach(vid => {
                                                                const option = varSel.querySelector(`option[value="${vid}"]`);
                                                                if (option) option.selected = true;
                                                            });
                                                        }
                                                    } else {
                                                        // Fallback to native select
                                                        variantsToSet.forEach(vid => {
                                                            const option = varSel.querySelector(`option[value="${vid}"]`);
                                                            if (option) option.selected = true;
                                                        });
                                                    }
                                                    
                                                    // Trigger change event to update UI
                                                    varSel.dispatchEvent(new Event('change'));
                                                }
                                                
                                                // Handle price data
                                                if (spec.variants.some(v => v.mrp || v.selling_price)) {
                                                    const radios = getYesNoRadios(firstSection);
                                                    if (radios.yes) {
                                                        if (radios.no) radios.no.checked = false;
                                                        radios.yes.checked = true;
                                                        radios.yes.dispatchEvent(new Event('change'));
                                                        // Wait for price rows to render
                                                        await new Promise(resolve => setTimeout(resolve, 300));
                                                        spec.variants.forEach(variant => {
                                                            if (variant.mrp || variant.selling_price) {
                                                                const mrpInput = firstSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                const spInput = firstSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                if (mrpInput && variant.mrp) mrpInput.value = variant.mrp;
                                                                if (spInput && variant.selling_price) spInput.value = variant.selling_price;
                                                            }
                                                        });
                                                    }
                                                }
                                                
                                                // Update preview
                                                try { renderPreview(); } catch (e) { }
                                            }
                                        }
                                    } else if (existingSectionForAttr) {
                                        // Use existing section for this attribute (e.g., mandatory section already created)
                                        const attrSel = getAttrSelect(existingSectionForAttr);
                                        const varSel = getVariantsSelect(existingSectionForAttr);
                                        if (attrSel && varSel && attributeId) {
                                            // Variants should already be populated, just set the selected ones
                                            if (spec.variants && Array.isArray(spec.variants) && spec.variants.length > 0) {
                                                const attributeType = existingSectionForAttr.dataset.attributeType || 'multi';
                                                
                                                if (attributeType === 'text') {
                                                    // For text attributes, only add variants that are text variants
                                                    // Skip old single/multi variants (they don't have is_text_variant flag)
                                                    for (const variant of spec.variants) {
                                                        // Only process if it's a text variant
                                                        if (variant.is_text_variant === true) {
                                                            const variantName = variant.name;
                                                            const variantId = variant.id;
                                                            if (variantName) {
                                                                await addTextVariant(existingSectionForAttr, variantName, variantId);
                                                                await new Promise(r => setTimeout(r, 50));
                                                            }
                                                        } else {
                                                            // Old single/multi variant - skip it
                                                            console.log('Skipping non-text variant for text attribute:', variant.id);
                                                        }
                                                    }
                                                } else {
                                                    // Wait for Choices.js to be ready
                                                    if (varSel._choices) {
                                                        await new Promise(resolve => setTimeout(resolve, 200));
                                                    } else {
                                                        await new Promise(resolve => setTimeout(resolve, 200));
                                                        if (window.Choices && !varSel._choices) {
                                                            try {
                                                                varSel._choices = new Choices(varSel, { removeItemButton: true, searchEnabled: true, allowHTML: true });
                                                                await new Promise(resolve => setTimeout(resolve, 200));
                                                            } catch (e) {
                                                                console.error('Error initializing Choices.js for existing section:', e);
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Set selected variants
                                                    const variantIds = spec.variants.map(v => String(v.id));
                                                    console.log('Setting variants for existing section:', variantIds);
                                                    
                                                    // For single-select, only use the first variant
                                                    const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                        ? [spec.variants[0].id] 
                                                        : variantIds;
                                                    
                                                    if (varSel._choices) {
                                                        try {
                                                            varSel._choices.setChoiceByValue(variantsToSet);
                                                            console.log('Variants set using Choices.js for existing section');
                                                        } catch (e) {
                                                            console.error('Error setting Choices.js values for existing section:', e);
                                                            // Fallback to native select
                                                            variantsToSet.forEach(vid => {
                                                                const option = varSel.querySelector(`option[value="${vid}"]`);
                                                                if (option) option.selected = true;
                                                            });
                                                        }
                                                    } else {
                                                        // Fallback to native select
                                                        variantsToSet.forEach(vid => {
                                                            const option = varSel.querySelector(`option[value="${vid}"]`);
                                                            if (option) option.selected = true;
                                                        });
                                                    }
                                                    
                                                    // Trigger change event
                                                    varSel.dispatchEvent(new Event('change'));
                                                }
                                                
                                                // Handle price data if it exists
                                                if (spec.variants.some(v => v.mrp || v.selling_price)) {
                                                    const radios = getYesNoRadios(existingSectionForAttr);
                                                    if (radios.yes) {
                                                        if (radios.no) radios.no.checked = false;
                                                        radios.yes.checked = true;
                                                        radios.yes.dispatchEvent(new Event('change'));
                                                        // Wait for price rows to render
                                                        await new Promise(resolve => setTimeout(resolve, 300));
                                                        spec.variants.forEach(variant => {
                                                            if (variant.mrp || variant.selling_price) {
                                                                const mrpInput = existingSectionForAttr.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                const spInput = existingSectionForAttr.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                if (mrpInput && variant.mrp) mrpInput.value = variant.mrp;
                                                                if (spInput && variant.selling_price) spInput.value = variant.selling_price;
                                                            }
                                                        });
                                                    }
                                                }
                                                
                                                // Update preview
                                                try { renderPreview(); } catch (e) { }
                                            }
                                        }
                                    } else {
                                        // Create new section
                                        let newSection;
                                        if (isMandatory) {
                                            newSection = await createMandatorySection(attributeId, null, false);
                                            // After creating mandatory section, populate variants if we have them
                                            if (newSection && spec.variants && Array.isArray(spec.variants) && spec.variants.length > 0) {
                                                const varSel = getVariantsSelect(newSection);
                                                if (varSel) {
                                                    const attributeType = newSection.dataset.attributeType || 'multi';
                                                    
                                                    if (attributeType === 'text') {
                                                        // For text attributes, only add variants that are text variants
                                                        // Skip old single/multi variants (they don't have is_text_variant flag)
                                                        for (const variant of spec.variants) {
                                                            // Only process if it's a text variant
                                                            if (variant.is_text_variant === true) {
                                                                const variantName = variant.name;
                                                                const variantId = variant.id;
                                                                if (variantName) {
                                                                    await addTextVariant(newSection, variantName, variantId);
                                                                    await new Promise(r => setTimeout(r, 50));
                                                                }
                                                            } else {
                                                                // Old single/multi variant - skip it
                                                                console.log('Skipping non-text variant for text attribute:', variant.id);
                                                            }
                                                        }
                                                    } else {
                                                        // Wait for variants to be populated (createMandatorySection already calls populateVariants)
                                                        await new Promise(resolve => setTimeout(resolve, 300));
                                                        
                                                        // Wait for Choices.js to initialize if it exists
                                                        if (varSel._choices) {
                                                            await new Promise(resolve => setTimeout(resolve, 200));
                                                        } else if (window.Choices && !varSel._choices) {
                                                            try {
                                                                varSel._choices = new Choices(varSel, { removeItemButton: true, searchEnabled: true, allowHTML: true });
                                                                await new Promise(resolve => setTimeout(resolve, 200));
                                                            } catch (e) {
                                                                console.error('Error initializing Choices.js for mandatory section:', e);
                                                            }
                                                        }
                                                        
                                                        // Set selected variants
                                                        const variantIds = spec.variants.map(v => String(v.id));
                                                        console.log('Setting variants for mandatory section:', variantIds);
                                                        
                                                        // For single-select, only use the first variant
                                                        const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                            ? [spec.variants[0].id] 
                                                            : variantIds;
                                                        
                                                        if (varSel._choices) {
                                                            try {
                                                                varSel._choices.setChoiceByValue(variantsToSet);
                                                                console.log('Variants set using Choices.js for mandatory section');
                                                            } catch (e) {
                                                                console.error('Error setting Choices.js values for mandatory section:', e);
                                                                // Fallback to native select
                                                                variantsToSet.forEach(vid => {
                                                                    const option = varSel.querySelector(`option[value="${vid}"]`);
                                                                    if (option) option.selected = true;
                                                                });
                                                            }
                                                        } else {
                                                            // Fallback to native select
                                                            variantsToSet.forEach(vid => {
                                                                const option = varSel.querySelector(`option[value="${vid}"]`);
                                                                if (option) option.selected = true;
                                                            });
                                                        }
                                                        
                                                        // Trigger change event
                                                        varSel.dispatchEvent(new Event('change'));
                                                    }
                                                    
                                                    // Handle price data if it exists
                                                    if (spec.variants.some(v => v.mrp || v.selling_price)) {
                                                        const radios = getYesNoRadios(newSection);
                                                        if (radios.yes) {
                                                            if (radios.no) radios.no.checked = false;
                                                            radios.yes.checked = true;
                                                            radios.yes.dispatchEvent(new Event('change'));
                                                            // Wait for price rows to render
                                                            await new Promise(resolve => setTimeout(resolve, 300));
                                                            spec.variants.forEach(variant => {
                                                                if (variant.mrp || variant.selling_price) {
                                                                    const mrpInput = newSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                    const spInput = newSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                    if (mrpInput && variant.mrp) mrpInput.value = variant.mrp;
                                                                    if (spInput && variant.selling_price) spInput.value = variant.selling_price;
                                                                }
                                                            });
                                                        }
                                                    }
                                                    
                                                    // Update preview
                                                    try { renderPreview(); } catch (e) { }
                                                }
                                            }
                                        } else {
                                            const addMoreBtn = document.querySelector('button.btn-light.btn-wave.mb-2');
                                            if (addMoreBtn) {
                                                addMoreBtn.click();
                                                const waitForNew = () => new Promise((resolve) => {
                                                    let n = 0;
                                                    const max = 100;
                                                    const tick = () => {
                                                        n++;
                                                        const sections = document.querySelectorAll('.spec-section');
                                                        if (sections.length > i) return resolve(sections[i]);
                                                        if (n >= max) return resolve(null);
                                                        setTimeout(tick, 50);
                                                    };
                                                    tick();
                                                });
                                                newSection = await waitForNew();
                                            }
                                        }
                                        
                                        if (newSection) {
                                            const attrSel = getAttrSelect(newSection);
                                            const varSel = getVariantsSelect(newSection);
                                            if (attrSel && attributeId) {
                                                await ensureAttributeInDropdown(attrSel, attributeId);
                                                attrSel.value = attributeId;
                                                if (attrSel._choices) {
                                                    attrSel._choices.setChoiceByValue(attributeId);
                                                }
                                                // Wait for variants to be populated
                                                await populateVariants(varSel, attributeId, newSection);
                                                
                                                // Wait for Choices.js to initialize if it exists
                                                if (varSel._choices) {
                                                    // Wait a bit more for Choices.js to be fully ready
                                                    await new Promise(resolve => setTimeout(resolve, 300));
                                                } else {
                                                    // If Choices.js not initialized, wait a bit and try to initialize it
                                                    await new Promise(resolve => setTimeout(resolve, 200));
                                                    if (window.Choices && !varSel._choices) {
                                                        try {
                                                            varSel._choices = new Choices(varSel, { removeItemButton: true, searchEnabled: true, allowHTML: true });
                                                            await new Promise(resolve => setTimeout(resolve, 200));
                                                        } catch (e) {
                                                            console.error('Error initializing Choices.js:', e);
                                                        }
                                                    }
                                                }
                                                
                                                if (spec.variants && Array.isArray(spec.variants) && spec.variants.length > 0) {
                                                    const attributeType = newSection.dataset.attributeType || 'multi';
                                                    
                                                    if (attributeType === 'text') {
                                                        // For text attributes, only add variants that are text variants
                                                        // Skip old single/multi variants (they don't have is_text_variant flag)
                                                        for (const variant of spec.variants) {
                                                            // Only process if it's a text variant
                                                            if (variant.is_text_variant === true) {
                                                                const variantName = variant.name;
                                                                const variantId = variant.id;
                                                                if (variantName) {
                                                                    await addTextVariant(newSection, variantName, variantId);
                                                                    await new Promise(r => setTimeout(r, 50));
                                                                }
                                                            } else {
                                                                // Old single/multi variant - skip it
                                                                console.log('Skipping non-text variant for text attribute:', variant.id);
                                                            }
                                                        }
                                                    } else {
                                                        // Set selected variants
                                                        const variantIds = spec.variants.map(v => String(v.id));
                                                        console.log('Setting variants for additional section:', variantIds);
                                                        
                                                        // For single-select, only use the first variant
                                                        const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                            ? [spec.variants[0].id] 
                                                            : variantIds;
                                                        
                                                        if (varSel._choices) {
                                                            try {
                                                                varSel._choices.setChoiceByValue(variantsToSet);
                                                                console.log('Variants set using Choices.js for additional section');
                                                            } catch (e) {
                                                                console.error('Error setting Choices.js values:', e);
                                                                // Fallback to native select
                                                                variantsToSet.forEach(vid => {
                                                                    const option = varSel.querySelector(`option[value="${vid}"]`);
                                                                    if (option) option.selected = true;
                                                                });
                                                            }
                                                        } else {
                                                            // Fallback to native select
                                                            variantsToSet.forEach(vid => {
                                                                const option = varSel.querySelector(`option[value="${vid}"]`);
                                                                if (option) option.selected = true;
                                                            });
                                                        }
                                                        
                                                        // Trigger change event to update UI
                                                        varSel.dispatchEvent(new Event('change'));
                                                    }
                                                    
                                                    // Handle price data
                                                    if (spec.variants.some(v => v.mrp || v.selling_price)) {
                                                        const radios = getYesNoRadios(newSection);
                                                        if (radios.yes) {
                                                            if (radios.no) radios.no.checked = false;
                                                            radios.yes.checked = true;
                                                            radios.yes.dispatchEvent(new Event('change'));
                                                            // Wait for price rows to render
                                                            await new Promise(resolve => setTimeout(resolve, 300));
                                                            spec.variants.forEach(variant => {
                                                                if (variant.mrp || variant.selling_price) {
                                                                    const mrpInput = newSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                    const spInput = newSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                    if (mrpInput && variant.mrp) mrpInput.value = variant.mrp;
                                                                    if (spInput && variant.selling_price) spInput.value = variant.selling_price;
                                                                }
                                                            });
                                                        }
                                                    }
                                                    
                                                    // Update preview
                                                    try { renderPreview(); } catch (e) { }
                                                }
                                            }
                                        }
                                    }
                                    
                                    if (i < mandatorySpecs.length - 1) {
                                        await new Promise(resolve => setTimeout(resolve, 500));
                                    }
                                }
                                
                                // Now load optional attributes AFTER mandatory ones are loaded
                                console.log(`Loading ${optionalSpecs.length} optional attributes after mandatory sections`);
                                
                                // Show loader for optional attributes IMMEDIATELY (no delay) to prevent gap
                                // Show it right after mandatory loop ends, before any other processing
                                if (hasOptionalAttrs && optionalSpecs.length > 0) {
                                    // Show loader immediately to prevent any gap - no await, no delay
                                    showOptionalAttributeLoader();
                                }
                                
                                for (let i = 0; i < optionalSpecs.length; i++) {
                                    const spec = optionalSpecs[i];
                                    if (!spec || !spec.attribute_id) continue;
                                    
                                    const attributeId = parseInt(spec.attribute_id, 10);
                                    if (isNaN(attributeId)) continue;
                                    
                                    // Check if section already exists (should not for optional)
                                    const existingSectionForAttr = findSectionByAttrId(attributeId);
                                    if (existingSectionForAttr) {
                                        // Section already exists, just populate it
                                        // Update loader position when existing section is populated
                                        if (i === 0) {
                                            // First iteration - show/update loader
                                            updateOptionalAttributeLoader();
                                        }
                                        
                                        const attrSel = getAttrSelect(existingSectionForAttr);
                                        const varSel = getVariantsSelect(existingSectionForAttr);
                                        if (attrSel && varSel && attributeId) {
                                            if (spec.variants && Array.isArray(spec.variants) && spec.variants.length > 0) {
                                                const attributeType = existingSectionForAttr.dataset.attributeType || 'multi';
                                                
                                                if (attributeType === 'text') {
                                                    // For text attributes, only add variants that are text variants
                                                    // Skip old single/multi variants (they don't have is_text_variant flag)
                                                    for (const variant of spec.variants) {
                                                        // Only process if it's a text variant
                                                        if (variant.is_text_variant === true) {
                                                            const variantName = variant.name;
                                                            const variantId = variant.id;
                                                            if (variantName) {
                                                                await addTextVariant(existingSectionForAttr, variantName, variantId);
                                                                await new Promise(r => setTimeout(r, 50));
                                                            }
                                                        } else {
                                                            // Old single/multi variant - skip it
                                                            console.log('Skipping non-text variant for text attribute:', variant.id);
                                                        }
                                                    }
                                                } else {
                                                    if (varSel._choices) {
                                                        await new Promise(resolve => setTimeout(resolve, 200));
                                                    } else {
                                                        await new Promise(resolve => setTimeout(resolve, 200));
                                                        if (window.Choices && !varSel._choices) {
                                                            try {
                                                                varSel._choices = new Choices(varSel, { removeItemButton: true, searchEnabled: true, allowHTML: true });
                                                                await new Promise(resolve => setTimeout(resolve, 200));
                                                            } catch (e) {
                                                                console.error('Error initializing Choices.js for optional section:', e);
                                                            }
                                                        }
                                                    }
                                                    
                                                    const variantIds = spec.variants.map(v => String(v.id));
                                                    
                                                    // For single-select, only use the first variant
                                                    const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                        ? [spec.variants[0].id] 
                                                        : variantIds;
                                                    
                                                    if (varSel._choices) {
                                                        try {
                                                            varSel._choices.setChoiceByValue(variantsToSet);
                                                        } catch (e) {
                                                            variantsToSet.forEach(vid => {
                                                                const option = varSel.querySelector(`option[value="${vid}"]`);
                                                                if (option) option.selected = true;
                                                            });
                                                        }
                                                    } else {
                                                        variantsToSet.forEach(vid => {
                                                            const option = varSel.querySelector(`option[value="${vid}"]`);
                                                            if (option) option.selected = true;
                                                        });
                                                    }
                                                    varSel.dispatchEvent(new Event('change'));
                                                }
                                                
                                                if (spec.variants.some(v => v.mrp || v.selling_price)) {
                                                    const radios = getYesNoRadios(existingSectionForAttr);
                                                    if (radios.yes) {
                                                        if (radios.no) radios.no.checked = false;
                                                        radios.yes.checked = true;
                                                        radios.yes.dispatchEvent(new Event('change'));
                                                        await new Promise(resolve => setTimeout(resolve, 300));
                                                        spec.variants.forEach(variant => {
                                                            if (variant.mrp || variant.selling_price) {
                                                                const mrpInput = existingSectionForAttr.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                const spInput = existingSectionForAttr.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                if (mrpInput && variant.mrp) mrpInput.value = variant.mrp;
                                                                if (spInput && variant.selling_price) spInput.value = variant.selling_price;
                                                            }
                                                        });
                                                    }
                                                }
                                                try { renderPreview(); } catch (e) { }
                                            }
                                        }
                                    } else {
                                        // Create new section for optional attribute using Add More button
                                        const addMoreBtn = document.querySelector('button.btn-light.btn-wave.mb-2');
                                        if (addMoreBtn) {
                                            addMoreBtn.click();
                                            const waitForNew = () => new Promise((resolve) => {
                                                let n = 0;
                                                const max = 100;
                                                const tick = () => {
                                                    n++;
                                                    const sections = document.querySelectorAll('.spec-section:not(.mandatory-section)');
                                                    // Find the last non-mandatory section (newly added)
                                                    const allSections = Array.from(document.querySelectorAll('.spec-section'));
                                                    const nonMandatorySections = allSections.filter(s => !s.classList.contains('mandatory-section'));
                                                    if (nonMandatorySections.length > i) {
                                                        return resolve(nonMandatorySections[nonMandatorySections.length - 1]);
                                                    }
                                                    if (n >= max) return resolve(null);
                                                    setTimeout(tick, 50);
                                                };
                                                tick();
                                            });
                                            const newSection = await waitForNew();
                                            
                                            if (newSection) {
                                                // Update loader position after new section is added
                                                updateOptionalAttributeLoader();
                                                
                                                const attrSel = getAttrSelect(newSection);
                                                const varSel = getVariantsSelect(newSection);
                                                if (attrSel && attributeId) {
                                                    await ensureAttributeInDropdown(attrSel, attributeId);
                                                    attrSel.value = attributeId;
                                                    if (attrSel._choices) {
                                                        attrSel._choices.setChoiceByValue(attributeId);
                                                    }
                                                    attrSel.dispatchEvent(new Event('change'));
                                                    await populateVariants(varSel, attributeId, newSection);
                                                    
                                                    if (varSel._choices) {
                                                        await new Promise(resolve => setTimeout(resolve, 300));
                                                    } else {
                                                        await new Promise(resolve => setTimeout(resolve, 200));
                                                        if (window.Choices && !varSel._choices) {
                                                            try {
                                                                varSel._choices = new Choices(varSel, { removeItemButton: true, searchEnabled: true, allowHTML: true });
                                                                await new Promise(resolve => setTimeout(resolve, 200));
                                                            } catch (e) {
                                                                console.error('Error initializing Choices.js for optional:', e);
                                                            }
                                                        }
                                                    }
                                                    
                                                    if (spec.variants && Array.isArray(spec.variants) && spec.variants.length > 0) {
                                                        const attributeType = newSection.dataset.attributeType || 'multi';
                                                        
                                                        if (attributeType === 'text') {
                                                            // For text attributes, add each variant using addTextVariant
                                                            for (const variant of spec.variants) {
                                                                const variantName = variant.is_text_variant === true ? variant.name : null;
                                                                const variantId = variant.id;
                                                                if (variantName) {
                                                                    await addTextVariant(newSection, variantName, variantId);
                                                                    await new Promise(r => setTimeout(r, 50));
                                                                }
                                                            }
                                                        } else {
                                                            const variantIds = spec.variants.map(v => String(v.id));
                                                            
                                                            // For single-select, only use the first variant
                                                            const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                                ? [spec.variants[0].id] 
                                                                : variantIds;
                                                            
                                                            if (varSel._choices) {
                                                                try {
                                                                    varSel._choices.setChoiceByValue(variantsToSet);
                                                                } catch (e) {
                                                                    variantsToSet.forEach(vid => {
                                                                        const option = varSel.querySelector(`option[value="${vid}"]`);
                                                                        if (option) option.selected = true;
                                                                    });
                                                                }
                                                            } else {
                                                                variantsToSet.forEach(vid => {
                                                                    const option = varSel.querySelector(`option[value="${vid}"]`);
                                                                    if (option) option.selected = true;
                                                                });
                                                            }
                                                            varSel.dispatchEvent(new Event('change'));
                                                        }
                                                        
                                                        if (spec.variants.some(v => v.mrp || v.selling_price)) {
                                                            const radios = getYesNoRadios(newSection);
                                                            if (radios.yes) {
                                                                if (radios.no) radios.no.checked = false;
                                                                radios.yes.checked = true;
                                                                radios.yes.dispatchEvent(new Event('change'));
                                                                await new Promise(resolve => setTimeout(resolve, 300));
                                                                spec.variants.forEach(variant => {
                                                                    if (variant.mrp || variant.selling_price) {
                                                                        const mrpInput = newSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                        const spInput = newSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                        if (mrpInput && variant.mrp) mrpInput.value = variant.mrp;
                                                                        if (spInput && variant.selling_price) spInput.value = variant.selling_price;
                                                                    }
                                                                });
                                                            }
                                                        }
                                                        try { renderPreview(); } catch (e) { }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    
                                    if (i < optionalSpecs.length - 1) {
                                        await new Promise(resolve => setTimeout(resolve, 500));
                                    } else {
                                        // Last iteration - update loader position one more time
                                        updateOptionalAttributeLoader();
                                    }
                                }
                                
                                // Hide optional attribute loader after all are loaded
                                hideOptionalAttributeLoader();
                                
                                setTimeout(async () => {
                                    renderPreview();
                                    
                                    // After all specs are loaded into UI, save complete state to localStorage
                                    // This ensures text variants with names and prices are saved
                                    try {
                                        const completeSpecs = collectSpecifications();
                                        if (completeSpecs && Array.isArray(completeSpecs) && completeSpecs.length > 0) {
                                            const key = getLocalStorageKey();
                                            const dataToStore = {
                                                specifications: completeSpecs,
                                                timestamp: Date.now(),
                                                productId: '<?php echo htmlspecialchars($editing_product_id ?? ''); ?>',
                                                productName: '<?php echo isset($pending_payload['product_name']) ? htmlspecialchars($pending_payload['product_name']) : ''; ?>'
                                            };
                                            localStorage.setItem(key, JSON.stringify(dataToStore));
                                            console.log('✓ Saved complete specifications to localStorage after loading from database (including text variants):', completeSpecs);
                                            
                                            // Update global variable
                                            window.localStorageSpecs = completeSpecs;
                                        }
                                    } catch (e) {
                                        console.error('Error saving complete specs to localStorage:', e);
                                    }
                                    
                                    markSpecsInitialized();
                                }, 1000);
                            };
                            
                            setTimeout(() => {
                                loadAllSpecs();
                            }, 100);
                        } else {
                            markSpecsInitialized();
                        }
                        
                        // Initialize subcategory change handler for edit mode
                            handleSubcategoryChangeInEditMode();
                    <?php else: ?>
                        // ===== NEW PRODUCT MODE =====
                        // Step 1: Check for localStorage data
                        const localStorageSpecs = await loadAndApplySpecsFromLocalStorage();
                        
                        // Step 2: Get category/subcategory for mandatory sections
                            const categorySelect = document.querySelector('select[name="category"]');
                            let categoryId = '';
                            let subcategoryId = '';
                            if (categorySelect && categorySelect.value) {
                                categoryId = categorySelect.value;
                            } else {
                                <?php
                                $stored_category = isset($_SESSION['selected_category_for_mandatory']) ? $_SESSION['selected_category_for_mandatory'] : '';
                                if (!empty($stored_category)):
                                    ?>
                                    categoryId = '<?php echo $stored_category; ?>';
                            <?php endif; ?>
                            }
                            <?php
                            $stored_subcategory = isset($_SESSION['selected_subcategory_for_mandatory']) ? $_SESSION['selected_subcategory_for_mandatory'] : '';
                            if (!empty($stored_subcategory)):
                                ?>
                                subcategoryId = '<?php echo $stored_subcategory; ?>';
                            <?php endif; ?>
                        
                        // Step 3: Create mandatory sections if we have category/subcategory
                            if (subcategoryId || categoryId) {
                            await createMandatorySections(subcategoryId || categoryId);
                            }
                                    
                        // Step 4: Load optional specs from localStorage (if any)
                        if (localStorageSpecs && Array.isArray(localStorageSpecs) && localStorageSpecs.length > 0) {
                            // Show optional loader IMMEDIATELY after mandatory sections finish to prevent gap
                            // We'll determine if optional attributes exist, but show loader right away to avoid delay
                            showOptionalAttributeLoader();
                            
                            // Filter out mandatory attributes
                                    let actualMandatoryAttrs = [];
                                    if (subcategoryId || categoryId) {
                                try {
                                    let apiUrl = '<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?';
                                    if (subcategoryId) {
                                        apiUrl += 'sub_category_id=' + encodeURIComponent(subcategoryId);
                                    } else {
                                        apiUrl += 'category_id=' + encodeURIComponent(categoryId);
                                    }
                                    const resp = await fetch(apiUrl);
                                    const data = await resp.json();
                                    if (data && data.mandatory && Array.isArray(data.mandatory)) {
                                        actualMandatoryAttrs = data.mandatory.map(id => parseInt(id, 10));
                                    }
                                } catch (e) {
                                    console.error('Error fetching mandatory attributes:', e);
                                }
                            }
                            
                            const optionalSpecs = localStorageSpecs.filter(spec => {
                                const attrId = parseInt(spec.attribute_id, 10);
                                return !actualMandatoryAttrs.includes(attrId);
                            });
                            
                            // Hide loader if no optional attributes found
                            if (optionalSpecs.length === 0) {
                                hideOptionalAttributeLoader();
                            }
                            
                            if (optionalSpecs.length > 0) {
                                // Load optional specs into UI (similar to edit mode logic)
                                
                                for (let i = 0; i < optionalSpecs.length; i++) {
                                    const spec = optionalSpecs[i];
                                    if (!spec || !spec.attribute_id) continue;
                                    
                                    const attributeId = parseInt(spec.attribute_id, 10);
                                    if (isNaN(attributeId)) continue;
                                    
                                    if (i === 0) {
                                        // Use first section
                                        // Update loader position when first optional attribute is loaded
                                        updateOptionalAttributeLoader();
                                        
                                        const attrSel = getAttrSelect(firstSection);
                                        const varSel = getVariantsSelect(firstSection);
                                        if (attrSel && attributeId) {
                                            await ensureAttributeInDropdown(attrSel, attributeId);
                                            attrSel.value = attributeId;
                                            if (attrSel._choices) {
                                                attrSel._choices.setChoiceByValue(attributeId);
                                            }
                                            attrSel.dispatchEvent(new Event('change'));
                                            await populateVariants(varSel, attributeId, firstSection);
                                            
                                            if (spec.variants && Array.isArray(spec.variants) && spec.variants.length) {
                                                const attributeType = firstSection.dataset.attributeType || 'multi';
                                                // Skip for text attributes (handled in populateVariants)
                                                if (attributeType !== 'text') {
                                                    setTimeout(() => {
                                                        // For single-select, only use the first variant
                                                        const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                            ? [spec.variants[0]] 
                                                            : spec.variants;
                                                        
                                                        if (varSel._choices) {
                                                            varSel._choices.setChoiceByValue(variantsToSet.map(v => String(v.id)));
                                                        }
                                                        varSel.dispatchEvent(new Event('change'));
                                                    
                                                    // Handle price data
                                                    if (spec.variants.some(v => v.mrp || v.selling_price)) {
                                                        const radios = getYesNoRadios(firstSection);
                                                        if (radios.yes) {
                                                            if (radios.no) radios.no.checked = false;
                                                            radios.yes.checked = true;
                                                            radios.yes.dispatchEvent(new Event('change'));
                                                            setTimeout(() => {
                                                                spec.variants.forEach(variant => {
                                                                    if (variant.mrp || variant.selling_price) {
                                                                        const mrpInput = firstSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                        const spInput = firstSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                        if (mrpInput && variant.mrp) mrpInput.value = variant.mrp;
                                                                        if (spInput && variant.selling_price) spInput.value = variant.selling_price;
                                                                    }
                                                                });
                                                            }, 200);
                                                        }
                                                    }
                                                }, 200);
                                            }
                                        }
                                    } else {
                                        // Add more sections for additional optional specs
                                        const addMoreBtn = document.querySelector('button.btn-light.btn-wave.mb-2');
                                        if (addMoreBtn) {
                                            addMoreBtn.click();
                                            const waitForNew = () => new Promise((resolve) => {
                                                let n = 0;
                                                const max = 100;
                                                const tick = () => {
                                                    n++;
                                                    const sections = document.querySelectorAll('.spec-section');
                                                    if (sections.length > i) return resolve(sections[i]);
                                                    if (n >= max) return resolve(null);
                                                    setTimeout(tick, 50);
                                                };
                                                tick();
                                            });
                                            const newSection = await waitForNew();
                                            
                                            if (newSection) {
                                                const attrSel = getAttrSelect(newSection);
                                                const varSel = getVariantsSelect(newSection);
                                                if (attrSel && attributeId) {
                                                    await ensureAttributeInDropdown(attrSel, attributeId);
                                                    attrSel.value = attributeId;
                                                    if (attrSel._choices) {
                                                        attrSel._choices.setChoiceByValue(attributeId);
                                                    }
                                                    attrSel.dispatchEvent(new Event('change'));
                                                    await populateVariants(varSel, attributeId, newSection);
                                                    
                                                    if (spec.variants && Array.isArray(spec.variants) && spec.variants.length) {
                                                        const attributeType = newSection.dataset.attributeType || 'multi';
                                                        // Skip for text attributes (handled in populateVariants)
                                                        if (attributeType !== 'text') {
                                                            setTimeout(() => {
                                                                // For single-select, only use the first variant
                                                                const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                                    ? [spec.variants[0]] 
                                                                    : spec.variants;
                                                                
                                                                if (varSel._choices) {
                                                                    varSel._choices.setChoiceByValue(variantsToSet.map(v => String(v.id)));
                                                                }
                                                                varSel.dispatchEvent(new Event('change'));
                                                            
                                                            // Handle price data
                                                            if (spec.variants.some(v => v.mrp || v.selling_price)) {
                                                                const radios = getYesNoRadios(newSection);
                                                                if (radios.yes) {
                                                                    if (radios.no) radios.no.checked = false;
                                                                    radios.yes.checked = true;
                                                                    radios.yes.dispatchEvent(new Event('change'));
                                                                    setTimeout(() => {
                                                                        spec.variants.forEach(variant => {
                                                                            if (variant.mrp || variant.selling_price) {
                                                                                const mrpInput = newSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                                const spInput = newSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                                if (mrpInput && variant.mrp) mrpInput.value = variant.mrp;
                                                                                if (spInput && variant.selling_price) spInput.value = variant.selling_price;
                                                                            }
                                                                        });
                                                                    }, 200);
                                                                }
                                                            }
                                                        }, 200);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    
                                    if (i < optionalSpecs.length - 1) {
                                        await new Promise(resolve => setTimeout(resolve, 300));
                                    } else {
                                        // Last iteration - update loader position one more time
                                        updateOptionalAttributeLoader();
                                    }
                                }
                                
                                // Hide optional attribute loader after all are loaded
                                hideOptionalAttributeLoader();
                            }
                        }
                        
                        markSpecsInitialized();
                    <?php endif; ?>
                    
                    // Ensure a dashed divider exists after the initial section
                    const after = firstSection && firstSection.nextElementSibling;
                    if (firstSection && !(after && after.tagName === 'HR')) {
                        const hr = document.createElement('hr');
                        hr.className = 'mt-0';
                        hr.setAttribute('style', 'border-top: 1px dashed #d1d5db; margin: 32px 0;');
                        firstSection.parentNode.insertBefore(hr, document.querySelector('form[method="POST"]')?.parentNode || firstSection.nextSibling);
                    }
                    
                    // Old duplicate code removed - functionality moved above
                    // Fallback timeout to hide loader if something goes wrong
                    setTimeout(() => {
                        if (!window.__specInitializationDone) {
                            markSpecsInitialized();
                        }
                    }, 4000);
                });
            </script>
            <script>
                // === PREVIEW RENDER (old UI style, show names not IDs) ===
                function renderPreview() {
                    const preview = document.getElementById('spec-preview');
                    const previewSkeleton = document.getElementById('preview-skeleton');
                    if (!preview) return;
                    // Ensure preview is visible and skeleton is hidden
                    if (previewSkeleton) {
                        previewSkeleton.style.display = 'none';
                    }
                    preview.style.display = 'block';
                    const sections = document.querySelectorAll('.spec-section');
                    if (!sections.length) { preview.innerHTML = '<div class="text-muted">Select attributes and variations to preview.</div>'; return; }
                    preview.innerHTML = '';
                    sections.forEach(section => {
                        const attrSel = getAttrSelect(section);
                        const varSel = getVariantsSelect(section);
                        if (!attrSel || !varSel) return;
                        const attrName = (attrSel.selectedOptions[0]?.textContent || '').trim();
                        
                        // Get variants based on attribute type
                        const attributeType = section.dataset.attributeType || 'multi';
                        let selectedOpts = [];
                        
                        if (attributeType === 'text') {
                            // Get text variants
                            const textVariants = getTextVariants(section);
                            selectedOpts = textVariants.map(v => ({
                                textContent: v.name
                            }));
                        } else {
                            // Get selected options from select element
                            selectedOpts = Array.from(varSel.selectedOptions || []);
                        }
                        
                        if (!attrName || !selectedOpts.length) return;
                        const block = document.createElement('div');
                        block.className = 'mb-3';
                        const row = document.createElement('div');
                        row.className = 'mb-1 d-flex align-items-start';
                        const label = document.createElement('label');
                        label.className = 'fw-semibold me-3';
                        label.style.minWidth = '60px';
                        label.textContent = attrName + ':';
                        // Add mandatory indicator if this is a mandatory section
                        if (section.classList.contains('mandatory-section')) {
                            const mandatoryIcon = document.createElement('span');
                            mandatoryIcon.className = 'text-warning ms-1';
                            mandatoryIcon.innerHTML = '<i class="fa fa-lock"></i>';
                            mandatoryIcon.title = 'Mandatory attribute';
                            label.appendChild(mandatoryIcon);
                        }
                        const btns = document.createElement('div');
                        btns.className = 'd-flex gap-2 flex-wrap';
                        selectedOpts.forEach(o => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'btn btn-outline-secondary btn-size-select';
                            btn.disabled = true;
                            btn.textContent = (o.textContent || '').trim();
                            btns.appendChild(btn);
                        });
                        row.appendChild(label);
                        row.appendChild(btns);
                        block.appendChild(row);
                        preview.appendChild(block);
                    });
                    // Ensure delete buttons are visible for non-mandatory sections only
                    try {
                        document.querySelectorAll('.spec-section:not(.mandatory-section) .btn-delete-section').forEach(btn => {
                            btn.style.display = '';
                            btn.disabled = false;
                        });
                        // Hide delete buttons for mandatory sections
                        document.querySelectorAll('.spec-section.mandatory-section .btn-delete-section').forEach(btn => {
                            btn.style.display = 'none';
                            btn.disabled = true;
                        });
                    } catch (e) { }
                }
                // Hook into interactions to update preview and save to localStorage
                document.addEventListener('change', function (e) {
                    if (e.target.closest('.spec-section')) {
                        renderPreview();
                        debouncedSaveToLocalStorage();
                    }
                });
                document.addEventListener('click', async function (e) {
                    if (e.target.closest('.btn-delete-section')) {
                        // Ensure mandatory sections still exist after deletion
                        await ensureMandatorySectionsExist();
                        renderPreview();
                        debouncedSaveToLocalStorage();
                    }
                });
                // Global listener for price inputs (handles dynamically added inputs)
                document.addEventListener('input', function (e) {
                    if (e.target.matches('input[data-variant-id][data-field]')) {
                        debouncedSaveToLocalStorage();
                    }
                });
                document.addEventListener('blur', function (e) {
                    if (e.target.matches('input[data-variant-id][data-field]')) {
                        debouncedSaveToLocalStorage();
                    }
                }, true);
                // Re-render after add more and initial load
                document.addEventListener('DOMContentLoaded', function () {
                    // Ensure preview is visible (not skeleton) for new products
                    const previewSkeleton = document.getElementById('preview-skeleton');
                    const previewContent = document.getElementById('spec-preview');
                    if (previewSkeleton) {
                        previewSkeleton.style.display = 'none';
                    }
                    if (previewContent) {
                        previewContent.style.display = 'block';
                    }
                    renderPreview();
                    const addMoreBtn = document.querySelector('button.btn-light.btn-wave.mb-2');
                    if (addMoreBtn) { addMoreBtn.addEventListener('click', () => setTimeout(renderPreview, 50)); }
                });
                
                // Save to localStorage before page unload (safety measure)
                window.addEventListener('beforeunload', function () {
                    try {
                        // Force immediate save (don't debounce)
                        saveSpecsToLocalStorage();
                    } catch (e) {
                        console.error('Error saving to localStorage on beforeunload:', e);
                    }
                });
            </script>
            <script>
                // Safety check: Ensure mandatory sections exist after all initialization
                setTimeout(async () => {
                    await ensureMandatorySectionsExist();
                }, 3000);
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    // Add More functionality
                    const addMoreBtn = document.querySelector('button.btn-light.btn-wave.mb-2');
                    const addMoreItem = document.getElementById('add-more-item');
                    if (!addMoreBtn || !addMoreItem) return;
                    
                    let isAddingSection = false; // Flag to prevent multiple rapid clicks
                    addMoreBtn.addEventListener('click', function () {
                        // Prevent multiple rapid clicks
                        if (isAddingSection) {
                            console.log('Already adding a section, please wait...');
                            return;
                        }
                        // Allow adding more sections; block only while initial mandatory load is pending
                        if (MANDATORY_ATTRIBUTES.length > 0 && !MANDATORY_SECTIONS_CREATED) {
                            alert('Please wait for mandatory attributes to load first.');
                            return;
                        }
                        // Set flag to prevent multiple clicks
                        isAddingSection = true;
                        
                        // Clone the first section
                        const clone = addMoreItem.cloneNode(true);
                        clone.id = '';
                        
                        // Remove all * marks
                        clone.querySelectorAll('span.text-danger').forEach(function (span) { span.remove(); });
                        
                        // Remove any existing price rows in the clone
                        clone.querySelectorAll('.variant-price-rows').forEach(function (x) { x.innerHTML = ''; });
                        
                        // Remove any Choices.js wrapper markup
                        clone.querySelectorAll('.choices').forEach(function (wrapper) {
                            const sel = wrapper.querySelector('select');
                            if (sel) {
                                wrapper.parentNode.insertBefore(sel, wrapper);
                                try {
                                    sel.removeAttribute('hidden');
                                    sel.removeAttribute('data-choice');
                                    sel.classList.remove('choices__input');
                                    sel.tabIndex = 0;
                                    sel.disabled = false;
                                    sel.style.backgroundColor = '';
                                    sel.style.cursor = '';
                                } catch (e) { }
                            }
                            wrapper.remove();
                        });
                        
                        // Add delete icon/button to the clone
                        const delBtn = document.createElement('button');
                        delBtn.type = 'button';
                        delBtn.className = 'btn btn-link text-danger btn-delete-section';
                        delBtn.style = 'position:absolute;top:-20px;right:0;font-size:16px;z-index:10;';
                        delBtn.innerHTML = '<i class="fa fa-trash"></i>';
                        delBtn.onclick = async function () {
                            clone.remove();
                            // Restore attribute options to other dropdowns when section is deleted
                            removeSelectedAttributesFromOtherDropdowns();
                            // Ensure mandatory sections still exist after deletion
                            await ensureMandatorySectionsExist();
                            // Save to localStorage after deletion
                            debouncedSaveToLocalStorage();
                            // Update preview
                            renderPreview();
                        };
                        clone.style.position = 'relative';
                        clone.appendChild(delBtn);
                        
                        // Reset inputs/select values and destroy Choices instances if any
                        clone.querySelectorAll('select').forEach(function (sel) {
                            try { if (sel._choices) { sel._choices.destroy(); sel._choices = null; } } catch (e) { }
                            if (sel.hasAttribute('multiple')) {
                                sel.innerHTML = '';
                            } else {
                                if (sel.options && sel.options.length) { sel.selectedIndex = 0; }
                                sel.disabled = false;
                                sel.style.backgroundColor = '';
                                sel.style.cursor = '';
                            }
                        });
                        clone.querySelectorAll('input').forEach(function (inp) {
                            if (inp.type === 'radio' || inp.type === 'checkbox') {
                                inp.checked = false;
                                if (inp.type === 'radio') {
                                    if (inp.id.includes('price-change-yes-')) {
                                        inp.id = 'flexRadioDefault1';
                                    } else if (inp.id.includes('price-change-no-')) {
                                        inp.id = 'flexRadioDefault2';
                                    }
                                    inp.name = 'flexRadioDefault';
                                }
                            }
                            if (inp.type === 'text') { inp.value = ''; }
                        });
                        
                        // Insert and initialize as a unique spec section
                        addMoreItem.parentNode.insertBefore(clone, addMoreBtn);
                        
                        // Add dashed divider after the section
                        const hr = document.createElement('hr');
                        hr.className = 'mt-0';
                        hr.setAttribute('style', 'border-top: 1px dashed #d1d5db; margin: 32px 0;');
                        clone.parentNode.insertBefore(hr, addMoreBtn);
                        
                        // Ensure the clone gets a fresh UID before initialization
                        delete clone.dataset.specUid;
                        
                        // Remove mandatory attributes from the dropdown in the new section
                        removeMandatoryAttributesFromDropdown(clone);
                        
                        // Initialize as non-mandatory
                        initOneSection(clone, false);
                        
                        // Remove already selected attributes from the new section dropdown
                        removeSelectedAttributesFromOtherDropdowns();
                        
                        const attrSel = (function () {
                            const lab = Array.from(clone.querySelectorAll('label')).find(l => (l.textContent || '').toLowerCase().includes('attribute'));
                            const wrap = lab ? lab.closest('.col-12, .col-xl-6, .col-lg-6, .col-md-6, .col-sm-6, .col-12.mb-1') : null;
                            return wrap ? wrap.querySelector('select') : null;
                        })();
                        const varSel = getVariantsSelect(clone);
                        
                        // Ensure a variants select exists
                        let ensuredVarSel = varSel;
                        if (!ensuredVarSel) {
                            const varLabel = Array.from(clone.querySelectorAll('label')).find(l => (l.textContent || '').toLowerCase().includes('variation'));
                            const varWrap = varLabel ? varLabel.parentElement : null;
                            if (varWrap) {
                                ensuredVarSel = document.createElement('select');
                                ensuredVarSel.className = 'form-control';
                                ensuredVarSel.setAttribute('multiple', '');
                                varWrap.appendChild(ensuredVarSel);
                            }
                        }
                        if (ensuredVarSel && window.Choices && !ensuredVarSel._choices) {
                            try { ensuredVarSel._choices = new Choices(ensuredVarSel, { removeItemButton: true, searchEnabled: true, allowHTML: true }); } catch (e) { }
                        }
                        if (attrSel) { populateVariants(ensuredVarSel, attrSel.value || '', clone); }
                        
                        // Default the radio to No
                        const radios = clone.querySelectorAll('input[type="radio"]');
                        if (radios.length > 1) { radios[1].checked = true; }
                        
                        // Reset flag after section is created and initialized
                        setTimeout(() => {
                            debouncedSaveToLocalStorage();
                            renderPreview();
                            isAddingSection = false; // Reset flag
                        }, 100);
                    });
                });
            </script>
            <script>
                // Old duplicate code removed - all functionality moved above
                                        try {
                                            let apiUrl = '<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?';
                                            if (subcategoryId) {
                                                apiUrl += 'sub_category_id=' + encodeURIComponent(subcategoryId);
                                            } else {
                                                apiUrl += 'category_id=' + encodeURIComponent(categoryId);
                                            }
                                            const resp = await fetch(apiUrl);
                                            const data = await resp.json();
                                            if (data && data.mandatory && Array.isArray(data.mandatory)) {
                                                actualMandatoryAttrs = data.mandatory.map(id => parseInt(id, 10));
                                                console.log('Actual mandatory attributes for category/subcategory:', actualMandatoryAttrs);
                                            }
                                        } catch (e) {
                                            console.error('Error fetching mandatory attributes:', e);
                                        }
                                    }
                                    
                                    // First, check localStorage (re-check in case it wasn't loaded yet)
                                    let specsToLoad = null;
                                    const storedSpecs = loadSpecsFromLocalStorage();
                                    if (storedSpecs && Array.isArray(storedSpecs) && storedSpecs.length > 0) {
                                        // Filter out mandatory attributes - they should already be in mandatory sections
                                        // Only load optional attributes from localStorage
                                        specsToLoad = storedSpecs.filter(spec => {
                                            const attrId = parseInt(spec.attribute_id, 10);
                                            const isMandatory = actualMandatoryAttrs.length > 0 && actualMandatoryAttrs.includes(attrId);
                                            if (isMandatory) {
                                                console.log(`Skipping mandatory attribute ${attrId} from localStorage - it should be in mandatory section`);
                                                return false; // Skip mandatory attributes
                                            }
                                            return true; // Keep optional attributes
                                        });
                                        window.useLocalStorageData = true; // Mark that we're loading from localStorage
                                        console.log('Filtered localStorage specs (removed mandatory):', specsToLoad);
                                    } else if (localStorageSpecs && Array.isArray(localStorageSpecs) && localStorageSpecs.length > 0) {
                                        // Filter out mandatory attributes
                                        specsToLoad = localStorageSpecs.filter(spec => {
                                            const attrId = parseInt(spec.attribute_id, 10);
                                            const isMandatory = actualMandatoryAttrs.length > 0 && actualMandatoryAttrs.includes(attrId);
                                            if (isMandatory) {
                                                console.log(`Skipping mandatory attribute ${attrId} from localStorage - it should be in mandatory section`);
                                                return false;
                                            }
                                            return true;
                                        });
                                        window.useLocalStorageData = true; // Mark that we're loading from localStorage
                                        console.log('Filtered localStorage specs (cached, removed mandatory):', specsToLoad);
                                    } else {
                                        // Fallback to hidden input or global variable
                                        let prefillJson = '';
                                        const prefillEl = document.getElementById('specifications_prefill_json');
                                        if (prefillEl && prefillEl.value && prefillEl.value.trim() !== '') {
                                            prefillJson = prefillEl.value.trim();
                                        } else if (Array.isArray(window.PREFILL_SPECIFICATIONS)) {
                                            prefillJson = JSON.stringify(window.PREFILL_SPECIFICATIONS);
                                        }
                                        if (prefillJson) {
                                            try { 
                                                const parsed = JSON.parse(prefillJson);
                                                // Filter out mandatory attributes
                                                specsToLoad = parsed.filter(spec => {
                                                    const attrId = parseInt(spec.attribute_id, 10);
                                                    const isMandatory = actualMandatoryAttrs.length > 0 && actualMandatoryAttrs.includes(attrId);
                                                    return !isMandatory;
                                                });
                                            } catch (_) { specsToLoad = []; }
                                        }
                                    }
                                    if (!specsToLoad || !Array.isArray(specsToLoad) || specsToLoad.length === 0) {
                                        console.log('No optional specifications to load for new product (mandatory ones are in mandatory sections)');
                                        return;
                                    }
                                    console.log('Loading', specsToLoad.length, 'optional specifications for new product from localStorage');
                                    
                                    // Helper to load a single spec into the UI (mirrors edit-mode logic)
                                    const loadSpecIntoSection = async (spec, index) => {
                                        if (!spec || !spec.attribute_id) {
                                            console.error('Invalid spec data at index', index, ':', spec);
                                            return;
                                        }
                                        const attributeId = parseInt(spec.attribute_id, 10);
                                        if (isNaN(attributeId)) {
                                            console.error('Invalid attribute_id:', spec.attribute_id);
                                            return;
                                        }
                                        
                                        // Check if a section already exists for this attribute
                                        const existingSection = findSectionByAttrId(attributeId);
                                        if (existingSection && existingSection !== firstSection) {
                                            console.log(`Section already exists for attribute ${attributeId}, using it instead of creating new`);
                                            // Use the existing section instead
                                            const attrSel = getAttrSelect(existingSection);
                                            const varSel = getVariantsSelect(existingSection);
                                            if (attrSel && varSel) {
                                                // Populate variants if not already populated
                                                if (spec.variants && Array.isArray(spec.variants) && spec.variants.length > 0) {
                                                    await populateVariants(varSel, attributeId, existingSection);
                                                    const attributeType = existingSection.dataset.attributeType || 'multi';
                                                    
                                                    if (attributeType === 'text') {
                                                        // For text attributes, add each variant using addTextVariant
                                                        for (const variant of spec.variants) {
                                                            const variantName = variant.is_text_variant === true ? variant.name : null;
                                                            const variantId = variant.id;
                                                            if (variantName) {
                                                                await addTextVariant(existingSection, variantName, variantId);
                                                                await new Promise(r => setTimeout(r, 50));
                                                            }
                                                        }
                                                    } else {
                                                        await new Promise(r => setTimeout(r, 200));
                                                        
                                                        // For single-select, only use the first variant
                                                        const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                            ? [spec.variants[0]] 
                                                            : spec.variants;
                                                        
                                                        if (varSel._choices) {
                                                            varSel._choices.setChoiceByValue(variantsToSet.map(v => String(v.id)));
                                                        } else {
                                                            variantsToSet.forEach(variant => {
                                                                const option = varSel.querySelector(`option[value="${variant.id}"]`);
                                                                if (option) option.selected = true;
                                                            });
                                                        }
                                                        varSel.dispatchEvent(new Event('change'));
                                                    }
                                                }
                                                    
                                                    // Check for price data and set Yes radio if needed
                                                    const radios = getYesNoRadios(existingSection);
                                                    const hasPriceInSpec = spec.variants.some(v => v.mrp || v.selling_price);
                                                    const hasPriceInStored = spec.variants.some(v => {
                                                        const variantId = v.id;
                                                        return window.existingPriceData && window.existingPriceData[variantId] && 
                                                               (window.existingPriceData[variantId].mrp || window.existingPriceData[variantId].selling_price);
                                                    });
                                                    const hasPriceData = hasPriceInSpec || hasPriceInStored;
                                                    
                                                    if (hasPriceData && radios.yes) {
                                                        console.log('Price data found for existing section, selecting Yes radio');
                                                        if (radios.no) radios.no.checked = false;
                                                        radios.yes.checked = true;
                                                        radios.yes.dispatchEvent(new Event('change'));
                                                        
                                                        // Wait for price rows to render
                                                        let attempts = 0;
                                                        const maxAttempts = 30;
                                                        const waitForPriceRows = () => {
                                                            return new Promise((resolve) => {
                                                                const checkPriceRows = () => {
                                                                    attempts++;
                                                                    const priceContainer = existingSection.querySelector('.variant-price-rows');
                                                                    const priceInputs = priceContainer ? priceContainer.querySelectorAll('input[data-variant-id]') : [];
                                                                    if (priceInputs.length > 0 || attempts >= maxAttempts) {
                                                                        resolve();
                                                                    } else {
                                                                        setTimeout(checkPriceRows, 100);
                                                                    }
                                                                };
                                                                checkPriceRows();
                                                            });
                                                        };
                                                        await waitForPriceRows();
                                                        
                                                        // Fill in prices
                                                        spec.variants.forEach(variant => {
                                                            const variantId = variant.id;
                                                            const mrp = variant.mrp || (window.existingPriceData && window.existingPriceData[variantId]?.mrp);
                                                            const sellingPrice = variant.selling_price || (window.existingPriceData && window.existingPriceData[variantId]?.selling_price);
                                                            
                                                            if (mrp || sellingPrice) {
                                                                const mrpInput = existingSection.querySelector(`input[data-variant-id="${variantId}"][data-field="mrp"]`);
                                                                const spInput = existingSection.querySelector(`input[data-variant-id="${variantId}"][data-field="selling_price"]`);
                                                                if (mrpInput && mrp) {
                                                                    mrpInput.value = mrp;
                                                                    mrpInput.dispatchEvent(new Event('input'));
                                                                }
                                                                if (spInput && sellingPrice) {
                                                                    spInput.value = sellingPrice;
                                                                    spInput.dispatchEvent(new Event('input'));
                                                                }
                                                            }
                                                        });
                                                    }
                                                }
                                            }
                                            return; // Skip creating a new section
                                        }
                                        
                                        if (index === 0) {
                                            // Make sure first section is visible when loading
                                            if (firstSection.style.display === 'none') {
                                                firstSection.style.display = '';
                                            }
                                            if (firstSection.hasAttribute('hidden')) {
                                                firstSection.removeAttribute('hidden');
                                            }
                                            
                                            const attrSel0 = getAttrSelect(firstSection);
                                            const varSel0 = getVariantsSelect(firstSection);
                                            if (attrSel0 && attributeId) {
                                                // Ensure attribute is in dropdown first
                                                await ensureAttributeInDropdown(attrSel0, attributeId);
                                                
                                                // Wait a bit for the option to be added
                                                await new Promise(r => setTimeout(r, 100));
                                                
                                                // Set the value
                                                attrSel0.value = String(attributeId);
                                                
                                                // Update Choices.js if initialized
                                                if (attrSel0._choices) {
                                                    try {
                                                        attrSel0._choices.setChoiceByValue(String(attributeId));
                                                    } catch (e) {
                                                        console.error('Error setting Choices.js value:', e);
                                                    }
                                                }
                                                
                                                // Trigger change event to ensure proper initialization
                                                attrSel0.dispatchEvent(new Event('change'));
                                                
                                                // Wait for variants to populate
                                                await populateVariants(varSel0, attributeId, firstSection);
                                                
                                                if (spec.variants && Array.isArray(spec.variants) && spec.variants.length) {
                                                    // Check attribute type
                                                    const attributeType = firstSection.dataset.attributeType || 'multi';
                                                    
                                                    if (attributeType === 'text') {
                                                        // For text attributes, add each variant using addTextVariant
                                                        for (const variant of spec.variants) {
                                                            const variantName = variant.is_text_variant === true ? variant.name : null;
                                                            const variantId = variant.id;
                                                            if (variantName) {
                                                                await addTextVariant(firstSection, variantName, variantId);
                                                                await new Promise(r => setTimeout(r, 50));
                                                            }
                                                        }
                                                    } else {
                                                        // Wait a bit more for Choices.js to initialize
                                                        await new Promise(r => setTimeout(r, 200));
                                                        
                                                        // For single-select, only use the first variant
                                                        const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                            ? [spec.variants[0]] 
                                                            : spec.variants;
                                                        
                                                        if (varSel0._choices) {
                                                            varSel0._choices.setChoiceByValue(variantsToSet.map(v => String(v.id)));
                                                        } else {
                                                            variantsToSet.forEach(variant => {
                                                                const option = varSel0.querySelector(`option[value="${variant.id}"]`);
                                                                if (option) option.selected = true;
                                                            });
                                                        }
                                                        
                                                        varSel0.dispatchEvent(new Event('change'));
                                                    }
                                                }
                                                    
                                                    const radios0 = getYesNoRadios(firstSection);
                                                    // Check if we have price data in spec or in existingPriceData
                                                    const hasPriceInSpec = spec.variants && spec.variants.some(v => v.mrp || v.selling_price);
                                                    const hasPriceInStored = spec.variants && spec.variants.some(v => {
                                                        const variantId = v.id;
                                                        return window.existingPriceData && window.existingPriceData[variantId] && 
                                                               (window.existingPriceData[variantId].mrp || window.existingPriceData[variantId].selling_price);
                                                    });
                                                    const hasPriceData = hasPriceInSpec || hasPriceInStored;
                                                    
                                                    // Handle price data if it exists
                                                    if (hasPriceData && radios0.yes) {
                                                        console.log('Price data found, selecting Yes radio for first section');
                                                        if (radios0.no) radios0.no.checked = false;
                                                        radios0.yes.checked = true;
                                                        radios0.yes.dispatchEvent(new Event('change'));
                                                        
                                                        // Wait for price rows to render
                                                        let attempts = 0;
                                                        const maxAttempts = 30;
                                                        const waitForPriceRows = () => {
                                                            return new Promise((resolve) => {
                                                                const checkPriceRows = () => {
                                                                    attempts++;
                                                                    const priceContainer = firstSection.querySelector('.variant-price-rows');
                                                                    const priceInputs = priceContainer ? priceContainer.querySelectorAll('input[data-variant-id]') : [];
                                                                    if (priceInputs.length > 0 || attempts >= maxAttempts) {
                                                                        resolve();
                                                                    } else {
                                                                        setTimeout(checkPriceRows, 100);
                                                                    }
                                                                };
                                                                checkPriceRows();
                                                            });
                                                        };
                                                        await waitForPriceRows();
                                                        
                                                        // Now fill in the prices
                                                        spec.variants.forEach(variant => {
                                                            const variantId = variant.id;
                                                            // Get price from spec first, then from stored data
                                                            const mrp = variant.mrp || (window.existingPriceData && window.existingPriceData[variantId]?.mrp);
                                                            const sellingPrice = variant.selling_price || (window.existingPriceData && window.existingPriceData[variantId]?.selling_price);
                                                            
                                                            if (mrp || sellingPrice) {
                                                                const mrpInput = firstSection.querySelector(`input[data-variant-id="${variantId}"][data-field="mrp"]`);
                                                                const spInput = firstSection.querySelector(`input[data-variant-id="${variantId}"][data-field="selling_price"]`);
                                                                if (mrpInput && mrp) {
                                                                    mrpInput.value = mrp;
                                                                    mrpInput.dispatchEvent(new Event('input'));
                                                                }
                                                                if (spInput && sellingPrice) {
                                                                    spInput.value = sellingPrice;
                                                                    spInput.dispatchEvent(new Event('input'));
                                                                }
                                                            }
                                                        });
                                                    } else {
                                                        if (radios0.no) radios0.no.checked = true;
                                                    }
                                                    try { renderPreview(); } catch (e) { }
                                                }
                                            }
                                        } else {
                                            const addMoreBtn = document.querySelector('button.btn-light.btn-wave.mb-2');
                                            if (!addMoreBtn) return;
                                            addMoreBtn.click();
                                            const waitForNew = () => new Promise((resolve, reject) => {
                                                let n = 0; const max = 100;
                                                const tick = () => {
                                                    n++;
                                                    const sections = document.querySelectorAll('.spec-section');
                                                    if (sections.length > index) return resolve(sections[index]);
                                                    if (n >= max) return reject(new Error('Timeout creating section'));
                                                    setTimeout(tick, 50);
                                                };
                                                tick();
                                            });
                                            try {
                                                const newSec = await waitForNew();
                                                const attrSelN = getAttrSelect(newSec);
                                                const varSelN = getVariantsSelect(newSec);
                                                if (attrSelN && attributeId) {
                                                    // Ensure attribute is in dropdown first
                                                    await ensureAttributeInDropdown(attrSelN, attributeId);
                                                    
                                                    // Wait a bit for the option to be added
                                                    await new Promise(r => setTimeout(r, 100));
                                                    
                                                    // Set the value
                                                    attrSelN.value = String(attributeId);
                                                    
                                                    // Update Choices.js if initialized
                                                    if (attrSelN._choices) {
                                                        try {
                                                            attrSelN._choices.setChoiceByValue(String(attributeId));
                                                        } catch (e) {
                                                            console.error('Error setting Choices.js value:', e);
                                                        }
                                                    }
                                                    
                                                    // Trigger change event to ensure proper initialization
                                                    attrSelN.dispatchEvent(new Event('change'));
                                                    
                                                    // Wait for variants to populate
                                                    await populateVariants(varSelN, attributeId, newSec);
                                                    
                                                    if (spec.variants && Array.isArray(spec.variants) && spec.variants.length) {
                                                        // Check attribute type
                                                        const attributeType = newSec.dataset.attributeType || 'multi';
                                                        
                                                        if (attributeType === 'text') {
                                                            // For text attributes, only add variants that are text variants
                                                            // Skip old single/multi variants (they don't have is_text_variant flag)
                                                            for (const variant of spec.variants) {
                                                                // Only process if it's a text variant
                                                                if (variant.is_text_variant === true) {
                                                                    const variantName = variant.name;
                                                                    const variantId = variant.id;
                                                                    if (variantName) {
                                                                        await addTextVariant(newSec, variantName, variantId);
                                                                        await new Promise(r => setTimeout(r, 50));
                                                                    }
                                                                } else {
                                                                    // Old single/multi variant - skip it or optionally show name
                                                                    // If we want to show it, we need to get the value name
                                                                    // For now, skip it as per requirement
                                                                    console.log('Skipping non-text variant for text attribute:', variant.id);
                                                                }
                                                            }
                                                        } else {
                                                            // Wait a bit more for Choices.js to initialize
                                                            await new Promise(r => setTimeout(r, 200));
                                                            
                                                            // For single-select, only use the first variant
                                                            const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                                ? [spec.variants[0]] 
                                                                : spec.variants;
                                                            
                                                            if (varSelN._choices) {
                                                                varSelN._choices.setChoiceByValue(variantsToSet.map(v => String(v.id)));
                                                            } else {
                                                                variantsToSet.forEach(variant => {
                                                                    const option = varSelN.querySelector(`option[value="${variant.id}"]`);
                                                                    if (option) option.selected = true;
                                                                });
                                                            }
                                                            
                                                            varSelN.dispatchEvent(new Event('change'));
                                                        }
                                                    }
                                                        
                                                        const radiosN = getYesNoRadios(newSec);
                                                        // Check if we have price data in spec or in existingPriceData
                                                        const hasPriceInSpecN = spec.variants && spec.variants.some(v => v.mrp || v.selling_price);
                                                        const hasPriceInStoredN = spec.variants && spec.variants.some(v => {
                                                            const variantId = v.id;
                                                            return window.existingPriceData && window.existingPriceData[variantId] && 
                                                                   (window.existingPriceData[variantId].mrp || window.existingPriceData[variantId].selling_price);
                                                        });
                                                        const hasPriceDataN = hasPriceInSpecN || hasPriceInStoredN;
                                                        
                                                        // Handle price data if it exists
                                                        if (hasPriceDataN && radiosN.yes) {
                                                            console.log('Price data found, selecting Yes radio for additional section', index);
                                                            if (radiosN.no) radiosN.no.checked = false;
                                                            radiosN.yes.checked = true;
                                                            radiosN.yes.dispatchEvent(new Event('change'));
                                                            
                                                            // Wait for price rows to render
                                                            let attemptsN = 0;
                                                            const maxAttemptsN = 30;
                                                            const waitForPriceRowsN = () => {
                                                                return new Promise((resolve) => {
                                                                    const checkPriceRows = () => {
                                                                        attemptsN++;
                                                                        const priceContainer = newSec.querySelector('.variant-price-rows');
                                                                        const priceInputs = priceContainer ? priceContainer.querySelectorAll('input[data-variant-id]') : [];
                                                                        if (priceInputs.length > 0 || attemptsN >= maxAttemptsN) {
                                                                            resolve();
                                                                        } else {
                                                                            setTimeout(checkPriceRows, 100);
                                                                        }
                                                                    };
                                                                    checkPriceRows();
                                                                });
                                                            };
                                                            await waitForPriceRowsN();
                                                            
                                                            // Now fill in the prices
                                                            spec.variants.forEach(variant => {
                                                                const variantId = variant.id;
                                                                // Get price from spec first, then from stored data
                                                                const mrp = variant.mrp || (window.existingPriceData && window.existingPriceData[variantId]?.mrp);
                                                                const sellingPrice = variant.selling_price || (window.existingPriceData && window.existingPriceData[variantId]?.selling_price);
                                                                
                                                                if (mrp || sellingPrice) {
                                                                    const mrpInput = newSec.querySelector(`input[data-variant-id="${variantId}"][data-field="mrp"]`);
                                                                    const spInput = newSec.querySelector(`input[data-variant-id="${variantId}"][data-field="selling_price"]`);
                                                                    if (mrpInput && mrp) {
                                                                        mrpInput.value = mrp;
                                                                        mrpInput.dispatchEvent(new Event('input'));
                                                                    }
                                                                    if (spInput && sellingPrice) {
                                                                        spInput.value = sellingPrice;
                                                                        spInput.dispatchEvent(new Event('input'));
                                                                    }
                                                                }
                                                            });
                                                        } else {
                                                            if (radiosN.no) radiosN.no.checked = true;
                                                        }
                                                        try { renderPreview(); } catch (e) { }
                                                    }
                                                }
                                            } catch (e) {
                                                console.error('Error creating section for spec', index, ':', e);
                                            }
                                        }
                                    };
                                    // Load specs sequentially
                                    for (let i = 0; i < specsToLoad.length; i++) {
                                        await loadSpecIntoSection(specsToLoad[i], i);
                                        if (i < specsToLoad.length - 1) {
                                            await new Promise(r => setTimeout(r, 300));
                                        }
                                    }
                                    
                                    // Clean up any blank sections (sections with no attribute selected)
                                    setTimeout(() => {
                                        const allSections = document.querySelectorAll('.spec-section');
                                        allSections.forEach(section => {
                                            // Skip mandatory sections
                                            if (section.classList.contains('mandatory-section')) return;
                                            
                                            const attrSelect = getAttrSelect(section);
                                            if (attrSelect) {
                                                const selectedValue = attrSelect.value;
                                                // If no attribute is selected and it's not the first section, remove it
                                                if ((!selectedValue || selectedValue === '' || selectedValue === 'select attribute') && section !== firstSection) {
                                                    console.log('Removing blank section');
                                                    const hr = section.nextElementSibling;
                                                    if (hr && hr.tagName === 'HR') {
                                                        hr.remove();
                                                    }
                                                    section.remove();
                                                }
                                            }
                                        });
                                        renderPreview();
                                    }, 1000);
                                    // After loading, if we have a category, ensure mandatory sections are present and locked
                                    if (categoryId) {
                                        try {
                                            // Prioritize subcategory over category for mandatory attributes
                                            let apiUrl = '<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?';
                                            const subcategoryId = document.getElementById('Light-SubCategory')?.value;
                                            if (subcategoryId) {
                                                apiUrl += 'sub_category_id=' + encodeURIComponent(subcategoryId);
                                            } else {
                                                apiUrl += 'category_id=' + encodeURIComponent(categoryId);
                                            }
                                            const resp = await fetch(apiUrl);
                                            const data = await resp.json();
                                            if (data && Array.isArray(data.mandatory) && data.mandatory.length) {
                                                const mandatorySet = new Set(data.mandatory.map(x => parseInt(x, 10)));
                                                // Create missing mandatory sections
                                                const presentSet = new Set(specsToLoad.map(s => parseInt(s.attribute_id, 10)).filter(Boolean));
                                                for (const mid of mandatorySet) {
                                                    if (!presentSet.has(mid)) {
                                                        await createMandatorySection(mid, null, false);
                                                    }
                                                }
                                                // Lock mandatory sections
                                                document.querySelectorAll('.spec-section').forEach(sec => {
                                                    const asel = getAttrSelect(sec);
                                                    const aid = asel ? parseInt(asel.value, 10) : 0;
                                                    if (aid && mandatorySet.has(aid)) {
                                                        sec.classList.add('mandatory-section');
                                                        // Check if we're in edit mode
                                                        const isEditMode = <?php echo !empty($editing_product_id) ? 'true' : 'false'; ?>;
                                                        // Always disable dropdown for mandatory attributes in both modes
                                                        asel.disabled = true;
                                                        asel.style.backgroundColor = '#f8f9fa';
                                                        asel.style.cursor = 'not-allowed';
                                                        const lab = sec.querySelector('label');
                                                        if (lab && !lab.querySelector('.mandatory-indicator')) {
                                                            const indicator = document.createElement('span');
                                                            indicator.className = 'mandatory-indicator text-warning ms-1';
                                                            indicator.innerHTML = '<i class="fa fa-lock"></i>';
                                                            indicator.title = 'Mandatory attribute';
                                                            lab.appendChild(indicator);
                                                        }
                                                        // Hide delete button for mandatory sections
                                                        const delBtn = sec.querySelector('.btn-delete-section');
                                                        if (delBtn) { delBtn.style.display = 'none'; delBtn.disabled = true; }
                                                    }
                                                });
                                                MANDATORY_SECTIONS_CREATED = true;
                                            }
                                        } catch (e) { console.error('Mandatory ensure (new) failed', e); }
                                    }
                                    // Refresh preview
                                    renderPreview();
                                    // Wait a bit for all sections to be fully rendered
                                    setTimeout(() => {
                                        markSpecsInitialized();
                                    }, 1000);
                                } catch (e) { console.error('Prefill specs failed', e); }
                            })();
                    }
                    // Ensure a dashed divider exists after the initial section
                    const after = firstSection && firstSection.nextElementSibling;
                    if (firstSection && !(after && after.tagName === 'HR')) {
                        const hr = document.createElement('hr');
                        hr.className = 'mt-0';
                        hr.setAttribute('style', 'border-top: 1px dashed #d1d5db; margin: 32px 0;');
                        firstSection.parentNode.insertBefore(hr, document.querySelector('form[method="POST"]').parentNode);
                    }
                    // Pre-fill existing specifications if editing
                    // Prioritize localStorage over session/database
                    <?php if (!empty($editing_product_id)): ?>
                        let existingSpecs = null;
                        
                        // First, check localStorage
                        if (localStorageSpecs && Array.isArray(localStorageSpecs) && localStorageSpecs.length > 0) {
                            existingSpecs = localStorageSpecs;
                            console.log('Using specifications from localStorage:', existingSpecs);
                        } else if (<?php 
                            $session_specs_key = 'existing_specifications_' . $editing_product_id;
                            $session_specs_key_admin = 'existing_specifications_admin_' . $editing_product_id;
                            // Check admin key first, then regular key
                            $has_specs = isset($_SESSION[$session_specs_key_admin]) || isset($_SESSION[$session_specs_key]);
                            echo $has_specs ? 'true' : 'false'; 
                        ?>) {
                            // Fallback to session data (using product ID-specific key, check admin version first)
                            existingSpecs = <?php 
                                $session_specs_key = 'existing_specifications_' . $editing_product_id;
                                $session_specs_key_admin = 'existing_specifications_admin_' . $editing_product_id;
                                // Check admin key first, then regular key
                                $specs_data = isset($_SESSION[$session_specs_key_admin]) ? $_SESSION[$session_specs_key_admin] : (isset($_SESSION[$session_specs_key]) ? $_SESSION[$session_specs_key] : null);
                                echo $specs_data ? json_encode($specs_data) : 'null'; 
                            ?>;
                            console.log('Using specifications from session:', existingSpecs);
                        }
                        
                        if (existingSpecs && Array.isArray(existingSpecs) && existingSpecs.length > 0) {
                            // Prevent multiple loading
                            if (window.specificationsLoaded) {
                                //console.log('Specifications already loaded, skipping...');
                                return;
                            }
                            window.specificationsLoaded = true;
                            // Create a global object to store existing price data for reference
                            window.existingPriceData = {};
                            existingSpecs.forEach(spec => {
                                if (spec.variants && Array.isArray(spec.variants)) {
                                    spec.variants.forEach(variant => {
                                        if (variant.id) {
                                            window.existingPriceData[variant.id] = {
                                                mrp: variant.mrp || null,
                                                selling_price: variant.selling_price || null
                                            };
                                        }
                                    });
                                }
                            });
                            //console.log('Created existing price data reference:', window.existingPriceData);
                            //console.log('Loading existing specifications:', existingSpecs);
                            //console.log('Number of specifications to load:', existingSpecs.length);
                            // Clear existing sections first
                            document.querySelectorAll('.spec-section').forEach(section => {
                                if (section !== firstSection) {
                                    section.remove();
                                }
                            });
                            // Create sections for each existing specification
                            const loadSpec = async (spec, index) => {
                                //console.log(`Loading spec ${index}:`, spec);
                                // Check if this attribute is mandatory
                                const isMandatory = MANDATORY_ATTRIBUTES.includes(parseInt(attributeId));
                                //console.log(`Attribute ${attributeId} is mandatory:`, isMandatory);
                                if (index === 0) {
                                    // Use the first section
                                    const attrSel = getAttrSelect(firstSection);
                                    const varSel = getVariantsSelect(firstSection);
                                    try {
                                        if (firstSection.hasAttribute('hidden')) firstSection.removeAttribute('hidden');
                                        if (firstSection.style && firstSection.style.display === 'none') firstSection.style.display = '';
                                    } catch (e) { }
                                    // Initialize the first section properly based on whether it's mandatory
                                    if (isMandatory) {
                                        //console.log(`First section is mandatory, initializing as mandatory`);
                                        initOneSection(firstSection, true, attributeId);
                                    } else {
                                        //console.log(`First section is non-mandatory, initializing as non-mandatory`);
                                        initOneSection(firstSection, false);
                                    }
                                    if (attrSel && attributeId) {
                                        // First, ensure the attribute name is available in the dropdown
                                        await ensureAttributeInDropdown(attrSel, attributeId);
                                        //console.log(`Setting attribute ${attributeId} for first section`);
                                        console.log(`Dropdown options before setting value:`, Array.from(attrSel.options).map(opt => ({ value: opt.value, text: opt.textContent })));
                                        attrSel.value = attributeId;
                                        //console.log(`Dropdown value after setting:`, attrSel.value);
                                        //console.log(`Selected option text:`, attrSel.selectedOptions[0]?.textContent);
                                        // Force Choices.js to update if it's initialized
                                        if (attrSel._choices) {
                                            //console.log('Updating Choices.js with new value');
                                            attrSel._choices.setChoiceByValue(attributeId);
                                        }
                                        // Wait for variants to populate
                                        await populateVariants(varSel, attributeId, firstSection);
                                        //console.log(`Populated variants for attribute ${attributeId}`);
                                        if (spec.variants && Array.isArray(spec.variants)) {
                                            const attributeType = firstSection.dataset.attributeType || 'multi';
                                            // Skip for text attributes (handled in populateVariants)
                                            if (attributeType !== 'text') {
                                                // Wait longer for Choices.js to initialize properly
                                                setTimeout(() => {
                                                    // For single-select, only use the first variant
                                                    const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                        ? [spec.variants[0]] 
                                                        : spec.variants;
                                                    
                                                    console.log(`Setting variants for spec ${index}:`, variantsToSet.map(v => v.id));
                                                    if (varSel._choices) {
                                                        // Use Choices.js API to set selected values
                                                        varSel._choices.setChoiceByValue(variantsToSet.map(v => String(v.id)));
                                                        console.log('Set variants using Choices.js API');
                                                    } else {
                                                        // Fallback to native select
                                                        variantsToSet.forEach(variant => {
                                                            const option = varSel.querySelector(`option[value="${variant.id}"]`);
                                                            if (option) {
                                                                option.selected = true;
                                                                //console.log(`Selected variant option: ${variant.id}`);
                                                            }
                                                        });
                                                    }
                                                    // Trigger change event to update UI
                                                    varSel.dispatchEvent(new Event('change'));
                                                }
                                                // Handle price data if it exists
                                                if (spec.variants && spec.variants.some(v => v.mrp || v.selling_price)) {
                                                    console.log('Price data found, automatically setting Yes radio and filling prices');
                                                    const radios = getYesNoRadios(firstSection);
                                                    if (radios.yes) {
                                                        // Uncheck the No radio first
                                                        if (radios.no) {
                                                            radios.no.checked = false;
                                                        }
                                                        // Check the Yes radio
                                                        radios.yes.checked = true;
                                                        // Trigger the change event to render price rows
                                                        radios.yes.dispatchEvent(new Event('change'));
                                                        // Wait for price rows to render and then fill them
                                                        const fillPriceData = () => {
                                                            const priceContainer = firstSection.querySelector('.variant-price-rows');
                                                            if (priceContainer && priceContainer.children.length > 0) {
                                                                console.log('Price rows found, filling price data for variants:', spec.variants);
                                                                spec.variants.forEach(variant => {
                                                                    if (variant.mrp || variant.selling_price) {
                                                                        const mrpInput = firstSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                        const spInput = firstSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                        //console.log(`Filling prices for variant ${variant.id}:`, {mrp: variant.mrp, selling_price: variant.selling_price});
                                                                        if (mrpInput && variant.mrp) {
                                                                            mrpInput.value = variant.mrp;
                                                                            //console.log(`Set MRP for variant ${variant.id}: ${variant.mrp}`);
                                                                        }
                                                                        if (spInput && variant.selling_price) {
                                                                            spInput.value = variant.selling_price;
                                                                            //console.log(`Set Selling Price for variant ${variant.id}: ${variant.selling_price}`);
                                                                        }
                                                                    }
                                                                });
                                                            } else {
                                                                //console.log('Price rows not ready yet, retrying...');
                                                                setTimeout(fillPriceData, 100);
                                                            }
                                                        };
                                                        setTimeout(fillPriceData, 200);
                                                    }
                                                } else {
                                                    // If no price data, ensure No radio is selected
                                                    const radios = getYesNoRadios(firstSection);
                                                    if (radios.no) {
                                                        radios.no.checked = true;
                                                    }
                                                }
                                            }, 100);
                                        }
                                    }
                                } else {
                                    // Create new sections for additional specs
                                    console.log(`Creating new section for spec ${index}, isMandatory: ${isMandatory}`);
                                    let newSection;
                                    if (isMandatory) {
                                        // Create mandatory section directly
                                        console.log(`Creating mandatory section for attribute ${attributeId}`);
                                        newSection = await createMandatorySection(attributeId, null, false);
                                    } else {
                                        // Use Add More button for non-mandatory sections
                                        const addMoreBtn = document.querySelector('button.btn-light.btn-wave.mb-2');
                                        if (addMoreBtn) {
                                            // Trigger the add more button click
                                            console.log(`Clicking Add More button for spec ${index}`);
                                            addMoreBtn.click();
                                            // Wait for the new section to be created and then populate it
                                            const waitForNewSection = () => {
                                                return new Promise((resolve, reject) => {
                                                    let attempts = 0;
                                                    const maxAttempts = 100; // 5 seconds max wait time
                                                    const checkForSection = () => {
                                                        attempts++;
                                                        const sections = document.querySelectorAll('.spec-section');
                                                        //console.log(`Checking for section ${index}, found ${sections.length} sections (attempt ${attempts})`);
                                                        if (sections.length > index) {
                                                            //console.log(`Section ${index} found, resolving`);
                                                            resolve(sections[index]);
                                                        } else if (attempts >= maxAttempts) {
                                                            console.error(`Timeout waiting for section ${index} to be created`);
                                                            reject(new Error(`Section ${index} not created within timeout`));
                                                        } else {
                                                            //console.log(`Section ${index} not found yet, retrying...`);
                                                            setTimeout(checkForSection, 50);
                                                        }
                                                    };
                                                    checkForSection();
                                                });
                                            };
                                            newSection = await waitForNewSection();
                                        }
                                    }
                                    // Populate the section (whether mandatory or non-mandatory)
                                    if (newSection) {
                                        //console.log(`New section created for spec ${index}:`, newSection);
                                        //console.log(`New section UID:`, newSection.dataset.specUid);
                                        try {
                                            if (newSection.hasAttribute('hidden')) newSection.removeAttribute('hidden');
                                            if (newSection.style && newSection.style.display === 'none') newSection.style.display = '';
                                        } catch (e) { }
                                        const attrSel = getAttrSelect(newSection);
                                        const varSel = getVariantsSelect(newSection);
                                        if (attrSel && attributeId) {
                                            // First, ensure the attribute name is available in the dropdown
                                            await ensureAttributeInDropdown(attrSel, attributeId);
                                            console.log(`Setting attribute ${attributeId} for additional section ${index}`);
                                            attrSel.value = attributeId;
                                            // Force Choices.js to update if it's initialized
                                            if (attrSel._choices) {
                                                console.log('Updating Choices.js with new value for additional section');
                                                attrSel._choices.setChoiceByValue(attributeId);
                                            }
                                            await populateVariants(varSel, attributeId, newSection);
                                            console.log(`Populated variants for additional section ${index}`);
                                            if (spec.variants && Array.isArray(spec.variants)) {
                                                const attributeType = newSection.dataset.attributeType || 'multi';
                                                // Skip for text attributes (handled in populateVariants)
                                                if (attributeType !== 'text') {
                                                    // Wait longer for Choices.js to initialize properly
                                                    setTimeout(() => {
                                                        // For single-select, only use the first variant
                                                        const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                            ? [spec.variants[0]] 
                                                            : spec.variants;
                                                        
                                                        console.log(`Setting variants for additional section ${index}:`, variantsToSet.map(v => v.id));
                                                        if (varSel._choices) {
                                                            // Use Choices.js API to set selected values
                                                            varSel._choices.setChoiceByValue(variantsToSet.map(v => String(v.id)));
                                                        console.log(`Set variants using Choices.js API for section ${index}`);
                                                    } else {
                                                        // Fallback to native select
                                                        spec.variants.forEach(variant => {
                                                            const option = varSel.querySelector(`option[value="${variant.id}"]`);
                                                            if (option) {
                                                                option.selected = true;
                                                                console.log(`Selected variant option for section ${index}: ${variant.id}`);
                                                            }
                                                        });
                                                    }
                                                    // Trigger change event to update UI
                                                    varSel.dispatchEvent(new Event('change'));
                                                    // Handle price data if it exists
                                                    if (spec.variants && spec.variants.some(v => v.mrp || v.selling_price)) {
                                                        //console.log('Price data found for additional section, automatically setting Yes radio and filling prices');
                                                        //console.log('Spec variants with price data:', spec.variants);
                                                        const radios = getYesNoRadios(newSection);
                                                        //console.log('Radios found for additional section:', radios);
                                                        if (radios.yes) {
                                                            // Uncheck the No radio first
                                                            if (radios.no) {
                                                                radios.no.checked = false;
                                                            }
                                                            // Check the Yes radio
                                                            radios.yes.checked = true;
                                                            //console.log('Set Yes radio to checked for additional section');
                                                            // Trigger the change event to render price rows
                                                            radios.yes.dispatchEvent(new Event('change'));
                                                            // Wait for price rows to render and then fill them
                                                            const fillPriceDataAdditional = () => {
                                                                const priceContainer = newSection.querySelector('.variant-price-rows');
                                                                if (priceContainer && priceContainer.children.length > 0) {
                                                                    //console.log('Price rows found for additional section, filling price data for variants:', spec.variants);
                                                                    spec.variants.forEach(variant => {
                                                                        if (variant.mrp || variant.selling_price) {
                                                                            const mrpInput = newSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                            const spInput = newSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                            //console.log(`Filling prices for variant ${variant.id} in additional section:`, {mrp: variant.mrp, selling_price: variant.selling_price});
                                                                            if (mrpInput && variant.mrp) {
                                                                                mrpInput.value = variant.mrp;
                                                                                //console.log(`Set MRP for variant ${variant.id}: ${variant.mrp}`);
                                                                            }
                                                                            if (spInput && variant.selling_price) {
                                                                                spInput.value = variant.selling_price;
                                                                                //console.log(`Set Selling Price for variant ${variant.id}: ${variant.selling_price}`);
                                                                            }
                                                                        }
                                                                    });
                                                                } else {
                                                                    //console.log('Price rows not ready yet for additional section, retrying...');
                                                                    setTimeout(fillPriceDataAdditional, 100);
                                                                }
                                                            };
                                                            setTimeout(fillPriceDataAdditional, 200);
                                                        }
                                                    } else {
                                                        // If no price data, ensure No radio is selected
                                                        const radios = getYesNoRadios(newSection);
                                                        if (radios.no) {
                                                            radios.no.checked = true;
                                                        }
                                                    }
                                                }, 100);
                                            }
                                        }
                                    }
                                }
                            }
                        };
                        // Load all specifications sequentially to ensure proper section creation
                        const loadAllSpecs = async () => {
                            //console.log(`Starting to load ${existingSpecs.length} specifications`);
                            // First, load mandatory attributes if not already loaded
                            if (MANDATORY_ATTRIBUTES.length === 0) {
                                //console.log('Loading mandatory attributes first...');
                                const categoryId = '<?php echo htmlspecialchars($edit_category_id ?? ''); ?>';
                                const subcategoryId = '<?php echo htmlspecialchars($edit_sub_category_id ?? ''); ?>';
                                if (categoryId || subcategoryId) {
                                    try {
                                        // Prioritize subcategory over category
                                        let apiUrl = '<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?';
                                        if (subcategoryId) {
                                            apiUrl += 'sub_category_id=' + encodeURIComponent(subcategoryId);
                                        } else {
                                            apiUrl += 'category_id=' + encodeURIComponent(categoryId);
                                        }
                                        const resp = await fetch(apiUrl);
                                        const data = await resp.json();
                                        // console.log('Mandatory attributes response:', data);
                                        if (data && Array.isArray(data.mandatory) && data.mandatory.length > 0) {
                                            MANDATORY_ATTRIBUTES = data.mandatory;
                                            // console.log('Mandatory attribute IDs loaded:', Array.from(MANDATORY_ATTRIBUTES));
                                            // console.log('Mandatory attribute names:', MANDATORY_ATTRIBUTES.map(id => getAttributeName(parseInt(id))));
                                        }
                                    } catch (e) {
                                        // console.error('Failed to load mandatory attributes:', e);
                                    }
                                }
                            }
                            try {
                                for (let i = 0; i < existingSpecs.length; i++) {
                                    //console.log`Loading specification ${i + 1} of ${existingSpecs.length}`);
                                    const spec = existingSpecs[i];
                                    const index = i;
                                    //console.log`Loading spec ${index}:`, spec);
                                    // Validate spec data
                                    if (!spec || !spec.attribute_id) {
                                        console.error(`Invalid spec data at index ${index}:`, spec);
                                        continue;
                                    }
                                    // Check if this attribute is mandatory
                                    const attributeId = parseInt(spec.attribute_id);
                                    //console.log`Parsed attribute ID: ${attributeId} from ${spec.attribute_id}`);
                                    if (isNaN(attributeId)) {
                                        console.error(`Invalid attribute ID: ${spec.attribute_id} for spec ${index}`);
                                        continue;
                                    }
                                    const isMandatory = MANDATORY_ATTRIBUTES.includes(attributeId);
                                    //console.log`Attribute ${attributeId} is mandatory:`, isMandatory);
                                    // Load both mandatory and optional attributes to preserve all selections
                                    if (index === 0) {
                                        // Use the first section
                                        const attrSel = getAttrSelect(firstSection);
                                        const varSel = getVariantsSelect(firstSection);
                                        try {
                                            if (firstSection.hasAttribute('hidden')) firstSection.removeAttribute('hidden');
                                            if (firstSection.style && firstSection.style.display === 'none') firstSection.style.display = '';
                                        } catch (e) { }
                                        // Initialize the first section properly based on whether it's mandatory
                                        if (isMandatory) {
                                            //console.log`First section is mandatory, initializing as mandatory`);
                                            initOneSection(firstSection, true, attributeId);
                                        } else {
                                            //console.log`First section is non-mandatory, initializing as non-mandatory`);
                                            initOneSection(firstSection, false);
                                        }
                                        if (attrSel && attributeId) {
                                            // First, ensure the attribute name is available in the dropdown
                                            await ensureAttributeInDropdown(attrSel, attributeId);
                                            //console.log`Setting attribute ${attributeId} for first section`);
                                            attrSel.value = attributeId;
                                            // Force Choices.js to update if it's initialized
                                            if (attrSel._choices) {
                                                //console.log'Updating Choices.js with new value for first section');
                                                attrSel._choices.setChoiceByValue(attributeId);
                                            }
                                            // Wait for variants to populate
                                            await populateVariants(varSel, attributeId, firstSection);
                                            //console.log`Populated variants for attribute ${attributeId}`);
                                            if (spec.variants && Array.isArray(spec.variants)) {
                                                const attributeType = firstSection.dataset.attributeType || 'multi';
                                                // Skip for text attributes (handled in populateVariants)
                                                if (attributeType !== 'text') {
                                                    // Set variants and handle price data
                                                    setTimeout(() => {
                                                        // For single-select, only use the first variant
                                                        const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                            ? [spec.variants[0]] 
                                                            : spec.variants;
                                                        
                                                        //console.log`Setting variants for spec ${index}:`, variantsToSet.map(v => v.id));
                                                        if (varSel._choices) {
                                                            varSel._choices.setChoiceByValue(variantsToSet.map(v => String(v.id)));
                                                            //console.log'Set variants using Choices.js API');
                                                        }
                                                        // Handle price data if it exists
                                                        if (spec.variants && spec.variants.some(v => v.mrp || v.selling_price)) {
                                                            //console.log'Price data found, automatically setting Yes radio and filling prices');
                                                            const radios = getYesNoRadios(firstSection);
                                                            if (radios.yes) {
                                                                if (radios.no) radios.no.checked = false;
                                                                radios.yes.checked = true;
                                                                radios.yes.dispatchEvent(new Event('change'));
                                                                setTimeout(() => {
                                                                    const priceRows = firstSection.querySelectorAll('.price-row-container .row');
                                                                    if (priceRows.length > 0) {
                                                                        //console.log`Price rows found, filling price data for variants:`, spec.variants);
                                                                        spec.variants.forEach(variant => {
                                                                            const mrpInput = firstSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                            const sellingInput = firstSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                            if (mrpInput && variant.mrp) mrpInput.value = variant.mrp;
                                                                            if (sellingInput && variant.selling_price) sellingInput.value = variant.selling_price;
                                                                        });
                                                                    }
                                                                }, 100);
                                                            }
                                                        }
                                                    }, 100);
                                                }
                                            }
                                        }
                                    } else {
                                        // Create new sections for additional specs
                                        //console.log`Creating new section for spec ${index}, isMandatory: ${isMandatory}`);
                                        let newSection;
                                        if (isMandatory) {
                                            // Create mandatory section directly
                                            //console.log`Creating mandatory section for attribute ${attributeId}`);
                                            newSection = await createMandatorySection(attributeId, null, false);
                                        } else {
                                            // Use Add More button for non-mandatory sections
                                            const addMoreBtn = document.querySelector('button.btn-light.btn-wave.mb-2');
                                            if (addMoreBtn) {
                                                //console.log`Clicking Add More button for spec ${index}`);
                                                addMoreBtn.click();
                                                const waitForNewSection = () => {
                                                    return new Promise((resolve, reject) => {
                                                        let attempts = 0;
                                                        const maxAttempts = 100;
                                                        const checkForSection = () => {
                                                            attempts++;
                                                            const sections = document.querySelectorAll('.spec-section');
                                                            if (sections.length > index) {
                                                                resolve(sections[index]);
                                                            } else if (attempts >= maxAttempts) {
                                                                console.error(`Timeout waiting for section ${index} to be created`);
                                                                reject(new Error(`Section ${index} not created within timeout`));
                                                            } else {
                                                                setTimeout(checkForSection, 50);
                                                            }
                                                        };
                                                        checkForSection();
                                                    });
                                                };
                                                newSection = await waitForNewSection();
                                            }
                                        }
                                        // Populate the section (whether mandatory or non-mandatory)
                                        if (newSection) {
                                            try {
                                                if (newSection.hasAttribute('hidden')) newSection.removeAttribute('hidden');
                                                if (newSection.style && newSection.style.display === 'none') newSection.style.display = '';
                                            } catch (e) { }
                                            const attrSel = getAttrSelect(newSection);
                                            const varSel = getVariantsSelect(newSection);
                                            if (attrSel && attributeId) {
                                                // First, ensure the attribute name is available in the dropdown
                                                await ensureAttributeInDropdown(attrSel, attributeId);
                                                //console.log`Setting attribute ${attributeId} for additional section ${index}`);
                                                attrSel.value = attributeId;
                                                // Force Choices.js to update if it's initialized
                                                if (attrSel._choices) {
                                                    //console.log'Updating Choices.js with new value for additional section');
                                                    attrSel._choices.setChoiceByValue(attributeId);
                                                }
                                                await populateVariants(varSel, attributeId, newSection);
                                                //console.log`Populated variants for additional section ${index}`);
                                                if (spec.variants && Array.isArray(spec.variants)) {
                                                    const attributeType = newSection.dataset.attributeType || 'multi';
                                                    // Skip for text attributes (handled in populateVariants)
                                                    if (attributeType !== 'text') {
                                                        setTimeout(() => {
                                                            // For single-select, only use the first variant
                                                            const variantsToSet = (attributeType === 'single' && spec.variants.length > 0) 
                                                                ? [spec.variants[0]] 
                                                                : spec.variants;
                                                            
                                                            //console.log`Setting variants for additional section ${index}:`, variantsToSet.map(v => v.id));
                                                            if (varSel._choices) {
                                                                varSel._choices.setChoiceByValue(variantsToSet.map(v => String(v.id)));
                                                                //console.log`Set variants using Choices.js API for section ${index}`);
                                                            }
                                                            // Handle price data
                                                            if (spec.variants && spec.variants.some(v => v.mrp || v.selling_price)) {
                                                                const radios = getYesNoRadios(newSection);
                                                                if (radios.yes) {
                                                                    if (radios.no) radios.no.checked = false;
                                                                    radios.yes.checked = true;
                                                                    radios.yes.dispatchEvent(new Event('change'));
                                                                    setTimeout(() => {
                                                                        spec.variants.forEach(variant => {
                                                                            const mrpInput = newSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="mrp"]`);
                                                                            const sellingInput = newSection.querySelector(`input[data-variant-id="${variant.id}"][data-field="selling_price"]`);
                                                                            if (mrpInput && variant.mrp) mrpInput.value = variant.mrp;
                                                                            if (sellingInput && variant.selling_price) sellingInput.value = variant.selling_price;
                                                                        });
                                                                    }, 100);
                                                                }
                                                            } else {
                                                                const radios = getYesNoRadios(newSection);
                                                                if (radios.no) {
                                                                radios.no.checked = true;
                                                            }
                                                        }
                                                    }, 100);
                                                }
                                            }
                                        }
                                    }
                                    // Add a longer delay between sections to ensure proper rendering
                                    if (i < existingSpecs.length - 1) {
                                        await new Promise(resolve => setTimeout(resolve, 1000));
                                    }
                                }
                                //console.log'All specifications loaded successfully');
                                // Final refresh of preview after all specs are loaded
                                // Wait for all sections to be fully rendered before stopping loader
                                setTimeout(() => {
                                    renderPreview();
                                    // Check if all sections are loaded (including mandatory) before stopping
                                    markSpecsInitialized();
                                }, 1500);
                            } catch (error) {
                                console.error('Error loading specifications:', error);
                                markSpecsInitialized();
                            }
                        };
                        // Start loading after a short delay to ensure DOM is ready
                        setTimeout(() => {
                            loadAllSpecs();
                        }, 100);
                        // Hide the loading message after a delay
                        setTimeout(() => {
                            const loadingMsg = document.querySelector('.alert-info');
                            if (loadingMsg) {
                                loadingMsg.style.display = 'none';
                            }
                        }, 1000);
                        // Clear the session data after a delay to ensure it's been used
                        setTimeout(() => {
                            // Clear session data via AJAX to avoid race condition
                            fetch('<?php echo USER_BASEURL; ?>app/api/clear_specifications_session.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'clear=1'
                            }).catch(e => console.log('Session clear failed:', e));
                        }, 2000);
                        // After existing specs load, ensure mandatory sections exist and are locked
                        (async function ensureMandatoryAfterLoad() {
                            try {
                                // Skip if mandatory sections are already created
                                if (MANDATORY_SECTIONS_CREATED) {
                                    //console.log'Mandatory sections already created, skipping ensureMandatoryAfterLoad');
                                    return;
                                }
                                const categoryId = '<?php echo htmlspecialchars($edit_category_id ?? ''); ?>';
                                const subcategoryId = '<?php echo htmlspecialchars($edit_sub_category_id ?? ''); ?>';
                                //console.log'Category ID for mandatory check:', categoryId);
                                ////console.log'Subcategory ID for mandatory check:', subcategoryId);
                                if (!categoryId && !subcategoryId && (!Array.isArray(MANDATORY_ATTRIBUTES) || MANDATORY_ATTRIBUTES.length === 0)) {
                                    ////console.log'No category or subcategory ID found, skipping mandatory check');
                                    return;
                                }
                                let mandatorySet;
                                if (Array.isArray(MANDATORY_ATTRIBUTES) && MANDATORY_ATTRIBUTES.length > 0) {
                                    mandatorySet = new Set(MANDATORY_ATTRIBUTES.map(n => parseInt(n, 10)));
                                } else {
                                // Fetch mandatory list - prioritize subcategory over category
                                let apiUrl = '<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?';
                                if (subcategoryId) {
                                    apiUrl += 'sub_category_id=' + encodeURIComponent(subcategoryId);
                                } else {
                                    apiUrl += 'category_id=' + encodeURIComponent(categoryId);
                                }
                                const resp = await fetch(apiUrl);
                                const data = await resp.json();
                                //console.log('Mandatory attributes response:', data);
                                if (!data || !Array.isArray(data.mandatory) || data.mandatory.length === 0) {
                                    //console.log('No mandatory attributes found');
                                    return;
                                }
                                    // Update global list from API if needed
                                    MANDATORY_ATTRIBUTES = data.mandatory;
                                    mandatorySet = new Set(data.mandatory.map(n => parseInt(n, 10)));
                                }
                                //console.log('Mandatory attribute IDs:', Array.from(mandatorySet));
                                // Update MANDATORY_ATTRIBUTES for consistency
                                if (!Array.isArray(MANDATORY_ATTRIBUTES) || MANDATORY_ATTRIBUTES.length === 0) {
                                    MANDATORY_ATTRIBUTES = Array.from(mandatorySet);
                                }
                                // Collect which attributes are already present in existing specs
                                const presentSet = new Set();
                                existingSpecs.forEach(spec => { if (spec.attribute_id) presentSet.add(parseInt(spec.attribute_id, 10)); });
                                // Create missing mandatory sections
                                //console.log('Creating missing mandatory sections...');
                                //console.log('Present attributes:', Array.from(presentSet));
                                //console.log('Mandatory attributes:', Array.from(mandatorySet));
                                for (const mid of mandatorySet) {
                                    if (!presentSet.has(mid)) {
                                        // Check if section already exists in DOM
                                        const existingSection = findSectionByAttrId(mid);
                                        if (existingSection) {
                                            //console.log(`Mandatory attribute ${mid} section already exists in DOM, skipping creation`);
                                            continue;
                                        }
                                        //console.log(`Creating mandatory section for missing attribute ${mid}`);
                                        await createMandatorySection(mid, null, false);
                                    } else {
                                        //console.log(`Mandatory attribute ${mid} already exists, skipping creation`);
                                    }
                                }
                                // Lock all mandatory sections' attribute selects and add indicator
                                document.querySelectorAll('.spec-section').forEach(section => {
                                    try {
                                        if (section.hasAttribute('hidden')) section.removeAttribute('hidden');
                                        if (section.style && section.style.display === 'none') section.style.display = '';
                                    } catch (e) { }
                                    const attrSel = getAttrSelect(section);
                                    const aid = attrSel ? parseInt(attrSel.value, 10) : 0;
                                    //console.log('Processing section with attribute ID:', aid, 'Is mandatory:', mandatorySet.has(aid));
                                    if (aid && mandatorySet.has(aid)) {
                                        section.classList.add('mandatory-section');
                                        // Check if we're in edit mode
                                        const isEditMode = <?php echo !empty($editing_product_id) ? 'true' : 'false'; ?>;
                                        // Always disable dropdown for mandatory attributes in both modes
                                        attrSel.disabled = true;
                                        attrSel.style.backgroundColor = '#f8f9fa';
                                        attrSel.style.cursor = 'not-allowed';
                                        const label = section.querySelector('label');
                                        if (label && !label.querySelector('.mandatory-indicator')) {
                                            const indicator = document.createElement('span');
                                            indicator.className = 'mandatory-indicator text-warning ms-1';
                                            indicator.innerHTML = '<i class="fa fa-lock"></i>';
                                            indicator.title = 'Mandatory attribute';
                                            label.appendChild(indicator);
                                        }
                                        const del = section.querySelector('.btn-delete-section');
                                        if (del) { del.style.display = 'none'; del.disabled = true; }
                                    }
                                });
                                // Mark that mandatory sections are ready so Add More works
                                MANDATORY_SECTIONS_CREATED = true;
                                // Refresh preview to reflect mandatory indicators
                                renderPreview();
                            } catch (e) { console.error('Mandatory ensure failed', e); }
                        })();
                        // Add event listeners to update existing price data when prices are changed
                        document.addEventListener('input', function (e) {
                            if (e.target.matches('input[data-variant-id][data-field]')) {
                                const variantId = e.target.dataset.variantId;
                                const field = e.target.dataset.field;
                                const value = parseFloat(e.target.value.replace(/[^0-9.]/g, ''));
                                if (variantId && field && !isNaN(value) && value > 0) {
                                    if (!window.existingPriceData[variantId]) {
                                        window.existingPriceData[variantId] = {};
                                    }
                                    window.existingPriceData[variantId][field] = value;
                                    ////console.log(`Updated existing price data for variant ${variantId}:`, window.existingPriceData[variantId]);
                                }
                            }
                        });
                        // Add event listener to preserve price data when variations are removed
                        document.addEventListener('change', function (e) {
                            if (e.target.matches('select[multiple]') && e.target._choices) {
                                // This is a Choices.js select, check if variants were removed
                                const section = e.target.closest('.spec-section');
                                if (section) {
                                    const priceContainer = section.querySelector('.variant-price-rows');
                                    if (priceContainer) {
                                        // Get all current price inputs before they might be removed
                                        const currentInputs = Array.from(priceContainer.querySelectorAll('input[data-variant-id]'));
                                        // Update global price data with current values
                                        currentInputs.forEach(input => {
                                            const variantId = input.dataset.variantId;
                                            const field = input.dataset.field;
                                            const value = parseFloat(input.value.replace(/[^0-9.]/g, ''));
                                            if (variantId && field && !isNaN(value) && value > 0) {
                                                if (!window.existingPriceData[variantId]) {
                                                    window.existingPriceData[variantId] = {};
                                                }
                                                window.existingPriceData[variantId][field] = value;
                                                ////console.log(`Preserved price data for variant ${variantId}:`, window.existingPriceData[variantId]);
                                            }
                                        });
                                        // Get currently selected variant IDs
                                        const selectedOptions = Array.from(e.target.selectedOptions);
                                        const selectedVariantIds = selectedOptions.map(opt => opt.value);
                                        // //console.log('Variation change detected:', {
                                        //     currentVariantIds: currentInputs.map(input => input.dataset.variantId),
                                        //     selectedVariantIds: selectedVariantIds,
                                        //     globalPriceData: window.existingPriceData
                                        // });
                                        ////console.log('Updated global price data after variation change:', window.existingPriceData);
                                    }
                                }
                            }
                        });
                        if (!existingSpecs || !Array.isArray(existingSpecs) || existingSpecs.length === 0) {
                            markSpecsInitialized();
                        }
                    <?php endif; ?>
                    // Fallback timeout to hide loader if something goes wrong
                    setTimeout(() => {
                        if (!window.__specInitializationDone) {
                            markSpecsInitialized();
                        }
                    }, 4000);
                    // Safety check: Ensure mandatory sections exist after all initialization
                    setTimeout(async () => {
                        await ensureMandatorySectionsExist();
                    }, 3000);
                });
            </script>
            <script>
                // === PREVIEW RENDER (old UI style, show names not IDs) ===
                function renderPreview() {
                    const preview = document.getElementById('spec-preview');
                    const previewSkeleton = document.getElementById('preview-skeleton');
                    if (!preview) return;
                    // Ensure preview is visible and skeleton is hidden
                    if (previewSkeleton) {
                        previewSkeleton.style.display = 'none';
                    }
                    preview.style.display = 'block';
                    const sections = document.querySelectorAll('.spec-section');
                    if (!sections.length) { preview.innerHTML = '<div class="text-muted">Select attributes and variations to preview.</div>'; return; }
                    preview.innerHTML = '';
                    sections.forEach(section => {
                        const attrSel = getAttrSelect(section);
                        const varSel = getVariantsSelect(section);
                        if (!attrSel || !varSel) return;
                        const attrName = (attrSel.selectedOptions[0]?.textContent || '').trim();
                        
                        // Get variants based on attribute type
                        const attributeType = section.dataset.attributeType || 'multi';
                        let selectedOpts = [];
                        
                        if (attributeType === 'text') {
                            // Get text variants
                            const textVariants = getTextVariants(section);
                            selectedOpts = textVariants.map(v => ({
                                textContent: v.name
                            }));
                        } else {
                            // Get selected options from select element
                            selectedOpts = Array.from(varSel.selectedOptions || []);
                        }
                        
                        if (!attrName || !selectedOpts.length) return;
                        const block = document.createElement('div');
                        block.className = 'mb-3';
                        const row = document.createElement('div');
                        row.className = 'mb-1 d-flex align-items-start';
                        const label = document.createElement('label');
                        label.className = 'fw-semibold me-3';
                        label.style.minWidth = '60px';
                        label.textContent = attrName + ':';
                        // Add mandatory indicator if this is a mandatory section
                        if (section.classList.contains('mandatory-section')) {
                            const mandatoryIcon = document.createElement('span');
                            mandatoryIcon.className = 'text-warning ms-1';
                            mandatoryIcon.innerHTML = '<i class="fa fa-lock"></i>';
                            mandatoryIcon.title = 'Mandatory attribute';
                            label.appendChild(mandatoryIcon);
                        }
                        const btns = document.createElement('div');
                        btns.className = 'd-flex gap-2 flex-wrap';
                        selectedOpts.forEach(o => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'btn btn-outline-secondary btn-size-select';
                            btn.disabled = true;
                            btn.textContent = (o.textContent || '').trim();
                            btns.appendChild(btn);
                        });
                        row.appendChild(label);
                        row.appendChild(btns);
                        block.appendChild(row);
                        preview.appendChild(block);
                    });
                    // Ensure delete buttons are visible for non-mandatory sections only
                    try {
                        document.querySelectorAll('.spec-section:not(.mandatory-section) .btn-delete-section').forEach(btn => {
                            btn.style.display = '';
                            btn.disabled = false;
                        });
                        // Hide delete buttons for mandatory sections
                        document.querySelectorAll('.spec-section.mandatory-section .btn-delete-section').forEach(btn => {
                            btn.style.display = 'none';
                            btn.disabled = true;
                        });
                    } catch (e) { }
                }
                // Hook into interactions to update preview and save to localStorage
                document.addEventListener('change', function (e) {
                    if (e.target.closest('.spec-section')) {
                        renderPreview();
                        debouncedSaveToLocalStorage();
                    }
                });
                document.addEventListener('click', async function (e) {
                    if (e.target.closest('.btn-delete-section')) {
                        // Ensure mandatory sections still exist after deletion
                        await ensureMandatorySectionsExist();
                        renderPreview();
                        debouncedSaveToLocalStorage();
                    }
                });
                // Global listener for price inputs (handles dynamically added inputs)
                document.addEventListener('input', function (e) {
                    if (e.target.matches('input[data-variant-id][data-field]')) {
                        debouncedSaveToLocalStorage();
                    }
                });
                document.addEventListener('blur', function (e) {
                    if (e.target.matches('input[data-variant-id][data-field]')) {
                        debouncedSaveToLocalStorage();
                    }
                }, true);
                // Re-render after add more and initial load
                document.addEventListener('DOMContentLoaded', function () {
                    // Ensure preview is visible (not skeleton) for new products
                    const previewSkeleton = document.getElementById('preview-skeleton');
                    const previewContent = document.getElementById('spec-preview');
                    if (previewSkeleton) {
                        previewSkeleton.style.display = 'none';
                    }
                    if (previewContent) {
                        previewContent.style.display = 'block';
                    }
                    renderPreview();
                    const addMoreBtn = document.querySelector('button.btn-light.btn-wave.mb-2');
                    if (addMoreBtn) { addMoreBtn.addEventListener('click', () => setTimeout(renderPreview, 50)); }
                });
                
                // Save to localStorage before page unload (safety measure)
                window.addEventListener('beforeunload', function () {
                    try {
                        // Force immediate save (don't debounce)
                        saveSpecsToLocalStorage();
                    } catch (e) {
                        console.error('Error saving to localStorage on beforeunload:', e);
                    }
                });
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    new Choices('#product-s', {
                        removeItemButton: true,
                        placeholder: true,
                        placeholderValue: '',
                        searchEnabled: true,
                        allowHTML: true
                    });
                });
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.Choices) {
                        new Choices('#product-MRP', {
                            removeItemButton: true,
                            placeholder: true,
                            placeholderValue: '',
                            searchEnabled: false,
                            allowHTML: true
                        });
                    }
                });
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.Choices) {
                        new Choices('#product-MRP-1', {
                            removeItemButton: true,
                            placeholder: true,
                            placeholderValue: '',
                            searchEnabled: false,
                            allowHTML: true
                        });
                    }
                });
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.Choices) {
                        new Choices('#product-MRP-2', {
                            removeItemButton: true,
                            placeholder: true,
                            placeholderValue: '',
                            searchEnabled: false,
                            allowHTML: true
                        });
                    }
                });
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.Choices) {
                        new Choices('#product-MRP-3', {
                            removeItemButton: true,
                            placeholder: true,
                            placeholderValue: '',
                            searchEnabled: false,
                            allowHTML: true
                        });
                    }
                });
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.Choices) {
                        new Choices('#product-MRP-4', {
                            removeItemButton: true,
                            placeholder: true,
                            placeholderValue: '',
                            searchEnabled: false,
                            allowHTML: true
                        });
                    }
                });
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.Choices) {
                        new Choices('#product-s2', {
                            removeItemButton: true,
                            placeholder: true,
                            placeholderValue: '',
                            searchEnabled: false,
                            allowHTML: true
                        });
                    }
                });
            </script>
            <script>
                // Duplicate Add More handler removed - functionality is in the handler above
</body>
<script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        new Choices('#product-s', {
            removeItemButton: true,
            placeholder: true,
            placeholderValue: '',
            searchEnabled: true,
            allowHTML: true
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.Choices) {
            new Choices('#product-MRP', {
                removeItemButton: true,
                placeholder: true,
                placeholderValue: '',
                searchEnabled: false,
                allowHTML: true
            });
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.Choices) {
            new Choices('#product-MRP-1', {
                removeItemButton: true,
                placeholder: true,
                placeholderValue: '',
                searchEnabled: false,
                allowHTML: true
            });
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.Choices) {
            new Choices('#product-MRP-2', {
                removeItemButton: true,
                placeholder: true,
                placeholderValue: '',
                searchEnabled: false,
                allowHTML: true
            });
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.Choices) {
            new Choices('#product-MRP-3', {
                removeItemButton: true,
                placeholder: true,
                placeholderValue: '',
                searchEnabled: false,
                allowHTML: true
            });
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.Choices) {
            new Choices('#product-MRP-4', {
                removeItemButton: true,
                placeholder: true,
                placeholderValue: '',
                searchEnabled: false,
                allowHTML: true
            });
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.Choices) {
            new Choices('#product-s2', {
                removeItemButton: true,
                placeholder: true,
                placeholderValue: '',
                searchEnabled: false,
                allowHTML: true
            });
        }
    });
</script>

</html>

