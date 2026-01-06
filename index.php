<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy System</title>
    <!-- Bootstrap CSS -->
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #e6f0fa; /* Light blue background */
            color: #333;
        }
        /* Navigation Bar */
        .navbar {
            background-color: #4a90e2; /* Light blue navbar */
        }
        .navbar-brand, .nav-link {
            color: #ffffff !important; /* White text for navbar */
        }
        .nav-link:hover {
            color: #d1e9ff !important; /* Lighter blue on hover */
        }
        .hero-section {
            background-image: url('img/img2.jpg');
            background-size: cover;
            background-position: center;
            height: 400px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .hero-section h1 {
            font-size: 3rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            background-color: rgba(74, 144, 226, 0.7); /* Semi-transparent light blue overlay */
            padding: 20px;
            border-radius: 10px;
        }
        .about-section, .contact-section {
            padding: 50px 0;
            background-color: #f0f8ff; /* Very light blue for sections */
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
            <a class="navbar-brand" href="#">Hewa Pharmacy System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <h1>Welcome to Our Hewa Pharmacy System</h1>
    </div>

    <!-- About Us Section -->
    <section id="about" class="about-section">
        <div class="container">
            <h2 class="text-center mb-4">About Us</h2>
            <p class="text-center">
                We are a dedicated pharmacy system committed to providing high-quality healthcare services. 
                Our mission is to ensure that our customers have access to safe and reliable medications 
                with exceptional customer service. Learn more about our values and services as we strive 
                to improve the health and well-being of our community.
            </p>
        </div>
    </section>

    <!-- Contact Form Section -->
    <section id="contact" class="contact-section">
        <div class="container">
            <h2 class="text-center mb-4">Contact Us</h2>
            <form action="submit_contact.php" method="POST" class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="col-12">
                    <label for="message" class="form-label">Message</label>
                    <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                </div>
                <div class="col-12 text-center">
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </div>
            </form>
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