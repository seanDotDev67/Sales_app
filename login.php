<?php
session_start();
include 'connection.php';

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            $sql = "SELECT id, username, password, role_id, status FROM users WHERE username = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $username);
            $stmt->execute();

            $user = $stmt->get_result()->fetch_assoc();

            $stored = $user['password'] ?? '';
            $passwordOk = $user && (
                password_verify($password, $stored) || hash_equals($stored, $password)
            );

            if ($passwordOk) {
                if ($user['status'] !== 'approved') {
                    $error = $user['status'] === 'denied'
                        ? 'Your account request was declined. Please contact an administrator.'
                        : 'Your account is waiting for administrator approval.';
                } else {
                    // Upgrade legacy plain-text passwords to hashes on first login
                    if (!password_get_info($stored)['algo']) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $up = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $up->bind_param('si', $newHash, $user['id']);
                        $up->execute();
                    }

                    // Prevent session fixation attacks
                    session_regenerate_id(true);

                    $_SESSION['user_id']  = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role_id']  = (int)$user['role_id'];   // 1 = Admin, 2 = Customer, 3 = Seller
                    $_SESSION['show_splash'] = true;

                    header("Location: index.php");
                    exit();
                }
            } else {
                $error = "Invalid username or password.";
            }
        } catch (mysqli_sql_exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>
<?php
$notice = $_SESSION['flash_login'] ?? null;
unset($_SESSION['flash_login']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Log In</title>
    <style>
        :root { --brand: #1f4e8c; --brand-dark: #163a68; --ink: #1c2530; --line: #d5dbe3; }
        body {
            background: #eef1f5;
            color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        /* Splash screen */
        #splash {
            position: fixed; inset: 0; z-index: 2000;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: var(--brand-dark);
            color: #fff;
            transition: opacity .6s ease .1s, visibility .6s ease .1s, transform .6s ease .1s;
        }
        #splash.hide { opacity: 0; visibility: hidden; transform: scale(1.04); }
        #splash .mark {
            width: 72px; height: 72px; border-radius: 18px;
            border: 2px solid rgba(255,255,255,.85);
            display: grid; place-items: center; font-size: 2rem;
            animation: pop .7s cubic-bezier(.2,.9,.3,1.2) both;
        }
        #splash .name {
            margin-top: 1.1rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; font-size: .95rem;
            animation: rise .6s ease .35s both;
        }
        #splash .bar {
            margin-top: 1.4rem; width: 140px; height: 3px; background: rgba(255,255,255,.2);
            border-radius: 3px; overflow: hidden; animation: rise .6s ease .5s both;
        }
        #splash .bar span {
            display: block; height: 100%; width: 100%; background: #fff;
            transform: translateX(-100%); animation: load 1.3s ease-in-out .6s forwards;
        }
        @keyframes pop  { from { opacity: 0; transform: scale(.6); } to { opacity: 1; transform: scale(1); } }
        @keyframes rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        @keyframes load { to { transform: translateX(0); } }

        /* Card */
        .login-card {
            max-width: 400px; width: 100%;
            background: #fff; border: 1px solid var(--line); border-radius: 10px;
            box-shadow: 0 8px 24px rgba(20, 35, 60, .08);
            opacity: 0; transform: translateY(14px);
        }
        body.ready .login-card { animation: rise .6s ease .15s forwards; }
        .brand-icon {
            width: 48px; height: 48px; border-radius: 10px; background: var(--brand); color: #fff;
            display: inline-grid; place-items: center; font-size: 1.4rem;
        }
        .login-card h1 { font-size: 1.45rem; font-weight: 650; }

        /* Inputs */
        .field { position: relative; }
        .field .input-group-text {
            background: #fff; border-color: var(--line); color: #7a8594;
            transition: color .2s, border-color .2s;
        }
        .field .form-control {
            border-color: var(--line); padding: .6rem .75rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .field .form-control:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(31,78,140,.14); }
        .field:focus-within .input-group-text { color: var(--brand); border-color: var(--brand); }
        .field .underline {
            position: absolute; left: 0; bottom: 0; height: 2px; width: 100%;
            background: var(--brand); border-radius: 2px;
            transform: scaleX(0); transform-origin: left; transition: transform .35s ease;
        }
        .field:focus-within .underline { transform: scaleX(1); }
        .field .form-control.tick { animation: tick .16s ease; }
        @keyframes tick { 50% { transform: translateY(-1px); } }
        .field .input-group-text i.bump { animation: bump .25s ease; }
        @keyframes bump { 40% { transform: scale(1.25); } }

        .btn-brand {
            background: var(--brand); border-color: var(--brand); color: #fff;
            transition: background .2s, transform .15s, box-shadow .2s;
        }
        .btn-brand:hover { background: var(--brand-dark); color: #fff; box-shadow: 0 4px 12px rgba(22,58,104,.25); }
        .btn-brand:active { transform: translateY(1px); }
        .login-card a { color: var(--brand); }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-delay: 0s !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
    <div id="splash" aria-hidden="true">
        <div class="mark"><i class="bi bi-bar-chart-line"></i></div>
        <div class="name">Sales Manager</div>
        <div class="bar"><span></span></div>
    </div>

    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="login-card p-4">
            <div class="text-center mb-4">
                <div class="brand-icon mb-3"><i class="bi bi-bar-chart-line"></i></div>
                <h1 class="mb-1">Sign in</h1>
                <p class="text-muted small mb-0">Use your account to access the sales system.</p>
            </div>

            <?php if (!empty($notice)): ?>
                <div class="alert alert-<?= htmlspecialchars($notice['type']) ?> py-2 small"><?= htmlspecialchars($notice['message']) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Username</label>
                    <div class="input-group field">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Username" autocomplete="username" required>
                        <span class="underline"></span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold">Password</label>
                    <div class="input-group field">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Password" autocomplete="current-password" required>
                        <span class="underline"></span>
                    </div>
                </div>

                <button type="submit" name="login" class="btn btn-brand w-100 py-2 fw-semibold">Log In</button>

                <div class="text-center mt-3">
                    <p class="small text-muted mb-0">
                        Don't have an account yet?
                        <a href="signup.php" class="text-decoration-none fw-semibold">Sign Up</a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var splash = document.getElementById('splash');
            var minShow = 1900;
            var start = Date.now();
            function reveal() {
                splash.classList.add('hide');
                document.body.classList.add('ready');
                setTimeout(function () { splash.remove(); }, 900);
            }
            window.addEventListener('load', function () {
                setTimeout(reveal, Math.max(0, minShow - (Date.now() - start)));
            });

            document.querySelectorAll('.field .form-control').forEach(function (input) {
                var icon = input.parentElement.querySelector('.input-group-text i');
                input.addEventListener('input', function () {
                    [[input, 'tick'], [icon, 'bump']].forEach(function (p) {
                        p[0].classList.remove(p[1]);
                        void p[0].offsetWidth;
                        p[0].classList.add(p[1]);
                    });
                });
            });
        })();
    </script>
</body>
</html>