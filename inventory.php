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

    // Check if the medicine is referenced in the orders table
    $sql = "SELECT COUNT(*) as order_count FROM orders WHERE medicine_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['order_count'] > 0) {
        $_SESSION['error'] = "Cannot delete medicine because it is referenced in " . $row['order_count'] . " order(s).";
    } else {
        // Delete the medicine
        $sql = "DELETE FROM medicines WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Medicine deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting medicine: " . $conn->error;
        }
    }

    // Redirect to avoid resubmission
    header("Location: inventory.php");
    exit();
}

// Handle edit form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $name = trim($_POST['name']);
    $stock = (int)$_POST['stock'];
    $price = (float)$_POST['price'];

    // Validation
    if (empty($name) || $stock < 0 || $price < 0) {
        $_SESSION['error'] = "All fields are required, and stock/price must be non-negative.";
    } else {
        $sql = "UPDATE medicines SET name = ?, stock = ?, price = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sidi", $name, $stock, $price, $edit_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Medicine updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating medicine: " . $conn->error;
        }
    }

    header("Location: inventory.php");
    exit();
}

// Handle add form submission
$error = "";
$success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['edit_id'])) {
    $name = $_POST['name'];
    $stock = $_POST['stock'];
    $price = $_POST['price'];

    // Basic validation
    if (empty($name) || empty($stock) || empty($price)) {
        $error = "All fields are required.";
    } elseif (!is_numeric($stock) || $stock < 0) {
        $error = "Stock must be a non-negative number.";
    } elseif (!is_numeric($price) || $price < 0) {
        $error = "Price must be a non-negative number.";
    } else {
        // Insert new medicine
        $sql = "INSERT INTO medicines (name, stock, price) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sid", $name, $stock, $price);
        if ($stmt->execute()) {
            $success = "Medicine added successfully!";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// Initialize variables for search and date filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$where_clauses = [];
$params = [];
$types = "";

// Build search query (case-insensitive)
if ($search) {
    $search = $conn->real_escape_string($search);
    $where_clauses[] = "LOWER(name) LIKE ?";
    $search_param = "%" . strtolower($search) . "%";
    $params[] = $search_param;
    $types .= "s";
}

// Build date filter
if ($start_date) {
    $where_clauses[] = "created_at >= ?";
    $params[] = $start_date . " 00:00:00";
    $types .= "s";
}
if ($end_date) {
    $where_clauses[] = "created_at <= ?";
    $params[] = $end_date . " 23:59:59";
    $types .= "s";
}

// Construct SQL query
$sql = "SELECT * FROM medicines";
if ($where_clauses) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$medicines = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Pharmacy System</title>
    <!-- Bootstrap CSS -->
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
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
            <h2 class="mb-4">Inventory Management</h2>

            <!-- Display session-based messages -->
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
            <div class="inventory-filter mb-4">
                <form action="inventory.php" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Medicine</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter medicine name...">
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
                        <a href="inventory.php" class="btn btn-secondary flex-grow-1">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Add Medicine Form -->
            <div class="inventory-form">
                <h3>Add New Medicine</h3>
                <form action="inventory.php" method="POST" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="name" class="form-label">Medicine Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="col-md-3">
                        <label for="stock" class="form-label">Stock</label>
                        <input type="number" class="form-control" id="stock" name="stock" min="0" required>
                    </div>
                    <div class="col-md-3">
                        <label for="price" class="form-label">Price (ksh)</label>
                        <input type="number" step="0.01" class="form-control" id="price" name="price" min="0" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </form>
            </div>

            <!-- Inventory Table -->
            <div class="inventory-table">
                <div class="logout-btn print-btn">
                    <button onclick="printTables()">🖨️ Print Records</button>
                </div>
                <div id="printableArea">
                    <h3>Current Inventory</h3>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Medicine Name</th>
                                <th>Stock</th>
                                <th>Price (ksh)</th>
                                <th>Added On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($medicines->num_rows > 0): ?>
                                <?php while ($row = $medicines->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo $row['stock']; ?></td>
                                        <td><?php echo number_format($row['price'], 2); ?></td>
                                        <td><?php echo $row['created_at']; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                    data-id="<?php echo $row['id']; ?>" 
                                                    data-name="<?php echo htmlspecialchars($row['name']); ?>" 
                                                    data-stock="<?php echo $row['stock']; ?>" 
                                                    data-price="<?php echo $row['price']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="inventory.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this medicine?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No medicines found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Medicine Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Medicine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="inventory.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Medicine Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_stock" class="form-label">Stock</label>
                            <input type="number" class="form-control" id="edit_stock" name="stock" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_price" class="form-label">Price (ksh)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_price" name="price" min="0" required>
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

        // Populate edit modal with data
        const editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const stock = button.getAttribute('data-stock');
            const price = button.getAttribute('data-price');

            const modal = this;
            modal.querySelector('#edit_id').value = id;
            modal.querySelector('#edit_name').value = name;
            modal.querySelector('#edit_stock').value = stock;
            modal.querySelector('#edit_price').value = price;
        });
    </script>
</body>
</html>