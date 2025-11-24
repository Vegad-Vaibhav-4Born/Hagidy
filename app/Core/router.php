<?php
// function route_to_page($route)
// {
//     // sanitize route: allow letters, numbers, dash, slash
//     $route = trim($route, "/ ");
//     if ($route === '') {
//         $route = 'index';
//     }

//     // Handle nested like product-detail/123 -> product-detail with $_GET['id']
//     if (preg_match('/^(product-detail)\/(\d+)/i', $route, $m)) {
//         $_GET['id'] = $m[2];
//         $route = $m[1];
//     } elseif (preg_match('/^(order-detail)\/(\d+)/i', $route, $m)) {
//         $_GET['id'] = $m[2];
//         $route = $m[1];
//     }

//     // Normalize file path
//     $route = preg_replace('/[^A-Za-z0-9\-\/]/', '', $route);
//     $relative = 'app/pages/' . $route . '.php';
//     $file = __DIR__ . '/../../' . $relative;

//     if (file_exists($file)) {
//         include $file;
//         return;
//     }

//     // Fallback to 404 if present, else simple message
//     $notFound = __DIR__ . '/../../app/pages/404.php';
//     if (file_exists($notFound)) {
//         http_response_code(404);
//         include $notFound;
//     } else {
//         http_response_code(404);
//         echo '404 Not Found';
//     }
// }
?>

