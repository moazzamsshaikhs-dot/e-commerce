// Dashboard JavaScript

$(document).ready(function() {
    // Sidebar toggle for mobile
    $('#sidebarToggle').click(function() {
        $('#sidebar').toggleClass('active');
        $(this).find('i').toggleClass('fa-bars fa-times');
    });
    
    // Auto-hide alerts
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
    // Update last activity time (prevents session timeout)
    setInterval(function() {
        $.ajax({
            url: '../includes/update-activity.php',
            method: 'POST',
            data: { update_activity: true }
        });
    }, 60000); // Every minute
    
    // Logout confirmation
    $('.logout-btn').click(function(e) {
        if (!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });
    
    // Prevent form resubmission on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-bs-toggle="popover"]').popover();
    
    // Smooth scroll for anchor links
    $('a[href^="#"]').on('click', function(event) {
        if (this.hash !== "") {
            event.preventDefault();
            const hash = this.hash;
            $('html, body').animate({
                scrollTop: $(hash).offset().top - 70
            }, 800);
        }
    });
});

// Session timeout warning
let idleTime = 0;
$(document).ready(function() {
    // Increment idle time every minute
    const idleInterval = setInterval(timerIncrement, 60000); // 1 minute
    
    // Reset idle time on user activity
    $(this).on('mousemove keypress scroll click', function() {
        idleTime = 0;
    });
});

function timerIncrement() {
    idleTime++;
    // const sessionTimeout = <?php echo SESSION_TIMEOUT_MINUTES; ?>;
    //  <?php echo SESSION_TIMEOUT_MINUTES; ?>;
    
    // Warn user 5 minutes before timeout
    if (idleTime >= (sessionTimeout - 5)) {
        showTimeoutWarning(sessionTimeout - idleTime);
    }
    
    // Logout when timeout reached
    if (idleTime >= sessionTimeout) {
        window.location.href = '../logout.php?timeout=true';
    }
}

function showTimeoutWarning(minutesLeft) {
    if (!$('#timeoutWarning').length) {
        const warning = `
            <div class="alert alert-warning alert-dismissible fade show fixed-top m-3" 
                 id="timeoutWarning" role="alert">
                <i class="fas fa-clock me-2"></i>
                Your session will expire in ${minutesLeft} minutes due to inactivity.
                <button type="button" class="btn btn-sm btn-outline-warning ms-2" 
                        onclick="extendSession()">
                    Stay Logged In
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('body').append(warning);
    }
}

function extendSession() {
    $.ajax({
        url: '../includes/update-activity.php',
        method: 'POST',
        data: { extend_session: true },
        success: function() {
            $('#timeoutWarning').alert('close');
            idleTime = 0;
        }
    });
}

// Dashboard statistics update (if using real-time data)
function updateDashboardStats() {
    $.ajax({
        url: '../includes/get-stats.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                // Update stats cards
                $('#totalUsers').text(data.total_users);
                $('#activeUsers').text(data.active_users);
                // Add more updates as needed
            }
        }
    });
}

// Auto-refresh dashboard every 5 minutes
setInterval(updateDashboardStats, 300000);

// Print functionality
function printReport(selector) {
    const printContent = $(selector).html();
    const originalContent = $('body').html();
    
    $('body').html(printContent);
    window.print();
    $('body').html(originalContent);
    location.reload();
}

// Export data to CSV
function exportToCSV(filename, rows) {
    let csvContent = "data:text/csv;charset=utf-8,";
    
    rows.forEach(function(rowArray) {
        let row = rowArray.join(",");
        csvContent += row + "\r\n";
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}