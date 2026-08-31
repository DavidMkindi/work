<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requireRole(['administrator', 'admin']);

$message = $_SESSION["success_message"] ?? "";
$error = $_SESSION["error_message"] ?? "";
unset($_SESSION["success_message"], $_SESSION["error_message"]);

require_once 'backend/config.php';

// Load all users with their role and permissions for the admin management table.
$users = [];
if (isset($connect) && !$connect->connect_error) {
    $result = $connect->query('
        SELECT u.id, u.Username, u.email, COALESCE(r.role, "user") AS role
        FROM users u
        LEFT JOIN role r ON r.id = u.id
        ORDER BY u.id ASC
    ');

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['permissions'] = [];
            $permResult = $connect->query('SELECT action FROM permissions WHERE id = ' . (int) $row['id']);
            if ($permResult) {
                while ($perm = $permResult->fetch_assoc()) {
                    $row['permissions'][] = $perm['action'];
                }
            }
            $users[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manage Users | PICS</title>
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

  <link rel="stylesheet" href="./assets/css/style.css">
</head>

<body>

    <div class="wrapper">

        <!-- Start Sidebar -->
        <?php require __DIR__ . '/backend/sidebar.php'; ?>
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
            
            
                        <!-- Setting Offcanvas Button -->
                        
            
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
            <main class="p-6 lg:p-8">
                <div class="max-w-5xl mx-auto">
                    <!-- Manage Users popup (hidden by default, centered modal over the form) -->
                    <div id="manageUsersModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none" role="dialog" aria-modal="true" aria-labelledby="manageUsersTitle">
                        <!-- Soft backdrop (click outside to close) -->
                        <div id="manageUsersBackdrop" class="absolute inset-0 bg-black/25 opacity-0 transition-opacity duration-200" onclick="closeManageUsers()"></div>

                        <!-- Small centered modal card, in front of the form -->
                        <div id="manageUsersPanel" class="relative w-full max-w-md max-h-[15rem] mt-12 bg-card rounded-2xl border border-default-200 shadow-2xl overflow-hidden flex flex-col opacity-0 scale-95 translate-y-4 transition-all duration-300">
                            <div class="flex items-start justify-between gap-3 px-4 py-3 border-b border-default-200 bg-default-50/50 shrink-0">
                                <div>
                                    <h4 id="manageUsersTitle" class="text-sm font-semibold text-default-900">Manage Users</h4>
                                    <p class="text-default-600 mt-0.5 text-[11px] leading-snug">Click <span class="font-medium text-default-900">Edit</span> to load a user into the form.</p>
                                </div>
                                <button type="button" onclick="closeManageUsers()" class="btn size-7 shrink-0 rounded-full bg-default-150 hover:bg-default-200 flex items-center justify-center" aria-label="Close">
                                    <i class="iconify tabler--x text-base"></i>
                                </button>
                            </div>

                            <div class="overflow-y-auto overflow-x-auto grow">
                                <table class="w-full text-left text-xs min-w-[380px]">
                                    <thead class="bg-default-50 text-default-600 sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2 font-semibold">Name</th>
                                            <th class="px-3 py-2 font-semibold">Email</th>
                                            <th class="px-3 py-2 font-semibold">Role</th>
                                            <th class="px-3 py-2 font-semibold text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-default-100">
                                        <?php if (empty($users)): ?>
                                            <tr>
                                                <td colspan="4" class="px-3 py-4 text-center text-default-500">No users found yet.</td>
                                            </tr>
                                        <?php else: foreach ($users as $user): ?>
                                            <tr class="hover:bg-default-50/60">
                                                <td class="px-3 py-2 font-medium text-default-900"><?= htmlspecialchars($user['Username'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="px-3 py-2 text-default-600"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="px-3 py-2">
                                                    <span class="inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 text-[11px] font-medium text-primary"><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </td>
                                                <td class="px-3 py-2 text-end">
                                                    <button type="button" onclick="editUser(<?= (int) $user['id'] ?>)" class="btn border border-default-200 bg-white text-default-700 rounded-lg px-2.5 py-1 text-[11px] font-medium hover:bg-default-50">Edit</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h4 id="formHeading" class="text-2xl font-semibold text-default-900">Create New User</h4>
                            <p id="formSubheading" class="text-default-600 mt-1">Create a new account and assign a role with the desired permissions.</p>
                        </div>
                        <a href="view-users.php" class="btn bg-primary text-white rounded-xl px-5 py-3 text-sm font-medium hover:bg-primary/90 inline-flex items-center gap-2">
                            <i data-lucide="users" class="size-4"></i>
                            Manage Users
                        </a>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="mb-4 rounded-xl border border-success/20 bg-success/10 px-4 py-3 text-sm text-success">
                            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="mb-4 rounded-xl border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="grid lg:grid-cols-[2fr_1fr] gap-6">
                        <div class="bg-card rounded-2xl border border-default-200 p-6 shadow-sm">
                            <form method="post" action="backend/authentication/admin.php" class="space-y-6">
                                <div class="grid md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="fullName" class="mb-2 block text-sm font-medium text-default-700">Full Name</label>
                                        <input type="text" id="fullName" name="fullName" required class="w-full rounded-xl border border-default-200 bg-white px-4 py-3 text-sm text-default-700 outline-none ring-0 focus:border-primary" placeholder="Enter full name" />
                                    </div>

                                    <div>
                                        <label for="email" class="mb-2 block text-sm font-medium text-default-700">Email Address</label>
                                        <input type="email" id="email" name="email" required class="w-full rounded-xl border border-default-200 bg-white px-4 py-3 text-sm text-default-700 outline-none ring-0 focus:border-primary" placeholder="Enter email address" />
                                    </div>
                                </div>

                                <div class="grid md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="password" id="passwordLabel" class="mb-2 block text-sm font-medium text-default-700">Temporary Password</label>
                                        <input type="password" id="password" name="password" class="w-full rounded-xl border border-default-200 bg-white px-4 py-3 text-sm text-default-700 outline-none ring-0 focus:border-primary" placeholder="Required for new users â€” leave blank to keep current" />
                                    </div>

                                    <div>
                                        <label for="role" class="mb-2 block text-sm font-medium text-default-700">Role</label>
                                        <select id="role" name="role" class="w-full rounded-xl border border-default-200 bg-white px-4 py-3 text-sm text-default-700 outline-none ring-0 focus:border-primary">
                                            <option value="administrator">Administrator</option>
                                            <option value="production manager">Production Manager</option>
                                            <option value="store manager">Store Manager</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label for="userId" class="mb-2 block text-sm font-medium text-default-700">User ID (optional for modify)</label>
                                    <input type="number" id="userId" name="userId" min="1" class="w-full rounded-xl border border-default-200 bg-white px-4 py-3 text-sm text-default-700 outline-none ring-0 focus:border-primary" placeholder="Auto-filled when you click Edit on a user above" />
                                </div>

                                <div>
                                    <label class="mb-3 block text-sm font-medium text-default-700">Permissions</label>
                                    <div class="grid md:grid-cols-2 gap-3">
                                        <label class="flex items-center gap-3 rounded-xl border border-default-200 bg-default-50 px-4 py-3 text-sm text-default-700">
                                            <input type="checkbox" name="permissions[]" value="read" class="size-4 rounded border-default-300 text-primary focus:ring-primary" />
                                            <span>Read</span>
                                        </label>
                                        <label class="flex items-center gap-3 rounded-xl border border-default-200 bg-default-50 px-4 py-3 text-sm text-default-700">
                                            <input type="checkbox" name="permissions[]" value="write" class="size-4 rounded border-default-300 text-primary focus:ring-primary" />
                                            <span>Write</span>
                                        </label>
                                        <label class="flex items-center gap-3 rounded-xl border border-default-200 bg-default-50 px-4 py-3 text-sm text-default-700">
                                            <input type="checkbox" name="permissions[]" value="view" class="size-4 rounded border-default-300 text-primary focus:ring-primary" />
                                            <span>View</span>
                                        </label>
                                        <label class="flex items-center gap-3 rounded-xl border border-default-200 bg-default-50 px-4 py-3 text-sm text-default-700">
                                            <input type="checkbox" name="permissions[]" value="delete" class="size-4 rounded border-default-300 text-primary focus:ring-primary" />
                                            <span>Delete</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-3 pt-2">
                                    <button type="submit" name="action" value="create" id="createUserBtn" class="btn bg-primary text-white rounded-xl px-5 py-3 text-sm font-medium hover:bg-primary/90">Create User</button>
                                    <button type="submit" name="action" value="modify" id="modifyUserBtn" style="display:none" class="btn border border-default-200 bg-white text-default-700 rounded-xl px-5 py-3 text-sm font-medium hover:bg-default-50">Modify</button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </main>

            <!-- Footer Start -->
            <footer class="mt-auto footer flex items-center py-5 border-t border-default-200">
                <div class="lg:px-8 px-6 w-full flex md:justify-between justify-center gap-4">
                    <div></div>
                    <div class="md:flex hidden gap-2 item-center md:justify-end"></div>
                </div>
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

    <script>
        // All registered users (id, Username, email, role, permissions) for the admin table.
        const USERS = <?= json_encode($users, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function openManageUsers() {
            const modal = document.getElementById('manageUsersModal');
            const backdrop = document.getElementById('manageUsersBackdrop');
            const panel = document.getElementById('manageUsersPanel');

            modal.style.display = 'flex';
            // Lock background scrolling: body plus the .page-content scroll container.
            document.body.style.overflow = 'hidden';
            const pageContent = document.querySelector('.page-content');
            if (pageContent) pageContent.style.overflow = 'hidden';

            // Trigger the fade/scale-in animation on the next frame.
            requestAnimationFrame(() => {
                backdrop.classList.remove('opacity-0');
                panel.classList.remove('opacity-0', 'scale-95', 'translate-y-4');
            });
        }

        function closeManageUsers() {
            const modal = document.getElementById('manageUsersModal');
            const backdrop = document.getElementById('manageUsersBackdrop');
            const panel = document.getElementById('manageUsersPanel');

            // Animate out, then hide after the transition.
            backdrop.classList.add('opacity-0');
            panel.classList.add('opacity-0', 'scale-95', 'translate-y-4');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
                const pageContent = document.querySelector('.page-content');
                if (pageContent) pageContent.style.overflow = '';
            }, 200);
        }

        // Close the modal when the Escape key is pressed.
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeManageUsers();
        });

        function editUser(id) {
            const user = USERS.find(u => Number(u.id) === Number(id));
            if (!user) return;

            closeManageUsers();

            document.getElementById('userId').value = user.id;
            document.getElementById('fullName').value = user.Username;
            document.getElementById('email').value = user.email;
            document.getElementById('password').value = '';

            const roleSelect = document.getElementById('role');
            for (const option of roleSelect.options) {
                option.selected = option.value.toLowerCase() === String(user.role).toLowerCase();
            }

            const perms = (user.permissions || []).map(p => String(p).toLowerCase());
            document.querySelectorAll('input[name="permissions[]"]').forEach(cb => {
                cb.checked = perms.includes(cb.value);
            });

            document.getElementById('formHeading').textContent = 'Edit User #' + user.id;
            document.getElementById('formSubheading').textContent = 'Update this user\u2019s details and permissions, then click Modify to save.';
            document.getElementById('passwordLabel').textContent = 'Password (leave blank to keep current)';
            // In edit mode, only the Modify button is available â€” hide the Create/Reset to Create button.
            document.getElementById('createUserBtn').style.display = 'none';
            document.getElementById('modifyUserBtn').style.display = '';
            document.getElementById('modifyUserBtn').classList.remove('border-default-200', 'bg-white', 'text-default-700');
            document.getElementById('modifyUserBtn').classList.add('bg-primary', 'text-white', 'border-primary');

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // When arriving with ?edit=<id> (from the All Users page), auto-fill the form.
        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const editId = params.get('edit');
            if (editId) {
                editUser(editId);
                openManageUsers();
            }
        });
    </script>
</body>

</html>
