<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO PAGES/content-edit.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}
require_once '../Includes/connection.php';
$basePath = '';
include 'includes/sidebar.php';
$sql = "SELECT inquiries.id, inquiries.question, inquiries.submitted_at, inquiries.status, inquiries.reply, account.first_name, account.last_name, account.email, account.id_number
        FROM inquiries
        JOIN account ON inquiries.user_id = account.id
        ORDER BY inquiries.submitted_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Inquiries</title>
    
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../PAMO CSS/view_inquiries.css">
    
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="wrapper">
        <div class="main-content-inquiries">
            <div class="inquiries-header">Student Inquiries</div>
            <div class="inquiries-grid">
            <?php foreach($result as $row): ?>
            <article class="inquiry-card" data-inquiry-id="<?= $row['id'] ?>">
                <header class="inquiry-header">
                    <span class="material-icons">person</span>
                    <span><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?> <span style="font-weight:400; color:#222;">(ID: <?= htmlspecialchars($row['id_number']) ?>)</span></span>
                </header>
                <div class="inquiry-meta">
                    <span class="material-icons">calendar_month</span>
                    <span>Date: <?= date('M d, Y H:i', strtotime($row['submitted_at'])) ?></span>
                </div>
                <div class="inquiry-message">
                    <span class="material-icons">mail</span>
                    <span><?= nl2br(htmlspecialchars($row['question'])) ?></span>
                </div>
                <?php if (!empty($row['reply'])): ?>
                <div class="inquiry-reply">
                    <b>Reply:</b> <?= nl2br(htmlspecialchars($row['reply'])) ?>
                </div>
                <?php endif; ?>
                <div>
                    <span class="inquiry-status<?= (!empty($row['reply']) || (isset($row['status']) && $row['status'] === 'replied')) ? ' replied' : '' ?>">
                        <?= (!empty($row['reply']) || (isset($row['status']) && $row['status'] === 'replied')) ? 'Replied' : 'New' ?>
                    </span>
                </div>
                <div class="inquiry-actions">
                    <button class="reply-btn" data-id="<?= $row['id'] ?>">Reply</button>
                </div>
            </article>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <!-- Reply Modal -->
    <div id="replyModal" class="reply-modal-bg" style="display:none;">
        <div class="reply-modal">
            <span id="closeReplyModal" class="reply-modal-close">&times;</span>
            <h2 class="reply-modal-title">Reply to Inquiry</h2>
            <form id="replyForm">
                <textarea name="reply" id="modalReplyText" rows="5" class="reply-modal-textarea" required placeholder="Type your reply here..."></textarea>
                <input type="hidden" name="inquiry_id" id="modalInquiryId">
                <button type="submit" class="reply-modal-btn">Send Reply</button>
            </form>
        </div>
    </div>
    <script src="../PAMO JS/view_inquiries.js"></script>
</body>
</html>
<?php $conn = null; ?> 