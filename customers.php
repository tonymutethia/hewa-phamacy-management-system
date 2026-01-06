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

// Handle delete customer
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    // Check if customer has orders
    $sql = "SELECT COUNT(*) AS order_count FROM orders WHERE customer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()['order_count'] > 0) {
        $_SESSION['error'] = "Cannot delete customer with existing orders.";
    } else {
        $sql = "DELETE FROM customers WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Customer deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting customer: " . $conn->error;
        }
    }
    header("Location: customers.php");
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
    header("Location: customers.php");
    exit();
}

// Handle add/edit customer form
$error = "";
$success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    // Validation
    if (empty($name) || empty($phone)) {
        $error = "Name and phone are required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) {
        $error = "Phone must be a 10-digit number.";
    } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        if ($edit_id) {
            // Update customer
            $sql = "UPDATE customers SET name = ?, phone = ?, email = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $name, $phone, $email, $edit_id);
            if ($stmt->execute()) {
                $success = "Customer updated successfully!";
            } else {
                $error = "Error updating customer: " . $conn->error;
            }
        } else {
            // Add customer
            $sql = "INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $name, $phone, $email);
            if ($stmt->execute()) {
                $success = "Customer added successfully!";
            } else {
                $error = "Error adding customer: " . $conn->error;
            }
        }
    }
}

// Initialize search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clauses = [];
$params = [];
$types = "";

// Build search query
if ($search) {
    $search_param = "%" . strtolower($conn->real_escape_string($search)) . "%";
    $where_clauses[] = "(LOWER(name) LIKE ? OR LOWER(email) LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Fetch customers
$sql = "SELECT * FROM customers";
if ($where_clauses) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY name ASC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$customers = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Hewa Pharmacy System</title>
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
            <h2 class="my-4 text-primary">Customer Management</h2>

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

            <!-- Add Customer Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Add New Customer</h5>
                </div>
                <div class="card-body">
                    <form action="customers.php" method="POST" class="row g-3">
                        <div class="col-md-4">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" pattern="[0-9]{10}" placeholder="e.g., 1234567890" required>
                        </div>
                        <div class="col-md-4">
                            <label for="email" class="form-label">Email (Optional)</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="e.g., user@example.com">
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Add Customer</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Search Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Search Customers</h5>
                </div>
                <div class="card-body">
                    <form action="customers.php" method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label for="search" class="form-label">Search by Name or Email</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter name or email...">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Search</button>
                            <a href="customers.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Customer List</h5>
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
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($customers->num_rows > 0): ?>
                                    <?php while ($customer = $customers->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $customer['id']; ?></td>
                                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($customer['email'] ?: '-'); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewModal"
                                                        data-id="<?php echo $customer['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($customer['name']); ?>"
                                                        data-phone="<?php echo htmlspecialchars($customer['phone']); ?>"
                                                        data-email="<?php echo htmlspecialchars($customer['email'] ?: '-'); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal"
                                                        data-id="<?php echo $customer['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($customer['name']); ?>"
                                                        data-phone="<?php echo htmlspecialchars($customer['phone']); ?>"
                                                        data-email="<?php echo htmlspecialchars($customer['email']); ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="customers.php?delete=<?php echo $customer['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this customer?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center">No customers found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Customer Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewModalLabel">Customer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>ID:</strong> <span id="view_id"></span></p>
                    <p><strong>Name:</strong> <span id="view_name"></span></p>
                    <p><strong>Phone:</strong> <span id="view_phone"></span></p>
                    <p><strong>Email:</strong> <span id="view_email"></span></p>
                    <hr>
                    <h6>Pending Orders</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Medicine</th>
                                    <th>Quantity</th>
                                    <th>Total Price (KSH)</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="pending_orders"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editModalLabel">Edit Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="customers.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone" pattern="[0-9]{10}" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email (Optional)</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
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

        // Populate view modal
        const viewModal = document.getElementById('viewModal');
        viewModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const customerId = button.getAttribute('data-id');
            document.getElementById('view_id').textContent = customerId;
            document.getElementById('view_name').textContent = button.getAttribute('data-name');
            document.getElementById('view_phone').textContent = button.getAttribute('data-phone');
            document.getElementById('view_email').textContent = button.getAttribute('data-email');

            // Fetch pending orders via AJAX
            fetch(`get_pending_orders.php?customer_id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('pending_orders');
                    tbody.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(order => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${order.id}</td>
                                <td>${order.medicine_name}</td>
                                <td>${order.quantity}</td>
                                <td>${parseFloat(order.total_price).toFixed(2)}</td>
                                <td>${order.created_at}</td>
                                <td><a href="customers.php?pay=${order.id}" class="btn btn-sm btn-success" onclick="return confirm('Mark this order as paid?');"><i class="fas fa-money-check-alt"></i> Pay</a></td>
                            `;
                            tbody.appendChild(tr);
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No pending orders.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching orders:', error);
                    document.getElementById('pending_orders').innerHTML = '<tr><td colspan="6" class="text-center">Error loading orders.</td></tr>';
                });
        });

        // Populate edit modal
        const editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('edit_id').value = button.getAttribute('data-id');
            document.getElementById('edit_name').value = button.getAttribute('data-name');
            document.getElementById('edit_phone').value = button.getAttribute('data-phone');
            document.getElementById('edit_email').value = button.getAttribute('data-email') || '';
        });
    </script>
</body>
</html>