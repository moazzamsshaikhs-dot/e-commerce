<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if (!isset($_SESSION['user_id'])) {
    die('Please login first');
}

$db = getDB();
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)$_POST['rating'];
    $review_text = trim($_POST['review_text']);
    
    try {
        // Insert review
        $stmt = $db->prepare("INSERT INTO reviews (user_id, product_id, rating, review_text, is_approved, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
        $result = $stmt->execute([$_SESSION['user_id'], $product_id, $rating, $review_text]);
        
        if ($result) {
            echo "<p style='color:green'> Review inserted successfully! ID: " . $db->lastInsertId() . "</p>";
            
            // Update product stats
            $stmt = $db->prepare("UPDATE products SET average_rating = (SELECT AVG(rating) FROM reviews WHERE product_id = ?), review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ?) WHERE id = ?");
            $stmt->execute([$product_id, $product_id, $product_id]);
            
        } else {
            echo "<p style='color:red'> Failed to insert review</p>";
            print_r($stmt->errorInfo());
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    }
}

// Show existing reviews
$stmt = $db->prepare("SELECT * FROM reviews WHERE product_id = ?");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

echo "<h2>Existing Reviews for Product ID: $product_id</h2>";
if (count($reviews) > 0) {
    echo "<pre>";
    print_r($reviews);
    echo "</pre>";
} else {
    echo "<p>No reviews yet</p>";
}
?>

<h3>Test Insert Review</h3>
<form method="POST">
    <input type="number" name="rating" placeholder="Rating (1-5)" min="1" max="5" required><br><br>
    <textarea name="review_text" placeholder="Review text" required></textarea><br><br>
    <button type="submit">Submit Test Review</button>
</form>