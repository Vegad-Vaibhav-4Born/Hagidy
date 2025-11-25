<?php

require_once __DIR__ . '/../pages/includes/init.php';
header('Content-Type: application/json');


if (!isset($_SESSION['vendor_reg_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$vendorId = (int)$_SESSION['vendor_reg_id'];

// Validate inputs
$categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$subCategoryId = isset($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : 0;

$isConfirm = isset($_POST['confirm']) && $_POST['confirm'] == '1';
$isDownloadErrors = isset($_GET['download_errors']) && $_GET['download_errors'] == '1';

if (!$isConfirm && !$isDownloadErrors) {
    if ($categoryId <= 0 || $subCategoryId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Category and Sub Category are required.']);
        exit;
    }

    if (!isset($_FILES['excel_file']) || !is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Excel file is required.']);
        exit;
    }
}
    
// Load PhpSpreadsheet
$autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
if ($autoloadPath && file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
    http_response_code(500);
    echo json_encode(['error' => 'Excel reader package not found (admin/packages).']);
    exit;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

// Compatibility helper for older PhpSpreadsheet builds
if (!function_exists('pss_get_cell_value_by_col_row')) {
    function pss_get_cell_value_by_col_row($sheet, $colIndex, $rowIndex) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        return $sheet->getCell($colLetter . $rowIndex)->getCalculatedValue();
    }
}

// Normalize header labels for robust matching (trim, collapse spaces, lowercase)
if (!function_exists('normalize_header_label')) {
    function normalize_header_label($label) {
        $label = (string)$label;
        $label = str_replace("\xC2\xA0", ' ', $label); // non-breaking space
        $label = trim(preg_replace('/\s+/', ' ', $label));
        return mb_strtolower($label);
    }
}

// Fetch mandatory attributes for subcategory
$mandatoryAttributeIds = [];
$subRes = mysqli_query($con, "SELECT mandatory_attributes FROM sub_category WHERE id = '" . mysqli_real_escape_string($con, (string)$subCategoryId) . "' LIMIT 1");
if ($subRes && mysqli_num_rows($subRes) === 1) {
    $row = mysqli_fetch_assoc($subRes);
    $csv = trim((string)($row['mandatory_attributes'] ?? ''));
    if ($csv !== '') {
        foreach (explode(',', $csv) as $id) {
            $id = (int)trim($id);
            if ($id > 0) { $mandatoryAttributeIds[] = $id; }
        }
    }
}

// Helper: find attribute id by name (case-insensitive)
function findAttributeByName($con, $name) {
    $name = trim((string)$name);
    if ($name === '') return null;
    $safe = mysqli_real_escape_string($con, $name);
    $rs = mysqli_query($con, "SELECT id, name, attribute_values, attribute_type FROM attributes WHERE LOWER(name) = LOWER('$safe') LIMIT 1");
    if ($rs && mysqli_num_rows($rs) === 1) {
        return mysqli_fetch_assoc($rs);
    }
    return null;
}

// Helper: generate text variant ID (PHP equivalent of JavaScript function)
function generateTextVariantId($attributeId) {
    // Use microtime for milliseconds precision (similar to JavaScript Date.now())
    $microtime = microtime(true);
    $timestamp = (int)($microtime * 1000); // Convert to milliseconds
    $random = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 9);
    return "text_{$attributeId}_{$timestamp}_{$random}";
}

// Helper: find attribute value id by display name
function findAttributeValueId($attributeRow, $valueName) {
    $valueName = trim((string)$valueName);
    if ($valueName === '') return null;
    $valuesJson = $attributeRow['attribute_values'] ?? '[]';
    $values = json_decode($valuesJson, true);
    if (!is_array($values)) return null;
    foreach ($values as $v) {
        if (isset($v['value_name']) && strcasecmp($v['value_name'], $valueName) === 0) {
            return $v['value_id'] ?? null;
        }
    }
    return null;
}

// Helper: get attribute names from IDs
function getAttributeNames($con, $attributeIds) {
    $names = [];
    if (empty($attributeIds)) return $names;
    $idsArray = array_filter(array_map('intval', $attributeIds));
    if (empty($idsArray)) return $names;
    $idsStr = implode(',', $idsArray);
    $rs = mysqli_query($con, "SELECT id, name FROM attributes WHERE id IN ($idsStr)");
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $names[(int)$row['id']] = $row['name'];
        }
    }
    return $names;
}

// Helper: get value from rowData case-insensitively
function getRowValueCaseInsensitive($rowData, $possibleKeys) {
    if (!is_array($possibleKeys)) {
        $possibleKeys = [$possibleKeys];
    }
    foreach ($possibleKeys as $key) {
        // Try exact match first
        if (isset($rowData[$key])) {
            return $rowData[$key];
        }
        // Try case-insensitive match
        foreach ($rowData as $rowKey => $value) {
            if (strcasecmp($rowKey, $key) === 0) {
                return $value;
            }
        }
    }
    return null;
}

// Helper: get value name from value ID
function getValueNameFromId($con, $attributeId, $valueId) {
    // Check if it's a text variant (starts with "text_")
    if (strpos($valueId, 'text_') === 0) {
        // For text variants, we need to get the name from the variant data
        // This will be handled separately as text variants store name in the variant entry
        return null; // Will be handled by caller
    }
    
    // For regular attributes, look up in attribute_values JSON
    $safeAttrId = (int)$attributeId;
    $rs = mysqli_query($con, "SELECT attribute_values FROM attributes WHERE id = $safeAttrId LIMIT 1");
    if ($rs && mysqli_num_rows($rs) === 1) {
        $row = mysqli_fetch_assoc($rs);
        $valuesJson = $row['attribute_values'] ?? '[]';
        $values = json_decode($valuesJson, true);
        if (is_array($values)) {
            foreach ($values as $v) {
                if (isset($v['value_id']) && $v['value_id'] === $valueId) {
                    return $v['value_name'] ?? $valueId;
                }
            }
        }
    }
    return $valueId; // Fallback to ID if not found
}

// If confirm mode, insert from session
if ($isConfirm) {
    if (!isset($_SESSION['bulk_insert_pending']) || !is_array($_SESSION['bulk_insert_pending'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No pending bulk insert data found.']);
        exit;
    }

    $insertable = $_SESSION['bulk_insert_pending'];
    unset($_SESSION['bulk_insert_pending']);
    unset($_SESSION['bulk_insert_category']);
    unset($_SESSION['bulk_insert_subcategory']);

    $inserted = 0;
    $failed = 0;
    $productErrors = [];

    foreach ($insertable as $gKey => $cols) {
        $columnsSql = [];
        $valuesSql = [];
        foreach ($cols as $k => $v) {
            $columnsSql[] = "`$k`";
            if ($v === null) { 
                $valuesSql[] = 'NULL'; 
            } else { 
                $valuesSql[] = "'" . mysqli_real_escape_string($con, (string)$v) . "'"; 
            }
        }
        $sql = "INSERT INTO products (" . implode(',', $columnsSql) . ") VALUES (" . implode(',', $valuesSql) . ")";
        $ok = mysqli_query($con, $sql);
        if ($ok) { 
            $inserted++; 
        } else {
            $productErrors[$gKey][] = 'DB error: ' . mysqli_error($con);
            $failed++;
        }
    }

    echo json_encode([
        'inserted' => $inserted,
        'failed' => $failed,
        'product_errors' => $productErrors,
    ]);
    exit;
}

// Download errors as Excel
if ($isDownloadErrors) {
    if (!isset($_SESSION['bulk_insert_errors'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No error data found.']);
        exit;
    }

    $errors = $_SESSION['bulk_insert_errors'];
    unset($_SESSION['bulk_insert_errors']);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Import Errors');

    // Headers
    $sheet->setCellValue('A1', 'Row Number');
    $sheet->setCellValue('B1', 'Error Type');
    $sheet->setCellValue('C1', 'Error Message');
    $sheet->setCellValue('D1', 'Product Name');
    $sheet->getStyle('A1:D1')->getFont()->setBold(true);

    $rowNum = 2;

    // Add row errors
    foreach ($errors['row_errors'] as $rowNum => $errorMessages) {
        foreach ((array)$errorMessages as $error) {
            $sheet->setCellValue('A' . $rowNum, $rowNum);
            $sheet->setCellValue('B' . $rowNum, 'Row Error');
            $sheet->setCellValue('C' . $rowNum, $error);
            $sheet->setCellValue('D' . $rowNum, 'N/A');
            $rowNum++;
        }
    }

    // Add product errors
    foreach ($errors['product_errors'] as $productName => $errorMessages) {
        foreach ((array)$errorMessages as $error) {
            $sheet->setCellValue('A' . $rowNum, 'N/A');
            $sheet->setCellValue('B' . $rowNum, 'Product Error');
            $sheet->setCellValue('C' . $rowNum, $error);
            $sheet->setCellValue('D' . $rowNum, $productName);
            $rowNum++;
        }
    }

    foreach (range('A','D') as $col) { 
        $sheet->getColumnDimension($col)->setAutoSize(true); 
    }

    // Clean all buffers to prevent XLSX corruption
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
    } else {
        @ob_end_clean();
    }

    $fileName = 'import_errors_' . date('Ymd_His') . '.xlsx';
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

// Preview mode: Read and process Excel
try {
    $reader = IOFactory::createReaderForFile($_FILES['excel_file']['tmp_name']);
    $spreadsheet = $reader->load($_FILES['excel_file']['tmp_name']);

    // Prefer the second worksheet (index 1) if available; otherwise fallback to header-detection
    $sheet = null;
    $sheetTitle = '';
    $highestRow = 0;
    $highestColIndex = 0;

    if ($spreadsheet->getSheetCount() >= 2) {
        $candidate = $spreadsheet->getSheet(1); // second tab
        $sheet = $candidate;
        $sheetTitle = $candidate->getTitle();
        $highestRow = $candidate->getHighestRow();
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($candidate->getHighestColumn());
    } else {
        // Fallback: auto-detect a sheet containing a "Product Name" header
        for ($si = 0; $si < $spreadsheet->getSheetCount(); $si++) {
            $candidate = $spreadsheet->getSheet($si);
            $candHighestRow = $candidate->getHighestRow();
            $candHighestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($candidate->getHighestColumn());
            $foundHeader = false;
            for ($r = 3; $r <= min($candHighestRow, 30); $r++) {
                if ($r == 4) continue; // Skip row 4
                for ($c = 3; $c <= $candHighestColIndex; $c++) {
                    $val = trim((string)pss_get_cell_value_by_col_row($candidate, $c, $r));
                    $norm = normalize_header_label($val);
                    if ($norm === 'product name' || $norm === 'product') { $foundHeader = true; break 2; }
                }
            }
            if ($foundHeader) {
                $sheet = $candidate;
                $sheetTitle = $candidate->getTitle();
                $highestRow = $candHighestRow;
                $highestColIndex = $candHighestColIndex;
                break;
            }
        }
        if ($sheet === null) {
            throw new \RuntimeException('No sheet with a "Product Name" header found starting from cell C3.');
        }
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Failed to read Excel: ' . $e->getMessage()]);
    exit;
}

// Detect header row: choose the first row containing "Product Name" starting from row 3, column C
$headerRowIndex = null;
for ($r = 3; $r <= min($highestRow, 20); $r++) {
    if ($r == 4) continue; // Skip row 4
    for ($c = 3; $c <= $highestColIndex; $c++) {
        $val = trim((string)pss_get_cell_value_by_col_row($sheet, $c, $r));
        if (strcasecmp($val, 'Product Name') === 0) { $headerRowIndex = $r; break 2; }
    }
}
if ($headerRowIndex === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Could not detect header row on sheet: ' . $sheetTitle . '. Ensure a "Product Name" column exists starting from cell C3.']);
    exit;
}

// Build headers map: column index => header label, starting from column C (index 3)
$headers = [];
$headersNorm = [];
for ($c = 3; $c <= $highestColIndex; $c++) {
    $label = trim((string)pss_get_cell_value_by_col_row($sheet, $c, $headerRowIndex));
    $headers[$c] = $label;
    $headersNorm[$c] = normalize_header_label($label);
}

// Known product fields (header => db key)
$fieldMapNorm = [
    'product name' => 'product_name',
    'product' => 'product_name',
    'mrp' => 'mrp',
    'selling price' => 'selling_price',
    'description' => 'description',
    'product description' => 'description',
    'coin' => 'coin',
    'platform fee' => 'platform_fee',
    'brand name' => 'brand',
    'brand' => 'brand',
    'gst' => 'gst',
    'gst %' => 'gst',
    'hsn id' => 'hsn_id',
    'manufacture details' => 'manufacture_details',
    'manufacturer details' => 'manufacture_details',
    'packaging details' => 'packaging_details',
    'packer details' => 'packaging_details',
    'sku id' => 'sku_id',
    'group id' => 'group_id',
    'style id' => 'style_id',
    'product id / style id' => 'style_id',
    'inventory' => 'Inventory',
    'product brand' => 'product_brand',
];

// Specification columns = anything not in fieldMap and not blank
$specHeaders = [];
$ignoreHeadersNorm = [
    'error status' => true,
    'error message' => true,
    'instructions' => true,
    'validation sheet' => true,
];
foreach ($headers as $colIdx => $label) {
    $norm = $headersNorm[$colIdx] ?? '';
    if ($norm === '') continue;
    if (!isset($fieldMapNorm[$norm])) {
        if (isset($ignoreHeadersNorm[$norm])) continue;
        if (stripos($label, 'Compulsory') !== false || strcasecmp($label, 'Specification') === 0) continue;
        $specHeaders[$colIdx] = $label; // keep original label for attribute lookup
    }
}

// Aggregate rows by (product_name, group_id)
$groups = [];
$rowErrors = [];
$failedRows = 0;

$consecutiveEmpty = 0;
for ($r = $headerRowIndex + 1; $r <= $highestRow; $r++) {
    if ($r == 4) continue; // Skip row 4
    $rowData = [];
    $isEmpty = true;
    foreach ($headers as $c => $label) {
        $val = trim((string)pss_get_cell_value_by_col_row($sheet, $c, $r));
        if ($val !== '') { $isEmpty = false; }
        $rowData[$label] = $val;
    }
    if ($isEmpty) {
        $consecutiveEmpty++;
        if ($consecutiveEmpty >= 50) { break; }
        continue;
    }
    $consecutiveEmpty = 0;

    // Support both "Product Name" and "Product"
    $productName = $rowData['Product Name'] ?? ($rowData['Product'] ?? '');
    if ($productName === '') {
        $rowErrors[$r] = ['Product Name is required'];
        $failedRows++;
        continue;
    }

    $groupId = getRowValueCaseInsensitive($rowData, ['Group ID', 'group id', 'Group id']) ?? '';
    $key = strtolower($productName) . '|' . strtolower($groupId);

    if (!isset($groups[$key])) {
        // Validate MRP and Selling Price at base level
        $mrpBase = trim((string)($rowData['MRP'] ?? ''));
        $spBase = trim((string)($rowData['Selling Price'] ?? ''));
        
        // Validate base product pricing
        if ($mrpBase !== '' && $spBase !== '') {
            $mrpNum = (float)$mrpBase;
            $spNum = (float)$spBase;
            if ($spNum > $mrpNum) {
                $rowErrors[$r][] = "Selling Price ($spBase) cannot be greater than MRP ($mrpBase)";
                $rowHasError = true;
            }
        }
        
        $base = [
            'product_name' => $productName,
            'mrp' => $mrpBase,
            'selling_price' => $spBase,
            'description' => ($rowData['Description'] ?? ($rowData['Product Description'] ?? '')),
            'coin' => $rowData['Coin'] ?? '0',
            'platform_fee' => $rowData['Platform Fee'] ?? '0',
            'brand' => $rowData['Brand Name'] ?? ($rowData['Brand'] ?? null),
            'gst' => ($rowData['GST'] ?? ($rowData['GST %'] ?? null)),
            'hsn_id' => $rowData['HSN ID'] ?? null,
            'manufacture_details' => ($rowData['Manufacture Details'] ?? ($rowData['Manufacturer Details'] ?? '')),
            'packaging_details' => ($rowData['Packaging Details'] ?? ($rowData['Packer Details'] ?? '')),
            'sku_id' => $rowData['SKU ID'] ?? '',
            'group_id' => $groupId,
            'style_id' => getRowValueCaseInsensitive($rowData, ['Product ID / Style ID', 'Style ID', 'style id', 'Style id', 'Product ID / Style id', 'product id / style id']) ?? null,
            'Inventory' => $rowData['Inventory'] ?? '0',
            'product_brand' => $rowData['Product Brand'] ?? null,
            'variants' => [],
            'spec_seen_attributes' => [],
        ];
        $groups[$key] = $base;
    }

    // Build variant spec from spec columns
    $variantSpecs = [];
    $seenAttributes = [];
    $rowHasError = false;
    $hasMultipleAttributeColumns = false;
    $attributeColumnCount = 0;
    $totalCommaSeparatedValues = 0;
    
    // Count how many attribute columns have values in this row
    // Also check if values are comma-separated (single row mode) vs single values (multiple rows mode)
    foreach ($specHeaders as $c => $displayName) {
        $rawVal = pss_get_cell_value_by_col_row($sheet, $c, $r);
        $value = trim((string)$rawVal);
        if ($value !== '') {
            $attributeColumnCount++;
            // Check if this value contains commas (comma-separated mode)
            if (strpos($value, ',') !== false) {
                $totalCommaSeparatedValues++;
            }
        }
    }
    
    // Detection logic:
    // - If multiple attribute columns with single values (no commas): "multiple rows" mode → store MRP/price in JSON
    // - If single attribute column with comma-separated values: "single row with comma-separated" mode → don't store MRP/price
    // - If multiple columns but most have comma-separated values: treat as "single row" mode
    // - If multiple columns with single values: treat as "multiple rows" mode
    $hasMultipleAttributeColumns = ($attributeColumnCount > 1 && $totalCommaSeparatedValues < $attributeColumnCount);
    
    foreach ($specHeaders as $c => $displayName) {
		$rawVal = pss_get_cell_value_by_col_row($sheet, $c, $r);
		$value = trim((string)$rawVal);
		if ($value === '') { continue; }

		$attributeRow = findAttributeByName($con, $displayName);
		if (!$attributeRow) {
			$rowErrors[$r][] = "Unknown attribute: $displayName";
			$rowHasError = true;
			continue;
		}

		$attributeId = (int)$attributeRow['id'];
		$attributeType = strtolower(trim($attributeRow['attribute_type'] ?? 'multi'));
		
		// Handle different attribute types
		if ($attributeType === 'text') {
			// Text type: treat entire value (including commas) as single text variant, max 30 characters
			$textVal = trim($value);
			
			if ($textVal !== '') {
				// Limit to 30 characters
				if (mb_strlen($textVal) > 30) {
					$textVal = mb_substr($textVal, 0, 30);
					$rowErrors[$r][] = "Attribute '$displayName' text value exceeds 30 characters and has been truncated.";
				}
				
				// Generate text variant ID
				$textVariantId = generateTextVariantId($attributeId);
				$variantSpecs[] = [
					'attribute_id' => $attributeId,
					'id' => $textVariantId,
					'name' => $textVal,
					'is_text_variant' => true,
				];
				$seenAttributes[$attributeId] = true;
			}
		} elseif ($attributeType === 'single') {
			// Single select: take only first value (ignore rest if comma-separated)
			$parts = array_map('trim', explode(',', $value));
			$nonEmptyParts = array_filter($parts, function($p) { return $p !== ''; });
			$valueCount = count($nonEmptyParts);
			
			// Check if multiple values are provided - show error but still process
			if ($valueCount > 1) {
				$allValues = implode(', ', $nonEmptyParts);
				$rowErrors[$r][] = "Attribute '$displayName' is Single Select - only one value allowed, but multiple values found: $allValues. Only the first value will be used.";
				// Don't set $rowHasError = true, so the row still gets processed
			}
			
			// Process first value (even if multiple provided)
			$firstVal = '';
			foreach ($parts as $singleVal) {
				if ($singleVal !== '') {
					$firstVal = $singleVal;
					break;
				}
			}
			if ($firstVal !== '') {
				$valueId = findAttributeValueId($attributeRow, $firstVal);
				if (!$valueId) {
					$rowErrors[$r][] = "Unknown value '" . $firstVal . "' for attribute $displayName";
					$rowHasError = true;
				} else {
					$variantSpecs[] = [
						'attribute_id' => $attributeId,
						'id' => $valueId,
					];
					$seenAttributes[$attributeId] = true;
				}
			}
		} else {
			// Multi select (default): support multiple values separated by commas
			$parts = array_map('trim', explode(',', $value));
			$addedAny = false;
			foreach ($parts as $singleVal) {
				if ($singleVal === '') { continue; }
				$valueId = findAttributeValueId($attributeRow, $singleVal);
				if (!$valueId) {
					$rowErrors[$r][] = "Unknown value '" . $singleVal . "' for attribute $displayName";
					$rowHasError = true;
					continue;
				}
				$variantSpecs[] = [
					'attribute_id' => $attributeId,
					'id' => $valueId,
				];
				$addedAny = true;
			}
			if ($addedAny) {
				$seenAttributes[$attributeId] = true;
			}
		}
    }

    if ($rowHasError) {
        $failedRows++;
        continue; // Skip this row
    }

	// Build variant payload
	// If multiple attribute columns: store MRP/price in JSON (multiple rows mode)
	// If single row with comma-separated: don't store MRP/price (use base product pricing)
	$variantPayload = [
		'values' => $variantSpecs,
	];
	
	// Only store MRP/price in variant if it's multiple rows mode (multiple attribute columns)
	if ($hasMultipleAttributeColumns) {
		$mrpVal = trim((string)($rowData['MRP'] ?? ''));
		$spVal = trim((string)($rowData['Selling Price'] ?? ''));
		
		// Validate variant-level pricing
		if ($mrpVal !== '' && $spVal !== '') {
			$mrpNum = (float)$mrpVal;
			$spNum = (float)$spVal;
			if ($spNum > $mrpNum) {
				$rowErrors[$r][] = "Selling Price ($spVal) cannot be greater than MRP ($mrpVal) for this variant";
				$rowHasError = true;
			}
		}
		
		if ($mrpVal !== '') { $variantPayload['mrp'] = $mrpVal; }
		if ($spVal !== '') { $variantPayload['selling_price'] = $spVal; }
	}

	$groups[$key]['variants'][] = $variantPayload;
    $groups[$key]['spec_seen_attributes'] = $groups[$key]['spec_seen_attributes'] + $seenAttributes;
}

// Process groups for validation
$insertable = []; // gKey => cols
$productErrors = [];
$totalAttempted = count($groups);

foreach ($groups as $gKey => $g) {
    // Skip empty groups
    if (empty($g['variants'])) {
        $productErrors[$g['product_name'] . ($g['group_id'] ? ' (' . $g['group_id'] . ')' : '')][] = 'No valid variants after skipping erroneous rows';
        continue;
    }

    // Check for duplicate product name
    $safeName = mysqli_real_escape_string($con, $g['product_name']);
    $existsQuery = "SELECT id FROM products WHERE vendor_id = $vendorId AND product_name = '$safeName' LIMIT 1";
    $existsRs = mysqli_query($con, $existsQuery);
    if ($existsRs && mysqli_num_rows($existsRs) > 0) {
        $productErrors[$g['product_name'] . ($g['group_id'] ? ' (' . $g['group_id'] . ')' : '')][] = 'Product with same name already exists';
        continue;
    }

    // Build specifications JSON
    $attrIdToVariants = [];
    $attrIdToSeenValues = []; // Track seen values to prevent duplicates
    $textDuplicateErrors = []; // Track duplicate text values for error messages
    
	foreach ($g['variants'] as $variant) {
		$mrpV = $variant['mrp'] ?? null;
		$spV = $variant['selling_price'] ?? null;
		foreach ($variant['values'] as $vv) {
            $aid = (int)$vv['attribute_id'];
            if (!isset($attrIdToVariants[$aid])) { 
                $attrIdToVariants[$aid] = [];
                $attrIdToSeenValues[$aid] = [];
            }
            
            // Check for duplicates
            $valueId = $vv['id'];
            $isTextVariant = isset($vv['is_text_variant']) && $vv['is_text_variant'] === true;
            
            // For text variants, check by name (case-insensitive, normalized)
            // For regular variants, check by ID
            if ($isTextVariant) {
                $textName = trim($vv['name'] ?? '');
                $duplicateKey = 'text_' . strtolower($textName);
            } else {
                $duplicateKey = $valueId;
            }
            
            // Skip if this value already exists for this attribute
            if (isset($attrIdToSeenValues[$aid][$duplicateKey])) {
                // For text variants, track duplicate for error message
                if ($isTextVariant) {
                    $textValueName = trim($vv['name'] ?? $valueId);
                    if ($textValueName !== '') {
                        if (!isset($textDuplicateErrors[$aid])) {
                            $textDuplicateErrors[$aid] = [];
                        }
                        // Only add if not already in the list
                        if (!in_array($textValueName, $textDuplicateErrors[$aid])) {
                            $textDuplicateErrors[$aid][] = $textValueName;
                        }
                    }
                }
                continue; // Skip duplicate
            }
            
            // Mark as seen
            $attrIdToSeenValues[$aid][$duplicateKey] = true;
            
			$entry = ['id' => $valueId];
			
			// For text variants, include name and is_text_variant flag
			if ($isTextVariant) {
				$entry['name'] = $vv['name'] ?? $vv['id'];
				$entry['is_text_variant'] = true;
			}
			
			// Only add MRP/price if they exist (multiple rows mode)
			if ($mrpV !== null) { $entry['mrp'] = $mrpV; }
			if ($spV !== null) { $entry['selling_price'] = $spV; }
            $attrIdToVariants[$aid][] = $entry;
        }
    }
    
    // Apply attribute type validation when combining variants from multiple rows
    // Get attribute types for all attributes in this product (needed before processing)
    $attributeTypes = [];
    $attributeNames = [];
    if (!empty($attrIdToVariants)) {
        $attrIds = array_keys($attrIdToVariants);
        $idsStr = implode(',', array_map('intval', $attrIds));
        $attrTypeRs = mysqli_query($con, "SELECT id, name, attribute_type FROM attributes WHERE id IN ($idsStr)");
        if ($attrTypeRs) {
            while ($attrRow = mysqli_fetch_assoc($attrTypeRs)) {
                $attributeTypes[(int)$attrRow['id']] = strtolower(trim($attrRow['attribute_type'] ?? 'multi'));
                $attributeNames[(int)$attrRow['id']] = $attrRow['name'];
            }
        }
    }
    
    // Add error messages for duplicate text values
    if (!empty($textDuplicateErrors)) {
        // Get attribute names for display
        $attrIdsForErrors = array_keys($textDuplicateErrors);
        $attrNamesForErrors = getAttributeNames($con, $attrIdsForErrors);
        
        foreach ($textDuplicateErrors as $aid => $duplicateValues) {
            $attrName = $attrNamesForErrors[$aid] ?? ($attributeNames[$aid] ?? "Attribute ID:$aid");
            $valuesStr = implode(', ', $duplicateValues);
            $productErrors[$g['product_name'] . ($g['group_id'] ? ' (' . $g['group_id'] . ')' : '')][] = 
                "Attribute '$attrName' is Text Type - duplicate values found across rows: $valuesStr. Only the first value will be used.";
        }
    }
    
    // Validate and fix single-select and text type attributes that have multiple values across rows
    // Both single-select and text type: only first value allowed across rows
    foreach ($attrIdToVariants as $aid => $variantsArr) {
        $attrType = $attributeTypes[$aid] ?? 'multi';
        $attrName = $attributeNames[$aid] ?? "Attribute ID:$aid";
        
        // Restrict both single-select and text type to one value across rows
        if (($attrType === 'single' || $attrType === 'text') && count($variantsArr) > 1) {
            // Single select or text type with multiple values from different rows
            // Keep only the first value, show error
            $firstValue = $variantsArr[0];
            
            // For text type, ensure name is limited to 30 characters
            if ($attrType === 'text' && isset($firstValue['name'])) {
                if (mb_strlen($firstValue['name']) > 30) {
                    $firstValue['name'] = mb_substr($firstValue['name'], 0, 30);
                }
            }
            
            $attrIdToVariants[$aid] = [$firstValue]; // Keep only first
            
            // Get value names for error message
            $valueNames = [];
            foreach ($variantsArr as $variant) {
                // Check if it's a text variant
                if (isset($variant['is_text_variant']) && $variant['is_text_variant'] === true) {
                    $valueNames[] = $variant['name'] ?? $variant['id'];
                } else {
                    // Look up value name from attribute_values
                    $valueId = $variant['id'];
                    $valueName = getValueNameFromId($con, $aid, $valueId);
                    $valueNames[] = $valueName;
                }
            }
            
            // Add error message with value names
            $allValuesStr = implode(', ', array_slice($valueNames, 0, 3));
            if (count($valueNames) > 3) {
                $allValuesStr .= '... (and ' . (count($valueNames) - 3) . ' more)';
            }
            
            $typeLabel = $attrType === 'text' ? 'Text Type' : 'Single Select';
            $productErrors[$g['product_name'] . ($g['group_id'] ? ' (' . $g['group_id'] . ')' : '')][] = 
                "Attribute '$attrName' is $typeLabel - multiple values found across different rows: $allValuesStr. Only the first value will be used.";
        } elseif ($attrType === 'text') {
            // For text type with single value: ensure text variant name is limited to 30 characters
            foreach ($variantsArr as &$variant) {
                if (isset($variant['name']) && mb_strlen($variant['name']) > 30) {
                    $variant['name'] = mb_substr($variant['name'], 0, 30);
                }
            }
            unset($variant); // Break reference
        }
    }
    
    $specifications = [];
    foreach ($attrIdToVariants as $aid => $variantsArr) {
        $specifications[] = [
            'attribute_id' => $aid,
            'variants' => $variantsArr,
        ];
    }
    $specificationsJson = json_encode($specifications, JSON_UNESCAPED_UNICODE);

    // Validate MRP and Selling Price at product level
    $mrpProduct = trim((string)($g['mrp'] ?? ''));
    $spProduct = trim((string)($g['selling_price'] ?? ''));
    $hasPricingError = false;
    $pricingErrors = [];
    
    // Check base product pricing
    if ($mrpProduct !== '' && $spProduct !== '') {
        $mrpNum = (float)$mrpProduct;
        $spNum = (float)$spProduct;
        if ($spNum > $mrpNum) {
            $pricingErrors[] = "Selling Price ($spProduct) cannot be greater than MRP ($mrpProduct)";
            $hasPricingError = true;
        }
    }
    
    // Validate variant-level MRP and Selling Price in specifications
    // Collect all variant errors first to avoid duplicates
    $variantErrors = [];
    foreach ($specifications as $spec) {
        foreach ($spec['variants'] as $variant) {
            if (isset($variant['mrp']) && isset($variant['selling_price'])) {
                $mrpVariant = trim((string)$variant['mrp']);
                $spVariant = trim((string)$variant['selling_price']);
                if ($mrpVariant !== '' && $spVariant !== '') {
                    $mrpNum = (float)$mrpVariant;
                    $spNum = (float)$spVariant;
                    if ($spNum > $mrpNum) {
                        // Create unique key to avoid duplicate errors for same variant pricing
                        $errorKey = "variant_{$mrpVariant}_{$spVariant}";
                        if (!isset($variantErrors[$errorKey])) {
                            $variantErrors[$errorKey] = "Variant Selling Price ($spVariant) cannot be greater than Variant MRP ($mrpVariant)";
                            $hasPricingError = true;
                        }
                    }
                }
            }
        }
    }
    
    // Add all pricing errors at once (base + variants)
    // Prevent duplicate error messages
    if ($hasPricingError) {
        $productKey = $g['product_name'] . ($g['group_id'] ? ' (' . $g['group_id'] . ')' : '');
        
        // Initialize array if not exists
        if (!isset($productErrors[$productKey])) {
            $productErrors[$productKey] = [];
        }
        
        // Track existing errors to avoid duplicates
        $existingErrors = array_flip($productErrors[$productKey]);
        
        foreach ($pricingErrors as $error) {
            if (!isset($existingErrors[$error])) {
                $productErrors[$productKey][] = $error;
                $existingErrors[$error] = true;
            }
        }
        foreach ($variantErrors as $error) {
            if (!isset($existingErrors[$error])) {
                $productErrors[$productKey][] = $error;
                $existingErrors[$error] = true;
            }
        }
        continue; // Skip product if pricing validation failed
    }

    // Validate mandatory attributes
    $missingMandatory = [];
    foreach ($mandatoryAttributeIds as $attrId) {
        $found = false;
        foreach ($specifications as $s) { if ((int)$s['attribute_id'] === (int)$attrId) { $found = true; break; } }
        if (!$found) { $missingMandatory[] = $attrId; }
    }
    if (!empty($missingMandatory)) {
        // Get attribute names for display
        $attrNames = getAttributeNames($con, $missingMandatory);
        $missingNames = [];
        foreach ($missingMandatory as $aid) {
            $missingNames[] = $attrNames[$aid] ?? "ID:$aid";
        }
        $productErrors[$g['product_name'] . ($g['group_id'] ? ' (' . $g['group_id'] . ')' : '')][] = 'Missing mandatory attributes: ' . implode(', ', $missingNames);
        continue;
    }

    $discount = 0;
    if ($g['mrp'] !== '' && $g['selling_price'] !== '') {
        $mrp = (float)$g['mrp'];
        $selling = (float)$g['selling_price'];
        if ($mrp > $selling && $mrp > 0) {
            $discount = round((($mrp - $selling) / $mrp) * 100);
        }
    }
    // Prepare cols for insert
    $cols = [
        'vendor_id' => $vendorId,
        'product_name' => $g['product_name'],
        'category_id' => $categoryId,
        'sub_category_id' => $subCategoryId,
        'mrp' => $g['mrp'],
        'selling_price' => $g['selling_price'],
        'discount' => $discount,
        'description' => $g['description'],
        'coin' => $g['coin'],
        'platform_fee' => $g['platform_fee'],
        'brand' => $g['brand'],
        'gst' => $g['gst'],
        'hsn_id' => $g['hsn_id'],
        'manufacture_details' => $g['manufacture_details'],
        'packaging_details' => $g['packaging_details'],
        'sku_id' => $g['sku_id'],
        'group_id' => $g['group_id'],
        'style_id' => $g['style_id'],
        'Inventory' => $g['Inventory'],
        'product_brand' => $g['product_brand'],
        'images' => json_encode([]),
        'video' => null,
        'specifications' => $specificationsJson,
        'status' => 'pending',
        'seen' => 0,
        'created_date' => date('Y-m-d h:i:s'),
    ];

    $insertable[$gKey] = $cols;
}

// Store insertable in session for confirm
$_SESSION['bulk_insert_pending'] = $insertable;
$_SESSION['bulk_insert_category'] = $categoryId;
$_SESSION['bulk_insert_subcategory'] = $subCategoryId;
$_SESSION['bulk_insert_errors'] = [
    'row_errors' => $rowErrors,
    'product_errors' => $productErrors,
    'failed_rows' => $failedRows
];

// Get attribute names for all attribute IDs in errors for better display
$allAttrIdsInErrors = [];
foreach ($productErrors as $errors) {
    foreach ((array)$errors as $error) {
        // Extract attribute IDs from error messages if any
        if (preg_match_all('/ID:(\d+)/', $error, $matches)) {
            $allAttrIdsInErrors = array_merge($allAttrIdsInErrors, $matches[1]);
        }
    }
}
$attrNamesMap = getAttributeNames($con, array_unique($allAttrIdsInErrors));

// Replace attribute IDs with names in product errors
$productErrorsWithNames = [];
foreach ($productErrors as $productKey => $errors) {
    $productErrorsWithNames[$productKey] = [];
    foreach ((array)$errors as $error) {
        // Replace "ID:123" with actual attribute name
        $error = preg_replace_callback('/ID:(\d+)/', function($matches) use ($attrNamesMap) {
            $aid = (int)$matches[1];
            return $attrNamesMap[$aid] ?? "ID:$aid";
        }, $error);
        $productErrorsWithNames[$productKey][] = $error;
    }
}

// Return preview report
$toInsert = count($insertable);
$failed = $totalAttempted - $toInsert;

echo json_encode([
    'to_insert' => $toInsert,
    'failed' => $failed,
    'total_attempted' => $totalAttempted,
    'failed_rows' => $failedRows,
    'row_errors' => $rowErrors,
    'product_errors' => $productErrorsWithNames,
]);
?>