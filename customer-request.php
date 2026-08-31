<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

require_once 'backend/config.php';

$flashSuccess = $_SESSION['success_message'] ?? '';
$flashError   = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$customers = [];
$services  = [];

if ($connect && !$connect->connect_error) {
    $res = $connect->query('SELECT id, company_name FROM customers ORDER BY company_name');
    if ($res) { while ($r = $res->fetch_assoc()) { $customers[] = $r; } }

    $res = $connect->query("SELECT id, name FROM services WHERE is_active = 1 ORDER BY name");
    if ($res) { while ($r = $res->fetch_assoc()) { $services[] = $r; } }
}

$displayName = isset($_SESSION['user_name']) && $_SESSION['user_name'] !== '' ? $_SESSION['user_name'] : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New Production Job Request | PICS</title>
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
                <h4 class="text-default-900 text-lg font-semibold">Printing Service Request</h4>
                <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                    <a href="index.php" class="text-sm font-medium text-default-700">PICS</a>
                    <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                    <a href="#" class="text-sm font-medium text-default-700" aria-current="page">Printing Service Request</a>
                </div>
            </div>
            <!-- Page Title End -->

            <?php if ($flashError !== ''): ?>
                <div id="flash-error" class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger print:hidden">
                    <span class="flex items-center gap-2"><i data-lucide="alert-circle" class="size-4"></i><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></span>
                    <button type="button" onclick="this.closest('#flash-error').remove()" class="shrink-0 text-danger/70 hover:text-danger"><i data-lucide="x" class="size-4"></i></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h6 class="card-title">Customer Request Details</h6>
                    <p class="card-subtitle mb-0">Submitting this form creates a printing production job and notifies the production &amp; store managers.</p>
                </div>
                <div class="card-body">
                    <form method="post" action="backend/create_job.php" class="grid md:grid-cols-2 gap-5">
                        <div>
                            <label class="form-label" for="customer_id">Customer</label>
                            <select id="customer_id" name="customer_id" class="form-input" required>
                                <option value="">Select customer</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['company_name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="product_id">Service</label>
                            <select id="product_id" name="product_id" class="form-input" required>
                                <option value="">Select service</option>
                                <?php foreach ($services as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" class="form-input" min="1" required>
                        </div>

                        <div>
                            <label class="form-label" for="due_date">Due Date</label>
                            <input type="date" id="due_date" name="due_date" class="form-input" required>
                        </div>

                        <div>
                            <label class="form-label" for="job_priority">Job Priority</label>
                            <select id="job_priority" name="job_priority" class="form-input">
                                <option value="Low">Low</option>
                                <option value="Normal" selected>Normal</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>

                        <div class="md:col-span-2 flex items-center gap-3">
                            <button type="submit" class="btn btn-primary bg-primary text-white">
                                <i data-lucide="send" class="size-4 me-1"></i>Submit Request
                            </button>
                            <button type="reset" class="btn bg-default-150 text-default-700">Clear</button>
                        </div>
                    </form>
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

    <!-- Job Created Confirmation Modal -->
    <div id="jobCreatedModal" class="hidden fixed inset-0 z-90 items-center justify-center p-4" style="display:none" role="dialog" aria-modal="true" aria-labelledby="jobCreatedTitle">
        <div id="jobCreatedBackdrop" class="absolute inset-0 bg-black/50 opacity-0 transition-opacity duration-150"></div>
        <div id="jobCreatedPanel" class="relative w-full max-w-sm rounded-xl bg-card shadow-xl opacity-0 scale-95 transition-all duration-150">
            <div class="flex flex-col items-center px-6 pt-6 pb-4 text-center">
                <div class="size-14 rounded-full bg-success/10 text-success flex items-center justify-center mb-4">
                    <i data-lucide="check-circle-2" class="size-6"></i>
                </div>
                <h5 id="jobCreatedTitle" class="text-base font-semibold text-default-900">Job Created</h5>
                <p id="jobCreatedMessage" class="mt-2 text-sm text-default-500"></p>
            </div>
            <div class="flex items-center justify-center gap-3 border-t border-default-200 px-6 py-4">
                <button type="button" onclick="closeJobCreatedModal()" class="btn bg-success text-white hover:bg-success/90">
                    <i data-lucide="check" class="size-4 me-1"></i>OK
                </button>
            </div>
        </div>
    </div>

    <script>
        function openJobCreatedModal(message) {
            document.getElementById('jobCreatedMessage').textContent = message;
            const modal = document.getElementById('jobCreatedModal');
            const backdrop = document.getElementById('jobCreatedBackdrop');
            const panel = document.getElementById('jobCreatedPanel');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(function () {
                backdrop.classList.remove('opacity-0');
                panel.classList.remove('opacity-0', 'scale-95');
            });
        }

        function closeJobCreatedModal() {
            const modal = document.getElementById('jobCreatedModal');
            const backdrop = document.getElementById('jobCreatedBackdrop');
            const panel = document.getElementById('jobCreatedPanel');
            backdrop.classList.add('opacity-0');
            panel.classList.add('opacity-0', 'scale-95');
            setTimeout(function () {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 150);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeJobCreatedModal();
        });

        <?php if ($flashSuccess !== ''): ?>
            document.addEventListener('DOMContentLoaded', function () {
                openJobCreatedModal(<?= json_encode($flashSuccess, ENT_QUOTES) ?>);
            });
        <?php endif; ?>
    </script>

</body>
</html>