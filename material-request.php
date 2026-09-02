<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

require_once 'backend/config.php';

$flashSuccess = $_SESSION['success_message'] ?? '';
$flashError   = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$selectedJobId = (int) ($_GET['job_id'] ?? 0);
$jobs = [];
$items = [];

if ($connect && !$connect->connect_error) {
    $res = $connect->query(
        "SELECT
            pj.id,
            pj.job_number,
            pj.quantity,
            pj.due_date,
            pj.machine,
            pj.operator,
            pj.job_priority,
            c.id AS customer_id,
            c.customer_code,
            c.company_name AS customer,
            c.phone,
            c.email,
            c.address,
            s.name AS service
         FROM production_jobs pj
         LEFT JOIN customers c ON c.id = pj.customer_id
         LEFT JOIN services s ON s.id = pj.service_id
         ORDER BY pj.id DESC"
    );
    if ($res) { while ($r = $res->fetch_assoc()) { $jobs[] = $r; } }

    $res = $connect->query(
        "SELECT NULL AS item_id,
                NULL AS name,
                NULL AS category,
                NULL AS unit, NULL AS color, NULL AS machine, NULL AS gsm, NULL AS type
         WHERE 1 = 0"
    );
    if ($res) { while ($r = $res->fetch_assoc()) { $items[] = $r; } }

    $stockItems = [];
    $res = $connect->query("SELECT DISTINCT item AS name FROM stock WHERE TRIM(item) <> '' ORDER BY item");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $known = false;
            foreach ($items as $it) {
                if (strcasecmp(trim($it['name']), trim($r['name'])) === 0) { $known = true; break; }
            }
            if (!$known) { $stockItems[] = ['item_id' => null, 'name' => $r['name'], 'category' => null, 'attributes' => []]; }
        }
    }
    $items = array_merge($items, $stockItems);

    foreach ($items as $idx => $it) {
        $attrs = [];
        if (!empty($it['unit'])) $attrs[] = ['name' => 'unit', 'value' => $it['unit']];
        if (!empty($it['color'])) $attrs[] = ['name' => 'color', 'value' => $it['color']];
        if (!empty($it['machine'])) $attrs[] = ['name' => 'machine', 'value' => $it['machine']];
        if (!empty($it['gsm'])) $attrs[] = ['name' => 'gsm', 'value' => $it['gsm']];
        if (!empty($it['type'])) $attrs[] = ['name' => 'type', 'value' => $it['type']];
        $items[$idx]['attributes'] = $attrs;
    }
}

$displayName = isset($_SESSION['user_name']) && $_SESSION['user_name'] !== '' ? $_SESSION['user_name'] : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Material Request | PICS</title>
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
        .material-select {
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            padding-right: 2.5rem;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%23a3a3a3' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.1em;
        }
        .material-input {
            padding-right: 2rem;
        }
        .material-caret {
            position: absolute;
            top: 50%;
            right: 0.5rem;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            padding: 0.25rem;
            background: transparent;
            border: none;
            cursor: pointer;
        }
        .material-combo.open .material-dropdown {
            display: block;
        }
        .material-dropdown {
            max-height: 16rem;
            overflow-y: auto;
        }
        .material-dropdown .material-opt {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            border-bottom: 1px solid #f1f1f4;
        }
        .material-dropdown .material-opt:last-child {
            border-bottom: none;
        }
        .material-dropdown .material-opt:hover,
        .material-dropdown .material-opt.active {
            background-color: #f3f4f6;
        }
        .material-dropdown .material-opt-empty {
            padding: 0.75rem;
            color: #6b7280;
            font-size: 0.875rem;
        }
        .material-dropdown .material-opt-left {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
            min-width: 0;
        }
        .material-dropdown .material-opt-name {
            font-size: 0.875rem;
            color: #1f2937;
            font-weight: 500;
        }
        .material-dropdown .material-opt-sub {
            font-size: 0.75rem;
            color: #6b7280;
            white-space: normal;
            word-break: break-word;
        }

        /* Theme-aware black/white action buttons */
        .btn-invert {
            background-color: #18181b; /* zinc-900, black in light theme */
            color: #ffffff;
            border-color: transparent;
        }
        .btn-invert:hover {
            background-color: #27272a;
        }
        [data-theme="dark"] .btn-invert,
        .dark .btn-invert {
            background-color: #ffffff; /* white in dark theme */
            color: #09090b;
        }
        [data-theme="dark"] .btn-invert:hover,
        .dark .btn-invert:hover {
            background-color: #e4e4e7;
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
                <h4 class="text-default-900 text-lg font-semibold">New Material Request</h4>
                <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                    <a href="index.php" class="text-sm font-medium text-default-700">PICS</a>
                    <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                    <a href="#" class="text-sm font-medium text-default-700" aria-current="page">Material Request</a>
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

            <form method="post" action="backend/material_request_save.php">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title">Production Job</h6>
                    </div>
                    <div class="card-body grid md:grid-cols-2 gap-5">
                        <div>
                            <label class="form-label" for="job-search">Production Job</label>
                            <div class="relative">
                                <input type="text" id="job-search" class="form-input" placeholder="Search job by number, customer or product..." autocomplete="off">
                                <input type="hidden" id="production_job_id" name="production_job_id" value="<?= (int) $selectedJobId ?>">
                                <div id="job-search-results" class="hidden absolute z-10 mt-1 w-full max-h-56 overflow-y-auto rounded-lg border border-default-200 bg-card shadow-lg"></div>
                            </div>
                            <p class="text-xs text-default-500 mt-1">Type to search. Newest jobs appear first.</p>
                        </div>
                        <div>
                            <label class="form-label" for="notes">Request Notes</label>
                            <input type="text" id="notes" name="notes" class="form-input" placeholder="Optional notes for this request...">
                        </div>
                    </div>
                </div>

                <div id="job-details-card" class="card mb-4 hidden">
                    <div class="card-header">
                        <h6 class="card-title">Customer &amp; Job Details</h6>
                    </div>
                    <div class="card-body grid md:grid-cols-2 gap-5">
                        <div class="rounded-lg border border-default-200 p-4">
                            <p class="text-sm font-semibold text-default-900 mb-3">Customer</p>
                            <div class="space-y-2 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-default-500 shrink-0">Name</span>
                                    <span id="jc-customer" class="font-medium text-default-800 text-end">-</span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-default-500 shrink-0">Code</span>
                                    <span id="jc-code" class="font-medium text-default-800 text-end">-</span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-default-500 shrink-0">Phone</span>
                                    <span id="jc-phone" class="font-medium text-default-800 text-end">-</span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-default-500 shrink-0">Email</span>
                                    <span id="jc-email" class="font-medium text-default-800 text-end break-all">-</span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-default-500 shrink-0">Address</span>
                                    <span id="jc-address" class="font-medium text-default-800 text-end">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-lg border border-default-200 p-4">
                            <p class="text-sm font-semibold text-default-900 mb-3">Production Job</p>
                            <div class="space-y-2 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-default-500 shrink-0">Job Number</span>
                                    <span id="jc-job" class="font-medium text-default-800 text-end">-</span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-default-500 shrink-0">Service</span>
                                    <span id="jc-service" class="font-medium text-default-800 text-end">-</span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-default-500 shrink-0">Quantity</span>
                                    <span id="jc-qty" class="font-medium text-default-800 text-end">-</span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-default-500 shrink-0">Due Date</span>
                                    <span id="jc-due" class="font-medium text-default-800 text-end">-</span>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-default-500 shrink-0">Priority</span>
                                    <span id="jc-priority" class="font-medium text-default-800 text-end">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header flex items-center justify-between">
                        <div>
                            <h6 class="card-title">Raw Materials Needed</h6>
                            <p class="card-subtitle mb-0">Add the materials required for this production job, e.g. "Paper A3 150 GM, 5 Reams".</p>
                        </div>
                        <button type="button" onclick="addItemRow()" class="btn btn-sm btn-invert whitespace-nowrap">
                            <i data-lucide="plus" class="size-3.5 me-1"></i>Add Material
                        </button>
                    </div>
                    <div class="card-body">
                        <table class="min-w-full divide-y divide-default-200">
                            <thead class="bg-default-150">
                                <tr class="text-sm font-normal text-default-700 text-start">
                                    <th class="px-3 py-3 text-start">Material</th>
                                    <th class="px-3 py-3 text-start">Quantity</th>
                                    <th class="px-3 py-3 text-start">Notes</th>
                                    <th class="px-3 py-3 text-end"></th>
                                </tr>
                            </thead>
                            <tbody id="items-body" class="divide-y divide-default-100">
                                <tr class="item-row">
                                    <td class="px-3 py-3">
                                        <input type="hidden" name="item_id[]" value="">
                                        <div class="relative material-combo">
                                            <input type="text" name="item_name[]" class="form-input form-input-sm material-input" placeholder="Search or select a material..." autocomplete="off" required>
                                            <button type="button" class="material-caret" tabindex="-1" aria-label="Toggle material list">
                                                <i data-lucide="chevron-down" class="size-3.5"></i>
                                            </button>
                                            <div class="material-dropdown hidden absolute z-20 mt-1 min-w-80 max-w-xl overflow-y-auto rounded-lg border border-default-200 bg-card shadow-lg"></div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input type="number" name="quantity[]" class="form-input form-input-sm integer-input" min="1" step="1" placeholder="5" required>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input type="text" name="item_notes[]" class="form-input form-input-sm" placeholder="Optional">
                                    </td>
                                    <td class="px-3 py-3 text-end">
                                        <button type="button" onclick="removeItemRow(this)" class="btn btn-sm bg-danger/10 text-danger">
                                            <i data-lucide="trash-2" class="size-3.5"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="mt-4 flex items-center gap-3">
                            <button type="submit" name="action" value="submit" class="btn btn-invert">
                                <i data-lucide="send" class="size-4 me-1"></i>Submit for Approval
                            </button>
                            <button type="submit" name="action" value="save_draft" class="btn bg-default-150 text-default-700">
                                <i data-lucide="save" class="size-4 me-1"></i>Save as Draft
                            </button>
                            <button type="reset" class="btn bg-default-150 text-default-700">Clear</button>
                        </div>
                    </div>
                </div>
            </form>

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
    const jobsData = <?= json_encode($jobs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function jobLabel(j) {
        const service = j.service ? ' · ' + j.service : '';
        return (j.job_number || '') + service + ' - ' + (j.customer || 'No customer');
    }

    function showJobDetails(jobId) {
        const card = document.getElementById('job-details-card');
        const job = jobsData.find(function (j) { return String(j.id) === String(jobId); });
        if (!job) {
            card.classList.add('hidden');
            return;
        }
        document.getElementById('jc-customer').textContent = job.customer || '-';
        document.getElementById('jc-code').textContent = job.customer_code || '-';
        document.getElementById('jc-phone').textContent = job.phone || '-';
        document.getElementById('jc-email').textContent = job.email || '-';
        document.getElementById('jc-address').textContent = job.address || '-';
        document.getElementById('jc-job').textContent = job.job_number || '-';
        document.getElementById('jc-service').textContent = job.service || '-';
        document.getElementById('jc-qty').textContent = job.quantity !== null ? job.quantity : '-';
        document.getElementById('jc-due').textContent = job.due_date || '-';
        document.getElementById('jc-priority').textContent = job.job_priority || '-';
        card.classList.remove('hidden');
    }

    function selectJob(job) {
        document.getElementById('production_job_id').value = job.id;
        document.getElementById('job-search').value = jobLabel(job);
        document.getElementById('job-search-results').classList.add('hidden');
        showJobDetails(job.id);
    }

    function renderJobResults(q) {
        const list = document.getElementById('job-search-results');
        q = (q || '').trim().toLowerCase();
        const matches = q === ''
            ? jobsData.slice(0, 50)
            : jobsData.filter(function (j) {
                return (j.job_number || '').toLowerCase().indexOf(q) !== -1 ||
                       (j.customer || '').toLowerCase().indexOf(q) !== -1 ||
                       (j.service || '').toLowerCase().indexOf(q) !== -1;
            });

        list.innerHTML = '';
        if (matches.length === 0) {
            const div = document.createElement('div');
            div.className = 'px-3.5 py-3 text-sm text-default-500';
            div.textContent = 'No jobs found';
            list.appendChild(div);
            return;
        }
        matches.forEach(function (j) {
            const div = document.createElement('div');
            div.className = 'px-3.5 py-2.5 text-sm cursor-pointer hover:bg-default-100 flex items-center justify-between gap-3';
            div.setAttribute('data-job', String(j.id));
            const left = document.createElement('span');
            left.className = 'font-medium text-default-800';
            left.textContent = jobLabel(j);
            const right = document.createElement('span');
            right.className = 'text-xs text-default-500 shrink-0';
            right.textContent = j.service || '';
            div.appendChild(left);
            div.appendChild(right);
            div.addEventListener('click', function () { selectJob(j); });
            list.appendChild(div);
        });
        list.classList.remove('hidden');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('job-search');
        const list = document.getElementById('job-search-results');
        const hidden = document.getElementById('production_job_id');
        let activeIdx = -1;

        <?php if ($selectedJobId > 0): ?>
            const pre = jobsData.find(function (j) { return String(j.id) === String(<?= (int) $selectedJobId ?>); });
            if (pre) {
                input.value = jobLabel(pre);
                hidden.value = pre.id;
                showJobDetails(pre.id);
            }
        <?php endif; ?>

        input.addEventListener('focus', function () { renderJobResults(input.value); });
        input.addEventListener('input', function () { renderJobResults(input.value); });

        input.addEventListener('keydown', function (e) {
            const items = list.querySelectorAll('[data-job]');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (items.length) {
                    activeIdx = (activeIdx + 1) % items.length;
                    items.forEach(function (el, i) { el.classList.toggle('bg-default-100', i === activeIdx); });
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (items.length) {
                    activeIdx = (activeIdx - 1 + items.length) % items.length;
                    items.forEach(function (el, i) { el.classList.toggle('bg-default-100', i === activeIdx); });
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIdx >= 0 && items[activeIdx]) items[activeIdx].click();
            } else if (e.key === 'Escape') {
                list.classList.add('hidden');
            }
        });

        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !list.contains(e.target)) {
                list.classList.add('hidden');
            }
        });
    });
</script>

<script>
    const materialsData = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function materialDisplay(it) {
        let parts = [it.name];
        if (it.category) parts.push(it.category);
        if (it.attributes && it.attributes.length) {
            it.attributes.forEach(function (a) { parts.push(a.value); });
        }
        return parts.filter(Boolean).join(' — ');
    }

    function materialSub(it) {
        const parts = [];
        if (it.category) parts.push(it.category);
        if (it.attributes && it.attributes.length) {
            it.attributes.forEach(function (a) { parts.push(a.name + ': ' + a.value); });
        }
        return parts.join(' · ');
    }

    function openMaterialCombo(combo) {
        renderMaterialOptions(combo);
        combo.classList.add('open');
    }

    function closeMaterialCombo(combo) {
        combo.classList.remove('open');
    }

    function renderMaterialOptions(combo) {
        const input = combo.querySelector('.material-input');
        const dropdown = combo.querySelector('.material-dropdown');
        const hidden = combo.closest('tr').querySelector('input[name="item_id[]"]');
        const q = (input.value || '').trim().toLowerCase();

        const matches = materialsData.filter(function (it) {
            if (q === '') return true;
            return (it.name || '').toLowerCase().indexOf(q) !== -1 ||
                   (it.category || '').toLowerCase().indexOf(q) !== -1 ||
                   (it.attributes || []).some(function (a) {
                       return (a.name + ' ' + a.value).toLowerCase().indexOf(q) !== -1;
                   });
        });

        dropdown.innerHTML = '';
        if (matches.length === 0) {
            const div = document.createElement('div');
            div.className = 'material-opt-empty';
            div.textContent = 'No materials found';
            dropdown.appendChild(div);
            return;
        }
        matches.forEach(function (it) {
            const div = document.createElement('div');
            div.className = 'material-opt';
            div.setAttribute('data-item-id', it.item_id || '');

            const nameWrap = document.createElement('div');
            nameWrap.className = 'material-opt-left';

            const nameSpan = document.createElement('span');
            nameSpan.className = 'material-opt-name';
            nameSpan.textContent = it.name;
            nameWrap.appendChild(nameSpan);

            const sub = materialSub(it);
            if (sub) {
                const subSpan = document.createElement('span');
                subSpan.className = 'material-opt-sub';
                subSpan.textContent = sub;
                nameWrap.appendChild(subSpan);
            }
            div.appendChild(nameWrap);

            div.addEventListener('click', function () {
                input.value = it.name;
                hidden.value = it.item_id || '';
                closeMaterialCombo(combo);
            });
            dropdown.appendChild(div);
        });
    }

    function wireMaterialCombo(combo) {
        const input = combo.querySelector('.material-input');
        const caret = combo.querySelector('.material-caret');
        const dropdown = combo.querySelector('.material-dropdown');
        const hidden = combo.closest('tr').querySelector('input[name="item_id[]"]');
        let activeIdx = -1;

        function selectActive() {
            const items = dropdown.querySelectorAll('.material-opt');
            if (activeIdx >= 0 && items[activeIdx]) items[activeIdx].click();
        }

        caret.addEventListener('click', function (e) {
            e.stopPropagation();
            if (combo.classList.contains('open')) {
                closeMaterialCombo(combo);
            } else {
                openMaterialCombo(combo);
            }
        });

        input.addEventListener('focus', function () { openMaterialCombo(combo); });
        input.addEventListener('input', function () {
            hidden.value = '';
            renderMaterialOptions(combo);
            combo.classList.add('open');
        });

        input.addEventListener('keydown', function (e) {
            const items = dropdown.querySelectorAll('.material-opt');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = (activeIdx + 1) % items.length;
                items.forEach(function (el, i) { el.classList.toggle('active', i === activeIdx); });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = (activeIdx - 1 + items.length) % items.length;
                items.forEach(function (el, i) { el.classList.toggle('active', i === activeIdx); });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                selectActive();
            } else if (e.key === 'Escape') {
                closeMaterialCombo(combo);
            }
        });

        document.addEventListener('click', function (e) {
            if (!combo.contains(e.target)) {
                closeMaterialCombo(combo);
            }
        });
    }

    function addItemRow() {
        const tbody = document.getElementById('items-body');
        const first = tbody.querySelector('.item-row');
        const clone = first.cloneNode(true);
        clone.querySelectorAll('input').forEach(function (input) { input.value = ''; });
        tbody.appendChild(clone);
        wireMaterialCombo(clone.querySelector('.material-combo'));
        if (window.lucide) lucide.createIcons();
    }

    function removeItemRow(btn) {
        const tbody = document.getElementById('items-body');
        if (tbody.querySelectorAll('.item-row').length > 1) {
            btn.closest('tr').remove();
            if (window.lucide) lucide.createIcons();
        }
    }

    document.addEventListener('input', function (e) {
        const el = e.target;
        if (!el || !el.classList || !el.classList.contains('integer-input')) return;
        if (el.value === '') return;
        const n = Math.trunc(Number(el.value));
        if (Number.isFinite(n)) {
            const str = String(n);
            if (el.value !== str) el.value = str;
        } else {
            el.value = '';
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.material-combo').forEach(function (combo) {
            wireMaterialCombo(combo);
        });
    });
</script>

</body>
</html>
