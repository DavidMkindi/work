<?php
/**
 * Role-based application sidebar. Included by every protected page inside the
 * <aside id="app-menu"> placeholder so the menu always matches the user's role.
 *
 * Requires backend/auth.php to be loaded first (pages call requireLogin()).
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logo_sm.php';

$authRole   = authCurrentUserRole();
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

$sidebarAwaitingMR = 0;
if (!empty($_SESSION['logged_in']) && (int) ($_SESSION['user_id'] ?? 0) > 0) {
    require_once __DIR__ . '/config.php';
    $currentUserId = (int) $_SESSION['user_id'];
    if ($connect && !$connect->connect_error) {
        $res = $connect->query("SELECT COUNT(*) AS c FROM material_requests WHERE status IN ('Submitted', 'Pending Approval')");
        $sidebarAwaitingMR = $res ? (int) $res->fetch_assoc()['c'] : 0;
    }
}

$sidebarMenu = [
    [
        'title' => 'Overview',
        'items' => [
            ['type' => 'link', 'icon' => 'monitor-dot', 'label' => 'Dashboard', 'href' => 'index.php', 'roles' => ['*']],
        ],
    ],
    [
        'title' => 'Sales',
        'items' => [
            ['type' => 'link', 'icon' => 'users-round', 'label' => 'Customers', 'href' => 'customers.php', 'roles' => ['administrator', 'admin', 'sales officer']],
            ['type' => 'link', 'icon' => 'printer', 'label' => 'Printing Service Request', 'href' => 'customer-request.php', 'roles' => ['administrator', 'admin', 'production manager']],
        ],
    ],
    [
        'title' => 'Management',
        'items' => [
            [
                'type'  => 'group',
                'icon'  => 'square-user-round',
                'label' => 'Users',
                'roles' => ['administrator', 'admin'],
                'items' => [
                    ['label' => 'All Users', 'href' => 'view-users.php'],
                    ['label' => 'Manage Users', 'href' => 'userregister.php'],
                ],
            ],
        ],
    ],
    [
        'title' => 'Production',
        'items' => [
            ['type' => 'link', 'icon' => 'factory', 'label' => 'Production Job', 'href' => 'production-jobs.php', 'roles' => ['administrator', 'admin', 'production manager', 'production supervisor', 'store manager']],
            ['type' => 'link', 'icon' => 'wrench', 'label' => 'Services', 'href' => 'services.php', 'roles' => ['administrator', 'admin', 'production manager']],
            ['type' => 'link', 'icon' => 'clipboard-list', 'label' => 'Material Request', 'href' => 'material-request.php', 'roles' => ['administrator', 'admin', 'production manager']],
            ['type' => 'link', 'icon' => 'list-checks', 'label' => 'Material Requests', 'href' => 'material-requests.php', 'roles' => ['administrator', 'admin', 'production manager', 'production supervisor', 'store manager']],
        ],
    ],
    [
        'title' => 'Waste Management',
        'items' => [
            ['type' => 'link', 'icon' => 'recycle', 'label' => 'Waste Records', 'href' => 'waste-records.php', 'roles' => ['administrator', 'admin', 'production manager', 'production supervisor', 'store manager']],
        ],
    ],
    [
        'title' => 'Inventory',
        'items' => [
            ['type' => 'link', 'icon' => 'warehouse', 'label' => 'Stock Management', 'href' => 'stock-management.php', 'roles' => ['administrator', 'admin', 'store manager']],
            ['type' => 'link', 'icon' => 'building-2', 'label' => 'Warehouses', 'href' => 'warehouses.php', 'roles' => ['administrator', 'admin', 'store manager']],
            ['type' => 'link', 'icon' => 'tags', 'label' => 'Categories', 'href' => 'categories.php', 'roles' => ['administrator', 'admin', 'store manager']],
        ],
    ],
];

function sidebarAllowed(array $roles): bool {
    return in_array('*', $roles, true) || authHasRole($roles);
}

function sidebarEsc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function sidebarLogo(string $class): string {
    $font = 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
    $svg = '<svg class="' . $class . '" viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Print Inventory Control System logo">';
    $svg .= '<circle cx="80" cy="80" r="76" fill="none" stroke="currentColor" stroke-width="3"/>';
    $svg .= '<text x="80" y="56" text-anchor="middle" font-family="' . $font . '" font-size="21" font-weight="800" letter-spacing="1" fill="currentColor">PRINTING</text>';
    $svg .= '<text x="80" y="84" text-anchor="middle" font-family="' . $font . '" font-size="21" font-weight="800" letter-spacing="1" fill="currentColor">INVENTORY</text>';
    $svg .= '<text x="80" y="108" text-anchor="middle" font-family="' . $font . '" font-size="12.5" font-weight="600" letter-spacing="1" fill="currentColor">CONTROL SYSTEM</text>';
    $svg .= '<path d="M54 93h52" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/>';
    $svg .= '</svg>';
    return $svg;
}

function sidebarLogoSymbol(string $class): string {
    $svg = '<svg class="' . $class . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Print Inventory Control System logo">';
    $svg .= '<circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" stroke-width="3"/>';
    $svg .= '<path d="M38 26h18l6 6v14H38z" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/>';
    $svg .= '<path d="M56 26v6h6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/>';
    $svg .= '<path d="M43 34h14M43 39h14M43 44h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';
    $svg .= '<rect x="27" y="46" width="46" height="28" rx="5" fill="none" stroke="currentColor" stroke-width="3"/>';
    $svg .= '<path d="M32 52h36" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>';
    $svg .= '<path d="M33 74v4h34v-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
    $svg .= '<circle cx="37" cy="68" r="1.5" fill="currentColor"/>';
    $svg .= '<circle cx="43" cy="68" r="1.5" fill="currentColor"/>';
    $svg .= '</svg>';
    return $svg;
}
?>
<style>
    /* Sidebar: grey hover+active background and normal text color instead of blue */
    html[data-theme="light"],
    html[data-sidenav-color="light"] {
        --sidenav-item-hover-bg: #f4f4f5;
        --sidenav-item-active-bg: #f4f4f5;
        --sidenav-item-hover-color: var(--color-zinc-600);
        --sidenav-item-active-color: var(--color-zinc-600);
    }
</style>
<!-- Start Sidebar -->
<aside id="app-menu" class="app-menu">

    <!-- Sidenav Menu Toggle Button -->
    <div class="absolute top-0 end-5 flex h-topbar items-center justify">
        <button id="button-hover-toggle" class="">
            <i class="iconify tabler--circle size-5"></i>
        </button>
    </div>

    <!-- Sidenav Menu Item Link -->
    <div class="relative min-h-0 flex-grow">
        <div class="size-full" data-simplebar>

            <!-- Sidenav Menu Brand Logo -->
            <div class="logo-box mt-4 flex min-h-topbar-height items-center justify-center px-6 backdrop-blur-xs">
                <a href="index.php" class="inline-flex items-center justify-center">
                    <!-- Brand Logo -->
                    <div class="logo-light">
                        <?php echo sidebarLogo('logo-lg h-16 w-auto'); ?>
                        <?php echo sidebarLogoSymbol('logo-sm size-10'); ?>
                    </div>

                    <div class="logo-dark">
                        <?php echo sidebarLogo('logo-lg h-16 w-auto'); ?>
                        <?php echo sidebarLogoSymbol('logo-sm size-10'); ?>
                    </div>
                </a>
            </div>

            <ul class="side-nav p-3 hs-accordion-group">
                <?php foreach ($sidebarMenu as $section): ?>
                    <?php
                        $sectionItems = array_values(array_filter($section['items'], fn($item) => sidebarAllowed($item['roles'])));
                        if (empty($sectionItems)) {
                            continue;
                        }
                    ?>
                    <li class="menu-title">
                        <span><?= sidebarEsc($section['title']) ?></span>
                    </li>

                    <?php foreach ($sectionItems as $item): ?>
                        <?php if ($item['type'] === 'group'):
                            $subItems = $item['items'];
                            $subActive = false;
                            foreach ($subItems as $sub) {
                                if ($sub['href'] === $currentPage) {
                                    $subActive = true;
                                    break;
                                }
                            }
                            $groupOpen = $subActive ? ' active' : '';
                            $groupHidden = $subActive ? '' : ' hidden';
                        ?>
                            <li class="menu-item hs-accordion<?= $groupOpen ?>">
                                <a href="javascript:void(0)" class="hs-accordion-toggle menu-link">
                                    <span class="menu-icon"><i data-lucide="<?= sidebarEsc($item['icon']) ?>"></i></span>
                                    <span class="menu-text"> <?= sidebarEsc($item['label']) ?> </span>
                                    <span class="menu-arrow"></span>
                                </a>

                                <ul class="sub-menu hs-accordion-content<?= $groupHidden ?>">
                                    <?php foreach ($subItems as $sub): ?>
                                        <li class="menu-item">
                                            <a href="<?= sidebarEsc($sub['href']) ?>" class="menu-link<?= $sub['href'] === $currentPage ? ' active' : '' ?>">
                                                <span class="menu-text"><?= sidebarEsc($sub['label']) ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="menu-item">
                                <a href="<?= sidebarEsc($item['href']) ?>" class="menu-link<?= $item['href'] === $currentPage ? ' active' : '' ?>">
                                    <span class="menu-icon"><i data-lucide="<?= sidebarEsc($item['icon']) ?>"></i></span>
                                    <div class="menu-text"><?= sidebarEsc($item['label']) ?></div>
                                    <?php if ($item['href'] === 'material-requests.php' && $sidebarAwaitingMR > 0): ?>
                                        <span class="menu-badge ms-auto">
                                            <span class="inline-flex h-2 w-2 rounded-full bg-primary"></span>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

</aside>
<!-- End Sidebar -->
