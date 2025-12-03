<?php
// Accounting Header (shared)
// Only start session if headers not sent to avoid warnings
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}
$profileData = [
    'First_Name' => 'User',
    'Last_Name' => '',
    'Profile_Pic' => '../images/default_profile.png'
];
try {
    if (isset($conn) && isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT First_Name, Last_Name, Profile_Pic FROM users WHERE User_ID = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $profileData['First_Name'] = $row['First_Name'] ?? 'User';
            $profileData['Last_Name'] = $row['Last_Name'] ?? '';
            $pic = $row['Profile_Pic'] ?? '';
            if ($pic && file_exists($pic)) {
                $profileData['Profile_Pic'] = $pic;
            }
        }
    }
} catch (Exception $e) {}
?>
<div class="header">
    <button class="toggle-sidebar" id="toggleSidebar">
        <span class="material-icons">menu</span>
    </button>
    <div class="current-datetime ms-3 d-none d-md-block">
        <span id="current-date"></span> | <span id="current-time"></span>
    </div>
    <div class="user-profile" id="userProfile">
        <span><?php echo htmlspecialchars($profileData['First_Name']. ' ' . $profileData['Last_Name']); ?></span>
        <a href="profile.php"><img src="<?php echo htmlspecialchars($profileData['Profile_Pic']); ?>" alt="User Profile"></a>
    </div>
</div>
