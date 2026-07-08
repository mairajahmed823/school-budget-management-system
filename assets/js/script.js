document.addEventListener('DOMContentLoaded', function() {
    
    // ========== SIDEBAR TOGGLE ==========
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            } else {
                sidebar.classList.toggle('active');
            }
        });
        
        // Load saved state
        if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 768) {
            sidebar.classList.add('collapsed');
        }
    }
    
    // Close sidebar on mobile when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
    
    // ========== DATE AND TIME ==========
    function updateDateTime() {
        const now = new Date();
        
        // Date
        const dateOptions = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' };
        const dateString = now.toLocaleDateString('en-US', dateOptions);
        const dateElement = document.getElementById('currentDate');
        if (dateElement) {
            dateElement.textContent = dateString;
        }
        
        // Time
        const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = timeString;
        }
    }
    
    // Update every second
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // ========== AUTO-HIDE ALERTS ==========
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // ========== FORM VALIDATION ==========
    const loginForm = document.querySelector('.login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
            }
        });
    }
    
    // ========== ACTIVE MENU HIGHLIGHT ==========
    const currentPath = window.location.pathname;
    const menuLinks = document.querySelectorAll('.sidebar-menu a');
    
    menuLinks.forEach(link => {
        if (link.getAttribute('href') === currentPath.split('/').pop()) {
            link.parentElement.classList.add('active');
        }
    });
    
    // ========== SMOOTH SCROLLING ==========
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
});

// ========== WINDOW RESIZE HANDLER ==========
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('active');
    }
});