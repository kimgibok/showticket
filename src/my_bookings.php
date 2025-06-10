<?php
include './dbconn.php';
session_start();

// 로그인 체크
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 탭 설정 (기본값: confirmed)
$tab = $_GET['tab'] ?? 'confirmed';
$status_filter = $tab === 'cancelled' ? 'cancelled' : 'confirmed';

// 페이지네이션 설정
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// 전체 예매 수 조회 (탭별)
$count_query = "
    SELECT COUNT(*) as total 
    FROM booking_groups 
    WHERE user_id = ? AND status = ?
";
$count_stmt = mysqli_prepare($connect, $count_query);
mysqli_stmt_bind_param($count_stmt, "ss", $_SESSION['user_id'], $status_filter);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_bookings = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_bookings / $limit);

// 예매 내역 조회 (최신순, 탭별)
$bookings_query = "
    SELECT 
        bg.group_id,
        bg.total_price,
        bg.payment_method,
        bg.booking_date,
        bg.status,
        bg.special_request,
        p.title,
        p.genre,
        v.venue_name,
        ps.performance_date,
        ps.show_time,
        ps.round_name
    FROM booking_groups bg
    JOIN performance_schedules ps ON bg.schedule_id = ps.schedule_id
    JOIN performances p ON ps.performance_id = p.performance_id
    JOIN venues v ON p.venue_id = v.venue_id
    WHERE bg.user_id = ? AND bg.status = ?
    ORDER BY bg.booking_date DESC
    LIMIT ? OFFSET ?
";

$bookings_stmt = mysqli_prepare($connect, $bookings_query);
mysqli_stmt_bind_param($bookings_stmt, "ssii", $_SESSION['user_id'], $status_filter, $limit, $offset);
mysqli_stmt_execute($bookings_stmt);
$bookings_result = mysqli_stmt_get_result($bookings_stmt);

// 취소 처리 (confirmed 상태의 예매만)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_booking']) && $tab === 'confirmed') {
    $group_id = $_POST['group_id'];
    
    // 공연 날짜가 지났는지 확인
    $check_query = "
        SELECT ps.performance_date, ps.show_time
        FROM booking_groups bg
        JOIN performance_schedules ps ON bg.schedule_id = ps.schedule_id
        WHERE bg.group_id = ? AND bg.user_id = ? AND bg.status = 'confirmed'
    ";
    $check_stmt = mysqli_prepare($connect, $check_query);
    mysqli_stmt_bind_param($check_stmt, "ss", $group_id, $_SESSION['user_id']);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if ($check_row = mysqli_fetch_assoc($check_result)) {
        $performance_datetime = $check_row['performance_date'] . ' ' . $check_row['show_time'];
        $performance_time = strtotime($performance_datetime);
        $current_time = time();
        
        // 공연 2시간 전까지만 취소 가능
        if ($performance_time - $current_time > 7200) {
            $cancel_query = "UPDATE booking_groups SET status = 'cancelled' WHERE group_id = ? AND user_id = ?";
            $cancel_stmt = mysqli_prepare($connect, $cancel_query);
            mysqli_stmt_bind_param($cancel_stmt, "ss", $group_id, $_SESSION['user_id']);
            
            if (mysqli_stmt_execute($cancel_stmt)) {
                $success_message = "예매가 성공적으로 취소되었습니다.";
            } else {
                $error_message = "취소 처리 중 오류가 발생했습니다.";
            }
            mysqli_stmt_close($cancel_stmt);
        } else {
            $error_message = "공연 2시간 전까지만 취소 가능합니다.";
        }
    }
    mysqli_stmt_close($check_stmt);
    
    // 페이지 새로고침을 위해 리다이렉트
    header("Location: my_bookings.php?tab=confirmed&page=$page");
    exit();
}

mysqli_stmt_close($count_stmt);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>내 예매 조회 - ShowTicket</title>
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

        .user-info a:hover {
            background-color: #f8f9fa;
        }
        
        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: transparent;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            border: 2px solid #667eea;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .btn-danger {
            border-color: #dc3545;
            color: #dc3545;
        }
        
        .btn-danger:hover {
            background: #dc3545;
            color: white;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .page-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            color: #666;
        }
        
        .tabs-section {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            background: white;
            border-radius: 10px;
            padding: 0.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }
        
        .tab-button {
            flex: 1;
            max-width: 200px;
            padding: 1rem 2rem;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            color: #666;
            text-decoration: none;
            text-align: center;
            font-size: 1rem;
            position: relative;
        }
        
        .tab-button:hover {
            color: #667eea;
            background: #f8f9ff;
        }
        
        .tab-button.active {
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .tab-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .tab-button:not(.active) .tab-count {
            background: #e9ecef;
            color: #666;
        }
        
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .bookings-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            padding: 1.5rem;
            border-bottom: 1px solid #e0e6ff;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
        }
        
        .booking-item {
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.3s;
        }
        
        .booking-item:hover {
            background: #f8f9fa;
        }
        
        .booking-item:last-child {
            border-bottom: none;
        }
        
        .booking-content {
            padding: 1.5rem;
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .booking-info {
            flex: 1;
        }
        
        .booking-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .booking-details {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .booking-meta {
            text-align: right;
        }
        
        .booking-id {
            font-size: 0.8rem;
            color: #999;
            margin-bottom: 0.5rem;
        }
        
        .booking-date {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .booking-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .status-confirmed {
            background-color: #28a745;
            color: white;
        }
        
        .status-cancelled {
            background-color: #dc3545;
            color: white;
        }
        
        .booking-seats {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f0f0f0;
        }
        
        .seats-title {
            font-size: 0.9rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .seat-types {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .seat-type-item {
            background: #f8f9fa;
            padding: 0.5rem 0.8rem;
            border-radius: 5px;
            font-size: 0.85rem;
            border: 1px solid #e9ecef;
        }
        
        .seat-type {
            font-weight: bold;
            color: #667eea;
        }
        
        .booking-actions {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f0f0f0;
            text-align: right;
        }
        
        .no-bookings {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }
        
        .no-bookings h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .no-bookings p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .page-link:hover, .page-link.active {
            background: #667eea;
            color: white;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
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
        
        @media (max-width: 768px) {
            .booking-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .booking-meta {
                text-align: left;
            }
            
            .seat-types {
                flex-direction: column;
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
                <a href="my_bookings.php" class="active">내 예매</a>
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
            <h1 class="page-title">🎫 내 예매 조회</h1>
            <p class="page-subtitle">예매한 공연 내역을 확인하고 관리하세요</p>
        </div>

        <!-- 탭 섹션 -->
        <div class="tabs-section">
            <?php
            // 탭별 카운트 조회
            $confirmed_count_query = "SELECT COUNT(*) as count FROM booking_groups WHERE user_id = ? AND status = 'confirmed'";
            $confirmed_stmt = mysqli_prepare($connect, $confirmed_count_query);
            mysqli_stmt_bind_param($confirmed_stmt, "s", $_SESSION['user_id']);
            mysqli_stmt_execute($confirmed_stmt);
            $confirmed_count = mysqli_fetch_assoc(mysqli_stmt_get_result($confirmed_stmt))['count'];
            
            $cancelled_count_query = "SELECT COUNT(*) as count FROM booking_groups WHERE user_id = ? AND status = 'cancelled'";
            $cancelled_stmt = mysqli_prepare($connect, $cancelled_count_query);
            mysqli_stmt_bind_param($cancelled_stmt, "s", $_SESSION['user_id']);
            mysqli_stmt_execute($cancelled_stmt);
            $cancelled_count = mysqli_fetch_assoc(mysqli_stmt_get_result($cancelled_stmt))['count'];
            
            mysqli_stmt_close($confirmed_stmt);
            mysqli_stmt_close($cancelled_stmt);
            ?>
            
            <a href="my_bookings.php?tab=confirmed" class="tab-button <?php echo $tab === 'confirmed' ? 'active' : ''; ?>">
                🎭 예매 완료
                <span class="tab-count"><?php echo $confirmed_count; ?></span>
            </a>
            <a href="my_bookings.php?tab=cancelled" class="tab-button <?php echo $tab === 'cancelled' ? 'active' : ''; ?>">
                ❌ 예매 취소
                <span class="tab-count"><?php echo $cancelled_count; ?></span>
            </a>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- 통계 섹션 -->
        <div class="stats-section">
            <?php
            // 통계 데이터 조회
            $stats_query = "
                SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                    COALESCE(SUM(CASE WHEN status = 'confirmed' THEN total_price ELSE 0 END), 0) as total_spent
                FROM booking_groups 
                WHERE user_id = ?
            ";
            $stats_stmt = mysqli_prepare($connect, $stats_query);
            mysqli_stmt_bind_param($stats_stmt, "s", $_SESSION['user_id']);
            mysqli_stmt_execute($stats_stmt);
            $stats_result = mysqli_stmt_get_result($stats_stmt);
            $stats = mysqli_fetch_assoc($stats_result);
            mysqli_stmt_close($stats_stmt);
            
            // NULL 값 방지
            $total_spent = $stats['total_spent'] ?? 0;
            ?>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                <div class="stat-label">총 예매 횟수</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['confirmed_bookings']; ?></div>
                <div class="stat-label">예매 완료</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['cancelled_bookings']; ?></div>
                <div class="stat-label">예매 취소</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_spent); ?>원</div>
                <div class="stat-label">총 결제금액</div>
            </div>
        </div>

        <!-- 예매 목록 -->
        <div class="bookings-section">
            <div class="section-header">
                <h2 class="section-title">
                    <?php echo $tab === 'confirmed' ? '🎭 예매 완료 내역' : '❌ 예매 취소 내역'; ?>
                </h2>
            </div>

            <?php if (mysqli_num_rows($bookings_result) > 0): ?>
                <?php while ($booking = mysqli_fetch_assoc($bookings_result)): ?>
                    <?php
                    // 해당 예매의 좌석 정보 조회
                    $seats_query = "
                        SELECT seat_type, seat_numbers, seat_count, unit_price, subtotal
                        FROM bookings 
                        WHERE booking_group_id = ?
                        ORDER BY seat_type
                    ";
                    $seats_stmt = mysqli_prepare($connect, $seats_query);
                    mysqli_stmt_bind_param($seats_stmt, "s", $booking['group_id']);
                    mysqli_stmt_execute($seats_stmt);
                    $seats_result = mysqli_stmt_get_result($seats_stmt);
                    
                    $is_past = strtotime($booking['performance_date'] . ' ' . $booking['show_time']) < time();
                    $can_cancel = !$is_past && $booking['status'] == 'confirmed' && 
                                  (strtotime($booking['performance_date'] . ' ' . $booking['show_time']) - time()) > 7200;
                    ?>

                    <div class="booking-item">
                        <div class="booking-content">
                            <div class="booking-header">
                                <div class="booking-info">
                                    <div class="booking-title"><?php echo htmlspecialchars($booking['title']); ?></div>
                                    <div class="booking-details">
                                        🎭 <?php echo $booking['genre']; ?> | 
                                        📍 <?php echo htmlspecialchars($booking['venue_name']); ?><br>
                                        🗓️ <?php echo date('Y년 m월 d일', strtotime($booking['performance_date'])); ?> 
                                        <?php echo date('H:i', strtotime($booking['show_time'])); ?> 
                                        (<?php echo $booking['round_name']; ?>)
                                    </div>
                                </div>
                                
                                <div class="booking-meta">
                                    <div class="booking-id">예매번호: <?php echo $booking['group_id']; ?></div>
                                    <div class="booking-date">
                                        <?php if ($tab === 'confirmed'): ?>
                                            예매일: <?php echo date('Y.m.d H:i', strtotime($booking['booking_date'])); ?>
                                        <?php else: ?>
                                            취소일: <?php echo date('Y.m.d H:i', strtotime($booking['booking_date'])); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="status-badge <?php echo $booking['status'] == 'confirmed' ? 'status-confirmed' : 'status-cancelled'; ?>">
                                        <?php echo $booking['status'] == 'confirmed' ? '예매완료' : '예매취소'; ?>
                                    </div>
                                    <div class="booking-price"><?php echo number_format($booking['total_price']); ?>원</div>
                                </div>
                            </div>

                            <!-- 좌석 정보 -->
                            <div class="booking-seats">
                                <div class="seats-title">📍 좌석 정보</div>
                                <div class="seat-types">
                                    <?php while ($seat = mysqli_fetch_assoc($seats_result)): ?>
                                        <div class="seat-type-item">
                                            <span class="seat-type"><?php echo $seat['seat_type']; ?>석</span>
                                            <?php echo $seat['seat_count']; ?>매 
                                            (<?php echo $seat['seat_numbers']; ?>번) - 
                                            <?php echo number_format($seat['subtotal']); ?>원
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <!-- 특별 요청사항 -->
                            <?php if (!empty($booking['special_request'])): ?>
                                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f0f0f0;">
                                    <strong>💬 특별 요청사항:</strong> <?php echo nl2br(htmlspecialchars($booking['special_request'])); ?>
                                </div>
                            <?php endif; ?>

                            <!-- 액션 버튼 (예매 완료 탭에서만 표시) -->
                            <?php if ($tab === 'confirmed' && $booking['status'] == 'confirmed'): ?>
                                <div class="booking-actions">
                                    <?php if ($can_cancel): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('정말로 예매를 취소하시겠습니까?');">
                                            <input type="hidden" name="group_id" value="<?php echo $booking['group_id']; ?>">
                                            <button type="submit" name="cancel_booking" class="btn btn-small btn-danger">
                                                예매 취소
                                            </button>
                                        </form>
                                    <?php elseif ($is_past): ?>
                                        <span style="color: #999; font-size: 0.9rem;">공연 종료</span>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 0.9rem;">취소 불가 (공연 2시간 전까지만 취소 가능)</span>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($tab === 'cancelled'): ?>
                                <div class="booking-actions">
                                    <span style="color: #dc3545; font-size: 0.9rem; font-weight: 500;">
                                        ✅ 예매가 취소되었습니다
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php mysqli_stmt_close($seats_stmt); ?>
                <?php endwhile; ?>

                <!-- 페이지네이션 -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?tab=<?php echo $tab; ?>&page=<?php echo $page - 1; ?>" class="page-link">‹ 이전</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?tab=<?php echo $tab; ?>&page=<?php echo $i; ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?tab=<?php echo $tab; ?>&page=<?php echo $page + 1; ?>" class="page-link">다음 ›</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="no-bookings">
                    <?php if ($tab === 'confirmed'): ?>
                        <h3>🎭 아직 예매한 공연이 없습니다</h3>
                        <p>다양한 공연을 둘러보고 첫 예매를 시작해보세요!</p>
                        <a href="home.php" class="btn">공연 둘러보기</a>
                    <?php else: ?>
                        <h3>❌ 취소한 예매가 없습니다</h3>
                        <p>취소한 예매 내역이 없습니다.</p>
                        <a href="my_bookings.php?tab=confirmed" class="btn">예매 완료 목록 보기</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    mysqli_stmt_close($bookings_stmt);
    mysqli_close($connect);
    ?>
</body>
</html>