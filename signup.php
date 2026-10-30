<?php
include 'connection.php';

$error = '';

if (isset($_POST['save'])) {
    $f_name   = trim($_POST['f_name'] ?? '');
    $l_name   = trim($_POST['l_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $roleMap  = ['customer' => 2, 'owner' => 3];
    $roleId   = $roleMap[$_POST['account_type'] ?? ''] ?? 0;
    $status   = 'pending';

    if ($f_name === '' || $l_name === '' || $email === '' || $username === '' || $password === '') {
        $error = "All fields are required.";
    } elseif (!$roleId) {
        $error = "Please choose an account type.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (f_name, l_name, email, username, password, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssssis', $f_name, $l_name, $email, $username, $hash, $roleId, $status);
            $stmt->execute();

            session_start();
            $_SESSION['flash_login'] = ['type' => 'success', 'message' => 'Account created. Please wait for an administrator to approve it before signing in.'];
            header("Location: login.php");
            exit();
        } catch (mysqli_sql_exception $e) {
            $error = $e->getCode() === 1062
                ? "That username or email is already registered."
                : "Registration failed. Please try again.";
        }
    }
}

$pageTitle = 'Create account';
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include 'head.php'; ?></head>
<body>
    <div class="auth-wrap">
        <div class="auth-card" style="max-width: 500px;">
            <div class="text-center mb-4">
                <div class="brand-icon mb-3"><i class="bi bi-person-plus"></i></div>
                <h1 class="h4 fw-semibold mb-1">Create your account</h1>
                <p class="text-muted small mb-0">An administrator will review your request before you can sign in.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label class="form-label">First name</label>
                        <input type="text" name="f_name" class="form-control" value="<?= htmlspecialchars($_POST['f_name'] ?? '') ?>" required>
                    </div>
                    <div class="col">
                        <label class="form-label">Last name</label>
                        <input type="text" name="l_name" class="form-control" value="<?= htmlspecialchars($_POST['l_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email address</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Account type</label>
                    <select name="account_type" class="form-select" required>
                        <option value="" disabled <?= empty($_POST['account_type']) ? 'selected' : '' ?>>Select account type</option>
                        <option value="customer" <?= ($_POST['account_type'] ?? '') === 'customer' ? 'selected' : '' ?>>Customer — browse and buy products</option>
                        <option value="owner" <?= ($_POST['account_type'] ?? '') === 'owner' ? 'selected' : '' ?>>Owner — list and sell products</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" minlength="6" autocomplete="new-password" required>
                    <div class="form-text">At least 6 characters.</div>
                </div>

                <button type="submit" name="save" class="btn btn-primary w-100 py-2">Create account</button>

                <p class="text-center small text-muted mt-3 mb-0">
                    Already registered? <a href="login.php" class="text-decoration-none fw-semibold">Sign in</a>
                </p>
            </form>
        </div>
    </div>
</body>
</html>
