<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/db.php';

// Fetch user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$username = $user['username'];

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle pay action
if (isset($_GET['pay'])) {
    $order_id = (int)$_GET['pay'];
    $sql = "SELECT status FROM orders WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($order = $result->fetch_assoc()) {
        if ($order['status'] === 'Completed') {
            $_SESSION['error'] = "Order is already paid.";
        } else {
            $sql = "UPDATE orders SET status = 'Completed' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $order_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Order marked as paid successfully!";
            } else {
                $_SESSION['error'] = "Error marking order as paid: " . $conn->error;
            }
        }
    } else {
        $_SESSION['error'] = "Order not found.";
    }
    header("Location: sales.php");
    exit();
}

// Handle create sale form submission
$error = "";
$success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $medicine_id = (int)$_POST['medicine_id'];
    $quantity = (int)$_POST['quantity'];
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $customer_name = trim($_POST['customer_name']);

    // Validation
    if (!$medicine_id || $quantity <= 0 || (!$customer_id && empty($customer_name))) {
        $error = "All required fields must be filled, and quantity must be positive.";
    } else {
        // Fetch medicine
        $sql = "SELECT price, stock FROM medicines WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $medicine_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($medicine = $result->fetch_assoc()) {
            if ($medicine['stock'] < $quantity) {
                $error = "Insufficient stock for this medicine.";
            } else {
                $total_price = $medicine['price'] * $quantity;
                // Get customer name if customer_id
                if ($customer_id) {
                    $sql = "SELECT name FROM customers WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $customer_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $customer_name = $result->fetch_assoc()['name'] ?? $customer_name;
                }
                // Insert sale (order with status Completed)
                $sql = "INSERT INTO orders (medicine_id, customer_id, customer_name, quantity, total_price, status) 
                        VALUES (?, ?, ?, ?, ?, 'Completed')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issid", $medicine_id, $customer_id, $customer_name, $quantity, $total_price);
                if ($stmt->execute()) {
                    // Update stock
                    $sql = "UPDATE medicines SET stock = stock - ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $quantity, $medicine_id);
                    $stmt->execute();
                    $success = "Sale recorded successfully!";
                } else {
                    $error = "Error recording sale: " . $conn->error;
                }
            }
        } else {
            $error = "Invalid medicine selected.";
        }
    }
}

// Initialize search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$where_clauses = ["o.status = 'Completed'"];
$params = [];
$types = "";

// Build search query
if ($search) {
    $search_param = "%" . strtolower($conn->real_escape_string($search)) . "%";
    $where_clauses[] = "(LOWER(c.name) LIKE ? OR LOWER(o.customer_name) LIKE ? OR LOWER(m.name) LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// Build date filter
if ($start_date) {
    $where_clauses[] = "o.created_at >= ?";
    $params[] = $start_date . " 00:00:00";
    $types .= "s";
}
if ($end_date) {
    $where_clauses[] = "o.created_at <= ?";
    $params[] = $end_date . " 23:59:59";
    $types .= "s";
}

// Fetch sales
$sql = "SELECT o.*, m.name AS medicine_name, c.name AS registered_customer_name, 
        SUM(o.total_price) OVER () AS total_revenue
        FROM orders o 
        JOIN medicines m ON o.medicine_id = m.id 
        LEFT JOIN customers c ON o.customer_id = c.id 
        WHERE " . implode(" AND ", $where_clauses) . " 
        ORDER BY o.created_at DESC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$sales = $result->fetch_all(MYSQLI_ASSOC);
$total_revenue = $sales[0]['total_revenue'] ?? 0;

// Fetch pending orders for Pay button
$sql = "SELECT o.id, m.name AS medicine_name, o.customer_name, c.name AS registered_customer_name, o.quantity, o.total_price, o.created_at 
        FROM orders o 
        JOIN medicines m ON o.medicine_id = m.id 
        LEFT JOIN customers c ON o.customer_id = c.id 
        WHERE o.status = 'Pending' 
        ORDER BY o.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$pending_orders = $stmt->get_result();

// Fetch medicines and customers for form
$sql = "SELECT id, name FROM medicines ORDER BY name";
$medicines = $conn->query($sql);
$sql = "SELECT id, name FROM customers ORDER BY name";
$customers = $conn->query($sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales - Hewa Pharmacy System</title>
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
            <h2 class="my-4 text-primary">Sales Management</h2>

            <!-- Alerts -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Create Sale Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Record New Sale</h5>
                </div>
                <div class="card-body">
                    <form action="sales.php" method="POST" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="medicine_id" class="form-label">Medicine</label>
                            <select class="form-select" id="medicine_id" name="medicine_id" required>
                                <option value="">Select Medicine</option>
                                <?php while ($medicine = $medicines->fetch_assoc()): ?>
                                    <option value="<?php echo $medicine['id']; ?>"><?php echo htmlspecialchars($medicine['name']); ?></option>
                                <?php endwhile; ?>
                                <?php $medicines->data_seek(0); ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="customer_id" class="form-label">Registered Customer (Optional)</label>
                            <select class="form-select" id="customer_id" name="customer_id" onchange="updateCustomerName()">
                                <option value="">None</option>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                                <?php endwhile; ?>
                                <?php $customers->data_seek(0); ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="customer_name" class="form-label">Customer Name</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="Enter customer name">
                        </div>
                        <div class="col-md-2">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary w-100">Add Sale</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Search and Filter Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Filter Sales</h5>
                </div>
                <div class="card-body">
                    <form action="sales.php" method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search (Customer or Medicine)</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter name...">
                        </div>
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                            <a href="sales.php" class="btn btn-secondary flex-grow-1">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Pending Orders (for Pay button) -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Pending Orders</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Medicine</th>
                                    <th>Customer</th>
                                    <th>Quantity</th>
                                    <th>Total Price (KSH)</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pending_orders->num_rows > 0): ?>
                                    <?php while ($order = $pending_orders->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $order['id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['medicine_name']); ?></td>
                                            <td><?php echo htmlspecialchars($order['registered_customer_name'] ?: $order['customer_name']); ?></td>
                                            <td><?php echo $order['quantity']; ?></td>
                                            <td><?php echo number_format($order['total_price'], 2); ?></td>
                                            <td><?php echo $order['created_at']; ?></td>
                                            <td>
                                                <a href="sales.php?pay=<?php echo $order['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Mark this order as paid?');">
                                                    <i class="fas fa-money-check-alt"></i> Pay
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center">No pending orders.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sales Records -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Sales Records (Total Revenue: KSH <?php echo number_format($total_revenue, 2); ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-end mb-3">
                        <button onclick="printTables()" class="btn btn-outline-primary"><i class="fas fa-print"></i> Print Records</button>
                    </div>
                    <div id="printableArea" class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Medicine</th>
                                    <th>Customer</th>
                                    <th>Quantity</th>
                                    <th>Total Price (KSH)</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($sales) > 0): ?>
                                    <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td><?php echo $sale['id']; ?></td>
                                            <td><?php echo htmlspecialchars($sale['medicine_name']); ?></td>
                                            <td><?php echo htmlspecialchars($sale['registered_customer_name'] ?: $sale['customer_name']); ?></td>
                                            <td><?php echo $sale['quantity']; ?></td>
                                            <td><?php echo number_format($sale['total_price'], 2); ?></td>
                                            <td><?php echo $sale['created_at']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center">No sales found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
    <script>
        function printTables() {
            const printContents = document.getElementById("printableArea").innerHTML;
            const originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        }

        function updateCustomerName() {
            const customerSelect = document.getElementById('customer_id');
            const customerNameInput = document.getElementById('customer_name');
            if (customerSelect.value) {
                customerNameInput.value = customerSelect.options[customerSelect.selectedIndex].text;
                customerNameInput.readOnly = true;
            } else {
                customerNameInput.value = '';
                customerNameInput.readOnly = false;
            }
        }
    </script>
</body>
</html>