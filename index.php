<?php
session_start();

// Check if user is already logged in and redirect to their dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role_id'])) {
    switch ($_SESSION['role_id']) {
        case 1: // Super Admin
            header("Location: super_admin/superadmin_dashboard.php");
            exit();
        case 2: // Admin
            header("Location: admin/admin_dashboard.php");
            exit();
        case 3: // HR
            header("Location: hr/hr_dashboard.php");
            exit();
        case 4: // Accounting
            header("Location: accounting/accounting_dashboard.php");
            exit();
        case 5: // Security Guard
            header("Location: guards/guards_dashboard.php");
            exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Meadows Security Agency Inc.</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }
        
        .bg-primary-green {
            background-color: #2a7d4f;
        }
        
        .bg-dark-green {
            background-color: #264653;
        }
        
        .bg-light-green {
            background-color: #e9f5ef;
        }
        
        .text-green {
            color: #2a7d4f;
        }

        .btn-light-green{
            background-color: #68a482;
            color: white;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-light-green:hover {
            background-color: #1e5c3a;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .btn-green {
            background-color: #2a7d4f;
            color: white;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-green:hover {
            background-color: #1e5c3a;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .hero-section {
            min-height: 85vh;
            position: relative;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
        }
        
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(42, 125, 79, 0.7);
        }
        
        .service-card {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        .service-icon {
            font-size: 2.5rem;
            color: #2a7d4f;
        }
        
        .deployment-item {
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            background-color: #e9f5ef;
            font-size: 0.9rem;
        }
        
        .testimonial-card {
            border-radius: 10px;
            overflow: hidden;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .custom-shape-divider {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            overflow: hidden;
            line-height: 0;
        }
        
        .custom-shape-divider svg {
            position: relative;
            display: block;
            width: calc(100% + 1.3px);
            height: 80px;
        }
        
        .custom-shape-divider .shape-fill {
            fill: #FFFFFF;
        }
        
        .about-img {
            border-radius: 10px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        .apply-section {
            background: linear-gradient(135deg, #2a7d4f 0%, #1e5c3a 100%);
            padding: 80px 0;
            position: relative;
        }
        
        .apply-card {
            border-radius: 15px;
            overflow: hidden;
            border: none;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .contact-info i {
            width: 30px;
            text-align: center;
            margin-right: 10px;
        }
        
        footer {
            background-color: #1e5c3a;
            color: white;
        }
        
        .footer-links a {
            color: #e9f5ef;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer-links a:hover {
            color: #2a7d4f;
        }
        
        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .social-icons a:hover {
            background-color: #2a7d4f;
            transform: translateY(-3px);
        }
        
        .navbar {
            transition: all 0.3s ease;
        }
        
        .navbar-scrolled {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background-color: white !important;
        }
        
        .logo-text {
            font-weight: 700;
        }
        
        .company-logo {
            height: 60px;
            width: auto;
            border-radius: 50%;
        }
        
        @media (max-width: 767.98px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .hero-content p {
                font-size: 1rem;
            }
            
            .company-logo {
                height: 30px;
            }
        }

        /* Add this to your existing CSS styles */
        .is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
        }

        .alert-success {
            background-color: #d1e7dd !important;
            color: #0f5132 !important;
            border-color: #badbcc !important;
        }

        .alert-danger {
            background-color: #f8d7da !important;
            color: #842029 !important;
            border-color: #f5c2c7 !important;
        }

        .alert-warning {
            background-color: #fff3cd !important;
            color: #664d03 !important;
            border-color: #ffecb5 !important;
        }

        /* Animation for alerts */
        .fade {
            opacity: 0;
            transition: opacity 0.5s;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="images/greenmeadows_logo.jpg" alt="Green Meadows Security Logo" class="company-logo me-2">
                <span class="logo-text">Security <span class="text-green">Agency</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#careers">Careers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                            <a href="login.php" class="btn btn-outline-secondary px-4 py-2 rounded-pill me-2">Login</a>
                        </li>
                        <li class="nav-item mt-2 mt-lg-0">
                            <a href="#apply" class="btn btn-green px-4 py-2 rounded-pill">Apply Now</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                            <a href="logout.php" class="btn btn-outline-danger px-4 py-2 rounded-pill me-2">Logout</a>
                        </li>
                        <li class="nav-item mt-2 mt-lg-0">
                            <span class="btn btn-info px-4 py-2 rounded-pill">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section" style="background-image: url('images/hero_section.png');">
        <div class="hero-overlay"></div>
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-lg-7 text-white hero-content">
                    <h1 class="fw-bold mb-4">Your Security Is Our <span class="text-warning">Priority</span></h1>
                    <p class="lead mb-5">Security Agency Inc. provides professional security services with highly trained personnel to protect what matters most to you.</p>
                    <div class="d-flex flex-wrap">
                        <a href="#services" class="btn btn-light-green btn-lg px-5 py-3 me-3 mb-3 mb-lg-0 rounded-pill">
                            Our Services <i class="fa-solid fa-arrow-right ms-2"></i>
                        </a>
                        <a href="#contact" class="btn btn-outline-light btn-lg px-5 py-3 rounded-pill">
                            Contact Us <i class="fa-solid fa-phone ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="custom-shape-divider">
            <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" class="shape-fill"></path>
            </svg>
        </div>
    </section>

    <!-- About Us Section -->
    <section id="about" class="py-5 my-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <img src="images/about_us.jpg" class="img-fluid about-img w-100" alt="About Green Meadows Security">
                </div>
                <div class="col-lg-6">
                    <h6 class="text-green fw-bold">ABOUT US</h6>
                    <h2 class="fw-bold mb-4">3 Decades Strong: Your Security, Our Commitment</h2>
                    <p class="mb-4">Since 1992, Security Agency Inc. has been a trusted name in the security industry. We provide comprehensive security solutions tailored to meet the specific needs of businesses, organizations, and individuals across the Philippines.</p>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-light-green p-3 rounded-circle me-3">
                            <i class="fa-solid fa-user-shield text-green"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 fw-bold">Trained Professionals</h5>
                            <p class="mb-0 small">Our security personnel undergo rigorous training</p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-light-green p-3 rounded-circle me-3">
                            <i class="fa-solid fa-certificate text-green"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 fw-bold">Licensed & Certified</h5>
                            <p class="mb-0 small">Fully compliant with industry standards</p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center">
                        <div class="bg-light-green p-3 rounded-circle me-3">
                            <i class="fa-solid fa-clock text-green"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 fw-bold">24/7 Service</h5>
                            <p class="mb-0 small">Round-the-clock security solutions</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-5 bg-light-green">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <h6 class="text-green fw-bold">OUR SERVICES</h6>
                    <h2 class="fw-bold">Comprehensive Security Solutions</h2>
                    <p class="text-muted">We offer a wide range of security services to meet your specific needs.</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card service-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="rounded-circle bg-light-green p-3 d-inline-block mb-3">
                                <i class="fa-solid fa-user-tie service-icon"></i>
                            </div>
                            <h4 class="fw-bold">Security Guards</h4>
                            <p class="text-muted">Professional security personnel trained to protect your property, assets, and people. Our guards maintain vigilance and respond to security concerns promptly.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card service-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="rounded-circle bg-light-green p-3 d-inline-block mb-3">
                                <i class="fa-solid fa-user-shield service-icon"></i>
                            </div>
                            <h4 class="fw-bold">Lady Guards</h4>
                            <p class="text-muted">Professionally trained female security personnel for gender-sensitive environments, VIP protection, and specialized security needs.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card service-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="rounded-circle bg-light-green p-3 d-inline-block mb-3">
                                <i class="fa-solid fa-id-badge service-icon"></i>
                            </div>
                            <h4 class="fw-bold">Security Officers</h4>
                            <p class="text-muted">Highly trained security professionals who provide supervision, coordination, and leadership for security operations in various facilities.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card service-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="rounded-circle bg-light-green p-3 d-inline-block mb-3">
                                <i class="fa-solid fa-video service-icon"></i>
                            </div>
                            <h4 class="fw-bold">CCTV Operators</h4>
                            <p class="text-muted">Trained personnel to monitor surveillance systems, detect security incidents, and coordinate appropriate responses to ensure safety.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card service-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="rounded-circle bg-light-green p-3 d-inline-block mb-3">
                                <i class="fa-solid fa-building-shield service-icon"></i>
                            </div>
                            <h4 class="fw-bold">Facility Security</h4>
                            <p class="text-muted">Comprehensive security solutions for office buildings, industrial complexes, shopping centers, and residential communities.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card service-card h-100">
                        <div class="card-body text-center p-4">
                            <div class="rounded-circle bg-light-green p-3 d-inline-block mb-3">
                                <i class="fa-solid fa-clipboard-check service-icon"></i>
                            </div>
                            <h4 class="fw-bold">Security Consulting</h4>
                            <p class="text-muted">Expert security assessments and tailored recommendations to enhance your security posture and address specific concerns.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Deployment Areas Section -->
    <section class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h6 class="text-green fw-bold">DEPLOYMENT AREAS</h6>
                    <h2 class="fw-bold mb-4">Where We Serve</h2>
                    <p class="mb-4">Security Agency provides services across multiple locations in the Philippines. Our strategic presence ensures prompt service delivery and effective security management.</p>
                    
                    <div class="deployment-item">
                        <i class="fa-solid fa-location-dot text-green me-2"></i> Batino, Calamba, Laguna
                    </div>
                    <div class="deployment-item">
                        <i class="fa-solid fa-location-dot text-green me-2"></i> Laguna Technopark, Biñan, Laguna
                    </div>
                    <div class="deployment-item">
                        <i class="fa-solid fa-location-dot text-green me-2"></i> Greenfield, Sta. Rosa, Laguna
                    </div>
                    <div class="deployment-item">
                        <i class="fa-solid fa-location-dot text-green me-2"></i> Mabalacat, Pampanga
                    </div>
                    <div class="deployment-item">
                        <i class="fa-solid fa-location-dot text-green me-2"></i> Carmona, Cavite
                    </div>
                    
                    <a href="#contact" class="btn btn-green mt-4 px-4 py-2 rounded-pill">
                        Request Service in Your Area <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                </div>
                
                <div class="col-lg-6">
                    <div class="rounded overflow-hidden">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3867.7202003555776!2d121.12349429999999!3d14.211154599999999!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd630712374757%3A0xcecd388bdb8e34e6!2sGreen%20Meadows%20Security%20Agency%20Inc.!5e0!3m2!1sen!2sph!4v1751576890080!5m2!1sen!2sph" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Career/Apply Section -->
    <section id="careers" class="apply-section">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto text-white">
                    <h6 class="fw-bold text-warning">JOIN OUR TEAM</h6>
                    <h2 class="fw-bold">Build Your Career With Us</h2>
                    <p>We're looking for dedicated professionals to join our security team.</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card apply-card">
                        <div class="card-body p-4 p-md-5">
                            <h3 class="fw-bold mb-4 text-center">Open Positions</h3>
                            
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <div class="bg-light-green p-4 rounded-3 h-100 text-center">
                                        <i class="fa-solid fa-user-tie text-green mb-3" style="font-size: 2rem;"></i>
                                        <h5 class="fw-bold">Security Guards</h5>
                                        <p class="small mb-0 text-muted">Full-time positions available in multiple locations</p>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="bg-light-green p-4 rounded-3 h-100 text-center">
                                        <i class="fa-solid fa-user-shield text-green mb-3" style="font-size: 2rem;"></i>
                                        <h5 class="fw-bold">Lady Guards</h5>
                                        <p class="small mb-0 text-muted">Opportunities for female security professionals</p>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="bg-light-green p-4 rounded-3 h-100 text-center">
                                        <i class="fa-solid fa-video text-green mb-3" style="font-size: 2rem;"></i>
                                        <h5 class="fw-bold">CCTV Operators</h5>
                                        <p class="small mb-0 text-muted">Monitoring and surveillance positions available</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="apply" class="mt-5">
                                <h4 class="fw-bold mb-4">Apply Now</h4>
                                <form id="applicationForm" method="POST" action="process_application.php" enctype="multipart/form-data">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label for="firstName" class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="firstName" name="firstName" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="middleName" class="form-label fw-bold">Middle Name</label>
                                            <input type="text" class="form-control" id="middleName" name="middleName">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="lastName" class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="lastName" name="lastName" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="nameExtension" class="form-label fw-bold"> Extension</label>
                                            <select class="form-select" id="nameExtension" name="nameExtension">
                                                <option value="" selected>None</option>
                                                <option value="Jr.">Jr.</option>
                                                <option value="Sr.">Sr.</option>
                                                <option value="III">III</option>
                                                <option value="IV">IV</option>
                                                <option value="V">V</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control" id="phone" name="phone" placeholder="09XXXXXXXXX" pattern="09[0-9]{9}" required>
                                            <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="position" class="form-label fw-bold">Position Applying For <span class="text-danger">*</span></label>
                                            <select class="form-select" id="position" name="position" required>
                                                <option value="" selected disabled>Select a position</option>
                                                <option value="Security Guard">Security Guard</option>
                                                <option value="Lady Guard">Lady Guard</option>
                                                <option value="Security Officer">Security Officer</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="preferredLocation" class="form-label fw-bold">Preferred Location <span class="text-danger">*</span></label>
                                            <select class="form-select" id="preferredLocation" name="preferredLocation">
                                                <option value="" selected>No preference</option>
                                                <option value="Batangas">Batangas</option>
                                                <option value="Biñan">Biñan</option>
                                                <option value="Bulacan">Bulacan</option>
                                                <option value="Cavite">Cavite</option>
                                                <option value="Laguna">Laguna</option>
                                                <option value="Naga">Naga</option>
                                                <option value="Pampanga">Pampanga</option>
                                                <option value="Pangasinan">Pangasinan</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label for="resume" class="form-label fw-bold">Upload Resume (PDF or DOC) <span class="text-danger">*</span></label>
                                            <input type="file" class="form-control" id="resume" name="resume" accept=".pdf,.doc,.docx" required>
                                            <small class="form-text text-muted">Maximum file size: 5MB</small>
                                        </div>
                                        <div class="col-12">
                                            <label for="message" class="form-label">Additional Information</label>
                                            <textarea class="form-control" id="message" name="message" rows="3"></textarea>
                                        </div>
                                        <div class="col-12 text-center mt-4">
                                            <button type="submit" class="btn btn-green btn-lg px-5 py-3 rounded-pill">
                                                Submit Application <i class="fa-solid fa-paper-plane ms-2"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                <!-- Status Messages with Enhanced Styling -->
                                <?php if (isset($_GET['status'])): ?>
                                    <?php
                                    $alertClass = 'alert-info';
                                    $alertIcon = 'fa-info-circle';
                                    $alertMessage = 'Processing your request...';
                                    
                                    switch($_GET['status']) {
                                        case 'success':
                                            $alertClass = 'alert-success';
                                            $alertIcon = 'fa-circle-check';
                                            $alertMessage = 'Your application has been submitted successfully! Our HR team will review it and contact you soon.';
                                            break;
                                        case 'error':
                                            $alertClass = 'alert-danger';
                                            $alertIcon = 'fa-circle-exclamation';
                                            $alertMessage = 'There was an error submitting your application. Please try again or contact us directly.';
                                            break;
                                        case 'duplicate_email':
                                            $alertClass = 'alert-warning';
                                            $alertIcon = 'fa-exclamation-triangle';
                                            $alertMessage = 'This email address is already in our system. If you\'ve applied before, please contact HR directly.';
                                            break;
                                        case 'duplicate_phone':
                                            $alertClass = 'alert-warning';
                                            $alertIcon = 'fa-exclamation-triangle';
                                            $alertMessage = 'This phone number is already in our system. If you\'ve applied before, please contact HR directly.';
                                            break;
                                        case 'incomplete_fields':
                                            $alertClass = 'alert-danger';
                                            $alertIcon = 'fa-circle-exclamation';
                                            $alertMessage = 'Please fill in all required fields marked with an asterisk (*).';
                                            break;
                                        case 'resume_required':
                                            $alertClass = 'alert-danger';
                                            $alertIcon = 'fa-circle-exclamation';
                                            $alertMessage = 'Please upload your resume (PDF or DOC format).';
                                            break;
                                        case 'invalid_file_type':
                                            $alertClass = 'alert-danger';
                                            $alertIcon = 'fa-circle-exclamation';
                                            $alertMessage = 'Invalid file type. Please upload your resume in PDF or DOC format.';
                                            break;
                                        case 'file_too_large':
                                            $alertClass = 'alert-danger';
                                            $alertIcon = 'fa-circle-exclamation';
                                            $alertMessage = 'File size exceeds the 5MB limit. Please upload a smaller file.';
                                            break;
                                        case 'upload_error':
                                            $alertClass = 'alert-danger';
                                            $alertIcon = 'fa-circle-exclamation';
                                            $alertMessage = 'Error uploading file. Please try again.';
                                            break;
                                    }
                                    ?>
                                    <div class="alert <?php echo $alertClass; ?> mt-4" id="statusAlert" role="alert">
                                        <i class="fa-solid <?php echo $alertIcon; ?> me-2"></i>
                                        <?php echo $alertMessage; ?>
                                    </div>
                                    <script>
                                        setTimeout(function() {
                                            document.getElementById('statusAlert').classList.add('fade');
                                            setTimeout(function() {
                                                document.getElementById('statusAlert').style.display = 'none';
                                            }, 500);
                                        }, 5000);
                                    </script>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-center mt-5">
                                <p class="mb-1">For more information, contact our recruitment team:</p>
                                <p class="mb-0"><i class="fa-solid fa-envelope me-2 text-green"></i> gmsairecruitment1992@gmail.com</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5 my-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <h6 class="text-green fw-bold">GET IN TOUCH</h6>
                    <h2 class="fw-bold">Contact Us</h2>
                    <p class="text-muted">We're here to answer your questions and provide the security solutions you need.</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-5">
                    <h4 class="fw-bold mb-4">Contact Information</h4>
                    
                    <div class="contact-info">
                        <div class="d-flex mb-3">
                            <i class="fa-solid fa-location-dot text-green mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Main Office</h6>
                                <p class="mb-0 text-muted">123 Security Avenue, Calamba City, Laguna, Philippines</p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-3">
                            <i class="fa-solid fa-phone text-green mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Phone</h6>
                                <p class="mb-0 text-muted">+63 968-877-3028</p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-3">
                            <i class="fa-solid fa-envelope text-green mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Email</h6>
                                <p class="mb-0 text-muted">gmsairecruitment1992@gmail.com</p>
                            </div>
                        </div>
                        
                        <div class="d-flex">
                            <i class="fa-solid fa-clock text-green mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Business Hours</h6>
                                <p class="mb-0 text-muted">Monday - Friday: 8:00 AM - 5:00 PM</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h5 class="fw-bold mb-3">Follow Us</h5>
                        <div class="social-icons">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-7">
                    <div class="card border-0 shadow">
                        <div class="card-body p-4 p-md-5">
                            <h4 class="fw-bold mb-4">Send Us a Message</h4>
                            <form>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="contactName" class="form-label">Your Name</label>
                                        <input type="text" class="form-control" id="contactName" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="contactEmail" class="form-label">Your Email</label>
                                        <input type="email" class="form-control" id="contactEmail" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="contactPhone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="contactPhone">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="contactSubject" class="form-label">Subject</label>
                                        <input type="text" class="form-control" id="contactSubject" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="contactMessage" class="form-label">Message</label>
                                        <textarea class="form-control" id="contactMessage" rows="4" required></textarea>
                                    </div>
                                    <div class="col-12 text-center mt-4">
                                        <button type="submit" class="btn btn-green px-5 py-3 rounded-pill">
                                            Send Message <i class="fa-solid fa-paper-plane ms-2"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="pt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5 class="logo-text text-white mb-3">
                        <img src="images/greenmeadows_logo.jpg" alt="Green Meadows Security Logo" class="company-logo me-2" style="height: 30px;">
                        Security <span class="text-green">Agency</span>
                    </h5>
                    <p class="text-light">Providing professional security services since 1992. Your security is our commitment.</p>
                    <div class="social-icons mt-4">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-2 footer-links">
                    <h5 class="text-white mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home"><i class="fa-solid fa-chevron-right me-2"></i>Home</a></li>
                        <li class="mb-2"><a href="#about"><i class="fa-solid fa-chevron-right me-2"></i>About Us</a></li>
                        <li class="mb-2"><a href="#services"><i class="fa-solid fa-chevron-right me-2"></i>Services</a></li>
                        <li class="mb-2"><a href="#careers"><i class="fa-solid fa-chevron-right me-2"></i>Careers</a></li>
                        <li><a href="#contact"><i class="fa-solid fa-chevron-right me-2"></i>Contact</a></li>
                    </ul>
                </div>
                
                <div class="col-sm-6 col-lg-3 footer-links">
                    <h5 class="text-white mb-4">Our Services</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#services"><i class="fa-solid fa-chevron-right me-2"></i>Security Guards</a></li>
                        <li class="mb-2"><a href="#services"><i class="fa-solid fa-chevron-right me-2"></i>Lady Guards</a></li>
                        <li class="mb-2"><a href="#services"><i class="fa-solid fa-chevron-right me-2"></i>Security Officers</a></li>
                        <li class="mb-2"><a href="#services"><i class="fa-solid fa-chevron-right me-2"></i>CCTV Operators</a></li>
                        <li><a href="#services"><i class="fa-solid fa-chevron-right me-2"></i>Security Consulting</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3">
                    <h5 class="text-white mb-4">Newsletter</h5>
                    <p class="text-light">Subscribe to receive updates about our services and security tips.</p>
                    <form class="mt-4">
                        <div class="input-group mb-3">
                            <input type="email" class="form-control" placeholder="Your Email" required>
                            <button class="btn btn-green" type="submit"><i class="fa-solid fa-paper-plane"></i></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <hr class="mt-4 bg-light opacity-25">
            
            <div class="row py-3">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 text-light">&copy; <?php echo date('Y'); ?> Security Agency Inc. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0 text-light">Designed by <a href="#" class="text-green text-decoration-none">4th Year CCC Students</a></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('navbar-scrolled');
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    const navbarHeight = document.querySelector('.navbar').offsetHeight;
                    const targetPosition = targetElement.getBoundingClientRect().top + window.scrollY - navbarHeight;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
    <script>
    // Phone number validation
    document.getElementById('phone').addEventListener('input', function() {
        // Remove non-numeric characters
        this.value = this.value.replace(/[^0-9]/g, '');
        
        // Enforce 11 digit limit with 09 prefix
        if (this.value.length > 11) {
            this.value = this.value.slice(0, 11);
        }
        
        // Ensure it starts with 09
        if (this.value.length >= 2 && this.value.substring(0, 2) !== '09') {
            this.value = '09' + this.value.slice(2);
        }
    });

    // Form validation before submit
    document.getElementById('applicationForm').addEventListener('submit', function(event) {
        const phoneInput = document.getElementById('phone');
        if (phoneInput.value.length !== 11 || !phoneInput.value.startsWith('09')) {
            event.preventDefault();
            alert('Please enter a valid phone number in the format: 09XXXXXXXXX');
            phoneInput.focus();
        }
    });
    </script>
</body>
</html>