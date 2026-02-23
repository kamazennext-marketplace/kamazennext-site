<?php
session_start();

// Protect page — redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: /forms/login.html");
    exit;
}

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$userEmail = htmlspecialchars($_SESSION['user_email'] ?? '');
$userId = (int) $_SESSION['user_id'];

// Initials avatar
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $_SESSION['user_name'] ?? 'U')));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Kama ZenNext</title>
    <meta name="description" content="Your personal Kama ZenNext dashboard — manage your account and explore tools.">
    <link rel="stylesheet" href="/assets/css/main-dark.css?v=3">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="/assets/js/header.js" defer></script>
    <style>
        /* ── Dashboard Layout ── */
        .dashboard-page {
            min-height: 100vh;
            padding: 6rem 1.5rem 4rem;
            background: var(--bg, #0d0d12);
        }

        .dashboard-grid {
            max-width: 1000px;
            margin: 0 auto;
            display: grid;
            gap: 1.5rem;
        }

        /* ── Profile Card ── */
        .profile-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            backdrop-filter: blur(12px);
        }

        .avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            letter-spacing: 1px;
        }

        .profile-info h2 {
            margin: 0 0 0.25rem;
            font-size: 1.4rem;
            font-weight: 700;
            color: #fff;
        }

        .profile-info p {
            margin: 0;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.95rem;
        }

        .profile-badge {
            margin-left: auto;
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #818cf8;
            padding: 0.3rem 0.9rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* ── Quick Actions ── */
        .section-heading {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.35);
            margin-bottom: 0.75rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 14px;
            padding: 1.5rem 1.25rem;
            text-decoration: none;
            color: #fff;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            transition: background 0.2s, border-color 0.2s, transform 0.15s;
        }

        .action-card:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: rgba(99, 102, 241, 0.35);
            transform: translateY(-2px);
            color: #fff;
        }

        .action-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .action-card h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .action-card p {
            margin: 0;
            font-size: 0.83rem;
            color: rgba(255, 255, 255, 0.45);
            line-height: 1.5;
        }

        /* icon colour variants */
        .icon-purple {
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
        }

        .icon-blue {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
        }

        .icon-green {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
        }

        .icon-orange {
            background: rgba(249, 115, 22, 0.15);
            color: #fb923c;
        }

        /* ── Logout strip ── */
        .logout-strip {
            display: flex;
            justify-content: flex-end;
            margin-top: 0.5rem;
        }

        .btn-logout {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #f87171;
            padding: 0.6rem 1.4rem;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s;
        }

        .btn-logout:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        @media (max-width: 480px) {
            .profile-card {
                flex-direction: column;
                text-align: center;
            }

            .profile-badge {
                margin: 0 auto;
            }
        }
    </style>
</head>

<body>
    <div id="site-header"></div>

    <main class="dashboard-page">
        <div class="dashboard-grid">

            <!-- Profile Card -->
            <div class="profile-card">
                <div class="avatar">
                    <?php echo $initials; ?>
                </div>
                <div class="profile-info">
                    <h2>Welcome back,
                        <?php echo $userName; ?> 👋
                    </h2>
                    <p>
                        <?php echo $userEmail; ?>
                    </p>
                </div>
                <span class="profile-badge">Member</span>
            </div>

            <!-- Quick Actions -->
            <div>
                <p class="section-heading">Quick Actions</p>
                <div class="actions-grid">
                    <a href="/software.html" class="action-card">
                        <div class="action-icon icon-purple"><i class="fas fa-magnifying-glass"></i></div>
                        <h3>Browse Tools</h3>
                        <p>Explore AI tools, SaaS apps and software.</p>
                    </a>
                    <a href="/compare.html" class="action-card">
                        <div class="action-icon icon-blue"><i class="fas fa-code-compare"></i></div>
                        <h3>Compare</h3>
                        <p>Compare software side by side.</p>
                    </a>
                    <a href="/submit-tool.html" class="action-card">
                        <div class="action-icon icon-green"><i class="fas fa-plus-circle"></i></div>
                        <h3>Suggest a Tool</h3>
                        <p>Know a great tool? Add it to the directory.</p>
                    </a>
                    <a href="/ai-assistant.html" class="action-card">
                        <div class="action-icon icon-orange"><i class="fas fa-robot"></i></div>
                        <h3>AI Assistant</h3>
                        <p>Get personalised software recommendations.</p>
                    </a>
                </div>
            </div>

            <!-- Logout -->
            <div class="logout-strip">
                <button class="btn-logout" id="logoutBtn">
                    <i class="fas fa-right-from-bracket"></i> Sign Out
                </button>
            </div>

        </div>
    </main>

    <script>
        document.getElementById('logoutBtn').addEventListener('click', async function () {
            this.textContent = 'Signing out...';
            this.disabled = true;
            try {
                await fetch('/api/v1/auth/logout', { method: 'POST' });
            } catch (e) { }
            window.location.href = '/forms/login.html';
        });
    </script>
</body>

</html>