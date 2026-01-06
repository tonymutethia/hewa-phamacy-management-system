<?php
session_start(); // Start session for user authentication
include 'includes/db.php';
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Prevent SQL injection
    $email = $conn->real_escape_string($email);

    // Query to check user
    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Verify password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: dashboard.php"); // Redirect to dashboard
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No user found with that email.";
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pharmacy System</title>
    <!-- Bootstrap CSS -->
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #e6f0fa; /* Light blue background */
            color: #333;
        }
        .navbar {
            background-color: #4a90e2; /* Light blue navbar */
        }
        .navbar-brand, .nav-link {
            color: #ffffff !important; /* White text for navbar */
        }
        .nav-link:hover {
            color: #d1e9ff !important; /* Lighter blue on hover */
        }
        .login-section {
            padding: 50px 0;
            background-color: #f0f8ff; /* Very light blue for section */
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 400px;
            margin: auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #2c3e50; /* Darker shade for headings */
        }
        .btn-primary {
            background-color: #4a90e2; /* Light blue buttons */
            border-color: #4a90e2;
        }
        .btn-primary:hover {
            background-color: #357abd; /* Darker blue on hover */
            border-color: #357abd;
        }
        .form-control {
            border-color: #4a90e2; /* Light blue borders for form inputs */
        }
        .form-control:focus {
            border-color: #357abd;
            box-shadow: 0 0 5px rgba(74, 144, 226, 0.5);
        }
        .alert {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        footer {
            background-color: #2c3e50; /* Darker blue for footer */
            color: #ffffff;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.html">Pharmacy System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#about">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#contact">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="login.php">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Login Section -->
    <section class="login-section">
        <div class="container">
            <div class="login-container">
                <h2 class="text-center mb-4">Login</h2>
                <?php if ($error): ?>
                    <div class="alert"><?php echo $error; ?></div>
                <?php endif; ?>
                <form action="login.php" method="POST" class="row g-3">
                    <div class="col-12">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="col-12">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>
                <p class="text-center mt-3">Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center py-3">
        <p>© 2025 Pharmacy System. All Rights Reserved.</p>
    </footer>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="bootstrap/js/bootstrap.min.js"></script>
</body>
</html>