<?php
// Include database connection
include __DIR__ . '/../includes/init.php';

if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}

$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
if ($autoloadPath && file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

// Handle Upload Attributes
if (isset($_POST['upload_attributes'])) {
    $file = $_FILES['uploadAttributesFile']['tmp_name'];

    if (!empty($file)) {
        try {
            $spreadsheet = IOFactory::load($file);
            $sheetData = $spreadsheet->getActiveSheet()->toArray();

            $isFirstRow = true;
            $inserted = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($sheetData as $row) {
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }

                // Check if row is completely empty - skip if all cells are empty
                $isRowEmpty = true;
                foreach ($row as $cell) {
                    if (!empty(trim($cell ?? ''))) {
                        $isRowEmpty = false;
                        break;
                    }
                }
                
                // Skip completely empty rows
                if ($isRowEmpty) {
                    continue;
                }

                $name = trim($row[0] ?? '');
                $valuesString = trim($row[1] ?? '');
                $attribute_type = trim($row[2] ?? '');

                // Validate and set default attribute_type
                $valid_types = ['single', 'multi', 'text'];
                if (empty($attribute_type) || !in_array(strtolower($attribute_type), $valid_types)) {
                    $attribute_type = 'multi'; // Default to multi if invalid or empty
                } else {
                    $attribute_type = strtolower($attribute_type);
                }

                // For text type, values are optional. For single/multi, values are required.
                $valuesRequired = ($attribute_type !== 'text');
                
                // Validate: name is always required, values are required only for single/multi types
                if ($name !== "" && ($valuesRequired === false || $valuesString !== "")) {
                    // Process values if provided (or if not text type)
                    $valuesArray = [];
                    if (!empty($valuesString)) {
                        $valuesArray = array_map('trim', explode(',', $valuesString));
                    }

                    $attribute_values = [];
                    foreach ($valuesArray as $index => $valueName) {
                        if (!empty($valueName)) {
                            $attribute_values[] = [
                                'value_id' => 'val_' . time() . '_' . $index,
                                'value_name' => $valueName
                            ];
                        }
                    }

                    // For text type with no values, create empty array
                    // For single/multi type, if no values provided, skip this row
                    if ($valuesRequired && empty($attribute_values)) {
                        $skipped++;
                        continue;
                    }

                    $json_values = json_encode($attribute_values, JSON_UNESCAPED_UNICODE);

                    $check = $con->prepare("SELECT id FROM attributes WHERE name = ?");
                    $check->bind_param("s", $name);
                    $check->execute();
                    $result = $check->get_result();

                    if ($result->num_rows > 0) {
                        $stmt = $con->prepare("UPDATE attributes SET attribute_values = ?, attribute_type = ?, created_date = NOW() WHERE name = ?");
                        $stmt->bind_param("sss", $json_values, $attribute_type, $name);
                        $stmt->execute();
                        $updated++;
                    } else {
                        $stmt = $con->prepare("INSERT INTO attributes (name, attribute_values, attribute_type, created_date) VALUES (?, ?, ?, NOW())");
                        $stmt->bind_param("sss", $name, $json_values, $attribute_type);
                        $stmt->execute();
                        $inserted++;
                    }
                } else {
                    $skipped++;
                }
            }

            // Set success message (for modal)
            $message = "Upload complete!<br>Inserted: <strong>{$inserted}</strong>, Updated: <strong>{$updated}</strong>, Skipped: <strong>{$skipped}</strong>";
            $message_type = "success";

            // Trigger modal via JS
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('uploadResultMessage').innerHTML = " . json_encode($message) . ";
                    const modal = new bootstrap.Modal(document.getElementById('uploadResultModal'));
                    modal.show();
                });
            </script>";
        } catch (Exception $e) {
            $message = "Error loading file: " . htmlspecialchars($e->getMessage());
            $message_type = "danger";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('uploadResultMessage').innerHTML = " . json_encode($message) . ";
                    document.getElementById('uploadResultModal').querySelector('.modal-header').classList.replace('border-success', 'border-danger');
                    document.getElementById('uploadResultModal').querySelector('.modal-title').textContent = 'Upload Failed';
                    const modal = new bootstrap.Modal(document.getElementById('uploadResultModal'));
                    modal.show();
                });
            </script>";
        }
    } else {
        $message = "Please upload a valid file.";
        $message_type = "danger";
    }
}
// Upload Attributes
// Export to Excel - Attributes (full list)
if (isset($_GET['export']) && strtolower((string) $_GET['export']) === 'excel') {
    // Clean all output buffers FIRST to prevent corruption
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    } else {
        @ob_end_clean();
    }

    $autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
    if ($autoloadPath && file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        // Clean buffers before error message
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }
        header('Content-Type: text/plain');
        header('Cache-Control: no-cache');
        echo 'Excel export packages not found. Please install PhpSpreadsheet via Composer.';
        exit;
    }

    $rs = mysqli_query($con, "SELECT * FROM attributes ORDER BY created_date DESC");
    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Attributes');
    $headers = [
        'A1' => 'Variant',
        'B1' => 'Values',
        'C1' => 'Attribute Type',
    ];
    foreach ($headers as $cell => $label) {
        $sheet->setCellValue($cell, $label);
    }
    $sheet->getStyle('A1:C1')->getFont()->setBold(true);

    $row = 2;
    if ($rs) {
        while ($attr = mysqli_fetch_assoc($rs)) {
            $values_display = '';
            if (!empty($attr['attribute_values'])) {
                $vals = json_decode($attr['attribute_values'], true);
                if (is_array($vals)) {
                    $value_names = array_column($vals, 'value_name');
                    $values_display = implode(', ', $value_names);
                }
            }
            $sheet->setCellValue('A' . $row, (string) ($attr['name'] ?? ''));
            $sheet->setCellValue('B' . $row, (string) $values_display);
            $sheet->setCellValue('C' . $row, (string) ($attr['attribute_type'] ?? 'multi'));
            $row++;
        }
    }
    foreach (range('A', 'C') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Clean output buffers again before sending headers
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    } else {
        @ob_end_clean();
    }

    // Set proper headers for Excel download
    $fileName = 'attributes_export_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: public');
    header('Expires: 0');
    header('Content-Transfer-Encoding: binary');

    // Write file
    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Handle CRUD operations
$message = '';
$message_type = '';

// Add new attribute using prepared statement
if (isset($_POST['add_attribute'])) {
    $name = trim($_POST['variant']);
    $values = $_POST['values'];
    $a_type = $_POST['attribute_type'];

    // Create JSON array for attribute values
    $attribute_values = [];
    foreach ($values as $index => $value) {
        if (!empty(trim($value))) {
            $attribute_values[] = [
                'value_id' => 'val_' . time() . '_' . $index,
                'value_name' => trim($value)
            ];
        }
    }

    $json_values = json_encode($attribute_values);

    $stmt = mysqli_prepare($con, "INSERT INTO attributes (name, attribute_values, attribute_type) VALUES (?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sss", $name, $json_values,  $a_type);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Attribute added successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . mysqli_stmt_error($stmt);
            $message_type = "danger";
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "Error preparing statement: " . mysqli_error($con);
        $message_type = "danger";
    }
}

// Update attribute using prepared statement
if (isset($_POST['update_attribute'])) {
    $id = (int) $_POST['attribute_id'];
    $name = trim($_POST['variant']);
    $attribute_type = trim($_POST['attribute_type']);

    $values = $_POST['values'];

    // Create JSON array for attribute values
    $attribute_values = [];
    foreach ($values as $index => $value) {
        if (!empty(trim($value))) {
            $attribute_values[] = [
                'value_id' => 'val_' . time() . '_' . $index,
                'value_name' => trim($value)
            ];
        }
    }

    $json_values = json_encode($attribute_values);

    $stmt = mysqli_prepare($con, "UPDATE attributes SET name=?,attribute_type=?, attribute_values=? WHERE id=?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssi", $name, $attribute_type, $json_values, $id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Attribute updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . mysqli_stmt_error($stmt);
            $message_type = "danger";
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "Error preparing statement: " . mysqli_error($con);
        $message_type = "danger";
    }
}

// Delete attribute using prepared statement
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = mysqli_prepare($con, "DELETE FROM attributes WHERE id=?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Attribute deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . mysqli_stmt_error($stmt);
            $message_type = "danger";
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "Error preparing statement: " . mysqli_error($con);
        $message_type = "danger";
    }
}

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = " WHERE name LIKE '%$search%'";
}

// Pagination settings
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1

// Calculate offset
$offset = ($current_page - 1) * $records_per_page;

// Get total count of attributes using prepared statement
$count_query = "SELECT COUNT(*) as total FROM attributes" . $search_condition;
$count_result = mysqli_query($con, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get attributes for current page using prepared statement
$query = "SELECT * FROM attributes" . $search_condition . " ORDER BY created_date DESC LIMIT $records_per_page OFFSET $offset";
$result = mysqli_query($con, $query);
$attributes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $attributes[] = $row;
}

// Get single attribute for editing
$edit_attribute = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $query = "SELECT * FROM attributes WHERE id=$id";
    $result = mysqli_query($con, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $edit_attribute = mysqli_fetch_assoc($result);
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
    <meta http-equiv="X-UA-Compatible" content="Hagidy-Super-Admin">
    <title>ATTRIBUTES MANAGEMENT | HADIDY</title>
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

    <!-- Custom CSS for Search and Address Truncation -->
    <style>
        /* Search Input Styling */
        .search-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        #searchInput {
            padding-right: 40px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        #searchInput:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        /* Loading state for search */
        .search-loading {
            position: relative;
        }

        .search-loading::after {
            content: '';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: translateY(-50%) rotate(0deg);
            }

            100% {
                transform: translateY(-50%) rotate(360deg);
            }
        }
    </style>

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

        <!-- End::app-sidebar -->

        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div
                    class="d-flex align-items-center justify-content-between my-3 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Attributes</h1>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-primary btn-wave waves-effect waves-light"
                            data-bs-toggle="modal" data-bs-target="#uploadAttributesModal" onclick="resetForm()">
                            <i class="fa-solid fa-file-arrow-down"></i>
                            Upload Attributes
                        </button>
                        <a href="#" type="button" class="btn btn-secondary btn-wave waves-effect waves-light"
                            data-bs-toggle="modal" data-bs-target="#addAttributeModal" onclick="resetForm()">+ Add
                            Attributes</a>
                    </div>
                </div>
                <!-- Page Header Close -->

                <!-- Alert Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"
                        id="successMessage">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap w-100-product">

                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">

                                    <div class="selecton-order">
                                        <div class="search-container">
                                            <input type="search" name="search" class="form-control" id="searchInput"
                                                placeholder="Search attributes..."
                                                value="<?php echo htmlspecialchars($search); ?>"
                                                aria-describedby="button-addon2">
                                        </div>
                                    </div>
                                    <div>
                                        <?php $exportUrl = basename(__FILE__) . '?' . http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>
                                        <a href="<?php echo htmlspecialchars($exportUrl); ?>"
                                            class="btn-down-excle1 w-100"><i class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</a>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped text-nowrap align-middle table-bordered-vertical">
                                    <thead class="table-group-divider">
                                        <tr>
                                            <th scope="col">No</th>
                                            <th scope="col">ID</th>
                                            <th scope="col">Variant</th>
                                            <th scope="col">Value</th>
                                            <th scope="col">Atribute Type</th>
                                            <th scope="col">Created On</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                        <?php if (empty($attributes)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <i class="fe fe-inbox fs-48 mb-3 text-muted"></i>
                                                    <div class="text-muted">No attributes found. <a href="#"
                                                            data-bs-toggle="modal" data-bs-target="#addAttributeModal"
                                                            onclick="resetForm()">Add your first attribute</a></div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($attributes as $index => $attribute):
                                                $values = json_decode($attribute['attribute_values'], true);
                                                $value_names = array_column($values, 'value_name');
                                                $values_display = implode(' , ', $value_names);
                                            ?>
                                                <tr>
                                                    <td><?php echo $offset + $index + 1; ?></td>
                                                    <td class="rqu-id">#<?php echo $attribute['id']; ?></td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($attribute['name']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($values_display); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($attribute['attribute_type']); ?>
                                                        </div>
                                                    </td>
                                                   <td>
                                                    <div class="order-date-tr">
                                                        <?php
                                                        if (!empty($attribute['created_date'])) {
                                                            try {
                                                                // Convert from UTC → IST
                                                                $dt = new DateTime($attribute['created_date'], new DateTimeZone('UTC'));
                                                                $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                                echo $dt->format('d M Y - h:i A');
                                                            } catch (Exception $e) {
                                                                echo htmlspecialchars($attribute['created_date']); // fallback if invalid format
                                                            }
                                                        } else {
                                                            echo '—';
                                                        }
                                                        ?>
                                                    </div>
                                                </td>

                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <a href="#" class="i-icon-eidt" data-bs-toggle="modal"
                                                                data-bs-target="#addAttributeModal"
                                                                onclick="editAttribute(<?php echo $attribute['id']; ?>, '<?php echo htmlspecialchars($attribute['name']); ?>','<?php echo htmlspecialchars($attribute['attribute_type']); ?>', <?php echo htmlspecialchars(json_encode($value_names)); ?>)">
                                                                <i class="fa-regular fa-pen-to-square"></i>
                                                            </a>
                                                            <a href="#" class="i-icon-trash cancelOrderBtn"
                                                                data-attribute-id="<?php echo $attribute['id']; ?>">
                                                                <i class="fa-solid fa-trash-can"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($total_pages > 1): ?>
                                <div class="d-flex justify-content-center mt-3 mb-3">
                                    <nav>
                                        <ul class="pagination pagination-sm mb-0">
                                            <!-- Previous Button -->
                                            <?php if ($current_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link"
                                                        href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">Previous</span>
                                                </li>
                                            <?php endif; ?>

                                            <!-- Page Numbers -->
                                            <?php
                                            $start_page = max(1, $current_page - 2);
                                            $end_page = min($total_pages, $current_page + 2);

                                            // Show first page if not in range
                                            if ($start_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">1</a>
                                                </li>
                                                <?php if ($start_page > 2): ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link">...</span>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <!-- Page numbers in range -->
                                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <!-- Show last page if not in range -->
                                            <?php if ($end_page < $total_pages): ?>
                                                <?php if ($end_page < $total_pages - 1): ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link">...</span>
                                                    </li>
                                                <?php endif; ?>
                                                <li class="page-item">
                                                    <a class="page-link"
                                                        href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $total_pages; ?></a>
                                                </li>
                                            <?php endif; ?>

                                            <!-- Next Button -->
                                            <?php if ($current_page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">Next</span>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                    <!-- End:: row-2 -->
                </div>

                <!-- Cancel Order Confirmation Modal -->
                <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel"
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
                                <h5 class="mb-4" style="color: #4A5568; font-weight: 600; font-size: 18px;">
                                    Are you sure, you want to Delete <br> the Attribute ?
                                </h5>

                                <!-- Buttons -->
                                <div class="d-flex gap-3 justify-content-center">
                                    <button type="button" class="btn btn-outline-danger" id="cancelNoBtn"
                                        style="  border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                        No
                                    </button>
                                    <button type="button" class="btn btn-primary" id="cancelYesBtn"
                                        style="background-color: #4A5568; border-color: #4A5568; border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                        Yes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Upload Result Modal -->
                <div class="modal fade" id="uploadResultModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border  border-3">
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title text-primary fw-bold">
                                    <i class="fa-solid fa-check-circle me-2"></i> Upload Successful
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center py-4">
                                <div class="mb-3">
                                    <i class="fa-solid fa-cloud-arrow-up text-primary" style="font-size: 48px;"></i>
                                </div>
                                <div id="uploadResultMessage" class="fs-16 fw-medium text-dark">
                                    <!-- Message injected via JS -->
                                </div>
                            </div>
                            <div class="modal-footer border-0 justify-content-center pt-0">
                                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal"
                                    onclick="window.location.href='attributes.php'">
                                    <i class="fa-solid fa-check me-2"></i> Done
                                </button>
                            </div>
                        </div>
                    </div>
                </div>


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
                                        <a href="#"><span>Notifications</span></a>
                                        <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                            aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                                    </div>
                                    <div class="p-2 border br-5 d-flex align-items-center text-muted mb-2 alert">
                                        <a href="alerts.html"><span>Alerts</span></a>
                                        <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                            aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                                    </div>
                                    <div class="p-2 border br-5 d-flex align-items-center text-muted mb-0 alert">
                                        <a href="mail.html"><span>Mail</span></a>
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

            <!-- Add Attribute Modal -->

            <div class="modal fade" id="addAttributeModal" tabindex="-1" aria-labelledby="addAttributeModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content add-attribute-modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h4 class="modal-title w-100 text-center fw-bold" id="addAttributeModalLabel">Add Attributes
                            </h4>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body pt-3">
                            <form id="attributeForm" method="POST">
                                <input type="hidden" id="attribute_id" name="attribute_id" value="">
                                <div class="mb-3">
                                    <label for="variant" class="form-label fw-semibold">Variant <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-lg" id="variant" name="variant"
                                        placeholder="Enter Variant" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Attribute Type <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-4">
                                        <div>
                                            <input type="radio" id="type_single" name="attribute_type" value="single" required>
                                            <label for="type_single">Single Select</label>
                                        </div>
                                        <div>
                                            <input type="radio" id="type_multi" name="attribute_type" value="multi">
                                            <label for="type_multi">Multi Select</label>
                                        </div>
                                        <div>
                                            <input type="radio" id="type_text" name="attribute_type" value="text">
                                            <label for="type_text">Text Input</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="value" class="form-label fw-semibold">Value <span
                                            class="text-danger" id="valueRequiredAsterisk">*</span></label>
                                    <div id="valueFields">
                                        <div class="input-group mb-2">
                                            <input type="text" class="form-control form-control-lg" name="values[]"
                                                placeholder="Enter Value" id="firstValueInput" required>
                                            <button type="button" class="btn btn-outline-secondary btn-icon move-up-btn"
                                                title="Move up" data-bs-toggle="tooltip">
                                                <i class="fa-solid fa-arrow-up"></i>
                                            </button>
                                            <button type="button"
                                                class="btn btn-outline-secondary btn-icon move-down-btn"
                                                title="Move down" data-bs-toggle="tooltip">
                                                <i class="fa-solid fa-arrow-down"></i>
                                            </button>
                                        </div>
                                        <div class="input-group mb-2">
                                            <input type="text" class="form-control form-control-lg" name="values[]"
                                                placeholder="Enter Value">
                                            <button type="button"
                                                class="btn btn-outline-danger btn-icon delete-value-btn" title="Remove"
                                                data-bs-toggle="tooltip">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-icon move-up-btn"
                                                title="Move up" data-bs-toggle="tooltip">
                                                <i class="fa-solid fa-arrow-up"></i>
                                            </button>
                                            <button type="button"
                                                class="btn btn-outline-secondary btn-icon move-down-btn"
                                                title="Move down" data-bs-toggle="tooltip">
                                                <i class="fa-solid fa-arrow-down"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn add-more-btn mb-4" id="addMoreValue">
                                    Add More <span class="ms-2">+</span>
                                </button>
                                <button type="submit" class="btn save-attribute-btn w-100" id="submitBtn">Save</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!--
             Upload Attributes Modal 
            -->
            <div class="modal fade" id="uploadAttributesModal" tabindex="-1" aria-labelledby="uploadAttributesModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="" id="uploadAttributesModalLabel">Upload Attributes</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="uploadAttributesForm" method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="uploadAttributesFile" class="form-label">Upload Attributes File</label>
                                    <input type="file" class="form-control mb-3" id="uploadAttributesFile" name="uploadAttributesFile" required>
                                    <p class="text-muted">Supported file types: .xlsx, .xls</p>
                                    <p class="text-muted">Example file: <a href="<?php echo PUBLIC_ASSETS; ?>uploads/excel/attribute_template.xlsx" target="_blank">download attribute template</a></p>
                                    <button type="submit" name="upload_attributes" class="btn btn-primary w-100">Upload</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End:: Upload Attributes Modal -->
            <!-- AJAX Search JavaScript -->
            <script>
                // AJAX Search functionality
                document.addEventListener('DOMContentLoaded', function() {
                    const searchInput = document.getElementById('searchInput');
                    const searchContainer = document.querySelector('.search-container');
                    let searchTimeout;

                    if (searchInput) {
                        // Search on input with debounce
                        searchInput.addEventListener('input', function() {
                            clearTimeout(searchTimeout);
                            const searchTerm = this.value.trim();

                            // Show loading state
                            searchContainer.classList.add('search-loading');

                            if (searchTerm.length > 0) {
                                searchTimeout = setTimeout(() => {
                                    performSearch(searchTerm);
                                }, 500); // 500ms delay
                            } else {
                                // Clear search if input is empty
                                clearSearch();
                            }
                        });

                        // Search on Enter key
                        searchInput.addEventListener('keypress', function(e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                const searchTerm = this.value.trim();
                                if (searchTerm.length > 0) {
                                    performSearch(searchTerm);
                                }
                            }
                        });

                        // Handle clear button (X) click
                        searchInput.addEventListener('search', function() {
                            if (this.value === '') {
                                clearSearch();
                            }
                        });
                    }

                    // Perform AJAX search
                    function performSearch(searchTerm) {
                        // Create URL with search parameter
                        const url = new URL(window.location);
                        url.searchParams.set('search', searchTerm);
                        url.searchParams.delete('page'); // Reset to first page

                        // Fetch the page with search results
                        fetch(url.toString())
                            .then(response => response.text())
                            .then(html => {
                                // Create a temporary DOM element to parse the response
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(html, 'text/html');

                                // Extract the table body content
                                const newTableBody = doc.querySelector('tbody');
                                const currentTableBody = document.querySelector('tbody');

                                if (newTableBody && currentTableBody) {
                                    currentTableBody.innerHTML = newTableBody.innerHTML;
                                }

                                // Update pagination if exists
                                const newPagination = doc.querySelector('.pagination');
                                const currentPagination = document.querySelector('.pagination');

                                if (newPagination && currentPagination) {
                                    currentPagination.innerHTML = newPagination.innerHTML;
                                }

                                // Update URL without page reload
                                window.history.pushState({}, '', url.toString());
                            })
                            .catch(error => {
                                console.error('Search error:', error);
                                alert('Search failed. Please try again.');
                            })
                            .finally(() => {
                                // Remove loading state
                                searchContainer.classList.remove('search-loading');
                            });
                    }

                    // Clear search function
                    function clearSearch() {
                        // Remove search parameter from URL
                        const url = new URL(window.location);
                        url.searchParams.delete('search');
                        url.searchParams.delete('page');

                        // Fetch the page without search
                        fetch(url.toString())
                            .then(response => response.text())
                            .then(html => {
                                // Create a temporary DOM element to parse the response
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(html, 'text/html');

                                // Extract the table body content
                                const newTableBody = doc.querySelector('tbody');
                                const currentTableBody = document.querySelector('tbody');

                                if (newTableBody && currentTableBody) {
                                    currentTableBody.innerHTML = newTableBody.innerHTML;
                                }

                                // Update pagination if exists
                                const newPagination = doc.querySelector('.pagination');
                                const currentPagination = document.querySelector('.pagination');

                                if (newPagination && currentPagination) {
                                    currentPagination.innerHTML = newPagination.innerHTML;
                                }

                                // Update URL without page reload
                                window.history.pushState({}, '', url.toString());
                            })
                            .catch(error => {
                                console.error('Clear search error:', error);
                                alert('Failed to clear search. Please refresh the page.');
                            })
                            .finally(() => {
                                // Remove loading state
                                searchContainer.classList.remove('search-loading');
                            });
                    }
                });
            </script>

            <!-- Modal JavaScript -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Auto-hide success message after 5 seconds and clean URL
                    const successMessage = document.getElementById('successMessage');
                    if (successMessage) {
                        // Clean URL parameters immediately to prevent message on refresh
                        if (window.history.replaceState) {
                            const url = new URL(window.location);
                            url.searchParams.delete('success');
                            url.searchParams.delete('updated');
                            url.searchParams.delete('deleted');
                            window.history.replaceState({}, document.title, url.pathname + url.search);
                        }

                        // Hide message after 5 seconds
                        setTimeout(function() {
                            successMessage.style.transition = 'opacity 0.5s';
                            successMessage.style.opacity = '0';
                            setTimeout(function() {
                                successMessage.remove();
                            }, 500);
                        }, 5000);
                    }

                    // Get all cancel buttons and modal elements
                    const cancelBtns = document.querySelectorAll('.cancelOrderBtn');
                    const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
                    const cancelNoBtn = document.getElementById('cancelNoBtn');
                    const cancelYesBtn = document.getElementById('cancelYesBtn');
                    let attributeIdToDelete = null;

                    // Show modal when any cancel button is clicked
                    cancelBtns.forEach(function(btn) {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            attributeIdToDelete = this.getAttribute('data-attribute-id');
                            modal.show();
                        });
                    });

                    // Handle No button click
                    if (cancelNoBtn) {
                        cancelNoBtn.addEventListener('click', function() {
                            modal.hide();
                            attributeIdToDelete = null;
                        });
                    }

                    // Handle Yes button click
                    if (cancelYesBtn) {
                        cancelYesBtn.addEventListener('click', function() {
                            if (attributeIdToDelete) {
                                // Redirect to delete the attribute
                                window.location.href = '?delete=' + attributeIdToDelete;
                            }
                        });
                    }
                });
            </script>
            <script>
                // Add more value fields
                document.getElementById('addMoreValue').addEventListener('click', function() {
                    const valueFields = document.getElementById('valueFields');
                    const div = document.createElement('div');
                    div.className = 'input-group mb-2';
                    // Check if text type is selected - don't add required to new fields
                    const selectedAttributeType = document.querySelector('input[name="attribute_type"]:checked');
                    const isTextType = selectedAttributeType && selectedAttributeType.value === 'text';
                    div.innerHTML = `
        <input type="text" class="form-control form-control-lg" name="values[]" placeholder="Enter Value" ${isTextType ? '' : ''}>
        <button type="button" class="btn delete-value-btn" title="Remove">
            <i class="fa-solid fa-trash-can"></i>
        </button>
        <button type="button" class="btn move-up-btn" title="Move up">
            <i class="fa-solid fa-arrow-up"></i>
        </button>
        <button type="button" class="btn move-down-btn" title="Move down">
            <i class="fa-solid fa-arrow-down"></i>
        </button>
    `;
                    valueFields.appendChild(div);
                });

                // Delete value fields
                document.getElementById('valueFields').addEventListener('click', function(e) {
                    if (e.target.closest('.delete-value-btn')) {
                        e.target.closest('.input-group').remove();
                        return;
                    }
                    // Move up
                    if (e.target.closest('.move-up-btn')) {
                        const group = e.target.closest('.input-group');
                        const prev = group.previousElementSibling;
                        if (prev) {
                            group.parentNode.insertBefore(group, prev);
                        }
                        return;
                    }
                    // Move down
                    if (e.target.closest('.move-down-btn')) {
                        const group = e.target.closest('.input-group');
                        const next = group.nextElementSibling;
                        if (next) {
                            group.parentNode.insertBefore(next, group);
                        }
                        return;
                    }
                });

                // Reset form for adding new attribute
                function resetForm() {
                    document.getElementById('attributeForm').reset();
                    document.getElementById('attribute_id').value = '';
                    document.getElementById('addAttributeModalLabel').textContent = 'Add Attributes';
                    document.getElementById('submitBtn').textContent = 'Save';
                    document.getElementById('submitBtn').name = 'add_attribute';

                    // Reset value fields to default (2 fields)
                    const valueFields = document.getElementById('valueFields');
                    valueFields.innerHTML = `
        <div class="input-group mb-2">
            <input type="text" class="form-control form-control-lg" name="values[]" id="firstValueInput" placeholder="Enter Value" required>
        </div>
        <div class="input-group mb-2">
            <input type="text" class="form-control form-control-lg" name="values[]" placeholder="Enter Value">
            <button type="button" class="btn delete-value-btn">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>
    `;
                    
                    // Reset required asterisk visibility
                    const valueRequiredAsterisk = document.getElementById('valueRequiredAsterisk');
                    if (valueRequiredAsterisk) {
                        valueRequiredAsterisk.style.display = 'inline';
                    }
                }

                // Edit attribute function
                function editAttribute(id, name,attribute_type, values) {
                    document.getElementById('attribute_id').value = id;
                    document.getElementById('variant').value = name;
                
                    
                    const radios = document.querySelectorAll('input[name="attribute_type"]');
                    radios.forEach(r => r.checked = false);
                    const selected = document.querySelector(`input[name="attribute_type"][value="${attribute_type}"]`);
                    if (selected) {
                        selected.checked = true;
                        // Trigger change event to update required fields
                        selected.dispatchEvent(new Event('change'));
                    }

                    document.getElementById('addAttributeModalLabel').textContent = 'Edit Attributes';
                    document.getElementById('submitBtn').textContent = 'Update';
                    document.getElementById('submitBtn').name = 'update_attribute';

                    // Clear existing value fields
                    const valueFields = document.getElementById('valueFields');
                    valueFields.innerHTML = '';

                    // Add value fields based on existing values
                    values.forEach((value, index) => {
                        const div = document.createElement('div');
                        div.className = 'input-group mb-2';
                        const isRequired = attribute_type !== 'text' && index === 0;
                        if (index === 0) {
                            div.innerHTML = `
                <input type="text" class="form-control form-control-lg" name="values[]" id="firstValueInput" placeholder="Enter Value" value="${value}" ${isRequired ? 'required' : ''}>
                <div class="d-flex align-items-center gap-2 ">
                    <button type="button" class="btn btn-outline-danger btn-icon delete-value-btn h-100" title="Remove" data-bs-toggle="tooltip">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-icon move-up-btn" title="Move up" data-bs-toggle="tooltip">
                        <i class="fa-solid fa-arrow-up"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-icon move-down-btn" title="Move down" data-bs-toggle="tooltip">
                        <i class="fa-solid fa-arrow-down"></i>
                    </button>
                </div>
            `;
                        } else {
                            div.innerHTML = `
                           
                <input type="text" class="form-control form-control-lg" name="values[]" placeholder="Enter Value" value="${value}">
                 <div class="d-flex align-items-center gap-2 ">
                <button type="button" class="btn btn-outline-danger btn-icon delete-value-btn h-100" title="Remove" data-bs-toggle="tooltip">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-icon move-up-btn" title="Move up" data-bs-toggle="tooltip">
                    <i class="fa-solid fa-arrow-up"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-icon move-down-btn" title="Move down" data-bs-toggle="tooltip">
                    <i class="fa-solid fa-arrow-down"></i>
                </button>
                </div>
            `;
                        }
                        valueFields.appendChild(div);
                    });

                    // If no values, add default fields
                    if (values.length === 0) {
                        const isRequired = attribute_type !== 'text';
                        valueFields.innerHTML = `
            <div class="input-group mb-2">
                <input type="text" class="form-control form-control-lg" name="values[]" id="firstValueInput" placeholder="Enter Value" ${isRequired ? 'required' : ''}>
            </div>
            <div class="input-group mb-2">
                <input type="text" class="form-control form-control-lg" name="values[]" placeholder="Enter Value">
                <button type="button" class="btn delete-value-btn" title="Remove">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
                <button type="button" class="btn move-up-btn" title="Move up">
                    <i class="fa-solid fa-arrow-up"></i>
                </button>
                <button type="button" class="btn move-down-btn" title="Move down">
                    <i class="fa-solid fa-arrow-down"></i>
                </button>
            </div>
        `;
                    }
                    
                    // Update required asterisk visibility based on attribute type
                    const valueRequiredAsterisk = document.getElementById('valueRequiredAsterisk');
                    if (valueRequiredAsterisk) {
                        valueRequiredAsterisk.style.display = attribute_type === 'text' ? 'none' : 'inline';
                    }
                }

                // Handle attribute type change - make Value field optional for text type
                document.querySelectorAll('input[name="attribute_type"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        const attributeType = this.value;
                        const valueLabel = document.querySelector('label[for="value"]');
                        const valueRequiredAsterisk = document.getElementById('valueRequiredAsterisk');
                        const valueInputs = document.querySelectorAll('input[name="values[]"]');
                        
                        if (attributeType === 'text') {
                            // Remove required asterisk
                            if (valueRequiredAsterisk) {
                                valueRequiredAsterisk.style.display = 'none';
                            }
                            // Remove required attribute from all value inputs
                            valueInputs.forEach(input => {
                                input.removeAttribute('required');
                            });
                        } else {
                            // Show required asterisk
                            if (valueRequiredAsterisk) {
                                valueRequiredAsterisk.style.display = 'inline';
                            }
                            // Add required attribute to first value input
                            const firstInput = document.getElementById('firstValueInput');
                            if (firstInput) {
                                firstInput.setAttribute('required', 'required');
                            }
                        }
                    });
                });

                // Form submission handling
                document.getElementById('attributeForm').addEventListener('submit', function(e) {
                    const selectedAttributeType = document.querySelector('input[name="attribute_type"]:checked');
                    
                    // Skip value validation if attribute type is "text"
                    if (selectedAttributeType && selectedAttributeType.value === 'text') {
                        return true; // Allow form submission without value validation
                    }
                    
                    const values = document.querySelectorAll('input[name="values[]"]');
                    let hasValue = false;

                    values.forEach(input => {
                        if (input.value.trim() !== '') {
                            hasValue = true;
                        }
                    });

                    if (!hasValue) {
                        e.preventDefault();
                        alert('Please enter at least one value.');
                        return false;
                    }
                });
            </script>

</body>

</html>