<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../pages/includes/init.php';

$ids = isset($_GET['ids']) ? trim($_GET['ids']) : '';
$result = ['success' => true, 'attributes' => []];

if ($ids !== '') {
    $idsArray = array_filter(array_map('intval', explode(',', $ids)));
    if (!empty($idsArray)) {
        $idsStr = implode(',', $idsArray);
        $rs = mysqli_query($con, "SELECT id, name FROM attributes WHERE id IN ($idsStr) ORDER BY name");
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $result['attributes'][] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name']
                ];
            }
        }
    }
}

echo json_encode($result);
exit;
?>
