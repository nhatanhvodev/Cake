<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Bạn chưa đăng nhập'
    ]);
    exit;
}

if (!isset($_POST['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu ID bài viết'
    ]);
    exit;
}

$blogId = (int)$_POST['id'];
$userId = (int)$_SESSION['user_id'];

require_once '../config/connect.php';

$stmt = $conn->prepare("DELETE FROM comments WHERE blog_id=?");
$stmt->bind_param("i", $blogId);
$stmt->execute();

$stmt = $conn->prepare("DELETE FROM likes WHERE blog_id=?");
$stmt->bind_param("i", $blogId);
$stmt->execute();

$stmt = $conn->prepare("DELETE FROM blogs WHERE id=? AND author_id=?");
$stmt->bind_param("ii", $blogId, $userId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Đã xóa bài viết thành công'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy bài viết hoặc bạn không có quyền'
    ]);
}

$conn->close();
