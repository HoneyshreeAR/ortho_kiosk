<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}
require_once 'config.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle delete if requested
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin_patients.php");
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count
$total = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$totalPages = ceil($total / $limit);

// Fix: Use bindValue with integer type for LIMIT and OFFSET
$stmt = $pdo->prepare("SELECT * FROM patients ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If still empty, try a simple query without LIMIT (for debugging)
if (empty($patients)) {
    // Fallback: get all patients (ignore pagination)
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalPages = 1;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Patients | Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .action-btn {
            padding: 5px 10px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.8rem;
            display: inline-block;
            margin: 2px;
        }
        .delete-btn { background: #dc3545; color: white; }
        .delete-btn:hover { background: #c82333; }
        .view-btn { background: #17a2b8; color: white; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { display: inline-block; padding: 8px 12px; margin: 0 4px; background: #f0f0f0; border-radius: 8px; text-decoration: none; color: #2c5f8a; }
        .pagination a.active { background: #2c5f8a; color: white; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
<div class="kiosk-container">
    <div class="header"><h1>👥 Manage Patients</h1><div class="user-info"><a href="admin_dashboard.php" style="color:white;">← Back</a></div></div>
    <div class="nav">
        <ul>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="admin_patients.php" class="active">Patients</a></li>
            <li><a href="admin_bills.php">Bills</a></li>
            <li><a href="admin_logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <div class="card">
            <h2 class="card-title">All Patients</h2>
            <?php if(empty($patients)): ?>
                <p style="color: red;">No patients found in database. Please add some patients first.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Name</th><th>Age</th><th>Gender</th><th>Phone</th><th>Email</th><th>Registered</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($patients as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['id']) ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['age']) ?></td>
                            <td><?= htmlspecialchars($p['gender']) ?></td>
                            <td><?= htmlspecialchars($p['phone']) ?></td>
                            <td><?= htmlspecialchars($p['email']) ?></td>
                            <td><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                            <td>
                                <a href="admin_view_patient.php?id=<?= $p['id'] ?>" class="action-btn view-btn">View</a>
                                <a href="?delete=<?= $p['id'] ?>" onclick="return confirm('Delete this patient?')" class="action-btn delete-btn">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if($totalPages > 1): ?>
                <div class="pagination">
                    <?php for($i=1; $i<=$totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>