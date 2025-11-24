<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/init.php';

$vendor_reg_id = $_SESSION['vendor_reg_id'];

require_once __DIR__ . '/../../handlers/acess_guard.php';

// Include notification helper
require_once __DIR__ . '/../../api/send_notification.php';

$get_vendor_name = mysqli_query($con, "SELECT * FROM vendor_registration WHERE id='" . mysqli_real_escape_string($con, $vendor_reg_id) . "' LIMIT 1");
$vendor_name = mysqli_fetch_assoc($get_vendor_name)['business_name'] ?? '';
// Load order for details page
$orderIdParam = isset($_GET['id']) ? trim($_GET['id']) : '';
$orderRow = null;
$vendorItems = [];
$orderSubTotal = 0.0;
$orderItemsCount = 0;
$orderShipping = 0.0;
$orderTotal = 0.0;
$orderPaymentMethod = '';
$orderCreatedAt = '';
$orderDisplayNo = '';

// Helper: Format date string, handling both IST (new entries) and UTC (old entries)
function formatTrackingDate($dateString, $orderCreatedAt = null) {
    if (empty($dateString)) return '';
    
    // Try to parse as IST first (for new entries stored with IST timezone)
    try {
        $dt = new DateTime($dateString, new DateTimeZone('Asia/Kolkata'));
        $istTime = $dt->getTimestamp();
        
        // If we have order creation date, check if the tracking date seems reasonable
        // If stored date is before order creation (after accounting for timezone), it might be UTC
        if ($orderCreatedAt) {
            try {
                $orderDt = new DateTime($orderCreatedAt, new DateTimeZone('Asia/Kolkata'));
                $orderTime = $orderDt->getTimestamp();
                
                // If tracking date is more than 12 hours before order creation, likely UTC
                if ($istTime < ($orderTime - 43200)) {
                    // Try parsing as UTC and converting to IST
                    $dtUtc = new DateTime($dateString, new DateTimeZone('UTC'));
                    $dtUtc->setTimezone(new DateTimeZone('Asia/Kolkata'));
                    return $dtUtc->format('d M Y h:i A');
                }
            } catch (Exception $e) {
                // Fall through to IST formatting
            }
        }
        
        return $dt->format('d M Y h:i A');
    } catch (Exception $e) {
        // Fallback: try UTC interpretation
        try {
            $dt = new DateTime($dateString, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            return $dt->format('d M Y h:i A');
        } catch (Exception $e2) {
            return $dateString; // Return as-is if parsing fails
        }
    }
}

// Helper: store one tracking timeline JSON per (order_id + vendor_id)
function addOrderTrackEvent($con, $orderId, $vendorId, $statusLabel)
{
    $orderId = mysqli_real_escape_string($con, (string)$orderId);
    $vendorId = mysqli_real_escape_string($con, (string)$vendorId);
    $selSql = "SELECT id, track_json FROM `order_track` WHERE order_id='" . $orderId . "' AND vendor_id='" . $vendorId . "' AND (product_id IS NULL OR product_id='') LIMIT 1";
    $sel = mysqli_query($con, $selSql);

    // Set timezone to Asia/Kolkata before storing timestamp
    $originalTimezone = date_default_timezone_get();
    date_default_timezone_set('Asia/Kolkata');
    $now = date('Y-m-d H:i:s');
    date_default_timezone_set($originalTimezone); // Restore original timezone
    $entry = ['status' => (string)$statusLabel, 'date' => $now];

    if ($sel && mysqli_num_rows($sel) > 0) {
        $row = mysqli_fetch_assoc($sel);
        $arr = [];
        if (!empty($row['track_json'])) {
            $tmp = json_decode($row['track_json'], true);
            if (is_array($tmp)) {
                $arr = $tmp;
            }
        }
        $arr[] = $entry;
        $json = mysqli_real_escape_string($con, json_encode($arr, JSON_UNESCAPED_SLASHES));
        mysqli_query($con, "UPDATE `order_track` SET track_json='" . $json . "', updated_at='" . $now . "' WHERE id='" . mysqli_real_escape_string($con, (string)$row['id']) . "' LIMIT 1");
    } else {
        $json = mysqli_real_escape_string($con, json_encode([$entry], JSON_UNESCAPED_SLASHES));
        mysqli_query($con, "INSERT INTO `order_track` (order_id, vendor_id, product_id, track_json, updated_at) VALUES ('" . $orderId . "', '" . $vendorId . "', NULL, '" . $json . "', '" . $now . "')");
    }
}

// Helper: Generate unique 8-digit settlement ID
function generateSettlementId($con)
{
    do {
        $settlementId = str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $checkSql = "SELECT id FROM transactions WHERE settlement_id = '" . mysqli_real_escape_string($con, $settlementId) . "' LIMIT 1";
        $checkResult = mysqli_query($con, $checkSql);
    } while ($checkResult && mysqli_num_rows($checkResult) > 0);

    return $settlementId;
}

// Helper: Restore inventory when order is cancelled
function restoreInventoryOnCancel($con, $items, $vendorId)
{
    $restoredItems = [];

    foreach ($items as $item) {
        if (isset($item['vendor_id']) && (string)$item['vendor_id'] === (string)$vendorId) {
            $productId = isset($item['product_id']) ? (int)$item['product_id'] : 0;
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;

            if ($productId > 0 && $quantity > 0) {
                // Get current inventory
                $currentQuery = "SELECT inventory FROM products WHERE id = " . (int)$productId . " AND vendor_id = " . (int)$vendorId . " LIMIT 1";
                $currentResult = mysqli_query($con, $currentQuery);

                if ($currentResult && mysqli_num_rows($currentResult) > 0) {
                    $currentRow = mysqli_fetch_assoc($currentResult);
                    $currentInventory = (int)$currentRow['inventory'];

                    // Add back the cancelled quantity
                    $newInventory = $currentInventory + $quantity;

                    // Update inventory
                    $updateQuery = "UPDATE products SET inventory = " . (int)$newInventory . " WHERE id = " . (int)$productId . " AND vendor_id = " . (int)$vendorId;

                    if (mysqli_query($con, $updateQuery)) {
                        $restoredItems[] = [
                            'product_id' => $productId,
                            'quantity_restored' => $quantity,
                            'previous_inventory' => $currentInventory,
                            'new_inventory' => $newInventory
                        ];
                    }
                }
            }
        }
    }

    return $restoredItems;
}

// Helper: Create transaction record when order is delivered
// Reuse existing pending settlement_id per vendor; otherwise generate once
function createSettlementTransaction($con, $orderId, $vendorId, $vendorName, $customerName, $totalAmount, $platformFee, $gstAmount = 0)
{
    $orderIdEsc = mysqli_real_escape_string($con, $orderId);
    $vendorIdEsc = mysqli_real_escape_string($con, $vendorId);
    $vendorNameEsc = mysqli_real_escape_string($con, $vendorName);
    $customerNameEsc = mysqli_real_escape_string($con, $customerName);
    $amountEsc = mysqli_real_escape_string($con, $totalAmount);
    $platformFeeEsc = mysqli_real_escape_string($con, $platformFee);
    $gstAmountEsc = mysqli_real_escape_string($con, $gstAmount);

    $sel = mysqli_query($con, "SELECT settlement_id FROM transactions WHERE vendor_id='" . $vendorIdEsc . "' AND status='pending' ORDER BY created_at DESC LIMIT 1");
    $settlementId = '';
    if ($sel && mysqli_num_rows($sel) > 0) {
        $r = mysqli_fetch_assoc($sel);
        $settlementId = $r['settlement_id'];
    } else {
        $settlementId = generateSettlementId($con);
    }

    $insertSql = "INSERT INTO transactions (settlement_id, vendor_id, vendor_name, order_id, amount, platform_fee, gst, customer_name, status)
                  VALUES ('" . $settlementId . "', '" . $vendorIdEsc . "', '" . $vendorNameEsc . "', '" . $orderIdEsc . "', '" . $amountEsc . "', '" . $platformFeeEsc . "', '" . $gstAmountEsc . "', '" . $customerNameEsc . "', 'pending')";
    return mysqli_query($con, $insertSql);
}

// Handle confirm / cancel / shipping flow actions for this vendor's items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendor_action']) && $orderIdParam !== '') {
    $action = $_POST['vendor_action'];
    $reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';
    $oid = mysqli_real_escape_string($con, $orderIdParam);
    $rsO = mysqli_query($con, "SELECT products, user_id FROM `order` WHERE order_id='" . $oid . "' LIMIT 1");
    if ($rsO && mysqli_num_rows($rsO) > 0) {
        $rowO = mysqli_fetch_assoc($rsO);
        $items = json_decode($rowO['products'] ?? '[]', true);
        $order_user_id = $rowO['user_id'] ?? 0;
        if (is_array($items)) {
            foreach ($items as &$it) {
                if (isset($it['vendor_id']) && (string)$it['vendor_id'] === (string)$vendor_reg_id) {
                    // Update only pending items
                    $statusLower = strtolower((string)($it['product_status'] ?? ''));
                    if ($action === 'confirm' && $statusLower === 'pending') {
                        $it['product_status'] = 'confirmed';
                    }
                    if ($action === 'cancel' && in_array($statusLower, ['pending', 'confirmed'], true)) {
                        $it['product_status'] = 'cancelled';
                        if ($reason !== '') {
                            $it['cancel_reason'] = $reason;
                        }
                    }
                    if ($action === 'ship' && $statusLower === 'confirmed') {
                        $it['product_status'] = 'shipped';
                    }
                    if ($action === 'out_for_delivery' && $statusLower === 'shipped') {
                        $it['product_status'] = 'out_for_delivery';
                    }
                    if ($action === 'delivered' && in_array($statusLower, ['out_for_delivery', 'shipped'], true)) {
                        $it['product_status'] = 'delivered';
                    }
                }
            }
            unset($it);
            $newJson = mysqli_real_escape_string($con, json_encode($items, JSON_UNESCAPED_SLASHES));
            mysqli_query($con, "UPDATE `order` SET products='" . $newJson . "' WHERE order_id='" . $oid . "' LIMIT 1");

            // Count vendor's items in this order
            $vendor_item_count = 0;
            foreach ($items as $item) {
                if (isset($item['vendor_id']) && (string)$item['vendor_id'] === (string)$vendor_reg_id) {
                    $vendor_item_count++;
                }
            }

            // Record one timeline event per vendor
            if ($action === 'confirm') {
                addOrderTrackEvent($con, $orderIdParam, $vendor_reg_id, 'Processing');
                // Send notification to customer about order confirmation
                sendOrderStatusNotification($con, $orderIdParam, $order_user_id, 'confirmed', 'Your order has been confirmed by the vendor', $vendor_item_count);
            }
            if ($action === 'cancel') {
                addOrderTrackEvent($con, $orderIdParam, $vendor_reg_id, 'Cancelled');
                // Send notification to customer about order cancellation
                sendOrderStatusNotification($con, $orderIdParam, $order_user_id, 'cancelled', 'Your order has been cancelled by the vendor', $vendor_item_count);

                // Restore inventory for cancelled items
                $restoredItems = restoreInventoryOnCancel($con, $items, $vendor_reg_id);

                // Log inventory restoration and set session message
                if (!empty($restoredItems)) {
                    error_log("Inventory restored for cancelled order " . $orderIdParam . ": " . json_encode($restoredItems));
                }

                // Set simple success message
                $_SESSION['order_cancel_success'] = "Order cancelled successfully!";
            }
            if ($action === 'ship') {
                addOrderTrackEvent($con, $orderIdParam, $vendor_reg_id, 'Shipped');
                // Send notification to customer about order shipped
                sendOrderStatusNotification($con, $orderIdParam, $order_user_id, 'shipped', 'Your order has been shipped and is on its way', $vendor_item_count);
            }
            if ($action === 'out_for_delivery') {
                addOrderTrackEvent($con, $orderIdParam, $vendor_reg_id, 'out_for_delivery');
                // Send notification to customer about out for delivery
                sendOrderStatusNotification($con, $orderIdParam, $order_user_id, 'out_for_delivery', 'Your order is out for delivery', $vendor_item_count);
            }
            if ($action === 'delivered') {
                addOrderTrackEvent($con, $orderIdParam, $vendor_reg_id, 'Delivered');
                // Send notification to customer about order delivered
                sendOrderStatusNotification($con, $orderIdParam, $order_user_id, 'delivered', 'Your order has been delivered successfully', $vendor_item_count);

                // Create settlement transaction record
                $vendorTotalAmount = 0.0;
                $totalPlatformFee = 0.0;
                $totalGstAmount = 0.0;
                $customerName = '';

                // Calculate vendor's total amount, platform fees, and GST from this order
                foreach ($items as $item) {
                    if (isset($item['vendor_id']) && (string)$item['vendor_id'] === (string)$vendor_reg_id) {
                        $itemPrice = (float)($item['price'] ?? 0);
                        $itemQty = (int)($item['quantity'] ?? 1);
                        $itemTotal = $itemPrice * $itemQty;
                        $vendorTotalAmount += $itemTotal;

                        // Get product details for platform fee and GST calculation
                        if (isset($item['product_id'])) {
                            $productQuery = mysqli_query($con, "SELECT platform_fee, gst FROM products WHERE id = " . (int)$item['product_id'] . " LIMIT 1");
                            if ($productQuery && mysqli_num_rows($productQuery) > 0) {
                                $productRow = mysqli_fetch_assoc($productQuery);

                                // Calculate platform fee in rupees (if stored as percentage)
                                $platformFeePercent = (float)($productRow['platform_fee'] ?? 0);
                                if ($platformFeePercent > 0) {
                                    $platformFeeAmount = ($itemTotal * $platformFeePercent) / 100;
                                    $totalPlatformFee += $platformFeeAmount;
                                }

                                // Calculate GST in rupees (if stored as percentage)
                                $gstPercent = (float)($productRow['gst'] ?? 0);
                                if ($gstPercent > 0) {
                                    $gstAmount = ($itemTotal * $gstPercent) / 100;
                                    $totalGstAmount += $gstAmount;
                                }
                            }
                        }
                    }
                }

                // Get customer name
                $customerQuery = mysqli_query($con, "SELECT first_name,last_name FROM users WHERE id = " . (int)$order_user_id . " LIMIT 1");
                if ($customerQuery && mysqli_num_rows($customerQuery) > 0) {
                    $customerRow = mysqli_fetch_assoc($customerQuery);
                    $customerName = $customerRow['first_name'] . ' ' . $customerRow['last_name'] ?? 'Unknown Customer';
                }

                // Create transaction record
                createSettlementTransaction($con, $orderIdParam, $vendor_reg_id, $vendor_name, $customerName, $vendorTotalAmount, $totalPlatformFee, $totalGstAmount);
            }

            // If all products have same status, set order_status
            $allStatuses = [];
            foreach ($items as $tmpIt) {
                $allStatuses[] = strtolower((string)($tmpIt['product_status'] ?? ''));
            }
            $allStatuses = array_filter($allStatuses, function ($s) {
                return $s !== '';
            });
            if (!empty($allStatuses) && count(array_unique($allStatuses)) === 1) {
                $common = strtolower((string)reset($allStatuses));
                // Map to ENUM values only (based on database schema)
                $statusMap = [
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'shipped' => 'Shipped',
                    'out_for_delivery' => 'out_for_delivery', // Map to Shipped since Out For Delivery not in ENUM
                    'out for delivery' => 'out_for_delivery', // Map to Shipped since Out For Delivery not in ENUM
                    'delivered' => 'Delivered',
                    'cancelled' => 'Cancelled',
                    'processing' => 'Processing'
                ];
                $mappedStatus = $statusMap[$common] ?? 'Pending';
                $commonEscaped = mysqli_real_escape_string($con, $mappedStatus);
                mysqli_query($con, "UPDATE `order` SET order_status='" . $commonEscaped . "' WHERE order_id='" . $oid . "' LIMIT 1");
            }
        }
    }
    // Redirect to avoid resubmission
    header('Location: order-details.php?id=' . urlencode($orderIdParam));
    exit;
}

if ($orderIdParam !== '') {
    $oid = mysqli_real_escape_string($con, $orderIdParam);
    $rs = mysqli_query($con, "SELECT * FROM `order` WHERE order_id='" . $oid . "' LIMIT 1");
    if ($rs && mysqli_num_rows($rs) > 0) {
        $orderRow = mysqli_fetch_assoc($rs);
        $orderDisplayNo = $orderRow['order_id'] ?? $orderIdParam;
        $orderCreatedAt = $orderRow['created_date'] ?? '';
        $orderPaymentMethod = $orderRow['payment_method'] ?? '';
        $orderShipping = isset($orderRow['shipping_charge']) ? (float)$orderRow['shipping_charge'] : 0.0;

        $items = [];
        if (!empty($orderRow['products'])) {
            $decoded = json_decode($orderRow['products'], true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }

        // Collect cancel reasons for this vendor
        $cancelReasons = [];

        // Filter only this vendor's items
        foreach ($items as $it) {
            if (!isset($it['vendor_id']) || (string)$it['vendor_id'] !== (string)$vendor_reg_id) {
                continue;
            }
            $pid = isset($it['product_id']) ? (int)$it['product_id'] : 0;
            $qty = isset($it['quantity']) ? (int)$it['quantity'] : 0;
            $price = isset($it['price']) ? (float)$it['price'] : 0.0;
            $title = '';
            $image = PUBLIC_ASSETS . 'images/vendor/Detail1s.png';

            if ($pid > 0) {
                $prs = mysqli_query($con, "SELECT product_name, images, sku_id FROM products WHERE id='" . mysqli_real_escape_string($con, (string)$pid) . "' LIMIT 1");
                if ($prs && mysqli_num_rows($prs) > 0) {
                    $prow = mysqli_fetch_assoc($prs);
                    $title = $prow['product_name'] ?? '';
                    if (!empty($prow['images'])) {
                        $imgs = @json_decode($prow['images'], true);
                        if (is_array($imgs) && !empty($imgs[0])) {
                            $image = './' . ltrim($imgs[0], './');
                        }
                    }
                    $sku = $prow['sku_id'] ?? '';
                } else {
                    $sku = '';
                }
            } else {
                $sku = '';
            }

            $lineTotal = $qty * $price;
            $orderSubTotal += $lineTotal;
            $orderItemsCount += $qty;
            // Get selected variants for display
            $selectedVariants = isset($it['selected_variants']) ? $it['selected_variants'] : [];
            $variantText = '';
            $variantHtml = '';
            if (!empty($selectedVariants) && is_array($selectedVariants)) {
                $variantParts = [];
                $variantHtmlParts = [];
                foreach ($selectedVariants as $variantKey => $variantValue) {
                    $variantParts[] = ucfirst($variantKey) . ': ' . $variantValue;

                    // Special handling for color variants
                    if (strtolower($variantKey) === 'color') {
                        $colorValue = strtolower(trim($variantValue));
                        $colorClass = '';
                        $colorStyle = '';

                        // Map common color names to CSS classes or styles
                        switch ($colorValue) {
                            case 'red':
                                $colorClass = 'bg-danger';
                                break;
                            case 'blue':
                                $colorClass = 'bg-primary';
                                break;
                            case 'green':
                                $colorClass = 'bg-success';
                                break;
                            case 'yellow':
                                $colorClass = 'bg-warning';
                                break;
                            case 'black':
                                $colorClass = 'bg-dark';
                                break;
                            case 'white':
                                $colorClass = 'bg-light border';
                                break;
                            case 'orange':
                                $colorStyle = 'background-color: #ff8c00;';
                                break;
                            case 'purple':
                                $colorStyle = 'background-color: #800080;';
                                break;
                            case 'pink':
                                $colorStyle = 'background-color: #ffc0cb;';
                                break;
                            case 'brown':
                                $colorStyle = 'background-color: #8b4513;';
                                break;
                            case 'gray':
                            case 'grey':
                                $colorStyle = 'background-color: #808080;';
                                break;
                            default:
                                // For custom colors, try to use the color name directly
                                $colorStyle = 'background-color: ' . $colorValue . ';';
                        }

                        $colorIndicator = '';
                        if ($colorClass) {
                            $colorIndicator = '<span class="badge ' . $colorClass . ' me-1" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span>';
                        } elseif ($colorStyle) {
                            $colorIndicator = '<span class="badge me-1" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block; ' . $colorStyle . '"></span>';
                        }

                        $variantHtmlParts[] = $colorIndicator . ucfirst($variantKey) . ': ' . $variantValue;
                    } else {
                        $variantHtmlParts[] = ucfirst($variantKey) . ': ' . $variantValue;
                    }
                }
                $variantText = ' (' . implode(', ', $variantParts) . ')';
                $variantHtml = ' (' . implode(', ', $variantHtmlParts) . ')';
            }

            // Capture cancel reason if present for this vendor's cancelled items
            $statusLower = strtolower((string)($it['product_status'] ?? ''));
            $itemCancelReason = isset($it['cancel_reason']) ? trim((string)$it['cancel_reason']) : '';
            if ($statusLower === 'cancelled' && $itemCancelReason !== '') {
                $cancelReasons[] = $itemCancelReason;
            }

            $vendorItems[] = [
                'title' => $title ?: ('#' . $pid),
                'variant_text' => $variantText,
                'variant_html' => $variantHtml,
                'selected_variants' => $selectedVariants,
                'image' => $image,
                'sku' => $sku,
                'price' => $price,
                'qty' => $qty,
                'total' => $lineTotal,
                'product_status' => isset($it['product_status']) ? (string)$it['product_status'] : '',
                'cancel_reason' => $itemCancelReason,
            ];
        }
        $orderTotal = $orderSubTotal + $orderShipping;

        // Expose a single overall cancel reason (first unique) for this vendor's view
        $cancelReasons = array_values(array_unique(array_filter($cancelReasons, function ($r) {
            return $r !== '';
        })));
        $cancelReason = !empty($cancelReasons) ? $cancelReasons[0] : '';
    }
}

// Build Buyer details (safe fallbacks)
$buyerName = 'User #' . ($orderRow['user_id'] ?? '');
$deliveryAddress = '';
if (!empty($orderRow['address_id'])) {
    $aid = mysqli_real_escape_string($con, (string)$orderRow['address_id']);
    $addr_rs = mysqli_query($con, "SELECT * FROM user_address WHERE id='" . $aid . "' LIMIT 1");
    if ($addr_rs && mysqli_num_rows($addr_rs) > 0) {
        $addr = mysqli_fetch_assoc($addr_rs);
        // Resolve state name
        $stateName = '';
        if (!empty($addr['state_id'])) {
            $sid = mysqli_real_escape_string($con, (string)$addr['state_id']);
            $st_rs = mysqli_query($con, "SELECT name FROM state WHERE id='" . $sid . "' LIMIT 1");
            if ($st_rs && mysqli_num_rows($st_rs) > 0) {
                $stateName = (mysqli_fetch_assoc($st_rs)['name'] ?? '');
            }
        }
        $parts = [];
        if (!empty($addr['street_address'])) {
            $parts[] = $addr['street_address'];
        }
        if (!empty($addr['city'])) {
            $parts[] = $addr['city'];
        }
        if (!empty($stateName)) {
            $parts[] = $stateName;
        } elseif (!empty($addr['state_id'])) {
            $parts[] = $addr['state_id'];
        }
        if (!empty($addr['pin_code'])) {
            $parts[] = $addr['pin_code'];
        }
        $deliveryAddress = implode(', ', $parts);
    }
}

// Vendor status flags from their items
$trackDates = [
    'processing' => '',
    'cancelled' => '',
    'shipped' => '',
    'out_for_delivery' => '',
    'delivered' => '',
];
$vendorHasPending = false;
$vendorHasConfirmed = false;
$vendorHasRejected = false;
$vendorHasShipped = false;
$vendorHasOutForDelivery = false;
$vendorHasDelivered = false;
$vendorHasShippedEligible = false;
$vendorAllCancelled = false;
$currentVendorStatus = 'pending';
foreach ($vendorItems as $viTmp) {
    // We need original status; recompute from orderRow products
}
if (!empty($orderRow['products'])) {
    $all = json_decode($orderRow['products'], true);
    if (is_array($all)) {
        $vendorItemCount = 0;
        $cancelCount = 0;
        foreach ($all as $it) {
            if (isset($it['vendor_id']) && (string)$it['vendor_id'] === (string)$vendor_reg_id) {
                $st = strtolower((string)($it['product_status'] ?? ''));
                $vendorItemCount++;
                if ($st === 'pending') {
                    $vendorHasPending = true;
                }
                if ($st === 'confirmed') {
                    $vendorHasConfirmed = true;
                    $vendorHasShippedEligible = true;
                }
                if ($st === 'cancelled') {
                    $vendorHasRejected = true;
                    $cancelCount++;
                }
                if ($st === 'shipped') {
                    $vendorHasShipped = true;
                }
                if ($st === 'out_for_delivery') {
                    $vendorHasOutForDelivery = true;
                }
                if ($st === 'delivered') {
                    $vendorHasDelivered = true;
                }
            }
        }
        if ($vendorItemCount > 0 && $cancelCount === $vendorItemCount) {
            $vendorAllCancelled = true;
        }
        // Determine a single current status for gating the UI
        if ($vendorAllCancelled) {
            $currentVendorStatus = 'cancelled';
        } elseif ($vendorHasDelivered) {
            $currentVendorStatus = 'delivered';
        } elseif ($vendorHasOutForDelivery) {
            $currentVendorStatus = 'out_for_delivery';
        } elseif ($vendorHasShipped) {
            $currentVendorStatus = 'shipped';
        } elseif ($vendorHasConfirmed) {
            $currentVendorStatus = 'confirmed';
        } else {
            $currentVendorStatus = 'pending';
        }
    }
}

// Load tracking JSON for this order+vendor and map to dates
if ($orderRow) {
    $selT = mysqli_query($con, "SELECT track_json FROM `order_track` WHERE order_id='" . mysqli_real_escape_string($con, (string)$orderRow['order_id']) . "' AND vendor_id='" . mysqli_real_escape_string($con, (string)$vendor_reg_id) . "' AND (product_id IS NULL OR product_id='') LIMIT 1");
    if ($selT && mysqli_num_rows($selT) > 0) {
        $rowT = mysqli_fetch_assoc($selT);
        $arrT = json_decode($rowT['track_json'] ?? '[]', true);
        if (is_array($arrT)) {
            foreach ($arrT as $ev) {
                $st = strtolower((string)($ev['status'] ?? ''));
                $dt = (string)($ev['date'] ?? '');
                if ($st === 'processing') {
                    $trackDates['processing'] = $dt;
                }
                if ($st === 'cancelled') {
                    $trackDates['cancelled'] = $dt;
                }
                if ($st === 'shipped') {
                    $trackDates['shipped'] = $dt;
                }
                if ($st === 'out for delivery' || $st === 'out_for_delivery') {
                    $trackDates['out_for_delivery'] = $dt;
                }
                if ($st === 'delivered') {
                    $trackDates['delivered'] = $dt;
                }
            }
        }
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
    <meta http-equiv="hagidy website" content="hagidy website">
    <title>ORDER DETAILS | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords"
        content="hagidy website">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo PUBLIC_ASSETS; ?>images/vendor/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Choices JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>

    <!-- Main Theme Js -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/styles.min.css" rel="stylesheet">

    <!-- Icons Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/icons.css" rel="stylesheet">

    <!-- Node Waves Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.css" rel="stylesheet">

    <!-- Simplebar Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.css" rel="stylesheet">

    <!-- Color Picker Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/themes/nano.min.css">
    <!-- FlatPickr CSS -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>css/vendor/order-details.css">

    <!-- Choices Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/styles/choices.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />


</head>

<body>

    <!-- Loader -->
    <div id="loader">
        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/media/loader.svg" alt="">
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
                    class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb flex-wrap gap-2">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Order Details</h1>
                    <div class="ms-md-1 ms-0">
                        <nav>
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="./order-management.php">Orders</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Order Details</li>
                            </ol>
                        </nav>
                    </div>
                </div>
                <!-- Page Header Close -->

                <!-- Order Cancellation Success Message -->
                <?php if (isset($_SESSION['order_cancel_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert" id="orderCancelAlert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Success!</strong> <?php echo htmlspecialchars($_SESSION['order_cancel_success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['order_cancel_success']); ?>

                    <script>
                        // Auto-dismiss alert after 5 seconds
                        setTimeout(function() {
                            const alert = document.getElementById('orderCancelAlert');
                            if (alert) {
                                const bsAlert = new bootstrap.Alert(alert);
                                bsAlert.close();
                            }
                        }, 5000);
                    </script>
                <?php endif; ?>

                <!-- Start:: row-2 -->
                <div class="row g-3">
                    <div class="col-12 col-xl-6 col-lg-12 col-md-12 col-sm-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title1 mb-0 card-order-details-hea"> Order No - <span
                                        class="spk-color">#<?php echo htmlspecialchars($orderDisplayNo); ?></span> </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table text-nowrap">
                                        <thead>
                                            <tr>
                                                <th scope="col">Item</th>
                                                <th scope="col">SKU</th>
                                                <th scope="col">Price</th>
                                                <th scope="col">Quantity</th>
                                                <th scope="col">Total Price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($vendorItems)): ?>
                                                <?php foreach ($vendorItems as $vi): ?>
                                                    <tr>
                                                        <th>
                                                            <div class="d-flex align-items-center gap-3">
                                                                <div class="detali-img1">
                                                                    <img src="<?php if (!empty($vi['image'])) {
                                                                                    echo $vendor_baseurl . $vi['image'];
                                                                                } else {
                                                                                    echo PUBLIC_ASSETS . 'images/vendor/default.png';
                                                                                } ?>" alt="">
                                                                </div>
                                                                <div class="detail-tab-1">
                                                                    <h4 class="mb-2">
                                                                        <?php echo htmlspecialchars($vi['title']); ?>
                                                                    </h4>
                                                                    <?php if (!empty($vi['variant_html'])): ?>
                                                                        <div class="variant-specs">
                                                                            <?php
                                                                            // Parse the variant HTML to display each variant on a separate line
                                                                            $selectedVariants = isset($vi['selected_variants']) ? $vi['selected_variants'] : [];
                                                                            if (!empty($selectedVariants) && is_array($selectedVariants)):
                                                                            ?>
                                                                                <div class="d-flex flex-wrap">
                                                                                    <?php foreach ($selectedVariants as $variantKey => $variantValue): ?>
                                                                                        <div class="variant-item d-flex align-items-center">
                                                                                            <?php if (strtolower($variantKey) === 'color'): ?>
                                                                                                <?php
                                                                                                $colorValue = strtolower(trim($variantValue));
                                                                                                $colorClass = '';
                                                                                                $colorStyle = '';

                                                                                                switch ($colorValue) {
                                                                                                    case 'red':
                                                                                                        $colorClass = 'bg-danger';
                                                                                                        break;
                                                                                                    case 'blue':
                                                                                                        $colorClass = 'bg-primary';
                                                                                                        break;
                                                                                                    case 'green':
                                                                                                        $colorClass = 'bg-success';
                                                                                                        break;
                                                                                                    case 'yellow':
                                                                                                        $colorClass = 'bg-warning';
                                                                                                        break;
                                                                                                    case 'black':
                                                                                                        $colorClass = 'bg-dark';
                                                                                                        break;
                                                                                                    case 'white':
                                                                                                        $colorClass = 'bg-light border';
                                                                                                        break;
                                                                                                    case 'orange':
                                                                                                        $colorStyle = 'background-color: #ff8c00;';
                                                                                                        break;
                                                                                                    case 'purple':
                                                                                                        $colorStyle = 'background-color: #800080;';
                                                                                                        break;
                                                                                                    case 'pink':
                                                                                                        $colorStyle = 'background-color: #ffc0cb;';
                                                                                                        break;
                                                                                                    case 'brown':
                                                                                                        $colorStyle = 'background-color: #8b4513;';
                                                                                                        break;
                                                                                                    case 'gray':
                                                                                                    case 'grey':
                                                                                                        $colorStyle = 'background-color: #808080;';
                                                                                                        break;
                                                                                                    default:
                                                                                                        $colorStyle = 'background-color: ' . $colorValue . ';';
                                                                                                }

                                                                                                if ($colorClass) {
                                                                                                    echo '<span class="color-indicator ' . $colorClass . '"></span>';
                                                                                                } elseif ($colorStyle) {
                                                                                                    echo '<span class="color-indicator" style="' . $colorStyle . '"></span>';
                                                                                                }
                                                                                                ?>
                                                                                            <?php endif; ?>
                                                                                            <span class="variant-label">
                                                                                                <strong><?php echo ucfirst($variantKey); ?>:</strong>
                                                                                                <span class="variant-value"><?php echo htmlspecialchars($variantValue); ?></span>
                                                                                            </span>
                                                                                        </div>
                                                                                    <?php endforeach; ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </th>
                                                        <td>
                                                            <b><?php echo htmlspecialchars($vi['sku'] ?: '-'); ?></b>
                                                        </td>
                                                        <td>
                                                            <b>₹<?php echo number_format((float)$vi['price'], 2); ?></b>
                                                        </td>
                                                        <td>
                                                            <?php echo (int)$vi['qty']; ?>
                                                        </td>
                                                        <td>
                                                            ₹<?php echo number_format((float)$vi['total'], 2); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">No items for you in this order.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end">
                                    <div class="detail-total-text">
                                        <h4>
                                            Sub Total :
                                        </h4>
                                        <p>
                                            ₹<?php echo number_format((float)$orderSubTotal, 2); ?>
                                        </p>
                                    </div>
                                    <div class="detail-total-text">
                                        <h4>
                                            Shipping Charges :
                                        </h4>
                                        <p class="text-danger">
                                            +₹<?php echo number_format((float)$orderShipping, 2); ?>
                                        </p>
                                    </div>
                                    <div class="detail-total-text">
                                        <h4>
                                            Total Price :
                                        </h4>
                                        <p>
                                            <b> ₹<?php echo number_format((float)$orderTotal, 2); ?></b>
                                        </p>
                                    </div>
                                    <div class="detail-total-text">
                                        <h4>
                                            Total Items :
                                        </h4>
                                        <p>
                                            <?php echo (int)$orderItemsCount; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <button class="btn btn-print" id="btnPrintCart"><i class="fa-solid fa-print"></i> Print</button>
                                </div>
                            </div>

                            <!-- Print Invoice Only -->
                            <div class="print-invoice">
                                <div class="pi-brand">
                                    <div class="print-title"><strong>Hagidy</strong></div>

                                </div>
                                <div class="print-header" style="display:block">
                                    <div class="print-meta">
                                        <div><strong>Order #<?php echo htmlspecialchars($orderDisplayNo); ?></strong></div>
                                        <div>Date: <?php date_default_timezone_set('Asia/Kolkata');
                                                    echo date('d M Y h:i A', strtotime(htmlspecialchars($orderCreatedAt ?: '-'))); ?></div>
                                        <div>Customer: <?php $getname = mysqli_query($con, "SELECT * FROM users WHERE id='" . mysqli_real_escape_string($con, (string)$orderRow['user_id']) . "' LIMIT 1");
                                                        if ($getname && mysqli_num_rows($getname) > 0) {
                                                            $getname_row = mysqli_fetch_assoc($getname);
                                                            echo htmlspecialchars($getname_row['first_name']) . ' ' . htmlspecialchars($getname_row['last_name']);
                                                        } ?></div>
                                        <div>Address: <?php echo htmlspecialchars($deliveryAddress); ?></div>
                                    </div>
                                </div>
                                <div class="pi-title">Items</div>
                                <table class="table pi-table avoid-break">
                                    <thead>
                                        <tr>
                                            <th style="width:40%">Item</th>
                                            <th style="width:12%">SKU</th>
                                            <th style="width:10%" class="text-end">Price</th>
                                            <th style="width:8%" class="text-center">Qty</th>
                                            <th style="width:12%" class="text-end">Total</th>
                                            <th style="width:18%" class="text-start">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($vendorItems)): ?>
                                            <?php foreach ($vendorItems as $vi): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-start">
                                                            <img class="img-fluid" style="width:30px;height:30px;border-radius:6px;margin-right:8px;" src="<?php if ($vi['image'] !== '' || $vi['image'] !== null) {
                                                                                                                                                                echo  $vendor_baseurl . $vi['image'];
                                                                                                                                                            } else {
                                                                                                                                                                echo PUBLIC_ASSETS . 'images/vendor/default.png';
                                                                                                                                                            } ?>">
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($vi['title']); ?></div>
                                                                <?php if (!empty($vi['selected_variants']) && is_array($vi['selected_variants'])): ?>
                                                                    <div class="mt-1">
                                                                        <?php foreach ($vi['selected_variants'] as $variantKey => $variantValue): ?>
                                                                            <div class="d-flex align-items-center mb-1">
                                                                                <?php if (strtolower($variantKey) === 'color'): ?>
                                                                                    <?php
                                                                                    $colorValue = strtolower(trim($variantValue));
                                                                                    $colorClass = '';
                                                                                    $colorStyle = '';

                                                                                    switch ($colorValue) {
                                                                                        case 'red':
                                                                                            $colorClass = 'bg-danger';
                                                                                            break;
                                                                                        case 'blue':
                                                                                            $colorClass = 'bg-primary';
                                                                                            break;
                                                                                        case 'green':
                                                                                            $colorClass = 'bg-success';
                                                                                            break;
                                                                                        case 'yellow':
                                                                                            $colorClass = 'bg-warning';
                                                                                            break;
                                                                                        case 'black':
                                                                                            $colorClass = 'bg-dark';
                                                                                            break;
                                                                                        case 'white':
                                                                                            $colorClass = 'bg-light border';
                                                                                            break;
                                                                                        case 'orange':
                                                                                            $colorStyle = 'background-color: #ff8c00;';
                                                                                            break;
                                                                                        case 'purple':
                                                                                            $colorStyle = 'background-color: #800080;';
                                                                                            break;
                                                                                        case 'pink':
                                                                                            $colorStyle = 'background-color: #ffc0cb;';
                                                                                            break;
                                                                                        case 'brown':
                                                                                            $colorStyle = 'background-color: #8b4513;';
                                                                                            break;
                                                                                        case 'gray':
                                                                                        case 'grey':
                                                                                            $colorStyle = 'background-color: #808080;';
                                                                                            break;
                                                                                        default:
                                                                                            $colorStyle = 'background-color: ' . $colorValue . ';';
                                                                                    }

                                                                                    if ($colorClass) {
                                                                                        echo '<span class="color-indicator ' . $colorClass . ' me-2"></span>';
                                                                                    } elseif ($colorStyle) {
                                                                                        echo '<span class="color-indicator me-2" style="' . $colorStyle . '"></span>';
                                                                                    }
                                                                                    ?>
                                                                                <?php endif; ?>
                                                                                <small class="text-muted">
                                                                                    <strong><?php echo ucfirst($variantKey); ?>:</strong>
                                                                                    <?php echo htmlspecialchars($variantValue); ?>
                                                                                </small>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class=" w-25"><?php echo htmlspecialchars($vi['sku'] ?: '-'); ?></td>
                                                    <td class="text-end">₹<?php echo number_format((float)$vi['price'], 2); ?></td>
                                                    <td class="text-center"><?php echo (int)$vi['qty']; ?></td>
                                                    <td class="text-end">₹<?php echo number_format((float)$vi['total'], 2); ?></td>
                                                    <td class="text-start">
                                                        <?php echo htmlspecialchars(ucfirst(($vi['product_status'] ?? '') !== '' ? $vi['product_status'] : 'Pending')); ?>
                                                        <?php if (strtolower($vi['product_status'] ?? '') === 'cancelled' && !empty($vi['cancel_reason'])): ?>
                                                            <br><small style="font-size: 0.7rem; color: #666;"><strong>Reason:</strong> <?php echo htmlspecialchars($vi['cancel_reason']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No items</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <div class="pi-summary avoid-break">
                                    <table>
                                        <tr>
                                            <td>Sub Total</td>
                                            <td class="text-end">₹<?php echo number_format((float)$orderSubTotal, 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td>Shipping Charges</td>
                                            <td class="text-end">₹<?php echo number_format((float)$orderShipping, 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td>Total</td>
                                            <td class="text-end">₹<?php echo number_format((float)$orderTotal, 2); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div style="margin-top:12px;font-size:12px;color:#555">
                                    Payment Method: <?php if ($orderPaymentMethod == 'cod') {
                                                        echo 'Cash on Delivery';
                                                    } else {
                                                        echo htmlspecialchars($orderPaymentMethod ?: '-');
                                                    } ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-3 col-lg-6 col-md-12 col-sm-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title1 mb-0 card-order-details-hea">Buyer Details </div>
                            </div>
                            <div class="d-flex align-items-center gap-3 border-bottom p-3">
                                <div class="address-text">
                                    <h3>
                                        Customer Name :
                                    </h3>
                                    <h6>

                                        <?php //echo htmlspecialchars($buyerName);
                                        $getname = mysqli_query($con, "SELECT * FROM users WHERE id='" . mysqli_real_escape_string($con, (string)$orderRow['user_id']) . "' LIMIT 1");
                                        if ($getname && mysqli_num_rows($getname) > 0) {
                                            $getname_row = mysqli_fetch_assoc($getname);
                                            echo htmlspecialchars($getname_row['first_name']) . ' ' . htmlspecialchars($getname_row['last_name']);
                                        }
                                        ?>
                                    </h6>
                                </div>
                            </div>
                            <div class="card-body border-bottom">
                                <div class="address-text">
                                    <h3>
                                        Delivery address :
                                    </h3>
                                    <p>
                                        <?php echo htmlspecialchars($deliveryAddress); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="card-body border-bottom">
                                <div class="order-summary">
                                    <!-- <h4>
                                        Order Summary
                                    </h4> -->
                                    <h5>Order Time Stamp : <span><?php date_default_timezone_set('Asia/Kolkata');
                                                                    echo date('d M Y h:i A', strtotime(htmlspecialchars($orderCreatedAt ?: '-'))); ?></span></h5>
                                    <h5>Payment Method : <span><?php if ($orderPaymentMethod == 'cod') {
                                                                    echo 'Cash on Delivery';
                                                                } else {
                                                                    echo htmlspecialchars($orderPaymentMethod ?: '-');
                                                                } ?></span></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-3 col-lg-6 col-md-12 col-sm-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title1 mb-0 card-order-details-hea"> Order Tracking </div>
                                <div class="text-sky-blue">
                                    <!-- #SPK1218153635 -->
                                </div>
                            </div>
                            <div class="card-body ">
                                <div id="one">
                                    <div class="order-detail-rela">
                                        <div class="d-flex align-items-start gap-2">
                                            <div class="order-shipping-img">
                                                <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/order-shipping1.png" alt="">
                                            </div>
                                            <div class="order-placed-text">
                                                <h3>Order Placed</h3>
                                                <h4>
                                                    Order placed successfully by <span>

                                                        <?php //echo htmlspecialchars($buyerName);
                                                        $getname = mysqli_query($con, "SELECT * FROM users WHERE id='" . mysqli_real_escape_string($con, (string)$orderRow['user_id']) . "' LIMIT 1");
                                                        if ($getname && mysqli_num_rows($getname) > 0) {
                                                            $getname_row = mysqli_fetch_assoc($getname);
                                                            echo htmlspecialchars($getname_row['first_name']) . ' ' . htmlspecialchars($getname_row['last_name']);
                                                        }
                                                        ?>
                                                    </span>
                                                </h4>
                                                <p>
                                                    <?php date_default_timezone_set('Asia/Kolkata');
                                                    echo date('d M Y h:i A', strtotime(htmlspecialchars($orderCreatedAt ?: '-'))); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="border-order-plas"></div>
                                    </div>
                                    <?php if ($currentVendorStatus === 'cancelled'): ?>
                                        <div class="order-detail-rela">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="order-shipping-img">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/cancel.png" alt="">
                                                </div>
                                                <div class="order-placed-text">
                                                    <h3>Order Cancelled</h3>
                                                    <h4>Order has been cancelled</h4>

                                                    <span class="text-muted text-sm text-danger">
                                                        Reason :
                                                        <?php if (!empty($cancelReason)): ?>
                                                            <?php echo htmlspecialchars($cancelReason); ?>

                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="border-order-plas"></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="order-detail-rela">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="order-shipping-img">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/order-shipping2.png" alt="">
                                                </div>
                                                <div class="order-placed-text">
                                                    <h3><?php echo ($currentVendorStatus === 'pending') ? 'Order Need To Confirmed' : 'Order Confirmed'; ?></h3>
                                                    <h4>
                                                        <?php if ($currentVendorStatus === 'pending') { ?>
                                                            Please review the order summary and confirm your order, otherwise the order will be cancelled.
                                                        <?php } else { ?>
                                                            Order has been confirmed
                                                        <?php } ?>
                                                        <?php if (!empty($trackDates['processing'])) { ?>
                                                            <br><small class="text-muted"><?php echo formatTrackingDate($trackDates['processing'], $orderCreatedAt); ?></small>
                                                        <?php } ?>
                                                    </h4>
                                                    <?php if ($currentVendorStatus === 'pending'): ?>
                                                        <div class="mt-2">
                                                            <form method="POST" class="d-inline js-action-confirm" data-confirm-message="Are you sure you want to confirm this order?">
                                                                <input type="hidden" name="vendor_action" value="confirm">
                                                                <button class="btn btn-secondary btn-wave waves-effect waves-light" type="submit">Confirm</button>
                                                            </form>
                                                            <button class="btn btn-danger btn-wave waves-effect waves-light" id="cancelOrderBtn" data-bs-toggle="modal" data-bs-target="#addAttributeModal">Cancel</button>
                                                            <div class="modal fade" id="addAttributeModal" tabindex="-1" aria-labelledby="addAttributeModalLabel" aria-hidden="true">
                                                                <div class="modal-dialog modal-sm modal-dialog-centered">
                                                                    <div class="modal-content add-attribute-modal-content">
                                                                        <div class="modal-header border-0 pb-0">
                                                                            <h4 class="modal-title w-100 text-center fw-bold" id="addAttributeModalLabel">Add Reason</h4>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body pt-3">
                                                                            <form method="POST">
                                                                                <input type="hidden" name="vendor_action" value="cancel">
                                                                                <div class="mb-3">
                                                                                    <label for="variant" class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                                                                                    <textarea class="form-control form-control-lg" name="cancel_reason" id="variant" placeholder=" Enter Reason" required></textarea>
                                                                                </div>
                                                                                <button type="submit" class="save-attribute-btn w-100">Submit</button>
                                                                            </form>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="border-order-plas"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div id="two">
                                    <div class="order-detail-rela">

                                        <div class="border-order-plas"></div>
                                    </div>
                                    <?php if ($currentVendorStatus === 'confirmed'): ?>
                                        <div class="order-detail-rela">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="order-shipping-img">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/order-shipping6.png" alt="">
                                                </div>
                                                <div class="order-placed-text">
                                                    <h3>Ready to Ship</h3>
                                                    <h4>
                                                        Please update the status once the order has been Shipped.
                                                    </h4>

                                                    <div>
                                                        <form method="POST" class="js-action-confirm" data-confirm-message="Mark this order as Shipped?">
                                                            <input type="hidden" name="vendor_action" value="ship">
                                                            <button class="btn btn-secondary btn-wave waves-effect waves-light mt-2" type="submit">Order Shipping</button>
                                                        </form>
                                                    </div>

                                                </div>
                                                <div class="border-order-plas"></div>
                                            </div>
                                        </div>
                                </div>
                            <?php endif; ?>
                            <?php if (in_array($currentVendorStatus, ['shipped', 'out_for_delivery', 'delivered'], true)): ?>
                                <div id="three">
                                    <div class="order-detail-rela">
                                        <div class="d-flex align-items-start gap-2">
                                            <div class="order-shipping-img">
                                                <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/order-shipping6.png" alt="">
                                            </div>
                                            <div class="order-placed-text">
                                                <h3>Shipped</h3>
                                                <h4>
                                                    Your order has been shipped
                                                    <?php if (!empty($trackDates['shipped'])) { ?>
                                                        <br><small class="text-muted"><?php echo formatTrackingDate($trackDates['shipped'], $orderCreatedAt); ?></small>
                                                    <?php } ?>
                                                </h4>
                                                <div>
                                                    <?php if ($currentVendorStatus === 'shipped'): ?>
                                                        <form method="POST" class="js-action-confirm" data-confirm-message="Mark this order as Out For Delivery?">
                                                            <input type="hidden" name="vendor_action" value="out_for_delivery">
                                                            <button class="btn btn-secondary btn-wave waves-effect waves-light mt-2" type="submit">Mark Out For Delivery</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-order-plas"></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (in_array($currentVendorStatus, ['out_for_delivery', 'delivered'], true)): ?>
                                    <div class="order-detail-rela">
                                        <div class="d-flex align-items-start gap-2">
                                            <div class="order-shipping-img">
                                                <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/order-shipping4.png" alt="">
                                            </div>
                                            <div class="order-placed-text">
                                                <h3>Out For Delivery</h3>
                                                <h4>
                                                    Your order is out for delivery
                                                    <?php if (!empty($trackDates['out_for_delivery'])) { ?>
                                                        <br><small class="text-muted"><?php echo formatTrackingDate($trackDates['out_for_delivery'], $orderCreatedAt); ?></small>
                                                    <?php } ?>
                                                </h4>
                                                <div>
                                                    <?php if ($currentVendorStatus === 'out_for_delivery'): ?>
                                                        <form method="POST" class="js-action-confirm" data-confirm-message="Mark this order as Delivered?">
                                                            <input type="hidden" name="vendor_action" value="delivered">
                                                            <button class="btn btn-secondary btn-wave waves-effect waves-light mt-2" type="submit">Mark Delivered</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-order-plas"></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($currentVendorStatus === 'delivered'): ?>
                                    <div class="order-detail-rela mb-0">
                                        <div class="d-flex align-items-start gap-2">
                                            <div class="order-shipping-img">
                                                <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/order-shipping4.png" alt="">
                                            </div>
                                            <div class="order-placed-text">
                                                <h3>Delivered</h3>
                                                <h4>
                                                    The order has been delivered.
                                                    <?php if (!empty($trackDates['delivered'])) { ?>
                                                        <br><small class="text-muted"><?php echo formatTrackingDate($trackDates['delivered'], $orderCreatedAt); ?></small>
                                                    <?php } ?>
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End:: row-2 -->
        </div>
        <!-- End::app-content -->


        <!-- Footer Start -->
        <footer class="footer mt-auto py-3 bg-white text-center">
            <div class="container">
                <span class="text-muted"> Copyright © 2025 <span id="year"></span> <a href="#"
                        class="text-primary fw-semibold"> Hagidy </a>.
                    Designed with <span class="bi bi-heart-fill text-danger"></span> by <a href="javascript:void(0);">
                        <span class="fw-semibold text-sky-blue text-decoration-underline">Mechodal Technology
                        </span>
                    </a>
                </span>
            </div>
        </footer>
        <!-- Footer End -->
    </div>

    <!-- Print Invoice Only -->


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
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/defaultmenu.min.js"></script>

    <!-- Node Waves JS-->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.js"></script>

    <!-- Sticky JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/sticky.js"></script>

    <!-- Simplebar JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/simplebar.js"></script>

    <!-- Color Picker JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/pickr.es5.min.js"></script>

    <!-- Apex Charts JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/apexcharts/apexcharts.min.js"></script>

    <!-- Ecommerce-Dashboard JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/ecommerce-dashboard.js"></script>

    <!-- Custom-Switcher JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom-switcher.min.js"></script>

    <!-- Date & Time Picker JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/date&time_pickers.js"></script>

    <!-- Custom JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom.js"></script>


    <div class="modal fade" id="actionConfirmModal" tabindex="-1" aria-labelledby="actionConfirmLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                <div class="modal-body text-center p-4">
                    <!-- Icon -->
                    <div class="mb-3">
                        <div style="width: 60px; height: 60px; background-color:green; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <i class="fas fa-check" style="color: white; font-size: 24px;"></i>
                        </div>
                    </div>

                    <!-- Message -->
                    <div class="modal-body">
                        <p id="actionConfirmMessage">Are you sure?</p>
                    </div>

                    <!-- Buttons -->
                    <div class="d-flex gap-3 justify-content-center">
                        <button type="button" class="btn btn-outline-danger" id="cancelNoBtn" data-bs-dismiss="modal" style="  border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                            No
                        </button>
                        <button type="button" class="btn btn-primary" id="actionConfirmYes" style="background-color:green; border-color:green; border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                            Yes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal JavaScript -->
    <script>
        // Open the correct cancel-reason modal without alerts
        document.addEventListener('DOMContentLoaded', function() {
            var cancelBtn = document.getElementById('cancelOrderBtn');
            var reasonModalEl = document.getElementById('addAttributeModal');
            if (cancelBtn && reasonModalEl && window.bootstrap && bootstrap.Modal) {
                var reasonModal = new bootstrap.Modal(reasonModalEl);
                cancelBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    reasonModal.show();
                });
            }
        });

        // Print functionality
        var btnPrintCart = document.getElementById('btnPrintCart');
        if (btnPrintCart) {
            btnPrintCart.addEventListener('click', function() {
                window.print();
            });
        }

        // Action confirmation flow for Confirm/Ship/OFD/Delivered
        document.addEventListener('DOMContentLoaded', function() {
            const modalEl = document.getElementById('actionConfirmModal');
            const modal = new bootstrap.Modal(modalEl);
            const msgEl = document.getElementById('actionConfirmMessage');
            const yesBtn = document.getElementById('actionConfirmYes');
            let pendingForm = null;

            document.querySelectorAll('form.js-action-confirm').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    pendingForm = form;
                    const msg = form.getAttribute('data-confirm-message') || 'Are you sure?';
                    msgEl.textContent = msg;
                    modal.show();
                });
            });

            yesBtn.addEventListener('click', function() {
                if (pendingForm) {
                    modal.hide();
                    // Small delay to allow modal to close animation
                    setTimeout(function() {
                        pendingForm.submit();
                        pendingForm = null;
                    }, 150);
                }
            });
        });
    </script>

</body>

</html>