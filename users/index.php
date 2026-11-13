<?php

$title = 'Users';
require '../includes/header.php';

if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    die('Access denied');
}

$pdo = db();
$currentUserId = (int)($_SESSION['user']['id'] ?? 0);

/*
|--------------------------------------------------------------------------
| CREATE / UPDATE / TOGGLE USER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $action = $_POST['action'] ?? '';

    try {

        /*
        |--------------------------------------------------------------------------
        | CREATE USER
        |--------------------------------------------------------------------------
        */
        if ($action === 'create') {

            $name     = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role     = $_POST['role'] ?? 'cashier';

            $allowedRoles = ['admin', 'pharmacist', 'cashier', 'storekeeper'];

            if ($name === '' || $username === '' || $password === '') {
                throw new Exception('Please fill all required fields.');
            }

            if (!in_array($role, $allowedRoles, true)) {
                throw new Exception('Invalid user role.');
            }

            if (strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters.');
            }

            $check = $pdo->prepare(
                'SELECT id FROM users WHERE username = ? LIMIT 1'
            );
            $check->execute([$username]);

            if ($check->fetch()) {
                throw new Exception('Username already exists.');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'INSERT INTO users
                (name, username, password_hash, role, status)
                VALUES (?, ?, ?, ?, "Active")'
            );

            $stmt->execute([
                $name,
                $username,
                $hash,
                $role
            ]);

            flash('success', 'User created successfully.');
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE USER
        |--------------------------------------------------------------------------
        */
        elseif ($action === 'update') {

            $id       = (int)($_POST['id'] ?? 0);
            $name     = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $role     = $_POST['role'] ?? 'cashier';
            $password = $_POST['password'] ?? '';

            $allowedRoles = ['admin', 'pharmacist', 'cashier', 'storekeeper'];

            if ($id <= 0 || $name === '' || $username === '') {
                throw new Exception('Invalid user information.');
            }

            if (!in_array($role, $allowedRoles, true)) {
                throw new Exception('Invalid user role.');
            }

            /*
             * Prevent duplicate username
             */
            $check = $pdo->prepare(
                'SELECT id FROM users
                 WHERE username = ?
                 AND id <> ?
                 LIMIT 1'
            );

            $check->execute([
                $username,
                $id
            ]);

            if ($check->fetch()) {
                throw new Exception('Username already exists.');
            }

            /*
             * Update basic information
             */
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET name = ?, username = ?, role = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                $name,
                $username,
                $role,
                $id
            ]);

            /*
             * Change password only if entered
             */
            if ($password !== '') {

                if (strlen($password) < 6) {
                    throw new Exception(
                        'New password must be at least 6 characters.'
                    );
                }

                $hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare(
                    'UPDATE users
                     SET password_hash = ?
                     WHERE id = ?'
                );

                $stmt->execute([
                    $hash,
                    $id
                ]);
            }

            flash('success', 'User updated successfully.');
        }


        /*
        |--------------------------------------------------------------------------
        | ACTIVATE / DEACTIVATE
        |--------------------------------------------------------------------------
        */
        elseif ($action === 'toggle') {

            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Invalid user.');
            }

            /*
             * Admin cannot deactivate himself
             */
            if ($id === $currentUserId) {
                throw new Exception(
                    'You cannot deactivate your own account.'
                );
            }

            $stmt = $pdo->prepare(
                'SELECT status FROM users WHERE id = ? LIMIT 1'
            );

            $stmt->execute([$id]);

            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception('User not found.');
            }

            $newStatus =
                ($user['status'] === 'Active')
                ? 'Inactive'
                : 'Active';

            $stmt = $pdo->prepare(
                'UPDATE users
                 SET status = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                $newStatus,
                $id
            ]);

            flash(
                'success',
                'User status changed to ' . $newStatus . '.'
            );
        }

    } catch (Throwable $e) {

        flash(
            'error',
            $e->getMessage()
        );
    }

    redirect('users/index.php');
}


/*
|--------------------------------------------------------------------------
| EDIT MODE
|--------------------------------------------------------------------------
*/

$editUser = null;

if (isset($_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    if ($editId > 0) {

        $stmt = $pdo->prepare(
            'SELECT id,name,username,role,status
             FROM users
             WHERE id = ?
             LIMIT 1'
        );

        $stmt->execute([$editId]);

        $editUser = $stmt->fetch();
    }
}


/*
|--------------------------------------------------------------------------
| GET USERS
|--------------------------------------------------------------------------
*/

$rows = $pdo->query(
    'SELECT id,name,username,role,status,created_at
     FROM users
     ORDER BY id DESC'
)->fetchAll();

?>

<div class="actions">
    <h1 style="margin-right:auto;">User Management</h1>

    <?php if ($editUser): ?>

        <a class="btn" href="index.php">
            + Create New User
        </a>

    <?php endif; ?>
</div>


<?php if ($editUser): ?>

<!-- ==========================================================
     EDIT USER
=========================================================== -->

<div class="form-card">

    <h2>Edit User</h2>

    <form method="post">

        <input
            type="hidden"
            name="csrf"
            value="<?= csrf() ?>"
        >

        <input
            type="hidden"
            name="action"
            value="update"
        >

        <input
            type="hidden"
            name="id"
            value="<?= (int)$editUser['id'] ?>"
        >

        <div class="grid">

            <p>
                <label>Name</label>

                <input
                    type="text"
                    name="name"
                    required
                    value="<?= e($editUser['name']) ?>"
                >
            </p>


            <p>
                <label>Username</label>

                <input
                    type="text"
                    name="username"
                    required
                    value="<?= e($editUser['username']) ?>"
                >
            </p>


            <p>
                <label>New Password</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Leave blank to keep current password"
                >
            </p>


            <p>
                <label>Role</label>

                <select name="role" required>

                    <option
                        value="admin"
                        <?= $editUser['role'] === 'admin' ? 'selected' : '' ?>
                    >
                        Admin
                    </option>

                    <option
                        value="pharmacist"
                        <?= $editUser['role'] === 'pharmacist' ? 'selected' : '' ?>
                    >
                        Pharmacist
                    </option>

                    <option
                        value="cashier"
                        <?= $editUser['role'] === 'cashier' ? 'selected' : '' ?>
                    >
                        Cashier
                    </option>

                    <option
                        value="storekeeper"
                        <?= $editUser['role'] === 'storekeeper' ? 'selected' : '' ?>
                    >
                        Storekeeper
                    </option>

                </select>

            </p>

        </div>

        <button type="submit">
            Save Changes
        </button>

        <a
            class="btn"
            href="index.php"
            style="margin-left:8px;"
        >
            Cancel
        </a>

    </form>

</div>

<?php else: ?>

<!-- ==========================================================
     CREATE USER
=========================================================== -->

<div class="form-card">

    <h2>Create New User</h2>

    <form method="post">

        <input
            type="hidden"
            name="csrf"
            value="<?= csrf() ?>"
        >

        <input
            type="hidden"
            name="action"
            value="create"
        >

        <div class="grid">

            <p>
                <label>Name</label>

                <input
                    type="text"
                    name="name"
                    placeholder="Full name"
                    required
                >
            </p>


            <p>
                <label>Username</label>

                <input
                    type="text"
                    name="username"
                    placeholder="Login username"
                    required
                >
            </p>


            <p>
                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Minimum 6 characters"
                    required
                >
            </p>


            <p>
                <label>Role</label>

                <select name="role" required>

                    <option value="admin">
                        Admin
                    </option>

                    <option value="pharmacist">
                        Pharmacist
                    </option>

                    <option value="cashier" selected>
                        Cashier
                    </option>

                    <option value="storekeeper">
                        Storekeeper
                    </option>

                </select>

            </p>

        </div>

        <button type="submit">
            Create User
        </button>

    </form>

</div>

<?php endif; ?>


<!-- ==========================================================
     USERS TABLE
=========================================================== -->

<div style="margin-top:25px;">

    <h2>System Users</h2>

    <table>

        <tr>

            <th>Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Status</th>
            <th>Created</th>
            <th>Action</th>

        </tr>


        <?php foreach ($rows as $r): ?>

        <tr>

            <td>
                <b><?= e($r['name']) ?></b>
            </td>


            <td>
                <?= e($r['username']) ?>
            </td>


            <td>

                <?php

                $roleLabel = [
                    'admin'      => 'Admin',
                    'pharmacist' => 'Pharmacist',
                    'cashier'    => 'Cashier',
                    'storekeeper' => 'Storekeeper'
                ];

                echo e(
                    $roleLabel[$r['role']] ?? $r['role']
                );

                ?>

            </td>


            <td>

                <?php if ($r['status'] === 'Active'): ?>

                    <span
                        style="
                            background:#dff5e8;
                            color:#087443;
                            padding:5px 10px;
                            border-radius:12px;
                            font-size:12px;
                            font-weight:bold;
                        "
                    >
                        ACTIVE
                    </span>

                <?php else: ?>

                    <span
                        style="
                            background:#fce1e1;
                            color:#b42318;
                            padding:5px 10px;
                            border-radius:12px;
                            font-size:12px;
                            font-weight:bold;
                        "
                    >
                        INACTIVE
                    </span>

                <?php endif; ?>

            </td>


            <td>
                <?= e($r['created_at']) ?>
            </td>


            <td>

                <a
                    class="btn"
                    href="index.php?edit=<?= (int)$r['id'] ?>"
                >
                    Edit
                </a>


                <?php if ((int)$r['id'] !== $currentUserId): ?>

                    <form
                        method="post"
                        style="display:inline;"
                        onsubmit="return confirm(
                            'Are you sure you want to change this user status?'
                        );"
                    >

                        <input
                            type="hidden"
                            name="csrf"
                            value="<?= csrf() ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="toggle"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int)$r['id'] ?>"
                        >

                        <button
                            type="submit"
                            style="
                                margin-left:5px;
                                background:<?= $r['status'] === 'Active'
                                    ? '#dc2626'
                                    : '#087443' ?>;
                            "
                        >
                            <?= $r['status'] === 'Active'
                                ? 'Deactivate'
                                : 'Activate' ?>
                        </button>

                    </form>

                <?php else: ?>

                    <small
                        style="
                            margin-left:8px;
                            color:#777;
                        "
                    >
                        Current Account
                    </small>

                <?php endif; ?>

            </td>

        </tr>

        <?php endforeach; ?>

    </table>

</div>


<?php require '../includes/footer.php'; ?>