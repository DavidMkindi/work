<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

require_once 'backend/config.php';

$records = [];
if (isset($connect) && !$connect->connect_error) {
    $result = $connect->query('SELECT * FROM purchase_orders ORDER BY po_date DESC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
    }
}

$totalOrders = count($records);
$totalValue = 0;
foreach ($records as $record) {
    $totalValue += (float) $record['total'];
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

$displayName = isset($_SESSION['user_name']) && $_SESSION['user_name'] !== '' ? $_SESSION['user_name'] : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Orders | PICS</title>
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
                    <h4 class="text-default-900 text-lg font-semibold">Purchase Orders</h4>

                    <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                        <a href="index.php" class="text-sm font-medium text-default-700">PICS</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700">Purchasing</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700" aria-current="page">Purchase Orders</a>
                    </div>
                </div>
                <!-- Page Title End -->

                <!-- Stats Cards Start -->
                <div class="grid lg:grid-cols-2 md:grid-cols-2 grid-cols-1 gap-5 mb-5">
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Total Orders</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $totalOrders ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <i data-lucide="file-text" class="size-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Total Value</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= '$' . number_format($totalValue, 2) ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-success/10 flex items-center justify-center">
                                    <i data-lucide="dollar-sign" class="size-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Stats Cards End -->

                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title">Purchase Orders</h6>
                    </div>

                    <div class="card-header">
                        <div class="md:flex items-center md:space-y-0 space-y-4 gap-3">
                            <div class="relative">
                                <input type="search" id="table-search" class="form-input form-input-sm ps-9" placeholder="Search PO, supplier or items">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3">
                                    <i data-lucide="search" class="size-3.5 flex items-center text-default-500 fill-default-100"></i>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2 items-center flex-wrap">
                            <span class="text-sm text-default-500"><?= $totalOrders ?> order<?= $totalOrders === 1 ? '' : 's' ?> found</span>
                            <button type="button" onclick="window.print()" class="btn btn-sm bg-transparent border border-dashed border-primary text-primary hover:bg-primary/10">
                                <i data-lucide="printer" class="size-4"></i>
                                Print
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <div class="overflow-x-auto">
                            <div class="min-w-full inline-block align-middle">
                                <div class="overflow-hidden">
                                    <table id="po-table" class="min-w-full divide-y divide-default-200">
                                        <thead class="bg-default-150">
                                            <tr class="text-sm font-normal text-default-700">
                                                <th scope="col" class="px-3.5 py-3 text-start">PO Number</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Supplier</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Date</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Expected Delivery</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Items</th>
                                                <th scope="col" class="px-3.5 py-3 text-end">Unit Price</th>
                                                <th scope="col" class="px-3.5 py-3 text-end">Discount</th>
                                                <th scope="col" class="px-3.5 py-3 text-end">Tax</th>
                                                <th scope="col" class="px-3.5 py-3 text-end">Total</th>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-default-100">
                                            <?php if (empty($records)): ?>
                                                <tr>
                                                    <td colspan="9" class="px-3.5 py-16 text-center text-default-500">
                                                        <i data-lucide="file-text" class="size-10 mx-auto mb-3 text-default-300"></i>
                                                        <p class="font-medium">No purchase orders found yet.</p>
                                                    </td>
                                                </tr>
                                            <?php else: foreach ($records as $record): ?>
                                                <tr class="text-default-800 font-normal text-sm">
                                                    <td class="px-3.5 py-3 text-sm text-primary font-medium"><?= htmlspecialchars($record['po_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-800 font-medium"><?= htmlspecialchars($record['supplier'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars(date('M d, Y', strtotime($record['po_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars(date('M d, Y', strtotime($record['expected_delivery'])), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($record['items'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-end text-default-600"><?= '$' . number_format((float) $record['unit_price'], 2) ?></td>
                                                    <td class="py-3 px-3.5 text-end text-default-600"><?= '$' . number_format((float) $record['discount'], 2) ?></td>
                                                    <td class="py-3 px-3.5 text-end text-default-600"><?= '$' . number_format((float) $record['tax'], 2) ?></td>
                                                    <td class="py-3 px-3.5 text-end text-default-800 font-semibold"><?= '$' . number_format((float) $record['total'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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
        (function () {
            const searchInput = document.getElementById('table-search');
            const rows = document.querySelectorAll('#po-table tbody tr');

            searchInput.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                rows.forEach(function (row) {
                    if (row.querySelector('td[colspan]')) return;
                    row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
                });
            });
        })();
    </script>

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