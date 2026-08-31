<?php
/**
 * Dashboard Trends API
 * --------------------
 * Returns real trend data from the database across all 12 months of the
 * current calendar year. Months with no records are left as `null` (nothing),
 * so the chart shows no value/fake zero for empty months.
 *
 *   - inventory:  net real stock movement (Stock In - Stock Out) per month,
 *                 from stock_movements.
 *   - waste:      total waste quantity per month, from waste_records.
 *   - production: number of production jobs per month, from production_jobs
 *                 (grouped by approved date, falling back to due date).
 *
 * Output: JSON
 *   {
 *     "categories": ["Jan","Feb",...,"Dec"],
 *     "inventory":  [null,null,...,85,...],
 *     "waste":      [null,null,...,10,...],
 *     "production": [null,null,...,4,...]
 *   }
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!$connect || $connect->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// ---- Build the full calendar year (Jan - Dec of current year) ---------------
$year = (int) date('Y');
$categories = [];
$keyToIndex = [];
for ($m = 1; $m <= 12; $m++) {
    $categories[] = date('M', mktime(0, 0, 0, $m, 1, $year));
    $keyToIndex[sprintf('%04d-%02d', $year, $m)] = $m - 1;
}

// Start with "nothing" (null) for every month.
$inventory = array_fill(0, 12, null);
$waste = array_fill(0, 12, null);
$production = array_fill(0, 12, null);

// ---- Inventory: net real stock movement per month ---------------------------
// Only genuine inbound/outbound transactions are real movement. Rows like
// Stock Adjustment, Stock Count, Stock Transfer and Reserved store a *target*
// quantity rather than a delta, so they are excluded to keep figures accurate.
$res = $connect->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(quantity) AS net
     FROM stock_movements
     WHERE transaction_type IN ('Stock In', 'Stock Out')
     GROUP BY ym"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $ym = $row['ym'];
        if (isset($keyToIndex[$ym])) {
            $inventory[$keyToIndex[$ym]] = round((float) $row['net']);
        }
    }
}

// ---- Waste: total quantity per month from waste_records ---------------------
$wres = $connect->query(
    "SELECT DATE_FORMAT(record_date, '%Y-%m') AS ym, SUM(quantity) AS qty
     FROM waste_records
     GROUP BY ym"
);
if ($wres) {
    while ($row = $wres->fetch_assoc()) {
        $ym = $row['ym'];
        if (isset($keyToIndex[$ym])) {
            $waste[$keyToIndex[$ym]] = round((float) $row['qty']);
        }
    }
}

// ---- Production: number of production jobs per month -------------------------
// Jobs are grouped by their approved date; jobs that were never approved fall
// back to their due date so no real job is left out of the trend.
$pres = $connect->query(
    "SELECT DATE_FORMAT(COALESCE(approved_at, due_date), '%Y-%m') AS ym, COUNT(*) AS total
     FROM production_jobs
     GROUP BY ym"
);
if ($pres) {
    while ($row = $pres->fetch_assoc()) {
        $ym = $row['ym'];
        if (isset($keyToIndex[$ym])) {
            $production[$keyToIndex[$ym]] = (int) $row['total'];
        }
    }
}

echo json_encode([
    'categories' => $categories,
    'inventory'  => $inventory,
    'waste'      => $waste,
    'production' => $production,
]);