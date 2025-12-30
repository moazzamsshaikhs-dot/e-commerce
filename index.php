<?php
session_start();
$site_title = "ShopEase Pro - Complete E-Commerce Solution";
$site_description = "A complete buying & selling system with payment integration, OTP verification, and admin dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #4cc9f0;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --success-color: #06d6a0;
            --warning-color: #ffd166;
            --danger-color: #ef476f;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        } */
        
        body {
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }
        
        h1, h2, h3, h4, .logo-text {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3" id="mainNav">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand logo-text" href="index.php">
                <img src="assets/images/logo.png" alt="ShopEase Pro" height="40" class="me-2">
                <span class="fw-bold" style="color: var(--primary-color);">ShopEase</span><span class="fw-bold" style="color: var(--secondary-color);">Pro</span>
            </a>
            
            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#demo">Demo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#dashboard">Dashboard</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a href="login.php" class="btn btn-outline-primary">Login</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a href="signup.php" class="btn btn-primary">Sign Up Free</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section" data-aos="fade-up">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right" data-aos-delay="100">
                    <h1 class="display-4 fw-bold mb-4">
                        Complete <span class="text-gradient">E-Commerce</span> Solution with PHP & XAMPP
                    </h1>
                    <p class="lead mb-4">
                        Build your own buying & selling platform with <strong>Payment Integration, OTP Verification, Admin Dashboard, CRUD Operations</strong> and much more!
                    </p>
                    <div class="d-flex flex-wrap gap-3 mb-5">
                        <a href="#demo" class="btn btn-primary btn-lg px-4">
                            <i class="fas fa-play-circle me-2"></i> Watch Demo
                        </a>
                        <a href="signup.php" class="btn btn-outline-primary btn-lg px-4">
                            <i class="fas fa-rocket me-2"></i> Get Started
                        </a>
                    </div>
                    
                    <!-- Stats -->
                    <div class="row g-4">
                        <div class="col-4">
                            <div class="text-center">
                                <h3 class="fw-bold text-primary mb-0">100%</h3>
                                <small>Secure Payments</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <h3 class="fw-bold text-primary mb-0">OTP</h3>
                                <small>Verification</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <h3 class="fw-bold text-primary mb-0">CRUD</h3>
                                <small>Operations</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6" data-aos="fade-left" data-aos-delay="200">
                    <div class="hero-image position-relative">
                        <img src="assets/images/hero.jpg" alt="E-Commerce Dashboard" class="img-fluid rounded shadow-lg">
                        <div class="floating-card card-1">
                            <i class="fas fa-shopping-cart text-primary"></i>
                            <small>Buy/Sell</small>
                        </div>
                        <div class="floating-card card-2">
                            <i class="fas fa-credit-card text-success"></i>
                            <small>Payments</small>
                        </div>
                        <div class="floating-card card-3">
                            <i class="fas fa-shield-alt text-warning"></i>
                            <small>OTP Secure</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">Powerful Features</h2>
                <p class="text-muted">Everything you need for a complete e-commerce platform</p>
            </div>
            
            <div class="row g-4">
                <!-- Feature 1 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card card h-100 border-0 shadow-sm hover-shadow">
                        <div class="card-body p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-shopping-cart fa-2x text-primary"></i>
                            </div>
                            <h4 class="card-title">Buying & Selling System</h4>
                            <p class="card-text text-muted">
                                Complete product management with categories, filters, and search functionality.
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Product CRUD Operations</li>
                                <li><i class="fas fa-check text-success me-2"></i> Inventory Management</li>
                                <li><i class="fas fa-check text-success me-2"></i> Order Tracking</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 2 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card card h-100 border-0 shadow-sm hover-shadow">
                        <div class="card-body p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-credit-card fa-2x text-success"></i>
                            </div>
                            <h4 class="card-title">Payment Integration</h4>
                            <p class="card-text text-muted">
                                Multiple payment gateways with secure transaction processing.
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Stripe & PayPal</li>
                                <li><i class="fas fa-check text-success me-2"></i> Bank Transfer</li>
                                <li><i class="fas fa-check text-success me-2"></i> Cash on Delivery</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 3 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card card h-100 border-0 shadow-sm hover-shadow">
                        <div class="card-body p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-mobile-alt fa-2x text-warning"></i>
                            </div>
                            <h4 class="card-title">OTP Verification</h4>
                            <p class="card-text text-muted">
                                Secure login and registration with mobile/email OTP verification.
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Email OTP</li>
                                <li><i class="fas fa-check text-success me-2"></i> SMS OTP</li>
                                <li><i class="fas fa-check text-success me-2"></i> Two-Factor Auth</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 4 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card card h-100 border-0 shadow-sm hover-shadow">
                        <div class="card-body p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-tachometer-alt fa-2x text-info"></i>
                            </div>
                            <h4 class="card-title">Admin Dashboard</h4>
                            <p class="card-text text-muted">
                                Complete admin panel for managing users, products, orders, and payments.
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> User Management</li>
                                <li><i class="fas fa-check text-success me-2"></i> Analytics & Reports</li>
                                <li><i class="fas fa-check text-success me-2"></i> Real-time Notifications</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 5 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card card h-100 border-0 shadow-sm hover-shadow">
                        <div class="card-body p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-user-circle fa-2x text-danger"></i>
                            </div>
                            <h4 class="card-title">User Dashboard</h4>
                            <p class="card-text text-muted">
                                Personalized dashboard for users to manage their profile, orders, and wishlist.
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Profile Management</li>
                                <li><i class="fas fa-check text-success me-2"></i> Order History</li>
                                <li><i class="fas fa-check text-success me-2"></i> Wishlist & Reviews</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 6 -->
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card card h-100 border-0 shadow-sm hover-shadow">
                        <div class="card-body p-4">
                            <div class="feature-icon mb-3">
                                <i class="fas fa-database fa-2x text-secondary"></i>
                            </div>
                            <h4 class="card-title">CRUD Operations</h4>
                            <p class="card-text text-muted">
                                Full Create, Read, Update, Delete operations for all modules.
                            </p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Product Management</li>
                                <li><i class="fas fa-check text-success me-2"></i> Order Management</li>
                                <li><i class="fas fa-check text-success me-2"></i> User Management</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Demo Video Section -->
    <section id="demo" class="demo-section py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">Watch Our Demo Video</h2>
                <p class="text-muted">See how our e-commerce platform works in action</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-10" data-aos="zoom-in" data-aos-delay="100">
                    <div class="demo-video-container position-relative">
                        <!-- Video Thumbnail -->
                        <div class="video-thumbnail rounded-3 overflow-hidden shadow-lg position-relative">
                            <img src="assets/images/demo-thumbnail.jpg" alt="Demo Video" class="img-fluid w-100">
                            <!-- Play Button -->
                            <div class="video-play-btn position-absolute top-50 start-50 translate-middle">
                                <a href="#" class="play-button" data-bs-toggle="modal" data-bs-target="#videoModal">
                                    <i class="fas fa-play"></i>
                                </a>
                            </div>
                            <!-- Video Duration -->
                            <div class="video-duration badge bg-dark position-absolute bottom-0 end-0 m-3">
                                5:42
                            </div>
                        </div>
                        
                        <!-- Video Description -->
                        <div class="video-description mt-4">
                            <h4 class="mb-3">Complete Platform Walkthrough</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check-circle text-success me-2"></i> User Registration with OTP</li>
                                        <li><i class="fas fa-check-circle text-success me-2"></i> Product Buying Process</li>
                                        <li><i class="fas fa-check-circle text-success me-2"></i> Payment Integration Demo</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check-circle text-success me-2"></i> Admin Dashboard Tour</li>
                                        <li><i class="fas fa-check-circle text-success me-2"></i> CRUD Operations Demo</li>
                                        <li><i class="fas fa-check-circle text-success me-2"></i> Responsive Design Showcase</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Video Modal -->
    <div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="videoModalLabel">E-Commerce Platform Demo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <!-- Video Player -->
                    <div class="ratio ratio-16x9">
                        <video id="demoVideo" controls poster="assets/images/demo-thumbnail.jpg">
                            <source src="assets/video/demo.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Preview -->
    <section id="dashboard" class="dashboard-preview py-5 bg-dark text-white">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">Dashboard Preview</h2>
                <p class="text-light">Beautiful and functional admin & user dashboards</p>
            </div>
            
            <div class="row g-4">
                <!-- Admin Dashboard -->
                <div class="col-lg-6" data-aos="fade-right" data-aos-delay="100">
                    <div class="dashboard-card">
                        <div class="dashboard-header bg-primary text-white p-3 rounded-top">
                            <h4 class="mb-0"><i class="fas fa-user-shield me-2"></i> Admin Dashboard</h4>
                        </div>
                        <div class="dashboard-body bg-white text-dark p-4 rounded-bottom">
                            <div class="row g-3 mb-4">
                                <div class="col-3">
                                    <div class="stat-box text-center p-2 bg-light rounded">
                                        <h5 class="text-primary mb-0">150</h5>
                                        <small>Users</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="stat-box text-center p-2 bg-light rounded">
                                        <h5 class="text-success mb-0">1,234</h5>
                                        <small>Orders</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="stat-box text-center p-2 bg-light rounded">
                                        <h5 class="text-warning mb-0">$45,678</h5>
                                        <small>Revenue</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="stat-box text-center p-2 bg-light rounded">
                                        <h5 class="text-info mb-0">89</h5>
                                        <small>Products</small>
                                    </div>
                                </div>
                            </div>
                            <div class="dashboard-features">
                                <h6 class="mb-3">Features:</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-primary">User Management</span>
                                    <span class="badge bg-success">Order Management</span>
                                    <span class="badge bg-warning">Product CRUD</span>
                                    <span class="badge bg-info">Analytics</span>
                                    <span class="badge bg-danger">Payment Tracking</span>
                                    <span class="badge bg-secondary">Reports</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User Dashboard -->
                <div class="col-lg-6" data-aos="fade-left" data-aos-delay="200">
                    <div class="dashboard-card">
                        <div class="dashboard-header bg-success text-white p-3 rounded-top">
                            <h4 class="mb-0"><i class="fas fa-user me-2"></i> User Dashboard</h4>
                        </div>
                        <div class="dashboard-body bg-white text-dark p-4 rounded-bottom">
                            <div class="user-info mb-4">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3">
                                        <div class="rounded-circle bg-light border" style="width: 50px; height: 50px;"></div>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">John Doe</h6>
                                        <small class="text-muted">Premium Member</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3 mb-4">
                                <div class="col-4">
                                    <div class="stat-box text-center p-2 bg-light rounded">
                                        <h6 class="text-primary mb-0">12</h6>
                                        <small>Orders</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-box text-center p-2 bg-light rounded">
                                        <h6 class="text-success mb-0">8</h6>
                                        <small>Delivered</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-box text-center p-2 bg-light rounded">
                                        <h6 class="text-warning mb-0">3</h6>
                                        <small>Pending</small>
                                    </div>
                                </div>
                            </div>
                            <div class="dashboard-features">
                                <h6 class="mb-3">Features:</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-primary">Profile Management</span>
                                    <span class="badge bg-success">Order History</span>
                                    <span class="badge bg-warning">Wishlist</span>
                                    <span class="badge bg-info">Reviews</span>
                                    <span class="badge bg-danger">Settings</span>
                                    <span class="badge bg-secondary">Notifications</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Technology Stack -->
    <section class="tech-stack py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">Technology Stack</h2>
                <p class="text-muted">Built with modern technologies for optimal performance</p>
            </div>
            
            <div class="row g-4 justify-content-center">
                <div class="col-6 col-md-4 col-lg-2 text-center" data-aos="zoom-in" data-aos-delay="100">
                    <div class="tech-icon shadow-sm rounded-3 p-4 mb-3">
                        <i class="fab fa-php fa-3x text-primary"></i>
                    </div>
                    <h6>PHP 8.x</h6>
                </div>
                <div class="col-6 col-md-4 col-lg-2 text-center" data-aos="zoom-in" data-aos-delay="150">
                    <div class="tech-icon shadow-sm rounded-3 p-4 mb-3">
                        <i class="fas fa-database fa-3x text-success"></i>
                    </div>
                    <h6>MySQL</h6>
                </div>
                <div class="col-6 col-md-4 col-lg-2 text-center" data-aos="zoom-in" data-aos-delay="200">
                    <div class="tech-icon shadow-sm rounded-3 p-4 mb-3">
                        <i class="fab fa-js-square fa-3x text-warning"></i>
                    </div>
                    <h6>JavaScript</h6>
                </div>
                <div class="col-6 col-md-4 col-lg-2 text-center" data-aos="zoom-in" data-aos-delay="250">
                    <div class="tech-icon shadow-sm rounded-3 p-4 mb-3">
                        <i class="fab fa-bootstrap fa-3x text-purple"></i>
                    </div>
                    <h6>Bootstrap 5</h6>
                </div>
                <div class="col-6 col-md-4 col-lg-2 text-center" data-aos="zoom-in" data-aos-delay="300">
                    <div class="tech-icon shadow-sm rounded-3 p-4 mb-3">
                        <i class="fas fa-server fa-3x text-info"></i>
                    </div>
                    <h6>XAMPP</h6>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section py-5 bg-gradient-primary text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8" data-aos="fade-right">
                    <h2 class="display-5 fw-bold mb-3">Ready to Build Your E-Commerce Platform?</h2>
                    <p class="lead mb-4">Get started with our complete PHP solution today. No credit card required for free trial.</p>
                </div>
                <div class="col-lg-4" data-aos="fade-left" data-aos-delay="100">
                    <div class="text-center text-lg-end">
                        <a href="signup.php" class="btn btn-light btn-lg px-5 me-3">
                            <i class="fas fa-rocket me-2"></i> Start Free Trial
                        </a>
                        <a href="#demo" class="btn btn-outline-light btn-lg px-5">
                            <i class="fas fa-play-circle me-2"></i> Watch Demo
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <a class="navbar-brand logo-text mb-3 d-inline-block" href="#">
                        <span class="fw-bold" style="color: #fff;">ShopEase</span><span class="fw-bold" style="color: var(--accent-color);">Pro</span>
                    </a>
                    <p class="text-light mb-4">Complete PHP e-commerce solution with buying & selling system, payment integration, and admin dashboard.</p>
                    <div class="social-links">
                        <a href="#" class="text-light me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-github"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4 mb-lg-0">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home" class="text-light text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="#features" class="text-light text-decoration-none">Features</a></li>
                        <li class="mb-2"><a href="#demo" class="text-light text-decoration-none">Demo</a></li>
                        <li class="mb-2"><a href="#dashboard" class="text-light text-decoration-none">Dashboard</a></li>
                        <li><a href="#contact" class="text-light text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <h5 class="mb-3">Features</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Buying & Selling</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Payment Integration</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">OTP Verification</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Admin Dashboard</a></li>
                        <li><a href="#" class="text-light text-decoration-none">User Dashboard</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <h5 class="mb-3">Contact Info</h5>
                    <ul class="list-unstyled text-light">
                        <li class="mb-3"><i class="fas fa-map-marker-alt me-2"></i> Karachi, Pakistan</li>
                        <li class="mb-3"><i class="fas fa-phone me-2"></i> +92 300 123 4567</li>
                        <li class="mb-3"><i class="fas fa-envelope me-2"></i> info@shopeasepro.com</li>
                        <li><i class="fas fa-clock me-2"></i> Mon - Sun: 9:00 AM - 11:00 PM</li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4 bg-light">
            
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2024 ShopEase Pro. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-light text-decoration-none me-3">Privacy Policy</a>
                    <a href="#" class="text-light text-decoration-none">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button id="backToTop" class="btn btn-primary btn-lg back-to-top">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- jQuery (Optional) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });
        
        // Navbar background on scroll
        $(window).scroll(function() {
            if ($(window).scrollTop() > 50) {
                $('.navbar').addClass('navbar-scrolled');
            } else {
                $('.navbar').removeClass('navbar-scrolled');
            }
        });
        
        // Smooth scrolling
        $('a[href^="#"]').on('click', function(event) {
            var target = $(this.getAttribute('href'));
            if(target.length) {
                event.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 70
                }, 1000);
            }
        });
        
        // Back to top button
        $(window).scroll(function() {
            if ($(this).scrollTop() > 300) {
                $('#backToTop').fadeIn();
            } else {
                $('#backToTop').fadeOut();
            }
        });
        
        $('#backToTop').click(function() {
            $('html, body').animate({scrollTop: 0}, 800);
            return false;
        });
        
        // Video modal close reset video
        $('#videoModal').on('hidden.bs.modal', function () {
            var video = document.getElementById("demoVideo");
            video.pause();
            video.currentTime = 0;
        });
    </script>
</body>
</html>