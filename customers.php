<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

require_once 'backend/config.php';

$message = '';
$messageType = '';

$displayName = isset($_SESSION['user_name']) && $_SESSION['user_name'] !== '' ? $_SESSION['user_name'] : 'User';
$canDelete = authHasRole(['administrator', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($connect) && !$connect->connect_error) {
    $companyName = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    try {
        if ($companyName === '') {
            throw new RuntimeException('Customer company name is required.');
        }

        $check = $connect->prepare('SELECT id FROM customers WHERE company_name = ?');
        $check->bind_param('s', $companyName);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows > 0;
        $check->close();

        if ($exists) {
            throw new RuntimeException('A customer with that company name already exists.');
        }

        $code = 'CUS-' . str_pad((string) ((int) time()), 6, '0', STR_PAD_LEFT);
        $stmt = $connect->prepare(
            'INSERT INTO customers (customer_code, company_name, phone, email, address) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssss', $code, $companyName, $phone, $email, $address);
        $stmt->execute();
        $stmt->close();

        $message = "Customer \"{$companyName}\" registered successfully.";
        $messageType = 'success';
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }

    if ($messageType === 'success') {
        header('Location: customers.php');
        exit();
    }
}

$flashSuccess = $_SESSION['success_message'] ?? '';
$flashError   = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
if ($flashSuccess !== '') {
    $message = $flashSuccess;
    $messageType = 'success';
} elseif ($flashError !== '') {
    $message = $flashError;
    $messageType = 'danger';
}

$customers = [];
if (isset($connect) && !$connect->connect_error) {
    $result = $connect->query('SELECT id, customer_code, company_name, phone, email, address, created_at FROM customers ORDER BY company_name ASC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
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
    <title>Customers | PICS</title>
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
                    <h4 class="text-default-900 text-lg font-semibold">Customers</h4>

                    <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                        <a href="index.php" class="text-sm font-medium text-default-700">PICS</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700" aria-current="page">Customers</a>
                    </div>
                </div>
                <!-- Page Title End -->

                <?php if ($message !== ''): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards Start -->
                <div class="grid lg:grid-cols-2 md:grid-cols-2 grid-cols-1 gap-5 mb-5">
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Total Customers</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= count($customers) ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <i data-lucide="users-round" class="size-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Registered</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= count($customers) ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-success/10 flex items-center justify-center">
                                    <i data-lucide="badge-check" class="size-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Stats Cards End -->

                <!-- Register Customer Card Start -->
                <div class="card mb-5">
                    <div class="card-header">
                        <h6 class="card-title">Register New Customer</h6>
                    </div>

                    <form method="POST" action="customers.php">
                        <div class="card-body grid lg:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="company_name" class="form-input" placeholder="e.g. Acme Corp" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Phone</label>
                                <input type="text" name="phone" class="form-input" placeholder="e.g. +255 123 456 789">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Email</label>
                                <input type="email" name="email" class="form-input" placeholder="e.g. billing@acme.com">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Address</label>
                                <input type="text" name="address" class="form-input" placeholder="e.g. 123 Business Rd">
                            </div>
                            <div class="flex items-end justify-end">
                                <button type="submit" class="btn btn-primary w-full">
                                    <i data-lucide="plus" class="size-4"></i>
                                    Register Customer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- Register Customer Card End -->

                <!-- Customers Table Start -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title">Registered Customers</h6>
                    </div>

                    <div class="card-header">
                        <div class="md:flex items-center md:space-y-0 space-y-4 gap-3">
                            <div class="relative">
                                <input type="search" id="customer-search" class="form-input form-input-sm ps-9" placeholder="Search name, phone or email">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3">
                                    <i data-lucide="search" class="size-3.5 flex items-center text-default-500 fill-default-100"></i>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2 items-center flex-wrap">
                            <span class="text-sm text-default-500"><?= count($customers) ?> customer<?= count($customers) === 1 ? '' : 's' ?> found</span>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <div class="overflow-x-auto">
                            <div class="min-w-full inline-block align-middle">
                                <div class="overflow-hidden">
                                    <table id="customer-table" class="min-w-full divide-y divide-default-200">
                                        <thead class="bg-default-150">
                                            <tr class="text-sm font-normal text-default-700">
                                                <th scope="col" class="px-3.5 py-3 text-start">Code</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Company</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Phone</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Email</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Address</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Registered</th>
                                                <?php if ($canDelete): ?>
                                                    <th scope="col" class="px-3.5 py-3 text-end">Actions</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-default-100">
                                            <?php if (empty($customers)): ?>
                                                <tr>
                                                    <td colspan="<?= $canDelete ? 7 : 6 ?>" class="px-3.5 py-16 text-center text-default-500">
                                                        <i data-lucide="users-round" class="size-10 mx-auto mb-3 text-default-300"></i>
                                                        <p class="font-medium">No customers registered yet. Use the form above to add one.</p>
                                                    </td>
                                                </tr>
                                            <?php else: foreach ($customers as $customer): ?>
                                                <tr class="text-default-800 font-normal text-sm">
                                                    <td class="px-3.5 py-3 text-default-500"><?= htmlspecialchars($customer['customer_code'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-primary font-medium"><?= htmlspecialchars($customer['company_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($customer['phone'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($customer['email'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($customer['address'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars(date('M d, Y', strtotime($customer['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <?php if ($canDelete): ?>
                                                        <td class="py-3 px-3.5 text-end">
                                                            <button type="button"
                                                                    class="btn btn-sm bg-danger/10 text-danger hover:bg-danger/20 open-delete"
                                                                    title="Delete customer"
                                                                    data-hs-overlay="#deleteCustomerModal"
                                                                    data-customer-id="<?= (int) $customer['id'] ?>"
                                                                    data-customer-name="<?= htmlspecialchars($customer['company_name'], ENT_QUOTES, 'UTF-8') ?>">
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
                <!-- Customers Table End -->

            </main>

            <!-- Footer Start -->
            <footer class="mt-auto footer flex items-center py-5 border-t border-default-200">
<div class="lg:px-8 px-6 w-full flex md:justify-between justify-center gap-4">
                </div>
            </footer>
            <!-- Footer End -->
        </div>
    </div>

    <!-- Delete Customer Modal -->
    <div id="deleteCustomerModal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" data-hs-overlay-options='{"backdropClasses":"fixed inset-0 cursor-pointer"}' role="dialog" tabindex="-1" aria-labelledby="deleteCustomerModal-label">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 scale-95 opacity-0 ease-in-out transition-all duration-200 max-w-md w-full mx-auto px-4 py-14 min-h-[calc(100%-56px)] flex items-center">
            <div class="card w-full flex flex-col bg-card border border-default-200 rounded-xl pointer-events-auto">
                <div class="card-header">
                    <h3 id="deleteCustomerModal-label" class="font-semibold text-base text-default-800 dark:text-white">Delete Customer</h3>
                    <button type="button" class="size-5 text-default-800" aria-label="Close" data-hs-overlay="#deleteCustomerModal">
                        <span class="sr-only">Close</span>
                        <i data-lucide="x" class="size-5"></i>
                    </button>
                </div>

                <form id="delete-customer-form" method="post" action="backend/customer_delete.php" class="flex flex-col">
                    <input type="hidden" name="id" id="delete-customer-id" value="">

                    <div class="card-body py-6">
                        <div class="flex items-start gap-3">
                            <div class="size-11 rounded-lg bg-danger/10 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="trash-2" class="size-5 text-danger"></i>
                            </div>
                            <div>
                                <p class="text-sm text-default-700">
                                    Are you sure you want to delete this customer?
                                </p>
                                <p class="text-sm text-default-500 mt-1">
                                    Customer: <span id="delete-customer-name" class="font-medium text-default-800"></span>
                                </p>
                                <p class="text-xs text-danger mt-2">This action cannot be undone.</p>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer mt-4 flex gap-2 md:justify-end">
                        <button type="button" class="btn btn-sm bg-transparent border border-default-300 text-default-600 hover:bg-default-150" data-hs-overlay="#deleteCustomerModal">
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

    <script>
        (function () {
            const searchInput = document.getElementById('customer-search');
            const rows = document.querySelectorAll('#customer-table tbody tr');

            searchInput.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                rows.forEach(function (row) {
                    if (row.querySelector('td[colspan]')) return;
                    row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
                });
            });

            document.querySelectorAll('.open-delete').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.getElementById('delete-customer-id').value = this.dataset.customerId || '';
                    document.getElementById('delete-customer-name').textContent = this.dataset.customerName || '';
                });
            });
        })();
    </script>

</body>
</html>