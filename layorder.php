<?php

const PREP_BASE_MIN        = 15; 
const PREP_ITEMS_PER_BATCH = 5;  
const PREP_PER_BATCH_MIN   = 5;  
const PREP_MAX_MIN         = 45; 
function update_and_get_order_status(array $row, mysqli $conn): array
{
    if (empty($row['created_at'])) {
        return $row;
    }

    if ($row['status'] === 'delivered' || $row['status'] === 'cancelled') {
        return $row;
    }

    $orderCreatedAt = new DateTime($row['created_at']);
    $now            = new DateTime();

    $prep = (int)($row['prep_minutes'] ?? 20);
    $ship = (int)($row['delivery_minutes'] ?? 20);

    $elapsedMinutes = ($now->getTimestamp() - $orderCreatedAt->getTimestamp()) / 60;

    $newStatus = null;

    if ($row['status'] === 'delivering' && $elapsedMinutes >= ($prep + $ship)) {
        $newStatus = 'delivered';
    }
    elseif ($row['status'] === 'preparing' && $elapsedMinutes >= $prep) {
        $newStatus = 'delivering';
    }

    if ($newStatus && $newStatus !== $row['status']) {
        $sqlU = "UPDATE orders SET status = ? WHERE order_id = ?";
        $stmtU = mysqli_prepare($conn, $sqlU);
        mysqli_stmt_bind_param($stmtU, "si", $newStatus, $row['order_id']);
        mysqli_stmt_execute($stmtU);
        mysqli_stmt_close($stmtU);
        $row['status'] = $newStatus;
    }

    return $row;
}

function calculate_shipping_fee(float $distanceKm): int {
    $baseFee = 20000; 
    if ($distanceKm <= 5) {
        return $baseFee;
    }
    $extraDistance = max(0, $distanceKm - 5);
    $extraBlocks   = ceil($extraDistance / 5); 
    $extraFee      = $extraBlocks * 5000;
    return $baseFee + $extraFee;
}

function estimate_prep_minutes(int $itemsCount): int {
    if ($itemsCount <= 0) {
        return PREP_BASE_MIN;
    }

    $batches = (int) ceil($itemsCount / PREP_ITEMS_PER_BATCH);
    $minutes = PREP_BASE_MIN + ($batches - 1) * PREP_PER_BATCH_MIN;

    return min($minutes, PREP_MAX_MIN);
}

function estimate_delivery_minutes(float $distanceKm): int {
    if ($distanceKm <= 3)  return 10;
    if ($distanceKm <= 7)  return 15;
    if ($distanceKm <= 12) return 20;
    return 25; 
}

function calculate_eta_from_minutes(int $totalMinutes): DateTime {
    $now = new DateTime(); 
    $eta = clone $now;
    $eta->add(new DateInterval('PT' . $totalMinutes . 'M'));
    return $eta;
}