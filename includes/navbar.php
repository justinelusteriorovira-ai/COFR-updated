<?php
// navbar.php
$current_page = basename($_SERVER['PHP_SELF']);
$dir = dirname($_SERVER['PHP_SELF']);
$is_subfolder = (strpos($dir, 'reservations') !== false || strpos($dir, 'facilities') !== false || strpos($dir, 'calendar') !== false || strpos($dir, 'auth') !== false);
$base_path = $is_subfolder ? "../" : "";
?>

<nav class="top-navbar">
    <div class="nav-brand">
        <img src="https://enrollment.cefi.website/images/cefi-logo.png" alt="cefi-logo">
        <span class="brand-text">CEFI ADMIN</span>
    </div>
    
    <button class="mobile-menu-toggle" id="mobile-menu-btn" aria-label="Toggle Menu">
        ☰
    </button>
    
    <div class="nav-links" id="nav-links">
        <a href="<?= $base_path ?>dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
        
        <div class="dropdown">
            <button class="dropbtn <?= (strpos($dir, 'facilities') !== false) ? 'active' : '' ?>">Facilities <span>▼</span></button>
            <div class="dropdown-content">
                <a href="<?= $base_path ?>facilities/index.php">View All</a>
                <a href="<?= $base_path ?>facilities/create.php">Add New</a>
            </div>
        </div>

        <div class="dropdown">
            <button class="dropbtn <?= (strpos($dir, 'reservations') !== false) ? 'active' : '' ?>">Reservations <span>▼</span></button>
            <div class="dropdown-content">
                <a href="<?= $base_path ?>reservations/index.php">Manage Approvals</a>
                <a href="<?= $base_path ?>reservations/create.php">Create Reservation</a>
                <a href="<?= $base_path ?>reservations/history.php">History</a>
            </div>
        </div>
        
        <div class="dropdown">
            <button class="dropbtn <?= (strpos($dir, 'calendar') !== false) ? 'active' : '' ?>">Calendar <span>▼</span></button>
            <div class="dropdown-content">
                <a href="<?= $base_path ?>calendar/index.php">View Calendar</a>
                <a href="<?= $base_path ?>calendar/occasions.php">Manage Occasions</a>
            </div>
        </div>
        
        <a href="<?= $base_path ?>audit_trail.php" class="<?= ($current_page == 'audit_trail.php') ? 'active' : '' ?>">Audit Trail</a>
    </div>

    <div class="nav-user">
        <a href="<?= $base_path ?>auth/logout.php" class="logout-btn">
            Logout
        </a>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileBtn = document.getElementById('mobile-menu-btn');
    const navLinks = document.getElementById('nav-links');
    
    mobileBtn.addEventListener('click', function() {
        navLinks.classList.toggle('show');
    });

    // Handle dropdown toggles on mobile
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(drop => {
        drop.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                // If it's already active, let it toggle off. If not, close others and open this one.
                const wasActive = this.classList.contains('active-mobile');
                
                dropdowns.forEach(d => d.classList.remove('active-mobile'));
                
                if (!wasActive) {
                    this.classList.add('active-mobile');
                }
            }
        });
    });
});
</script>
