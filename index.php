<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

require_once 'backend/config.php';

$currentUserId = $_SESSION['user_id'] ?? 0;
$notifications = [];
$unreadCount = 0;

if ($currentUserId > 0 && $connect && !$connect->connect_error) {
    $stmt = $connect->prepare(
        'SELECT id, type, title, message, link, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY is_read ASC, created_at DESC
         LIMIT 12'
    );
    $stmt->bind_param('i', $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();

    $unreadStmt = $connect->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $unreadStmt->bind_param('i', $currentUserId);
    $unreadStmt->execute();
    $unreadStmt->bind_result($unreadCount);
    $unreadStmt->fetch();
    $unreadStmt->close();
}

// ---- Dashboard metrics ----------------------------------------------
$isStoreManager   = authHasRole(['store manager']);
$isProductionRole = authHasRole(['production manager', 'production supervisor', 'project manager']);
$isAdmin          = authHasRole(['administrator', 'admin']);

$showStoreCards      = $isStoreManager || $isAdmin || (!$isStoreManager && !$isProductionRole);
$showProductionCards = $isProductionRole || $isAdmin || (!$isStoreManager && !$isProductionRole);

$sm = [
    'total_products'   => 0,
    'low_stock'        => 0,
    'total_warehouses' => 0,
    'total_categories' => 0,
    'mr_pending'       => 0,
    'waste_records'    => 0,
];

if ($showStoreCards && $connect && !$connect->connect_error) {
    $smValue = function (string $sql) use ($connect) {
        $res = $connect->query($sql);
        if ($res && ($row = $res->fetch_row())) {
            return $row[0];
        }
        return 0;
    };

    // Aligned with the counts shown on the dedicated pages.
    $sm['total_products']   = (int) $smValue("SELECT COUNT(DISTINCT item) FROM stock");
    $sm['low_stock']        = (int) $smValue("SELECT COUNT(*) FROM stock WHERE quantity < 10");
    $sm['total_warehouses'] = (int) $smValue("SELECT COUNT(*) FROM warehouses");
    $sm['total_categories'] = (int) $smValue("SELECT COUNT(*) FROM categories");
    $sm['mr_pending']       = (int) $smValue("SELECT COUNT(*) FROM material_requests WHERE status = 'Submitted'");
    $sm['waste_records']    = (int) $smValue("SELECT COUNT(*) FROM waste_records");
}

$pm = [
    'total_jobs'     => 0,
    'running'        => 0,
    'completed'      => 0,
    'pending'        => 0,
    'mr_pending'     => 0,
    'waste_records'  => 0,
];

if ($showProductionCards && $connect && !$connect->connect_error) {
    $pmValue = function (string $sql) use ($connect) {
        $res = $connect->query($sql);
        if ($res && ($row = $res->fetch_row())) {
            return $row[0];
        }
        return 0;
    };

    // Aligned with the counts shown on the dedicated pages.
    $pm['total_jobs']    = (int) $pmValue("SELECT COUNT(*) FROM production_jobs");
    $pm['running']       = (int) $pmValue("SELECT COUNT(*) FROM production_jobs WHERE status = 'Running'");
    $pm['completed']     = (int) $pmValue("SELECT COUNT(*) FROM production_jobs WHERE status = 'Completed'");
    $pm['pending']       = (int) $pmValue("SELECT COUNT(*) FROM production_jobs WHERE status IN ('Draft','Submitted','Pending Approval','Approved','')");
    $pm['mr_pending']    = (int) $pmValue("SELECT COUNT(*) FROM material_requests WHERE status = 'Submitted'");
    $pm['waste_records'] = (int) $pmValue("SELECT COUNT(*) FROM waste_records");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PICS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description">
    <meta content="Themesdesign" name="author">
<!-- App favicon -->
    <link rel="icon" type="image/svg+xml" href="./assets/images/pics-logo.svg">

    <script>
        (function () {
            const html = document.documentElement;
            const storageKey = "__TAILWICK_CONFIG__";
            const savedConfig = sessionStorage.getItem(storageKey);
    
            // Default config
            const defaultConfig = {
                dir: "ltr",
                theme: "light",
                sidenav: {
                    color: "light",
                    size: "default",
                },
            };
    
            // Build config from HTML attributes
            function getSystemTheme() {
                return window.matchMedia('(prefers-color-scheme: dark)').matches ? "dark" : "light";
            }
    
            // Build config from HTML attributes
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
    
            // Save merged config as defaults globally
            window.defaultConfig = structuredClone(htmlConfig);
    
            // Load from session if exists
            let config = savedConfig ? JSON.parse(savedConfig) : htmlConfig;
            window.config = config;
    
            // Apply layout attributes immediately
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
    

    
    <!-- Plain HTML and Linked CSS (No NPM server required) -->
  <script defer src="./assets/js/apexcharts.min.js"></script>
  <script defer src="./assets/js/lucide.min.js"></script>
  <script defer src="./assets/js/app.js"></script>
  <script defer src="./assets/js/timepicker.js"></script>
  <script defer src="./assets/js/index.js"></script>
  <script defer src="./assets/js/pics-dashboard.js?v=5"></script>

  
  
  
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
                        <!-- Sidenav Menu Toggle Button -->
                        <button id="button-toggle-menu" class="btn btn-icon size-8 hover:bg-default-150 rounded">
                            <i class="iconify lucide--align-left text-xl"></i>
                        </button>
            
                    </div>
            
<div class="flex items-center gap-3">
            
                        <?php require __DIR__ . '/backend/notifications_dropdown.php'; ?>

                        <!-- Light/Dark Mode Button -->
                        <div class="topbar-item">
                            <button class="btn btn-icon size-8 hover:bg-default-150 transition-[scale,background] rounded-full" id="light-dark-mode" type="button">
                                <i class="iconify tabler--moon text-xl absolute dark:scale-0 dark:-rotate-90 scale-100 rotate-0 transition-all duration-200"></i>
                                <i class="iconify tabler--sun text-xl absolute dark:scale-100 dark:rotate-0 scale-0 rotate-90 transition-all duration-200"></i>
                            </button>
                        </div>
            
                        <!-- Profile Dropdown Button -->
                        <div class="topbar-item hs-dropdown relative inline-flex">
                            <button class="cursor-pointer bg-primary/10 rounded-full" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                <span class="hs-dropdown-toggle size-9.5 rounded-full flex items-center justify-center text-sm font-semibold text-primary"><?= htmlspecialchars(strtoupper(mb_substr($_SESSION['user_name'] ?? 'User', 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                            </button>
                            <div class="hs-dropdown-menu min-w-48" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-with-icons">
                                <div class="p-2">
                                    <h6 class="mb-2 text-default-500">Signed in as</h6>
                                    <div class="flex gap-3">
                                        <div class="rounded bg-primary/10 size-12 flex items-center justify-center font-semibold text-primary">
                                            <?= htmlspecialchars(strtoupper(mb_substr($_SESSION['user_name'] ?? 'User', 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 text-sm font-semibold text-default-800"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></h6>
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
                    <h4 class="text-default-900 text-lg font-semibold">Print Inventory Control System (PICS)</h4>
                
                    <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                        <a href="#" class="text-sm font-medium text-default-700">Tailwick</a>
                
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                
                        <a href="#" class="text-sm font-medium text-default-700">Dashboards</a>
                
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                
                        <a href="#" class="text-sm font-medium text-default-700" aria-current="page">Home</a>
                    </div>
                </div>
                <!-- Page Title End -->

                        <?php if ($showStoreCards && $showProductionCards): ?>
                        <div class="mb-5">
                            <h4 class="text-default-900 text-lg font-semibold mb-3">Production Overview</h4>
                            <div class="grid lg:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-5">
                                <a href="production-jobs.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                    <div class="card-body">
                                        <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-primary/10">
                                            <i data-lucide="factory" class="size-6 text-primary"></i>
                                        </div>
                                        <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                            <span data-target="<?= $pm['total_jobs'] ?>"><?= number_format($pm['total_jobs']) ?></span>
                                        </h5>
                                        <p class="text-center text-sm text-default-500">Total Production Jobs</p>
                                    </div>
                                </a>

                                <a href="production-jobs.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                    <div class="card-body">
                                        <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-info/10">
                                            <i data-lucide="loader-circle" class="size-6 text-info"></i>
                                        </div>
                                        <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                            <span data-target="<?= $pm['running'] ?>"><?= number_format($pm['running']) ?></span>
                                        </h5>
                                        <p class="text-center text-sm text-default-500">Running Jobs</p>
                                    </div>
                                </a>

                                <a href="production-jobs.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                    <div class="card-body">
                                        <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-success/10">
                                            <i data-lucide="check-circle-2" class="size-6 text-success"></i>
                                        </div>
                                        <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                            <span data-target="<?= $pm['completed'] ?>"><?= number_format($pm['completed']) ?></span>
                                        </h5>
                                        <p class="text-center text-sm text-default-500">Completed Jobs</p>
                                    </div>
                                </a>

                                <a href="production-jobs.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                    <div class="card-body">
                                        <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-warning/10">
                                            <i data-lucide="clock" class="size-6 text-warning"></i>
                                        </div>
                                        <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                            <span data-target="<?= $pm['pending'] ?>"><?= number_format($pm['pending']) ?></span>
                                        </h5>
                                        <p class="text-center text-sm text-default-500">Pending / Approved</p>
                                    </div>
                                </a>

                                <a href="material-requests.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                    <div class="card-body">
                                        <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-cyan/10">
                                            <i data-lucide="list-checks" class="size-6 text-cyan"></i>
                                        </div>
                                        <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                            <span data-target="<?= $pm['mr_pending'] ?>"><?= number_format($pm['mr_pending']) ?></span>
                                        </h5>
                                        <p class="text-center text-sm text-default-500">Material Requests Pending</p>
                                    </div>
                                </a>

                                <a href="waste-records.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                    <div class="card-body">
                                        <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-danger/10">
                                            <i data-lucide="recycle" class="size-6 text-danger"></i>
                                        </div>
                                        <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                            <span data-target="<?= $pm['waste_records'] ?>"><?= number_format($pm['waste_records']) ?></span>
                                        </h5>
                                        <p class="text-center text-sm text-default-500">Waste Records</p>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <div class="mb-5">
                            <h4 class="text-default-900 text-lg font-semibold mb-3">Inventory &amp; Sales Overview</h4>
                            <div class="grid lg:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-5">
                                <a href="stock-management.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                    <div class="card-body">
                                        <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-primary/10">
                                            <i data-lucide="package" class="size-6 text-primary"></i>
                                        </div>
                                        <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                            <span data-target="<?= $sm['total_products'] ?>"><?= number_format($sm['total_products']) ?></span>
                                        </h5>
                                        <p class="text-center text-sm text-default-500">Total Products</p>
                                    </div>
                                </a>

                                <a href="stock-management.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                    <div class="card-body">
                                        <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-warning/10">
                                            <i data-lucide="alert-triangle" class="size-6 text-warning"></i>
                                        </div>
                                        <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                            <span data-target="<?= $sm['low_stock'] ?>"><?= number_format($sm['low_stock']) ?></span>
                                        </h5>
                                        <p class="text-center text-sm text-default-500">Low Stock Items</p>
                                    </div>
                                </a>

                                <a href="warehouses.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                    <div class="card-body">
                                        <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-secondary/10">
                                            <i data-lucide="building-2" class="size-6 text-secondary"></i>
                                        </div>
                                        <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                            <span data-target="<?= $sm['total_warehouses'] ?>"><?= number_format($sm['total_warehouses']) ?></span>
                                        </h5>
                                        <p class="text-center text-sm text-default-500">Total Warehouses</p>
                                    </div>
                                </a>

                                <a href="categories.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                    <div class="card-body">
                                        <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-cyan/10">
                                            <i data-lucide="tags" class="size-6 text-cyan"></i>
                                        </div>
                                        <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                            <span data-target="<?= $sm['total_categories'] ?>"><?= number_format($sm['total_categories']) ?></span>
                                        </h5>
                                        <p class="text-center text-sm text-default-500">Total Categories</p>
                                    </div>
                                </a>

                                <a href="material-requests.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                    <div class="card-body">
                                        <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-warning/10">
                                            <i data-lucide="list-checks" class="size-6 text-warning"></i>
                                        </div>
                                        <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                            <span data-target="<?= $sm['mr_pending'] ?>"><?= number_format($sm['mr_pending']) ?></span>
                                        </h5>
                                        <p class="text-center text-sm text-default-500">Material Requests Pending</p>
                                    </div>
                                </a>

                                <a href="waste-records.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                    <div class="card-body">
                                        <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-danger/10">
                                            <i data-lucide="recycle" class="size-6 text-danger"></i>
                                        </div>
                                        <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                            <span data-target="<?= $sm['waste_records'] ?>"><?= number_format($sm['waste_records']) ?></span>
                                        </h5>
                                        <p class="text-center text-sm text-default-500">Waste Records</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <?php elseif ($showStoreCards): ?>
                        <div class="grid lg:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-5 mb-5">
                            <a href="stock-management.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                <div class="card-body">
                                    <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-primary/10">
                                        <i data-lucide="package" class="size-6 text-primary"></i>
                                    </div>
                                    <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                        <span data-target="<?= $sm['total_products'] ?>"><?= number_format($sm['total_products']) ?></span>
                                    </h5>
                                    <p class="text-center text-sm text-default-500">Total Products</p>
                                </div>
                            </a>

                            <a href="stock-management.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                <div class="card-body">
                                    <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-warning/10">
                                        <i data-lucide="alert-triangle" class="size-6 text-warning"></i>
                                    </div>
                                    <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                        <span data-target="<?= $sm['low_stock'] ?>"><?= number_format($sm['low_stock']) ?></span>
                                    </h5>
                                    <p class="text-center text-sm text-default-500">Low Stock Items</p>
                                </div>
                            </a>

                            <a href="warehouses.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                <div class="card-body">
                                    <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-secondary/10">
                                        <i data-lucide="building-2" class="size-6 text-secondary"></i>
                                    </div>
                                    <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                        <span data-target="<?= $sm['total_warehouses'] ?>"><?= number_format($sm['total_warehouses']) ?></span>
                                    </h5>
                                    <p class="text-center text-sm text-default-500">Total Warehouses</p>
                                </div>
                            </a>

                            <a href="categories.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                <div class="card-body">
                                    <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-cyan/10">
                                        <i data-lucide="tags" class="size-6 text-cyan"></i>
                                    </div>
                                    <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                        <span data-target="<?= $sm['total_categories'] ?>"><?= number_format($sm['total_categories']) ?></span>
                                    </h5>
                                    <p class="text-center text-sm text-default-500">Total Categories</p>
                                </div>
                            </a>

                            <a href="material-requests.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                <div class="card-body">
                                    <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-warning/10">
                                        <i data-lucide="list-checks" class="size-6 text-warning"></i>
                                    </div>
                                    <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                        <span data-target="<?= $sm['mr_pending'] ?>"><?= number_format($sm['mr_pending']) ?></span>
                                    </h5>
                                    <p class="text-center text-sm text-default-500">Material Requests Pending</p>
                                </div>
                            </a>

                            <a href="waste-records.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                <div class="card-body">
                                    <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-danger/10">
                                        <i data-lucide="recycle" class="size-6 text-danger"></i>
                                    </div>
                                    <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                        <span data-target="<?= $sm['waste_records'] ?>"><?= number_format($sm['waste_records']) ?></span>
                                    </h5>
                                    <p class="text-center text-sm text-default-500">Waste Records</p>
                                </div>
                            </a>

                        </div>
                        <?php elseif ($showProductionCards): ?>
                        <div class="grid lg:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-5 mb-5">
                            <a href="production-jobs.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                <div class="card-body">
                                    <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-primary/10">
                                        <i data-lucide="factory" class="size-6 text-primary"></i>
                                    </div>
                                    <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                        <span data-target="<?= $pm['total_jobs'] ?>"><?= number_format($pm['total_jobs']) ?></span>
                                    </h5>
                                    <p class="text-center text-sm text-default-500">Total Production Jobs</p>
                                </div>
                            </a>

                            <a href="production-jobs.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                <div class="card-body">
                                    <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-info/10">
                                        <i data-lucide="loader-circle" class="size-6 text-info"></i>
                                    </div>
                                    <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                        <span data-target="<?= $pm['running'] ?>"><?= number_format($pm['running']) ?></span>
                                    </h5>
                                    <p class="text-center text-sm text-default-500">Running Jobs</p>
                                </div>
                            </a>

                            <a href="production-jobs.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                <div class="card-body">
                                    <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-success/10">
                                        <i data-lucide="check-circle-2" class="size-6 text-success"></i>
                                    </div>
                                    <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                        <span data-target="<?= $pm['completed'] ?>"><?= number_format($pm['completed']) ?></span>
                                    </h5>
                                    <p class="text-center text-sm text-default-500">Completed Jobs</p>
                                </div>
                            </a>

                            <a href="production-jobs.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                <div class="card-body">
                                    <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-warning/10">
                                        <i data-lucide="clock" class="size-6 text-warning"></i>
                                    </div>
                                    <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                        <span data-target="<?= $pm['pending'] ?>"><?= number_format($pm['pending']) ?></span>
                                    </h5>
                                    <p class="text-center text-sm text-default-500">Pending / Approved</p>
                                </div>
                            </a>

                            <a href="material-requests.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                <div class="card-body">
                                    <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-cyan/10">
                                        <i data-lucide="list-checks" class="size-6 text-cyan"></i>
                                    </div>
                                    <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                        <span data-target="<?= $pm['mr_pending'] ?>"><?= number_format($pm['mr_pending']) ?></span>
                                    </h5>
                                    <p class="text-center text-sm text-default-500">Material Requests Pending</p>
                                </div>
                            </a>

                            <a href="waste-records.php" class="card block hover:shadow-md hover:border-primary/40 transition-all">
                                <div class="card-body">
                                    <div class="flex items-center justify-center mx-auto rounded-full size-14 bg-danger/10">
                                        <i data-lucide="recycle" class="size-6 text-danger"></i>
                                    </div>
                                    <h5 class="mt-4 text-center mb-2 text-default-800 font-semibold text-lg">
                                        <span data-target="<?= $pm['waste_records'] ?>"><?= number_format($pm['waste_records']) ?></span>
                                    </h5>
                                    <p class="text-center text-sm text-default-500">Waste Records</p>
                                </div>
                            </a>
                        </div>
                        <?php endif; ?>

                    <!-- <div class="col-span-1">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title">Order Statistics</h6>
                                <a href="#" class="btn btn-sm border-0 text-primary/90 hover:text-primary">View All
                                    <i data-lucide="move-right" class="ms-1 size-4"></i>
                                </a>
                            </div>

                            <div class="card-body">
                                <div id="orderStatisticsChart"></div>
                            </div>
                        </div>
                    </div> -->

                <div class="grid lg:grid-cols-1 grid-cols-1 gap-5 mb-5">
                    <div class="lg:col-span-2 col-span-1">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title">Inventory &amp; Operations Trends</h6>
                            </div>

                            <div class="card-body">
                                <div class="grid lg:grid-cols-2 grid-cols-1 gap-5">
                                    <?php if ($showStoreCards): ?>
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="card-title">Inventory Trends</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="inventoryTrendsChart"></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($showProductionCards): ?>
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="card-title">Production Trends</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="productionTrendsChart"></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="card-title">Waste Trends</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="wasteTrendsChart"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="col-span-1">
                        <div class="card mb-5 ">
                            <div class="card-header">
                                <h6 class="card-title">Traffic Resources</h6>
                                <a href="#" class="btn btn-sm border-0 text-primary/90 hover:text-primary">View Status
                                    <i data-lucide="move-right" class="ms-1 size-4"></i>
                                </a>
                            </div>

                            <div class="card-body">
                                <div class="grid md:grid-cols-12 grid-cols-1">
                                    <div class="rounded-md md:col-span-7 col-span-1">
                                        <div id="trafficResourcesChart" dir="rtl"></div>
                                    </div>

                                    <div class="md:col-span-5 col-span-1">
                                        <div class="flex flex-col gap-3">
                                            <div class="flex items-center gap-2">
                                                <div class="bg-green-500 size-3" style="clip-path: polygon(50% 0%, 0% 100%, 100% 100%);"></div>
                                                <p class="text-green-500">Search Engine (22%)</p>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <div class="bg-purple-500 size-3" style="clip-path: polygon(50% 0%, 0% 100%, 100% 100%);"></div>
                                                <p class="text-purple-500">Referral (34%)</p>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <div class="bg-sky-500 size-3" style="clip-path: polygon(50% 0%, 0% 100%, 100% 100%);"></div>
                                                <p class="text-sky-500">Direct (44%)</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="text-lg text-default-900 font-semibold">
                                        <span data-target="1596">1,596</span>
                                    </h5>
                                    <span class="px-2.5 py-0.5 text-xs rounded border bg-transparent border-danger/50 text-danger flex items-center gap-1">
                                        <i data-lucide="trending-down" class="size-3"></i>
                                        6.8%
                                    </span>
                                </div>

                                <h6 class="font-semibold text-default-900">Monthly Orders Goal (20000+)</h6>

                                <div>
                                    <div class="flex items-center justify-between mt-5 mb-2">
                                        <p class="text-default-500 text-sm">Total Orders</p>
                                        <h6 class="mb-0 text-primary">85%</h6>
                                    </div>
                                    <div class="w-full bg-default-200 rounded-full h-2.54">
                                        <div class="bg-primary h-2.5 rounded-full" style="width: 85%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> -->
                </div>

            </main>

            <!-- Footer Start -->
            <footer class="mt-auto footer flex items-center py-5 border-t border-default-200">
                <div class="lg:px-8 px-6 w-full flex md:justify-between justify-center gap-4"></div>
            </footer>
            <!-- Footer End -->

        </div>
    </div>

    <!-- Theme Settings Offcanvas -->
    <div>
        <div id="theme-customization" class="hs-overlay hs-overlay-open:translate-x-0 hidden bg-card dark:bg-default-100 hs-overlay-open:flex flex-col translate-x-full rtl:-translate-x-full fixed inset-y-0 end-0 bottom-0 transition-all duration-300 transform max-w-sm w-full z-80 overflow-hidden">
            <div class="min-h-16 flex items-center text-default-600 border-b border-dashed border-default-900/10 px-6 gap-3">
                <h5 class="text-base grow">Theme Settings</h5>
    
                <button class="btn size-9 rounded-full btn-sm hover:bg-default-150 group" id="fullscreenBtn" data-toggle="fullscreen" aria-label="Full Screen">
                    <i class="iconify lucide--fullscreen size-5 group-[.fullscreen-active]:hidden"></i>
                    <i class="iconify lucide--minimize size-5 hidden group-[.fullscreen-active]:inline-block"></i>
                </button>
    
                <button type="button" data-hs-overlay="#theme-customization" class="btn size-9 rounded-full btn-sm hover:bg-default-150">
                    <i class="iconify tabler--x text-xl"></i>
                </button>
            </div>
    
            <div class="h-full flex-grow overflow-y-auto" data-simplebar>
                <div class="divide-y divide-dashed divide-default-200">
                    <div class="p-6">
                        <h5 class="font-semibold text-sm mb-3">Sidenav View</h5>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="card-radio">
                                <input class="hidden" type="radio" name="data-sidenav-size" id="sidenav-view-default" value="default">
                                <label class="form-label" for="sidenav-view-default">
                                    <span class="flex h-16 overflow-hidden border border-default-200 rounded-md">
                                        <span class="block w-8 bg-default-100">
                                            <span class="mt-1.5 mx-1.5 block space-y-1">
                                                <span class="h-1 block rounded-sm mb-2.5 bg-default-300"></span>
                                                <span class="h-1 block rounded-sm bg-default-300"></span>
                                                <span class="h-1 block rounded-sm bg-default-300"></span>
                                                <span class="h-1 block rounded-sm bg-default-300"></span>
                                                <span class="h-1 block rounded-sm bg-default-300"></span>
                                                <span class="h-1 block rounded-sm bg-default-300"></span>
                                            </span>
                                        </span>
                                        <span class="flex flex-col flex-auto border-s border-default-200">
                                            <span class="h-3 bg-default-100">
                                                <span class="flex items-center justify-end h-full mr-1.5">
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                </span>
                                            </span>
                                            <span class="flex flex-auto border-t border-default-200 bg-default-50"></span>
                                        </span>
                                    </span>
                                </label>
                                <div class="mt-1 text-md font-medium text-center text-default-600"> Default </div>
                            </div>
    
                            <div class="card-radio">
                                <input class="hidden" type="radio" name="data-sidenav-size" id="sidenav-view-hover" value="hover">
                                <label class="form-label" for="sidenav-view-hover">
                                    <span class="flex h-16 overflow-hidden border border-default-200 rounded-md">
                                        <span class="w-3 bg-default-100">
                                            <span class="w-1.5 h-1.5 mt-1 mx-auto rounded-sm bg-default-300"></span>
                                            <span class="flex flex-col items-center w-full mt-1.5 space-y-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-default-300"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-default-300"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-default-300"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-default-300"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-default-300"></span>
                                            </span>
                                        </span>
                                        <span class="flex flex-col flex-auto border-s border-default-200">
                                            <span class="h-3 bg-default-100">
                                                <span class="flex items-center justify-end h-full mr-1.5">
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                </span>
                                            </span>
                                            <span class="flex flex-auto border-t border-default-200 bg-default-50"></span>
                                        </span>
                                    </span>
                                </label>
                                <div class="mt-1 text-md font-medium text-center text-default-600"> Hover </div>
                            </div>
    
                            <div class="card-radio">
                                <input class="hidden" type="radio" name="data-sidenav-size" id="sidenav-view-hover-active" value="hover-active">
                                <label class="form-label" for="sidenav-view-hover-active">
                                    <span class="flex h-16 overflow-hidden border border-default-200 rounded-md">
                                        <span class="w-8 bg-default-100">
                                            <span class="mt-1.5 mx-1.5 block space-y-1">
                                                <span class="flex mb-2.5 gap-1">
                                                    <span class="h-1 block w-full rounded-sm bg-default-300"></span>
                                                    <span class="h-1 block w-2 rounded-full bg-default-300"></span>
                                                </span>
                                                <span class="h-1 block rounded-sm bg-default-300"></span>
                                                <span class="h-1 block rounded-sm bg-default-300"></span>
                                                <span class="h-1 block rounded-sm bg-default-300"></span>
                                                <span class="h-1 block rounded-sm bg-default-300"></span>
                                                <span class="h-1 block rounded-sm bg-default-300"></span>
                                            </span>
                                        </span>
                                        <span class="flex flex-col flex-auto border-s border-default-200">
                                            <span class="h-3 bg-default-100">
                                                <span class="flex items-center justify-end h-full mr-1.5">
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                </span>
                                            </span>
                                            <span class="flex flex-auto border-t border-default-200 bg-default-50"></span>
                                        </span>
                                    </span>
                                </label>
                                <div class="mt-1 text-md font-medium text-center text-default-600"> Hover Active </div>
                            </div>
    
                            <div class="card-radio">
                                <input class="hidden" type="radio" name="data-sidenav-size" id="sidenav-view-sm" value="sm">
                                <label class="form-label" for="sidenav-view-sm">
                                    <span class="flex h-16 overflow-hidden border border-default-200 rounded-md">
                                        <span class="w-3 bg-default-100">
                                            <span class="w-1.5 h-1.5 mt-1 mx-auto rounded-sm bg-default-300"></span>
                                            <span class="flex flex-col items-center w-full mt-1.5 space-y-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-default-300"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-default-300"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-default-300"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-default-300"></span>
                                                <span class="w-1.5 h-1.5 rounded-full bg-default-300"></span>
                                            </span>
                                        </span>
                                        <span class="flex flex-col flex-auto border-s border-default-200">
                                            <span class="h-3 bg-default-100">
                                                <span class="flex items-center h-full mr-1.5">
                                                    <span class="grow">
                                                        <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                    </span>
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                </span>
                                            </span>
                                            <span class="flex flex-auto border-t border-default-200 bg-default-50"></span>
                                        </span>
                                    </span>
                                </label>
                                <div class="mt-1 text-md font-medium text-center text-default-600"> Small </div>
                            </div>
    
                            <div class="card-radio">
                                <input class="hidden" type="radio" name="data-sidenav-size" id="sidenav-view-md" value="md">
                                <label class="form-label" for="sidenav-view-md">
                                    <span class="flex h-16 overflow-hidden border border-default-200 rounded-md">
                                        <span class="w-4 bg-default-100">
                                            <span class="w-2 h-2 mt-2 mx-auto rounded-sm bg-default-300"></span>
                                            <span class="flex flex-col items-center w-full mt-2 space-y-1">
                                                <span class="w-2 h-2 rounded-sm bg-default-300"></span>
                                                <span class="w-2 h-2 rounded-sm bg-default-300"></span>
                                                <span class="w-2 h-2 rounded-sm bg-default-300"></span>
                                            </span>
                                        </span>
                                        <span class="flex flex-col flex-auto border-s border-default-200">
                                            <span class="h-3 bg-default-100">
                                                <span class="flex items-center h-full mr-1.5">
                                                    <span class="grow">
                                                        <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                    </span>
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ml-1 rounded-full bg-default-300"></span>
                                                </span>
                                            </span>
                                            <span class="flex flex-auto border-t border-default-200 bg-default-50"></span>
                                        </span>
                                    </span>
                                </label>
                                <div class="mt-1 text-md font-medium text-center text-default-600"> Compact </div>
                            </div>
    
                            <div class="card-radio">
                                <input class="hidden" type="radio" name="data-sidenav-size" id="sidenav-view-mobile" value="offcanvas">
                                <label class="form-label" for="sidenav-view-mobile">
                                    <span class="flex h-16 overflow-hidden border border-default-200 rounded-md">
                                        <span class="flex flex-col flex-auto">
                                            <span class="h-3 bg-default-100">
                                                <span class="flex items-center h-full mr-1.5">
                                                    <span class="w-1.5 h-1.5  ms-1 rounded-sm bg-default-300"></span>
                                                    <span class="w-1 h-1 block ms-1  rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ms-auto rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ms-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ms-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ms-1 rounded-full bg-default-300"></span>
                                                </span>
                                            </span>
                                            <span class="flex flex-auto border-t border-default-200 bg-default-50"></span>
                                        </span>
                                    </span>
                                </label>
                                <div class="mt-1 text-md font-medium text-center text-default-600"> Mobile </div>
                            </div>
    
                            <div class="card-radio">
                                <input class="hidden" type="radio" name="data-sidenav-size" id="sidenav-view-hidden" value="hidden">
                                <label class="form-label" for="sidenav-view-hidden">
                                    <span class="flex h-16 overflow-hidden border border-default-200 rounded-md">
                                        <span class="flex flex-col flex-auto">
                                            <span class="h-3 bg-default-100">
                                                <span class="flex flex-auto items-center h-full me-1.5">
                                                    <span class="w-1 h-1 block ms-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ms-auto rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ms-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ms-1 rounded-full bg-default-300"></span>
                                                    <span class="w-1 h-1 block ms-1 rounded-full bg-default-300"></span>
                                                </span>
                                            </span>
                                            <span class="flex flex-auto border-t border-default-200 bg-default-50"></span>
                                        </span>
                                    </span>
                                </label>
                                <div class="mt-1 text-md font-medium text-center text-default-600"> Hidden </div>
                            </div>
                        </div>
                    </div>
    
                    <div class="p-6">
                        <h5 class="font-semibold text-sm mb-3">Theme Mode</h5>
                        <div class="flex gap-2">
                            <div>
                                <input class="hidden" type="radio" name="data-theme" id="layout-color-light" value="light">
                                <label class="form-label btn bg-default-150" for="layout-color-light">Light</label>
                            </div>
    
                            <div>
                                <input class="hidden" type="radio" name="data-theme" id="layout-color-dark" value="dark">
                                <label class="form-label btn bg-default-150" for="layout-color-dark">Dark</label>
                            </div>
    
                            <div>
                                <input class="hidden" type="radio" name="data-theme" id="layout-color-system" value="system">
                                <label class="form-label btn bg-default-150" for="layout-color-system">System</label>
                            </div>
                        </div>
                    </div>
    
                    <div class="p-6">
                        <h5 class="font-semibold text-sm mb-3">Direction</h5>
    
                        <div class="flex gap-2">
                            <div>
                                <input class="hidden" type="radio" name="dir" id="direction-ltr" value="ltr">
                                <label class="form-label btn bg-default-150" for="direction-ltr">LTR Mode</label>
                            </div>
    
                            <div>
                                <input class="hidden" type="radio" name="dir" id="direction-rtl" value="rtl">
                                <label class="form-label btn bg-default-150" for="direction-rtl">RTL Mode</label>
                            </div>
                        </div>
                    </div>
    
                    <div class="p-6">
                        <h5 class="font-semibold text-sm mb-3">Sidenav Color</h5>
                        <div class="flex gap-2">
                            <div>
                                <input class="hidden" type="radio" name="data-sidenav-color" id="menu-color-light" value="light">
                                <label class="form-label btn bg-default-150" for="menu-color-light">Light</label>
                            </div>
    
                            <div>
                                <input class="hidden" type="radio" name="data-sidenav-color" id="menu-color-dark" value="dark">
                                <label class="form-label btn bg-default-150" for="menu-color-dark">Dark</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    
            <div class="p-4 flex border-t border-dashed border-default-900/10">
                <div class="flex w-full gap-4">
                    <button type="button" class="btn bg-default-150 grow" id="reset-layout">Reset</button>
                    <a href="https://1.envato.market/tailwick-tailwind" target="_blank" class="btn bg-primary text-white grow">Buy Now</a>
                </div>
            </div>
        </div>
    </div>

</body>

</html>
