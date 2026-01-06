<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/db.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT username, email FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$username = $user['username'];
$email = $user['email'];

// Fetch inventory metrics
$sql = "SELECT COUNT(*) AS total_medicines FROM medicines";
$stmt = $conn->prepare($sql);
$stmt->execute();
$total_medicines = $stmt->get_result()->fetch_assoc()['total_medicines'];

$sql = "SELECT COUNT(*) AS low_stock FROM medicines WHERE stock < 10";
$stmt = $conn->prepare($sql);
$stmt->execute();
$low_stock = $stmt->get_result()->fetch_assoc()['low_stock'];

// Fetch orders metrics
$sql = "SELECT COUNT(*) AS pending_orders FROM orders WHERE status = ?";
$stmt = $conn->prepare($sql);
$status = 'Pending';
$stmt->bind_param("s", $status);
$stmt->execute();
$pending_orders = $stmt->get_result()->fetch_assoc()['pending_orders'];

$sql = "SELECT COUNT(*) AS completed_orders FROM orders WHERE status = ?";
$stmt = $conn->prepare($sql);
$status = 'Completed';
$stmt->bind_param("s", $status);
$stmt->execute();
$completed_orders = $stmt->get_result()->fetch_assoc()['completed_orders'];

// Fetch recent orders (last 5)
$sql = "SELECT o.id, o.customer_name, o.quantity, o.total_price, o.created_at, o.status, m.name AS medicine_name 
        FROM orders o JOIN medicines m ON o.medicine_id = m.id 
        ORDER BY o.created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->execute();
$recent_orders = $stmt->get_result();

// Fetch low stock medicines
$sql = "SELECT id, name, stock FROM medicines WHERE stock < 10 ORDER BY stock ASC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->execute();
$low_stock_medicines = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Hewa Pharmacy System</title>
    <!-- Bootstrap CSS -->
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="main.css">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-prescription-bottle-alt"></i> Hewa Pharmacy</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link text-light">Welcome, <?php echo htmlspecialchars($username); ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-light" href="?logout=true"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <h2 class="my-4 text-primary">Dashboard</h2>

            <!-- Quick Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card dashboard-card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-boxes fa-2x text-primary me-3"></i>
                                <div>
                                    <h5 class="card-title">Total Medicines</h5>
                                    <h3 class="card-text"><?php echo htmlspecialchars($total_medicines); ?></h3>
                                </div>
                            </div>
                            <a href="inventory.php" class="btn btn-outline-primary mt-3">View Inventory</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card dashboard-card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning me-3"></i>
                                <div>
                                    <h5 class="card-title">Low Stock</h5>
                                    <h3 class="card-text"><?php echo htmlspecialchars($low_stock); ?></h3>
                                </div>
                            </div>
                            <a href="inventory.php" class="btn btn-outline-warning mt-3">Manage Stock</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card dashboard-card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-shopping-cart fa-2x text-info me-3"></i>
                                <div>
                                    <h5 class="card-title">Pending Orders</h5>
                                    <h3 class="card-text"><?php echo htmlspecialchars($pending_orders); ?></h3>
                                </div>
                            </div>
                            <a href="order.php" class="btn btn-outline-info mt-3">View Orders</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card dashboard-card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle fa-2x text-success me-3"></i>
                                <div>
                                    <h5 class="card-title">Completed Orders</h5>
                                    <h3 class="card-text"><?php echo htmlspecialchars($completed_orders); ?></h3>
                                </div>
                            </div>
                            <a href="order.php" class="btn btn-outline-success mt-3">View Orders</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders and Low Stock -->
            <div class="row g-4">
                <!-- Recent Orders -->
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Recent Orders</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Medicine</th>
                                        <th>Customer</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_orders->num_rows > 0): ?>
                                        <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($order['id']); ?></td>
                                                <td><?php echo htmlspecialchars($order['medicine_name']); ?></td>
                                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $order['status'] === 'Pending' ? 'warning' : 'success'; ?>">
                                                        <?php echo htmlspecialchars($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center">No recent orders.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <a href="order.php" class="btn btn-primary">View All Orders</a>
                        </div>
                    </div>
                </div>
                <!-- Low Stock Medicines -->
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0">Low Stock Medicines</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Medicine</th>
                                        <th>Stock</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($low_stock_medicines->num_rows > 0): ?>
                                        <?php while ($medicine = $low_stock_medicines->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($medicine['id']); ?></td>
                                                <td><?php echo htmlspecialchars($medicine['name']); ?></td>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <?php echo htmlspecialchars($medicine['stock']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="inventory.php" class="btn btn-sm btn-warning">Restock</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center">No low stock medicines.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <a href="inventory.php" class="btn btn-warning">Manage Inventory</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Chart -->
            <div class="row g-4 mt-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Orders Status Overview</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="ordersChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-3 bg-light mt-5">
        <p class="text-muted">© 2025 Hewa Pharmacy System. All Rights Reserved.</p>
    </footer>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="bootstrap/js/bootstrap.min.js"></script>
    <!-- Chart.js Script -->
    <script>
        const ctx = document.getElementById('ordersChart').getContext('2d');
        const ordersChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Pending Orders', 'Completed Orders'],
                datasets: [{
                    data: [<?php echo $pending_orders; ?>, <?php echo $completed_orders; ?>],
                    backgroundColor: ['#ffc107', '#28a745'],
                    borderColor: ['#ffffff', '#ffffff'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Orders Distribution' }
                }
            }
        });
    </script>
</body>
</html>