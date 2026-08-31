<?php
require_once __DIR__ . '/backend/auth.php';
requireLogin();
requirePageAccess();

require_once 'backend/config.php';

$message = '';
$messageType = '';

$displayName = isset($_SESSION['user_name']) && $_SESSION['user_name'] !== '' ? $_SESSION['user_name'] : 'User';

// Wizard state: selected product for step 2
$wizardItemId = (int) ($_SESSION['cat_wizard_item_id'] ?? 0);

// Fetch products for dropdown
$products = [];
if (isset($connect) && !$connect->connect_error) {
    $prodResult = $connect->query('SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC');
    if ($prodResult) {
        while ($row = $prodResult->fetch_assoc()) {
            $products[] = $row;
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($connect) && !$connect->connect_error) {
    $action = trim($_POST['action'] ?? '');
    
    try {
        if ($action === 'next_product') {
            $itemId = (int) ($_POST['item_id'] ?? 0);
            if ($itemId <= 0) {
                throw new RuntimeException('Please select a product.');
            }
            $_SESSION['cat_wizard_item_id'] = $itemId;
            header('Location: categories.php');
            exit();

        } elseif ($action === 'cancel_wizard') {
            unset($_SESSION['cat_wizard_item_id']);
            header('Location: categories.php');
            exit();

        } elseif ($action === 'save_category') {
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $unit = trim($_POST['unit'] ?? '');
            $color = trim($_POST['color'] ?? '');
            $machine = trim($_POST['machine'] ?? '');
            $gsm = trim($_POST['gsm'] ?? '');
            $type = trim($_POST['type'] ?? '');

            if ($itemId <= 0) {
                throw new RuntimeException('Please select a product.');
            }
            if ($name === '') {
                throw new RuntimeException('Please enter a category name.');
            }

            // Check if category already exists
            $check = $connect->prepare('SELECT id FROM categories WHERE name = ?');
            $check->bind_param('s', $name);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                throw new RuntimeException('A category with that name already exists.');
            }
            $check->close();

            // Insert category with attributes
            $stmt = $connect->prepare('INSERT INTO categories (name, description, unit, color, machine, item_id, gsm, type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
            $stmt->bind_param('sssssiss', $name, $description, $unit, $color, $machine, $itemId, $gsm, $type);
            $stmt->execute();
            $categoryId = (int) $stmt->insert_id;
            $stmt->close();

            $message = "Category \"{$name}\" created successfully.";
            $messageType = 'success';

            unset($_SESSION['cat_wizard_item_id']);

        } elseif ($action === 'edit_category') {
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $unit = trim($_POST['unit'] ?? '');
            $color = trim($_POST['color'] ?? '');
            $machine = trim($_POST['machine'] ?? '');
            $gsm = trim($_POST['gsm'] ?? '');
            $type = trim($_POST['type'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($categoryId <= 0 || $name === '') {
                throw new RuntimeException('Invalid category data.');
            }

            $check = $connect->prepare('SELECT id FROM categories WHERE name = ? AND id != ?');
            $check->bind_param('si', $name, $categoryId);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                throw new RuntimeException('A category with that name already exists.');
            }
            $check->close();

            $stmt = $connect->prepare('UPDATE categories SET name = ?, description = ?, unit = ?, color = ?, machine = ?, item_id = ?, gsm = ?, type = ?, is_active = ? WHERE id = ?');
            $stmt->bind_param('sssssissii', $name, $description, $unit, $color, $machine, $itemId, $gsm, $type, $isActive, $categoryId);
            $stmt->execute();
            $stmt->close();

            $message = "Category \"{$name}\" updated successfully.";
            $messageType = 'success';
            
        } elseif ($action === 'delete') {
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            if ($categoryId <= 0) {
                throw new RuntimeException('Invalid category.');
            }

            $itemsCount = 0;

            if ($itemsCount > 0) {
                throw new RuntimeException('Cannot delete: ' . $itemsCount . ' product(s) are linked to this category. Reassign them first.');
            }

            $stmt = $connect->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->bind_param('i', $categoryId);
            $stmt->execute();
            $stmt->close();

            $message = 'Category deleted successfully.';
            $messageType = 'success';
        }
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }

    if ($messageType === 'success') {
        header('Location: categories.php');
        exit();
    }
}

// Fetch all categories
$categories = [];
$categoryById = [];

if (isset($connect) && !$connect->connect_error) {
    $result = $connect->query(
        'SELECT c.id, c.name, c.description, c.unit, c.color, c.machine, c.gsm, c.type, c.item_id, c.is_active, c.created_at,
                NULL AS product_name
         FROM categories c
         ORDER BY c.name ASC'
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
            $categoryById[(int) $row['id']] = $row;
        }
    }
}

$totalCategories = count($categories);

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

// Wizard selected product details
$wizardProduct = null;
if ($wizardItemId > 0) {
    foreach ($products as $p) {
        if ((int) $p['id'] === $wizardItemId) {
            $wizardProduct = $p;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Categories | PICS</title>
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
        .category-card {
            transition: all 0.2s ease;
        }
        .category-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .attribute-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            background: #f0f0f5;
            color: #4a4a6a;
        }
        .attribute-tag.required {
            background: #fee2e2;
            color: #991b1b;
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
                    <h4 class="text-default-900 text-lg font-semibold">Categories</h4>
                    <div class="md:flex hidden items-center gap-2 text-sm font-semibold">
                        <a href="index.php" class="text-sm font-medium text-default-700">PICS</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700">Inventory</a>
                        <i class="iconify tabler--chevron-right text-sm flex-shrink-0 text-default-500 rtl:rotate-180"></i>
                        <a href="#" class="text-sm font-medium text-default-700" aria-current="page">Categories</a>
                    </div>
                </div>
                <!-- Page Title End -->

                <?php if ($message !== ''): ?>
                    <div class="alert alert-<?= $messageType ?> mb-4">
                        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <!-- Step 1: Select Product -->
                <div class="card mb-5" <?= $wizardItemId > 0 ? 'hidden' : '' ?>>
                    <div class="card-header">
                        <h6 class="card-title">Add New Category</h6>
                    </div>

                    <form method="POST" action="categories.php" id="category-form">
                        <input type="hidden" name="action" value="next_product">
                        <div class="card-body">
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-default-800 mb-1.5">Product <span class="text-danger">*</span></label>
                                    <select name="item_id" class="form-input" required>
                                        <option value="">-- Select Product --</option>
                                        <?php foreach ($products as $prod): ?>
                                            <option value="<?= (int) $prod['id'] ?>"><?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer flex justify-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="arrow-right" class="size-4 me-1"></i>
                                Next
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Step 2: Enter Attribute Values -->
                <?php if ($wizardItemId > 0 && $wizardProduct): ?>
                <div class="card mb-5 border-2 border-primary/20" id="attributes-section">
                    <div class="card-header bg-primary/5">
                        <h6 class="card-title text-primary">Define Category for "<?= htmlspecialchars($wizardProduct['name'], ENT_QUOTES, 'UTF-8') ?>"</h6>
                    </div>

                    <form method="POST" action="categories.php" id="attributes-form">
                        <input type="hidden" name="action" value="save_category">
                        <input type="hidden" name="item_id" value="<?= $wizardItemId ?>">
                        <div class="card-body">
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-default-800 mb-1.5">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-input" placeholder="e.g. Printing Paper" value="<?= htmlspecialchars($wizardProduct['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-default-800 mb-1.5">Unit</label>
                                    <input type="text" name="unit" class="form-input" placeholder="e.g. Ream, Piece">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-default-800 mb-1.5">Color</label>
                                    <input type="text" name="color" class="form-input" placeholder="e.g. White, Blue">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-default-800 mb-1.5">Machine</label>
                                    <input type="text" name="machine" class="form-input" placeholder="e.g. Heidelberg, Roland">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-default-800 mb-1.5">GSM</label>
                                    <input type="text" name="gsm" class="form-input" placeholder="e.g. 170, 130">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-default-800 mb-1.5">Type</label>
                                    <input type="text" name="type" class="form-input" placeholder="e.g. Wave, DSA">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-default-800 mb-1.5">Description</label>
                                    <textarea name="description" class="form-input" rows="2" placeholder="Optional description for this category"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer flex justify-between gap-2">
                            <button type="button" class="btn bg-transparent border border-default-300 text-default-600 hover:bg-default-150" id="back-to-step1">
                                <i data-lucide="arrow-left" class="size-4 me-1"></i>
                                Back
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="save" class="size-4 me-1"></i>
                                Save Category
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Categories Grid -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title">All Categories</h6>
                    </div>
                    <div class="card-header">
                        <div class="md:flex items-center md:space-y-0 space-y-4 gap-3">
                            <div class="relative">
                                <input type="search" id="category-search" class="form-input form-input-sm ps-9" placeholder="Search categories...">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3">
                                    <i data-lucide="search" class="size-3.5 flex items-center text-default-500 fill-default-100"></i>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2 items-center flex-wrap">
                            <span class="text-sm text-default-500"><?= $totalCategories ?> categor<?= $totalCategories === 1 ? 'y' : 'ies' ?> found</span>
                        </div>
                    </div>

                    <div class="p-4">
                        <?php if (empty($categories)): ?>
                            <div class="text-center py-12">
                                <i data-lucide="tags" class="size-12 mx-auto mb-3 text-default-300"></i>
                                <p class="text-default-500">No categories created yet. Use the form above to add one.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" id="categories-grid">
                                <?php foreach ($categories as $cat): ?>
                                <div class="category-card card border border-default-200 p-4" data-name="<?= strtolower($cat['name']) ?>">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <h6 class="text-sm font-semibold text-default-800"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></h6>
                                            <p class="text-xs text-default-500 mt-0.5">
                                                Product: <?= htmlspecialchars($cat['product_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                            <?php if ($cat['description']): ?>
                                                <p class="text-xs text-default-400 mt-1"><?= htmlspecialchars($cat['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-xs px-2 py-0.5 rounded-full <?= $cat['is_active'] ? 'bg-success/10 text-success' : 'bg-default-200/60 text-default-600' ?>">
                                            <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <!-- Attributes -->
                                    <div class="mt-3 flex flex-wrap gap-1">
                                        <?php
                                        $attrTags = [];
                                        if (!empty($cat['unit'])) $attrTags[] = ['n' => 'Unit', 'v' => $cat['unit']];
                                        if (!empty($cat['color'])) $attrTags[] = ['n' => 'Color', 'v' => $cat['color']];
                                        if (!empty($cat['machine'])) $attrTags[] = ['n' => 'Machine', 'v' => $cat['machine']];
                                        if (!empty($cat['gsm'])) $attrTags[] = ['n' => 'GSM', 'v' => $cat['gsm']];
                                        if (!empty($cat['type'])) $attrTags[] = ['n' => 'Type', 'v' => $cat['type']];
                                        ?>
                                        <?php if (!empty($attrTags)): ?>
                                            <?php foreach ($attrTags as $t): ?>
                                                <span class="attribute-tag">
                                                    <?= htmlspecialchars($t['n'], ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($t['v'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-default-400">No attributes</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Actions -->
                                    <div class="mt-3 pt-3 border-t border-default-100 flex items-center justify-end gap-1">
                                        <button type="button"
                                                class="btn btn-icon btn-sm rounded bg-transparent border border-default-300 text-default-600 hover:bg-default-150 open-edit"
                                                data-id="<?= (int) $cat['id'] ?>"
                                                data-name="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-item="<?= (int) $cat['item_id'] ?>"
                                                data-unit="<?= htmlspecialchars($cat['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-color="<?= htmlspecialchars($cat['color'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-machine="<?= htmlspecialchars($cat['machine'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-gsm="<?= htmlspecialchars($cat['gsm'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-type="<?= htmlspecialchars($cat['type'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-description="<?= htmlspecialchars($cat['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-active="<?= (int) $cat['is_active'] ?>"
                                                data-hs-overlay="#editCategoryModal">
                                            <i data-lucide="pencil" class="size-3.5"></i>
                                        </button>
                                        <form method="POST" action="categories.php" class="inline" onsubmit="return confirm('Delete category &quot;<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>&quot;?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                                            <button type="submit" class="btn btn-icon btn-sm rounded bg-transparent border border-default-300 text-danger hover:bg-danger/10">
                                                <i data-lucide="trash-2" class="size-3.5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="editCategoryModal-label">
        <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 scale-95 opacity-0 ease-in-out transition-all duration-200 max-w-2xl w-full mx-auto px-4 py-14 min-h-[calc(100%-56px)] flex items-center">
            <div class="card w-full flex flex-col border border-default-200 shadow-2xs rounded-xl pointer-events-auto">
                <div class="card-header">
                    <h3 id="editCategoryModal-label" class="font-semibold text-base text-default-800">Edit Category</h3>
                    <button type="button" class="size-5 text-default-800" aria-label="Close" data-hs-overlay="#editCategoryModal">
                        <i data-lucide="x" class="size-5"></i>
                    </button>
                </div>

                <form method="POST" action="categories.php" class="flex flex-col">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="category_id" id="edit-id">
                    <div class="card-body">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="edit-name" class="form-input" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Product <span class="text-danger">*</span></label>
                                <select name="item_id" id="edit-item" class="form-input" required>
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ($products as $prod): ?>
                                        <option value="<?= (int) $prod['id'] ?>"><?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Unit</label>
                                <input type="text" name="unit" id="edit-unit" class="form-input">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Color</label>
                                <input type="text" name="color" id="edit-color" class="form-input">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Machine</label>
                                <input type="text" name="machine" id="edit-machine" class="form-input">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">GSM</label>
                                <input type="text" name="gsm" id="edit-gsm" class="form-input">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Type</label>
                                <input type="text" name="type" id="edit-type" class="form-input">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-default-800 mb-1.5">Description</label>
                                <textarea name="description" id="edit-description" class="form-input" rows="1"></textarea>
                            </div>
                            <label class="flex items-center gap-2 text-sm font-medium text-default-800 cursor-pointer">
                                <input type="checkbox" name="is_active" id="edit-active" class="form-checkbox">
                                Active
                            </label>
                        </div>
                    </div>

                    <div class="card-footer flex justify-end gap-2">
                        <button type="button" class="btn bg-transparent border border-default-300 text-default-600 hover:bg-default-150" data-hs-overlay="#editCategoryModal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="check" class="size-4"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            // Search
            const searchInput = document.getElementById('category-search');
            const cards = document.querySelectorAll('#categories-grid .category-card');
            searchInput.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                cards.forEach(function (card) {
                    const name = card.dataset.name || '';
                    card.style.display = name.indexOf(q) !== -1 ? '' : 'none';
                });
            });

            // Edit modal
            document.querySelectorAll('.open-edit').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.getElementById('edit-id').value = btn.dataset.id;
                    document.getElementById('edit-name').value = btn.dataset.name || '';
                    document.getElementById('edit-item').value = btn.dataset.item || '';
                    document.getElementById('edit-unit').value = btn.dataset.unit || '';
                    document.getElementById('edit-color').value = btn.dataset.color || '';
                    document.getElementById('edit-machine').value = btn.dataset.machine || '';
                    document.getElementById('edit-gsm').value = btn.dataset.gsm || '';
                    document.getElementById('edit-type').value = btn.dataset.type || '';
                    document.getElementById('edit-description').value = btn.dataset.description || '';
                    document.getElementById('edit-active').checked = btn.dataset.active === '1';
                });
            });

            // Back to step 1
            const backBtn = document.getElementById('back-to-step1');
            if (backBtn) {
                backBtn.addEventListener('click', function () {
                    fetch('categories.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=cancel_wizard'
                    }).then(function () {
                        window.location.href = 'categories.php';
                    });
                });
            }
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