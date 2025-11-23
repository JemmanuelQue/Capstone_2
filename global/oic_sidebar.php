<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo-container">
        <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
        <div class="agency-name">
            <div>GREEN MEADOWS</div>
            <div>SECURITY AGENCY</div>
        </div>
    </div>
    <ul class="nav flex-column mt-4">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                <span class="material-icons">dashboard</span>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="scheduling.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'scheduling.php' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Scheduling">
                <span class="material-icons">event_note</span>
                <span>Scheduling</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="attendance.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Attendance">
                <span class="material-icons">fact_check</span>
                <span>Attendance</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="archives.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'archives.php' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Archives">
                <span class="material-icons">archive</span>
                <span>Archives</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Logs">
                <span class="material-icons">receipt_long</span>
                <span>Logs</span>
            </a>
        </li>
        <li class="nav-item mt-5">
            <a href="../logout.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Logout">
                <span class="material-icons">logout</span>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>
