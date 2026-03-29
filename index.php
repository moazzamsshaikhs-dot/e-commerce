<?php
require_once 'includes/config.php';

$site_title = SITE_NAME . " - Complete E-Commerce Solution";
$site_description = "A complete buying & selling system with payment integration, OTP verification, and admin dashboard for complete control";
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
    
    <!-- Three.js for 3D Effects -->
    <script type="importmap">
        {
            "imports": {
                "three": "https://unpkg.com/three@0.128.0/build/three.module.js"
            }
        }
    </script>
    
    <style>
        /* ============================================
           CSS VARIABLES
        ============================================ */
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #4cc9f0;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --success-color: #06d6a0;
            --warning-color: #ffd166;
            --danger-color: #ef476f;
            --admin-color: #ef476f;
            --vendor-color: #ffb703;
            --user-color: #06d6a0;
            --primary: #4361ee;
            --success: #06d6a0;
            --warning: #ffb703;
            --danger: #ef476f;
            --info: #4cc9f0;
            --dark: #2b2d42;
            --light: #f8f9fa;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-admin: linear-gradient(135deg, #ef476f, #d62828);
            --gradient-vendor: linear-gradient(135deg, #ffb703, #f77f00);
            --gradient-user: linear-gradient(135deg, #06d6a0, #0ca678);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
            background: var(--light);
        }
        
        h1, h2, h3, h4, .logo-text {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
        }
        
        /* ============================================
           BACKGROUND EFFECTS
        ============================================ */
        #canvas-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .particle {
            position: absolute;
            background: radial-gradient(circle, rgba(67,97,238,0.3), transparent);
            border-radius: 50%;
            animation: particleFloat 15s infinite linear;
        }
        
        @keyframes particleFloat {
            from {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            to {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 1;
            }
        }
        
        /* ============================================
           NAVBAR
        ============================================ */
        .navbar {
            transition: all 0.3s ease;
            background: transparent;
            padding: 15px 0;
        }
        
        .navbar-scrolled {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            padding: 10px 0;
        }
        
        .navbar-brand {
            font-size: 1.5rem;
        }
        
        .nav-link {
            font-weight: 500;
            color: var(--dark);
            transition: all 0.3s ease;
            margin: 0 8px;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--primary-color);
        }
        
        /* ============================================
           HERO SECTION
        ============================================ */
        .hero-section {
            position: relative;
            overflow: hidden;
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, rgba(255,255,255,0.96) 0%, rgba(248,249,250,0.96) 100%);
            padding: 100px 0 80px;
        }
        
        .hero-image img {
            border-radius: 20px;
            box-shadow: 0 30px 50px rgba(0,0,0,0.1);
            width: 100%;
            transition: transform 0.5s ease;
        }
        
        .hero-image img:hover {
            transform: rotateY(5deg) scale(1.02);
        }
        
        /* Floating Cards */
        .floating-card {
            position: absolute;
            /* top: 0; */
            /* left: 0; */
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 12px 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10;
            animation: float 3s ease-in-out infinite;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .floating-card i {
            font-size: 1.3rem;
        }
        
        .floating-card span {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        .card-1 { top: 25%; left: 50%; animation-delay: 0s; }
        .card-2 { bottom: 20%; right: 8%; animation-delay: 1s; }
        .card-3 { top: 50%; left: 80%; animation-delay: 2s; }
        .card-4 { bottom: 15%; left: 15%; animation-delay: 1.5s; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(3deg); }
        }
        
        /* Text Gradient */
        .text-gradient {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .glow-text {
            animation: glow 2s ease-in-out infinite;
        }
        
        @keyframes glow {
            0%, 100% { text-shadow: 0 0 5px rgba(67,97,238,0.3); }
            50% { text-shadow: 0 0 20px rgba(67,97,238,0.6); }
        }
        
        /* ============================================
           ROLE CARDS
        ============================================ */
        .roles-section {
            padding: 80px 0;
            background: var(--light);
        }
        
        .role-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            height: 100%;
        }
        
        .role-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 30px 50px rgba(0,0,0,0.15);
        }
        
        .role-header {
            padding: 35px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .role-header.admin { background: var(--gradient-admin); }
        .role-header.vendor { background: var(--gradient-vendor); }
        .role-header.user { background: var(--gradient-user); }
        
        .role-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 70%);
            transform: rotate(45deg);
            transition: all 0.6s ease;
        }
        
        .role-card:hover .role-header::before {
            transform: rotate(45deg) translate(30%, 30%);
        }
        
        .role-icon {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
        }
        
        .role-card:hover .role-icon {
            transform: scale(1.1) rotate(360deg);
        }
        
        .role-icon.admin i { color: var(--admin-color); }
        .role-icon.vendor i { color: var(--vendor-color); }
        .role-icon.user i { color: var(--user-color); }
        
        .role-title {
            color: white;
            font-size: 1.8rem;
            margin-bottom: 0;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }
        
        .role-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 10px;
            position: relative;
            z-index: 1;
        }
        
        .role-badge.admin { background: rgba(239, 71, 111, 0.2); color: #fff; }
        .role-badge.vendor { background: rgba(255, 183, 3, 0.2); color: #fff; }
        .role-badge.user { background: rgba(6, 214, 160, 0.2); color: #fff; }
        
        .role-body {
            padding: 25px;
        }
        
        .role-features {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
        }
        
        .role-features li {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
        }
        
        .role-features li i {
            width: 20px;
            font-size: 0.9rem;
            color: var(--success-color);
        }
        
        /* ============================================
           BUTTONS
        ============================================ */
        .btn-3d {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-3d:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .btn-role {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            color: white;
        }
        
        .btn-role.admin { background: var(--gradient-admin); }
        .btn-role.vendor { background: var(--gradient-vendor); }
        .btn-role.user { background: var(--gradient-user); }
        
        .btn-role:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            color: white;
        }
        
        /* Ripple Effect */
        .ripple {
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .ripple:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .ripple:active:after {
            width: 300px;
            height: 300px;
        }
        
        /* ============================================
           COMPARISON TABLE
        ============================================ */
        .comparison-section {
            padding: 80px 0;
            background: white;
        }
        
        .comparison-table {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .comparison-table:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .comparison-table th {
            background: var(--gradient-primary);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
        }
        
        .comparison-table td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        
        .comparison-table .role-cell {
            font-weight: 600;
            background: #f8f9fa;
        }
        
        .comparison-table .check {
            color: var(--success-color);
            font-size: 1.2rem;
        }
        
        .comparison-table .times {
            color: var(--danger-color);
            font-size: 1.2rem;
        }
        
        /* ============================================
           STATISTICS SECTION
        ============================================ */
        .stats-section {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            padding: 80px 0;
        }
        
        .stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff, var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.9; transform: scale(1.02); }
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
            letter-spacing: 1px;
        }
        
        /* ============================================
           TESTIMONIALS
        ============================================ */
        .testimonials-section {
            padding: 80px 0;
            background: var(--light);
        }
        
        .testimonial-card {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
        }
        
        .testimonial-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 50px rgba(67,97,238,0.15);
        }
        
        .testimonial-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .testimonial-card:hover .testimonial-avatar {
            transform: rotateY(180deg);
        }
        
        .testimonial-name {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 1.2rem;
        }
        
        .testimonial-role {
            font-size: 0.85rem;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .testimonial-text {
            color: #666;
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        /* ============================================
           CTA SECTION
        ============================================ */
        .cta-section {
            padding: 80px 0;
            background: var(--gradient-primary);
            color: white;
        }
        
        /* ============================================
           FOOTER
        ============================================ */
        .footer {
            background: var(--dark);
            color: white;
            padding: 60px 0 30px;
        }
        
        .footer a {
            color: rgba(255,255,255,0.7);
            transition: all 0.3s ease;
        }
        
        .footer a:hover {
            color: white;
            text-decoration: none;
        }
        
        .social-links a {
            display: inline-block;
            width: 36px;
            height: 36px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            text-align: center;
            line-height: 36px;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: var(--primary-color);
            transform: translateY(-3px);
        }
        
        /* ============================================
           BACK TO TOP
        ============================================ */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            border: none;
            cursor: pointer;
            display: none;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        /* ============================================
           RESPONSIVE DESIGN
        ============================================ */
        @media (max-width: 992px) {
            .hero-section {
                text-align: center;
                padding: 100px 0 60px;
            }
            
            .hero-section .col-lg-6:first-child {
                margin-bottom: 40px;
            }
            
            .floating-card {
                display: none;
            }
            
            .role-card {
                margin-bottom: 30px;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
            
            .stats-section .col-md-3 {
                margin-bottom: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .navbar {
                background: white;
                padding: 10px 0;
            }
            
            .hero-section h1 {
                font-size: 2rem;
            }
            
            .comparison-table {
                font-size: 0.8rem;
            }
            
            .comparison-table th,
            .comparison-table td {
                padding: 8px;
            }
            
            .testimonial-card {
                margin-bottom: 20px;
            }
            
            .cta-section .d-flex {
                flex-direction: column;
                gap: 10px;
            }
            
            .cta-section .btn {
                width: 100%;
            }
            
            .back-to-top {
                bottom: 20px;
                right: 20px;
                width: 40px;
                height: 40px;
            }
        }
        
        @media (max-width: 576px) {
            .hero-section h1 {
                font-size: 1.8rem;
            }
            
            .role-title {
                font-size: 1.5rem;
            }
            
            .role-icon {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
        
        /* ============================================
           UTILITY CLASSES
        ============================================ */
        .bg-gradient-primary { background: var(--gradient-primary); }
        .bg-gradient-admin { background: var(--gradient-admin); }
        .bg-gradient-vendor { background: var(--gradient-vendor); }
        .bg-gradient-user { background: var(--gradient-user); }
        
        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }
        /* FAQ Section */
        .faq-item {
            background: white;
            border-radius: 12px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .faq-question {
            padding: 18px 20px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .faq-question:hover {
            background: #f8f9fa;
        }
        
        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            color: #666;
        }
        
        .faq-item.active .faq-answer {
            padding: 0 20px 20px 20px;
            max-height: 200px;
        }
        
        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }
    </style>
</head>
<body>
    <!-- 3D Canvas Container -->
    <div id="canvas-container"></div>
    
    <!-- Particle Background -->
    <div class="particles" id="particles"></div>
    
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <span class="fw-bold" style="color: var(--primary-color);">ShopEase</span><span class="fw-bold" style="color: var(--secondary-color);">Pro</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#roles">Roles</a></li>
                    <li class="nav-item"><a class="nav-link" href="#comparison">Comparison</a></li>
                    <li class="nav-item"><a class="nav-link" href="#testimonials">Testimonials</a></li>
                    <li class="nav-item ms-lg-3">
                        <a href="<?php echo SITE_URL; ?>login.php" class="btn btn-outline-primary btn-3d">Login</a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a href="<?php echo SITE_URL; ?>signup.php" class="btn btn-primary btn-3d">Sign Up Free</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right" data-aos-delay="100">
                    <h1 class="display-4 fw-bold mb-4">
                        Complete <span class="text-gradient glow-text">E-Commerce</span> Solution for <span class="text-gradient">Everyone</span>
                    </h1>
                    <p class="lead mb-4">
                        <strong>ShopEase Pro</strong> is a complete buying & selling platform with three distinct roles:
                        <span class="badge bg-danger ripple">Admin (Controller)</span> | 
                        <span class="badge bg-warning text-dark ripple">Vendor (Seller)</span> | 
                        <span class="badge bg-success ripple">User (Customer)</span>
                    </p>
                    <div class="d-flex flex-wrap gap-3 mb-5">
                        <a href="#roles" class="btn btn-primary btn-lg btn-3d px-4">
                            <i class="fas fa-users me-2"></i> Explore Roles
                        </a>
                        <a href="<?php echo SITE_URL; ?>signup.php" class="btn btn-outline-primary btn-lg btn-3d px-4">
                            <i class="fas fa-rocket me-2"></i> Join as Vendor
                        </a>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-4" data-aos="zoom-in" data-aos-delay="200">
                            <div class="text-center">
                                <h3 class="fw-bold text-primary mb-0">100%</h3>
                                <small>Secure Payments</small>
                            </div>
                        </div>
                        <div class="col-4" data-aos="zoom-in" data-aos-delay="300">
                            <div class="text-center">
                                <h3 class="fw-bold text-primary mb-0">24/7</h3>
                                <small>Support</small>
                            </div>
                        </div>
                        <div class="col-4" data-aos="zoom-in" data-aos-delay="400">
                            <div class="text-center">
                                <h3 class="fw-bold text-primary mb-0">1000+</h3>
                                <small>Active Sellers</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6" data-aos="fade-left" data-aos-delay="200">
                    <div class="hero-image">
                        <img src="assets/images/hero.jpg" alt="E-Commerce Dashboard" class="img-fluid rounded shadow-lg">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Floating Cards -->
        <div class="floating-card card-1">
            <i class="fas fa-chart-line text-primary"></i>
            <span>Real-time Analytics</span>
        </div>
        <div class="floating-card card-2">
            <i class="fas fa-credit-card text-success"></i>
            <span>Secure Payments</span>
        </div>
        <div class="floating-card card-3">
            <i class="fas fa-mobile-alt text-warning"></i>
            <span>Mobile Friendly</span>
        </div>
        <div class="floating-card card-4">
            <i class="fas fa-headset text-info"></i>
            <span>24/7 Support</span>
        </div>
    </section>

    <!-- Role Cards Section -->
    <section id="roles" class="roles-section">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3 glow-text">Three Powerful Roles</h2>
                <p class="text-muted">Every user gets a role with specific permissions and capabilities</p>
            </div>
            
            <div class="row g-4">
                <!-- Admin Role Card -->
                <div class="col-lg-4" data-aos="flip-left" data-aos-delay="100">
                    <div class="role-card">
                        <div class="role-header admin">
                            <div class="role-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <h3 class="role-title">Admin</h3>
                            <span class="role-badge admin">Controller & Manager</span>
                        </div>
                        <div class="role-body">
                            <ul class="role-features">
                                <li><i class="fas fa-check-circle"></i> Full System Control</li>
                                <li><i class="fas fa-check-circle"></i> Manage All Users & Vendors</li>
                                <li><i class="fas fa-check-circle"></i> Approve/Reject Products</li>
                                <li><i class="fas fa-check-circle"></i> Monitor All Transactions</li>
                                <li><i class="fas fa-check-circle"></i> Generate Reports & Analytics</li>
                                <li><i class="fas fa-check-circle"></i> Manage Payment Gateways</li>
                                <li><i class="fas fa-check-circle"></i> System Settings & Configurations</li>
                                <li><i class="fas fa-check-circle"></i> Handle Disputes & Complaints</li>
                            </ul>
                            <a href="<?php echo SITE_URL; ?>login.php" class="btn btn-role admin btn-3d">
                                <i class="fas fa-sign-in-alt me-2"></i> Login as Admin
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Vendor/Seller Role Card -->
                <div class="col-lg-4" data-aos="flip-up" data-aos-delay="200">
                    <div class="role-card">
                        <div class="role-header vendor">
                            <div class="role-icon">
                                <i class="fas fa-store"></i>
                            </div>
                            <h3 class="role-title">Vendor</h3>
                            <span class="role-badge vendor">Seller & Merchant</span>
                        </div>
                        <div class="role-body">
                            <ul class="role-features">
                                <li><i class="fas fa-check-circle"></i> Create & Manage Products</li>
                                <li><i class="fas fa-check-circle"></i> Track Sales & Earnings</li>
                                <li><i class="fas fa-check-circle"></i> Manage Inventory</li>
                                <li><i class="fas fa-check-circle"></i> View Customer Orders</li>
                                <li><i class="fas fa-check-circle"></i> Process Refunds</li>
                                <li><i class="fas fa-check-circle"></i> Manage Store Profile</li>
                                <li><i class="fas fa-check-circle"></i> Withdraw Earnings</li>
                                <li><i class="fas fa-check-circle"></i> View Performance Analytics</li>
                            </ul>
                            <a href="<?php echo SITE_URL; ?>signup.php" class="btn btn-role vendor btn-3d">
                                <i class="fas fa-store me-2"></i> Become a Seller
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- User/Customer Role Card -->
                <div class="col-lg-4" data-aos="flip-right" data-aos-delay="300">
                    <div class="role-card">
                        <div class="role-header user">
                            <div class="role-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <h3 class="role-title">Customer</h3>
                            <span class="role-badge user">Buyer & Shopper</span>
                        </div>
                        <div class="role-body">
                            <ul class="role-features">
                                <li><i class="fas fa-check-circle"></i> Browse Products & Categories</li>
                                <li><i class="fas fa-check-circle"></i> Add to Cart & Checkout</li>
                                <li><i class="fas fa-check-circle"></i> Secure Payment Options</li>
                                <li><i class="fas fa-check-circle"></i> Track Orders</li>
                                <li><i class="fas fa-check-circle"></i> Write Product Reviews</li>
                                <li><i class="fas fa-check-circle"></i> Save Wishlist Items</li>
                                <li><i class="fas fa-check-circle"></i> Request Returns/Refunds</li>
                                <li><i class="fas fa-check-circle"></i> Manage Profile & Addresses</li>
                            </ul>
                            <a href="<?php echo SITE_URL; ?>signup.php" class="btn btn-role user btn-3d">
                                <i class="fas fa-shopping-cart me-2"></i> Shop Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Comparison Table -->
    <section id="comparison" class="comparison-section">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">Role Comparison</h2>
                <p class="text-muted">See what each role can do on ShopEase Pro</p>
            </div>
            
            <div class="table-responsive" data-aos="zoom-in" data-aos-delay="100">
                <table class="table comparison-table">
                    <thead>
                        <tr>
                            <th style="width: 30%">Features</th>
                            <th style="width: 23%">Admin (Controller)</th>
                            <th style="width: 23%">Vendor (Seller)</th>
                            <th style="width: 24%">Customer (Buyer)</th>
                         </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="role-cell">Manage Products</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i> Full Control</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i> Own Products</td>
                            <td class="text-center"><i class="fas fa-times-circle times"></i></td>
                        </tr>
                        <tr>
                            <td class="role-cell">Add/Edit Products</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-times-circle times"></i></td>
                        </tr>
                        <tr>
                            <td class="role-cell">Approve Products</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-times-circle times"></i></td>
                            <td class="text-center"><i class="fas fa-times-circle times"></i></td>
                        </tr>
                        <tr>
                            <td class="role-cell">Manage Users/Vendors</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-times-circle times"></i></td>
                            <td class="text-center"><i class="fas fa-times-circle times"></i></td>
                        </tr>
                        <tr>
                            <td class="role-cell">Browse & Buy Products</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                        </tr>
                        <tr>
                            <td class="role-cell">Sell Products</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-times-circle times"></i></td>
                        </tr>
                        <tr>
                            <td class="role-cell">Manage Orders</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i> All Orders</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i> Own Orders</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i> My Orders</td>
                        </tr>
                        <tr>
                            <td class="role-cell">Process Payments</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i> Configure</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i> Receive Payments</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i> Make Payments</td>
                        </tr>
                        <tr>
                            <td class="role-cell">View Analytics</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i> Full System</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i> Store Analytics</td>
                            <td class="text-center"><i class="fas fa-times-circle times"></i></td>
                        </tr>
                        <tr>
                            <td class="role-cell">Withdraw Earnings</td>
                            <td class="text-center"><i class="fas fa-times-circle times"></i></td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-times-circle times"></i></td>
                        </tr>
                        <tr>
                            <td class="role-cell">Write Reviews</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                        </tr>
                        <tr>
                            <td class="role-cell">Wishlist</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                        </tr>
                        <tr>
                            <td class="role-cell">System Settings</td>
                            <td class="text-center"><i class="fas fa-check-circle check"></i></td>
                            <td class="text-center"><i class="fas fa-times-circle times"></i></td>
                            <td class="text-center"><i class="fas fa-times-circle times"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3" data-aos="zoom-in" data-aos-delay="100">
                    <div class="stat-number counter" data-count="500">500+</div>
                    <div class="stat-label">Active Sellers</div>
                </div>
                <div class="col-md-3" data-aos="zoom-in" data-aos-delay="200">
                    <div class="stat-number counter" data-count="10000">10,000+</div>
                    <div class="stat-label">Happy Customers</div>
                </div>
                <div class="col-md-3" data-aos="zoom-in" data-aos-delay="300">
                    <div class="stat-number counter" data-count="50000">50,000+</div>
                    <div class="stat-label">Products Sold</div>
                </div>
                <div class="col-md-3" data-aos="zoom-in" data-aos-delay="400">
                    <div class="stat-number">98%</div>
                    <div class="stat-label">Customer Satisfaction</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="testimonials-section">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">What Our Users Say</h2>
                <p class="text-muted">Real experiences from real people</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4" data-aos="flip-left" data-aos-delay="100">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h5 class="testimonial-name">Ahmed Raza</h5>
                        <div class="testimonial-role">Admin - System Controller</div>
                        <p class="testimonial-text">"As an admin, I have complete control over the platform. Managing vendors, approving products, and monitoring transactions is super easy."</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="flip-up" data-aos-delay="200">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar" style="background: linear-gradient(135deg, #ffb703, #f77f00);">
                            <i class="fas fa-store"></i>
                        </div>
                        <h5 class="testimonial-name">Fatima Khan</h5>
                        <div class="testimonial-role">Vendor - Fashion Seller</div>
                        <p class="testimonial-text">"Being a seller on ShopEase Pro has transformed my business. I can easily list products, manage inventory, and track sales."</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="flip-right" data-aos-delay="300">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar" style="background: linear-gradient(135deg, #06d6a0, #0ca678);">
                            <i class="fas fa-user"></i>
                        </div>
                        <h5 class="testimonial-name">Bilal Ahmed</h5>
                        <div class="testimonial-role">Customer - Regular Buyer</div>
                        <p class="testimonial-text">"Shopping on ShopEase Pro is a breeze! Easy checkout, multiple payment options, and fast delivery."</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- FAQ Section -->
    <section class="faq-section py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">Frequently Asked Questions</h2>
                <p class="text-muted">Got questions? We've got answers</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="faq-item" data-aos="fade-up">
                        <div class="faq-question">
                            <span>What is the difference between Admin, Vendor, and Customer?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <strong>Admin (Controller)</strong> manages the entire platform, approves vendors and products, and oversees all transactions.<br>
                            <strong>Vendor (Seller)</strong> lists and sells products, manages their store, and withdraws earnings.<br>
                            <strong>Customer (Buyer)</strong> browses products, makes purchases, and leaves reviews.
                        </div>
                    </div>
                    
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="100">
                        <div class="faq-question">
                            <span>How do I become a vendor/seller?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Simply click on "Become a Seller" on the signup page, fill in your business details, and submit for admin approval. Once approved, you can start listing your products immediately.
                        </div>
                    </div>
                    
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="200">
                        <div class="faq-question">
                            <span>What payment methods are accepted?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            We support multiple payment methods including: Credit/Debit Cards (Visa, Mastercard), PayPal, Bank Transfer, Easypaisa, JazzCash, and Cash on Delivery (COD).
                        </div>
                    </div>
                    
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="300">
                        <div class="faq-question">
                            <span>How do I withdraw my earnings as a vendor?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Vendors can withdraw their earnings from the vendor dashboard. You can choose your preferred withdrawal method (Bank Transfer, PayPal, Easypaisa, etc.) and request withdrawal. Payments are processed within 2-3 business days.
                        </div>
                    </div>
                    
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="400">
                        <div class="faq-question">
                            <span>Is OTP verification mandatory?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Yes, OTP verification is required for account registration and for sensitive actions like password changes. This ensures your account remains secure.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8" data-aos="fade-right">
                    <h2 class="display-5 fw-bold mb-3">Ready to Join ShopEase Pro?</h2>
                    <p class="lead mb-4">Choose your role and start your journey today!</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?php echo SITE_URL; ?>signup.php?role=admin" class="btn btn-light btn-lg btn-3d">
                            <i class="fas fa-user-shield me-2"></i> Apply as Admin
                        </a>
                        <a href="<?php echo SITE_URL; ?>signup.php" class="btn btn-outline-light btn-lg btn-3d">
                            <i class="fas fa-store me-2"></i> Become a Seller
                        </a>
                        <a href="<?php echo SITE_URL; ?>signup.php" class="btn btn-outline-light btn-lg btn-3d">
                            <i class="fas fa-shopping-cart me-2"></i> Shop as Customer
                        </a>
                    </div>
                </div>
                <div class="col-lg-4" data-aos="fade-left" data-aos-delay="100">
                    <div class="text-center text-lg-end">
                        <small class="d-block mb-2">Join 10,000+ happy users</small>
                        <div class="d-flex justify-content-center justify-content-lg-end gap-1">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <span class="ms-2">4.9/5 Rating</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h4 class="mb-3">ShopEase Pro</h4>
                    <p class="text-light mb-4">Complete PHP e-commerce solution with Admin Controller, Vendor Seller, and Customer Buyer roles.</p>
                    <div class="social-links">
                        <a href="#" class="me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-github"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4 mb-lg-0">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home" class="text-light text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="#roles" class="text-light text-decoration-none">Roles</a></li>
                        <li class="mb-2"><a href="#comparison" class="text-light text-decoration-none">Comparison</a></li>
                        <li><a href="#testimonials" class="text-light text-decoration-none">Testimonials</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <h5 class="mb-3">Roles</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Admin (Controller)</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Vendor (Seller)</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Customer (Buyer)</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <h5 class="mb-3">Contact Info</h5>
                    <ul class="list-unstyled text-light">
                        <li class="mb-3"><i class="fas fa-map-marker-alt me-2"></i> Karachi, Pakistan</li>
                        <li class="mb-3"><i class="fas fa-phone me-2"></i> +92 300 123 4567</li>
                        <li class="mb-3"><i class="fas fa-envelope me-2"></i> support@shopeasepro.com</li>
                        <li><i class="fas fa-clock me-2"></i> 24/7 Support Available</li>
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
    <button id="backToTop" class="back-to-top">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script type="module">
        import * as THREE from 'three';
        
        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
        const renderer = new THREE.WebGLRenderer({ alpha: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setClearColor(0x000000, 0);
        document.getElementById('canvas-container').appendChild(renderer.domElement);
        
        const geometry = new THREE.BoxGeometry();
        const colors = [0x4361ee, 0x3a0ca3, 0x4cc9f0, 0x06d6a0, 0xffb703, 0xef476f];
        const cubes = [];
        const positions = [
            { x: -3, y: 2, z: -5 }, { x: 3, y: -1, z: -6 }, { x: -2, y: -2, z: -7 },
            { x: 4, y: 1, z: -8 }, { x: -4, y: 0, z: -4 }, { x: 2, y: 3, z: -9 }
        ];
        
        positions.forEach((pos, i) => {
            const cube = new THREE.Mesh(geometry, new THREE.MeshBasicMaterial({ color: colors[i], transparent: true, opacity: 0.3 }));
            cube.position.set(pos.x, pos.y, pos.z);
            cube.scale.set(0.5, 0.5, 0.5);
            scene.add(cube);
            cubes.push(cube);
        });
        
        camera.position.z = 8;
        
        function animate() {
            requestAnimationFrame(animate);
            cubes.forEach(cube => {
                cube.rotation.x += 0.01;
                cube.rotation.y += 0.02;
            });
            renderer.render(scene, camera);
        }
        animate();
        
        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });
    </script>
    
    <script>
        AOS.init({ duration: 1000, once: true, offset: 100 });
        
        // Create Particle Background
        function createParticles() {
            const container = document.getElementById('particles');
            for (let i = 0; i < 50; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.width = (Math.random() * 5 + 2) + 'px';
                particle.style.height = particle.style.width;
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 15 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
                container.appendChild(particle);
            }
        }
        createParticles();
        
        // Counter Animation
        const counters = document.querySelectorAll('.counter');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counter = entry.target;
                    const target = parseInt(counter.getAttribute('data-count'));
                    let count = 0;
                    const increment = Math.ceil(target / 100);
                    const updateCount = () => {
                        if (count < target) {
                            count += increment;
                            counter.innerText = count.toLocaleString() + '+';
                            setTimeout(updateCount, 20);
                        } else {
                            counter.innerText = target.toLocaleString() + '+';
                        }
                    };
                    updateCount();
                    observer.unobserve(counter);
                }
            });
        });
        counters.forEach(counter => observer.observe(counter));
        
        // Navbar Scroll Effect
        $(window).scroll(function() {
            if ($(window).scrollTop() > 50) {
                $('.navbar').addClass('navbar-scrolled');
            } else {
                $('.navbar').removeClass('navbar-scrolled');
            }
        });
        
        // Smooth Scrolling
        $('a[href^="#"]').on('click', function(event) {
            var target = $(this.getAttribute('href'));
            if(target.length) {
                event.preventDefault();
                $('html, body').animate({ scrollTop: target.offset().top - 70 }, 1000);
            }
        });
        
        // Back to Top
        $(window).scroll(function() {
            if ($(this).scrollTop() > 300) {
                $('#backToTop').fadeIn();
            } else {
                $('#backToTop').fadeOut();
            }
        });
        
        $('#backToTop').click(function() {
            $('html, body').animate({ scrollTop: 0 }, 800);
        });
        $('.faq-question').click(function() {
            $(this).parent().toggleClass('active');
        });
    </script>
</body>
</html>