<?php
http_response_code(403);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>403 Forbidden — Green Meadows Security Agency</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    :root { --brand:#c62828; --brand-strong:#b71c1c; --brand-grad:#e53935; }
    body { font-family:'Poppins',sans-serif; background:#f5f5f5; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px; }
    .card { border:none; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.08); overflow:hidden; }
    .hero {
      background:linear-gradient(135deg, var(--brand), var(--brand-grad));
      color:#fff;
      padding:0;
    }
    .hero-grid{
      display:grid;
      grid-template-columns: 1fr 1.2fr;
      gap: 0;
    }
    @media (max-width: 768px){
      .hero-grid{ grid-template-columns: 1fr; }
    }
    .hero-media{
      display:flex; align-items:flex-end; justify-content:center;
      padding:20px; background:rgba(0,0,0,.05);
    }
    .hero-media img{ max-height: 260px; width:auto; border-radius:12px; box-shadow:0 10px 24px rgba(0,0,0,.25); }
    .hero-copy{ padding:28px; display:flex; flex-direction:column; justify-content:center; }
    .tagline{ letter-spacing:.08em; font-weight:700; text-transform:uppercase; opacity:.95; }
    .code-403{ font-weight:800; font-size: clamp(56px, 9vw, 120px); line-height: .9; margin: 6px 0 10px; text-shadow: 0 6px 18px rgba(0,0,0,.25); }
    .subtitle{ font-size:1.05rem; opacity:.95; }
    .content { padding:22px 26px 28px; background:#fff; }
    .btn-brand { background:var(--brand); border-color:var(--brand); }
    .btn-brand:hover { background:var(--brand-strong); border-color:var(--brand-strong); }
    .btn-pill{ border-radius: 50rem; padding:.6rem 1rem; }
  </style>
</head>
<body>
  <div class="container px-3">
    <div class="row justify-content-center">
      <div class="col-12 col-md-10 col-lg-8">
        <div class="card">
          <div class="hero">
            <div class="hero-grid">
              <div class="hero-media">
                <img src="/HRIS/images/stop-img.jpg" alt="Stop - Access Forbidden">
              </div>
              <div class="hero-copy">
                <div class="tagline mb-2"><h2>YOU SHALL NOT PASS!</h1></div>
                <div class="code-403">403</div>
                <div class="subtitle">We're sorry but you don't have access to this page or resource</div>
              </div>
            </div>
          </div>
          <div class="content">
            <p class="mb-3">For security reasons, browsing directories is disabled. If you believe this is an error, return to your dashboard or contact the administrator.</p>
            <div class="d-flex gap-2 flex-wrap">
              <a href="/HRIS/index.php" class="btn btn-brand btn-pill text-white">
                <span class="material-icons align-middle me-1">home</span> Back to Home Page
              </a>
              <a href="/HRIS/login.php" class="btn btn-outline-secondary btn-pill">
                <span class="material-icons align-middle me-1">login</span> Login
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
