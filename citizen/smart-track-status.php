<?php
/**
 * SMART TRACKING SEARCH WITH LEVENSHTEIN DISTANCE ALGORITHM
 * 
 * ALGORITHM 2: LEVENSHTEIN DISTANCE (EDIT DISTANCE)
 * 
 * WHY THIS ALGORITHM WAS CHOSEN:
 * 
 * 1. USER-FRIENDLY: Citizens often make typos when entering tracking numbers
 *    - "PAS-2025-00123" might be typed as "PAS-2025-00l23" (lowercase L instead of 1)
 *    - "CIT-2025-00456" might be "CIT-2025-00465" (transposed digits)
 *    - Traditional exact matching would fail, frustrating users
 * 
 * 2. DYNAMIC PROGRAMMING EFFICIENCY: O(m × n) where m,n are string lengths
 *    - For tracking numbers (~15 chars), this is highly efficient
 *    - Much better than brute-force character-by-character comparison
 * 
 * 3. REAL-WORLD APPLICATION: Major companies use this
 *    - Google search "did you mean?"
 *    - Spell checkers
 *    - DNA sequence matching in bioinformatics
 * 
 * 4. UNCOMMON IN STUDENT PROJECTS: Most students stick to exact matching
 *    - Demonstrates knowledge of advanced string algorithms
 *    - Shows understanding of dynamic programming
 * 
 * PRACTICAL USE CASE:
 * When a citizen enters a tracking number with minor errors, instead of showing
 * "not found", the system suggests the closest matching tracking number(s).
 * 
 * Example:
 * User enters: "PAS-2025-00l23" (lowercase L instead of 1)
 * System finds:  "PAS-2025-00123" (distance = 1, suggests this)
 * 
 * The algorithm calculates the minimum number of single-character edits
 * (insertions, deletions, substitutions) needed to change one string into another.
 */

session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$citizen_id = $_SESSION['user_id'];
$citizen_name = $_SESSION['name'];

/**
 * LEVENSHTEIN DISTANCE ALGORITHM IMPLEMENTATION
 * 
 * Dynamic Programming approach to calculate edit distance between two strings
 * 
 * Time Complexity: O(m × n) where m, n are lengths of the two strings
 * Space Complexity: O(m × n) for the DP table
 * 
 * Algorithm Steps:
 * 1. Create a 2D table dp[i][j] representing distance between first i chars
 *    of string1 and first j chars of string2
 * 2. Initialize first row and column (distance from empty string)
 * 3. Fill table using recurrence relation:
 *    - If characters match: dp[i][j] = dp[i-1][j-1]
 *    - If they don't match: dp[i][j] = 1 + min(
 *        dp[i-1][j],    // deletion
 *        dp[i][j-1],    // insertion
 *        dp[i-1][j-1]   // substitution
 *      )
 * 4. Return dp[m][n] (bottom-right cell)
 */
function levenshteinDistance($str1, $str2) {
    $m = strlen($str1);
    $n = strlen($str2);
    
    // Create DP table
    $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
    
    // Initialize first column (distance from empty string)
    for ($i = 0; $i <= $m; $i++) {
        $dp[$i][0] = $i;
    }
    
    // Initialize first row (distance from empty string)
    for ($j = 0; $j <= $n; $j++) {
        $dp[0][$j] = $j;
    }
    
    // Fill the DP table
    for ($i = 1; $i <= $m; $i++) {
        for ($j = 1; $j <= $n; $j++) {
            // If characters match, no operation needed
            if ($str1[$i - 1] === $str2[$j - 1]) {
                $dp[$i][$j] = $dp[$i - 1][$j - 1];
            } else {
                // Take minimum of three operations
                $dp[$i][$j] = 1 + min(
                    $dp[$i - 1][$j],      // Deletion
                    $dp[$i][$j - 1],      // Insertion
                    $dp[$i - 1][$j - 1]   // Substitution
                );
            }
        }
    }
    
    return $dp[$m][$n];
}

/**
 * Find similar tracking numbers using Levenshtein Distance
 * Returns tracking numbers within a threshold distance
 */
function findSimilarTrackingNumbers($input, $conn, $citizen_id, $max_distance = 3) {
    // Get all tracking numbers for this citizen
    $stmt = $conn->prepare("
        SELECT tracking_number, status, submitted_date,
               (SELECT name FROM sections WHERE id = section_id) as section_name
        FROM applications 
        WHERE citizen_id = ?
    ");
    $stmt->bind_param("i", $citizen_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $suggestions = [];
    $input_upper = strtoupper($input);
    
    while ($row = $result->fetch_assoc()) {
        $tracking = strtoupper($row['tracking_number']);
        
        // Calculate edit distance
        $distance = levenshteinDistance($input_upper, $tracking);
        
        // If within threshold, add to suggestions
        if ($distance <= $max_distance) {
            $suggestions[] = [
                'tracking_number' => $row['tracking_number'],
                'distance' => $distance,
                'status' => $row['status'],
                'submitted_date' => $row['submitted_date'],
                'section_name' => $row['section_name'],
                'confidence' => max(0, 100 - ($distance * 20)) // Confidence score
            ];
        }
    }
    
    // Sort by distance (best matches first)
    usort($suggestions, function($a, $b) {
        return $a['distance'] - $b['distance'];
    });
    
    return $suggestions;
}

/**
 * Calculate similarity percentage between two strings
 */
function calculateSimilarity($str1, $str2) {
    $maxLen = max(strlen($str1), strlen($str2));
    if ($maxLen === 0) return 100;
    
    $distance = levenshteinDistance($str1, $str2);
    return round((1 - ($distance / $maxLen)) * 100, 1);
}

// Handle search
$search_input = '';
$exact_match = null;
$suggestions = [];
$search_performed = false;
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['tracking_number'])) {
    $search_input = trim($_POST['tracking_number']);
    $search_performed = true;
    
    // First, try exact match
    $stmt = $conn->prepare("
        SELECT a.*, s.name as section_name, s.office_name
        FROM applications a
        JOIN sections s ON a.section_id = s.id
        WHERE a.tracking_number = ? AND a.citizen_id = ?
    ");
    $stmt->bind_param("si", $search_input, $citizen_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Exact match found
        $exact_match = $result->fetch_assoc();
    } else {
        // No exact match, use Levenshtein algorithm to find similar
        $suggestions = findSimilarTrackingNumbers($search_input, $conn, $citizen_id, 3);
        
        if (empty($suggestions)) {
            $error_msg = "No applications found matching '{$search_input}'. Please check your tracking number.";
        }
    }
}

// Get unread messages
$unread_messages_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM messages 
    WHERE receiver_id = $citizen_id AND is_read = 0
");
$unread_messages = $unread_messages_query ? $unread_messages_query->fetch_assoc()['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Smart Track Application - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f4f7fb; min-height: 100vh; color: #333; }
        
        /* Sidebar */
        .sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 260px; background: #0d1b2a; color: #fff; padding: 2rem 1.5rem; display: flex; flex-direction: column; gap: 0.6rem; box-shadow: 2px 0 10px rgba(0,0,0,0.05); z-index: 1000; }
        .sidebar h2 { font-size: 1.25rem; color: #00b4d8; margin-bottom: 0.5rem; }
        .sidebar a { display: flex; align-items: center; gap: 0.8rem; text-decoration: none; color: #cfe8f3; padding: 0.6rem 0.75rem; border-radius: 8px; font-weight: 600; transition: background 0.2s, color 0.2s; }
        .sidebar a i { color: #00b4d8; min-width: 18px; }
        .sidebar a.active, .sidebar a:hover { background: rgba(255,255,255,0.06); color: #fff; }
        .sidebar .badge { background: #ff4d4f; color: white; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; margin-left: auto; }
        
        /* Main Content */
        .main-content { margin-left: 300px; padding: 2rem; max-width: calc(100% - 300px); }
        
        .page-header { background: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        .page-header h1 { color: #0d1b2a; font-size: 1.75rem; margin-bottom: 0.5rem; }
        .page-header p { color: #666; }
        
        .algorithm-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; margin-left: 15px; }
        
        .search-card { background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        
        .search-form { max-width: 600px; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #0d1b2a; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem; transition: all 0.3s; }
        .form-group input:focus { outline: none; border-color: #00b4d8; box-shadow: 0 0 0 3px rgba(0,180,216,0.1); }
        
        .btn-primary { background: #0d1b2a; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-primary:hover { background: #00b4d8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,180,216,0.4); }
        
        .info-box { background: #e8f4fd; border-left: 4px solid #2196F3; padding: 15px 20px; border-radius: 8px; margin: 20px 0; }
        .info-box h4 { color: #1565c0; margin-bottom: 8px; }
        .info-box p { margin: 0; color: #424242; line-height: 1.6; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        
        .result-card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .tracking-number { font-size: 1.5rem; font-weight: 700; color: #0d1b2a; font-family: 'Courier New', monospace; background: #f8f9fa; padding: 10px 15px; border-radius: 6px; display: inline-block; }
        
        .status-badge { padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; display: inline-block; }
        .status-submitted { background: #d1ecf1; color: #0c5460; }
        .status-ready_for_pickup { background: #d4edda; color: #155724; }
        .status-completed { background: #c3e6cb; color: #155724; }
        
        .suggestion-card { background: #fffef7; border: 2px solid #ffc107; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; transition: all 0.3s; }
        .suggestion-card:hover { box-shadow: 0 4px 15px rgba(255,193,7,0.3); transform: translateY(-2px); }
        
        .similarity-score { background: #4CAF50; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; margin-left: 10px; }
        
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .detail-item { }
        .detail-item .label { color: #666; font-size: 0.9rem; margin-bottom: 0.25rem; }
        .detail-item .value { color: #0d1b2a; font-weight: 600; font-size: 1.1rem; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>🏛️ SarkariSathi</h2>
        <a href="<?= BASE_URL ?>/citizen/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="<?= BASE_URL ?>/citizen/sections.php"><i class="fas fa-list"></i> Services</a>
        <a href="<?= BASE_URL ?>/citizen/queue-booking.php"><i class="fas fa-calendar-check"></i> Book Queue</a>
        <a href="<?= BASE_URL ?>/citizen/my-queue.php"><i class="fas fa-users"></i> My Queue</a>
        <a href="smart-track-status.php" class="active"><i class="fas fa-search"></i> Track Status</a>
        <a href="<?= BASE_URL ?>/citizen/complaints.php"><i class="fas fa-exclamation-circle"></i> Complaints</a>
        <a href="<?= BASE_URL ?>/citizen/messages.php">
            <i class="fas fa-envelope"></i> Messages
            <?php if ($unread_messages > 0): ?>
                <span class="badge"><?= $unread_messages ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/citizen/profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="<?= BASE_URL ?>/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1>
                <i class="fas fa-brain"></i>
                Smart Application Tracking
                <span class="algorithm-badge">Levenshtein Distance Algorithm</span>
            </h1>
            <p>Track your applications with intelligent typo correction</p>
        </div>

        <!-- Algorithm Explanation -->
        <div class="info-box">
            <h4><i class="fas fa-info-circle"></i> How Smart Search Works</h4>
            <p>
                <strong>Levenshtein Distance Algorithm</strong> calculates the minimum number of single-character edits 
                (insertions, deletions, substitutions) needed to change one string into another. This allows the system 
                to find your application even if you make small typos in the tracking number.
            </p>
            <p style="margin-top: 10px;">
                <strong>Example:</strong> If you type "PAS-2025-00l23" (lowercase L instead of 1), the algorithm finds 
                "PAS-2025-00123" because the edit distance is only 1 character.
            </p>
            <p style="margin-top: 8px;"><em>Time Complexity: O(m × n) using Dynamic Programming where m, n are string lengths</em></p>
        </div>

        <!-- Search Form -->
        <div class="search-card">
            <h3 style="margin-bottom: 1.5rem; color: #0d1b2a;">
                <i class="fas fa-search"></i>
                Enter Tracking Number
            </h3>
            <form method="POST" class="search-form">
                <div class="form-group">
                    <label for="tracking_number">
                        <i class="fas fa-barcode"></i>
                        Tracking Number
                    </label>
                    <input 
                        type="text" 
                        id="tracking_number" 
                        name="tracking_number" 
                        value="<?= htmlspecialchars($search_input) ?>"
                        placeholder="e.g., PAS-2025-00123"
                        required
                        pattern="[A-Za-z0-9\-]+"
                        title="Only letters, numbers, and hyphens allowed">
                    <small style="color: #666; display: block; margin-top: 0.5rem;">
                        Don't worry about typos - our smart search will find similar matches!
                    </small>
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i>
                    Search with Smart Matching
                </button>
            </form>
        </div>

        <!-- Results -->
        <?php if ($search_performed): ?>
            <?php if ($exact_match): ?>
                <!-- Exact Match Found -->
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>Perfect Match Found!</strong> Exact tracking number located.
                </div>
                
                <div class="result-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <div class="tracking-number"><?= htmlspecialchars($exact_match['tracking_number']) ?></div>
                        <span class="status-badge status-<?= $exact_match['status'] ?>">
                            <?= ucwords(str_replace('_', ' ', $exact_match['status'])) ?>
                        </span>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="label">Service</div>
                            <div class="value"><?= htmlspecialchars($exact_match['section_name']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Office</div>
                            <div class="value"><?= htmlspecialchars($exact_match['office_name']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Submitted</div>
                            <div class="value"><?= date('M d, Y', strtotime($exact_match['submitted_date'])) ?></div>
                        </div>
                        <?php if ($exact_match['ready_date']): ?>
                        <div class="detail-item">
                            <div class="label">Ready Date</div>
                            <div class="value"><?= date('M d, Y', strtotime($exact_match['ready_date'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php elseif (!empty($suggestions)): ?>
                <!-- Similar Matches Found -->
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>No Exact Match Found</strong> - But we found <?= count($suggestions) ?> similar tracking number(s) using Levenshtein Distance Algorithm:
                </div>
                
                <?php foreach ($suggestions as $idx => $suggestion): ?>
                    <div class="suggestion-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <div>
                                <h4 style="color: #0d1b2a; margin-bottom: 0.5rem;">
                                    Did you mean: <span class="tracking-number" style="font-size: 1.2rem;"><?= htmlspecialchars($suggestion['tracking_number']) ?></span>
                                    <span class="similarity-score">
                                        <?= $suggestion['confidence'] ?>% Match
                                    </span>
                                </h4>
                                <p style="color: #666; margin: 0;">
                                    <i class="fas fa-exchange-alt"></i>
                                    Edit distance: <?= $suggestion['distance'] ?> character(s) different from your search
                                </p>
                            </div>
                            <span class="status-badge status-<?= $suggestion['status'] ?>">
                                <?= ucwords(str_replace('_', ' ', $suggestion['status'])) ?>
                            </span>
                        </div>
                        
                        <div class="detail-grid">
                            <div class="detail-item">
                                <div class="label">Service</div>
                                <div class="value"><?= htmlspecialchars($suggestion['section_name']) ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="label">Submitted</div>
                                <div class="value"><?= date('M d, Y', strtotime($suggestion['submitted_date'])) ?></div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="tracking_number" value="<?= htmlspecialchars($suggestion['tracking_number']) ?>">
                                <button type="submit" class="btn-primary" style="padding: 8px 20px; font-size: 0.9rem;">
                                    <i class="fas fa-check"></i>
                                    Yes, Track This One
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                
            <?php elseif ($error_msg): ?>
                <div class="alert alert-error">
                    <i class="fas fa-times-circle"></i>
                    <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
