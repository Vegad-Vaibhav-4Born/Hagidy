<?php
// Bootstrap app (session, config, db)
$init = __DIR__ . '/../app/Core/init.php';
if (file_exists($init)) { require_once $init; }

// Minimal router helper that resolves app/pages files
function route_to_page($route)
{
    // expose DB connection in this function scope so included pages can use it
    global $db_connection, $con;
    // sanitize route: allow letters, numbers, dash, slash
    $route = trim($route, "/ ");
    if ($route === '') {
        $route = 'index';
    }

    // Handle nested like product-detail/123 -> product-detail with $_GET['id']
    if (preg_match('/^(product-detail)\/(\d+)/i', $route, $m)) {
        $_GET['id'] = $m[2];
        $route = $m[1];
    } elseif (preg_match('/^(order-detail)\/(\d+)/i', $route, $m)) {
        $_GET['id'] = $m[2];
        $route = $m[1];
    }

    // Normalize file path and resolve under app/pages (allow underscore for files like privacy_policy)
    $route = preg_replace('/[^A-Za-z0-9_\-\/]/', '', $route);
    $relative = 'app/pages/' . $route . '.php';
    $file = __DIR__ . '/../' . $relative;

    if (file_exists($file)) {
        include $file;
        return;
    }

    // Fallback to 404 if present, else simple message
    $notFound = __DIR__ . '/../app/pages/404.php';
    if (file_exists($notFound)) {
        http_response_code(404);
        include $notFound;
    } else {
        http_response_code(404);
        echo '404 Not Found';
    }
}

// Resolve route from query param set by .htaccess (e.g., index.php -> route=index)
$route = isset($_GET['route']) ? trim($_GET['route'], "/ ") : '';
if ($route === '' || strcasecmp($route, 'index.php') === 0) {
    $route = 'index';
}
// Drop .php extension if present (cart.php -> cart)
$route = preg_replace('/\.php$/i', '', $route);

// Dispatch
route_to_page($route);
?>
