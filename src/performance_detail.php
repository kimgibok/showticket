<?php
include './dbconn.php';
session_start();

// 공연 ID 가져오기
$performance_id = $_GET['id'] ?? 0;

if (!$performance_id) {
    header("Location: home.php");
    exit();
}

// 공연 정보 조회
$query = "
    SELECT p.*, v.venue_name, v.location 
    FROM performances p 
    JOIN venues v ON p.venue_id = v.venue_id 
    WHERE p.performance_id = ?
";
$stmt = mysqli_prepare($connect, $query);
mysqli_stmt_bind_param($stmt, "i", $performance_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$performance = mysqli_fetch_assoc($result);

if (!$performance) {
    header("Location: home.php");
    exit();
}

// 공연 회차 정보 조회 (현재 시간 이후의 회차만)
$schedule_query = "
    SELECT * FROM performance_schedules 
    WHERE performance_id = ? 
    AND (
        performance_date > CURDATE() 
        OR (performance_date = CURDATE() AND show_time > CURTIME())
    )
    ORDER BY performance_date ASC, show_time ASC
    LIMIT 20
";
$schedule_stmt = mysqli_prepare($connect, $schedule_query);
mysqli_stmt_bind_param($schedule_stmt, "i", $performance_id);
mysqli_stmt_execute($schedule_stmt);
$schedule_result = mysqli_stmt_get_result($schedule_stmt);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($performance['title']); ?> - ShowTicket</title>
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
            background-color: white;
        }
        
        .header {
            background-color: white;
            color: #333;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 1px solid #eee;
            position: relative;
            z-index: 1000;
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
            position: relative;
            align-items: center;
        }
        
        .nav-menu a {
            color: #333;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .nav-menu a:hover {
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
            text-align: center;
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
            font-size: 1.1rem;
            padding: 1rem 2rem;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .breadcrumb {
            margin-bottom: 2rem;
            color: #666;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .performance-header {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }
        
        .poster-section {
            position: sticky;
            top: 2rem;
            height: fit-content;
        }
        
        .poster-image {
            width: 100%;
            height: 400px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .performance-info {
            flex: 1;
        }
        
        .performance-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .genre-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        
        .info-list {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .info-list-item {
            display: flex;
            padding: 0.8rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-list-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #667eea;
            font-weight: 600;
            min-width: 100px;
            margin-right: 1rem;
        }
        
        .info-value {
            color: #333;
            font-weight: 500;
        }
        
        .price-section {
            background: white;
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .price-title {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: #333;
            text-align: center;
        }
        
        .price-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .price-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .price-type {
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .price-amount {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }
        
        .description-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            margin-bottom: 3rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        .schedule-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            margin-bottom: 3rem;
        }
        
        .schedule-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            gap: 2rem;
        }
        
        .step-section {
            flex: 1;
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .step-section.active {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .step-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }
        
        .step-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .step-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }
        
        .calendar-container {
            max-width: 100%;
            margin: 0 auto;
        }
        
        .calendar-header {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1rem;
            gap: 1rem;
        }
        
        .calendar-nav {
            background: #667eea;
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: background 0.3s;
        }
        
        .calendar-nav:hover {
            background: #5a67d8;
        }
        
        .calendar-month {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            min-width: 120px;
            text-align: center;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            background: #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .calendar-day-header {
            background: #667eea;
            color: white;
            padding: 0.8rem 0.5rem;
            text-align: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .calendar-day {
            background: white;
            padding: 0.8rem 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            min-height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border: 2px solid transparent;
        }
        
        .calendar-day.other-month {
            background: #f8f9fa;
            color: #adb5bd;
            cursor: not-allowed;
        }
        
        .calendar-day.has-performance {
            background: #e3f2fd;
            color: #1976d2;
            font-weight: bold;
            cursor: pointer;
        }
        
        .calendar-day.has-performance:hover {
            background: #bbdefb;
            transform: scale(1.05);
        }
        
        .calendar-day.selected {
            background: #667eea !important;
            color: white !important;
            border-color: #5a67d8;
        }
        
        .performance-indicator {
            position: absolute;
            bottom: 3px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background: #667eea;
            border-radius: 50%;
        }
        
        .calendar-day.selected .performance-indicator {
            background: white;
        }
        
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .time-slot {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .time-slot:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .time-slot.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        
        .time-slot.past {
            background: #f8f9fa;
            color: #adb5bd;
            cursor: not-allowed;
            border-color: #dee2e6;
        }
        
        .time-slot.past:hover {
            background: #f8f9fa;
            border-color: #dee2e6;
        }
        
        .time-slot-time {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.3rem;
        }
        
        .time-slot-round {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .time-slot-status {
            font-size: 0.8rem;
            color: #dc3545;
            margin-top: 0.3rem;
        }
        
        .selected-info {
            background: white;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            text-align: center;
        }
        
        .selected-date {
            font-size: 1.1rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.3rem;
        }
        
        .selected-time {
            color: #666;
        }
        
        .step2-placeholder {
            text-align: center;
            color: #adb5bd;
            font-style: italic;
            padding: 2rem;
        }
        
        .booking-section {
            text-align: center;
            padding: 3rem 0;
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .booking-title {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .booking-subtitle {
            color: #666;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .status-booking {
            background-color: #28a745;
            color: white;
        }
        
        .status-upcoming {
            background-color: #ffc107;
            color: #333;
        }
        
        .status-closed {
            background-color: #6c757d;
            color: white;
        }
        
        .no-schedule {
            text-align: center;
            padding: 2rem;
            color: #666;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .performance-header {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .poster-section {
                position: static;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .price-grid {
                grid-template-columns: 1fr;
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
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="my_bookings.php">내 예매</a>
                    <?php if (isset($_SESSION['is_staff']) && $_SESSION['is_staff']): ?>
                        <a href="admin_performances.php">공연 관리</a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
            
            <div class="user-info">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="mypage.php" style="color: #333; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 8px; transition: background-color 0.3s;">
                        <span>👤</span>
                        <span><?php echo htmlspecialchars($_SESSION['name']); ?>님</span>
                    </a>
                    <a href="logout.php" class="btn">로그아웃</a>
                <?php else: ?>
                    <a href="login.php" class="btn">로그인</a>
                    <a href="register.php" class="btn">회원가입</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- 브레드크럼 -->
        <div class="breadcrumb">
            <a href="home.php">홈</a> > 
            <a href="performances.php?genre=<?php echo urlencode($performance['genre']); ?>"><?php echo $performance['genre']; ?></a> > 
            <?php echo htmlspecialchars($performance['title']); ?>
        </div>

        <!-- 공연 헤더 -->
        <div class="performance-header">
            <div class="poster-section">
                <?php if (!empty($performance['poster_image'])): ?>
                    <img src="<?php echo $performance['poster_image']; ?>" alt="<?php echo $performance['title']; ?>" class="poster-image">
                <?php else: ?>
                    <div class="poster-image">
                        <?php echo htmlspecialchars($performance['title']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="performance-info">
                <h1 class="performance-title"><?php echo htmlspecialchars($performance['title']); ?></h1>
                <span class="genre-badge"><?php echo $performance['genre']; ?></span>
                
                <!-- 상태 배지 -->
                <?php
                $status_class = match($performance['status']) {
                    'booking' => 'status-booking',
                    'upcoming' => 'status-upcoming',
                    'closed' => 'status-closed',
                    'finished' => 'status-closed',
                    default => 'status-upcoming'
                };
                
                $status_text = match($performance['status']) {
                    'booking' => '예매 중',
                    'upcoming' => '예매 예정',
                    'closed' => '예매 마감',
                    'finished' => '공연 종료',
                    default => '예매 예정'
                };
                ?>
                <div class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></div>

                <div class="info-list">
                    <div class="info-list-item">
                        <span class="info-label">공연장</span>
                        <span class="info-value"><?php echo htmlspecialchars($performance['venue_name']); ?></span>
                    </div>
                    <div class="info-list-item">
                        <span class="info-label">장소</span>
                        <span class="info-value"><?php echo htmlspecialchars($performance['location']); ?></span>
                    </div>
                    <div class="info-list-item">
                        <span class="info-label">공연 기간</span>
                        <span class="info-value"><?php echo $performance['performance_start_date']; ?> ~ <?php echo $performance['performance_end_date']; ?></span>
                    </div>
                    <div class="info-list-item">
                        <span class="info-label">예매 기간</span>
                        <span class="info-value"><?php echo date('Y-m-d', strtotime($performance['booking_start_date'])); ?> ~ <?php echo date('Y-m-d', strtotime($performance['booking_end_date'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 가격 정보 -->
        <div class="price-section">
            <h3 class="price-title">💰 좌석별 가격 정보</h3>
            <div class="price-grid">
                <?php if ($performance['vip_seats'] > 0): ?>
                    <div class="price-item">
                        <div class="price-type">VIP석 (<?php echo $performance['vip_floor']; ?>층)</div>
                        <div class="price-amount"><?php echo number_format($performance['vip_price']); ?>원</div>
                    </div>
                <?php endif; ?>
                
                <?php if ($performance['r_seats'] > 0): ?>
                    <div class="price-item">
                        <div class="price-type">R석 (<?php echo $performance['r_floor']; ?>층)</div>
                        <div class="price-amount"><?php echo number_format($performance['r_price']); ?>원</div>
                    </div>
                <?php endif; ?>
                
                <?php if ($performance['s_seats'] > 0): ?>
                    <div class="price-item">
                        <div class="price-type">S석 (<?php echo $performance['s_floor']; ?>층)</div>
                        <div class="price-amount"><?php echo number_format($performance['s_price']); ?>원</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 공연 일정 -->
        <div class="schedule-section">
            <h2 class="section-title">📅 예매하기</h2>
            
            <div class="schedule-steps">
                <!-- STEP 1: 날짜 선택 -->
                <div class="step-section active" id="step1">
                    <div class="step-header">
                        <div class="step-number">1</div>
                        <div class="step-title">날짜 선택</div>
                    </div>
                    
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <button class="calendar-nav" onclick="changeMonth(-1)">‹</button>
                            <div class="calendar-month" id="currentMonth"></div>
                            <button class="calendar-nav" onclick="changeMonth(1)">›</button>
                        </div>
                        
                        <div class="calendar-grid" id="calendarGrid">
                            <!-- 달력이 JavaScript로 동적 생성됩니다 -->
                        </div>
                    </div>
                </div>
                
                <!-- STEP 2: 회차 선택 -->
                <div class="step-section" id="step2">
                    <div class="step-header">
                        <div class="step-number">2</div>
                        <div class="step-title">회차 선택</div>
                    </div>
                    
                    <div id="timeSlots">
                        <div class="step2-placeholder">
                            먼저 날짜를 선택해주세요
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 선택 정보 및 예매 버튼 -->
            <div id="bookingArea" style="display: none;">
                <div class="selected-info">
                    <div class="selected-date" id="selectedDate"></div>
                    <div class="selected-time" id="selectedTime"></div>
                </div>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    <?php if ($performance['status'] == 'booking'): ?>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <button class="btn btn-primary" onclick="startBooking()" id="bookingButton">
                                선택한 회차로 예매하기
                            </button>
                        <?php else: ?>
                            <div style="margin-bottom: 1rem; color: #666;">
                                예매를 위해서는 로그인이 필요합니다
                            </div>
                            <a href="login.php" class="btn btn-primary">로그인 후 예매하기</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="color: #999; font-style: italic;">
                            현재 예매가 불가능합니다
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 공연 설명 -->
        <?php if (!empty($performance['description'])): ?>
        <div class="description-section">
            <h2 class="section-title">📋 공연 소개</h2>
            <p><?php echo nl2br(htmlspecialchars($performance['description'])); ?></p>
        </div>
        <?php endif; ?>

    </div>

    <script>
        let selectedScheduleId = null;
        let currentDate = new Date();
        
        // 현재 시간 (한국 시간 기준)
        const now = new Date();
        const currentTime = {
            year: now.getFullYear(),
            month: now.getMonth() + 1,
            date: now.getDate(),
            hour: now.getHours(),
            minute: now.getMinutes()
        };
        
        // PHP에서 스케줄 데이터를 JavaScript로 전달
        const performanceSchedules = {
            <?php
            mysqli_data_seek($schedule_result, 0); // 결과 포인터를 다시 처음으로
            $schedules_by_date = [];
            while ($schedule = mysqli_fetch_assoc($schedule_result)) {
                $date = $schedule['performance_date'];
                if (!isset($schedules_by_date[$date])) {
                    $schedules_by_date[$date] = [];
                }
                $schedules_by_date[$date][] = [
                    'schedule_id' => $schedule['schedule_id'],
                    'time' => $schedule['show_time'],
                    'round' => $schedule['round_name'],
                    'date' => $schedule['performance_date']
                ];
            }
            
            foreach ($schedules_by_date as $date => $schedules) {
                echo "'$date': " . json_encode($schedules) . ",\n";
            }
            ?>
        };
        
        console.log('Performance schedules:', performanceSchedules); // 디버깅용
        
        // 시간이 지났는지 확인하는 함수
        function isPastTime(dateStr, timeStr) {
            const [year, month, day] = dateStr.split('-').map(Number);
            const [hour, minute] = timeStr.split(':').map(Number);
            
            // 현재 날짜와 비교
            if (year < currentTime.year) return true;
            if (year > currentTime.year) return false;
            
            if (month < currentTime.month) return true;
            if (month > currentTime.month) return false;
            
            if (day < currentTime.date) return true;
            if (day > currentTime.date) return false;
            
            // 같은 날짜인 경우 시간 비교
            if (hour < currentTime.hour) return true;
            if (hour > currentTime.hour) return false;
            
            // 같은 시간인 경우 분 비교 (30분 여유시간 두기)
            return minute <= (currentTime.minute + 30);
        }
        
        function initCalendar() {
            updateCalendarDisplay();
        }
        
        function updateCalendarDisplay() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            // 월 표시 업데이트
            document.getElementById('currentMonth').textContent = `${year}.${String(month + 1).padStart(2, '0')}`;
            
            // 달력 그리드 생성
            const calendarGrid = document.getElementById('calendarGrid');
            calendarGrid.innerHTML = '';
            
            // 요일 헤더
            const dayHeaders = ['일', '월', '화', '수', '목', '금', '토'];
            dayHeaders.forEach(day => {
                const header = document.createElement('div');
                header.className = 'calendar-day-header';
                header.textContent = day;
                calendarGrid.appendChild(header);
            });
            
            // 이번 달 첫날과 마지막날 (한국 시간 기준)
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - firstDay.getDay());
            
            // 달력 칸 생성 (6주)
            for (let i = 0; i < 42; i++) {
                const cellDate = new Date(startDate);
                cellDate.setDate(startDate.getDate() + i);
                
                const day = document.createElement('div');
                day.className = 'calendar-day';
                day.textContent = cellDate.getDate();
                
                // 한국 시간 기준으로 날짜 문자열 생성
                const dateStr = cellDate.getFullYear() + '-' + 
                               String(cellDate.getMonth() + 1).padStart(2, '0') + '-' + 
                               String(cellDate.getDate()).padStart(2, '0');
                
                // 다른 달 날짜 처리
                if (cellDate.getMonth() !== month) {
                    day.classList.add('other-month');
                } else {
                    // 공연이 있는 날짜 체크 (현재 시간 이후의 회차가 있는지 확인)
                    const daySchedules = performanceSchedules[dateStr];
                    if (daySchedules && daySchedules.length > 0) {
                        // 해당 날짜에 예매 가능한 회차가 있는지 확인
                        const hasAvailableShows = daySchedules.some(schedule => 
                            !isPastTime(schedule.date, schedule.time)
                        );
                        
                        if (hasAvailableShows) {
                            day.classList.add('has-performance');
                            day.onclick = () => selectDate(dateStr, cellDate);
                            
                            // 공연 표시 점
                            const indicator = document.createElement('div');
                            indicator.className = 'performance-indicator';
                            day.appendChild(indicator);
                        }
                    }
                }
                
                calendarGrid.appendChild(day);
            }
        }
        
        function changeMonth(direction) {
            currentDate.setMonth(currentDate.getMonth() + direction);
            updateCalendarDisplay();
        }
        
        function selectDate(dateStr, dateObj) {
            // 기존 선택 해제
            document.querySelectorAll('.calendar-day.selected').forEach(el => {
                el.classList.remove('selected');
            });
            
            // 새로운 선택 적용
            event.target.classList.add('selected');
            
            // STEP2 활성화
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.add('active');
            
            // 해당 날짜의 시간대 표시
            const schedules = performanceSchedules[dateStr] || [];
            const timeSlotsContainer = document.getElementById('timeSlots');
            
            if (schedules.length > 0) {
                let timeSlotsHTML = '<div class="time-slots">';
                schedules.forEach(schedule => {
                    const timeFormatted = schedule.time.substring(0, 5); // HH:MM 형식
                    const isPast = isPastTime(schedule.date, schedule.time);
                    const slotClass = isPast ? 'time-slot past' : 'time-slot';
                    const onclick = isPast ? '' : `onclick="selectTimeSlot(${schedule.schedule_id}, '${dateStr}', '${timeFormatted}', '${schedule.round}')"`;
                    
                    timeSlotsHTML += `
                        <div class="${slotClass}" ${onclick}>
                            <div class="time-slot-time">${timeFormatted}</div>
                            <div class="time-slot-round">${schedule.round}</div>
                            ${isPast ? '<div class="time-slot-status">예매 마감</div>' : ''}
                        </div>
                    `;
                });
                timeSlotsHTML += '</div>';
                timeSlotsContainer.innerHTML = timeSlotsHTML;
            } else {
                timeSlotsContainer.innerHTML = '<div class="step2-placeholder">해당 날짜에 예매 가능한 공연이 없습니다.</div>';
            }
        }
        
        function selectTimeSlot(scheduleId, dateStr, time, round) {
            // 기존 선택 해제
            document.querySelectorAll('.time-slot.selected').forEach(el => {
                el.classList.remove('selected');
            });
            
            // 새로운 선택 적용
            event.target.classList.add('selected');
            
            selectedScheduleId = scheduleId;
            
            // 선택 정보 표시
            const bookingArea = document.getElementById('bookingArea');
            const selectedDate = document.getElementById('selectedDate');
            const selectedTime = document.getElementById('selectedTime');
            
            // 한국 시간 기준으로 날짜 파싱
            const dateParts = dateStr.split('-');
            const date = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2]));
            const formattedDate = `${date.getFullYear()}년 ${date.getMonth() + 1}월 ${date.getDate()}일 (${['일', '월', '화', '수', '목', '금', '토'][date.getDay()]})`;
            
            selectedDate.textContent = formattedDate;
            selectedTime.textContent = `${time} ${round}`;
            bookingArea.style.display = 'block';
            
            // 예매 버튼 텍스트 업데이트
            const bookingBtn = document.getElementById('bookingButton');
            if (bookingBtn) {
                bookingBtn.textContent = '🎫 선택한 회차로 예매하기';
                bookingBtn.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            }
        }

        function startBooking() {
            if (!selectedScheduleId) {
                alert('예매할 공연 일정을 먼저 선택해주세요.');
                return;
            }
            
            location.href = `seat_selection.php?schedule_id=${selectedScheduleId}&performance_id=<?php echo $performance_id; ?>`;
        }
        
        // 페이지 로드 시 달력 초기화
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing calendar...');
            
            // 현재 예매 가능한 가장 빠른 날짜로 달력 초기화
            const scheduleKeys = Object.keys(performanceSchedules);
            if (scheduleKeys.length > 0) {
                // 가장 빠른 공연 날짜 찾기
                const earliestDate = scheduleKeys.sort()[0];
                const dateParts = earliestDate.split('-');
                currentDate = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, 1);
                console.log('Setting calendar to earliest performance month:', earliestDate);
            } else {
                // 공연 시작 날짜로 대체
                const performanceStart = '<?php echo $performance['performance_start_date']; ?>';
                if (performanceStart) {
                    const startDateParts = performanceStart.split('-');
                    currentDate = new Date(parseInt(startDateParts[0]), parseInt(startDateParts[1]) - 1, 1);
                }
            }
            
            initCalendar();
        });
    </script>

    <?php
    mysqli_stmt_close($stmt);
    mysqli_stmt_close($schedule_stmt);
    mysqli_close($connect);
    ?>
</body>
</html>