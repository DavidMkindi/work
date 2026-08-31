<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

require_once 'backend/config.php';
require_once 'backend/production_helpers.php';

$flashSuccess = $_SESSION['success_message'] ?? '';
$flashError   = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$currentUserId = (int) $_SESSION['user_id'];
$isApprover = jobIsUserRole($connect, $currentUserId, ['administrator', 'admin', 'store manager']);
$canCreateRequest = authHasRole(['administrator', 'admin', 'production manager']);

$records = [];
$statusCounts = ['Submitted' => 0, 'Pending Approval' => 0, 'Approved' => 0, 'Rejected' => 0];

if (isset($connect) && !$connect->connect_error) {
    $sql = 'SELECT
                mr.id,
                mr.mr_number,
                mr.requested_date,
                mr.status,
                mr.approval_notes,
                mr.notes,
                pj.job_number,
                req.Username AS requested_by_name,
                app.Username AS approved_by_name
            FROM material_requests mr
            LEFT JOIN production_jobs pj ON pj.id = mr.production_job_id
            LEFT JOIN users req ON req.id = mr.requested_by
            LEFT JOIN users app ON app.id = mr.approved_by
            ORDER BY mr.id DESC';
    $result = $connect->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
            $s = $row['status'];
            if (isset($statusCounts[$s])) {
                $statusCounts[$s]++;
            }
        }
    }
}

$total = count($records);

function statusBadge(string $status): string {
    switch ($status) {
        case 'Approved':
            return 'success';
        case 'Rejected':
            return 'danger';
        case 'Draft':
            return 'secondary';
        case 'Pending Approval':
            return 'warning';
        case 'Submitted':
        default:
            return 'primary';
    }
}

function isAwaitingApproval(string $status): bool {
    return in_array($status, ['Submitted', 'Pending Approval'], true);
}

$displayName = isset($_SESSION['user_name']) && $_SESSION['user_name'] !== '' ? $_SESSION['user_name'] : 'User';

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

$reviewData = [];
if (isset($connect) && !$connect->connect_error && !empty($records)) {
    $pendingIds = [];
    foreach ($records as $record) {
        if (isAwaitingApproval($record['status'])) {
            $pendingIds[] = (int) $record['id'];
        }
    }

    if (!empty($pendingIds)) {
        $in = implode(',', $pendingIds);

        $jobInfo = [];
        $res = $connect->query(
"SELECT mr.id, pj.job_number, c.company_name AS customer, s.name AS service,
                    pj.quantity AS job_quantity, pj.due_date, pj.job_priority
             FROM material_requests mr
             LEFT JOIN production_jobs pj ON pj.id = mr.production_job_id
             LEFT JOIN customers c ON c.id = pj.customer_id
             LEFT JOIN services s ON s.id = pj.service_id
             WHERE mr.id IN ($in)"
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $jobInfo[(int) $r['id']] = $r;
            }
        }

        $lineGroups = [];
        $res = $connect->query(
            "SELECT material_request_id, id, item_name, unit, quantity
             FROM material_request_items
             WHERE material_request_id IN ($in)"
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $lineGroups[(int) $r['material_request_id']][] = $r;
            }
        }

        foreach ($records as $record) {
            $id = (int) $record['id'];
            if (!isAwaitingApproval($record['status'])) {
                continue;
            }
            $lines = $lineGroups[$id] ?? [];
            foreach ($lines as $i => $line) {
                $lines[$i]['available'] = mrGetAvailableStock($connect, $line['item_name']);
            }
            $reviewData[] = [
                'id'            => $id,
                'mr_number'     => $record['mr_number'],
                'job_number'    => $jobInfo[$id]['job_number'] ?? '-',
                'customer'      => $jobInfo[$id]['customer'] ?? '-',
                'service'       => $jobInfo[$id]['service'] ?? '-',
                'job_quantity'  => $jobInfo[$id]['job_quantity'] ?? '-',
                'due_date'      => $jobInfo[$id]['due_date'] ?? '-',
                'job_priority'  => $jobInfo[$id]['job_priority'] ?? '-',
                'requested_by'  => $record['requested_by_name'] ?? '-',
                'notes'         => $record['notes'] ?? '',
                'lines'         => $lines,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Material Requests | PICS</title>
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
                <h4 class="text-default-900 text-lg font-semibold">Material Requests</h4>
                <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                    <a href="index.php" class="text-sm font-medium text-default-700">PICS</a>
                    <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                    <a href="#" class="text-sm font-medium text-default-700">Production</a>
                    <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                    <a href="#" class="text-sm font-medium text-default-700" aria-current="page">Material Requests</a>
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

            <!-- Stats Cards Start -->
            <div class="grid lg:grid-cols-4 md:grid-cols-2 grid-cols-1 gap-5 mb-5">
                <div class="card">
                    <div class="card-body">
                        <p class="text-default-500 text-sm">Total Requests</p>
                        <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $total ?></h4>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <p class="text-default-500 text-sm">Pending Approval</p>
                        <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $statusCounts['Submitted'] + $statusCounts['Pending Approval'] ?></h4>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <p class="text-default-500 text-sm">Approved</p>
                        <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $statusCounts['Approved'] ?></h4>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <p class="text-default-500 text-sm">Rejected</p>
                        <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $statusCounts['Rejected'] ?></h4>
                    </div>
                </div>
            </div>
            <!-- Stats Cards End -->

            <!-- Requests Table Start -->
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title">Material Requests</h6>
                </div>

                <div class="card-header">
                    <div class="flex gap-2 items-center flex-wrap">
<span class="text-sm text-default-500"><?= $total ?> request<?= $total === 1 ? '' : 's' ?> found</span>
                        <?php if ($canCreateRequest): ?>
                            <a href="material-request.php" class="btn btn-sm bg-transparent border border-dashed border-primary text-primary hover:bg-primary/10">
                                <i data-lucide="plus" class="size-4"></i>
                                New Request
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex flex-col">
                    <div class="overflow-x-auto">
                        <div class="min-w-full inline-block align-middle">
                            <div class="overflow-hidden">
                                <table id="mr-table" class="min-w-full divide-y divide-default-200">
                                    <thead class="bg-default-150">
                                        <tr class="text-sm font-normal text-default-700">
                                            <th scope="col" class="px-3.5 py-3 text-start">MR Number</th>
                                            <th scope="col" class="px-3.5 py-3 text-start">Production Job</th>
                                            <th scope="col" class="px-3.5 py-3 text-start">Requested By</th>
                                            <th scope="col" class="px-3.5 py-3 text-start">Date</th>
                                            <th scope="col" class="px-3.5 py-3 text-start">Status</th>
                                            <th scope="col" class="px-3.5 py-3 text-start">Approved By</th>
                                            <th scope="col" class="px-3.5 py-3 text-start">Notes</th>
                                            <th scope="col" class="px-3.5 py-3 text-end">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-default-100">
                                        <?php if (empty($records)): ?>
                                            <tr>
                                                <td colspan="8" class="px-3.5 py-16 text-center text-default-500">
                                                    <i data-lucide="box" class="size-10 mx-auto mb-3 text-default-300"></i>
                                                    <p class="font-medium">No material requests yet.</p>
                                                </td>
                                            </tr>
                                        <?php else: foreach ($records as $record): ?>
                                            <tr class="text-default-800 font-normal text-sm">
                                                <td class="px-3.5 py-3 text-sm text-primary font-medium"><?= htmlspecialchars($record['mr_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($record['job_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="py-3 px-3.5 text-default-800 font-medium"><?= htmlspecialchars($record['requested_by_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars(date('M d, Y', strtotime($record['requested_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="py-3 px-3.5 text-default-600">
                                                    <span class="text-xs font-medium rounded-full px-2.5 py-1 bg-<?= statusBadge($record['status']) ?>/10 text-<?= statusBadge($record['status']) ?>">
                                                        <?= htmlspecialchars($record['status'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                    <?php if (($record['approval_notes'] ?? '') !== ''): ?>
                                                        <p class="text-xs text-default-500 mt-1"><?= htmlspecialchars($record['approval_notes'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($record['approved_by_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($record['notes'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="py-3 px-3.5 text-end">
                                                    <?php if ($isApprover && isAwaitingApproval($record['status'])): ?>
                                                        <button type="button" class="btn btn-sm bg-primary text-white whitespace-nowrap open-review"
                                                                data-hs-overlay="#reviewModal" data-mr-id="<?= (int) $record['id'] ?>">
                                                            <i data-lucide="clipboard-check" class="size-3.5 me-1"></i>Review
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-default-300">&mdash;</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Requests Table End -->

        </main>

        <!-- Footer Start -->
        <footer class="mt-auto footer flex items-center py-5 border-t border-default-200">
<div class="lg:px-8 px-6 w-full flex md:justify-between justify-center gap-4">
                </div>
            </footer>
            <!-- Footer End -->
    </div>
</div>

    <!-- Review Material Request Modal -->
    <div id="reviewModal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" data-hs-overlay-options='{"backdropClasses":"fixed inset-0 cursor-pointer"}' role="dialog" tabindex="-1" aria-labelledby="reviewModal-label">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 scale-95 opacity-0 ease-in-out transition-all duration-200 max-w-md w-full mx-auto px-4 py-14 min-h-[calc(100%-56px)] flex items-center">
            <div class="card w-full flex flex-col bg-card border border-default-200 rounded-xl pointer-events-auto">
                <div class="card-header">
                    <h3 id="reviewModal-label" class="font-semibold text-base text-default-800 dark:text-white">Review Material Request</h3>
                    <button type="button" class="size-5 text-default-800" aria-label="Close" data-hs-overlay="#reviewModal">
                        <span class="sr-only">Close</span>
                        <i data-lucide="x" class="size-5"></i>
                    </button>
                </div>

                <form id="rv-form" method="post" action="backend/material_request_approve.php" class="flex flex-col">
                    <input type="hidden" name="mr_id" id="rv-mr-id" value="">
                    <input type="hidden" name="action" id="rv-action" value="">

                        <div class="card-body py-6">
                        <div id="rv-summary" class="space-y-3 mb-4"></div>

                        <div class="overflow-x-auto rounded-lg border border-default-200">
                            <table class="min-w-full divide-y divide-default-200">
                                <thead class="bg-default-150">
                                    <tr class="text-xs font-normal text-default-700 text-start">
                                        <th class="px-2.5 py-2 text-start">Material</th>
                                        <th class="px-2.5 py-2 text-end">Qty</th>
                                        <th class="px-2.5 py-2 text-end">Stock</th>
                                    </tr>
                                </thead>
                                <tbody id="rv-lines" class="divide-y divide-default-100"></tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            <label class="form-label" for="rv-notes">Notes / Rejection Reason</label>
                            <input type="text" id="rv-notes" name="approval_notes" class="form-input form-input-sm" placeholder="Required when rejecting">
                        </div>
                    </div>

                    <div class="card-footer mt-4 flex gap-2 md:justify-end">
                        <button type="button" class="btn btn-sm bg-danger text-white" onclick="submitReview('reject')">
                            <i data-lucide="x" class="size-3.5 me-1"></i>Reject
                        </button>
                        <button type="button" class="btn btn-sm bg-success text-white" onclick="submitReview('approve')">
                            <i data-lucide="check" class="size-3.5 me-1"></i>Approve
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const reviewData = <?= json_encode($reviewData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        function rvBadge(status) {
            switch (status) {
                case 'Urgent': return '<span class="text-xs font-medium rounded-full px-2.5 py-1 bg-danger/10 text-danger">Urgent</span>';
                case 'High': return '<span class="text-xs font-medium rounded-full px-2.5 py-1 bg-warning/10 text-warning">High</span>';
                case 'Low': return '<span class="text-xs font-medium rounded-full px-2.5 py-1 bg-success/10 text-success">Low</span>';
                default: return '<span class="text-xs font-medium rounded-full px-2.5 py-1 bg-info/10 text-info">Normal</span>';
            }
        }

        function rvRow(label, value, icon) {
            return '<div class="flex items-start justify-between gap-3 text-sm">' +
                '<span class="text-default-600 flex items-center gap-2 shrink-0">' +
                '<i data-lucide="' + icon + '" class="size-4"></i> ' + label +
                '</span>' +
                '<span class="font-medium text-default-900 text-end">' + value + '</span>' +
                '</div>';
}

        function rvLineRow(line) {
            const requested = parseFloat(line.quantity);
            const available = parseFloat(line.available || 0);
            const short = available < requested;
            const stockHtml = '<span class="text-[11px] font-medium rounded-full px-2 py-0.5 ' + (short ? 'bg-danger/10 text-danger' : 'bg-success/10 text-success') + '">' +
                available.toLocaleString(undefined, { maximumFractionDigits: 2 }) + '</span>';
            return '<tr>' +
                '<td class="px-2.5 py-2 text-sm font-medium text-default-800">' + esc(line.item_name) +
                (line.unit ? '<span class="block text-xs font-normal text-default-500">' + esc(line.unit) + '</span>' : '') +
                '</td>' +
                '<td class="px-2.5 py-2 text-sm text-default-600 text-end whitespace-nowrap">' + requested.toLocaleString(undefined, { maximumFractionDigits: 2 }) + '</td>' +
                '<td class="px-2.5 py-2 text-end whitespace-nowrap">' + stockHtml + '</td>' +
                '</tr>';
        }

        function esc(value) {
            const div = document.createElement('div');
            div.textContent = String(value == null ? '' : value);
            return div.innerHTML;
        }

        function openReview(id) {
            const r = reviewData.find(function (x) { return x.id === id; });
            if (!r) return;

            document.getElementById('rv-mr-id').value = r.id;
            document.getElementById('rv-action').value = '';
            document.getElementById('rv-notes').value = '';

            document.getElementById('rv-summary').innerHTML =
                rvRow('MR Number', esc(r.mr_number), 'file-text') +
                rvRow('Job Number', esc(r.job_number), 'briefcase') +
                rvRow('Customer', esc(r.customer), 'building') +
                rvRow('Service', esc(r.service), 'wrench') +
                rvRow('Job Quantity', esc(r.job_quantity), 'layers') +
                rvRow('Due Date', esc(r.due_date), 'calendar') +
                rvRow('Priority', rvBadge(r.job_priority), 'flag') +
                rvRow('Requested By', esc(r.requested_by), 'user');

            window.lucide && lucide.createIcons();

            const tbody = document.getElementById('rv-lines');
            tbody.innerHTML = '';
            (r.lines || []).forEach(function (line) {
                tbody.insertAdjacentHTML('beforeend', rvLineRow(line));
            });
        }

        function submitReview(action) {
            if (action === 'reject' && document.getElementById('rv-notes').value.trim() === '') {
                alert('Please provide a rejection reason.');
                return;
            }
            document.getElementById('rv-action').value = action;
            document.getElementById('rv-form').submit();
        }

        document.querySelectorAll('.open-review').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openReview(parseInt(this.dataset.mrId, 10));
            });
        });
    </script>

</body>
</html>
