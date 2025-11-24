<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}

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
        preg_match('/^pending_product_(images|videos|video|path|payload)_admin_\d+$/', $key) ||
        preg_match('/^existing_specifications_\d+$/', $key) ||
        preg_match('/^db_specs_loaded_\d+$/', $key)) {
        $keysToRemove[] = $key;
    }
}
// Remove collected keys
foreach ($keysToRemove as $key) {
    unset($_SESSION[$key]);
}

// Export to Excel handler (applies current filters, ignores pagination)
if (isset($_POST['export_excel']) && $_POST['export_excel'] === '1') {
    // Collect filters
    $whereConditions = [];
    $whereConditions[] = "p.status = 'approved'"; // Only show approved products for superadmin
    $params = [];
    $paramTypes = '';

    if (isset($_POST['vendor_id']) && !empty($_POST['vendor_id'])) {
        $whereConditions[] = "p.vendor_id = ?";
        $params[] = (int) $_POST['vendor_id'];
        $paramTypes .= 'i';
    }
    if (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
        $whereConditions[] = "p.category_id = ?";
        $params[] = (int) $_POST['category_id'];
        $paramTypes .= 'i';
    }
    if (isset($_POST['sub_category_id']) && !empty($_POST['sub_category_id'])) {
        $whereConditions[] = "p.sub_category_id = ?";
        $params[] = (int) $_POST['sub_category_id'];
        $paramTypes .= 'i';
    }
    if (isset($_POST['q']) && !empty($_POST['q'])) {
        $searchTerm = trim($_POST['q']);
        $whereConditions[] = "(p.product_name LIKE ? OR v.business_name LIKE ?)";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
        $paramTypes .= 'ss';
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    // Build ORDER BY clause
    $orderBy = "ORDER BY p.created_date DESC";
    if (isset($_POST['sort_by']) && !empty($_POST['sort_by'])) {
        switch ($_POST['sort_by']) {
            case 'latest_order':
                $orderBy = "ORDER BY p.created_date DESC";
                break;
            case 'oldest_order':
                $orderBy = "ORDER BY p.created_date ASC";
                break;
            case 'price_high_low':
                $orderBy = "ORDER BY p.selling_price DESC";
                break;
            case 'price_low_high':
                $orderBy = "ORDER BY p.selling_price ASC";
                break;
            case 'stock_high_low':
                $orderBy = "ORDER BY p.Inventory DESC";
                break;
            case 'stock_low_high':
                $orderBy = "ORDER BY p.Inventory ASC";
                break;
            default:
                $orderBy = "ORDER BY p.created_date DESC";
                break;
        }
    }

    $exportQuery = "
        SELECT 
            p.id,
            p.vendor_id,
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
            p.Inventory,
            p.platform_fee,
            p.specifications,
            p.status,
            p.created_date,
            v.business_name,
            v.vendor_id AS vendor_public_id,
            c.name as category_name,
            sc.name as sub_category_name
        FROM products p
        LEFT JOIN vendor_registration v ON p.vendor_id = v.id
        LEFT JOIN category c ON p.category_id = c.id
        LEFT JOIN sub_category sc ON p.sub_category_id = sc.id
        {$whereClause}
        {$orderBy}
    ";

    $stmt = mysqli_prepare($con, $exportQuery);
    if ($stmt && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
    }
    $exec = $stmt ? mysqli_stmt_execute($stmt) : mysqli_query($con, $exportQuery);
    $result = $stmt ? mysqli_stmt_get_result($stmt) : $exec;

    // Collect all specifications first to optimize attribute fetching
    $rows = [];
    $allAttributeIds = [];
    $allVariantIds = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Get units sold and revenue for this product
            $unitsSold = 0;
            $revenue = 0;

            $orderQuery = "SELECT products FROM `order` WHERE products IS NOT NULL AND products != ''";
            $orderResult = mysqli_query($con, $orderQuery);

            if ($orderResult) {
                while ($order = mysqli_fetch_assoc($orderResult)) {
                    $productsData = json_decode($order['products'], true);
                    if (is_array($productsData)) {
                        foreach ($productsData as $orderProduct) {
                            if (isset($orderProduct['product_id']) && $orderProduct['product_id'] == $row['id']) {
                                $unitsSold += isset($orderProduct['quantity']) ? (int) $orderProduct['quantity'] : 0;
                                $revenue += isset($orderProduct['total']) ? (float) $orderProduct['total'] : 0;
                            }
                        }
                    }
                }
            }

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
                'units_sold' => $unitsSold,
                'revenue' => $revenue,
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
            // If JSON decode fails, return empty or show error indicator
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

    // Attempt to load PhpSpreadsheet
    $canSpreadsheet = false;
    $autoloadCandidates = [
        __DIR__ . '/../../../vendor/autoload.php'
    ];
    foreach ($autoloadCandidates as $autopath) {
        if (file_exists($autopath)) {
            @include_once $autopath;
            if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
                $canSpreadsheet = true;
                break;
            }
        }
    }

    if ($canSpreadsheet) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');

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
            'unitsSold',
            'revenue',
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
            $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, '#' . ($product['vendor_public_id'] ?: $product['vendor_id']));
            $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['sku_id']);
            $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['business_name']);
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
            $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['units_sold']);
            $sheet->setCellValue($colLetters[$colIndex++] . $rowNum, $product['revenue']);

            // Product status
            $inventory = (int) $product['Inventory'];
            if ($inventory <= 0) {
                $status = 'Out Of Stock';
            } elseif ($inventory <= 10) {
                $status = 'Low Stock';
            } else {
                $status = 'Available';
            }
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

        // Clean any previous output buffers
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        } else {
            @ob_end_clean();
        }

        $filename = 'products_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: public');
        header('Expires: 0');
        header('Content-Transfer-Encoding: binary');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // Fallback: CSV export
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    } else {
        @ob_end_clean();
    }
    $filename = 'products_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: public');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
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
        'unitsSold',
        'revenue',
        'productStatus',
        'listingDate'
    ]);
    foreach ($rows as $product) {
        $inventory = (int) $product['Inventory'];
        if ($inventory <= 0) {
            $status = 'Out Of Stock';
        } elseif ($inventory <= 10) {
            $status = 'Low Stock';
        } else {
            $status = 'Available';
        }
        // Ensure specifications are displayed - use formatted value
        $specsValue = !empty($product['specifications_formatted']) ? $product['specifications_formatted'] : '';
        fputcsv($out, [
            '#' . ($product['vendor_public_id'] ?: $product['vendor_id']),
            $product['sku_id'],
            $product['business_name'],
            $product['product_name'],
            $product['category_name'],
            $product['sub_category_name'],
            $product['brand_final'],
            $product['description_clean'],
            $specsValue,
            $product['hsn_id'] ?? '',
            $product['mrp'],
            $product['selling_price'],
            $product['discount'],
            $product['gst'],
            $product['coin'],
            $product['platform_fee'],
            $product['Inventory'],
            $product['units_sold'],
            $product['revenue'],
            $status,
            date('d/m/Y', strtotime($product['created_date']))
        ]);
    }
    fclose($out);
    exit;
}

// Handle product deletion
if (isset($_POST['delete_product_id']) && !empty($_POST['delete_product_id'])) {
    $productId = (int) $_POST['delete_product_id'];

    // First, check if the product exists
    $checkQuery = "SELECT id, product_name FROM products WHERE id = ?";
    $checkStmt = mysqli_prepare($con, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, 'i', $productId);
    mysqli_stmt_execute($checkStmt);
    $productResult = mysqli_stmt_get_result($checkStmt);

    if (mysqli_num_rows($productResult) > 0) {
        $productData = mysqli_fetch_assoc($productResult);

        // Delete the product
        $deleteQuery = "DELETE FROM products WHERE id = ?";
        $deleteStmt = mysqli_prepare($con, $deleteQuery);
        mysqli_stmt_bind_param($deleteStmt, 'i', $productId);

        if (mysqli_stmt_execute($deleteStmt)) {
            $_SESSION['success_message'] = "Product '{$productData['product_name']}' has been deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting product: " . mysqli_error($con);
        }

        mysqli_stmt_close($deleteStmt);
    } else {
        $_SESSION['error_message'] = "Product not found!";
    }

    mysqli_stmt_close($checkStmt);

    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="dark" data-toggled="close">

<head>
    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="X-UA-Compatible" content="Hagidy-Super-Admin">
    <title>PRODUCT MANAGEMENT | HADIDY</title>
    <meta name="Description" content="Hagidy-Super-Admin">
    <meta name="Author" content="Hagidy-Super-Admin">
    <meta name="keywords"
        content="blazor bootstrap, c# blazor, admin panel, blazor c#, template dashboard, admin, bootstrap admin template, blazor, blazorbootstrap, bootstrap 5 templates, dashboard, dashboard template bootstrap, admin dashboard bootstrap.">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo PUBLIC_ASSETS; ?>images/admin/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Choices JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>

    <!-- Main Theme Js -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/admin/styles.min.css" rel="stylesheet">

    <!-- Icons Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/admin/icons.css" rel="stylesheet">

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

    <!-- Custom CSS for Address Truncation -->

</head>

<body>


    <!-- Loader -->
    <div id="loader">
        <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/media/loader.svg" alt="">
    </div>
    <!-- Loader -->

    <div class="page">
        <!-- app-header -->
        <?php include './include/header.php'; ?>
        <!-- /app-header -->
        <!-- Start::app-sidebar -->
        <?php include './include/sidebar.php'; ?>


        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div
                    class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">All Products
                        <?php if (isset($totalRecords)): ?>
                            <span class="text-muted fs-14">(<?php echo number_format($totalRecords); ?> total)</span>
                        <?php endif; ?>
                    </h1>
                    <div>
                        <button type="button" id="exportToExcel" class="btn-down-excle1"><i
                                class="fa-solid fa-file-arrow-down"></i>
                            Export to Excel</button>
                    </div>
                </div>
                <!-- Page Header Close -->

                <!-- Success/Error Messages -->
                <?php 
                // Prioritize error messages over success messages - only show one at a time
                // Check in order: error_message > flash_error > success_message > flash_success
                if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])): 
                    // Clear all success messages if error exists
                    unset($_SESSION['success_message'], $_SESSION['flash_success']);
                ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php elseif (isset($_SESSION['flash_error']) && !empty($_SESSION['flash_error'])): 
                    // Clear all success messages if error exists
                    unset($_SESSION['success_message'], $_SESSION['flash_success'], $_SESSION['error_message']);
                ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['flash_error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['flash_error']); ?>
                <?php elseif (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])): 
                    // Clear error messages if success exists
                    unset($_SESSION['error_message'], $_SESSION['flash_error'], $_SESSION['flash_success']);
                ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php elseif (isset($_SESSION['flash_success']) && !empty($_SESSION['flash_success'])): 
                    // Clear error messages if success exists
                    unset($_SESSION['error_message'], $_SESSION['flash_error'], $_SESSION['success_message']);
                ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['flash_success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['flash_success']); ?>
                <?php endif; ?>
                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap w-100-product">
                                    <?php

                                    $pmFilterVendorId = isset($_POST['vendor_id']) ? (int) $_POST['vendor_id'] : 0;
                                    $pmFilterCategoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
                                    $pmFilterSubCategoryId = isset($_POST['sub_category_id']) ? (int) $_POST['sub_category_id'] : 0;
                                    $pmSearchTerm = isset($_POST['q']) ? trim((string) $_POST['q']) : '';
                                    $pmSortBy = isset($_POST['sort_by']) ? trim((string) $_POST['sort_by']) : 'default';

                                    $pmVendorsRes = isset($con) ? mysqli_query($con, "SELECT id, business_name FROM vendor_registration ORDER BY business_name ASC") : false;
                                    $pmCategoriesRes = isset($con) ? mysqli_query($con, "SELECT id, name FROM category ORDER BY name ASC") : false;
                                    $pmSubCategoriesRes = null;
                                    if ($pmFilterCategoryId > 0 && isset($con)) {
                                        $pmSubCategoriesRes = mysqli_query($con, "SELECT id, name FROM sub_category WHERE category_id = " . $pmFilterCategoryId . " ORDER BY name ASC");
                                    }
                                    ?>
                                    <form method="post" id="pmFilterForm"
                                        class="d-flex align-items-center gap-2 flex-wrap w-100-product">
                                        <input type="hidden" name="page" id="pmPageInput"
                                            value="<?php echo isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1; ?>">
                                        <div class="selecton-order">
                                            <select name="vendor_id" id="pmVendorSelect"
                                                class="form-select form-select-lg" onchange="this.form.submit()">
                                                <option value="0" <?php echo $pmFilterVendorId === 0 ? ' selected' : ''; ?>>Seller (All)</option>
                                                <?php if ($pmVendorsRes) {
                                                    while ($v = mysqli_fetch_assoc($pmVendorsRes)) { ?>
                                                        <option value="<?php echo (int) $v['id']; ?>" <?php echo $pmFilterVendorId === (int) $v['id'] ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($v['business_name']); ?>
                                                        </option>
                                                    <?php }
                                                } ?>
                                            </select>
                                        </div>
                                        <div class="selecton-order">
                                            <select name="category_id" id="pmCategorySelect"
                                                class="form-select form-select-lg" onchange="this.form.submit()">
                                                <option value="0" <?php echo $pmFilterCategoryId === 0 ? ' selected' : ''; ?>>Category (All)</option>
                                                <?php if ($pmCategoriesRes) {
                                                    while ($c = mysqli_fetch_assoc($pmCategoriesRes)) { ?>
                                                        <option value="<?php echo (int) $c['id']; ?>" <?php echo $pmFilterCategoryId === (int) $c['id'] ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($c['name']); ?>
                                                        </option>
                                                    <?php }
                                                } ?>
                                            </select>
                                        </div>
                                        <div class="selecton-order">
                                            <select name="sub_category_id" id="pmSubCategorySelect"
                                                class="form-select form-select-lg selecton-order"
                                                onchange="this.form.submit()">
                                                <option value="0" <?php echo $pmFilterSubCategoryId === 0 ? ' selected' : ''; ?>>Sub Category (All)</option>
                                                <?php if ($pmSubCategoriesRes) {
                                                    while ($sc = mysqli_fetch_assoc($pmSubCategoriesRes)) { ?>
                                                        <option value="<?php echo (int) $sc['id']; ?>" <?php echo $pmFilterSubCategoryId === (int) $sc['id'] ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($sc['name']); ?>
                                                        </option>
                                                    <?php }
                                                } ?>
                                            </select>
                                        </div>
                                    </form>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div for="Default-sorting"><b>Sort By :</b></div>
                                    <div class="selecton-order">
                                        <form method="post" id="sortForm">
                                            <input type="hidden" name="vendor_id"
                                                value="<?php echo $pmFilterVendorId; ?>">
                                            <input type="hidden" name="category_id"
                                                value="<?php echo $pmFilterCategoryId; ?>">
                                            <input type="hidden" name="sub_category_id"
                                                value="<?php echo $pmFilterSubCategoryId; ?>">
                                            <input type="hidden" name="q"
                                                value="<?php echo htmlspecialchars($pmSearchTerm); ?>">
                                            <select name="sort_by" id="Default-sorting"
                                                class="form-select form-select-lg"
                                                onchange="document.getElementById('sortForm').submit()">
                                                <option value="default" <?php echo (!isset($_POST['sort_by']) || $_POST['sort_by'] == 'default') ? ' selected' : ''; ?>>Default sorting
                                                </option>
                                                <option value="latest_order" <?php echo (isset($_POST['sort_by']) && $_POST['sort_by'] == 'latest_order') ? ' selected' : ''; ?>>Sort by
                                                    latest Order</option>
                                                <option value="oldest_order" <?php echo (isset($_POST['sort_by']) && $_POST['sort_by'] == 'oldest_order') ? ' selected' : ''; ?>>Sort by
                                                    oldest Order</option>
                                                <option value="price_high_low" <?php echo (isset($_POST['sort_by']) && $_POST['sort_by'] == 'price_high_low') ? ' selected' : ''; ?>>Sort by
                                                    Amount: high to low</option>
                                                <option value="price_low_high" <?php echo (isset($_POST['sort_by']) && $_POST['sort_by'] == 'price_low_high') ? ' selected' : ''; ?>>Sort by
                                                    Amount: low to high</option>
                                                <option value="revenue_high_low" <?php echo (isset($_POST['sort_by']) && $_POST['sort_by'] == 'revenue_high_low') ? ' selected' : ''; ?>>Sort
                                                    by Revenue: high to low</option>
                                                <option value="revenue_low_high" <?php echo (isset($_POST['sort_by']) && $_POST['sort_by'] == 'revenue_low_high') ? ' selected' : ''; ?>>Sort
                                                    by Revenue: low to high</option>
                                                <option value="stock_high_low" <?php echo (isset($_POST['sort_by']) && $_POST['sort_by'] == 'stock_high_low') ? ' selected' : ''; ?>>Sort by
                                                    Stock: high to low</option>
                                                <option value="stock_low_high" <?php echo (isset($_POST['sort_by']) && $_POST['sort_by'] == 'stock_low_high') ? ' selected' : ''; ?>>Sort by
                                                    Stock: low to high</option>
                                            </select>
                                        </form>
                                    </div>
                                    <div class="selecton-order">
                                        <form method="post" id="searchForm" class="d-flex">
                                            <input type="hidden" name="vendor_id"
                                                value="<?php echo $pmFilterVendorId; ?>">
                                            <input type="hidden" name="category_id"
                                                value="<?php echo $pmFilterCategoryId; ?>">
                                            <input type="hidden" name="sub_category_id"
                                                value="<?php echo $pmFilterSubCategoryId; ?>">
                                            <input type="search" name="q" id="searchInput" class="form-control"
                                                placeholder="Search products or sellers"
                                                value="<?php echo htmlspecialchars($pmSearchTerm); ?>"
                                                aria-describedby="button-addon2">
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" id="exportForm" style="display:none;">
                                <input type="hidden" name="export_excel" value="1">
                                <input type="hidden" name="vendor_id" id="exp_vendor_id"
                                    value="<?php echo $pmFilterVendorId; ?>">
                                <input type="hidden" name="category_id" id="exp_category_id"
                                    value="<?php echo $pmFilterCategoryId; ?>">
                                <input type="hidden" name="sub_category_id" id="exp_sub_category_id"
                                    value="<?php echo $pmFilterSubCategoryId; ?>">
                                <input type="hidden" name="sort_by" id="exp_sort_by"
                                    value="<?php echo htmlspecialchars($pmSortBy); ?>">
                                <input type="hidden" name="q" id="exp_search"
                                    value="<?php echo htmlspecialchars($pmSearchTerm); ?>">
                            </form>
                            <div class="table-responsive">
                                <table class="table table-striped text-nowrap align-middle table-bordered-vertical">
                                    <thead class="table-group-divider">
                                        <tr>
                                            <th scope="col">No</th>
                                            <th scope="col">Seller ID</th>
                                            <th scope="col">SKU ID</th>
                                            <th scope="col">Seller Name</th>
                                            <th scope="col">Product</th>
                                            <th scope="col">Category</th>
                                            <th scope="col">Sub Category</th>
                                            <th scope="col">MRP</th>
                                            <th scope="col">Price</th>
                                            <th scope="col">Discount (%)</th>
                                            <th scope="col">GST (%)</th>
                                            <th scope="col">Coins</th>
                                            <th scope="col">Platform Fee</th>
                                            <th scope="col">Available Stock</th>
                                            <th scope="col">Units Sold</th>
                                            <th scope="col">Revenue</th>
                                            <th scope="col">Product Status</th>
                                            <th scope="col">Listing Date</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                        <?php
                                        // Pagination settings
                                        $recordsPerPage = 10;
                                        $currentPage = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
                                        $offset = ($currentPage - 1) * $recordsPerPage;

                                        // Build WHERE clause for filtering
                                        $whereConditions = [];
                                        $whereConditions[] = "p.status = 'approved'"; // Only show approved products for superadmin
                                        $params = [];
                                        $paramTypes = '';

                                        if ($pmFilterVendorId > 0) {
                                            $whereConditions[] = "p.vendor_id = ?";
                                            $params[] = $pmFilterVendorId;
                                            $paramTypes .= 'i';
                                        }

                                        if ($pmFilterCategoryId > 0) {
                                            $whereConditions[] = "p.category_id = ?";
                                            $params[] = $pmFilterCategoryId;
                                            $paramTypes .= 'i';
                                        }

                                        if ($pmFilterSubCategoryId > 0) {
                                            $whereConditions[] = "p.sub_category_id = ?";
                                            $params[] = $pmFilterSubCategoryId;
                                            $paramTypes .= 'i';
                                        }

                                        if (!empty($pmSearchTerm)) {
                                            $whereConditions[] = "(p.product_name LIKE ? OR v.business_name LIKE ?)";
                                            $params[] = "%{$pmSearchTerm}%";
                                            $params[] = "%{$pmSearchTerm}%";
                                            $paramTypes .= 'ss';
                                        }

                                        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

                                        // Build ORDER BY clause based on sorting option
                                        $orderByClause = "ORDER BY p.updated_date DESC"; // Default sorting
                                        switch ($pmSortBy) {
                                            case 'latest_order':
                                                $orderByClause = "ORDER BY p.created_date DESC";
                                                break;
                                            case 'oldest_order':
                                                $orderByClause = "ORDER BY p.created_date ASC";
                                                break;
                                            case 'price_high_low':
                                                $orderByClause = "ORDER BY CAST(p.selling_price AS UNSIGNED) DESC";
                                                break;
                                            case 'price_low_high':
                                                $orderByClause = "ORDER BY CAST(p.selling_price AS UNSIGNED) ASC";
                                                break;
                                            case 'stock_high_low':
                                                $orderByClause = "ORDER BY CAST(p.Inventory AS UNSIGNED) DESC";
                                                break;
                                            case 'stock_low_high':
                                                $orderByClause = "ORDER BY CAST(p.Inventory AS UNSIGNED) ASC";
                                                break;
                                            case 'revenue_high_low':
                                                // For revenue sorting, we'll need a subquery or calculate it separately
                                                $orderByClause = "ORDER BY CAST(p.created_date AS UNSIGNED) DESC"; // Will be handled in PHP
                                                break;
                                            case 'revenue_low_high':
                                                $orderByClause = "ORDER BY CAST(p.created_date AS UNSIGNED) DESC"; // Will be handled in PHP
                                                break;
                                            default:
                                                $orderByClause = "ORDER BY p.created_date DESC";
                                                break;
                                        }

                                        // Main query to get products with vendor, category, and subcategory information
                                        $mainQuery = "
    SELECT 
        p.id,
        p.vendor_id,
        p.sku_id,
        p.product_name,
        p.mrp,
        p.selling_price,
        p.discount,
        p.gst,
        p.coin,
        p.Inventory,
        p.platform_fee,
        p.images,
        p.status,
        p.created_date,
        v.business_name,
        v.vendor_id AS vendor_public_id,
        c.name as category_name,
        sc.name as sub_category_name
    FROM products p
    LEFT JOIN vendor_registration v ON p.vendor_id = v.id
    LEFT JOIN category c ON p.category_id = c.id
    LEFT JOIN sub_category sc ON p.sub_category_id = sc.id
    {$whereClause}
    {$orderByClause}
    LIMIT {$offset}, {$recordsPerPage}
";
                                        // echo $mainQuery;
                                        // Execute main query
                                        $stmt = mysqli_prepare($con, $mainQuery);
                                        if ($stmt && !empty($params)) {
                                            mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
                                        }
                                        $result = $stmt ? mysqli_stmt_execute($stmt) : mysqli_query($con, $mainQuery);
                                        $products = $stmt ? mysqli_stmt_get_result($stmt) : $result;

                                        // Check for query errors
                                        if (!$products) {
                                            echo '<tr><td colspan="17" class="text-center text-danger">Database error: ' . mysqli_error($con) . '</td></tr>';
                                            $products = false;
                                        }

                                        // Count total records for pagination
                                        $countQuery = "
    SELECT COUNT(*) as total
    FROM products p
    LEFT JOIN vendor_registration v ON p.vendor_id = v.id
    LEFT JOIN category c ON p.category_id = c.id
    LEFT JOIN sub_category sc ON p.sub_category_id = sc.id
    {$whereClause}
";

                                        $countStmt = mysqli_prepare($con, $countQuery);
                                        if ($countStmt && !empty($params)) {
                                            mysqli_stmt_bind_param($countStmt, $paramTypes, ...$params);
                                        }
                                        $countResult = $countStmt ? mysqli_stmt_execute($countStmt) : mysqli_query($con, $countQuery);
                                        $totalRecords = $countStmt ? mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] : mysqli_fetch_assoc($countResult)['total'];
                                        $totalPages = $totalRecords > 0 ? ceil($totalRecords / $recordsPerPage) : 1;

                                        $rowNumber = $offset + 1;

                                        if ($products && mysqli_num_rows($products) > 0) {
                                            while ($product = mysqli_fetch_assoc($products)) {
                                                // Get units sold and revenue for this product
                                                $unitsSold = 0;
                                                $revenue = 0;

                                                // Query to get units sold and revenue from orders
                                                $orderQuery = "SELECT products FROM `order` WHERE products IS NOT NULL AND products != ''";
                                                $orderResult = mysqli_query($con, $orderQuery);

                                                if ($orderResult) {
                                                    while ($order = mysqli_fetch_assoc($orderResult)) {
                                                        $productsData = json_decode($order['products'], true);
                                                        if (is_array($productsData)) {
                                                            foreach ($productsData as $orderProduct) {
                                                                if (isset($orderProduct['product_id']) && $orderProduct['product_id'] == $product['id']) {
                                                                    $unitsSold += isset($orderProduct['quantity']) ? (int) $orderProduct['quantity'] : 0;
                                                                    $revenue += isset($orderProduct['total']) ? (float) $orderProduct['total'] : 0;
                                                                }
                                                            }
                                                        }
                                                    }
                                                }

                                                // Parse product images
                                                $productImages = [];
                                                if (!empty($product['images'])) {
                                                    $productImages = json_decode($product['images'], true);
                                                }
                                                $firstImage = !empty($productImages) && isset($productImages[0]) ? $productImages[0] : 'uploads/vendors/no-product.png';

                                                // Determine product status badge
                                                $statusBadge = '';
                                                $inventory = (int) $product['Inventory'];

                                                if ($inventory <= 0) {
                                                    $statusBadge = '<span class="badge bg-danger-transparent">Out Of Stock</span>';
                                                } elseif ($inventory <= 10) {
                                                    $statusBadge = '<span class="badge bg-warning-transparent">Low Stock</span>';
                                                } else {
                                                    $statusBadge = '<span class="badge bg-success-transparent">Available</span>';
                                                }

                                                // Format dates
                                                $listingDate = date('d F, Y', strtotime($product['created_date']));

                                                // Format prices
                                                $mrp = '₹' . number_format($product['mrp'], 2);
                                                $sellingPrice = '₹' . number_format($product['selling_price'], 2);
                                                $revenueFormatted = '₹' . number_format($revenue, 2);
                                                ?>
                                                <tr>
                                                    <td><?php echo $rowNumber; ?></td>
                                                    <td class="rqu-id">
                                                        #<?php echo htmlspecialchars(isset($product['vendor_public_id']) && $product['vendor_public_id'] !== '' ? $product['vendor_public_id'] : $product['vendor_id']); ?>
                                                    </td>
                                                    <td class="rqu-id"><?php echo htmlspecialchars($product['sku_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($product['business_name']); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="product-img">
                                                                <img src="<?php
                                                                if ($firstImage !== '') {
                                                                    echo $vendor_baseurl . htmlspecialchars($firstImage);
                                                                } else {
                                                                    echo $vendor_baseurl . '/assets/images/default.png';
                                                                } ?>"
                                                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                                    style="width:32px;height:32px;border-radius:6px;margin-right:8px;">
                                                            </div>
                                                            <div class="product-name-text">
                                                                <H4><?php echo htmlspecialchars($product['product_name']); ?>
                                                                </H4>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge bg-light1 text-dark"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($product['sub_category_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr"><?php echo $mrp; ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr"><?php echo $sellingPrice; ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr"><?php echo $product['discount']; ?>%</div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr"><?php echo $product['gst']; ?>%</div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr"><?php echo $product['coin']; ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr"><?php echo $product['platform_fee']; ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr"><?php echo $inventory; ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr"><?php echo $unitsSold; ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr"><?php echo $revenueFormatted; ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr"><?php echo $statusBadge; ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr"><?php echo $listingDate; ?></div>
                                                    </td>
                                                    <td class="">
                                                        <div class="action-cell d-flex align-items-center gap-2">
                                                            <a href="./product-details.php?id=<?php echo $product['id']; ?>"
                                                                class="i-icon-product"><i class="fa-solid fa-eye"></i></a>
                                                            <a href="./first-edit-product.php?id=<?php echo $product['id']; ?>"
                                                                class="i-icon-eidt"><i
                                                                    class="fa-regular fa-pen-to-square"></i></a>
                                                            <a href="#" class="i-icon-trash cancelOrderBtn"
                                                                data-product-id="<?php echo $product['id']; ?>"><i
                                                                    class="fa-solid fa-trash-can"></i></a>
                                                            <a href="#" class="btn btn-sm btn-warning edit-fee-btn"
                                                                data-product-id="<?php echo $product['id']; ?>">
                                                                Edit Fees
                                                            </a>

                                                        </div>
                                                    </td>

                                                </tr>
                                                <?php
                                                $rowNumber++;
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td colspan="17" class="text-center">
                                                    <i class="fe fe-inbox fs-48 mb-3 text-muted"></i>
                                                    <div class="text-muted">No products found</div>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-center mt-3 mb-3">
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php if ($currentPage > 1): ?>
                                            <li class="page-item">
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="vendor_id"
                                                        value="<?php echo $pmFilterVendorId; ?>">
                                                    <input type="hidden" name="category_id"
                                                        value="<?php echo $pmFilterCategoryId; ?>">
                                                    <input type="hidden" name="sub_category_id"
                                                        value="<?php echo $pmFilterSubCategoryId; ?>">
                                                    <input type="hidden" name="q"
                                                        value="<?php echo htmlspecialchars($pmSearchTerm); ?>">
                                                    <input type="hidden" name="sort_by"
                                                        value="<?php echo htmlspecialchars($pmSortBy); ?>">
                                                    <button type="submit" name="page"
                                                        value="<?php echo $currentPage - 1; ?>"
                                                        class="page-link">Previous</button>
                                                </form>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">Previous</span>
                                            </li>
                                        <?php endif; ?>

                                        <?php
                                        $startPage = max(1, $currentPage - 2);
                                        $endPage = min($totalPages, $currentPage + 2);

                                        for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                            <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="vendor_id"
                                                        value="<?php echo $pmFilterVendorId; ?>">
                                                    <input type="hidden" name="category_id"
                                                        value="<?php echo $pmFilterCategoryId; ?>">
                                                    <input type="hidden" name="sub_category_id"
                                                        value="<?php echo $pmFilterSubCategoryId; ?>">
                                                    <input type="hidden" name="q"
                                                        value="<?php echo htmlspecialchars($pmSearchTerm); ?>">
                                                    <input type="hidden" name="sort_by"
                                                        value="<?php echo htmlspecialchars($pmSortBy); ?>">
                                                    <button type="submit" name="page" value="<?php echo $i; ?>"
                                                        class="page-link"><?php echo $i; ?></button>
                                                </form>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($currentPage < $totalPages): ?>
                                            <li class="page-item">
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="vendor_id"
                                                        value="<?php echo $pmFilterVendorId; ?>">
                                                    <input type="hidden" name="category_id"
                                                        value="<?php echo $pmFilterCategoryId; ?>">
                                                    <input type="hidden" name="sub_category_id"
                                                        value="<?php echo $pmFilterSubCategoryId; ?>">
                                                    <input type="hidden" name="q"
                                                        value="<?php echo htmlspecialchars($pmSearchTerm); ?>">
                                                    <input type="hidden" name="sort_by"
                                                        value="<?php echo htmlspecialchars($pmSortBy); ?>">
                                                    <button type="submit" name="page"
                                                        value="<?php echo $currentPage + 1; ?>"
                                                        class="page-link">Next</button>
                                                </form>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">Next</span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>

                    </div>
                </div>
                <!-- End:: row-2 -->
            </div>
            <!-- End::app-content -->

            <!-- Delete Product Confirmation Modal -->
            <div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content"
                        style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                        <div class="modal-body text-center p-4">
                            <!-- Icon -->
                            <div class="mb-3">
                                <div
                                    style="width: 60px; height: 60px; background-color: #F45B4B; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto;">
                                    <i class="fas fa-info" style="color: white; font-size: 24px;"></i>
                                </div>
                            </div>

                            <!-- Message -->
                            <h5 class="mb-2" style="color: #4A5568; font-weight: 600; font-size: 18px;">
                                Delete Product
                            </h5>
                            <p class="mb-4 text-muted" id="deleteProductMessage">
                                Are you sure you want to delete this product? This action cannot be undone.
                            </p>

                            <!-- Hidden form for delete operation -->
                            <form method="post" id="deleteProductForm" style="display: none;">
                                <input type="hidden" name="delete_product_id" id="deleteProductId">
                            </form>

                            <!-- Buttons -->
                            <div class="d-flex gap-3 justify-content-center">
                                <button type="button" class="btn btn-outline-secondary" id="deleteNoBtn"
                                    style="border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                    Cancel
                                </button>
                                <button type="button" class="btn btn-danger" id="deleteYesBtn"
                                    style="border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                    <i class="fas fa-trash-can me-1"></i>Delete
                                </button>
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

        <!-- Popper JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/@popperjs/core/umd/popper.min.js"></script>

        <!-- Bootstrap JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/js/bootstrap.bundle.min.js"></script>

        <!-- Defaultmenu JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/defaultmenu.min.js"></script>

        <!-- Node Waves JS-->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.js"></script>

        <!-- Sticky JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/sticky.js"></script>

        <!-- Simplebar JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.js"></script>
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/simplebar.js"></script>

        <!-- Color Picker JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/pickr.es5.min.js"></script>

        <!-- Apex Charts JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/apexcharts/apexcharts.min.js"></script>

        <!-- Ecommerce-Dashboard JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/ecommerce-dashboard.js"></script>

        <!-- Custom-Switcher JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/custom-switcher.min.js"></script>

        <!-- Date & Time Picker JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.js"></script>
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/date&time_pickers.js"></script>

        <!-- Custom JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/custom.js"></script>

        <!-- JavaScript for Address Truncation -->

        <script>
            function attachFeeEditListeners() {
                document.querySelectorAll('.edit-fee-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var productId = this.getAttribute('data-product-id');
                        var td = btn.closest('.action-cell');
                        var tr = td.closest('tr');

                        // Hide the edit button
                        btn.style.display = 'none';

                        // Get the coin and platform_fee cells
                        var coinCell = tr.querySelectorAll('td')[11]; // assuming 12th column = coin
                        var feeCell = tr.querySelectorAll('td')[12]; // assuming 13th column = platform_fee

                        if (coinCell && !coinCell.querySelector('input')) {
                            var coinValue = coinCell.textContent.trim();
                            coinCell.innerHTML = '<input type="number" class="form-control form-control-sm" value="' + coinValue + '" style="width:80px;" step="1">';
                        }

                        if (feeCell && !feeCell.querySelector('input')) {
                            var feeValue = feeCell.textContent.trim();
                            feeCell.innerHTML = '<input type="number" class="form-control form-control-sm" value="' + feeValue + '" style="width:80px;" step="0.01">';
                        }

                        // Add Update button
                        var updateBtn = document.createElement('button');
                        updateBtn.className = 'btn btn-success btn-sm ms-2 action-update-fee-btn';
                        updateBtn.textContent = 'Update';

                        updateBtn.onclick = function () {
                            var newCoin = coinCell.querySelector('input').value;
                            var newFee = feeCell.querySelector('input').value;

                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', '<?php echo USER_BASEURL; ?>api/update_product_fee.php', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.onreadystatechange = function () {
                                if (xhr.readyState === 4 && xhr.status === 200) {
                                    try {
                                        var response = JSON.parse(xhr.responseText);
                                        if (response.success) {
                                            // Update UI
                                            coinCell.textContent = newCoin;
                                            feeCell.textContent = newFee;
                                            updateBtn.remove();
                                            btn.style.display = 'inline-block';
                                            showSuccessMessage('Fees updated successfully!');
                                        } else {
                                            showErrorMessage('Error: ' + response.message);
                                        }
                                    } catch (e) {
                                        showErrorMessage('Invalid server response');
                                    }
                                }
                            };
                            xhr.send('product_id=' + productId + '&coin=' + newCoin + '&platform_fee=' + newFee);
                        };

                        td.appendChild(updateBtn);
                    });
                });
            }

            // Run this after table load
            attachFeeEditListeners();

            // Re-attach if your table reloads via AJAX pagination
            document.addEventListener('ajaxTableReload', attachFeeEditListeners);
        </script>



        <!-- Modal JavaScript -->
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
                
                // Clear localStorage for product specifications after successful save/update (if session flag exists)
                <?php if (isset($_SESSION['clear_localStorage_key']) && !empty($_SESSION['clear_localStorage_key'])): ?>
                try {
                    const keyToClear = '<?php echo htmlspecialchars($_SESSION['clear_localStorage_key']); ?>';
                    localStorage.removeItem(keyToClear);
                    console.log('✓ Cleared localStorage after successful product save/update:', keyToClear);
                    
                    // Clear the session flag
                    <?php unset($_SESSION['clear_localStorage_key']); ?>
                } catch (e) {
                    console.error('Error clearing localStorage after successful save:', e);
                }
                <?php endif; ?>
                
                // Get all delete buttons and modal elements
                const deleteBtns = document.querySelectorAll('.cancelOrderBtn');
                const modal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
                const deleteNoBtn = document.getElementById('deleteNoBtn');
                const deleteYesBtn = document.getElementById('deleteYesBtn');
                const deleteProductId = document.getElementById('deleteProductId');
                const deleteProductForm = document.getElementById('deleteProductForm');
                const deleteProductMessage = document.getElementById('deleteProductMessage');

                // Show modal when any delete button is clicked
                deleteBtns.forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();

                        // Get product ID from data attribute
                        const productId = btn.getAttribute('data-product-id');

                        // Set the product ID in the hidden input
                        deleteProductId.value = productId;

                        // Update the message with product ID
                        deleteProductMessage.textContent = `Are you sure you want to delete product ? This action cannot be undone.`;

                        // Show the modal
                        modal.show();
                    });
                });

                // Handle Cancel button click
                if (deleteNoBtn) {
                    deleteNoBtn.addEventListener('click', function () {
                        modal.hide();
                    });
                }

                // Handle Delete button click
                if (deleteYesBtn) {
                    deleteYesBtn.addEventListener('click', function () {
                        // Show loading state
                        deleteYesBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...';
                        deleteYesBtn.disabled = true;

                        // Submit the form
                        deleteProductForm.submit();
                    });
                }

                // Search functionality without button
                const searchInput = document.getElementById('searchInput');
                const searchForm = document.getElementById('searchForm');
                let searchTimeout;

                if (searchInput && searchForm) {
                    searchInput.addEventListener('input', function () {
                        clearTimeout(searchTimeout);

                        // Show loading indicator
                        const tableContainer = document.querySelector('.table-responsive');
                        if (tableContainer) {
                            tableContainer.style.opacity = '0.6';
                        }

                        searchTimeout = setTimeout(function () {
                            searchForm.submit();
                        }, 500); // Submit after 500ms of no typing
                    });
                }

                // Sorting functionality without button
                const sortSelect = document.getElementById('Default-sorting');
                const sortForm = document.getElementById('sortForm');

                if (sortSelect && sortForm) {
                    sortSelect.addEventListener('change', function () {
                        // Show loading indicator
                        const tableContainer = document.querySelector('.table-responsive');
                        if (tableContainer) {
                            tableContainer.style.opacity = '0.6';
                        }

                        // Submit form immediately when sorting changes
                        sortForm.submit();
                    });
                }

                // Auto-dismiss success/error alerts after 5 seconds
                setTimeout(function () {
                    document.querySelectorAll('.alert').forEach(function (el) {
                        try {
                            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
                            bsAlert.close();
                        } catch (err) {
                            if (el && el.parentNode) {
                                el.parentNode.removeChild(el);
                            }
                        }
                    });
                }, 5000);

                // Export to Excel functionality
                const exportBtn = document.getElementById('exportToExcel');
                if (exportBtn) {
                    exportBtn.addEventListener('click', function () {
                        // Copy current filter values to export form
                        const vendorSelect = document.getElementById('pmVendorSelect');
                        const categorySelect = document.getElementById('pmCategorySelect');
                        const subCategorySelect = document.getElementById('pmSubCategorySelect');
                        const sortSelect = document.getElementById('Default-sorting');
                        const searchInput = document.getElementById('searchInput');

                        if (vendorSelect) {
                            document.getElementById('exp_vendor_id').value = vendorSelect.value;
                        }
                        if (categorySelect) {
                            document.getElementById('exp_category_id').value = categorySelect.value;
                        }
                        if (subCategorySelect) {
                            document.getElementById('exp_sub_category_id').value = subCategorySelect.value;
                        }
                        if (sortSelect) {
                            document.getElementById('exp_sort_by').value = sortSelect.value;
                        }
                        if (searchInput) {
                            document.getElementById('exp_search').value = searchInput.value;
                        }

                        // Submit export form
                        document.getElementById('exportForm').submit();
                    });
                }
            });
        </script>
</body>

</html>