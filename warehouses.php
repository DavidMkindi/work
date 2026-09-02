<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

require_once 'backend/config.php';

$message = '';
$messageType = '';

$displayName = isset($_SESSION['user_name']) && $_SESSION['user_name'] !== '' ? $_SESSION['user_name'] : 'User';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($connect) && !$connect->connect_error) {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');

    try {
        if ($name === '') {
            throw new RuntimeException('Warehouse name is required.');
        }

        $check = $connect->prepare('SELECT id FROM warehouses WHERE name = ?');
        $check->bind_param('s', $name);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows > 0;
        $check->close();

        if ($exists) {
            throw new RuntimeException('A warehouse with that name already exists.');
        }

        $stmt = $connect->prepare('INSERT INTO warehouses (name, address) VALUES (?, ?)');
        $stmt->bind_param('ss', $name, $address);
        $stmt->execute();
        $stmt->close();

        $message = "Warehouse \"{$name}\" registered successfully.";
        $messageType = 'success';
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }

    if ($messageType === 'success') {
        header('Location: warehouses.php');
        exit();
    }
}

$warehouses = [];
if (isset($connect) && !$connect->connect_error) {
    $result = $connect->query(
        'SELECT w.id, w.name, w.address, w.created_at, COUNT(s.id) AS item_count
         FROM warehouses w
         LEFT JOIN stock s ON s.warehouse_id = w.id
         GROUP BY w.id
         ORDER BY w.name ASC'
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $warehouses[] = $row;
        }
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
    <title>Warehouses | PICS</title>
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

        .warehouse-add-button {
            background-color: #000 !important;
            border-radius: 0.5rem !important;
            color: #fff !important;
        }

        .warehouse-add-button:hover {
            background-color: #262626 !important;
        }

        html[data-theme="dark"] .warehouse-add-button {
            background-color: #ffffff !important;
            color: #000000 !important;
        }

        html[data-theme="dark"] .warehouse-add-button:hover {
            background-color: #e5e5e5 !important;
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
                    <h4 class="text-default-900 text-lg font-semibold">Warehouses</h4>

                    <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                        <a href="index.php" class="text-sm font-medium text-default-700">PICS</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700">Inventory</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700" aria-current="page">Warehouses</a>
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
                                    <p class="text-default-500 text-sm">Total Warehouses</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= count($warehouses) ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <i data-lucide="warehouse" class="size-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Stocked Items</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= array_sum(array_column($warehouses, 'item_count')) ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-success/10 flex items-center justify-center">
                                    <i data-lucide="boxes" class="size-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Registered</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= count(array_filter($warehouses, fn($w) => isset($w['created_at']))) ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-warning/10 flex items-center justify-center">
                                    <i data-lucide="map-pin" class="size-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Stats Cards End -->

                <!-- Register Warehouse Card Start -->
                <div class="card mb-5">
                    <div class="card-header">
                        <h6 class="card-title">Register New Warehouse</h6>
                    </div>

                    <form method="POST" action="warehouses.php">
                        <div class="card-body grid lg:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Warehouse Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-input" placeholder="e.g. Main Warehouse" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Address</label>
                                <input type="text" name="address" class="form-input" placeholder="e.g. 123 Industrial St">
                            </div>
                            <div class="flex items-end justify-end">
                                <button type="submit" class="btn btn-primary w-full warehouse-add-button">
                                    <i data-lucide="plus" class="size-4"></i>
                                    Register Warehouse
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- Register Warehouse Card End -->

                <!-- Warehouses Table Start -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title">Registered Warehouses</h6>
                    </div>

                    <div class="card-header">
                        <div class="md:flex items-center md:space-y-0 space-y-4 gap-3">
                            <div class="relative">
                                <input type="search" id="warehouse-search" class="form-input form-input-sm ps-9" placeholder="Search warehouse name or address">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3">
                                    <i data-lucide="search" class="size-3.5 flex items-center text-default-500 fill-default-100"></i>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2 items-center flex-wrap">
                            <span class="text-sm text-default-500"><?= count($warehouses) ?> warehouse<?= count($warehouses) === 1 ? '' : 's' ?> found</span>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <div class="overflow-x-auto">
                            <div class="min-w-full inline-block align-middle">
                                <div class="overflow-hidden">
                                    <table id="warehouse-table" class="min-w-full divide-y divide-default-200">
                                        <thead class="bg-default-150">
                                            <tr class="text-sm font-normal text-default-700">
                                                <th scope="col" class="px-3.5 py-3 text-start">ID</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Name</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Address</th>
                                                <th scope="col" class="px-3.5 py-3 text-end">Stocked Items</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Registered</th>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-default-100">
                                            <?php if (empty($warehouses)): ?>
                                                <tr>
                                                    <td colspan="5" class="px-3.5 py-16 text-center text-default-500">
                                                        <i data-lucide="warehouse" class="size-10 mx-auto mb-3 text-default-300"></i>
                                                        <p class="font-medium">No warehouses registered yet. Use the form above to add one.</p>
                                                    </td>
                                                </tr>
                                            <?php else: foreach ($warehouses as $warehouse): ?>
                                                <tr class="text-default-800 font-normal text-sm">
                                                    <td class="px-3.5 py-3 text-default-600"><?= (int) $warehouse['id'] ?></td>
                                                    <td class="py-3 px-3.5 text-primary font-medium"><?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($warehouse['address'] !== '' ? $warehouse['address'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-end font-semibold"><?= (int) $warehouse['item_count'] ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars(date('M d, Y H:i', strtotime($warehouse['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Warehouses Table End -->

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
            const searchInput = document.getElementById('warehouse-search');
            const rows = document.querySelectorAll('#warehouse-table tbody tr');

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
