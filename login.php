<?php
session_start();
require_once 'config.php';

// If already logged in and session is valid, redirect to admin
if (isset($_SESSION['admin_logged_in']) && isset($_SESSION['user_role'])) {
    if (in_array($_SESSION['user_role'], ['admin', 'manager'])) {
        header('Location: admin.php');
        exit;
    }
}

// Clear invalid session
if (isset($_SESSION['admin_logged_in']) && !isset($_SESSION['user_role'])) {
    session_destroy();
    session_start();
}

$error = null;

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $user = authenticateUser($conn, $username, $password);

    if ($user) {
        // Check if user has admin or manager role
        if (in_array($user['role'], ['admin', 'manager'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_name'] = $user['nama_lengkap'];
            $_SESSION['user_role'] = $user['role'];

            header('Location: admin.php');
            exit;
        } else {
            $error = 'User Anda hanya memiliki akses viewer. Silakan hubungi administrator.';
        }
    } else {
        $error = 'Username atau password salah!';
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0f1e;
            --surface: #111827;
            --border: #1e2d45;
            --text: #e2e8f0;
            --accent: #6366f1;
            --k1: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Sora', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-container {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(90deg, var(--k1), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: #94a3b8;
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
        }

        input {
            width: 100%;
            padding: 0.75rem;
            background: #1a2235;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-family: 'Sora', sans-serif;
            font-size: 1rem;
            transition: all 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--accent);
            background: #1f2a3a;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1rem;
            margin-top: 1rem;
        }

        button:hover {
            background: #4f46e5;
        }

        .error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .info-box {
            background: rgba(99, 102, 241, 0.08);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: #cbd5e1;
            line-height: 1.6;
        }

        .info-box strong {
            color: var(--accent);
            display: block;
            margin-bottom: 0.5rem;
        }

        .credentials {
            margin-top: 0.5rem;
            font-family: 'Courier New', monospace;
            background: rgba(0, 0, 0, 0.2);
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-logo">
            <h1></h1>
            <p class="subtitle">Admin Panel - SIG K-Means++ Wisata</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" name="login">Masuk</button>
        </form>

        <div class="info-box">
            <strong>🔐 Default Credentials (Demo):</strong>
            <div class="credentials">
                <strong>Admin:</strong><br>
                Username: <code>admin</code><br>
                Password: <code>admin123</code>
            </div>

        </div>
    </div>
</body>

</html>