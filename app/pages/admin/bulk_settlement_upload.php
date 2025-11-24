<?php
// Clean all output buffers first
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
}

// Suppress errors to prevent output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to capture any output from init.php
@ob_start();

// Include init.php and capture any output
include __DIR__ . '/../includes/init.php';

// Discard any output from init.php
@ob_end_clean();

if (!isset($_SESSION['superadmin_id'])) {
    // Clean buffers before redirect
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access', 'data' => ['errors' => ['Session expired. Please login again.']]]);
    exit;
}


// Load PhpSpreadsheet
$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    // Clean buffers before error
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'PhpSpreadsheet library not found',
        'data' => [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => ['PhpSpreadsheet library not installed. Please install via Composer.']
        ]
    ]);
    exit;
}

if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
    // Clean buffers before error
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'PhpSpreadsheet class not found',
        'data' => [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => ['PhpSpreadsheet class not available. Please install PhpSpreadsheet via Composer.']
        ]
    ]);
    exit;
}

// Handle template download
if (isset($_GET['download_template']) && $_GET['download_template'] == '1') {
    // Clear any output buffer
    ob_clean();
    // Create an Excel template using PhpSpreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $worksheet = $spreadsheet->getActiveSheet();

    // Set headers
    $worksheet->setCellValue('A1', 'settlement_id');
    $worksheet->setCellValue('B1', 'transaction_id');

    // Add sample data
    $worksheet->setCellValue('A2', 'SETTLE001');
    $worksheet->setCellValue('B2', 'TXN123456');
    $worksheet->setCellValue('A3', 'SETTLE002');
    $worksheet->setCellValue('B3', 'TXN123457');

    // Style the header row
    $worksheet->getStyle('A1:B1')->getFont()->setBold(true);
    $worksheet->getStyle('A1:B1')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFE0E0E0');

    // Auto-size columns
    $worksheet->getColumnDimension('A')->setAutoSize(true);
    $worksheet->getColumnDimension('B')->setAutoSize(true);

    $filename = 'bulk_settlement_template.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}

// Handle file upload and processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    // Clean all output buffers before sending JSON
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
    @ob_start();
    
    // Set JSON header
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-cache, must-revalidate');

    try {
        $file = $_FILES['excel_file'];

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }

        $allowedTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'];
        $fileType = mime_content_type($file['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Invalid file type. Please upload Excel or CSV file.');
        }

        $rows = [];

        // Handle CSV files
        if ($fileType === 'text/csv') {
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle !== false) {
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
        } else {
            // For Excel files, use PhpSpreadsheet
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file['tmp_name']);
                $reader->setReadDataOnly(true); // Only read data, ignore formatting
                $spreadsheet = $reader->load($file['tmp_name']);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                
                // Filter out completely empty rows
                $rows = array_filter($rows, function($row) {
                    if (!is_array($row)) return false;
                    foreach ($row as $cell) {
                        if (!empty(trim((string)$cell))) {
                            return true;
                        }
                    }
                    return false;
                });
                $rows = array_values($rows); // Re-index array
            } catch (Exception $e) {
                throw new Exception('Error reading Excel file: ' . $e->getMessage() . '. Please ensure the file is a valid Excel file (.xlsx or .xls).');
            }
        }

        if (count($rows) < 2) {
            throw new Exception('File must contain at least a header row and one data row');
        }

        // Get header row and find column indices
        $headerRow = $rows[0];
        $settlementIdIndex = -1;
        $transactionIdIndex = -1;

        // Normalize header row - handle null values and convert to strings
        foreach ($headerRow as $index => $header) {
            $header = is_null($header) ? '' : trim((string)$header);
            $headerLower = strtolower($header);
            
            // Check for settlement_id column (case-insensitive, handles variations)
            if (($headerLower === 'settlement_id' || $headerLower === 'settlement id' || 
                 (strpos($headerLower, 'settlement') !== false && strpos($headerLower, 'id') !== false)) && 
                $settlementIdIndex === -1) {
                $settlementIdIndex = $index;
            }
            
            // Check for transaction_id column (case-insensitive, handles variations)
            if (($headerLower === 'transaction_id' || $headerLower === 'transaction id' || 
                 (strpos($headerLower, 'transaction') !== false && strpos($headerLower, 'id') !== false)) && 
                $transactionIdIndex === -1) {
                $transactionIdIndex = $index;
            }
        }

        if ($settlementIdIndex === -1 || $transactionIdIndex === -1) {
            $missingColumns = [];
            if ($settlementIdIndex === -1) $missingColumns[] = 'Settlement ID';
            if ($transactionIdIndex === -1) $missingColumns[] = 'Transaction ID';
            throw new Exception('File must contain columns for: ' . implode(' and ', $missingColumns) . '. Found headers: ' . implode(', ', array_filter($headerRow)));
        }

        $processed = 0;
        $successful = 0;
        $failed = 0;
        $errors = [];
        $successfulSettlements = [];
        $failedSettlements = [];

        // Process each row (skip header)
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            // Skip completely empty rows
            $isEmptyRow = true;
            foreach ($row as $cell) {
                if (!empty(trim((string)$cell))) {
                    $isEmptyRow = false;
                    break;
                }
            }
            if ($isEmptyRow) {
                continue; // Skip empty rows
            }
            
            $processed++;

            // Get settlement and transaction IDs, handling null values
            $settlementId = isset($row[$settlementIdIndex]) ? trim((string)$row[$settlementIdIndex]) : '';
            $transactionId = isset($row[$transactionIdIndex]) ? trim((string)$row[$transactionIdIndex]) : '';

            if (empty($settlementId) || empty($transactionId)) {
                $failed++;
                $errorMsg = "Row " . ($i + 1) . ": Settlement ID or Transaction ID is empty";
                $errors[] = $errorMsg;
                $failedSettlements[] = [
                    'row' => $i + 1,
                    'settlement_id' => $settlementId,
                    'transaction_id' => $transactionId,
                    'error' => $errorMsg
                ];
                continue;
            }

            // Check if settlement_id exists in transactions table
            $checkSql = "SELECT id, settlement_id, status, transaction_id FROM transactions WHERE settlement_id = ?";
            $checkStmt = mysqli_prepare($con, $checkSql);
            mysqli_stmt_bind_param($checkStmt, "s", $settlementId);
            mysqli_stmt_execute($checkStmt);
            $result = mysqli_stmt_get_result($checkStmt);

            if (mysqli_num_rows($result) > 0) {
                $transaction = mysqli_fetch_assoc($result);

                // Settlement exists, update transaction_id and status
                $updateSql = "UPDATE transactions SET transaction_id = ?, status = 'settled' WHERE settlement_id = ?";
                $updateStmt = mysqli_prepare($con, $updateSql);
                mysqli_stmt_bind_param($updateStmt, "ss", $transactionId, $settlementId);

                if (mysqli_stmt_execute($updateStmt)) {
                    $successful++;
                    $successfulSettlements[] = [
                        'row' => $i + 1,
                        'settlement_id' => $settlementId,
                        'transaction_id' => $transactionId,
                        'previous_transaction_id' => $transaction['transaction_id'],
                        'previous_status' => $transaction['status']
                    ];
                } else {
                    $failed++;
                    $errorMsg = "Row " . ($i + 1) . ": Failed to update settlement " . $settlementId . " - " . mysqli_error($con);
                    $errors[] = $errorMsg;
                    $failedSettlements[] = [
                        'row' => $i + 1,
                        'settlement_id' => $settlementId,
                        'transaction_id' => $transactionId,
                        'error' => $errorMsg
                    ];
                }
                mysqli_stmt_close($updateStmt);
            } else {
                $failed++;
                $errorMsg = "Row " . ($i + 1) . ": Settlement ID " . $settlementId . " not found in database";
                $errors[] = $errorMsg;
                $failedSettlements[] = [
                    'row' => $i + 1,
                    'settlement_id' => $settlementId,
                    'transaction_id' => $transactionId,
                    'error' => $errorMsg
                ];
            }

            mysqli_stmt_close($checkStmt);
        }

        // Clean any remaining output before sending JSON
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }
        
        // Return results
        $response = [
            'success' => true,
            'message' => "Bulk settlement processing completed. Processed: $processed, Successful: $successful, Failed: $failed",
            'data' => [
                'processed' => $processed,
                'successful' => $successful,
                'failed' => $failed,
                'errors' => $errors,
                'successful_settlements' => $successfulSettlements,
                'failed_settlements' => $failedSettlements
            ]
        ];
        
        echo json_encode($response);
        exit;

    } catch (Exception $e) {
        // Clean any remaining output before sending JSON
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }
        
        $response = [
            'success' => false,
            'message' => 'Error processing file: ' . $e->getMessage(),
            'data' => [
                'processed' => 0,
                'successful' => 0,
                'failed' => 0,
                'errors' => [$e->getMessage()]
            ]
        ];
        
        echo json_encode($response);
        exit;
    }
} else {
    // Clean any remaining output before sending JSON
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
    
    header('Content-Type: application/json; charset=UTF-8');
    $response = [
        'success' => false,
        'message' => 'No file uploaded',
        'data' => [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => ['No file uploaded']
        ]
    ];
    
    echo json_encode($response);
    exit;
}
?>