<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sign Up | Tailwick - Tailwind CSS 3 Admin Layout & UI Kit Template</title>
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

    <div class="relative min-h-screen w-full flex justify-center items-center py-16 md:py-10">

        <div class="card md:w-lg w-screen z-10">
            <div class="text-center px-10 py-12">
                <!-- Logo -->
                <a href="index.php" class="flex justify-center">
                    <svg class="h-14 w-auto" viewBox="0 0 220 96" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Print Inventory Control System logo">
                        <defs>
                            <linearGradient id="picsGradRegister" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="#0B2A6E"/>
                                <stop offset="1" stop-color="#0EC5FF"/>
                            </linearGradient>
                        </defs>
                        <text x="110" y="25" text-anchor="middle" font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif" font-size="21" font-weight="800" letter-spacing="7" fill="url(#picsGradRegister)">PRINTING</text>
                        <text x="110" y="51" text-anchor="middle" font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif" font-size="21" font-weight="800" letter-spacing="7" fill="url(#picsGradRegister)">INVENTORY</text>
                        <text x="110" y="75" text-anchor="middle" font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif" font-size="12.5" font-weight="600" letter-spacing="4" fill="url(#picsGradRegister)">CONTROL SYSTEM</text>
                        <path d="M84 88h52" stroke="url(#picsGradRegister)" stroke-width="2" stroke-linecap="round" fill="none"/>
                    </svg>
                </a>

                <div class="mt-8 text-center">
                    <h4 class="mb-2.5 text-xl font-semibold text-primary">Create your free account</h4>
                    <p class="text-base text-default-500">Get your free Tailwick account now</p>
                </div>

                <!-- form -->
                <form action="backend/authentication/register.php" class="text-left w-full mt-10" method="POST">
                    <?php if (isset($_SESSION["success_message"])): ?>
                        <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                            <?= htmlspecialchars($_SESSION["success_message"], ENT_QUOTES, "UTF-8") ?>
                        </div>
                        <?php unset($_SESSION["success_message"]); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION["error_message"])): ?>
                        <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <?= htmlspecialchars($_SESSION["error_message"], ENT_QUOTES, "UTF-8") ?>
                        </div>
                        <?php unset($_SESSION["error_message"]); ?>
                    <?php endif; ?>

                    <div class="mb-4">
                        <label for="email" class="block font-medium text-default-900 text-sm mb-2">Enter email</label>
                        <input type="email" id="email" class="form-input" placeholder="Enter your email" name="email" required/>
                    </div>

                    <div class="mb-4">
                        <label for="Username" class="block font-medium text-default-900 text-sm mb-2">Username</label>
                        <input type="text" id="Username" class="form-input" placeholder="Enter Username" name="username" required/>
                    </div>

                    <div class="mb-4">
                        <label for="Password" class="block font-medium text-default-900 text-sm mb-2">Password</label>
                        <input type="password" id="Password" class="form-input" placeholder="Enter Password" name="password"/>
                    </div>

                    <p class="italic text-sm font-medium text-default-500">By registering you agree to the Tailwick <a href="#" class="underline">Terms of Use</a></p>


                    <div class="mt-10 text-center">
                        <button type="submit" class="btn bg-primary text-white w-full">Sign Up</button>
                    </div>

                    <div class="my-9 relative text-center before:absolute before:top-2.5 before:left-0 before:border-t before:border-t-default-200 before:w-full before:h-0.5 before:right-0 before:-z-0">
                        <h4 class="relative z-1 py-0.5 px-2 inline-block font-medium bg-card text-default-600">Create Account with</h4>
                    </div>

                    <div class="flex w-full justify-center items-center gap-2">
                        <a href="#" class="btn border border-default-200 flex-grow hover:bg-default-150 shadow-sm hover:text-default-800">
                            <i class="iconify-color logos--google-icon"></i>
                            Use Google
                        </a>

                        <a href="#" class="btn border border-default-200 flex-grow hover:bg-default-150 shadow-sm hover:text-default-800">
                            <i class="iconify logos--apple text-mono"></i>
                            Use Apple
                        </a>
                    </div>

                    <div class="mt-10 text-center">
                        <p class="text-base text-default-500">Already have an account ?
                            <a href="auth-basic-login.php" class="font-semibold underline hover:text-primary transition duration-200">Login</a>
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