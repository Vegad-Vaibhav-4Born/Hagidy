<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/init.php';
date_default_timezone_set('Asia/Calcutta');

require_once __DIR__ . '/../../handlers/acess_guard.php';

// Clear ALL pending product session data when navigating to product-management page
// This ensures no stale data remains from previous product add/edit sessions
unset(
    $_SESSION['pending_product_images'],
    $_SESSION['pending_product_videos'],
    $_SESSION['pending_product_video'],
    $_SESSION['pending_product_path'],
    $_SESSION['pending_product_payload'],
    $_SESSION['selected_subcategory_for_mandatory'],
    $_SESSION['selected_category_for_mandatory'],
    $_SESSION['editing_product_id']
);

// Also clear any product ID-specific session keys (for edit mode)
// Collect keys first to avoid modifying array during iteration
$keysToRemove = [];
foreach ($_SESSION as $key => $value) {
    if (preg_match('/^pending_product_(images|videos|video|path|payload)_\d+$/', $key) ||
        preg_match('/^existing_specifications_\d+$/', $key) ||
        preg_match('/^db_specs_loaded_\d+$/', $key)) {
        $keysToRemove[] = $key;
    }
}
// Remove collected keys
foreach ($keysToRemove as $key) {
    unset($_SESSION[$key]);
}

$vendor_reg_id = $_SESSION['vendor_reg_id'];

// Handle product delete (same file, no external API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product_id'])) {
    $delete_product_id = mysqli_real_escape_string($con, $_POST['delete_product_id']);
    $verify = mysqli_query($con, "SELECT id FROM products WHERE id = '$delete_product_id' AND vendor_id = '$vendor_reg_id'");
    if ($verify && mysqli_num_rows($verify) > 0) {
        mysqli_query($con, "DELETE FROM products WHERE id = '$delete_product_id' AND vendor_id = '$vendor_reg_id'");
    }
    // Redirect back to same page with current filters to avoid resubmission
    $qs = [];
    if (!empty($_GET['category'])) {
        $qs[] = 'category=' . urlencode($_GET['category']);
    }
    if (!empty($_GET['subcategory'])) {
        $qs[] = 'subcategory=' . urlencode($_GET['subcategory']);
    }
    if (!empty($_GET['search'])) {
        $qs[] = 'search=' . urlencode($_GET['search']);
    }
    if (!empty($_GET['page'])) {
        $qs[] = 'page=' . urlencode($_GET['page']);
    }
    if (!empty($_GET['per_page'])) {
        $qs[] = 'per_page=' . urlencode($_GET['per_page']);
    }
    $redir = basename(__FILE__);
    if (!empty($qs)) {
        $redir .= '?' . implode('&', $qs);
    }
    header('Location: ' . $redir);
    exit;
}

// Handle category filtering from URL
$selected_category = isset($_GET['category']) ? $_GET['category'] : '';
$selected_subcategory = isset($_GET['subcategory']) ? $_GET['subcategory'] : '';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// Build the product query
$where_conditions = ["p.vendor_id = '$vendor_reg_id'"];

if (!empty($selected_category)) {
    $where_conditions[] = "p.category_id = '$selected_category'";
}

if (!empty($selected_subcategory)) {
    $where_conditions[] = "p.sub_category_id = '$selected_subcategory'";
}

if (!empty($search_term)) {
    $where_conditions[] = "(product_name LIKE '%$search_term%' OR sku_id LIKE '%$search_term%')";
}

$where_clause = implode(' AND ', $where_conditions);

// Pagination setup
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
if ($per_page < 5) {
    $per_page = 10;
}
if ($per_page > 100) {
    $per_page = 100;
}
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

// Total count for pagination
$count_query = "SELECT COUNT(*) AS total FROM products p WHERE $where_clause";
$count_result = mysqli_query($con, $count_query);
$total_rows = 0;
if ($count_result) {
    $row = mysqli_fetch_assoc($count_result);
    $total_rows = (int) $row['total'];
}
$total_pages = max(1, (int) ceil($total_rows / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;


// Build ORDER BY clause based on sort parameter
$order_by = "p.updated_date DESC"; // default
switch ($sort_by) {
    case 'oldest':
        $order_by = "p.created_date ASC";
        break;
    case 'name_asc':
        $order_by = "p.product_name ASC";
        break;
    case 'name_desc':
        $order_by = "p.product_name DESC";
        break;
    case 'price_high':
        $order_by = "CAST(p.selling_price AS UNSIGNED) DESC";
        break;
    case 'price_low':
        $order_by = "CAST(p.selling_price AS UNSIGNED) ASC";
        break;
    case 'latest':
    default:
        $order_by = "p.created_date DESC";
        break;
}

// Export to Excel using current filters (no pagination limit)
if (isset($_GET['export']) && strtolower((string) $_GET['export']) === 'excel') {
    // Clean all existing output buffers to avoid corrupting the XLSX stream
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    } else {
        @ob_end_clean();
    }

    $autoloadPath = realpath(__DIR__ . '/../../../vendor/autoload.php');
    if ($autoloadPath && file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        // Ensure no buffered content leaks before plain text error
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }
        header('Content-Type: text/plain');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo 'Excel export packages not found. Please install PhpSpreadsheet.';
        exit;
    }

    // Query all filtered products without LIMIT - include category and subcategory names
    $export_query = "SELECT 
        p.id,
        p.sku_id,
        p.product_name,
        p.description,
        p.brand,
        p.product_brand,
        p.hsn_id,
        p.mrp,
        p.selling_price,
        p.discount,
        p.gst,
        p.coin,
        p.platform_fee,
        p.Inventory,
        p.specifications,
        p.status,
        p.created_date,
        c.name as category_name,
        sc.name as sub_category_name
    FROM products p
    LEFT JOIN category c ON p.category_id = c.id
    LEFT JOIN sub_category sc ON p.sub_category_id = sc.id
    WHERE $where_clause 
    ORDER BY $order_by";
    $export_rs = mysqli_query($con, $export_query);

    // Collect all specifications first to optimize attribute fetching
    $rows = [];
    $allAttributeIds = [];
    $allVariantIds = [];
    
    if ($export_rs) {
        while ($row = mysqli_fetch_assoc($export_rs)) {
            // Collect attribute and variant IDs from specifications
            if (!empty($row['specifications'])) {
                $specs = json_decode($row['specifications'], true);
                if (is_array($specs)) {
                    foreach ($specs as $spec) {
                        if (isset($spec['attribute_id'])) {
                            $allAttributeIds[] = (int)$spec['attribute_id'];
                        }
                        if (isset($spec['variants']) && is_array($spec['variants'])) {
                            foreach ($spec['variants'] as $variant) {
                                if (isset($variant['id'])) {
                                    // Keep variant ID as string since they're stored as strings like "val_1759080811_2"
                                    $variantId = (string)$variant['id'];
                                    if (!empty($variantId)) {
                                        $allVariantIds[] = $variantId;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            // Get brand (prefer product_brand over brand)
            $brand = !empty($row['product_brand']) ? $row['product_brand'] : ($row['brand'] ?? '');
            
            // Clean description (remove HTML tags and limit length)
            $description = !empty($row['description']) ? strip_tags($row['description']) : '';
            $description = mb_substr($description, 0, 500); // Limit to 500 characters

            $rows[] = array_merge($row, [
                'brand_final' => $brand,
                'description_clean' => $description
            ]);
        }
    }
    
    // Fetch all attribute names and variant names in one query
    $attributeNames = [];
    $variantNames = [];
    
    if (!empty($allAttributeIds)) {
        $uniqueAttributeIds = array_unique($allAttributeIds);
        $attributeIdsStr = implode(',', array_map('intval', $uniqueAttributeIds));
        $attrQuery = "SELECT id, name, attribute_values FROM attributes WHERE id IN ($attributeIdsStr)";
        $attrResult = mysqli_query($con, $attrQuery);
        
        if ($attrResult) {
            while ($attr = mysqli_fetch_assoc($attrResult)) {
                $attrId = (int)$attr['id'];
                $attributeNames[$attrId] = $attr['name'];
                
                // Parse attribute values JSON - handle different possible structures
                if (!empty($attr['attribute_values'])) {
                    $attributeValues = json_decode($attr['attribute_values'], true);
                    if (is_array($attributeValues)) {
                        foreach ($attributeValues as $value) {
                            // Try different possible key names for variant ID and name
                            // Keep variant ID as string since they're stored as strings like "val_1759080811_2"
                            $variantId = null;
                            $variantName = null;
                            
                            if (isset($value['value_id']) && isset($value['value_name'])) {
                                $variantId = (string)$value['value_id'];
                                $variantName = $value['value_name'];
                            } elseif (isset($value['id']) && isset($value['name'])) {
                                $variantId = (string)$value['id'];
                                $variantName = $value['name'];
                            } elseif (isset($value['id']) && isset($value['value_name'])) {
                                $variantId = (string)$value['id'];
                                $variantName = $value['value_name'];
                            }
                            
                            if ($variantId && $variantName) {
                                $variantNames[$variantId] = $variantName;
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Function to format specifications as readable text
    function formatSpecifications($specificationsJson, $attributeNames, $variantNames) {
        // Check if specifications is empty or null
        if (empty($specificationsJson) || $specificationsJson === '[]' || $specificationsJson === 'null' || trim($specificationsJson) === '') {
            return '';
        }
        
        // Try to decode JSON
        $specs = json_decode($specificationsJson, true);
        
        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '';
        }
        
        // Check if decoded result is an array
        if (!is_array($specs) || empty($specs)) {
            return '';
        }
        
        // Format specifications
        $formatted = [];
        foreach ($specs as $spec) {
            if (!is_array($spec)) {
                continue;
            }
            
            $attributeId = isset($spec['attribute_id']) ? (int)$spec['attribute_id'] : 0;
            $variants = isset($spec['variants']) && is_array($spec['variants']) ? $spec['variants'] : [];
            
            if ($attributeId > 0 && !empty($variants)) {
                // Get attribute name - use from array or try to get from spec
                $attributeName = '';
                if (isset($attributeNames[$attributeId]) && !empty($attributeNames[$attributeId])) {
                    $attributeName = $attributeNames[$attributeId];
                } elseif (isset($spec['attribute_name']) && !empty($spec['attribute_name'])) {
                    $attributeName = $spec['attribute_name'];
                } else {
                    // Skip if we can't get attribute name
                    continue;
                }
                
                $variantNameList = [];
                
                foreach ($variants as $variant) {
                    if (!is_array($variant)) {
                        continue;
                    }
                    
                    // Keep variant ID as string since they're stored as strings like "val_1759080811_2"
                    $variantId = isset($variant['id']) ? (string)$variant['id'] : '';
                    if (!empty($variantId)) {
                        if (isset($variantNames[$variantId]) && !empty($variantNames[$variantId])) {
                            $variantNameList[] = $variantNames[$variantId];
                        } elseif (isset($variant['name']) && !empty($variant['name'])) {
                            $variantNameList[] = $variant['name'];
                        } elseif (isset($variant['value_name']) && !empty($variant['value_name'])) {
                            $variantNameList[] = $variant['value_name'];
                        }
                    }
                }
                
                if (!empty($variantNameList)) {
                    $formatted[] = $attributeName . ': ' . implode(', ', $variantNameList);
                }
            }
        }
        
        return !empty($formatted) ? implode(' | ', $formatted) : '';
    }
    
    // Format specifications for each row
    foreach ($rows as &$row) {
        // Get specifications from row - handle null/empty cases
        $specsJson = isset($row['specifications']) ? $row['specifications'] : '';
        
        // Try to format specifications
        $formatted = formatSpecifications($specsJson, $attributeNames, $variantNames);
        
        // If formatting returned empty but we have specifications, try to show something
        if (empty($formatted) && !empty($specsJson) && $specsJson !== '[]' && $specsJson !== 'null' && trim($specsJson) !== '') {
            // Try to decode and show at least attribute IDs if formatting failed
            $specs = json_decode($specsJson, true);
            if (is_array($specs) && !empty($specs)) {
                $fallbackFormatted = [];
                foreach ($specs as $spec) {
                    if (is_array($spec) && isset($spec['attribute_id'])) {
                        $attrId = (int)$spec['attribute_id'];
                        $attrName = isset($attributeNames[$attrId]) ? $attributeNames[$attrId] : 'Attribute ' . $attrId;
                        $variants = isset($spec['variants']) && is_array($spec['variants']) ? $spec['variants'] : [];
                        if (!empty($variants)) {
                            $variantIds = [];
                            foreach ($variants as $v) {
                                if (isset($v['id'])) {
                                    $variantIdStr = (string)$v['id'];
                                    $variantIds[] = isset($variantNames[$variantIdStr]) ? $variantNames[$variantIdStr] : 'Variant ' . $variantIdStr;
                                }
                            }
                            if (!empty($variantIds)) {
                                $fallbackFormatted[] = $attrName . ': ' . implode(', ', $variantIds);
                            }
                        }
                    }
                }
                if (!empty($fallbackFormatted)) {
                    $formatted = implode(' | ', $fallbackFormatted);
                }
            }
        }
        
        $row['specifications_formatted'] = $formatted;
    }
    unset($row); // Break reference

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Products');

    // Headers with camelCase (matching admin side)
    $headers = [
        'sellerId',
        'skuId',
        'sellerName',
        'productName',
        'category',
        'subCategory',
        'brand',
        'description',
        'specifications',
        'hsnId',
        'mrp',
        'price',
        'discount',
        'gst',
        'coins',
        'platformFee',
        'availableStock',
        'productStatus',
        'listingDate'
    ];

    $colLetters = [];
    for ($i = 0; $i < count($headers); $i++) {
        $n = $i;
        $col = '';
        do {
            $col = chr($n % 26 + 65) . $col;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);
        $colLetters[] = $col;
    }

    // Add headers
    foreach ($headers as $i => $h) {
        $sheet->setCellValue($colLetters[$i] . '1', $h);
    }

    // Header styling and freeze pane
    $headerRange = $colLetters[0] . '1:' . $colLetters[count($colLetters) - 1] . '1';
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFEFEFEF');
    $sheet->freezePane('A2');

    // Add data
    $rowNum = 2;
    foreach ($rows as $product) {
        $colIndex = 0;
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, '#' . $vendor_reg_id);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['sku_id']);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, ''); // Seller name (vendor's own name, can be left empty or fetched)
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['product_name']);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['category_name']);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['sub_category_name']);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['brand_final']);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['description_clean']);
        // Ensure specifications are displayed - use formatted value
        $specsValue = !empty($product['specifications_formatted']) ? $product['specifications_formatted'] : '';
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $specsValue);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['hsn_id'] ?? '');
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['mrp']);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['selling_price']);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['discount']);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['gst']);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['coin']);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['platform_fee']);
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['Inventory']);

        // Product status
        $status = ucfirst(str_replace('_', ' ', $product['status']));
        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $status);

        $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, date('d/m/Y', strtotime($product['created_date'])));
        $rowNum++;
    }

    // Auto-size columns
    foreach ($colLetters as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Thin borders around data region
    $lastDataRow = $rowNum - 1;
    if ($lastDataRow >= 1) {
        $sheet->getStyle($colLetters[0] . '1:' . $colLetters[count($colLetters) - 1] . $lastDataRow)
            ->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    }
    // Clean buffers again right before sending headers/output
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    } else {
        @ob_end_clean();
    }

    $fileName = 'products_export_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: public');
    header('Expires: 0');
    header('Content-Transfer-Encoding: binary');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Fetch products for the vendor
$products_query = "SELECT p.*, c.name as category_name, sc.name as subcategory_name 
                   FROM products p 
                   LEFT JOIN category c ON p.category_id = c.id 
                   LEFT JOIN sub_category sc ON p.sub_category_id = sc.id 
                   WHERE $where_clause 
                   ORDER BY $order_by
                   LIMIT $per_page OFFSET $offset";

$products_result = mysqli_query($con, $products_query);

// Fetch categories for dropdown
$categories_query = "SELECT * FROM category ORDER BY name";
$categories_result = mysqli_query($con, $categories_query);

// Fetch subcategories for dropdown (if category is selected)
$subcategories_result = null;
if (!empty($selected_category)) {
    $subcategories_query = "SELECT * FROM sub_category WHERE category_id = '$selected_category' ORDER BY name";
    $subcategories_result = mysqli_query($con, $subcategories_query);
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
    <title>PRODUCT MANAGEMENT | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords" content="hagidy website">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo PUBLIC_ASSETS; ?>/images/vendor/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Choices JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>

    <!-- Main Theme Js -->
    <script src="<?php echo PUBLIC_ASSETS; ?>/js/vendor/main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>/css/vendor/styles.min.css" rel="stylesheet">

    <!-- Icons Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>/css/vendor/icons.css" rel="stylesheet">

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
    <!-- Confetti Animation -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <!-- Custom CSS for Address Truncation -->
    <style>
        .bg-gradient-success {
            background: linear-gradient(135deg, #00c853, #00bfa5) !important;
        }

        .success-icon i {
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }

        #viewProductsBtn {
            background: #3B4B6B;
            border: none;
            color: white;
            font-weight: 600;
        }

        #viewProductsBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 200, 83, 0.3) !important;
        }

        .modal-content {
            animation: modalPop 0.4s ease-out;
        }

        @keyframes modalPop {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        :root {
            --hagidy-blue: #3B4B6B;
        }



        /* Use Hagidy blue for icons and text */
        .text-hagidy {
            color: #3B4B6B !important;
        }

        .bg-gradient-hagidy-success {
            background: #3B4B6B !important;
        }

        /* Replace green success button with Hagidy blue */
        .btn-hagidy {
            background-color: #3B4B6B !important;
            border: none !important;
            color: #fff !important;
            font-weight: 600;
        }

        .btn-hagidy:hover {
            background-color: #2C3850 !important;
            box-shadow: 0 4px 10px rgba(59, 75, 107, 0.3);
        }

        /* Site-themed success gradient (replace green with your blue tone) */
    </style>
</head>

<body>

    <!-- Loader -->
    <div id="loader">
        <img src="<?php echo PUBLIC_ASSETS; ?>/images/vendor/media/loader.svg" alt="">
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
                <div
                    class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Product Listing </h1>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php $exportUrl = basename(__FILE__) . '?' . http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>
                        <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn-down-excle1"><i
                                class="fa-solid fa-file-arrow-down"></i>
                            Export to Excel</a>
                        <button type="button" class="btn btn-dark btn-wave text-white waves-effect waves-light mx-2"
                            data-bs-toggle="modal" data-bs-target="#bulkImportModal">Bulk Import</button>
                        <a href="./first-add-product.php" class="btn btn-secondary btn-wave waves-effect waves-light">+
                            Add
                            Product</a>
                    </div>
                </div>
                <!-- Page Header Close -->
                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12">
                        <?php 
                        // Prioritize error messages over success messages - only show one at a time
                        if (!empty($_SESSION['flash_error'])): 
                            // Clear success message if error exists
                            unset($_SESSION['flash_success']);
                        ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert" id="flashMessageErr">
                                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($_SESSION['flash_error']);
                                unset($_SESSION['flash_error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <script>
                                setTimeout(function () {
                                    var el = document.getElementById('flashMessageErr');
                                    if (el) {
                                        var alert = bootstrap.Alert.getOrCreateInstance(el);
                                        alert.close();
                                    }
                                }, 5000);
                            </script>
                        <?php elseif (!empty($_SESSION['flash_success'])): 
                            // Clear error message if success exists
                            unset($_SESSION['flash_error']);
                        ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert" id="flashMessage">
                                <i class="fa-solid fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($_SESSION['flash_success']);
                                unset($_SESSION['flash_success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <script>
                                setTimeout(function () {
                                    var el = document.getElementById('flashMessage');
                                    if (el) {
                                        var alert = bootstrap.Alert.getOrCreateInstance(el);
                                        alert.close();
                                    }
                                }, 5000);
                            </script>
                        <?php endif; ?>
                        <div class="card custom-card">
                            <div class="card-header justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap w-100-product">
                                    <div class="selecton-order">
                                        <select id="categorySelect" class="form-select form-select-lg">
                                            <option value="">All Categories</option>
                                            <?php
                                            if ($categories_result) {
                                                while ($row = mysqli_fetch_assoc($categories_result)) {
                                                    $selected = ($selected_category == $row['id']) ? 'selected' : '';
                                                    echo "<option value='" . $row['id'] . "' $selected>" . $row['name'] . "</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="selecton-order">
                                        <select id="subcategorySelect"
                                            class="form-select form-select-lg selecton-order">
                                            <option value="">All Sub Categories</option>
                                            <?php
                                            if ($subcategories_result && mysqli_num_rows($subcategories_result) > 0) {
                                                while ($row = mysqli_fetch_assoc($subcategories_result)) {
                                                    $selected = ($selected_subcategory == $row['id']) ? 'selected' : '';
                                                    echo "<option value='" . $row['id'] . "' $selected>" . $row['name'] . "</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div for="Default-sorting"><b>Sort By :</b></div>
                                    <div class="selecton-order">
                                        <select id="Default-sorting" class="form-select form-select-lg">
                                            <option value="latest" <?php echo $sort_by === 'latest' ? 'selected' : ''; ?>>
                                                Sort by latest Product</option>
                                            <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>
                                                Sort by oldest Product</option>
                                            <option value="name_asc" <?php echo $sort_by === 'name_asc' ? 'selected' : ''; ?>>Sort by Name: A to Z</option>
                                            <option value="name_desc" <?php echo $sort_by === 'name_desc' ? 'selected' : ''; ?>>Sort by Name: Z to A</option>
                                            <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Sort by Price: high to low</option>
                                            <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Sort by Price: low to high</option>
                                        </select>
                                    </div>
                                    <div class="selecton-order position-relative">
                                        <input type="search" id="searchInput" class="form-control"
                                            placeholder="Search products..."
                                            value="<?php echo htmlspecialchars($search_term); ?>"
                                            aria-describedby="button-addon2">
                                        <?php if (!empty($search_term)): ?>
                                            <button type="button" id="clearSearch" class="btn btn-sm position-absolute"
                                                style="right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6c757d;">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped text-nowrap align-middle table-bordered-vertical">
                                    <thead class="table-group-divider">
                                        <tr>
                                            <th scope="col">No</th>
                                            <th scope="col">SKU ID</th>
                                            <th scope="col">Product</th>
                                            <th scope="col">Category</th>
                                            <th scope="col">Sub Category</th>
                                            <th scope="col">MRP</th>
                                            <th scope="col">Price</th>
                                            <th scope="col">Stock</th>
                                            <th scope="col">Product Status</th>
                                            <th scope="col">Note</th>
                                            <th scope="col">Created At</th>
                                            <th scope="col">Last Update</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                        <?php
                                        if ($products_result && mysqli_num_rows($products_result) > 0) {
                                            $counter = 1;
                                            while ($product = mysqli_fetch_assoc($products_result)) {
                                                // Decode JSON if needed
                                                $images = $product['images'];
                                                if (is_string($images)) {
                                                    $decoded = json_decode($images, true);
                                                    if (json_last_error() === JSON_ERROR_NONE) {
                                                        $images = $decoded;
                                                    }
                                                }

                                                // Pick the first image or fallback
                                                $product_image = (!empty($images) && is_array($images))
                                                    ? USER_BASEURL . 'public/' . $images[0]
                                                    : '<?php echo PUBLIC_ASSETS; ?>/images/vendor/first.png';
                                                //print_r($product_image);
                                        
                                                $created_utc = $product['created_date'] ?? null;
                                                $updated_utc = $product['updated_date'] ?? null;

                                                if (!empty($created_utc)) {
                                                    $dt_created = new DateTime($created_utc, new DateTimeZone('UTC')); // assuming DB time is UTC
                                                    $dt_created->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                    $created_date = $dt_created->format('d M Y - h:i A');
                                                } else {
                                                    $created_date = '-';
                                                }

                                                if (!empty($updated_utc)) {
                                                    $dt_updated = new DateTime($updated_utc, new DateTimeZone('UTC')); // assuming DB time is UTC
                                                    $dt_updated->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                    $updated_date = $dt_updated->format('d M Y - h:i A');
                                                } else {
                                                    $updated_date = '-';
                                                }


                                                ?>

                                                <tr>
                                                    <td><?php echo $counter++; ?></td>
                                                    <td class="rqu-id">#<?php echo htmlspecialchars($product['sku_id']); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="product-img">
                                                                <img src="<?php if (!empty($product_image)) {
                                                                    echo htmlspecialchars($product_image);
                                                                } else {
                                                                    echo PUBLIC_ASSETS . '/images/vendor/default.png';
                                                                } ?>"
                                                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                                            </div>
                                                            <div class="product-name-text">
                                                                <h4><?php echo htmlspecialchars($product['product_name']); ?>
                                                                </h4>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <span
                                                            class="badge bg-light1 text-dark"><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($product['subcategory_name'] ?? 'N/A'); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            ₹<?php echo number_format($product['mrp'], 2); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            ₹<?php echo number_format($product['selling_price'], 2); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($product['Inventory'] ?? '0'); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if ($product['status'] == 'pending') {
                                                            echo '<span class="badge rounded-pill bg-outline-warning">' . $product['status'] . '</span>';
                                                        } elseif ($product['status'] == 'hold') {
                                                            echo '<span class="badge rounded-pill bg-outline-info">' . $product['status'] . '</span>';
                                                        } elseif ($product['status'] == 'approved') {
                                                            echo '<span class="badge rounded-pill bg-outline-success">' . $product['status'] . '</span>';
                                                        } elseif ($product['status'] == 'rejected') {
                                                            echo '<span class="badge rounded-pill bg-outline-danger">' . $product['status'] . '</span>';
                                                        } elseif ($product['status'] == 'under_review') {
                                                            echo '<span class="badge rounded-pill bg-outline-info">' . $product['status'] . '</span>';
                                                        }
                                                        ?>

                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <span
                                                                class="text-danger text-wrap text-truncate"><?php echo $product['status_note'] ?? ''; ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo $created_date; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo $updated_date; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <a href="./product-details.php?id=<?php echo $product['id']; ?>"
                                                                class="i-icon-product"><i class="fa-solid fa-eye"></i></a>
                                                            <a href="./first-edit-product.php?id=<?php echo $product['id']; ?>"
                                                                class="i-icon-eidt"><i
                                                                    class="fa-regular fa-pen-to-square"></i></a>
                                                            <a href="#" onclick="deleteProduct(<?php echo $product['id']; ?>)"
                                                                class="i-icon-trash"><i class="fa-solid fa-trash-can"></i></a>

                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td colspan="12" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="fa-solid fa-box-open fa-3x mb-3"></i>
                                                        <h5>No products found</h5>
                                                        <p>Start by adding your first product or adjust your filters.</p>
                                                        <a href="./first-add-product.php" class="btn btn-primary">Add
                                                            Product</a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class=" my-4">
                                <nav aria-label="Page navigation">
                                    <?php
                                    // Build base query string preserving filters and per_page
                                    $base_params = [];
                                    if (!empty($selected_category)) {
                                        $base_params['category'] = $selected_category;
                                    }
                                    if (!empty($selected_subcategory)) {
                                        $base_params['subcategory'] = $selected_subcategory;
                                    }
                                    if (!empty($search_term)) {
                                        $base_params['search'] = $search_term;
                                    }
                                    if (!empty($per_page)) {
                                        $base_params['per_page'] = $per_page;
                                    }

                                    // Helper to build page url
                                    function page_url($page_num, $base_params)
                                    {
                                        $params = $base_params;
                                        $params['page'] = $page_num;
                                        return basename(__FILE__) . '?' . http_build_query($params);
                                    }
                                    ?>
                                    <ul class="pagination justify-content-center">
                                        <?php
                                        $prev_disabled = ($page <= 1) ? ' disabled' : '';
                                        $next_disabled = ($page >= $total_pages) ? ' disabled' : '';
                                        ?>
                                        <li class="page-item<?php echo $prev_disabled; ?>">
                                            <a class="page-link"
                                                href="<?php echo $page > 1 ? page_url($page - 1, $base_params) : 'javascript:void(0);'; ?>">Previous</a>
                                        </li>
                                        <?php
                                        // Render page numbers (compact)
                                        $max_links = 5;
                                        $start = max(1, $page - 2);
                                        $end = min($total_pages, $start + $max_links - 1);
                                        if ($end - $start + 1 < $max_links) {
                                            $start = max(1, $end - $max_links + 1);
                                        }
                                        for ($i = $start; $i <= $end; $i++) {
                                            $active = ($i === $page) ? ' active' : '';
                                            echo '<li class="page-item' . $active . '"><a class="page-link" href="' . page_url($i, $base_params) . '">' . $i . '</a></li>';
                                        }
                                        ?>
                                        <li class="page-item<?php echo $next_disabled; ?>">
                                            <a class="page-link"
                                                href="<?php echo $page < $total_pages ? page_url($page + 1, $base_params) : 'javascript:void(0);'; ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>

                    </div>
                </div>
                <!-- End:: row-2 -->

                <!-- Import Result Modal - FINAL PERFECTION -->
                <div class="modal fade" id="importResultModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                            <div class="modal-header text-white border-0 py-4" id="resultModalHeader">
                                <div class="d-flex align-items-center gap-3 w-100 justify-content-center">
                                    <div class="success-icon">
                                        <i class="fa-solid fa-circle-check fa-3x"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0 fw-bold text-white" id="importResultModalLabel">Import
                                            Successful!</h4>
                                        <p class="mb-0 opacity-90 text-white" id="importSubtitle">0 Products Added</p>
                                    </div>
                                </div>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center py-5">
                                <div id="importResultMessage" class="mb-4">
                                    <!-- JS injects here -->
                                </div>
                                <div class="d-flex gap-3 justify-content-center">
                                    <button type="button" class="btn btn-outline-secondary px-4"
                                        data-bs-dismiss="modal">
                                        Close
                                    </button>
                                    <button type="button" class="btn btn-hagidy px-5 shadow-sm" id="viewProductsBtn">
                                        View Products
                                    </button>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- End::app-content -->


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

        <!-- Popper JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>/libs/@popperjs/core/umd/popper.min.js"></script>

        <!-- Bootstrap JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

        <!-- Defaultmenu JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>/js/vendor/defaultmenu.min.js"></script>

        <!-- Node Waves JS-->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.js"></script>

        <!-- Sticky JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>/js/vendor/sticky.js"></script>

        <!-- Simplebar JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.js"></script>
        <script src="<?php echo PUBLIC_ASSETS; ?>/js/vendor/simplebar.js"></script>

        <!-- Color Picker JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/pickr.es5.min.js"></script>

        <!-- Apex Charts JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/apexcharts/apexcharts.min.js"></script>

        <!-- Ecommerce-Dashboard JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>/js/vendor/ecommerce-dashboard.js"></script>

        <!-- Custom-Switcher JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>/js/vendor/custom-switcher.min.js"></script>

        <!-- Date & Time Picker JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.js"></script>
        <script src="<?php echo PUBLIC_ASSETS; ?>/js/vendor/date&time_pickers.js"></script>

        <!-- Custom JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>/js/vendor/custom.js"></script>

        <style>
            /* Pretty confirm modal to match reference */
            .modal-warning .modal-content {
                border-radius: 16px;
                border: 0;
            }

            .modal-warning .warning-icon {
                width: 64px;
                height: 64px;
                border-radius: 50%;
                background: #ffe9ea;
                color: #e23e57;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 8px auto 4px;
            }

            .modal-warning .warning-icon svg {
                width: 34px;
                height: 34px;
            }

            .modal-warning .modal-title {
                width: 100%;
                text-align: center;
                font-weight: 700;
                font-size: 22px;
            }

            .modal-warning .modal-body {
                text-align: center;
                color: #6c757d;
                font-size: 15px;
            }

            .btn-gradient-primary {
                background: linear-gradient(90deg, #2F57EF 0%, #6A35FF 100%);
                color: #fff;
                border: 0;
            }

            .btn-gradient-primary:hover {
                filter: brightness(0.95);
                color: #fff;
            }

            .btn-soft {
                background: #f2f3f5;
                border: 1px solid #e5e7eb;
                color: #334155;
            }

            .btn-soft:hover {
                background: #e9eaef;
                color: #111827;
            }
        </style>

        <!-- Delete Confirmation Modal (styled) -->
        <div class="modal fade modal-warning" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content p-2">
                    <div class="warning-icon">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path
                                d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div class="modal-header border-0 pt-0">
                        <h5 class="modal-title" id="confirmDeleteLabel">Delete Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-0">
                        Are you sure you want to delete this product? This action cannot be undone.
                    </div>
                    <div class="modal-footer border-0 d-flex justify-content-center gap-2 pb-4">
                        <button type="button" class="btn btn-secondary px-4" id="confirmDeleteBtn">Yes,
                            Delete</button>
                        <button type="button" class="btn btn-soft px-4" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- JavaScript for Address Truncation -->

        <!-- Bulk Import Modal -->
        <!-- Enhanced Bulk Import Modal -->
        <div class="modal fade" id="bulkImportModal" tabindex="-1" aria-labelledby="bulkImportModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkImportModalLabel">Product Bulk Upload</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="bulkImportForm" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="bulkCategory" class="form-label">Category <span
                                                class="text-danger">*</span></label>
                                        <select class="form-select" id="bulkCategory" name="category_id" required>
                                            <option value="">Select Category</option>

                                            <?php
                                            $vendorQuery = mysqli_query($con, "SELECT product_categories FROM vendor_registration WHERE vendor_id = '$vendor_id'");
                                            $vendorData = mysqli_fetch_assoc($vendorQuery);
                                            $selectedCategories = [];
                                            if (!empty($vendorData['product_categories'])) {
                                                $selectedCategories = explode(',', $vendorData['product_categories']); // array of ids
                                            }
                                            // Build SQL only if categories exist
                                            if (!empty($selectedCategories)) {
                                                $ids = implode(',', array_map('intval', $selectedCategories)); // sanitize to int
                                                $categories = mysqli_query($con, "SELECT * FROM category WHERE id IN ($ids) ORDER BY name");
                                            } else {
                                                $categories = mysqli_query($con, "SELECT * FROM category ORDER BY name");
                                            }
                                            while ($category = mysqli_fetch_assoc($categories)) {
                                                $sel = ($selected_category == $category['id']) ? 'selected' : '';
                                                echo "<option value='" . $category['id'] . "' $sel>" . $category['name'] . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="bulkSubCategory" class="form-label">Sub Category <span
                                                class="text-danger">*</span></label>
                                        <select class="form-select" id="bulkSubCategory" name="sub_category_id"
                                            required>
                                            <option value="">Select Sub Category</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="excelFile" class="form-label">Excel File <span
                                        class="text-danger">*</span></label>
                                <div class="drop-zone" id="fileUploadArea" style="height: auto;">
                                    <div class="file-upload-content">
                                        <i class="fa fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                        <p class="mb-2">Drag & Drop your Excel file here or</p>
                                        <button type="button" class="btn btn-outline-primary">Browse Files</button>
                                        <input type="file" id="excelFile" name="excel_file" accept=".xlsx,.xls"
                                            class="d-none">
                                    </div>
                                    <div class="file-selected d-none">
                                        <i class="fa fa-file-excel fa-2x text-success mb-2"></i>
                                        <p class="mb-1" id="selectedFileName">Selected File</p>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="clearFile()">Remove</button>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <h6><i class="fa fa-info-circle"></i> Template Information</h6>
                                <p class="mb-2">Excel format will be generated based on your selected subcategory.</p>
                                <p class="mb-0">Mandatory attributes will be marked with <span
                                        class="text-danger">*</span> in the template.</p>
                            </div>

                            <div class="text-center">
                                <button type="button" class="btn btn-primary" onclick="downloadExcelFormat()">Download
                                    Excel Format</button>
                                <!-- Template download removed per client request; using provided Excel format -->
                                <button type="submit" class="btn btn-primary" disabled>
                                    <i class="fa fa-upload"></i> Upload Products
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add this new modal after the bulkImportModal in your HTML -->
        <div class="modal fade" id="bulkConfirmModal" tabindex="-1" aria-labelledby="bulkConfirmModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkConfirmModalLabel">Bulk Import Confirmation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="bulkConfirmReport"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="bulkConfirmYes">Yes, Proceed</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom CSS for Bulk Import Modal -->
        <style>
            .file-upload-area {
                transition: all 0.3s ease;
                cursor: pointer;
            }

            .file-upload-area:hover {
                border-color: #007bff !important;
                background-color: #f8f9fa;
            }

            .file-upload-area.dragover {
                border-color: #007bff !important;
                background-color: #e3f2fd;
            }

            .modal-content {
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            }

            .modal-header {
                padding: 1.5rem 1.5rem 0 1.5rem;
            }

            .modal-body {
                padding: 0 1.5rem 1.5rem 1.5rem;
            }

            .form-select:focus {
                border-color: #007bff;
                box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
            }

            /* Import Preview Styling */
            .import-preview .alert {
                border-radius: 8px;
                border: none;
            }

            .import-preview .alert h6 {
                font-weight: 600;
                margin-bottom: 0.5rem;
            }

            .import-preview .row-errors,
            .import-preview .product-errors {
                background: rgba(255, 255, 255, 0.1);
                border-radius: 4px;
                padding: 10px;
            }

            .import-preview ul {
                margin-bottom: 0;
                padding-left: 1.2rem;
            }

            .import-preview li {
                margin-bottom: 0.25rem;
            }

            /* Modal sizing for better error display */
            #bulkConfirmModal .modal-dialog {
                max-width: 800px;
            }

            #bulkConfirmModal .modal-body {
                max-height: 70vh;
                overflow-y: auto;
            }
        </style>

        <!-- JavaScript for Bulk Import Modal -->
        <script>
            // File upload functionality
            const fileUploadArea = document.getElementById('fileUploadArea');
            const excelFile = document.getElementById('excelFile');
            const fileUploadContent = fileUploadArea.querySelector('.file-upload-content');
            const fileSelected = fileUploadArea.querySelector('.file-selected');
            const selectedFileName = document.getElementById('selectedFileName');
            const bulkCategorySelect = document.getElementById('bulkCategory');
            const bulkSubCategorySelect = document.getElementById('bulkSubCategory');
            const downloadTemplateBtn = null; // removed
            const bulkUploadSubmitBtn = document.querySelector('#bulkImportForm button[type="submit"]');

            // Click to upload
            fileUploadArea.addEventListener('click', () => {
                excelFile.click();
            });

            // File selection
            excelFile.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    selectedFileName.textContent = file.name;
                    fileUploadContent.classList.add('d-none');
                    fileSelected.classList.remove('d-none');
                }
                setBulkButtonsState();
            });

            // Drag and drop functionality
            fileUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileUploadArea.classList.add('dragover');
            });

            fileUploadArea.addEventListener('dragleave', () => {
                fileUploadArea.classList.remove('dragover');
            });

            fileUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                fileUploadArea.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const file = files[0];
                    if (file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
                        file.type === 'application/vnd.ms-excel') {
                        excelFile.files = files;
                        selectedFileName.textContent = file.name;
                        fileUploadContent.classList.add('d-none');
                        fileSelected.classList.remove('d-none');
                    } else {
                        alert('Please select a valid Excel file (.xlsx or .xls)');
                    }
                }
            });

            // Clear file function
            function clearFile() {
                excelFile.value = '';
                fileUploadContent.classList.remove('d-none');
                fileSelected.classList.add('d-none');
                setBulkButtonsState();
            }
            // Download error report function
            function downloadErrorReport() {
                window.open('<?php echo USER_BASEURL; ?>app/api/import_products.php?download_errors=1', '_blank');
            }

            // Download Excel format function
            function downloadExcelFormat() {
                window.open('<?php echo PUBLIC_ASSETS; ?>uploads/excel/product_template.xlsx', '_blank');
            }

            // Populate Sub Categories when Category changes (Bulk Import Modal)
            function resetBulkSubcategories() {
                if (!bulkSubCategorySelect) return;
                bulkSubCategorySelect.innerHTML = '<option value="">Select Sub Category</option>';
            }

            // function setBulkButtonsState() {
            //     const enabled = !!(bulkSubCategorySelect && bulkSubCategorySelect.value);
            //     // no template button anymore
            //     if (bulkUploadSubmitBtn) bulkUploadSubmitBtn.disabled = !enabled;
            // }

            function setBulkButtonsState() {
                const hasFile = excelFile?.files?.length > 0;
                const enabled = bulkSubCategorySelect?.value && hasFile;
                bulkUploadSubmitBtn.disabled = !enabled;
            }

            if (bulkCategorySelect && bulkSubCategorySelect) {
                // Function to load subcategories for a given category ID
                function loadSubcategories(categoryId, selectedSubCategoryId = null) {
                    if (!categoryId) {
                        resetBulkSubcategories();
                        setBulkButtonsState();
                        return;
                    }

                    fetch(`<?php echo USER_BASEURL; ?>app/api/get_subcategories.php?category_id=${encodeURIComponent(categoryId)}`)
                        .then(res => res.json())
                        .then(data => {
                            resetBulkSubcategories();
                            if (data && Array.isArray(data.subcategories)) {
                                data.subcategories.forEach(sc => {
                                    const opt = document.createElement('option');
                                    opt.value = sc.id;
                                    opt.textContent = sc.name;
                                    // Select if this is the pre-selected subcategory from URL
                                    if (selectedSubCategoryId && sc.id == selectedSubCategoryId) {
                                        opt.selected = true;
                                    }
                                    bulkSubCategorySelect.appendChild(opt);
                                });
                            }
                            setBulkButtonsState();
                        })
                        .catch(err => {
                            console.error('Failed to load sub categories:', err);
                            resetBulkSubcategories();
                            setBulkButtonsState();
                        });
                }

                // Initial state
                resetBulkSubcategories();
                setBulkButtonsState();

                // Load subcategories if category is pre-selected from URL
                const initialCategoryId = bulkCategorySelect.value;
                const initialSubCategoryId = '<?php echo isset($_GET["subcategory"]) ? (int)$_GET["subcategory"] : ""; ?>';
                if (initialCategoryId && initialCategoryId !== '') {
                    loadSubcategories(initialCategoryId, initialSubCategoryId || null);
                }

                bulkCategorySelect.addEventListener('change', function () {
                    const categoryId = this.value;
                    loadSubcategories(categoryId);
                });

                bulkSubCategorySelect.addEventListener('change', function () {
                    setBulkButtonsState();
                });
            }

            // Form submission
            // Replace the existing form submission handler in your <script> tag with this

            document.getElementById('bulkImportForm').addEventListener('submit', async (e) => {
                e.preventDefault();

                const category = bulkCategorySelect ? bulkCategorySelect.value : '';
                const subCategory = bulkSubCategorySelect ? bulkSubCategorySelect.value : '';
                const file = document.getElementById('excelFile').files[0];

                if (!category) {
                    alert('Please select a category');
                    return;
                }
                if (!subCategory) {
                    alert('Please select a sub category');
                    return;
                }
                if (!file) {
                    alert('Please select an Excel file');
                    return;
                }

                const formData = new FormData();
                formData.append('category_id', category);
                formData.append('sub_category_id', subCategory);
                formData.append('excel_file', file);

                // Disable buttons while processing
                if (bulkUploadSubmitBtn) bulkUploadSubmitBtn.disabled = true;

                try {
                    const res = await fetch('<?php echo USER_BASEURL; ?>app/api/import_products.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        throw new Error(data?.error || 'Processing failed');
                    }

                    // Build preview report with better formatting
                    let report = `<div class="import-preview">
            <div class="alert alert-info mb-3">
                <h6><i class="fa fa-info-circle"></i> Import Preview</h6>
                <p class="mb-1"><strong>Total Products Found:</strong> ${data.total_attempted}</p>
                <p class="mb-1"><strong>Ready to Import:</strong> <span class="text-success">${data.to_insert}</span></p>
                <p class="mb-1"><strong>Failed/Skipped:</strong> <span class="text-danger">${data.failed}</span></p>
                <p class="mb-0"><strong>Invalid Rows:</strong> <span class="text-warning">${data.failed_rows}</span></p>
            </div>`;

                    const rowErrs = data.row_errors || {};
                    const prodErrs = data.product_errors || {};

                    if (Object.keys(rowErrs).length > 0) {
                        report += `<div class="alert alert-warning mb-3">
                <h6><i class="fa fa-exclamation-triangle"></i> Row Errors (${Object.keys(rowErrs).length} rows)</h6>
                <div class="row-errors" style="max-height: 200px; overflow-y: auto;">`;
                        Object.entries(rowErrs).forEach(([rowNum, errors]) => {
                            report += `<div class="mb-2">
                    <strong>Row ${rowNum}:</strong>
                    <ul class="mb-0">`;
                            (Array.isArray(errors) ? errors : [errors]).forEach(error => {
                                report += `<li class="text-danger">${error}</li>`;
                            });
                            report += `</ul></div>`;
                        });
                        report += `</div>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="downloadErrorReport()">
                        <i class="fa fa-download"></i> Download Error Report
                    </button>
                </div>
            </div>`;
                    }

                    if (Object.keys(prodErrs).length > 0) {
                        report += `<div class="alert alert-danger mb-3">
                <h6><i class="fa fa-times-circle"></i> Product Errors (${Object.keys(prodErrs).length} products)</h6>
                <div class="product-errors" style="max-height: 200px; overflow-y: auto;">`;
                        Object.entries(prodErrs).forEach(([productName, errors]) => {
                            report += `<div class="mb-2">
                    <strong>${productName}:</strong>
                    <ul class="mb-0">`;
                            (Array.isArray(errors) ? errors : [errors]).forEach(error => {
                                report += `<li class="text-danger">${error}</li>`;
                            });
                            report += `</ul></div>`;
                        });
                        report += `</div>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="downloadErrorReport()">
                        <i class="fa fa-download"></i> Download Error Report
                    </button>
                </div>
            </div>`;
                    }

                    report += `<div class="alert alert-success">
            <h6><i class="fa fa-check-circle"></i> Ready to Import</h6>
            <p class="mb-0">${data.to_insert} products are ready to be imported. Click "Yes, Proceed" to continue or "Cancel" to abort.</p>
        </div></div>`;

                    // Show confirmation modal
                    const confirmReportEl = document.getElementById('bulkConfirmReport');
                    if (confirmReportEl) {
                        confirmReportEl.innerHTML = report;
                    }
                    const confirmModal = new bootstrap.Modal(document.getElementById('bulkConfirmModal'));
                    confirmModal.show();

                    // Handle Yes button
                    const yesBtn = document.getElementById('bulkConfirmYes');
                    const handleYes = async () => {
                        try {
                            const confirmFormData = new FormData();
                            confirmFormData.append('confirm', '1');
                            confirmFormData.append('category_id', category);
                            confirmFormData.append('sub_category_id', subCategory);

                            const confirmRes = await fetch('<?php echo USER_BASEURL; ?>app/api/import_products.php', {
                                method: 'POST',
                                body: confirmFormData
                            });
                            const confirmData = await confirmRes.json();
                            if (!confirmRes.ok) {
                                throw new Error(confirmData?.error || 'Import failed');
                            }

                            // Build final report with better formatting
                            let finalReport = `✅ Import Completed!\n\n📊 Results:\n• Successfully Inserted: ${confirmData.inserted || 0} products\n• Failed: ${confirmData.failed || 0} products`;
                            const confirmProdErrs = confirmData.product_errors || {};
                            if (Object.keys(confirmProdErrs).length > 0) {
                                finalReport += `\n\n❌ Errors during import:\n`;
                                Object.entries(confirmProdErrs).forEach(([productName, errors]) => {
                                    finalReport += `\n• ${productName}:\n`;
                                    (Array.isArray(errors) ? errors : [errors]).forEach(error => {
                                        finalReport += `  - ${error}\n`;
                                    });
                                });
                            }

                            // Show success/error message with better styling
                            // Show success/error message with better styling
                            // TRIGGER CONFETTI
                            if (confirmData.inserted > 0) {
                                confetti({
                                    particleCount: 120,
                                    spread: 70,
                                    origin: {
                                        y: 0.6
                                    },
                                    colors: ['', '#00bfa5', '#4caf50']
                                });
                            }

                            // DYNAMIC SUCCESS / ERROR
                            const isSuccess = confirmData.inserted > 0;

                            document.getElementById('importResultModalLabel').textContent =
                                isSuccess ? 'Import Successful!' : 'Import Failed';

                            document.getElementById('importSubtitle').textContent =
                                isSuccess ?
                                    `${confirmData.inserted} Products Added` :
                                    'No Products Imported';

                            document.getElementById('importResultMessage').innerHTML = isSuccess ?
                                `<div class="py-3">
                                    <h5 class="text-hagidy fw-bold mb-3">
                                        <i class="fa-solid fa-check-circle text-hagidy"></i> Import Completed!
                                    </h5>
                                    <div class="row text-center g-4">
                                        <div class="col-6">
                                            <div class="fs-1 fw-bold text-hagidy">${confirmData.inserted}</div>
                                            <div class="text-muted">Inserted</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="fs-1 fw-bold text-danger">${confirmData.failed}</div>
                                            <div class="text-muted">Failed</div>
                                        </div>
                                    </div>
                                    <p class="mt-4 text-hagidy fw-semibold">
                                        Your products are live!
                                    </p>
                                </div>` :

                                `<div class="py-3">
                                    <h5 class="text-danger fw-bold mb-3">
                                        <i class="fa-solid fa-times-circle"></i> Import Failed
                                    </h5>
                                    <p>No products added. Try again.</p>
                                    <button onclick="downloadErrorReport()" class="btn btn-sm btn-outline-danger">
                                        Download Report
                                    </button>
                                </div>`;

                            // HEADER COLOR
                            const header = document.getElementById('resultModalHeader');
                            header.className = 'modal-header text-white border-0 py-4 ' +
                                (isSuccess ? 'bg-gradient-hagidy-success' : 'bg-gradient-danger');

                            // SHOW MODAL
                            const resultModal = new bootstrap.Modal('#importResultModal');
                            resultModal.show();

                            // COUNTDOWN
                            let seconds = 10;
                            const countdownEl = document.getElementById('countdown');
                            const timer = setInterval(() => {
                                seconds--;
                                countdownEl.textContent = seconds;
                                if (seconds <= 0) {
                                    clearInterval(timer);
                                    resultModal.hide();
                                }
                            }, 1000);

                            // VIEW PRODUCTS BUTTON
                            document.getElementById('viewProductsBtn').onclick = () => {
                                resultModal.hide();
                                document.querySelector('.table-responsive')?.scrollIntoView({
                                    behavior: 'smooth'
                                });
                            };

                            // RELOAD ONLY AFTER CLOSE
                            document.getElementById('importResultModal')
                                .addEventListener('hidden.bs.modal', () => {
                                    window.location.reload();
                                }, {
                                    once: true
                                });
                            // Close other modals
                            confirmModal.hide();
                            const importModal = bootstrap.Modal.getInstance(document.getElementById('bulkImportModal'));
                            importModal?.hide();
                            clearFile();
                            // Reset form
                            if (bulkCategorySelect) bulkCategorySelect.value = '';
                            if (bulkSubCategorySelect) bulkSubCategorySelect.value = '';
                            setBulkButtonsState();
                        } catch (err) {
                            alert('Error: ' + err.message);
                        } finally {
                            yesBtn.removeEventListener('click', handleYes);
                            if (bulkUploadSubmitBtn) bulkUploadSubmitBtn.disabled = false;
                        }
                    };
                    yesBtn.addEventListener('click', handleYes);

                    // Clean up listener on modal hide
                    document.getElementById('bulkConfirmModal').addEventListener('hidden.bs.modal', () => {
                        yesBtn.removeEventListener('click', handleYes);
                    }, {
                        once: true
                    });

                } catch (err) {
                    alert('Error: ' + err.message);
                    if (bulkUploadSubmitBtn) bulkUploadSubmitBtn.disabled = false;
                }
            });
        </script>

        <script>
            document.querySelectorAll(".drop-zone__input").forEach((inputElement) => {
                const dropZoneElement = inputElement.closest(".drop-zone");

                dropZoneElement.addEventListener("click", (e) => {
                    inputElement.click();
                });

                inputElement.addEventListener("change", (e) => {
                    if (inputElement.files.length) {
                        updateThumbnail(dropZoneElement, inputElement.files[0]);
                    }
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

                    if (e.dataTransfer.files.length) {
                        inputElement.files = e.dataTransfer.files;
                        updateThumbnail(dropZoneElement, e.dataTransfer.files[0]);
                    }

                    dropZoneElement.classList.remove("drop-zone--over");
                });
            });
        </script>

        <!-- Product Management JavaScript -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Clear ALL product-related localStorage data when navigating to product-management page
                try {
                    const allKeys = Object.keys(localStorage);
                    let clearedCount = 0;
                    allKeys.forEach(key => {
                        // Clear all product specification keys (both edit and new product modes)
                        if (key.startsWith('product_specs_') || key.startsWith('product_specs_new_')) {
                            localStorage.removeItem(key);
                            clearedCount++;
                            console.log('✓ Cleared product localStorage key:', key);
                        }
                    });
                    if (clearedCount > 0) {
                        console.log('✓ Cleared ' + clearedCount + ' product-related localStorage key(s) on page load');
                    }
                } catch (e) {
                    console.error('Error clearing product localStorage on page load:', e);
                }
                
                // Clear localStorage after successful product save/update (if session flag exists)
                const localStorageKeyToClear = <?php 
                    if (isset($_SESSION['clear_localStorage_key']) && !empty($_SESSION['clear_localStorage_key'])) {
                        $key = $_SESSION['clear_localStorage_key'];
                        unset($_SESSION['clear_localStorage_key']); // Clear after reading
                        echo json_encode($key);
                    } else {
                        echo 'null';
                    }
                ?>;
                
                if (localStorageKeyToClear) {
                    try {
                        localStorage.removeItem(localStorageKeyToClear);
                        console.log('Cleared localStorage key after successful save:', localStorageKeyToClear);
                    } catch (e) {
                        console.error('Error clearing localStorage key:', e);
                    }
                }
                
                // Search functionality with debouncing and AJAX
                let searchTimeout;
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => {
                            loadProductsWithFilters();
                        }, 500); // 500ms debounce
                    });
                }

                // Clear search functionality
                const clearSearchBtn = document.getElementById('clearSearch');
                if (clearSearchBtn) {
                    clearSearchBtn.addEventListener('click', function () {
                        searchInput.value = '';
                        loadProductsWithFilters();
                    });
                }

                // Category filter functionality
                document.getElementById('categorySelect')?.addEventListener('change', function () {
                    const categoryId = this.value;
                    loadSubcategories(categoryId);
                    loadProductsWithFilters();
                });

                // Subcategory filter functionality
                document.getElementById('subcategorySelect')?.addEventListener('change', function () {
                    loadProductsWithFilters();
                });

                // Sort dropdown functionality
                document.getElementById('Default-sorting')?.addEventListener('change', function () {
                    loadProductsWithFilters();
                });

                // AJAX function to load products without page refresh
                function loadProductsWithFilters(page = 1) {
                    const params = new URLSearchParams();

                    // Get current filter values
                    const category = document.getElementById('categorySelect')?.value || '';
                    const subcategory = document.getElementById('subcategorySelect')?.value || '';
                    const sortBy = document.getElementById('Default-sorting')?.value || 'latest';
                    const searchTerm = document.getElementById('searchInput')?.value || '';

                    // Build parameters
                    if (category) params.set('category', category);
                    if (subcategory) params.set('subcategory', subcategory);
                    if (sortBy && sortBy !== 'latest') params.set('sort', sortBy);
                    if (searchTerm) params.set('search', searchTerm);
                    params.set('page', page);

                    // Show loading indicator
                    showLoadingIndicator();

                    // Make AJAX request
                    fetch('?' + params.toString())
                        .then(response => response.text())
                        .then(html => {
                            // Extract only the table content
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newTable = doc.querySelector('.table-responsive');
                            const newPagination = doc.querySelector('.pagination');

                            // Update table content
                            const currentTable = document.querySelector('.table-responsive');
                            if (currentTable && newTable) {
                                currentTable.innerHTML = newTable.innerHTML;
                            }

                            // Update pagination
                            const currentPagination = document.querySelector('.pagination');
                            if (newPagination) {
                                if (currentPagination) {
                                    currentPagination.parentElement.innerHTML = newPagination.parentElement.innerHTML;
                                }
                            } else {
                                if (currentPagination) {
                                    currentPagination.parentElement.innerHTML = '';
                                }
                            }

                            // Update URL without page refresh
                            const newUrl = window.location.pathname + '?' + params.toString();
                            window.history.pushState({}, '', newUrl);

                            hideLoadingIndicator();
                        })
                        .catch(error => {
                            console.error('Error loading products:', error);
                            hideLoadingIndicator();
                        });
                }

                // Loading indicator functions
                function showLoadingIndicator() {
                    const table = document.querySelector('.table-responsive');
                    if (table) {
                        table.style.opacity = '0.5';
                        table.style.pointerEvents = 'none';
                    }
                }

                function hideLoadingIndicator() {
                    const table = document.querySelector('.table-responsive');
                    if (table) {
                        table.style.opacity = '1';
                        table.style.pointerEvents = 'auto';
                    }
                }

                // Handle pagination clicks with AJAX
                document.addEventListener('click', function (e) {
                    if (e.target.closest('.pagination a')) {
                        e.preventDefault();
                        const href = e.target.closest('a').getAttribute('href');
                        if (href) {
                            const url = new URL(href, window.location.origin);
                            const page = url.searchParams.get('page');
                            if (page) {
                                loadProductsWithFilters(page);
                            }
                        }
                    }
                });

                // Function to load subcategories dynamically
                function loadSubcategories(categoryId) {
                    const subcategorySelect = document.getElementById('subcategorySelect');
                    if (!subcategorySelect) return;

                    // Clear existing options except the first one
                    subcategorySelect.innerHTML = '<option value="">All Sub Categories</option>';

                    if (!categoryId || categoryId === '') {
                        return;
                    }

                    // Fetch subcategories via AJAX
                    fetch(`<?php echo USER_BASEURL; ?>app/api/get_subcategories.php?category_id=${categoryId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.subcategories && data.subcategories.length > 0) {
                                data.subcategories.forEach(subcategory => {
                                    const option = document.createElement('option');
                                    option.value = subcategory.id;
                                    option.textContent = subcategory.name;
                                    subcategorySelect.appendChild(option);
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error loading subcategories:', error);
                        });
                }
            });

            // Delete product via POST back to the same page
            let __pendingDeleteProductId = null;

            function deleteProduct(productId) {
                __pendingDeleteProductId = String(productId);
                const modalEl = document.getElementById('confirmDeleteModal');
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }

            // Confirm delete handler
            (function () {
                const btn = document.getElementById('confirmDeleteBtn');
                if (!btn) return;
                btn.addEventListener('click', function () {
                    if (!__pendingDeleteProductId) return;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.href;
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'delete_product_id';
                    input.value = __pendingDeleteProductId;
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                });
            })();
        </script>
</body>

</html>