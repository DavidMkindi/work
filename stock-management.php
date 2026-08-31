<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

require_once 'backend/config.php';
require_once 'backend/stock_helpers.php';

$message = '';
$messageType = '';

$displayName = isset($_SESSION['user_name']) && $_SESSION['user_name'] !== '' ? $_SESSION['user_name'] : 'User';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($connect) && !$connect->connect_error) {
    $action = trim($_POST['action'] ?? '');
    $item = trim($_POST['item'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $qty = (int) ($_POST['quantity'] ?? 0);

    try {
        switch ($action) {
            case 'stock_in':
                if ($item === '' || $location === '' || $qty <= 0) {
                    throw new RuntimeException('Please provide an item, location and a quantity greater than zero.');
                }

                stockIn($connect, $item, $location, $qty, $displayName);
                $message = "Stock In recorded: {$qty} × {$item} added to {$location}.";
                $messageType = 'success';
                break;

            case 'stock_out':
                if ($item === '' || $location === '') {
                    throw new RuntimeException('Please provide an item and a location.');
                }
                if (!stockDelete($connect, $item, $location, $displayName)) {
                    throw new RuntimeException('No stock record found for that item in the selected warehouse.');
                }
                $message = "Stock Out recorded: {$item} removed from {$location}.";
                $messageType = 'success';
                break;

            case 'stock_adjust':
                if ($item === '' || $location === '' || $qty < 0) {
                    throw new RuntimeException('Please provide an item, location and a non-negative target quantity.');
                }
                $result = stockAdjust($connect, $item, $location, $qty, $displayName);
                $message = $result['changed']
                    ? "Stock adjusted for {$item} at {$location} from {$result['current']} to {$qty}."
                    : 'No change made; target quantity matches current stock.';
                $messageType = $result['changed'] ? 'success' : 'info';
                break;

            case 'stock_transfer':
                $fromLocation = trim($_POST['from_location'] ?? '');
                $toLocation = trim($_POST['to_location'] ?? '');
                if ($item === '' || $fromLocation === '' || $toLocation === '' || $qty <= 0) {
                    throw new RuntimeException('Please provide an item, both locations and a quantity greater than zero.');
                }
                if (!stockTransfer($connect, $item, $fromLocation, $toLocation, $qty, $displayName)) {
                    throw new RuntimeException('Transfer failed: locations must differ and stock on hand must be sufficient.');
                }
                $message = "Transferred {$qty} × {$item} from {$fromLocation} to {$toLocation}.";
                $messageType = 'success';
                break;

            default:
                throw new RuntimeException('Unknown stock operation.');
        }
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }

    if ($messageType === 'success') {
        stockNotifyLowStock($connect);
        header('Location: stock-management.php');
        exit();
    }
}

$stock = [];
$movements = [];
if (isset($connect) && !$connect->connect_error) {
    $result = $connect->query('SELECT * FROM stock ORDER BY item ASC, location ASC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stock[] = $row;
        }
    }

    $result = $connect->query('SELECT * FROM stock_movements ORDER BY created_at DESC, id DESC LIMIT 200');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $movements[] = $row;
        }
    }
}

$warehouses = [];
if (isset($connect) && !$connect->connect_error) {
    $result = $connect->query('SELECT id, name FROM warehouses ORDER BY name ASC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $warehouses[] = $row;
        }
    }
}

$items = [];
$locations = [];
$stockLookup = [];
foreach ($stock as $row) {
    $items[$row['item']] = $row['item'];
    $locations[$row['location']] = $row['location'];
    $stockLookup[$row['item'] . '|' . $row['location']] = $row;
}

ksort($items);
ksort($locations);

$totalItems = count($items);
$totalUnits = 0;
$totalLocations = count($locations);
foreach ($stock as $row) {
    $totalUnits += (int) $row['quantity'];
}
$totalMovements = count($movements);
$lowStockCount = 0;
foreach ($stock as $row) {
    if ((int) $row['quantity'] < 10) {
        $lowStockCount++;
    }
}

function userInitials(string $name): string {
    $name = trim($name);
    if ($name === '') {
        return 'U';
    }
    $parts = preg_split('/\s+/', $name);
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $initials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    }
    return $initials;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Stock Management | PICS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description">
    <meta content="Themesdesign" name="author">

    <link rel="icon" type="image/svg+xml" href="./assets/images/pics-logo.svg">

    <script>
        (function () {
            const html = document.documentElement;
            const storageKey = "__TAILWICK_CONFIG__";
            const savedConfig = sessionStorage.getItem(storageKey);
            const defaultConfig = { dir: "ltr", theme: "light", sidenav: { color: "light", size: "default" } };

            function getSystemTheme() {
                return window.matchMedia('(prefers-color-scheme: dark)').matches ? "dark" : "light";
            }

            const htmlConfig = {
                dir: html.getAttribute("dir") || defaultConfig.dir,
                theme: html.getAttribute("data-theme") === 'system'
                    ? getSystemTheme()
                    : html.getAttribute("data-theme") || (defaultConfig.theme === 'system' ? getSystemTheme() : defaultConfig.theme),
                sidenav: {
                    color: html.getAttribute("data-sidenav-color") || defaultConfig.sidenav.color,
                    size: html.getAttribute("data-sidenav-size") || defaultConfig.sidenav.size,
                },
            };

            window.defaultConfig = structuredClone(htmlConfig);
            let config = savedConfig ? JSON.parse(savedConfig) : htmlConfig;
            window.config = config;

            html.setAttribute("dir", config.dir);
            html.setAttribute("data-theme", config.theme);
            html.setAttribute("data-sidenav-color", config.sidenav.color);

            if (config.sidenav.size) {
                let size = config.sidenav.size;
                if (window.innerWidth <= 1140) {
                    size = "offcanvas";
                }
                html.setAttribute("data-sidenav-size", size);
            }
        })();
    </script>

    <script defer src="./assets/js/apexcharts.min.js"></script>
    <script defer src="./assets/js/lucide.min.js"></script>
    <script defer src="./assets/js/app.js"></script>

    <link rel="stylesheet" href="./assets/css/style.css">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
    </style>
</head>

<body>

    <div class="wrapper">

        <!-- Start Sidebar -->
        <?php require __DIR__ . '/backend/sidebar.php'; ?>
        <!-- End Sidebar -->
        <!-- Start Page Content here -->
        <div class="page-content">

            <!-- Topbar Start -->
            <div class="app-header min-h-topbar-height flex items-center sticky top-0 z-30 bg-(--topbar-background) border-b border-default-200">
                <div class="w-full flex items-center justify-between px-6">
                    <div class="flex items-center gap-5">
                        <button id="button-toggle-menu" class="btn btn-icon size-8 hover:bg-default-150 rounded">
                            <i class="iconify lucide--align-left text-xl"></i>
                        </button>


                    </div>

                    <div class="flex items-center gap-3">

                        <?php require __DIR__ . '/backend/notifications_dropdown.php'; ?>

                        <div class="topbar-item">
                            <button class="btn btn-icon size-8 hover:bg-default-150 transition-[scale,background] rounded-full" id="light-dark-mode" type="button">
                                <i class="iconify tabler--moon text-xl absolute dark:scale-0 dark:-rotate-90 scale-100 rotate-0 transition-all duration-200"></i>
                                <i class="iconify tabler--sun text-xl absolute dark:scale-100 dark:rotate-0 scale-0 rotate-90 transition-all duration-200"></i>
                            </button>
                        </div>



<div class="topbar-item hs-dropdown relative inline-flex">
                            <button class="cursor-pointer bg-primary/10 rounded-full" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                <span class="hs-dropdown-toggle size-9.5 rounded-full flex items-center justify-center text-sm font-semibold text-primary"><?= htmlspecialchars(strtoupper(mb_substr($displayName, 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                            </button>
                            <div class="hs-dropdown-menu min-w-48" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-with-icons">
                                <div class="p-2">
                                    <h6 class="mb-2 text-default-500">Signed in as</h6>
                                    <div class="flex gap-3">
                                        <div class="rounded bg-primary/10 size-12 flex items-center justify-center font-semibold text-primary">
                                            <?= htmlspecialchars(strtoupper(mb_substr($displayName, 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 text-sm font-semibold text-default-800"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h6>
                                            <p class="text-default-500"><?= htmlspecialchars($_SESSION['user_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="border-t border-t-default-200 -mx-2 my-2"></div>
                                <div class="flex flex-col gap-y-1">
                                    <a class="flex items-center gap-x-3.5 py-1.5 font-medium px-3 text-default-600 hover:bg-default-150 rounded" href="backend/authentication/logout.php">
                                        <i data-lucide="log-out" class="size-4"></i>
                                        Sign Out
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Topbar End -->
            <main>

                <!-- Page Title Start -->
                <div class="flex items-center md:justify-between flex-wrap gap-2 mb-4 print:hidden">
                    <h4 class="text-default-900 text-lg font-semibold">Stock Management</h4>

                    <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                        <a href="index.php" class="text-sm font-medium text-default-700">PICS</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700">Inventory</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700" aria-current="page">Stock Management</a>
                    </div>
                </div>
                <!-- Page Title End -->

                <?php if ($message !== ''): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards Start -->
                <div class="grid lg:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-5 mb-5">
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Total Items</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $totalItems ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <i data-lucide="boxes" class="size-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Locations</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $totalLocations ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-warning/10 flex items-center justify-center">
                                    <i data-lucide="map-pin" class="size-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Low Stocks</p>
                                    <h4 class="text-xl font-semibold <?= $lowStockCount > 0 ? 'text-danger' : 'text-default-900' ?> mt-1.5"><?= $lowStockCount ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-danger/10 flex items-center justify-center">
                                    <i data-lucide="alert-triangle" class="size-5 text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Stats Cards End -->

                <!-- Stock Operations Start -->
                <div class="card mb-5">
                    <div class="card-header">
                        <h6 class="card-title">Stock Operations</h6>
                    </div>

                    <div class="card-header">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-primary stock-tab" data-form="form-stock-in">Stock In</button>
                            <button type="button" class="btn btn-sm bg-transparent border border-dashed border-default-300 text-default-600 hover:bg-default-150 stock-tab" data-form="form-stock-out">Stock Out</button>
                            <button type="button" class="btn btn-sm bg-transparent border border-dashed border-default-300 text-default-600 hover:bg-default-150 stock-tab" data-form="form-adjust">Adjust</button>
                            <button type="button" class="btn btn-sm bg-transparent border border-dashed border-default-300 text-default-600 hover:bg-default-150 stock-tab" data-form="form-count">Stock Count</button>
                            <button type="button" class="btn btn-sm bg-transparent border border-dashed border-default-300 text-default-600 hover:bg-default-150 stock-tab" data-form="form-transfer">Transfer</button>
                        </div>
                    </div>

                    <form method="POST" action="stock-management.php" id="form-stock-in" class="stock-form">
                        <input type="hidden" name="action" value="stock_in">
                        <div class="card-body grid lg:grid-cols-4 md:grid-cols-2 grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Item <span class="text-danger">*</span></label>
                                <input type="text" name="item" class="form-input" placeholder="Item name" autocomplete="off" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Location <span class="text-danger">*</span></label>
                                <select name="location" class="form-input" required>
                                    <option value="">Select warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <option value="<?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="form-input" min="1" placeholder="0" required>
                            </div>
                            <div class="flex items-end justify-end">
                                <button type="submit" class="btn btn-primary w-full">
                                    <i data-lucide="arrow-down-to-line" class="size-4"></i>
                                    Stock In
                                </button>
                            </div>
                        </div>
                    </form>

                    <form method="POST" action="stock-management.php" id="form-stock-out" class="stock-form hidden">
                        <input type="hidden" name="action" value="stock_out">
                        <div class="card-body grid lg:grid-cols-4 md:grid-cols-2 grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Location <span class="text-danger">*</span></label>
                                <select id="stockout-location" name="location" class="form-input" required>
                                    <option value="">Select warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <option value="<?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Item <span class="text-danger">*</span></label>
                                <select id="stockout-item" name="item" class="form-input" required>
                                    <option value="">Select warehouse first</option>
                                </select>
                            </div>
                            <div class="flex items-end justify-end">
                                <button type="submit" class="btn btn-danger w-full">
                                    <i data-lucide="trash-2" class="size-4"></i>
                                    Remove from Stock
                                </button>
                            </div>
                        </div>
                    </form>

                    <form method="POST" action="stock-management.php" id="form-adjust" class="stock-form hidden">
                        <input type="hidden" name="action" value="stock_adjust">
                        <div class="card-body grid lg:grid-cols-4 md:grid-cols-2 grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Location <span class="text-danger">*</span></label>
                                <select id="adjust-location" name="location" class="form-input" required>
                                    <option value="">Select warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <option value="<?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Item <span class="text-danger">*</span></label>
                                <select id="adjust-item" name="item" class="form-input" required>
                                    <option value="">Select warehouse first</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Target Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="form-input" min="0" placeholder="0" required>
                            </div>
                            <div class="flex items-end justify-end">
                                <button type="submit" class="btn btn-primary w-full">
                                    <i data-lucide="sliders-horizontal" class="size-4"></i>
                                    Adjust
                                </button>
                            </div>
                        </div>
                    </form>

                    <div id="form-count" class="stock-form hidden">
                        <div class="card-body grid lg:grid-cols-4 md:grid-cols-2 grid-cols-1 gap-4 items-end">
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Item <span class="text-danger">*</span></label>
                                <select id="count-item" class="form-input" required>
                                    <option value="">Select item</option>
                                    <?php foreach ($items as $itemName): ?>
                                        <option value="<?= htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Location <span class="text-danger">*</span></label>
                                <select id="count-location" class="form-input" required>
                                    <option value="">Select warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <option value="<?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Hint</label>
                                <p class="text-sm text-default-500">Select an item and location, then press <span class="font-semibold text-default-800">Count Stock</span> to check the on-hand quantity.</p>
                            </div>
                            <div>
                                <button type="button" id="count-button" class="btn btn-primary w-full">
                                    <i data-lucide="clipboard-list" class="size-4"></i>
                                    Count Stock
                                </button>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="stock-management.php" id="form-transfer" class="stock-form hidden">
                        <input type="hidden" name="action" value="stock_transfer">
                        <div class="card-body grid lg:grid-cols-4 md:grid-cols-2 grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Item <span class="text-danger">*</span></label>
                                <input type="text" name="item" class="form-input" placeholder="Item name" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">From Location <span class="text-danger">*</span></label>
                                <select name="from_location" class="form-input" required>
                                    <option value="">Select warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <option value="<?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">To Location <span class="text-danger">*</span></label>
                                <select name="to_location" class="form-input" required>
                                    <option value="">Select warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <option value="<?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="form-input" min="1" placeholder="0" required>
                            </div>
                            <div class="flex items-end justify-end">
                                <button type="submit" class="btn btn-primary w-full">
                                    <i data-lucide="move-right" class="size-4"></i>
                                    Transfer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- Stock Operations End -->

                <!-- Stock Levels Table Start -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title">Stock Levels</h6>
                    </div>

                    <div class="card-header">
                        <div class="md:flex items-center md:space-y-0 space-y-4 gap-3">
                            <div class="relative">
                                <input type="search" id="stock-search" class="form-input form-input-sm ps-9" placeholder="Search item or location">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3">
                                    <i data-lucide="search" class="size-3.5 flex items-center text-default-500 fill-default-100"></i>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2 items-center flex-wrap">
                            <span class="text-sm text-default-500"><?= count($stock) ?> stock line<?= count($stock) === 1 ? '' : 's' ?> found</span>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <div class="overflow-x-auto">
                            <div class="min-w-full inline-block align-middle">
                                <div class="overflow-hidden">
                                    <table id="stock-table" class="min-w-full divide-y divide-default-200">
                                        <thead class="bg-default-150">
                                            <tr class="text-sm font-normal text-default-700">
                                                <th scope="col" class="px-3.5 py-3 text-start">Item</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Location</th>
                                                <th scope="col" class="px-3.5 py-3 text-end">Quantity</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Last Updated</th>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-default-100">
                                            <?php if (empty($stock)): ?>
                                                <tr>
                                                    <td colspan="4" class="px-3.5 py-16 text-center text-default-500">
                                                        <i data-lucide="warehouse" class="size-10 mx-auto mb-3 text-default-300"></i>
                                                        <p class="font-medium">No stock records yet. Use an operation above to add stock.</p>
                                                    </td>
                                                </tr>
                                            <?php else: foreach ($stock as $row): ?>
                                                <tr class="text-default-800 font-normal text-sm">
                                                    <td class="px-3.5 py-3 text-sm text-primary font-medium"><?= htmlspecialchars($row['item'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-800 font-medium"><?= htmlspecialchars($row['location'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-end <?= (int) $row['quantity'] <= 0 ? 'text-danger font-semibold' : 'text-default-800' ?>"><?= (int) $row['quantity'] ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars(date('M d, Y H:i', strtotime($row['updated_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Stock Levels Table End -->

            </main>

            <!-- Footer Start -->
            <footer class="mt-auto footer flex items-center py-5 border-t border-default-200">
<div class="lg:px-8 px-6 w-full flex md:justify-between justify-center gap-4">
                </div>
            </footer>
            <!-- Footer End -->
        </div>
    </div>

    <script>
        const stockLookup = <?= json_encode($stockLookup, JSON_UNESCAPED_UNICODE) ?>;
        (function () {
            const tabButtons = document.querySelectorAll('.stock-tab');
            const forms = document.querySelectorAll('.stock-form');
            tabButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    tabButtons.forEach(function (b) {
                        b.classList.remove('btn-primary');
                        b.classList.add('bg-transparent', 'border', 'border-dashed', 'border-default-300', 'text-default-600', 'hover:bg-default-150');
                    });
                    btn.classList.remove('bg-transparent', 'border', 'border-dashed', 'border-default-300', 'text-default-600', 'hover:bg-default-150');
                    btn.classList.add('btn-primary');

                    forms.forEach(function (form) {
                        form.classList.toggle('hidden', form.id !== btn.dataset.form);
                    });
                });
            });

            function wireLocationItems(locationId, itemId) {
                const locationEl = document.getElementById(locationId);
                const itemEl = document.getElementById(itemId);
                if (!locationEl || !itemEl) return;

                locationEl.addEventListener('change', function () {
                    const location = this.value;
                    itemEl.innerHTML = '';
                    if (!location) {
                        itemEl.innerHTML = '<option value="">Select warehouse first</option>';
                        return;
                    }
                    const locationItems = [];
                    const rows = document.querySelectorAll('#stock-table tbody tr');
                    rows.forEach(function (row) {
                        if (row.querySelector('td[colspan]')) return;
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 2 && cells[1].textContent === location) {
                            const itemName = cells[0].textContent.trim();
                            if (locationItems.indexOf(itemName) === -1) {
                                locationItems.push(itemName);
                            }
                        }
                    });
                    locationItems.sort();
                    if (locationItems.length === 0) {
                        itemEl.innerHTML = '<option value="">No items in this warehouse</option>';
                        return;
                    }
                    locationItems.forEach(function (itemName) {
                        const opt = document.createElement('option');
                        opt.value = itemName;
                        opt.textContent = itemName;
                        itemEl.appendChild(opt);
                    });
                });
            }

            wireLocationItems('adjust-location', 'adjust-item');
            wireLocationItems('stockout-location', 'stockout-item');

            function attachSearch(inputId, tableId) {
                const input = document.getElementById(inputId);
                const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
                if (!input) return;
                input.addEventListener('input', function () {
                    const q = this.value.trim().toLowerCase();
                    rows.forEach(function (row) {
                        if (row.querySelector('td[colspan]')) return;
                        row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
                    });
                });
            }

            attachSearch('stock-search', 'stock-table');

            const countItem = document.getElementById('count-item');
            const countLocation = document.getElementById('count-location');
            const countButton = document.getElementById('count-button');

            function showCountResult() {
                if (!countItem.value || !countLocation.value) {
                    alert('Please select an item and a location to count.');
                    return;
                }
                const key = countItem.value + '|' + countLocation.value;
                const row = stockLookup[key];
                const isMissing = !row;

                document.getElementById('count-result-item').textContent = countItem.value;
                document.getElementById('count-result-location').textContent = countLocation.value;
                document.getElementById('count-result-quantity').textContent = row ? row.quantity : '0';
                document.getElementById('count-result-updated').textContent = row && row.updated_at ? row.updated_at : '—';

                const badge = document.getElementById('count-result-status');
                if (isMissing) {
                    badge.textContent = 'No stock recorded';
                    badge.className = 'inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-danger/10 text-danger';
                } else if (row.quantity === 0) {
                    badge.textContent = 'Out of stock';
                    badge.className = 'inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-danger/10 text-danger';
                } else if (row.quantity <= 10) {
                    badge.textContent = 'Low stock';
                    badge.className = 'inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-warning/10 text-warning';
                } else {
                    badge.textContent = 'In stock';
                    badge.className = 'inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-success/10 text-success';
                }

                if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
                    window.HSOverlay.open('#stockCountModal');
                } else {
                    document.getElementById('stockCountModal').classList.remove('hidden');
                }
            }

            if (countButton) countButton.addEventListener('click', showCountResult);
        })();
    </script>

    <!-- Stock Count Result Modal -->
    <div id="stockCountModal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="stockCountModal-label">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 scale-95 opacity-0 ease-in-out transition-all duration-200 max-w-sm w-full mx-auto my-6 px-4 sm:px-0 min-h-[calc(100%-56px)] flex items-center">
            <div class="card w-full flex flex-col border border-default-200 shadow-2xs rounded-xl pointer-events-auto">
                <div class="card-header">
                    <h3 id="stockCountModal-label" class="font-semibold text-base text-default-800 dark:text-white">
                        Stock Count Result
                    </h3>
                    <button type="button" class="size-5 text-default-800" aria-label="Close" data-hs-overlay="#stockCountModal">
                        <span class="sr-only">Close</span>
                        <i data-lucide="x" class="size-5"></i>
                    </button>
                </div>

                <div class="card-body">
                    <div class="flex flex-col items-center text-center py-4">
                        <div class="size-16 rounded-full bg-primary/10 flex items-center justify-center mb-4">
                            <i data-lucide="boxes" class="size-8 text-primary"></i>
                        </div>
                        <p class="text-sm text-default-500">Current stock on hand for</p>
                        <h4 id="count-result-item" class="text-lg font-semibold text-default-900 mt-1"></h4>
                        <p class="text-sm text-default-500 mt-1">at <span id="count-result-location" class="font-medium text-default-800"></span></p>

                        <div class="flex items-center justify-center mt-5">
                            <span id="count-result-quantity" class="text-5xl font-bold text-default-900"></span>
                        </div>
                        <span id="count-result-status" class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full mt-3"></span>

                        <div class="mt-5 text-sm text-default-500">
                            Last updated: <span id="count-result-updated" class="font-medium text-default-700"></span>
                        </div>
                    </div>
                </div>

                <div class="card-footer flex justify-end">
                    <button type="button" class="btn btn-primary" data-hs-overlay="#stockCountModal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme Settings Offcanvas -->
    <div>
        <div id="theme-customization" class="hs-overlay hs-overlay-open:translate-x-0 hidden bg-card dark:bg-default-100 hs-overlay-open:flex flex-col translate-x-full rtl:-translate-x-full fixed inset-y-0 end-0 bottom-0 transition-all duration-300 transform max-w-sm w-full z-80 overflow-hidden">
            <div class="min-h-16 flex items-center text-default-600 border-b border-dashed border-default-900/10 px-6 gap-3">
                <h5 class="text-base grow">Theme Settings</h5>
                <button type="button" data-hs-overlay="#theme-customization" class="btn size-9 rounded-full btn-sm hover:bg-default-150">
                    <i class="iconify tabler--x text-xl"></i>
                </button>
            </div>
        </div>
    </div>

</body>
</html>
