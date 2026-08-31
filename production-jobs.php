<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

require_once 'backend/config.php';
require_once 'backend/production_helpers.php';

$records = [];
if (isset($connect) && !$connect->connect_error) {
    $sql = 'SELECT
                pj.id,
                pj.job_number,
                pj.quantity,
                pj.due_date,
                pj.machine,
                pj.job_priority,
                pj.status,
                c.company_name AS customer_name,
                COALESCE(s.name, \'—\') AS product_name,
                u.Username AS created_by_name
            FROM production_jobs pj
            LEFT JOIN customers c ON c.id = pj.customer_id
            LEFT JOIN services s ON s.id = pj.service_id
            LEFT JOIN users u ON u.id = pj.created_by
            ORDER BY pj.id DESC';
    $result = $connect->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
    }
}

// ---- Production / Project manager (or admin) may update the status ---------
$canUpdateStatus = false;
$canCreateJob    = authHasRole(['administrator', 'admin', 'production manager']);
if (!empty($_SESSION['logged_in']) && (int) ($_SESSION['user_id'] ?? 0) > 0 && isset($connect) && !$connect->connect_error) {
    $uid = (int) $_SESSION['user_id'];
    $canUpdateStatus = jobIsUserRole($connect, $uid, ['production manager', 'project manager', 'administrator', 'admin']);
}

$totalJobs = count($records);
$runningCount = 0;
$completedCount = 0;
$pendingCount = 0;
foreach ($records as $record) {
    switch ($record['status']) {
        case 'Running':
            $runningCount++;
            break;
        case 'Completed':
            $completedCount++;
            break;
        case 'Draft':
        case 'Submitted':
        case 'Pending Approval':
        case 'Approved':
        case '':
            $pendingCount++;
            break;
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

function priorityBadge(string $priority): string {
    switch ($priority) {
        case 'Urgent':
            return 'danger';
        case 'High':
            return 'warning';
        case 'Low':
            return 'success';
        case 'Normal':
        default:
            return 'info';
    }
}

function statusBadge(string $status): string {
    switch ($status) {
        case 'Running':        return 'bg-info/10 text-info';
        case 'Completed':      return 'bg-success/10 text-success';
        case 'Approved':       return 'bg-primary/10 text-primary';
        case 'Rejected':       return 'bg-danger/10 text-danger';
        case 'Cancelled':      return 'bg-default-500/10 text-default-600';
        case 'Pending Approval':
        case 'Submitted':
        case 'Draft':
        default:               return 'bg-warning/10 text-warning';
    }
}

function statusLabel(string $status): string {
    return $status === '' ? 'Draft' : $status;
}

$updateableStatuses = ['Approved', 'Running', 'Completed', 'Cancelled'];

$displayName = isset($_SESSION['user_name']) && $_SESSION['user_name'] !== '' ? $_SESSION['user_name'] : 'User';
$flashSuccess = $_SESSION['success_message'] ?? '';
$flashError   = $_SESSION['error_message'] ?? '';
$flashInfo    = $_SESSION['info_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['info_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Production Jobs | PICS</title>
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
                    <h4 class="text-default-900 text-lg font-semibold">Production Jobs</h4>

                    <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                        <a href="index.php" class="text-sm font-medium text-default-700">PICS</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700">Production</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700" aria-current="page">Production Jobs</a>
                    </div>
                </div>
                <!-- Page Title End -->

                <?php if ($flashSuccess): ?>
                    <div class="flex items-center gap-2 rounded-lg bg-success/10 text-success px-4 py-3 mb-4 text-sm font-medium border border-success/20">
                        <i data-lucide="check-circle-2" class="size-4"></i>
                        <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <?php if ($flashError): ?>
                    <div class="flex items-center gap-2 rounded-lg bg-danger/10 text-danger px-4 py-3 mb-4 text-sm font-medium border border-danger/20">
                        <i data-lucide="alert-circle" class="size-4"></i>
                        <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <?php if ($flashInfo): ?>
                    <div class="flex items-center gap-2 rounded-lg bg-info/10 text-info px-4 py-3 mb-4 text-sm font-medium border border-info/20">
                        <i data-lucide="info" class="size-4"></i>
                        <?= htmlspecialchars($flashInfo, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards Start -->
                <div class="grid lg:grid-cols-4 md:grid-cols-2 grid-cols-1 gap-5 mb-5">
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Total Jobs</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $totalJobs ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <i data-lucide="factory" class="size-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Pending / Approved</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $pendingCount ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-warning/10 flex items-center justify-center">
                                    <i data-lucide="clock" class="size-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Running</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $runningCount ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-info/10 flex items-center justify-center">
                                    <i data-lucide="loader-circle" class="size-5 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Completed</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $completedCount ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-success/10 flex items-center justify-center">
                                    <i data-lucide="check-circle-2" class="size-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Stats Cards End -->

                <!-- Jobs Table Start -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title">Production Jobs</h6>
                    </div>

                    <div class="card-header">
                        <div class="md:flex items-center md:space-y-0 space-y-4 gap-3">
                            <div class="relative">
                                <input type="search" id="table-search" class="form-input form-input-sm ps-9" placeholder="Search job, customer or product">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3">
                                    <i data-lucide="search" class="size-3.5 flex items-center text-default-500 fill-default-100"></i>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2 items-center flex-wrap">
                            <span class="text-sm text-default-500"><?= $totalJobs ?> job<?= $totalJobs === 1 ? '' : 's' ?> found</span>
                            <?php if ($canCreateJob): ?>
                                <a href="customer-request.php" class="btn btn-sm bg-transparent border border-dashed border-primary text-primary hover:bg-primary/10">
                                    <i data-lucide="plus" class="size-4"></i>
                                    New Job
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <div class="overflow-x-auto">
                            <div class="min-w-full inline-block align-middle">
                                <div class="overflow-hidden">
                                    <table id="job-table" class="min-w-full divide-y divide-default-200">
                                        <thead class="bg-default-150">
                                            <tr class="text-sm font-normal text-default-700">
                                                <th scope="col" class="px-3.5 py-3 text-start">Job Number</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Customer</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Product / Service</th>
                                                <th scope="col" class="px-3.5 py-3 text-end">Quantity</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Due Date</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Priority</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Status</th>
                                                <?php if ($canUpdateStatus): ?>
                                                    <th scope="col" class="px-3.5 py-3 text-start">Update Status</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-default-100">
                                            <?php if (empty($records)): ?>
                                                <tr>
                                                    <td colspan="<?= $canUpdateStatus ? 8 : 7 ?>" class="px-3.5 py-16 text-center text-default-500">
                                                        <i data-lucide="factory" class="size-10 mx-auto mb-3 text-default-300"></i>
                                                        <p class="font-medium">No production jobs yet.</p>
                                                    </td>
                                                </tr>
                                            <?php else: foreach ($records as $record): ?>
                                                <tr class="text-default-800 font-normal text-sm">
                                                    <td class="px-3.5 py-3 text-sm text-primary font-medium"><?= htmlspecialchars($record['job_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-800 font-medium"><?= htmlspecialchars($record['customer_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($record['product_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-end text-default-600"><?= (int) $record['quantity'] ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars(date('M d, Y', strtotime($record['due_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600">
                                                        <span class="text-xs font-medium rounded-full px-2.5 py-1 bg-<?= priorityBadge($record['job_priority']) ?>/10 text-<?= priorityBadge($record['job_priority']) ?>">
                                                            <?= htmlspecialchars($record['job_priority'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-3 px-3.5 text-default-600">
                                                        <span class="text-xs font-medium rounded-full px-2.5 py-1 <?= statusBadge($record['status']) ?>">
                                                            <?= htmlspecialchars(statusLabel($record['status']), ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>
                                                    <?php if ($canUpdateStatus): ?>
                                                        <td class="py-3 px-3.5">
                                                            <form method="post" action="backend/production_status_update.php" class="flex items-center gap-2">
                                                                <input type="hidden" name="job_id" value="<?= (int) $record['id'] ?>">
                                                                <select name="status" class="form-input form-input-sm" style="width:auto">
                                                                    <?php foreach ($updateableStatuses as $opt):
                                                                        $sel = ($opt === $record['status']) ? ' selected' : ''; ?>
                                                                        <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>"<?= $sel ?>><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <button type="submit" class="btn btn-sm bg-primary text-white hover:bg-primary/90">Update</button>
                                                            </form>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Jobs Table End -->

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
            const rows = document.querySelectorAll('#job-table tbody tr');

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
