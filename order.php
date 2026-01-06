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
$sql = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$username = $user['username'];

// Handle delete request
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    // Fetch order to restore stock
    $sql = "SELECT medicine_id, quantity, status FROM orders WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($order = $result->fetch_assoc()) {
        if ($order['status'] === 'Completed') {
            $_SESSION['error'] = "Cannot delete completed orders.";
        } else {
            // Delete order
            $sql = "DELETE FROM orders WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                // Restore stock
                $sql = "UPDATE medicines SET stock = stock + ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $order['quantity'], $order['medicine_id']);
                $stmt->execute();
                $_SESSION['success'] = "Order deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting order: " . $conn->error;
            }
        }
    } else {
        $_SESSION['error'] = "Order not found.";
    }
    header("Location: order.php");
    exit();
}

// Handle edit form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $medicine_id = (int)$_POST['medicine_id'];
    $quantity = (int)$_POST['quantity'];
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $customer_name = trim($_POST['customer_name']);
    $status = $_POST['status'];

    // Validation
    if (!$medicine_id || $quantity <= 0 || (!$customer_id && empty($customer_name))) {
        $_SESSION['error'] = "All required fields must be filled, and quantity must be positive.";
    } else {
        // Fetch current order
        $sql = "SELECT medicine_id, quantity, status FROM orders WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($current_order = $result->fetch_assoc()) {
            // Fetch medicine
            $sql = "SELECT price, stock FROM medicines WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $medicine_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($medicine = $result->fetch_assoc()) {
                // Calculate stock adjustment
                $stock_change = $current_order['quantity'] - $quantity;
                if ($medicine['stock'] + $stock_change < 0) {
                    $_SESSION['error'] = "Insufficient stock for this quantity.";
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
                    // Update order
                    $sql = "UPDATE orders SET medicine_id = ?, customer_id = ?, customer_name = ?, quantity = ?, total_price = ?, status = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("issidsi", $medicine_id, $customer_id, $customer_name, $quantity, $total_price, $status, $edit_id);
                    if ($stmt->execute()) {
                        // Adjust stock
                        if ($current_order['medicine_id'] == $medicine_id) {
                            $sql = "UPDATE medicines SET stock = stock + ? WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("ii", $stock_change, $medicine_id);
                        } else {
                            // Restore old medicine stock
                            $sql = "UPDATE medicines SET stock = stock + ? WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("ii", $current_order['quantity'], $current_order['medicine_id']);
                            $stmt->execute();
                            // Deduct new medicine stock
                            $sql = "UPDATE medicines SET stock = stock - ? WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("ii", $quantity, $medicine_id);
                        }
                        $stmt->execute();
                        $_SESSION['success'] = "Order updated successfully!";
                    } else {
                        $_SESSION['error'] = "Error updating order: " . $conn->error;
                    }
                }
            } else {
                $_SESSION['error'] = "Invalid medicine selected.";
            }
        } else {
            $_SESSION['error'] = "Order not found.";
        }
    }
    header("Location: order.php");
    exit();
}

// Handle create order form submission
$error = "";
$success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['edit_id'])) {
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
                // Insert order
                $sql = "INSERT INTO orders (medicine_id, customer_id, customer_name, quantity, total_price, status) VALUES (?, ?, ?, ?, ?, 'Pending')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issid", $medicine_id, $customer_id, $customer_name, $quantity, $total_price);
                if ($stmt->execute()) {
                    // Update stock
                    $sql = "UPDATE medicines SET stock = stock - ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $quantity, $medicine_id);
                    $stmt->execute();
                    $success = "Order created successfully!";
                } else {
                    $error = "Error creating order: " . $conn->error;
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
$where_clauses = [];
$params = [];
$types = "";

// Build search query
if ($search) {
    $search_param = "%" . strtolower($conn->real_escape_string($search)) . "%";
    $where_clauses[] = "(LOWER(o.customer_name) LIKE ? OR LOWER(m.name) LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
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

// Fetch orders
$sql = "SELECT o.*, m.name AS medicine_name, c.name AS registered_customer_name 
        FROM orders o 
        JOIN medicines m ON o.medicine_id = m.id 
        LEFT JOIN customers c ON o.customer_id = c.id";
if ($where_clauses) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY o.created_at DESC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();

// Fetch medicines and customers for forms
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
    <title>Orders - Pharmacy System</title>
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="main.css">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.html">Pharmacy System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">Welcome, <?php echo htmlspecialchars($username); ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="?logout=true">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <div class="container">
            <h2 class="mb-4">Order Management</h2>

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

            <!-- Search and Filter Form -->
            <div class="order-filter mb-4">
                <form action="order.php" method="GET" class="row g-3 align-items-end">
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
                        <a href="order.php" class="btn btn-secondary flex-grow-1">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Create Order Form -->
            <div class="order-form">
                <h3>Create New Order</h3>
                <form action="order.php" method="POST" class="row g-3 align-items-end">
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
                        <button type="submit" class="btn btn-primary w-100">Create</button>
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="order-table">
                <div class="logout-btn print-btn">
                    <button onclick="printTables()">🖨️ Print Records</button>
                </div>
                <div id="printableArea">
                    <h3>Current Orders</h3>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Medicine</th>
                                <th>Customer</th>
                                <th>Quantity</th>
                                <th>Total Price (KSH)</th>
                                <th>Status</th>
                                <th>Ordered On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($orders->num_rows > 0): ?>
                                <?php while ($row = $orders->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['medicine_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['registered_customer_name'] ?: $row['customer_name']); ?></td>
                                        <td><?php echo $row['quantity']; ?></td>
                                        <td><?php echo number_format($row['total_price'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                                        <td><?php echo $row['created_at']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewModal"
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-medicine="<?php echo htmlspecialchars($row['medicine_name']); ?>"
                                                    data-customer="<?php echo htmlspecialchars($row['registered_customer_name'] ?: $row['customer_name']); ?>"
                                                    data-quantity="<?php echo $row['quantity']; ?>"
                                                    data-total="<?php echo number_format($row['total_price'], 2); ?>"
                                                    data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                                    data-date="<?php echo $row['created_at']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal"
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-medicine-id="<?php echo $row['medicine_id']; ?>"
                                                    data-customer-id="<?php echo $row['customer_id'] ?: ''; ?>"
                                                    data-customer-name="<?php echo htmlspecialchars($row['customer_name']); ?>"
                                                    data-quantity="<?php echo $row['quantity']; ?>"
                                                    data-status="<?php echo htmlspecialchars($row['status']); ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="order.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this order?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No orders found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Order Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>ID:</strong> <span id="view_id"></span></p>
                    <p><strong>Medicine:</strong> <span id="view_medicine"></span></p>
                    <p><strong>Customer:</strong> <span id="view_customer"></span></p>
                    <p><strong>Quantity:</strong> <span id="view_quantity"></span></p>
                    <p><strong>Total Price (KSH):</strong> <span id="view_total"></span></p>
                    <p><strong>Status:</strong> <span id="view_status"></span></p>
                    <p><strong>Ordered On:</strong> <span id="view_date"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Order Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="order.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_medicine_id" class="form-label">Medicine</label>
                            <select class="form-select" id="edit_medicine_id" name="medicine_id" required>
                                <option value="">Select Medicine</option>
                                <?php $medicines->data_seek(0); while ($medicine = $medicines->fetch_assoc()): ?>
                                    <option value="<?php echo $medicine['id']; ?>"><?php echo htmlspecialchars($medicine['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_customer_id" class="form-label">Registered Customer (Optional)</label>
                            <select class="form-select" id="edit_customer_id" name="customer_id">
                                <option value="">None</option>
                                <?php $customers->data_seek(0); while ($customer = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_customer_name" class="form-label">Customer Name</label>
                            <input type="text" class="form-control" id="edit_customer_name" name="customer_name">
                        </div>
                        <div class="mb-3">
                            <label for="edit_quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="edit_quantity" name="quantity" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-3">
        <p>© 2025 Pharmacy System. All Rights Reserved.</p>
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

        // Populate view modal
        const viewModal = document.getElementById('viewModal');
        viewModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('view_id').textContent = button.getAttribute('data-id');
            document.getElementById('view_medicine').textContent = button.getAttribute('data-medicine');
            document.getElementById('view_customer').textContent = button.getAttribute('data-customer');
            document.getElementById('view_quantity').textContent = button.getAttribute('data-quantity');
            document.getElementById('view_total').textContent = button.getAttribute('data-total');
            document.getElementById('view_status').textContent = button.getAttribute('data-status');
            document.getElementById('view_date').textContent = button.getAttribute('data-date');
        });

        // Populate edit modal
        const editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('edit_id').value = button.getAttribute('data-id');
            document.getElementById('edit_medicine_id').value = button.getAttribute('data-medicine-id');
            document.getElementById('edit_customer_id').value = button.getAttribute('data-customer-id') || '';
            document.getElementById('edit_customer_name').value = button.getAttribute('data-customer-name');
            document.getElementById('edit_quantity').value = button.getAttribute('data-quantity');
            document.getElementById('edit_status').value = button.getAttribute('data-status');
        });

        // Update customer name based on selection
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