<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['superadmin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
$offset = ($page - 1) * $limit;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Build WHERE clause and parameters for prepared statement
$where = "WHERE v.status = 'pending'";
$params = [];
$paramTypes = "";

if ($q !== '') {
    $searchTerm = "%$q%";
    $where .= " AND (v.business_name LIKE ? OR v.owner_name LIKE ? OR v.mobile_number LIKE ? OR v.city LIKE ? OR s.name LIKE ?)";
    $params = array_fill(0, 5, $searchTerm);
    $paramTypes = "sssss";
}

// Count query with prepared statement
$countSql = "SELECT COUNT(*) AS total FROM vendor_registration v LEFT JOIN state s ON v.state = s.id $where";
$countStmt = mysqli_prepare($con, $countSql);
$total = 0;
if ($countStmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($countStmt, $paramTypes, ...$params);
    }
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    if ($countResult) {
        $row = mysqli_fetch_assoc($countResult);
        $total = (int)($row['total'] ?? 0);
    }
    mysqli_stmt_close($countStmt);
}
$totalPages = (int)ceil($total / $limit);

// Main query with prepared statement
$sql = "SELECT v.*, bt.type_name AS business_type, s.name AS state_name
        FROM vendor_registration v
        LEFT JOIN business_types bt ON v.business_type = bt.id
        LEFT JOIN state s ON v.state = s.id
        $where
        ORDER BY v.created_at DESC
        LIMIT ? OFFSET ?";
$mainStmt = mysqli_prepare($con, $sql);
$res = false;
if ($mainStmt) {
    if (!empty($params)) {
        $allParams = array_merge($params, [$limit, $offset]);
        $allTypes = $paramTypes . "ii";
        mysqli_stmt_bind_param($mainStmt, $allTypes, ...$allParams);
    } else {
        mysqli_stmt_bind_param($mainStmt, "ii", $limit, $offset);
    }
    mysqli_stmt_execute($mainStmt);
    $res = mysqli_stmt_get_result($mainStmt);
}

$rowsHtml = '';
if ($res && mysqli_num_rows($res) > 0) {
    $serial = $offset + 1;
    while ($v = mysqli_fetch_assoc($res)) {
        $requestedAt = !empty($v['created_at']) ? date('d,M Y - h:iA', strtotime($v['created_at'])) : (!empty($v['registration_date']) ? date('d,M Y', strtotime($v['registration_date'])) : '—');
        $isManufacture = !empty($v['manufacture_products']) ? $v['manufacture_products'] : 'No';
        $rowsHtml .= '<tr>'
            . '<td>' . $serial++ . '</td>'
            . '<td><b>' . htmlspecialchars($v['business_name']) . '</b></td>'
            . '<td>' . htmlspecialchars($v['business_type']) . '</td>'
            . '<td>' . htmlspecialchars($v['state_name']) . '</td>'
            . '<td>' . htmlspecialchars($v['city']) . '</td>'
            . '<td>' . htmlspecialchars($v['owner_name']) . '</td>'
            . '<td>' . htmlspecialchars($v['mobile_number']) . '</td>'
            . '<td>' . htmlspecialchars($isManufacture) . '</td>'
            . '<td>' . htmlspecialchars($requestedAt) . '</td>'
            . '<td><a href="./sellerDetail.php?vendor_id=' . urlencode($v['id']) . '" type="button" class="btn btn-secondary btn-wave waves-effect waves-light">View Request</a></td>'
            . '</tr>';
    }
} else {
    $rowsHtml = '<tr><td colspan="10" class="text-center">No pending vendors found.</td></tr>';
}

// Build pagination HTML
$base = '?';
$pagHtml = '';
$prevDisabled = ($page <= 1) ? 'disabled' : '';
$nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
$pagHtml .= '<li class="page-item ' . $prevDisabled . '"><a class="page-link" href="#" data-page="' . max(1, $page-1) . '">Previous</a></li>';
for ($i = 1; $i <= max(1,$totalPages); $i++) {
    $active = ($i == $page) ? 'active' : '';
    $pagHtml .= '<li class="page-item ' . $active . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
}
$pagHtml .= '<li class="page-item ' . $nextDisabled . '"><a class="page-link" href="#" data-page="' . min($totalPages, $page+1) . '">Next</a></li>';

echo json_encode([
    'success' => true,
    'rows_html' => $rowsHtml,
    'pagination_html' => $pagHtml,
    'total' => $total,
    'page' => $page,
    'total_pages' => $totalPages,
]);
?>


