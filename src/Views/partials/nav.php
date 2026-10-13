<?php

use App\Core\Auth;
use App\Core\Session;

$currentUser = Auth::user();
$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');
?>
<nav style="background: #111827; border-bottom: 1px solid #374151; padding: 0.85rem 2rem; display: flex; justify-content: space-between; align-items: center;">
    <div style="display: flex; align-items: center; gap: 1.5rem;">
        <span style="font-weight: 700; color: #38bdf8; font-size: 1.1rem; letter-spacing: -0.025em;">PharmaDEM</span>
        <div style="display: flex; gap: 1rem; font-size: 0.875rem;">
            <a href="/employee/dashboard.php" style="color: #94a3b8; text-decoration: none; padding: 0.25rem 0.5rem; border-radius: 4px;">Employee Portal</a>
            <a href="/manager/dashboard.php" style="color: #94a3b8; text-decoration: none; padding: 0.25rem 0.5rem; border-radius: 4px;">Manager Review</a>
            <a href="/admin/dashboard.php" style="color: #94a3b8; text-decoration: none; padding: 0.25rem 0.5rem; border-radius: 4px;">Admin Center</a>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 1rem; font-size: 0.875rem;">
        <span style="color: #e2e8f0;">
            <strong><?= htmlspecialchars($currentUser['name'] ?? 'User') ?></strong>
            <span style="display: inline-block; margin-left: 0.4rem; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #1e293b; border: 1px solid #475569; color: #38bdf8;">
                <?= htmlspecialchars($currentUser['role'] ?? '') ?>
            </span>
        </span>
        <a href="/logout.php" style="color: #f87171; text-decoration: none; font-weight: 500; padding: 0.35rem 0.75rem; border-radius: 6px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);">Logout</a>
    </div>
</nav>

<?php if ($flashSuccess): ?>
    <div style="max-width: 1000px; margin: 1.5rem auto 0; padding: 0.75rem 1rem; border-radius: 6px; background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.875rem;">
        ✅ <?= htmlspecialchars($flashSuccess) ?>
    </div>
<?php endif; ?>

<?php if ($flashError): ?>
    <div style="max-width: 1000px; margin: 1.5rem auto 0; padding: 0.75rem 1rem; border-radius: 6px; background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); font-size: 0.875rem;">
        ⚠️ <?= htmlspecialchars($flashError) ?>
    </div>
<?php endif; ?>
