<?php
session_start();

$error_message = $_SESSION["error_message"] ?? "";
unset($_SESSION["error_message"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Login | Tailwick - Tailwind CSS 3 Admin Layout & UI Kit Template</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description">
    <meta content="Themesdesign" name="author">

    <link rel="icon" type="image/svg+xml" href="./assets/images/pics-logo.svg">

    <script>
        (function () {
            const html = document.documentElement;
            const storageKey = "__TAILWICK_CONFIG__";
            const savedConfig = sessionStorage.getItem(storageKey);

            const defaultConfig = {
                dir: "ltr",
                theme: "light",
                sidenav: {
                    color: "light",
                    size: "default",
                },
            };

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
</head>

<body>
    <div class="relative min-h-screen w-full flex justify-center items-center py-16 md:py-10">
        <div class="card md:w-lg w-screen z-10">
            <div class="text-center px-10 py-12">
                <a href="index.php" class="flex justify-center">
                    <svg class="h-20 w-auto text-default-900 dark:text-white" viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Print Inventory Control System logo">
                        <circle cx="80" cy="80" r="76" fill="none" stroke="currentColor" stroke-width="3"/>
                        <text x="80" y="56" text-anchor="middle" font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif" font-size="21" font-weight="800" letter-spacing="1" fill="currentColor">PRINTING</text>
                        <text x="80" y="84" text-anchor="middle" font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif" font-size="21" font-weight="800" letter-spacing="1" fill="currentColor">INVENTORY</text>
                        <text x="80" y="108" text-anchor="middle" font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif" font-size="12.5" font-weight="600" letter-spacing="1" fill="currentColor">CONTROL SYSTEM</text>
                        <path d="M54 93h52" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/>
                    </svg>
                </a>

                <div class="mt-8 text-center">
                    <h4 class="mb-2.5 text-xl font-semibold text-default-900 dark:text-white">Welcome Back !</h4>
                    
                </div>

                <form action="backend/authentication/login.php" class="text-left w-full mt-10" method="POST">
                    <?php if ($error_message !== ""): ?>
                        <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <?= htmlspecialchars($error_message, ENT_QUOTES, "UTF-8") ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <label for="identifier" class="block font-medium text-default-900 text-sm mb-2">Username / Email ID</label>
                        <input type="text" id="identifier" name="identifier" class="form-input" placeholder="Enter Username or email" required />
                    </div>

                    <div class="mb-4">
                        <label for="Password" class="block font-medium text-default-900 text-sm mb-2">Password</label>
                        <input type="password" id="Password" name="password" class="form-input" placeholder="Enter Password" required />
                    </div>

                    <div class="flex items-center gap-2 mb-4">
                        <input id="checkbox-1" type="checkbox" class="form-checkbox">
                        <label class="text-default-900 text-sm font-medium" for="checkbox-1">Remember Me</label>
                    </div>

                    <div class="mt-10 text-center">
                        <button type="submit" class="btn bg-default-900 text-white dark:bg-neutral-700 w-full">Sign In</button>
                    </div>

                    <!-- <div class="my-9 relative text-center before:absolute before:top-2.5 before:left-0 before:border-t before:border-t-default-200 before:w-full before:h-0.5 before:right-0 before:-z-0">
                        <h4 class="relative z-1 py-0.5 px-2 inline-block font-medium text-default-600 bg-card">Sign In With</h4>
                    </div> -->

                    <!-- <div class="flex w-full justify-center items-center gap-2">
                        <a href="#" class="btn border border-default-200 flex-grow hover:bg-default-150 shadow-sm hover:text-default-800">
                            <i class="iconify-color logos--google-icon"></i>
                            Use Google
                        </a>

                        <a href="#" class="btn border border-default-200 flex-grow hover:bg-default-150 shadow-sm hover:text-default-800">
                            <i class="iconify logos--apple text-mono"></i>
                            Use Apple
                        </a>
                    </div> -->

                    <div class="mt-10 text-center">
                        <p class="text-base text-default-500">Don't have an Account ?
                            <span class="font-semibold">Contact Admin</span>
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <div class="absolute inset-0 overflow-hidden">
            <svg aria-hidden="true" class="absolute inset-0 size-full fill-black/2 stroke-black/5 dark:fill-white/2.5 dark:stroke-white/2.5">
                <defs>
                    <pattern id="authPattern" width="56" height="56" patternUnits="userSpaceOnUse" x="50%" y="16">
                        <path d="M.5 56V.5H72" fill="none"></path>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" stroke-width="0" fill="url(#authPattern)"></rect>
            </svg>
        </div>
    </div>
</body>
</html>