<?php
error_reporting(0);
ini_set("display_errors", 0);
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/error.log");
use PhpOffice\PhpSpreadsheet\Spreadsheet;   
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
require_once __DIR__ . '/../pages/includes/init.php';


if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
    http_response_code(403);
    exit;
}
$userId = (int)$_SESSION['user_id'];
$month    = isset($_POST['month']) ? trim($_POST['month']) : '';
$fromDate = isset($_POST['from_date']) ? trim($_POST['from_date']) : '';
$toDate   = isset($_POST['to_date']) ? trim($_POST['to_date']) : '';
$where  = ['user_id = ?'];
$params = [$userId];
$types  = 'i';
if ($month !== '') {
    $where[] = 'MONTH(created_date) = ?';
    $params[] = (int)$month;
    $types .= 'i';
}
if ($fromDate !== '') {
    $where[] = 'DATE(created_date) >= ?';
    $params[] = $fromDate;
    $types .= 's';
}
if ($toDate !== '') {
    $where[] = 'DATE(created_date) <= ?';
    $params[] = $toDate;
    $types .= 's';
}
$sql = 'SELECT 
    id,
    user_id,
    products,
    order_id,
    cart_amount,
    shipping_charge,
    coupan_saving,
    total_amount,
    coin_earn,
    payment_method,
    payment_status,
    address_id,
    order_status,
    order_track,
    reason,
    date,
    created_date,
    updated_date
  FROM `order`
  WHERE ' . implode(' AND ', $where) . ' 
  ORDER BY created_date DESC';
$stmt = mysqli_prepare($db_connection, $sql);
if (!$stmt) {
    http_response_code(500);
    exit;
}
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products = json_decode($row['products'], true);
    // If there are product line items, expand each into its own row while repeating order-level fields
    if (is_array($products) && count($products) > 0) {
        foreach ($products as $p) {
            $qty   = isset($p['quantity']) ? (float)$p['quantity'] : 0;
            $price = isset($p['price']) ? (float)$p['price'] : 0;
            $rows[] = array_merge($row, [
                'product_id'     => $p['product_id'] ?? '',
                'quantity'       => $qty,
                'price'          => $price,
                'product_amount' => $qty * $price,
                'product_status' => $p['product_status'] ?? '',
                'product_coin'   => $p['coin'] ?? '',
                'vendor_id'      => $p['vendor_id'] ?? '',
                'selected_variants_json' => $p['selected_variants_json'] ?? '',
                'variant_prices_json' => $p['variant_prices_json'] ?? ''
            ]);
        }
    } else {
        $rows[] = array_merge($row, [
            'product_id'     => '',
            'quantity'       => '',
            'price'          => '',
            'product_amount' => '',
            'product_status' => '',
            'product_coin'   => '',
            'vendor_id'      => '',
            'selected_variants_json' => '',
            'variant_prices_json' => ''
        ]);
    }
}
$canSpreadsheet = false;
// Prefer the bundled admin/packages autoloader first (vendor/ may be incomplete)
$autoloadCandidates = [
    __DIR__ . '/admin/packages/autoload.php',
    __DIR__ . '/vendor/autoload.php'
];
foreach ($autoloadCandidates as $autopath) {
    if (file_exists($autopath)) {
        // Use include to avoid fatal if the autoload references missing packages
        @include_once $autopath;
        if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            $canSpreadsheet = true;
            break;
        }
    }
}
$filename = 'orders_' . date('Ymd_His');

// Helper: fully clean output buffers before sending file content
function __clean_all_output_buffers__() {
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    } else {
        @ob_end_clean();
    }
}
// Helper functions for friendly names and formatting
function fetch_product_name($db, $productId) {
    if (!$productId) return '';
    $q = mysqli_query($db, "SELECT product_name FROM products WHERE id=".(int)$productId);
    if ($q && mysqli_num_rows($q)>0) { $r = mysqli_fetch_assoc($q); return $r['product_name'] ?? ''; }
    return '';
}
function fetch_vendor_name($db, $vendorId) {
    if (!$vendorId) return '';
    $q = mysqli_query($db, "SELECT business_name, owner_name FROM vendor_registration WHERE id=".(int)$vendorId);
    if ($q && mysqli_num_rows($q)>0) { $r = mysqli_fetch_assoc($q); return $r['business_name'] ?? ($r['name'] ?? ''); }
    return '';
}
function format_ddmmyyyy($ts) {
    if (!$ts) return '';
    $t = strtotime($ts);
    if ($t === false) return $ts;
    return date('d/m/Y', $t);
}
function format_time($ts) {
    if (!$ts) return '';
    $t = strtotime($ts);
    if ($t === false) return '';
    return date('h:i A', $t);
}

if ($canSpreadsheet) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Orders');

    // Friendly header row (include product amount and product status)
    $headers = [
        'S.No',
        'Order ID',
        'Order Amount',
        'Coupon Savings',
        'Amount Paid',
        'Coins Earned',
        'Payment Method',
        'Payment Status',
        'Order Status',
        'Order Date',
        'Order Time',
        'Vendor Name',
        'Product',
        'Quantity',
        'Product Amount',
        'Product Status'
    ];
    // Map columns dynamically
    $colLetters = [];
    for ($i = 0; $i < count($headers); $i++) {
        // Convert index to Excel column letters
        $n = $i;
        $col = '';
        do {
            $col = chr($n % 26 + 65) . $col;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);
        $colLetters[] = $col;
    }
    foreach ($headers as $i => $h) { $sheet->setCellValue($colLetters[$i] . '1', $h); }
    // Group rows by order_id for cell merging
    $grouped = [];
    foreach ($rows as $r) {
        $grouped[$r['order_id']][] = $r;
    }
    $rowNum = 2; $serial = 1;
    foreach ($grouped as $orderId => $items) {
        $startRow = $rowNum;
        $vendorName = fetch_vendor_name($db_connection, $items[0]['vendor_id']);
        foreach ($items as $item) {
            $productName = fetch_product_name($db_connection, $item['product_id']);
            // Order-level columns (filled on first row; merged later)
            if ($rowNum === $startRow) {
                $sheet->setCellValue($colLetters[0] . $rowNum, $serial++);
                $sheet->setCellValue($colLetters[1] . $rowNum, $orderId);
                $sheet->setCellValue($colLetters[2] . $rowNum, $item['cart_amount']);
                $sheet->setCellValue($colLetters[3] . $rowNum, $item['coupan_saving']);
                $sheet->setCellValue($colLetters[4] . $rowNum, $item['total_amount']);
                $sheet->setCellValue($colLetters[5] . $rowNum, $item['coin_earn']);
                $sheet->setCellValue($colLetters[6] . $rowNum, strtoupper($item['payment_method']));
                $sheet->setCellValue($colLetters[7] . $rowNum, $item['payment_status']);
                $sheet->setCellValue($colLetters[8] . $rowNum, ucfirst($item['order_status']));
                $sheet->setCellValue($colLetters[9] . $rowNum, format_ddmmyyyy($item['created_date']));
                $sheet->setCellValue($colLetters[10] . $rowNum, format_time($item['created_date']));
                $sheet->setCellValue($colLetters[11] . $rowNum, $vendorName);
            }
            // Product-level columns per row
            $sheet->setCellValue($colLetters[12] . $rowNum, $productName);
            $sheet->setCellValue($colLetters[13] . $rowNum, $item['quantity']);
            $sheet->setCellValue($colLetters[14] . $rowNum, $item['product_amount']);
            $sheet->setCellValue($colLetters[15] . $rowNum, $item['product_status']);
            $rowNum++;
        }
        // Merge order-level columns across the range for this order
        $endRow = $rowNum - 1;
        if ($endRow > $startRow) {
            $orderColsToMerge = range(0, 11); // S.No through Vendor Name
            foreach ($orderColsToMerge as $ci) {
                $sheet->mergeCells($colLetters[$ci] . $startRow . ':' . $colLetters[$ci] . $endRow);
                // Vertically center merged cells
                $sheet->getStyle($colLetters[$ci] . $startRow . ':' . $colLetters[$ci] . $endRow)
                      ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }
        }
    }
    // Clean any previous output to prevent corrupting XLSX stream
    __clean_all_output_buffers__();
    // Strong, Excel-safe headers
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: public');
    header('Expires: 0');
    header('Content-Transfer-Encoding: binary');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
// Fallback: CSV
__clean_all_output_buffers__();
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: public');
header('Expires: 0');
$out = fopen('php://output', 'w');
// CSV headers
fputcsv($out, [
    'id','user_id','products','order_id','cart_amount','shipping_charge','coupan_saving','total_amount','coin_earn','payment_method','payment_status','address_id','order_status','order_track','reason','date','created_date','updated_date',
    'product_id','quantity','price','product_coin','selected_variants_json','variant_prices_json','vendor_id','product_status'
]);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'],
        $r['user_id'],
        $r['products'],
        $r['order_id'],
        $r['cart_amount'],
        $r['shipping_charge'],
        $r['coupan_saving'],
        $r['total_amount'],
        $r['coin_earn'],
        $r['payment_method'],
        $r['payment_status'],
        $r['address_id'],
        $r['order_status'],
        $r['order_track'],
        $r['reason'],
        $r['date'],
        $r['created_date'],
        $r['updated_date'],
        $r['product_id'],
        $r['quantity'],
        $r['price'],
        $r['product_coin'],
        $r['selected_variants_json'],
        $r['variant_prices_json'],
        $r['vendor_id'],
        $r['product_status']
    ]);
}
fclose($out);
exit;
