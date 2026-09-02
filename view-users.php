<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

$message = $_SESSION["success_message"] ?? "";
$error = $_SESSION["error_message"] ?? "";
unset($_SESSION["success_message"], $_SESSION["error_message"]);

require_once 'backend/config.php';

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

// Helper to render an avatar with the user's initials.
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

// Expose user data to the client so the Overview modal can be filled in.
$usersJson = json_encode(array_map(function ($user) {
    return [
        'id'          => (int) $user['id'],
        'name'        => $user['Username'],
        'email'       => $user['email'],
        'role'        => ucfirst($user['role']),
        'roleKey'     => strtolower($user['role']),
        'initials'    => userInitials($user['Username']),
        'permissions' => array_values($user['permissions']),
    ];
}, $users));

$displayName = isset($_SESSION['user_name']) && $_SESSION['user_name'] !== '' ? $_SESSION['user_name'] : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>All Users | PICS</title>
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

                <?php if ($message !== ''): ?>
                    <div id="flash-success" class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success print:hidden">
                        <span class="flex items-center gap-2">
                            <i data-lucide="check-circle-2" class="size-4"></i>
                            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <button type="button" onclick="this.closest('#flash-success').remove()" class="shrink-0 text-success/70 hover:text-success">
                            <i data-lucide="x" class="size-4"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div id="flash-error" class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger print:hidden">
                        <span class="flex items-center gap-2">
                            <i data-lucide="alert-circle" class="size-4"></i>
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <button type="button" onclick="this.closest('#flash-error').remove()" class="shrink-0 text-danger/70 hover:text-danger">
                            <i data-lucide="x" class="size-4"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Page Title Start -->
                <div class="flex items-center md:justify-between flex-wrap gap-2 mb-4 print:hidden">
                    <h4 class="text-default-900 text-lg font-semibold">All Users</h4>

                    <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                        <a href="index.php" class="text-sm font-medium text-default-700">PICS</a>

                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>

                        <a href="#" class="text-sm font-medium text-default-700">Users</a>

                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>

                        <a href="#" class="text-sm font-medium text-default-700" aria-current="page">All Users</a>
                    </div>
                </div>
                <!-- Page Title End -->

                <!-- Stats Cards End -->

                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title">Registered Users</h6>
                        <a href="userregister.php" class="btn btn-sm bg-primary text-white">
                            <i data-lucide="plus" class="size-4 me-1"></i>Add user
                        </a>
                    </div>

                    <div class="card-header">
                        <div class="md:flex items-center md:space-y-0 space-y-4 gap-3">
                            <div class="relative">
                                <input type="search" id="table-search" class="form-input form-input-sm ps-9" placeholder="Search name, email or role">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3">
                                    <i data-lucide="search" class="size-3.5 flex items-center text-default-500 fill-default-100"></i>
                                </div>
                            </div>

                            <select id="role-filter" class="form-input form-input-sm">
                                <option value="">All roles</option>
                                <?php foreach (array_unique(array_column($users, 'role')) as $role): ?>
                                    <option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex gap-2 items-center flex-wrap">
                            <span class="text-sm text-default-500"><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?> found</span>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <div class="overflow-x-auto">
                            <div class="min-w-full inline-block align-middle">
                                <div class="overflow-hidden">
                                    <table class="min-w-full divide-y divide-default-200">
                                        <thead class="bg-default-150">
                                            <tr class="text-sm font-normal text-default-700 whitespace-nowrap">
                                                <th scope="col" class="px-3.5 py-3 text-start">User ID</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Name</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Email</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Role</th>
                                                <th scope="col" class="px-3.5 py-3 text-start">Permissions</th>
                                                <th scope="col" class="px-3.5 py-3 text-end">Action</th>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-default-100">
                                            <?php if (empty($users)): ?>
                                                <tr>
                                                    <td colspan="6" class="px-3.5 py-16 text-center text-default-500">
                                                        <i data-lucide="users" class="size-10 mx-auto mb-3 text-default-300"></i>
                                                        <p class="font-medium">No users found yet.</p>
                                                        <p class="text-sm mt-1">Create your first user from the Manage Users page.</p>
                                                    </td>
                                                </tr>
                                            <?php else: foreach ($users as $user): ?>
                                                <?php
                                                    $role = strtolower(trim($user['role']));
                                                    $isAdmin = $role === 'admin';
                                                    $perms = $user['permissions'];
                                                    $visiblePerms = array_slice($perms, 0, 3);
                                                    $extraPerms = count($perms) - count($visiblePerms);
                                                ?>
                                                <tr class="text-default-800 font-normal text-sm whitespace-nowrap">
                                                    <td class="px-3.5 py-3 text-sm text-primary">#USR<?= str_pad((string) $user['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                                    <td class="flex py-3 px-3.5 items-center gap-3">
                                                        <div class="size-10 rounded-full <?= $isAdmin ? 'bg-danger/10 text-danger' : 'bg-primary/10 text-primary' ?> flex items-center justify-center font-semibold text-sm shrink-0">
                                                            <?= htmlspecialchars(userInitials($user['Username']), ENT_QUOTES, 'UTF-8') ?>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-1.5 font-semibold">
                                                                <a href="userregister.php?edit=<?= (int) $user['id'] ?>" class="text-default-800"><?= htmlspecialchars($user['Username'], ENT_QUOTES, 'UTF-8') ?></a>
                                                            </h6>
                                                            <p class="text-default-500">User ID: <?= (int) $user['id'] ?></p>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 px-3.5 text-default-600"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="px-3.5 py-3">
                                                        <span class="py-0.5 px-2.5 inline-flex items-center gap-x-1 text-xs font-medium <?= $isAdmin ? 'bg-danger/10 text-danger' : 'bg-primary/10 text-primary' ?> rounded">
                                                            <i data-lucide="<?= $isAdmin ? 'shield-check' : 'user' ?>" class="size-3"></i>
                                                            <?= htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-3 px-3.5 whitespace-normal">
                                                        <?php if (empty($perms)): ?>
                                                            <span class="text-default-400 text-xs">No permissions</span>
                                                        <?php else: ?>
                                                            <div class="flex flex-wrap gap-1.5">
                                                                <?php foreach ($visiblePerms as $perm): ?>
                                                                    <span class="py-0.5 px-2 inline-flex items-center text-[11px] font-medium bg-default-200 text-default-600 rounded">
                                                                        <?= htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', $perm)), ENT_QUOTES, 'UTF-8') ?>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                                <?php if ($extraPerms > 0): ?>
                                                                    <span class="py-0.5 px-2 inline-flex items-center text-[11px] font-medium bg-default-150 text-default-500 rounded">+<?= $extraPerms ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-3.5 py-3">
                                                        <div class="hs-dropdown relative inline-flex">
                                                            <button type="button" class="hs-dropdown-toggle btn size-7.5 bg-default-200 hover:bg-default-600 text-default-500" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown" hs-dropdown-placement="bottom-end">
                                                                <i class="iconify lucide--ellipsis size-4"></i>
                                                            </button>
                                                            <div class="hs-dropdown-menu" role="menu">
                                                                <a class="flex items-center gap-1.5 py-1.5 font-medium px-3 text-default-500 hover:bg-default-150 rounded cursor-pointer open-user-overview" data-user-id="<?= (int) $user['id'] ?>" data-hs-overlay="#userOverviewModal">
                                                                    <i data-lucide="eye" class="size-3"></i>
                                                                    Overview
                                                                </a>
                                                                <a class="flex items-center gap-1.5 py-1.5 font-medium px-3 text-default-500 hover:bg-default-150 rounded" href="userregister.php?edit=<?= (int) $user['id'] ?>">
                                                                    <i data-lucide="edit" class="size-3"></i>
                                                                    Edit
                                                                </a>
                                                                <button type="button" onclick="openDeleteModal(<?= (int) $user['id'] ?>, '<?= htmlspecialchars($user['Username'], ENT_QUOTES) ?>')" class="flex w-full items-center gap-1.5 py-1.5 font-medium px-3 text-danger hover:bg-danger/10 rounded cursor-pointer">
                                                                    <i data-lucide="trash-2" class="size-3"></i>
                                                                    Delete
                                                                </button>
                                                            </div>
                                                        </div>
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

    <!-- User Overview Modal -->
    <div id="userOverviewModal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="userOverviewModal-label">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 scale-95 opacity-0 ease-in-out transition-all duration-200 max-w-sm w-full mx-auto px-4 my-3 min-h-[calc(100%-56px)] flex items-center">
            <div class="card w-full flex flex-col border border-default-200 shadow-2xs rounded-xl pointer-events-auto">
                <div class="card-header">
                    <h3 id="userOverviewModal-label" class="font-semibold text-base text-default-800 dark:text-white">
                        User Overview
                    </h3>
                    <button type="button" class="size-5 text-default-800" aria-label="Close" data-hs-overlay="#userOverviewModal">
                        <span class="sr-only">Close</span>
                        <i data-lucide="x" class="size-5"></i>
                    </button>
                </div>

                <div class="card-body">
                    <div class="flex items-center gap-4 mb-5">
                        <div id="uo-avatar" class="size-14 shrink-0 rounded-full bg-primary/10 text-primary flex items-center justify-center font-semibold text-lg">--</div>
                        <div class="min-w-0">
                            <h5 id="uo-name" class="font-semibold text-base text-default-800 truncate">--</h5>
                            <span id="uo-role" class="mt-1.5 py-0.5 px-2.5 inline-flex items-center gap-x-1 text-xs font-medium bg-primary/10 text-primary rounded">--</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3 text-sm">
                            <span class="text-default-500 flex items-center gap-2 shrink-0">
                                <i data-lucide="hash" class="size-4"></i> User ID
                            </span>
                            <span id="uo-id" class="font-medium text-default-800 text-end">--</span>
                        </div>
                        <div class="flex items-start justify-between gap-3 text-sm">
                            <span class="text-default-500 flex items-center gap-2 shrink-0">
                                <i data-lucide="mail" class="size-4"></i> Email
                            </span>
                            <span id="uo-email" class="font-medium text-default-800 text-end break-all">--</span>
                        </div>
                        <div class="flex items-start justify-between gap-3 text-sm">
                            <span class="text-default-500 flex items-center gap-2 shrink-0">
                                <i data-lucide="user-check" class="size-4"></i> Permissions
                            </span>
                            <span id="uo-perms" class="font-medium text-default-800 text-end">--</span>
                        </div>
                    </div>
                </div>

                <div class="card-footer mt-4 flex gap-2 md:justify-end">
                    <button type="button" class="bg-transparent text-danger btn border-0 hover:bg-danger/10" data-hs-overlay="#userOverviewModal">Close</button>
                    <a id="uo-edit" href="#" class="btn bg-primary text-white">Edit User</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete User Confirmation Modal -->
    <div id="deleteUserModal" class="hidden fixed inset-0 z-90 items-center justify-center p-4" style="display:none">
        <div id="deleteUserBackdrop" class="absolute inset-0 bg-black/50 opacity-0 transition-opacity duration-150"></div>
        <div id="deleteUserPanel" class="relative w-full max-w-sm rounded-xl bg-card shadow-xl opacity-0 scale-95 transition-all duration-150">
            <div class="flex flex-col items-center px-6 pt-6 pb-4 text-center">
                <div class="size-14 rounded-full bg-danger/10 text-danger flex items-center justify-center mb-4">
                    <i data-lucide="trash-2" class="size-6"></i>
                </div>
                <h5 class="text-base font-semibold text-default-900">Delete User</h5>
                <p class="mt-2 text-sm text-default-500">
                    Are you sure you want to delete
                    <span id="del-name" class="font-semibold text-default-800">this user</span>?
                    This action cannot be undone.
                </p>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-default-200 px-6 py-4">
                <button type="button" onclick="closeDeleteModal()" class="btn bg-default-150 text-default-700">Cancel</button>
                <form method="post" action="backend/authentication/admin.php" class="m-0">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="del-userId" name="userId" value="">
                    <button type="submit" class="btn bg-danger text-white hover:bg-danger/90">
                        <i data-lucide="trash-2" class="size-4 me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Client-side table filtering (search + role filter).
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('table-search');
            const roleFilter = document.getElementById('role-filter');
            const table = document.querySelector('table tbody');
            const rows = table ? Array.from(table.querySelectorAll('tr')) : [];
            const counter = document.querySelector('.card-header span.text-sm');

            // Skip the "no results" placeholder row when filtering.
            const dataRows = rows.filter(function (row) {
                return row.querySelector('td[colspan]') === null;
            });
            const emptyRow = rows.find(function (row) {
                return row.querySelector('td[colspan]') !== null;
            });

            function applyFilter() {
                const q = (searchInput ? searchInput.value : '').trim().toLowerCase();
                const role = roleFilter ? roleFilter.value : '';

                let visible = 0;
                dataRows.forEach(function (row) {
                    const text = row.textContent.toLowerCase();
                    const roleCell = row.querySelector('td:nth-child(4)');
                    const roleText = roleCell ? roleCell.textContent.trim().toLowerCase() : '';

                    const matchesSearch = q === '' || text.includes(q);
                    const matchesRole = role === '' || roleText === role.toLowerCase();

                    const show = matchesSearch && matchesRole;
                    row.style.display = show ? '' : 'none';
                    if (show) visible++;
                });

                if (emptyRow) {
                    emptyRow.style.display = visible === 0 ? '' : 'none';
                }
                if (counter) {
                    counter.textContent = visible + ' user' + (visible === 1 ? '' : 's') + ' found';
                }
            }

            if (searchInput) searchInput.addEventListener('input', applyFilter);
            if (searchInput) searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') applyFilter();
            });
            if (roleFilter) roleFilter.addEventListener('change', applyFilter);
        });
    </script>

    <script>
        // Delete confirmation modal.
        function openDeleteModal(id, name) {
            document.getElementById('del-name').textContent = name;
            document.getElementById('del-userId').value = id;
            const modal = document.getElementById('deleteUserModal');
            const backdrop = document.getElementById('deleteUserBackdrop');
            const panel = document.getElementById('deleteUserPanel');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(function () {
                backdrop.classList.remove('opacity-0');
                panel.classList.remove('opacity-0', 'scale-95');
            });
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteUserModal');
            const backdrop = document.getElementById('deleteUserBackdrop');
            const panel = document.getElementById('deleteUserPanel');
            backdrop.classList.add('opacity-0');
            panel.classList.add('opacity-0', 'scale-95');
            setTimeout(function () {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 150);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDeleteModal();
        });
    </script>

    <script>
        // User Overview modal: fill it with the clicked user's data.
        const usersData = <?= $usersJson ?: '[]' ?>;

        document.querySelectorAll('.open-user-overview').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = parseInt(this.dataset.userId, 10);
                const user = usersData.find(function (u) { return u.id === id; });
                if (!user) return;

                document.getElementById('uo-avatar').textContent = user.initials;
                document.getElementById('uo-name').textContent = user.name;
                document.getElementById('uo-id').textContent = '#USR' + String(user.id).padStart(4, '0');
                document.getElementById('uo-email').textContent = user.email;
                document.getElementById('uo-edit').setAttribute('href', 'userregister.php?edit=' + user.id);

                const roleEl = document.getElementById('uo-role');
                roleEl.className = 'mt-1.5 py-0.5 px-2.5 inline-flex items-center gap-x-1 text-xs font-medium rounded ' +
                    (user.roleKey === 'admin' ? 'bg-danger/10 text-danger' : 'bg-primary/10 text-primary');
                roleEl.textContent = user.role;

                const permsEl = document.getElementById('uo-perms');
                permsEl.innerHTML = user.permissions.length === 0
                    ? '<span class="text-default-400 text-xs">No permissions</span>'
                    : '<span class="inline-flex flex-wrap gap-1.5">' + user.permissions.map(function (p) {
                        return '<span class="py-0.5 px-2 inline-flex items-center text-[11px] font-medium bg-default-200 text-default-600 rounded">' +
                            p.replace(/[_-]/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }) + '</span>';
                    }).join('') + '</span>';
            });
        });
    </script>

</body>

</html>
