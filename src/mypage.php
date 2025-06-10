<?php
include './dbconn.php';
session_start();

// 로그인 체크
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$error_message = "";
$success_message = "";

// 사용자 정보 조회
$user_query = "SELECT * FROM users WHERE user_id = ?";
$user_stmt = mysqli_prepare($connect, $user_query);
mysqli_stmt_bind_param($user_stmt, "s", $_SESSION['user_id']);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// 프로필 수정 처리
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    
    // 유효성 검사
    if (empty($name) || empty($phone) || empty($email) || empty($address)) {
        $error_message = "모든 필수 정보를 입력해주세요.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "올바른 이메일 형식이 아닙니다.";
    } else {
        // 이메일 중복 확인 (본인 제외)
        $email_check = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $email_stmt = mysqli_prepare($connect, $email_check);
        mysqli_stmt_bind_param($email_stmt, "ss", $email, $_SESSION['user_id']);
        mysqli_stmt_execute($email_stmt);
        $email_result = mysqli_stmt_get_result($email_stmt);
        
        if (mysqli_num_rows($email_result) > 0) {
            $error_message = "이미 사용 중인 이메일입니다.";
        } else {
            // 정보 업데이트
            $update_query = "UPDATE users SET name = ?, phone = ?, email = ?, address = ? WHERE user_id = ?";
            $update_stmt = mysqli_prepare($connect, $update_query);
            mysqli_stmt_bind_param($update_stmt, "sssss", $name, $phone, $email, $address, $_SESSION['user_id']);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success_message = "회원 정보가 성공적으로 수정되었습니다.";
                $_SESSION['name'] = $name; // 세션 이름도 업데이트
                
                // 사용자 정보 다시 조회
                mysqli_stmt_execute($user_stmt);
                $user_result = mysqli_stmt_get_result($user_stmt);
                $user = mysqli_fetch_assoc($user_result);
            } else {
                $error_message = "정보 수정 중 오류가 발생했습니다.";
            }
            mysqli_stmt_close($update_stmt);
        }
        mysqli_stmt_close($email_stmt);
    }
}

// 비밀번호 변경 처리
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "모든 비밀번호 필드를 입력해주세요.";
    } elseif ($current_password !== $user['password']) {
        $error_message = "현재 비밀번호가 올바르지 않습니다.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "새 비밀번호는 6글자 이상이어야 합니다.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "새 비밀번호가 일치하지 않습니다.";
    } else {
        // 비밀번호 업데이트
        $password_query = "UPDATE users SET password = ? WHERE user_id = ?";
        $password_stmt = mysqli_prepare($connect, $password_query);
        mysqli_stmt_bind_param($password_stmt, "ss", $new_password, $_SESSION['user_id']);
        
        if (mysqli_stmt_execute($password_stmt)) {
            $success_message = "비밀번호가 성공적으로 변경되었습니다.";
            
            // 사용자 정보 다시 조회
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $user = mysqli_fetch_assoc($user_result);
        } else {
            $error_message = "비밀번호 변경 중 오류가 발생했습니다.";
        }
        mysqli_stmt_close($password_stmt);
    }
}

// 회원탈퇴 처리
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_account'])) {
    $confirm_password = $_POST['confirm_delete_password'];
    $confirm_text = $_POST['confirm_delete_text'];
    
    if (empty($confirm_password)) {
        $error_message = "비밀번호를 입력해주세요.";
    } elseif ($confirm_password !== $user['password']) {
        $error_message = "비밀번호가 올바르지 않습니다.";
    } elseif ($confirm_text !== "회원탈퇴") {
        $error_message = "확인 문구를 정확히 입력해주세요.";
    } else {
        // 예매 내역 확인
        $booking_check = "
            SELECT COUNT(*) as booking_count 
            FROM booking_groups bg 
            JOIN performance_schedules ps ON bg.schedule_id = ps.schedule_id 
            WHERE bg.user_id = ? AND bg.status = 'confirmed' 
            AND ps.performance_date >= CURDATE()
        ";
        $booking_stmt = mysqli_prepare($connect, $booking_check);
        mysqli_stmt_bind_param($booking_stmt, "s", $_SESSION['user_id']);
        mysqli_stmt_execute($booking_stmt);
        $booking_result = mysqli_stmt_get_result($booking_stmt);
        $booking_count = mysqli_fetch_assoc($booking_result)['booking_count'];
        
        if ($booking_count > 0) {
            $error_message = "예정된 예매가 있어 탈퇴할 수 없습니다. 예매를 취소한 후 다시 시도해주세요.";
        } else {
            // 트랜잭션 시작
            mysqli_begin_transaction($connect);
            
            try {
                // 과거 예매 기록은 유지하되 개인정보만 마스킹
                $mask_bookings = "
                    UPDATE booking_groups 
                    SET user_id = CONCAT('DELETED_', user_id, '_', UNIX_TIMESTAMP()), 
                        special_request = '(탈퇴한 사용자)'
                    WHERE user_id = ?
                ";
                $mask_stmt = mysqli_prepare($connect, $mask_bookings);
                mysqli_stmt_bind_param($mask_stmt, "s", $_SESSION['user_id']);
                mysqli_stmt_execute($mask_stmt);
                
                // 사용자 계정 삭제
                $delete_user = "DELETE FROM users WHERE user_id = ?";
                $delete_stmt = mysqli_prepare($connect, $delete_user);
                mysqli_stmt_bind_param($delete_stmt, "s", $_SESSION['user_id']);
                mysqli_stmt_execute($delete_stmt);
                
                mysqli_commit($connect);
                
                // 세션 삭제
                session_destroy();
                
                // 탈퇴 완료 페이지로 리다이렉트
                echo "<script>
                    alert('회원탈퇴가 완료되었습니다. 그동안 이용해주셔서 감사합니다.');
                    window.location.href = 'home.php';
                </script>";
                exit();
                
                mysqli_stmt_close($mask_stmt);
                mysqli_stmt_close($delete_stmt);
                
            } catch (Exception $e) {
                mysqli_rollback($connect);
                $error_message = "회원탈퇴 처리 중 오류가 발생했습니다.";
            }
        }
        mysqli_stmt_close($booking_stmt);
    }
}

// 예매 통계 조회
$stats_query = "
    SELECT 
        COUNT(DISTINCT bg.group_id) as total_bookings,
        SUM(bg.total_price) as total_spent,
        COUNT(DISTINCT ps.performance_id) as unique_performances
    FROM booking_groups bg
    JOIN performance_schedules ps ON bg.schedule_id = ps.schedule_id
    WHERE bg.user_id = ? AND bg.status = 'confirmed'
";
$stats_stmt = mysqli_prepare($connect, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "s", $_SESSION['user_id']);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

mysqli_stmt_close($user_stmt);
mysqli_stmt_close($stats_stmt);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>마이페이지 - ShowTicket</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
        }
        
        .header {
            background-color: white;
            color: #333;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 1px solid #eee;
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1rem;
        }
        
        .logo {
            font-size: 2rem;
            font-weight: bold;
            text-decoration: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-left: -1rem;
        }
        
        .nav-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-menu a {
            color: #333;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
            font-weight: 500;
        }
        
        .nav-menu a:hover, .nav-menu a.active {
            color: #667eea;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            border: 1px solid #eee;
            border-radius: 8px;
            z-index: 1000;
            top: 100%;
            left: 0;
            padding: 0.5rem 0;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-content a {
            color: #333 !important;
            padding: 0.8rem 1.2rem !important;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s;
            border-radius: 0 !important;
        }
        
        .dropdown-content a:hover {
            color: #667eea !important;
        }
        
        .user-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2rem;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .page-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
        }
        
        .user-welcome {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            border: 2px solid #e0e6ff;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 3rem;
            text-align: center;
        }
        
        .welcome-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .welcome-title {
            font-size: 1.8rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .welcome-subtitle {
            color: #666;
            margin-bottom: 1.5rem;
        }
        
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .stat-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.3rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }
        
        .tabs-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            overflow: hidden;
        }
        
        .tabs-header {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .tab-button {
            flex: 1;
            padding: 1.2rem;
            background: transparent;
            border: none;
            font-size: 1rem;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .tab-button:hover {
            background: #e9ecef;
            color: #333;
        }
        
        .tab-button.active {
            background: white;
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
            padding: 2rem;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .profile-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-control:disabled {
            background-color: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }
        
        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: transparent;
            color: #667eea;
            text-decoration: none;
            border-radius: 8px;
            border: 2px solid #667eea;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }
        
        .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .danger-zone {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            border: 2px solid #f44336;
            border-radius: 10px;
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .danger-title {
            color: #c62828;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .danger-text {
            color: #d32f2f;
            margin-bottom: 1.5rem;
        }
        
        .confirm-inputs {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 0.5rem;
        }
        
        .badge-admin {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .badge-user {
            background: #28a745;
            color: white;
        }
        
        .user-info-display {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .user-info a:hover {
            background-color: #f8f9fa;
        }
        
        .info-item {
            display: flex;
            padding: 0.8rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #667eea;
            min-width: 120px;
            margin-right: 1rem;
        }
        
        .info-value {
            color: #333;
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .tabs-header {
                flex-direction: column;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .user-stats {
                grid-template-columns: 1fr;
            }
            
            .info-item {
                flex-direction: column;
            }
            
            .info-label {
                margin-bottom: 0.3rem;
            }
        }
    </style>
</head>
<body>
    <!-- 헤더 -->
    <header class="header">
        <div class="nav-container">
            <a href="home.php" class="logo">🎭 ShowTicket</a>
            
            <nav class="nav-menu">
                <a href="home.php">홈</a>
                <div class="dropdown">
                    <a href="performances.php">공연</a>
                    <div class="dropdown-content">
                        <a href="performances.php">전체 공연</a>
                        <?php
                        // 데이터베이스에서 실제 존재하는 장르들을 조회
                        $genre_query = "SELECT DISTINCT genre FROM performances ORDER BY genre";
                        $genre_result = mysqli_query($connect, $genre_query);
                        
                        while ($genre_row = mysqli_fetch_assoc($genre_result)) {
                            $genre = $genre_row['genre'];
                            echo "<a href='performances.php?genre=" . urlencode($genre) . "'>{$genre}</a>";
                        }
                        mysqli_free_result($genre_result);
                        ?>
                    </div>
                </div>
                <a href="my_bookings.php">내 예매</a>
                <?php if (isset($_SESSION['is_staff']) && $_SESSION['is_staff']): ?>
                    <a href="admin_performances.php">공연 관리</a>
                <?php endif; ?>
            </nav>
            
            <div class="user-info">
                    <a href="mypage.php" style="color: #333; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 8px; transition: background-color 0.3s;">
                        <span>👤</span>
                        <span><?php echo htmlspecialchars($_SESSION['name']); ?>님</span>
                    </a>
                    <a href="logout.php" class="btn">로그아웃</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- 페이지 헤더 -->
        <div class="page-header">
            <h1 class="page-title">👤 마이페이지</h1>
            <p class="page-subtitle">회원 정보를 관리하고 계정 설정을 변경하세요</p>
        </div>

        <!-- 사용자 환영 섹션 -->
        <div class="user-welcome">
            <div class="welcome-icon">🌟</div>
            <h2 class="welcome-title">
                <?php echo htmlspecialchars($user['name']); ?>님, 환영합니다!
                <span class="badge <?php echo $user['is_staff'] ? 'badge-admin' : 'badge-user'; ?>">
                    <?php echo $user['is_staff'] ? '관리자' : '일반회원'; ?>
                </span>
            </h2>
            <p class="welcome-subtitle">가입일: <?php echo date('Y년 m월 d일', strtotime($user['created_at'])); ?></p>
            
            <div class="user-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total_bookings'] ?: 0; ?></div>
                    <div class="stat-label">총 예매 횟수</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($stats['total_spent'] ?: 0); ?>원</div>
                    <div class="stat-label">총 이용금액</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['unique_performances'] ?: 0; ?></div>
                    <div class="stat-label">관람한 공연 수</div>
                </div>
            </div>
        </div>

        <!-- 알림 메시지 -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- 탭 컨테이너 -->
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-button active" onclick="showTab('profile')">📋 기본 정보</button>
                <button class="tab-button" onclick="showTab('password')">🔒 비밀번호 변경</button>
                <button class="tab-button" onclick="showTab('account')">⚙️ 계정 관리</button>
            </div>

            <!-- 기본 정보 탭 -->
            <div id="profile" class="tab-content active">
                <div class="profile-section">
                    <h3 class="section-title">회원 기본 정보</h3>
                    
                    <!-- 현재 정보 표시 -->
                    <div class="user-info-display">
                        <div class="info-item">
                            <span class="info-label">아이디</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['user_id']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">이름</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">전화번호</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['phone']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">이메일</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">주소</span>
                            <span class="info-value"><?php echo nl2br(htmlspecialchars($user['address'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">가입일</span>
                            <span class="info-value"><?php echo date('Y년 m월 d일 H:i', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>

                    <!-- 정보 수정 폼 -->
                    <form method="POST" action="" id="profileForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">아이디</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['user_id']); ?>" disabled>
                                <small style="color: #666; font-size: 0.85rem;">아이디는 변경할 수 없습니다</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">이름 <span class="required">*</span></label>
                                <input type="text" name="name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">전화번호 <span class="required">*</span></label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">이메일 <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="form-group full-width">
                                <label class="form-label">주소 <span class="required">*</span></label>
                                <textarea name="address" class="form-control" rows="3" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 2rem;">
                            <button type="submit" name="update_profile" class="btn btn-primary">📝 정보 수정</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 비밀번호 변경 탭 -->
            <div id="password" class="tab-content">
                <div class="profile-section">
                    <h3 class="section-title">비밀번호 변경</h3>
                    
                    <form method="POST" action="" id="passwordForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">현재 비밀번호 <span class="required">*</span></label>
                                <input type="password" name="current_password" class="form-control" 
                                       placeholder="현재 비밀번호를 입력하세요" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">새 비밀번호 <span class="required">*</span></label>
                                <input type="password" name="new_password" class="form-control" 
                                       placeholder="6글자 이상 입력하세요" minlength="6" required>
                            </div>
                            
                            <div class="form-group full-width">
                                <label class="form-label">새 비밀번호 확인 <span class="required">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" 
                                       placeholder="새 비밀번호를 다시 입력하세요" required>
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 2rem;">
                            <button type="submit" name="change_password" class="btn btn-primary">🔒 비밀번호 변경</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 계정 관리 탭 -->
            <div id="account" class="tab-content">
                <div class="profile-section">
                    <h3 class="section-title">계정 설정</h3>
                    
                    <div style="margin-bottom: 3rem;">
                        <h4 style="color: #333; margin-bottom: 1rem;">📊 내 활동 내역</h4>
                        <div class="user-info-display">
                            <div class="info-item">
                                <span class="info-label">총 예매 건수</span>
                                <span class="info-value"><?php echo $stats['total_bookings'] ?: 0; ?>건</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">총 이용금액</span>
                                <span class="info-value"><?php echo number_format($stats['total_spent'] ?: 0); ?>원</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">관람한 공연</span>
                                <span class="info-value"><?php echo $stats['unique_performances'] ?: 0; ?>개</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">회원 등급</span>
                                <span class="info-value">
                                    <?php echo $user['is_staff'] ? '관리자' : '일반회원'; ?>
                                    <?php if ($user['is_staff']): ?>
                                        <small style="color: #667eea;">(공연 관리 권한)</small>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div style="text-align: center;">
                            <a href="my_bookings.php" class="btn">📋 내 예매 내역 보기</a>
                        </div>
                    </div>

                    <!-- 위험 구역 -->
                    <div class="danger-zone">
                        <h4 class="danger-title">⚠️ 위험 구역</h4>
                        <p class="danger-text">
                            회원탈퇴를 하시면 모든 개인정보가 삭제되며, 이는 되돌릴 수 없습니다.<br>
                            단, 예정된 예매가 있는 경우 탈퇴할 수 없습니다.
                        </p>
                        
                        <button type="button" class="btn btn-danger" onclick="showDeleteForm()">
                            🗑️ 회원탈퇴
                        </button>
                        
                        <!-- 회원탈퇴 확인 폼 -->
                        <div id="deleteForm" style="display: none; margin-top: 2rem;">
                            <form method="POST" action="" id="deleteAccountForm">
                                <div class="confirm-inputs">
                                    <h5 style="color: #c62828; margin-bottom: 1rem;">탈퇴 확인</h5>
                                    
                                    <div class="form-group">
                                        <label class="form-label">비밀번호 확인 <span class="required">*</span></label>
                                        <input type="password" name="confirm_delete_password" class="form-control" 
                                               placeholder="계정 비밀번호를 입력하세요" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">확인 문구 입력 <span class="required">*</span></label>
                                        <input type="text" name="confirm_delete_text" class="form-control" 
                                               placeholder="'회원탈퇴'를 정확히 입력하세요" required>
                                        <small style="color: #666; font-size: 0.85rem;">위험한 작업이므로 정확한 문구를 입력해야 합니다</small>
                                    </div>
                                </div>
                                
                                <div style="text-align: center; gap: 1rem; display: flex; justify-content: center;">
                                    <button type="button" class="btn btn-secondary" onclick="hideDeleteForm()">취소</button>
                                    <button type="submit" name="delete_account" class="btn btn-danger">⚠️ 탈퇴 진행</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 탭 전환 함수
        function showTab(tabName) {
            // 모든 탭 버튼에서 active 클래스 제거
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // 모든 탭 콘텐츠 숨기기
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // 선택된 탭 버튼에 active 클래스 추가
            event.target.classList.add('active');
            
            // 선택된 탭 콘텐츠 보이기
            document.getElementById(tabName).classList.add('active');
        }

        // 비밀번호 확인 검증
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('새 비밀번호가 일치하지 않습니다.');
                return;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('새 비밀번호는 6글자 이상이어야 합니다.');
                return;
            }
        });

        // 프로필 수정 확인
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const confirmed = confirm('회원 정보를 수정하시겠습니까?');
            if (!confirmed) {
                e.preventDefault();
            }
        });

        // 회원탈퇴 폼 표시/숨김
        function showDeleteForm() {
            document.getElementById('deleteForm').style.display = 'block';
        }

        function hideDeleteForm() {
            document.getElementById('deleteForm').style.display = 'none';
            // 폼 내용 초기화
            document.getElementById('deleteAccountForm').reset();
        }

        // 회원탈퇴 최종 확인
        document.getElementById('deleteAccountForm').addEventListener('submit', function(e) {
            const confirmText = document.querySelector('input[name="confirm_delete_text"]').value;
            
            if (confirmText !== '회원탈퇴') {
                e.preventDefault();
                alert('확인 문구를 정확히 입력해주세요: "회원탈퇴"');
                return;
            }
            
            const finalConfirm = confirm(
                '정말로 회원탈퇴를 진행하시겠습니까?\n\n' +
                '⚠️ 이 작업은 되돌릴 수 없습니다!\n' +
                '⚠️ 모든 개인정보가 삭제됩니다!\n' +
                '⚠️ 과거 예매 기록은 익명화되어 유지됩니다!\n\n' +
                '정말로 탈퇴하시려면 "확인"을 클릭하세요.'
            );
            
            if (!finalConfirm) {
                e.preventDefault();
            }
        });

        // 전화번호 입력 시 자동 포맷팅 (선택사항)
        document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value.length >= 3 && value.length <= 7) {
                value = value.replace(/(\d{3})(\d+)/, '$1-$2');
            } else if (value.length > 7) {
                value = value.replace(/(\d{3})(\d{4})(\d+)/, '$1-$2-$3');
            }
            e.target.value = value;
        });

        // URL 해시를 기반으로 탭 활성화
        window.addEventListener('load', function() {
            const hash = window.location.hash.substr(1);
            if (hash && ['profile', 'password', 'account'].includes(hash)) {
                // 모든 탭 버튼과 콘텐츠 비활성화
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                // 해당 탭 활성화
                document.querySelector(`[onclick="showTab('${hash}')"]`).classList.add('active');
                document.getElementById(hash).classList.add('active');
            }
        });

        // 탭 변경 시 URL 해시 업데이트
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tabName = this.getAttribute('onclick').match(/'([^']+)'/)[1];
                history.replaceState(null, null, `#${tabName}`);
            });
        });
    </script>

    <?php mysqli_close($connect); ?>
</body>
</html>