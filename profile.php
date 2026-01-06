<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
include 'includes/db.php';

// Fetch user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT username, email FROM users WHERE id = $user_id";
$result = $conn->query($sql);
$user = $result->fetch_assoc();
$username = $user['username'];
$email = $user['email'];

// Handle form submission
$error = "";
$success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = $_POST['username'];
    $new_email = $_POST['email'];

    // Basic validation
    if (empty($new_username) || empty($new_email)) {
        $error = "All fields are required.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Prevent SQL injection
        $new_username = $conn->real_escape_string($new_username);
        $new_email = $conn->real_escape_string($new_email);

        // Check if email is already taken by another user
        $sql = "SELECT id FROM users WHERE email = '$new_email' AND id != $user_id";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            $error = "Email is already in use.";
        } else {
            // Update user profile
            $sql = "UPDATE users SET username = '$new_username', email = '$new_email' WHERE id = $user_id";
            if ($conn->query($sql) === TRUE) {
                $success = "Profile updated successfully!";
                // Update session data
                $_SESSION['username'] = $new_username;
                $_SESSION['email'] = $new_email;
                // Refresh user data for display
                $username = $new_username;
                $email = $new_email;
            } else {
                $error = "Error: " . $conn->error;
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Pharmacy System</title>
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

    <!-- Sidebar -->
   <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
  <div class="content">
        <div class="container">
            <h2 class="mb-4">User Profile</h2>

            <!-- Update Profile Form (Horizontal) -->
            <div class="profile-form">
                <h3>Update Profile</h3>
                <?php if ($error): ?>
                    <div class="alert"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success"><?php echo $success; ?></div>
                <?php endif; ?>
                <form action="profile.php" method="POST" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                    </div>
                    <div class="col-md-2">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password">
                    </div>
                    <div class="col-md-2">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Update Profile</button>
                    </div>
                </form>
                <p class="mt-3 text-muted">Note: Leave password fields blank to keep your current password.</p>
            </div>

            <!-- Profile Details -->
            <div class="profile-details">
                <h3>Current Profile Details</h3>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                <p><strong>Account Created:</strong> <?php echo $user['created_at'] ?? 'N/A'; ?></p>
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
</body>
</html>