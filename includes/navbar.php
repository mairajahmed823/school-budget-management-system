<?php

$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-school"></i>
            <span class="logo-text">Institute Management System</span>
        </div>
    </div>

    <div class="sidebar-menu">
        <ul class="p-0">

            <!-- Manage School -->
            <li class="<?php echo ($current_page == 'manage_school.php' || $current_page == 'add_new_school.php') ? 'active' : ''; ?>">
                <a href="manage_school.php">
                    <i class="fas fa-school"></i>
                    <span>Manage Institute</span>
                </a>
            </li>


            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <!-- Manage Vendor -->
                <li class="<?php echo ($current_page == 'manage_vendors.php' || $current_page == 'add_vendor.php' || $current_page == 'edit_vendor.php') ? 'active' : ''; ?>">
                    <a href="manage_vendors.php">
                        <i class="fas fa-truck"></i>
                        <span>Manage Vendor</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Manage Items -->
            <li class="<?php echo ($current_page == 'manage_items.php' || $current_page == 'add_item.php' || $current_page == 'edit_item.php') ? 'active' : ''; ?>">
                <a href="manage_items.php">
                    <i class="fas fa-box-open"></i>
                    <span>Manage Items</span>
                </a>
            </li>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <!-- Manage Heads -->
                <li class="<?php echo ($current_page == 'manage_heads.php' || $current_page == 'add_new_head.php' || $current_page == 'edit_head.php') ? 'active' : ''; ?>">
                    <a href="manage_heads.php">
                        <i class="fas fa-layer-group"></i>
                        <span>Manage Heads</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Manage Requisition -->
            <li class="<?php echo ($current_page == 'manage_requistion.php' || $current_page == 'add_requisition.php' || $current_page == 'edit_head.php') ? 'active' : ''; ?>">
                <a href="manage_requisition.php">
                    <i class="fas fa-layer-group"></i>
                    <span>Manage Requisition</span>
                </a>
            </li>


            <li class="<?php echo ($current_page == 'change_password.php') ? 'active' : ''; ?>">
                <a href="change_password.php">
                    <i class="fas fa-key"></i>
                    <span>Change Password</span>
                </a>
            </li>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'user'): ?>
                <li class="<?php echo ($current_page == 'change_password.php') ? 'active' : ''; ?>">
                    <a href="https://api.whatsapp.com/send?phone=0923162638673&text=Hello%20I%20need%20help"
                        target="_blank"
                        rel="noopener noreferrer"
                        style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa-brands fa-whatsapp"
                            style="font-size: 27px !important; margin-right: 6px;">
                        </i>
                        <span>Contact Us on WhatsApp</span>
                    </a>
                </li>
            <?php endif; ?>

        </ul>
    </div>
</div>


<div class="top-header">
    <div class="header-left">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="breadcrumb">
            <i class="fas fa-home"></i>
            <span>
                Home /
                <?php
                $page_name = str_replace(['manage_', '.php', '_'], ['', '', ' '], $current_page);
                echo str_replace('school', 'Institute', $page_name);
                ?>
            </span>
        </div>



    </div>

    <div class="header-right">
        <div class="current-date">
            <i class="far fa-calendar-alt"></i>
            <span id="currentDate"><?php echo date('d M, Y'); ?></span>
        </div>

        <div class="current-time">
            <i class="far fa-clock"></i>
            <span id="currentTime"></span>
        </div>

        <div class="user-profile">
            <div class="user-avatar">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'Admin'); ?>&background=FFA500&color=fff&size=40" alt="User">
            </div>
            <div class="user-info">
                <?php
                // Pehle check karein ke admin hai ya school user
                if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                    $display_uname = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
                } else {
                    $display_uname = htmlspecialchars($_SESSION['email'] ?? 'User');
                }
                ?>

                <span class="user-name"><?php echo $display_uname; ?></span>

                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="dropdown-menu">
                <!-- <a href="profile.php"><i class="fas fa-user"></i> My Profile</a> -->
                <!-- <hr> -->
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<div class="main-content" id="mainContent">