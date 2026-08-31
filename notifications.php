<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

require_once 'backend/config.php';

$currentUserId = (int) $_SESSION['user_id'];
$notifications = [];
$unreadCount = 0;


if ($connect && !$connect->connect_error) {
    $res = $connect->query(
        'SELECT COUNT(*) AS c FROM notifications WHERE user_id = ' . $currentUserId . ' AND is_read = 0'
    );
    $unreadCount = $res ? (int) $res->fetch_assoc()['c'] : 0;

    $res = $connect->query(
        'SELECT id, type, title, message, link, is_read, created_at
         FROM notifications
         WHERE user_id = ' . $currentUserId . '
         ORDER BY is_read ASC, created_at DESC
         LIMIT 100'
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Notifications | PICS</title>
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
                    <h4 class="text-default-900 text-lg font-semibold">Notifications</h4>

                    <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                        <a href="index.php" class="text-sm font-medium text-default-700">PICS</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700" aria-current="page">Notifications</a>
                    </div>
                </div>
                <!-- Page Title End -->

                <div class="grid lg:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-5 mb-5">
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Total Notifications</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= count($notifications) ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <i data-lucide="bell-ring" class="size-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Unread</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= $unreadCount ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-warning/10 flex items-center justify-center">
                                    <i data-lucide="mail-open" class="size-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-default-500 text-sm">Read</p>
                                    <h4 class="text-xl font-semibold text-default-900 mt-1.5"><?= count($notifications) - $unreadCount ?></h4>
                                </div>
                                <div class="size-11 rounded-lg bg-success/10 flex items-center justify-center">
                                    <i data-lucide="check-check" class="size-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title">Your Notifications</h6>
                    </div>

                    <div class="flex flex-col">
                        <div class="overflow-x-auto">
                            <div class="min-w-full inline-block align-middle">
                                <div class="overflow-hidden">
                                    <table id="notification-table" class="min-w-full divide-y divide-default-200">
                                        <thead class="bg-default-150">
                                            <tr class="text-sm font-normal text-default-700">
                                                <th scope="col" class="px-3.5 py-3 text-start">Type</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Message</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Received</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Status</th>
                                                <th scope="col" class="px-3.5 py-3 text-end">Action</th>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-default-100">
                                            <?php if (empty($notifications)): ?>
                                                <tr>
                                                    <td colspan="5" class="px-3.5 py-16 text-center text-default-500">
                                                        <i data-lucide="bell-off" class="size-10 mx-auto mb-3 text-default-300"></i>
                                                        <p class="font-medium">No notifications for you yet.</p>
                                                    </td>
                                                </tr>
                                            <?php else: foreach ($notifications as $ntf): ?>
                                                <tr class="text-default-800 font-normal text-sm <?= (int) $ntf['is_read'] === 0 ? 'bg-primary/5' : '' ?>">
                                                    <td class="px-3.5 py-3">
                                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium rounded px-2 py-0.5 <?= $ntf['type'] === 'production_job' ? 'bg-info/10 text-info' : 'bg-primary/10 text-primary' ?>">
                                                            <i data-lucide="<?= $ntf['type'] === 'production_job' ? 'factory' : 'bell-ring' ?>" class="size-3"></i>
                                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $ntf['type'])), ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-3 px-3.5">
                                                        <p class="font-medium text-default-800"><?= htmlspecialchars($ntf['title'], ENT_QUOTES, 'UTF-8') ?></p>
                                                        <p class="text-default-500 text-xs"><?= htmlspecialchars($ntf['message'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    </td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars(date('M d, Y H:i', strtotime($ntf['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="py-3 px-3.5">
                                                        <?php if ((int) $ntf['is_read'] === 0): ?>
                                                            <span class="text-xs font-medium text-primary"><i data-lucide="circle-dot" class="size-3"></i> Unread</span>
                                                        <?php else: ?>
                                                            <span class="text-xs font-medium text-success"><i data-lucide="check-circle-2" class="size-3"></i> Read</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-3.5 py-3 text-end">
                                                        <?php if ((int) $ntf['is_read'] === 0): ?>
                                                            <a href="backend/mark_notification_read.php?id=<?= (int) $ntf['id'] ?>" class="btn btn-sm bg-primary text-white whitespace-nowrap">
                                                                <i data-lucide="external-link" class="size-3.5 me-1"></i>Open
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="<?= htmlspecialchars($ntf['link'] !== '' ? $ntf['link'] : 'notifications.php', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm bg-default-150 text-default-700 whitespace-nowrap">
                                                                <i data-lucide="arrow-right" class="size-3.5 me-1"></i>View
                                                            </a>
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

            </main>

            <!-- Footer Start -->
            <footer class="mt-auto footer flex items-center py-5 border-t border-default-200">
<div class="lg:px-8 px-6 w-full flex md:justify-between justify-center gap-4">
                </div>
            </footer>
            <!-- Footer End -->
        </div>
    </div>

</body>
</html>