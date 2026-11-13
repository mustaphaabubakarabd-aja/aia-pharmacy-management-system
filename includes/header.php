<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$u = $_SESSION['user'];
$role = strtolower($u['role'] ?? '');

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/*
|--------------------------------------------------------------------------
| ROLE-BASED PERMISSIONS
|--------------------------------------------------------------------------
*/

$permissions = [

    'admin' => [
        'dashboard',
        'pos',
        'products',
        'categories',
        'purchases',
        'suppliers',
        'expenses',
        'reports',
        'users',
        'settings',
        'backup'
    ],

    'pharmacist' => [
        'dashboard',
        'pos',
        'products',
        'categories',
        'reports'
    ],

    'cashier' => [
        'dashboard',
        'pos'
    ],

    'storekeeper' => [
        'dashboard',
        'products',
        'categories',
        'purchases',
        'suppliers',
        'reports'
    ]
];

/*
|--------------------------------------------------------------------------
| CURRENT PAGE DETECTION
|--------------------------------------------------------------------------
*/

$currentPath = str_replace('\\', '/', $_SERVER['PHP_SELF']);

$module = 'dashboard';

if (strpos($currentPath, '/pos/') !== false) {

    $module = 'pos';

} elseif (strpos($currentPath, '/products/') !== false) {

    $module = 'products';

} elseif (strpos($currentPath, '/categories/') !== false) {

    $module = 'categories';

} elseif (strpos($currentPath, '/purchases/') !== false) {

    $module = 'purchases';

} elseif (strpos($currentPath, 'suppliers.php') !== false) {

    $module = 'suppliers';

} elseif (strpos($currentPath, 'expenses.php') !== false) {

    $module = 'expenses';

} elseif (strpos($currentPath, '/reports/') !== false) {

    $module = 'reports';

} elseif (strpos($currentPath, '/users/') !== false) {

    $module = 'users';

} elseif (strpos($currentPath, 'settings.php') !== false) {

    $module = 'settings';

} elseif (strpos($currentPath, '/backup/') !== false) {

    $module = 'backup';
}

/*
|--------------------------------------------------------------------------
| PERMISSION CHECK
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $module,
        $permissions[$role] ?? [],
        true
    )
) {

    http_response_code(403);
    ?>

    <!doctype html>

    <html lang="en">

    <head>

        <meta charset="utf-8">

        <meta
            name="viewport"
            content="width=device-width,initial-scale=1"
        >

        <title>
            Access Denied - AIA PHARMACY
        </title>

        <style>

            * {
                box-sizing: border-box;
            }

            body {

                margin: 0;

                min-height: 100vh;

                display: flex;

                align-items: center;

                justify-content: center;

                font-family: Arial, sans-serif;

                background: #f4f7f6;

                color: #123;

            }

            .denied {

                width: 90%;

                max-width: 520px;

                background: #fff;

                border-radius: 14px;

                padding: 40px;

                text-align: center;

                box-shadow:
                    0 10px 30px
                    rgba(0,0,0,.10);

                border-top:
                    5px solid #dc2626;

            }

            .icon {

                font-size: 55px;

                margin-bottom: 15px;

            }

            h1 {

                margin:
                    0 0 10px;

                color: #b91c1c;

            }

            p {

                color: #555;

                line-height: 1.6;

            }

            .btn {

                display: inline-block;

                margin-top: 15px;

                padding:
                    11px 22px;

                background: #087f4f;

                color: #fff;

                text-decoration: none;

                border-radius: 7px;

                font-weight: 600;

            }

            .btn:hover {

                background: #05663f;

            }

        </style>

    </head>

    <body>

        <div class="denied">

            <div class="icon">
                🔒
            </div>

            <h1>
                Access Denied
            </h1>

            <p>
                Sorry, you do not have permission
                to access this section of
                AIA PHARMACY Management System.
            </p>

            <p>

                <strong>Your Role:</strong>

                <?= e(ucfirst($role)) ?>

            </p>

            <a
                class="btn"
                href="<?= BASE_URL ?>index.php"
            >
                ← Back to Dashboard
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}

?>

<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title>
        <?= e($title ?? APP_NAME) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/style.css?v=20261009"
    >

</head>

<body>

<header class="top">

    <div class="brand">

        <div class="logo">
            A
        </div>

        <div>

            <b>
                AIA PHARMACY
            </b>

            <small>
                Pharmacy Management System
            </small>

        </div>

    </div>

    <div class="user">

        👤 <?= e($u['name']) ?>

        ·

        <?= e(ucfirst($role)) ?>

        ·

        <a
            href="<?= BASE_URL ?>logout.php"
        >
            Logout
        </a>

    </div>

</header>

<div class="layout">

<aside>

    <!-- DASHBOARD -->
    <a class="<?= $module === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>index.php">

        🏠 Dashboard

    </a>


    <!-- POS -->
    <?php if (
        in_array(
            'pos',
            $permissions[$role] ?? [],
            true
        )
    ): ?>

        <a class="<?= $module === 'pos' ? 'active' : '' ?>" href="<?= BASE_URL ?>pos/index.php">

            🛒 POS / Sales

        </a>

    <?php endif; ?>


    <!-- PRODUCTS -->
    <?php if (
        in_array(
            'products',
            $permissions[$role] ?? [],
            true
        )
    ): ?>

        <a class="<?= $module === 'products' ? 'active' : '' ?>" href="<?= BASE_URL ?>products/index.php">

            💊 Products

        </a>

    <?php endif; ?>


    <!-- CATEGORIES -->
    <?php if (
        in_array(
            'categories',
            $permissions[$role] ?? [],
            true
        )
    ): ?>

        <a class="<?= $module === 'categories' ? 'active' : '' ?>" href="<?= BASE_URL ?>categories/index.php">

            🗂️ Categories

        </a>

    <?php endif; ?>


    <!-- PURCHASES -->
    <?php if (
        in_array(
            'purchases',
            $permissions[$role] ?? [],
            true
        )
    ): ?>

        <a class="<?= $module === 'purchases' ? 'active' : '' ?>" href="<?= BASE_URL ?>purchases/index.php">

            📦 Purchases

        </a>

    <?php endif; ?>


    <!-- SUPPLIERS -->
    <?php if (
        in_array(
            'suppliers',
            $permissions[$role] ?? [],
            true
        )
    ): ?>

        <a class="<?= $module === 'suppliers' ? 'active' : '' ?>" href="<?= BASE_URL ?>suppliers.php">

            🚚 Suppliers

        </a>

    <?php endif; ?>


    <!-- EXPENSES -->
    <?php if (
        in_array(
            'expenses',
            $permissions[$role] ?? [],
            true
        )
    ): ?>

        <a class="<?= $module === 'expenses' ? 'active' : '' ?>" href="<?= BASE_URL ?>expenses.php">

            💵 Expenses

        </a>

    <?php endif; ?>


    <!-- REPORTS -->
    <?php if (
        in_array(
            'reports',
            $permissions[$role] ?? [],
            true
        )
    ): ?>

        <a class="<?= $module === 'reports' ? 'active' : '' ?>" href="<?= BASE_URL ?>reports/index.php">

            📊 Reports

        </a>

    <?php endif; ?>


    <!-- ADMIN MENU -->
    <?php if ($role === 'admin'): ?>

        <!-- USERS -->

        <a class="<?= $module === 'users' ? 'active' : '' ?>" href="<?= BASE_URL ?>users/index.php">

            👥 Users

        </a>


        <!-- SETTINGS -->

        <a class="<?= $module === 'settings' ? 'active' : '' ?>" href="<?= BASE_URL ?>settings.php">

            ⚙️ Settings

        </a>


        <!-- BACKUP & RESTORE -->

        <a class="<?= $module === 'backup' ? 'active' : '' ?>" href="<?= BASE_URL ?>backup/index.php">

            💾 Backup & Restore

        </a>

    <?php endif; ?>

</aside>

<main>

<?php if ($flash): ?>

    <div
        class="alert <?= e($flash['type']) ?>"
    >

        <?= e($flash['msg']) ?>

    </div>

<?php endif; ?>