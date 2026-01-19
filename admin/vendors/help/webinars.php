<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Webinars - Vendor Dashboard';
require_once '../../includes/header.php';
?>

<div class="dashboard-container">
    <!-- Include Vendor Sidebar -->
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Webinars & Workshops</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-chalkboard-teacher me-1 text-warning"></i>
                        Live training sessions and expert workshops
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="knowledge-base.php" class="btn btn-outline-primary">
                        <i class="fas fa-book me-2"></i> Knowledge Base
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Webinars -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-calendar-alt me-2 text-success"></i>
                    Upcoming Webinars
                </h5>
                <button class="btn btn-sm btn-outline-primary" onclick="syncCalendar()">
                    <i class="fas fa-calendar-plus me-1"></i> Add to Calendar
                </button>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <!-- Webinar 1 -->
                    <div class="col-lg-6">
                        <div class="card border h-100 webinar-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <span class="badge bg-success">Live Session</span>
                                        <span class="badge bg-info ms-2">Free</span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">Jan 25, 2026</small>
                                        <small class="text-muted">2:00 PM EST</small>
                                    </div>
                                </div>
                                
                                <h5 class="fw-bold mb-2">Boost Your Sales with SEO</h5>
                                <p class="text-muted mb-3">Learn how to optimize your product listings for better search visibility and increased sales.</p>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Duration</small>
                                        <div class="fw-bold">60 minutes</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Seats Available</small>
                                        <div class="fw-bold">45/100</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar-sm me-2">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 32px; height: 32px;">
                                            <i class="fas fa-user text-primary"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">Host</small>
                                        <div class="fw-bold">Sarah Johnson</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary flex-grow-1" onclick="registerWebinar(1)">
                                        <i class="fas fa-user-plus me-1"></i> Register Now
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="addReminder(1)">
                                        <i class="fas fa-bell"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Webinar 2 -->
                    <div class="col-lg-6">
                        <div class="card border h-100 webinar-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <span class="badge bg-warning">Premium</span>
                                        <span class="badge bg-danger ms-2">Filling Fast</span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">Jan 28, 2026</small>
                                        <small class="text-muted">11:00 AM EST</small>
                                    </div>
                                </div>
                                
                                <h5 class="fw-bold mb-2">Advanced Inventory Management</h5>
                                <p class="text-muted mb-3">Master inventory optimization techniques to reduce costs and increase efficiency.</p>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Duration</small>
                                        <div class="fw-bold">90 minutes</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Seats Available</small>
                                        <div class="fw-bold">18/50</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar-sm me-2">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 32px; height: 32px;">
                                            <i class="fas fa-user text-success"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">Host</small>
                                        <div class="fw-bold">Michael Chen</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary flex-grow-1" onclick="registerWebinar(2)">
                                        <i class="fas fa-user-plus me-1"></i> Register Now
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="addReminder(2)">
                                        <i class="fas fa-bell"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Webinar 3 -->
                    <div class="col-lg-6">
                        <div class="card border h-100 webinar-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <span class="badge bg-success">Live Session</span>
                                        <span class="badge bg-info ms-2">Free</span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">Feb 2, 2026</small>
                                        <small class="text-muted">3:30 PM EST</small>
                                    </div>
                                </div>
                                
                                <h5 class="fw-bold mb-2">Customer Service Excellence</h5>
                                <p class="text-muted mb-3">Learn techniques to provide exceptional customer service and build brand loyalty.</p>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Duration</small>
                                        <div class="fw-bold">75 minutes</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Seats Available</small>
                                        <div class="fw-bold">62/100</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar-sm me-2">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 32px; height: 32px;">
                                            <i class="fas fa-user text-warning"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">Host</small>
                                        <div class="fw-bold">Jessica Williams</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary flex-grow-1" onclick="registerWebinar(3)">
                                        <i class="fas fa-user-plus me-1"></i> Register Now
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="addReminder(3)">
                                        <i class="fas fa-bell"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Webinar 4 -->
                    <div class="col-lg-6">
                        <div class="card border h-100 webinar-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <span class="badge bg-secondary">Recorded</span>
                                        <span class="badge bg-info ms-2">Free</span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">Available Now</small>
                                        <small class="text-muted">Self-paced</small>
                                    </div>
                                </div>
                                
                                <h5 class="fw-bold mb-2">Getting Started with Analytics</h5>
                                <p class="text-muted mb-3">Understanding your sales data and using analytics to make informed decisions.</p>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Duration</small>
                                        <div class="fw-bold">45 minutes</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Enrolled</small>
                                        <div class="fw-bold">1,245 vendors</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar-sm me-2">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 32px; height: 32px;">
                                            <i class="fas fa-user text-info"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">Host</small>
                                        <div class="fw-bold">David Miller</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success flex-grow-1" onclick="watchRecording(4)">
                                        <i class="fas fa-play-circle me-1"></i> Watch Now
                                    </button>
                                    <button class="btn btn-outline-secondary">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Webinar Schedule -->
        <div class="row g-4">
            <!-- Calendar View -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-calendar me-2 text-primary"></i>
                            Webinar Calendar
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="calendar-container">
                            <div class="calendar-header d-flex justify-content-between align-items-center mb-4">
                                <h6 class="mb-0 fw-bold">January 2026</h6>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary">Today</button>
                                    <button class="btn btn-outline-secondary">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered calendar-table">
                                    <thead>
                                        <tr class="bg-light">
                                            <th class="text-center">Sun</th>
                                            <th class="text-center">Mon</th>
                                            <th class="text-center">Tue</th>
                                            <th class="text-center">Wed</th>
                                            <th class="text-center">Thu</th>
                                            <th class="text-center">Fri</th>
                                            <th class="text-center">Sat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-muted text-center">28</td>
                                            <td class="text-muted text-center">29</td>
                                            <td class="text-muted text-center">30</td>
                                            <td class="text-muted text-center">31</td>
                                            <td class="text-center">
                                                <div>1</div>
                                            </td>
                                            <td class="text-center">
                                                <div>2</div>
                                            </td>
                                            <td class="text-center">
                                                <div>3</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">
                                                <div>4</div>
                                            </td>
                                            <td class="text-center">
                                                <div>5</div>
                                            </td>
                                            <td class="text-center">
                                                <div>6</div>
                                            </td>
                                            <td class="text-center">
                                                <div>7</div>
                                            </td>
                                            <td class="text-center">
                                                <div>8</div>
                                            </td>
                                            <td class="text-center">
                                                <div>9</div>
                                            </td>
                                            <td class="text-center">
                                                <div>10</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">
                                                <div>11</div>
                                            </td>
                                            <td class="text-center">
                                                <div>12</div>
                                            </td>
                                            <td class="text-center">
                                                <div>13</div>
                                            </td>
                                            <td class="text-center">
                                                <div>14</div>
                                            </td>
                                            <td class="text-center">
                                                <div>15</div>
                                                <small class="badge bg-info d-block">Workshop</small>
                                            </td>
                                            <td class="text-center">
                                                <div>16</div>
                                            </td>
                                            <td class="text-center">
                                                <div>17</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">
                                                <div>18</div>
                                            </td>
                                            <td class="text-center">
                                                <div>19</div>
                                            </td>
                                            <td class="text-center">
                                                <div>20</div>
                                            </td>
                                            <td class="text-center">
                                                <div>21</div>
                                            </td>
                                            <td class="text-center">
                                                <div>22</div>
                                            </td>
                                            <td class="text-center">
                                                <div>23</div>
                                            </td>
                                            <td class="text-center">
                                                <div>24</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">
                                                <div>25</div>
                                                <small class="badge bg-success d-block">Live</small>
                                            </td>
                                            <td class="text-center">
                                                <div>26</div>
                                            </td>
                                            <td class="text-center">
                                                <div>27</div>
                                            </td>
                                            <td class="text-center">
                                                <div>28</div>
                                                <small class="badge bg-warning d-block">Premium</small>
                                            </td>
                                            <td class="text-center">
                                                <div>29</div>
                                            </td>
                                            <td class="text-center">
                                                <div>30</div>
                                            </td>
                                            <td class="text-center">
                                                <div>31</div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="calendar-legend mt-4">
                                <h6 class="fw-bold mb-2">Legend:</h6>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-success me-2"></span>
                                        <small>Free Live Webinar</small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-warning me-2"></span>
                                        <small>Premium Session</small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-info me-2"></span>
                                        <small>Workshop</small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-secondary me-2"></span>
                                        <small>Recorded Session</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- My Webinars -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-user-check me-2 text-success"></i>
                            My Webinars
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">SEO Masterclass</h6>
                                        <small class="text-muted">Jan 25 • 2:00 PM EST</small>
                                    </div>
                                    <span class="badge bg-success">Registered</span>
                                </div>
                            </div>
                            
                            <div class="list-group-item border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Inventory Management</h6>
                                        <small class="text-muted">Jan 28 • 11:00 AM EST</small>
                                    </div>
                                    <span class="badge bg-warning">Pending</span>
                                </div>
                            </div>
                            
                            <div class="list-group-item border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Analytics Workshop</h6>
                                        <small class="text-muted">Watched on Jan 15</small>
                                    </div>
                                    <span class="badge bg-secondary">Completed</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6 class="fw-bold mb-3">Join Webinar Tips</h6>
                            <ul class="small text-muted">
                                <li class="mb-2">Join 5 minutes early to test audio/video</li>
                                <li class="mb-2">Prepare questions in advance</li>
                                <li class="mb-2">Use a quiet, well-lit space</li>
                                <li>Download materials before the session</li>
                            </ul>
                        </div>
                        
                        <div class="mt-4">
                            <button class="btn btn-outline-primary w-100" onclick="suggestTopic()">
                                <i class="fas fa-lightbulb me-2"></i> Suggest a Topic
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Registration Modal -->
<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Register for Webinar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="webinarRegistrationForm">
                <div class="modal-body">
                    <input type="hidden" id="webinarId">
                    
                    <div class="mb-3">
                        <label class="form-label">Webinar Title</label>
                        <input type="text" id="webinarTitle" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Your Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email for Joining Instructions</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Will you attend live?</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="attendance" id="attendanceLive" value="live" checked>
                            <label class="form-check-label" for="attendanceLive">
                                Yes, I'll attend live
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="attendance" id="attendanceRecording" value="recording">
                            <label class="form-check-label" for="attendanceRecording">
                                No, I'll watch recording
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Topics of Interest (Optional)</label>
                        <textarea class="form-control" rows="2" placeholder="What specific topics would you like covered?"></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="receiveUpdates" checked>
                        <label class="form-check-label" for="receiveUpdates">
                            Send me webinar reminders and updates
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check me-2"></i> Confirm Registration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.webinar-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.webinar-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
}

.calendar-table td {
    height: 80px;
    vertical-align: top;
    padding: 8px;
    position: relative;
}

.calendar-table td:hover {
    background-color: #f8f9fa;
    cursor: pointer;
}

.calendar-table .badge {
    font-size: 0.6rem;
    padding: 2px 5px;
    margin-top: 2px;
}
</style>

<script>
const webinars = {
    1: {
        title: 'Boost Your Sales with SEO',
        date: 'Jan 25, 2026',
        time: '2:00 PM EST',
        host: 'Sarah Johnson'
    },
    2: {
        title: 'Advanced Inventory Management',
        date: 'Jan 28, 2026',
        time: '11:00 AM EST',
        host: 'Michael Chen'
    },
    3: {
        title: 'Customer Service Excellence',
        date: 'Feb 2, 2026',
        time: '3:30 PM EST',
        host: 'Jessica Williams'
    }
};

function registerWebinar(webinarId) {
    const webinar = webinars[webinarId];
    if (!webinar) return;
    
    document.getElementById('webinarId').value = webinarId;
    document.getElementById('webinarTitle').value = webinar.title;
    
    const modal = new bootstrap.Modal(document.getElementById('registerModal'));
    modal.show();
}

function addReminder(webinarId) {
    const webinar = webinars[webinarId];
    if (!webinar) return;
    
    if (confirm(`Add reminder for "${webinar.title}"?`)) {
        // In real system, this would be an AJAX call
        fetch('add-reminder.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                webinar_id: webinarId,
                title: webinar.title,
                date: webinar.date,
                time: webinar.time
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Reminder added successfully!');
            }
        })
        .catch(() => {
            alert('Reminder added (demo mode)');
        });
    }
}

function watchRecording(webinarId) {
    alert('Opening recorded webinar...');
    // In real system, this would open the video player
}

function syncCalendar() {
    const calendarEvents = [
        {
            title: 'SEO Masterclass Webinar',
            start: '2026-01-25T14:00:00',
            end: '2026-01-25T15:00:00',
            description: 'Boost Your Sales with SEO webinar'
        },
        {
            title: 'Inventory Management Webinar',
            start: '2026-01-28T11:00:00',
            end: '2026-01-28T12:30:00',
            description: 'Advanced Inventory Management techniques'
        }
    ];
    
    // Create .ics file for download
    let icsContent = `BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Vendor Dashboard//Webinars//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH`;
    
    calendarEvents.forEach(event => {
        icsContent += `
BEGIN:VEVENT
UID:${Date.now()}@vendordashboard
DTSTAMP:${new Date().toISOString().replace(/[-:]/g, '').split('.')[0]}Z
DTSTART:${event.start.replace(/[-:]/g, '').split('.')[0]}Z
DTEND:${event.end.replace(/[-:]/g, '').split('.')[0]}Z
SUMMARY:${event.title}
DESCRIPTION:${event.description}
LOCATION:Online Webinar
STATUS:CONFIRMED
SEQUENCE:0
END:VEVENT`;
    });
    
    icsContent += '\nEND:VCALENDAR';
    
    // Download .ics file
    const blob = new Blob([icsContent], { type: 'text/calendar' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'webinar-schedule.ics';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    alert('Calendar file downloaded. Import it to your calendar app.');
}

function suggestTopic() {
    const topic = prompt('What webinar topic would you like us to cover?');
    if (topic) {
        alert('Thank you for your suggestion! We\'ll consider "' + topic + '" for future webinars.');
    }
}

// Handle registration form submission
document.getElementById('webinarRegistrationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const webinarId = document.getElementById('webinarId').value;
    const webinarTitle = document.getElementById('webinarTitle').value;
    
    // In real system, this would be an AJAX call
    fetch('register-webinar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            webinar_id: webinarId,
            webinar_title: webinarTitle,
            user_id: <?php echo $_SESSION['user_id']; ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Registration successful! Check your email for joining instructions.');
            const modal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
            modal.hide();
        } else {
            alert('Registration failed: ' + data.message);
        }
    })
    .catch(() => {
        alert('Registration submitted (demo mode)');
        const modal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
        modal.hide();
    });
});

// Initialize calendar interactions
document.addEventListener('DOMContentLoaded', function() {
    const calendarCells = document.querySelectorAll('.calendar-table td');
    calendarCells.forEach(cell => {
        cell.addEventListener('click', function() {
            const date = this.querySelector('div')?.textContent;
            if (date) {
                alert(`Viewing events for January ${date}, 2026`);
            }
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>