<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

require_once 'backend/config.php';

$flashSuccess = $_SESSION['success_message'] ?? '';
$flashError   = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Only the Production Manager (and admins) can fill in waste records.
$canRecordWaste = authHasRole(['administrator', 'admin', 'production manager']);

$records = [];
if (isset($connect) && !$connect->connect_error) {
    $result = $connect->query('SELECT * FROM waste_records ORDER BY record_date DESC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
    }
}

// Production jobs available to record waste against.
$jobs = [];
$jobNumbers = [];
if (isset($connect) && !$connect->connect_error) {
    $result = $connect->query("SELECT id, job_number FROM production_jobs WHERE status = 'Running' ORDER BY id DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
            $jobNumbers[(int) $row['id']] = $row['job_number'];
        }
    }
}

// Materials ordered per job (from approved material requests) and how much
// waste has already been recorded against each job + material combination.
$jobMaterials = [];    // job id => [ ['name' => ..., 'ordered' => float, 'unit' => ...] ]
$wastedByJobItem = []; // "jobId|item name" => total wasted qty
if (isset($connect) && !$connect->connect_error) {
    $result = $connect->query(
        "SELECT mr.production_job_id AS job_id,
                mri.item_name AS name,
                SUM(COALESCE(mri.approved_quantity, mri.quantity)) AS ordered_qty,
                MAX(mri.unit) AS unit
         FROM material_requests mr
         INNER JOIN material_request_items mri ON mri.material_request_id = mr.id
         WHERE mr.status = 'Approved' AND mr.production_job_id IS NOT NULL
         GROUP BY mr.production_job_id, mri.item_name
         ORDER BY mr.production_job_id DESC, mri.item_name ASC"
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $jobMaterials[(int) $row['job_id']][] = [
                'name'    => $row['name'],
                'ordered' => (float) $row['ordered_qty'],
                'unit'    => $row['unit'],
            ];
        }
    }

    $result = $connect->query(
        "SELECT production_job_id, waste_type, SUM(quantity) AS wasted
         FROM waste_records
         WHERE production_job_id IS NOT NULL
         GROUP BY production_job_id, waste_type"
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $wastedByJobItem[(int) $row['production_job_id'] . '|' . $row['waste_type']] = (float) $row['wasted'];
        }
    }
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

$totalRecords = count($records);
$totalQuantity = 0;
foreach ($records as $record) {
    $totalQuantity += (int) $record['quantity'];
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
    <title>Waste Management | PICS</title>
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

        /* Buttons: match theme (black in light, white in dark) */
        .btn-primary,
        .btn.bg-primary,
        .btn.bg-transparent {
            background-color: #000 !important;
            border-color: #000 !important;
            border-radius: 0.5rem !important;
            color: #fff !important;
        }
        .btn-primary:hover,
        .btn.bg-primary:hover,
        .btn.bg-transparent:hover {
            background-color: #262626 !important;
            border-color: #262626 !important;
            color: #fff !important;
        }
        html[data-theme="dark"] .btn-primary,
        html[data-theme="dark"] .btn.bg-primary,
        html[data-theme="dark"] .btn.bg-transparent {
            background-color: #ffffff !important;
            border-color: #ffffff !important;
            color: #000000 !important;
        }
        html[data-theme="dark"] .btn-primary:hover,
        html[data-theme="dark"] .btn.bg-primary:hover,
        html[data-theme="dark"] .btn.bg-transparent:hover {
            background-color: #e5e5e5 !important;
            color: #000000 !important;
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
                    <h4 class="text-default-900 text-lg font-semibold">Waste Management</h4>

                    <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                        <a href="index.php" class="text-sm font-medium text-default-700">PICS</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700">Waste Management</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700" aria-current="page">Waste Records</a>
                    </div>
                </div>
                <!-- Page Title End -->

                <?php if ($flashSuccess !== ''): ?>
                    <div id="flash-success" class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success print:hidden">
                        <span class="flex items-center gap-2"><i data-lucide="check-circle-2" class="size-4"></i><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
                        <button type="button" onclick="this.closest('#flash-success').remove()" class="shrink-0 text-success/70 hover:text-success"><i data-lucide="x" class="size-4"></i></button>
                    </div>
                <?php endif; ?>

                <?php if ($flashError !== ''): ?>
                    <div id="flash-error" class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger print:hidden">
                        <span class="flex items-center gap-2"><i data-lucide="alert-circle" class="size-4"></i><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></span>
                        <button type="button" onclick="this.closest('#flash-error').remove()" class="shrink-0 text-danger/70 hover:text-danger"><i data-lucide="x" class="size-4"></i></button>
                    </div>
                <?php endif; ?>

                <?php if ($canRecordWaste): ?>
                    <div class="card mb-5">
                        <div class="card-header flex justify-between items-center border-b border-default-200">
                            <div>
                                <h6 class="card-title text-base font-semibold text-default-900">Record Waste</h6>
                                <p class="text-xs text-default-500 mt-0.5">Track production scrap and material waste against active jobs.</p>
                            </div>
                            
                        </div>

                        <form method="post" action="backend/waste_record_save.php" id="waste-form">
                            <div class="card-body p-6 space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                                    <!-- Production Job -->
                                    <div>
                                        <label class="block text-sm font-medium text-default-800 mb-1.5" for="production_job">
                                            Production Job <span class="text-danger">*</span>
                                        </label>
                                        <div class="relative">
                                            <select id="production_job" name="production_job_id" class="form-input" required>
                                                <option value="" disabled selected>Select production job...</option>
                                                <?php foreach ($jobs as $job): ?>
                                                    <option value="<?= (int) $job['id'] ?>">
                                                        <?= htmlspecialchars($job['job_number'], ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <p class="text-xs text-default-400 mt-1">Choose the job to pull approved materials from.</p>
                                    </div>

                                    <!-- Waste Type / Material -->
                                    <div>
                                        <label class="block text-sm font-medium text-default-800 mb-1.5" for="waste_type">
                                            Material / Waste Type <span class="text-danger">*</span>
                                        </label>
                                        <select id="waste_type" name="waste_type" class="form-input" required disabled>
                                            <option value="" disabled selected>Select a job first...</option>
                                        </select>
                                        <p class="text-xs text-default-400 mt-1">Populated automatically from approved materials.</p>
                                    </div>

                                    <!-- Quantity -->
                                    <div>
                                        <label class="block text-sm font-medium text-default-800 mb-1.5" for="quantity">
                                            Waste Quantity <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" id="quantity" name="quantity" class="form-input" min="1" step="1" placeholder="Enter waste quantity" required disabled>
                                        <p class="text-xs text-default-400 mt-1" id="qty-helper-text">Cannot exceed remaining ordered quantity.</p>
                                    </div>

                                    <!-- Date -->
                                    <div>
                                        <label class="block text-sm font-medium text-default-800 mb-1.5" for="record_date">
                                            Record Date <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" id="record_date" name="record_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                                        <p class="text-xs text-default-400 mt-1">Date when waste occurred.</p>
                                    </div>

                                    <!-- Reason -->
                                    <div>
                                        <label class="block text-sm font-medium text-default-800 mb-1.5" for="reason">
                                            Reason / Notes <span class="text-default-400 font-normal text-xs">(Optional)</span>
                                        </label>
                                        <input type="text" id="reason" name="reason" class="form-input" placeholder="e.g. Defective cut, machine calibration...">
                                        <p class="text-xs text-default-400 mt-1">Brief note explaining the cause of waste.</p>
                                    </div>
                                </div>

                                <!-- Dynamic Material Stats Summary Banner -->
                                <div id="material-info" class="hidden rounded-xl border border-primary/20 bg-primary/5 p-4 transition-all"></div>
                            </div>

                            <div class="card-footer border-t border-default-200 px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-3 bg-default-50/50">
                                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                                    <button type="reset" class="btn btn-sm bg-default-150 text-default-700 hover:bg-default-200">
                                        <i data-lucide="rotate-ccw" class="size-3.5"></i>
                                        Reset
                                    </button>
                                    <button type="submit" class="btn btn-sm bg-primary text-white hover:bg-primary/90">
                                        <i data-lucide="plus-circle" class="size-4"></i>
                                        Save Waste Record
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards Start -->
                <div class="grid lg:grid-cols-2 md:grid-cols-2 grid-cols-1 gap-5 mb-5">
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Total Records</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $totalRecords ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <i data-lucide="recycle" class="size-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Total Quantity</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= number_format($totalQuantity) ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-danger/10 flex items-center justify-center">
                                    <i data-lucide="trash-2" class="size-5 text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Stats Cards End -->

                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title">Waste Records</h6>
                    </div>

                    <div class="card-header">
                        <div class="md:flex items-center md:space-y-0 space-y-4 gap-3">
                            <div class="relative">
                                <input type="search" id="table-search" class="form-input form-input-sm ps-9" placeholder="Search job, waste type, reason or employee">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3">
                                    <i data-lucide="search" class="size-3.5 flex items-center text-default-500 fill-default-100"></i>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2 items-center flex-wrap">
                            <span class="text-sm text-default-500"><?= $totalRecords ?> record<?= $totalRecords === 1 ? '' : 's' ?> found</span>
                            <button type="button" data-hs-overlay="#printReportModal" class="btn btn-sm bg-transparent border border-dashed border-primary text-primary hover:bg-primary/10">
                                <i data-lucide="printer" class="size-4"></i>
                                Print Report
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <div class="overflow-x-auto">
                            <div class="min-w-full inline-block align-middle">
                                <div class="overflow-hidden">
                                    <table id="waste-table" class="min-w-full divide-y divide-default-200">
                                        <thead class="bg-default-150">
                                            <tr class="text-sm font-normal text-default-700">
                                                <th scope="col" class="px-3.5 py-3 text-start">Job</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Waste Type</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Quantity</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Reason</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Employee</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Date</th>
                                                <?php if ($canRecordWaste): ?>
                                                    <th scope="col" class="px-3.5 py-3 text-start">Action</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-default-100">
                                            <?php if (empty($records)): ?>
                                                <tr>
                                                    <td colspan="<?= $canRecordWaste ? 7 : 6 ?>" class="px-3.5 py-16 text-center text-default-500">
                                                        <i data-lucide="recycle" class="size-10 mx-auto mb-3 text-default-300"></i>
                                                        <p class="font-medium">No waste records found yet.</p>
                                                    </td>
                                                </tr>
                                            <?php else: foreach ($records as $record): ?>
                                                <?php $recordJobNumber = $record['production_job_id'] ? ($jobNumbers[(int) $record['production_job_id']] ?? '—') : '—'; ?>
                                                <tr class="text-default-800 font-normal text-sm">
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($recordJobNumber, ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="px-3.5 py-3 text-sm text-primary font-medium"><?= htmlspecialchars($record['waste_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= number_format((int) $record['quantity']) ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($record['reason'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($record['employee'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars(date('M d, Y', strtotime($record['record_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <?php if ($canRecordWaste): ?>
                                                        <td class="py-3 px-3.5">
                                                            <button type="button"
                                                                    class="btn btn-sm bg-transparent border border-dashed border-danger text-danger hover:bg-danger/10 open-delete"
                                                                    title="Delete record"
                                                                    data-hs-overlay="#deleteWasteModal"
                                                                    data-record-id="<?= (int) $record['id'] ?>"
                                                                    data-waste-type="<?= htmlspecialchars($record['waste_type'], ENT_QUOTES, 'UTF-8') ?>">
                                                                <i data-lucide="trash-2" class="size-4"></i>
                                                                Delete
                                                            </button>
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
            const rows = document.querySelectorAll('#waste-table tbody tr');

            searchInput.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                rows.forEach(function (row) {
                    if (row.querySelector('td[colspan]')) return;
                    row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
                });
            });

            document.querySelectorAll('.open-delete').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.getElementById('delete-waste-id').value = this.dataset.recordId || '';
                    document.getElementById('delete-waste-type').textContent = this.dataset.wasteType || '';
                });
            });

            const reportForm = document.getElementById('print-report-form');
            if (reportForm) {
                const fromInput = document.getElementById('report_from');
                const toInput = document.getElementById('report_to');

                reportForm.addEventListener('submit', function (e) {
                    const from = fromInput.value;
                    const to = toInput.value;
                    if (from && to && from > to) {
                        e.preventDefault();
                        alert('The "From" date cannot be after the "To" date.');
                    }
                });
            }
        })();
    </script>

    <script>
        (function () {
            const jobMaterials = <?= json_encode($jobMaterials, $jsonFlags) ?>;
            const wastedByJobItem = <?= json_encode($wastedByJobItem, $jsonFlags) ?>;

            const form = document.getElementById('waste-form');
            const jobSelect = document.getElementById('production_job');
            const typeSelect = document.getElementById('waste_type');
            const qtyInput = document.getElementById('quantity');
            const infoBox = document.getElementById('material-info');

            function formatQty(n) {
                return Number(n).toLocaleString();
            }

            function selectedItem() {
                const materials = jobMaterials[jobSelect.value] || [];
                for (let i = 0; i < materials.length; i++) {
                    if (materials[i].name === typeSelect.value) {
                        return materials[i];
                    }
                }
                return null;
            }

            function remainingFor(item) {
                if (!item) return 0;
                const wasted = parseFloat(wastedByJobItem[jobSelect.value + '|' + item.name] || 0);
                return Math.max(0, parseFloat(item.ordered) - wasted);
            }

            function refreshInfo() {
                const item = selectedItem();
                if (!item) {
                    infoBox.classList.add('hidden');
                    infoBox.innerHTML = '';
                    const helper = document.getElementById('qty-helper-text');
                    if (helper) helper.textContent = 'Cannot exceed remaining ordered quantity.';
                    return;
                }
                const availableBeforeInput = remainingFor(item);
                const wastedPrior = parseFloat(item.ordered) - availableBeforeInput;
                const unit = item.unit ? ' ' + item.unit : '';
                const typedQty = parseFloat(qtyInput.value) || 0;
                const newRemaining = availableBeforeInput - typedQty;

                qtyInput.max = Math.floor(availableBeforeInput);

                const helper = document.getElementById('qty-helper-text');
                if (helper) {
                    if (typedQty > availableBeforeInput) {
                        helper.innerHTML = '<span class="text-danger font-medium">Exceeds available ordered amount! Max allowable is ' + formatQty(Math.floor(availableBeforeInput)) + unit + '</span>';
                    } else if (typedQty > 0) {
                        helper.innerHTML = 'Deducting <strong>' + formatQty(typedQty) + unit + '</strong> &rarr; Net Remaining will be <strong>' + formatQty(Math.max(0, newRemaining)) + unit + '</strong>';
                    } else {
                        helper.textContent = 'Max allowable: ' + formatQty(Math.floor(availableBeforeInput)) + unit;
                    }
                }

                let remainingBadgeColor = 'text-success';
                let remainingLabel = 'total waste';
                if (newRemaining < 0) {
                    remainingBadgeColor = 'text-danger';
                } else if (newRemaining === 0 && typedQty > 0) {
                    remainingBadgeColor = 'text-warning';
                }

                infoBox.innerHTML =
                    '<div class="flex items-center justify-between flex-wrap gap-4 w-full">' +
                        '<div class="flex items-center gap-2.5">' +
                            '<div class="size-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary">' +
                                '<i data-lucide="layers" class="size-4.5"></i>' +
                            '</div>' +
                            '<div>' +
                                '<p class="text-xs text-default-500 font-medium">Selected Material</p>' +
                                '<p class="text-sm font-semibold text-default-900">' + item.name + '</p>' +
                            '</div>' +
                        '</div>' +
                        '<div class="flex items-center gap-6 flex-wrap">' +
                            '<div>' +
                                '<p class="text-xs text-default-500">Ordered Total</p>' +
                                '<p class="text-sm font-semibold text-default-800">' + formatQty(item.ordered) + unit + '</p>' +
                            '</div>' +
                            '<div>' +
                                '<p class="text-xs text-default-500">Previous Waste</p>' +
                                '<p class="text-sm font-semibold text-default-700">' + formatQty(wastedPrior) + unit + '</p>' +
                            '</div>' +
                            '<div>' +
                                '<p class="text-xs text-default-500">Available Before</p>' +
                                '<p class="text-sm font-semibold text-default-800">' + formatQty(availableBeforeInput) + unit + '</p>' +
                            '</div>' +
                            '<div>' +
                                '<p class="text-xs text-default-500">' + (typedQty > 0 ? remainingLabel : 'Available Remaining') + '</p>' +
                                '<p class="text-sm font-bold ' + remainingBadgeColor + '">' +
                                    formatQty(newRemaining) + unit +
                                    (typedQty > 0 ? ' <span class="text-xs font-normal text-default-400">(-' + formatQty(typedQty) + ')</span>' : '') +
                                '</p>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                infoBox.classList.remove('hidden');
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
            }

            jobSelect.addEventListener('change', function () {
                const materials = jobMaterials[this.value] || [];
                typeSelect.innerHTML = '';
                qtyInput.value = '';
                qtyInput.disabled = true;

                if (!materials.length) {
                    typeSelect.appendChild(new Option('No ordered materials for this job...', '', true, true));
                    typeSelect.disabled = true;
                    refreshInfo();
                    return;
                }

                typeSelect.appendChild(new Option('Select material...', '', true, true));
                materials.forEach(function (m) {
                    const unitStr = m.unit ? ' ' + m.unit : '';
                    typeSelect.appendChild(new Option(m.name + ' (Ordered: ' + formatQty(m.ordered) + unitStr + ')', m.name));
                });
                typeSelect.disabled = false;
                refreshInfo();
            });

            typeSelect.addEventListener('change', function () {
                qtyInput.value = '';
                qtyInput.disabled = !this.value;
                refreshInfo();
            });

            qtyInput.addEventListener('input', function () {
                refreshInfo();
            });

            form.addEventListener('reset', function () {
                setTimeout(function () {
                    typeSelect.innerHTML = '<option value="" disabled selected>Select a job first...</option>';
                    typeSelect.disabled = true;
                    qtyInput.value = '';
                    qtyInput.disabled = true;
                    refreshInfo();
                }, 10);
            });

            form.addEventListener('submit', function (e) {
                const item = selectedItem();
                if (!item) return;

                const remaining = Math.floor(remainingFor(item));
                const q = parseInt(qtyInput.value, 10);
                if (!(q > 0) || q > remaining) {
                    e.preventDefault();
                    alert('Waste quantity must be between 1 and ' + remaining +
                          ' (ordered ' + formatQty(item.ordered) + ' minus already recorded waste).');
                }
            });
        })();
    </script>

    <!-- Print Report Modal -->
    <div id="printReportModal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" data-hs-overlay-options='{"backdropClasses":"fixed inset-0 cursor-pointer"}' role="dialog" tabindex="-1" aria-labelledby="printReportModal-label">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 scale-95 opacity-0 ease-in-out transition-all duration-200 max-w-md w-full mx-auto px-4 py-14 min-h-[calc(100%-56px)] flex items-center">
            <div class="card w-full flex flex-col bg-card border border-default-200 rounded-xl pointer-events-auto">
                <div class="card-header">
                    <h3 id="printReportModal-label" class="font-semibold text-base text-default-800 dark:text-white">Print Waste Records Report</h3>
                    <button type="button" class="size-5 text-default-800" aria-label="Close" data-hs-overlay="#printReportModal">
                        <span class="sr-only">Close</span>
                        <i data-lucide="x" class="size-5"></i>
                    </button>
                </div>

                <form id="print-report-form" method="get" action="backend/waste_records_report.php" class="flex flex-col">
                    <div class="card-body py-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-default-800 mb-1.5">Report Period <span class="text-danger">*</span></label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-default-500 mb-1" for="report_from">From</label>
                                    <input type="date" id="report_from" name="from" class="form-input">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-default-500 mb-1" for="report_to">To</label>
                                    <input type="date" id="report_to" name="to" class="form-input">
                                </div>
                            </div>
                            <p class="text-xs text-default-400 mt-1">Select the date range for the report. Leave both empty for all records.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-default-800 mb-1.5">Report Format</label>
                            <div class="flex items-center gap-5">
                                <label class="flex items-center gap-2 text-sm text-default-700 cursor-pointer">
                                    <input type="radio" name="format" value="pdf" class="form-radio" checked>
                                    <i data-lucide="file-text" class="size-4 text-danger"></i>
                                    PDF (Print/Save)
                                </label>
                                <label class="flex items-center gap-2 text-sm text-default-700 cursor-pointer">
                                    <input type="radio" name="format" value="excel" class="form-radio">
                                    <i data-lucide="file-spreadsheet" class="size-4 text-success"></i>
                                    Excel (.xlsx)
                                </label>
                            </div>
                            <p class="text-xs text-default-400 mt-1">Excel (.xlsx) works with the latest Microsoft Excel versions. PDF can be printed or saved.</p>
                        </div>
                    </div>

                    <div class="card-footer mt-4 flex gap-2 md:justify-end">
                        <button type="button" class="btn btn-sm bg-transparent border border-default-300 text-default-600 hover:bg-default-150" data-hs-overlay="#printReportModal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-sm bg-primary text-white hover:bg-primary/90">
                            <i data-lucide="printer" class="size-4 me-1"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Waste Record Modal -->
    <div id="deleteWasteModal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" data-hs-overlay-options='{"backdropClasses":"fixed inset-0 cursor-pointer"}' role="dialog" tabindex="-1" aria-labelledby="deleteWasteModal-label">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 scale-95 opacity-0 ease-in-out transition-all duration-200 max-w-md w-full mx-auto px-4 py-14 min-h-[calc(100%-56px)] flex items-center">
            <div class="card w-full flex flex-col bg-card border border-default-200 rounded-xl pointer-events-auto">
                <div class="card-header">
                    <h3 id="deleteWasteModal-label" class="font-semibold text-base text-default-800 dark:text-white">Delete Waste Record</h3>
                    <button type="button" class="size-5 text-default-800" aria-label="Close" data-hs-overlay="#deleteWasteModal">
                        <span class="sr-only">Close</span>
                        <i data-lucide="x" class="size-5"></i>
                    </button>
                </div>

                <form id="delete-waste-form" method="post" action="backend/waste_record_delete.php" class="flex flex-col">
                    <input type="hidden" name="id" id="delete-waste-id" value="">

                    <div class="card-body py-6">
                        <div class="flex items-start gap-3">
                            <div class="size-11 rounded-lg bg-danger/10 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="trash-2" class="size-5 text-danger"></i>
                            </div>
                            <div>
                                <p class="text-sm text-default-700">
                                    Are you sure you want to delete this waste record?
                                </p>
                                <p class="text-sm text-default-500 mt-1">
                                    Waste type: <span id="delete-waste-type" class="font-medium text-default-800"></span>
                                </p>
                                <p class="text-xs text-danger mt-2">This action cannot be undone.</p>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer mt-4 flex gap-2 md:justify-end">
                        <button type="button" class="btn btn-sm bg-transparent border border-default-300 text-default-600 hover:bg-default-150" data-hs-overlay="#deleteWasteModal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-sm bg-danger text-white hover:bg-danger/90">
                            <i data-lucide="trash-2" class="size-3.5 me-1"></i>Delete
                        </button>
                    </div>
                </form>
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