<?php
require_once 'auth.php';

if (!$isSuperAdmin) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Access denied. Administrators only.'];
    header("Location: index.php");
    exit();
}

function roleName(int $role): string {
    return [1 => 'Admin', 2 => 'Customer', 3 => 'Owner'][$role] ?? 'Unknown';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);
    $return   = ($_POST['return'] ?? '') === 'index.php' ? 'index.php' : 'manage_users.php';

    if ($action === 'change_role') {
        $newRole = (int)($_POST['role_id'] ?? 0);

        if (!in_array($newRole, [1, 2, 3], true)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid role.'];
        } elseif ($targetId === $userId) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'You cannot change your own role.'];
        } else {
            $stmt = $conn->prepare("UPDATE users SET role_id = ? WHERE id = ?");
            $stmt->bind_param('ii', $newRole, $targetId);
            $stmt->execute();
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'User role updated.'];
        }

    } elseif ($action === 'update_status') {
        $status = $_POST['status'] ?? '';

        if (!in_array($status, ['approved', 'denied'], true)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid account status.'];
        } elseif ($targetId === $userId) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'You cannot change your own account status.'];
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $targetId);
            $stmt->execute();

            $_SESSION['flash'] = $stmt->affected_rows > 0
                ? ['type' => 'success', 'message' => $status === 'approved' ? 'Account approved.' : 'Account declined.']
                : ['type' => 'warning', 'message' => 'No account status was changed.'];
        }

    } elseif ($action === 'delete_user') {
        if ($targetId === $userId) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'You cannot delete your own account.'];
        } else {
            // Cart rows, orders and product ownership are handled by the foreign keys.
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $targetId);
            $stmt->execute();
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'User deleted.'];
        }
    }

    header("Location: $return");
    exit();
}

$users = $conn->query("
    SELECT id, f_name, l_name, email, username, role_id, status, created_at
    FROM users ORDER BY (status = 'pending') DESC, id ASC
")->fetch_all(MYSQLI_ASSOC);

$counts = ['all' => count($users), 'pending' => 0, 'approved' => 0, 'denied' => 0];
foreach ($users as $u) {
    $counts[$u['status']]++;
}

$pageTitle = 'Users & Approvals';
$pageCrumb = 'Administration';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include 'head.php'; ?></head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <?php include 'topbar.php'; ?>

    <div class="page-body">
        <?php include 'flash.php'; ?>

        <div class="section-head">
            <div>
                <h2>Accounts</h2>
                <p>Approve or decline new registrations and manage roles.</p>
            </div>
            <ul class="nav nav-pills gap-1" id="statusTabs">
                <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Declined'] as $key => $label): ?>
                    <li class="nav-item">
                        <button type="button" class="nav-link py-1 px-3 <?= $key === ($counts['pending'] ? 'pending' : 'all') ? 'active' : '' ?>" data-filter="<?= $key ?>">
                            <?= $label ?> <span class="badge text-bg-light border ms-1"><?= $counts[$key] ?></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Approval</th>
                            <th style="width: 230px;">Change role</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $self = (int)$u['id'] === $userId;
                        $statusClass = ['approved' => 'success', 'denied' => 'danger'][$u['status']] ?? 'warning';
                        ?>
                        <tr data-status="<?= htmlspecialchars($u['status']) ?>">
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($u['f_name'] . ' ' . $u['l_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($u['username']) ?> · <?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td><span class="badge text-bg-<?= (int)$u['role_id'] === 1 ? 'primary' : ((int)$u['role_id'] === 3 ? 'info' : 'secondary') ?>"><?= roleName((int)$u['role_id']) ?></span></td>
                            <td><span class="badge text-bg-<?= $statusClass ?>"><?= $u['status'] === 'denied' ? 'Declined' : htmlspecialchars(ucfirst($u['status'])) ?></span></td>
                            <td>
                                <?php if ($self): ?>
                                    <span class="text-muted small">Your account</span>
                                <?php else: ?>
                                    <form method="POST" class="d-flex gap-1">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <button name="status" value="approved" class="btn btn-success btn-sm" <?= $u['status'] === 'approved' ? 'disabled' : '' ?>>Approve</button>
                                        <button name="status" value="denied" class="btn btn-outline-danger btn-sm" <?= $u['status'] === 'denied' ? 'disabled' : '' ?>>Decline</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$self): ?>
                                    <form method="POST" class="d-flex gap-1">
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <select name="role_id" class="form-select form-select-sm" style="min-width:110px">
                                            <option value="1" <?= (int)$u['role_id'] === 1 ? 'selected' : '' ?>>Admin</option>
                                            <option value="2" <?= (int)$u['role_id'] === 2 ? 'selected' : '' ?>>Customer</option>
                                            <option value="3" <?= (int)$u['role_id'] === 3 ? 'selected' : '' ?>>Owner</option>
                                        </select>
                                        <button class="btn btn-outline-primary btn-sm">Save</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (!$self): ?>
                                    <form method="POST" onsubmit="return confirm('Delete this user permanently?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm" title="Delete user"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var tabs = document.querySelectorAll('#statusTabs .nav-link');
    function apply(filter) {
        tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.filter === filter); });
        document.querySelectorAll('tbody tr').forEach(function (tr) {
            tr.style.display = (filter === 'all' || tr.dataset.status === filter) ? '' : 'none';
        });
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { apply(t.dataset.filter); }); });
    apply(document.querySelector('#statusTabs .nav-link.active').dataset.filter);
})();
</script>
</body>
</html>
