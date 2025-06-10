<?php
include './dbconn.php';
session_start();

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_staff']) || !$_SESSION['is_staff']) {
    header("Location: home.php");
    exit();
}

$error_message = "";
$success_message = "";

// 공연장 목록 조회
$venues_query = "SELECT venue_id, venue_name, location FROM venues ORDER BY venue_name";
$venues_result = mysqli_query($connect, $venues_query);

// 폼 제출 처리
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 기본 정보
    $title = trim($_POST['title']);
    $genre = $_POST['genre'];
    $venue_id = $_POST['venue_id'];
    $description = trim($_POST['description']);
    $poster_image = trim($_POST['poster_image']);
    
    // 공연 기간
    $performance_start_date = $_POST['performance_start_date'];
    $performance_end_date = $_POST['performance_end_date'];
    
    // 예매 기간
    $booking_start_date = $_POST['booking_start_date'] . ' ' . $_POST['booking_start_time'];
    $booking_end_date = $_POST['booking_end_date'] . ' ' . $_POST['booking_end_time'];
    
    // 좌석 구성
    $vip_floor = $_POST['vip_floor'] ?? 1;
    $vip_seats = $_POST['vip_seats'] ?? 0;
    $vip_price = $_POST['vip_price'] ?? 0;
    
    $r_floor = $_POST['r_floor'] ?? 1;
    $r_seats = $_POST['r_seats'] ?? 0;
    $r_price = $_POST['r_price'] ?? 0;
    
    $s_floor = $_POST['s_floor'] ?? 2;
    $s_seats = $_POST['s_seats'] ?? 0;
    $s_price = $_POST['s_price'] ?? 0;
    
    $status = $_POST['status'];
    
    // 회차 정보
    $schedules = [];
    if (isset($_POST['schedule_date'])) {
        for ($i = 0; $i < count($_POST['schedule_date']); $i++) {
            if (!empty($_POST['schedule_date'][$i]) && !empty($_POST['schedule_time'][$i])) {
                $schedules[] = [
                    'date' => $_POST['schedule_date'][$i],
                    'time' => $_POST['schedule_time'][$i],
                    'round_name' => trim($_POST['schedule_round'][$i])
                ];
            }
        }
    }
    
    // 유효성 검사
    if (empty($title)) {
        $error_message = "공연명을 입력해주세요.";
    } elseif (empty($genre)) {
        $error_message = "장르를 선택해주세요.";
    } elseif (empty($venue_id)) {
        $error_message = "공연장을 선택해주세요.";
    } elseif (empty($performance_start_date) || empty($performance_end_date)) {
        $error_message = "공연 기간을 입력해주세요.";
    } elseif (empty($booking_start_date) || empty($booking_end_date)) {
        $error_message = "예매 기간을 입력해주세요.";
    } elseif (strtotime($performance_start_date) > strtotime($performance_end_date)) {
        $error_message = "공연 종료일은 시작일보다 늦어야 합니다.";
    } elseif (strtotime($booking_start_date) > strtotime($booking_end_date)) {
        $error_message = "예매 종료일은 시작일보다 늦어야 합니다.";
    } elseif (strtotime($booking_end_date) > strtotime($performance_end_date)) {
        $error_message = "예매 종료일은 공연 종료일일 이전이어야 합니다.";
    } elseif ($vip_seats == 0 && $r_seats == 0 && $s_seats == 0) {
        $error_message = "최소 하나 이상의 좌석을 설정해주세요.";
    } elseif (empty($schedules)) {
        $error_message = "최소 하나 이상의 공연 회차를 추가해주세요.";
    } else {
        // 트랜잭션 시작
        mysqli_begin_transaction($connect);
        
        try {
            // 공연 정보 저장
            $insert_performance = "
                INSERT INTO performances (
                    title, genre, venue_id, description, poster_image,
                    performance_start_date, performance_end_date,
                    vip_floor, vip_seats, vip_price,
                    r_floor, r_seats, r_price,
                    s_floor, s_seats, s_price,
                    booking_start_date, booking_end_date, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $perf_stmt = mysqli_prepare($connect, $insert_performance);
            mysqli_stmt_bind_param($perf_stmt, "ssissssiiiiiiiiisss", 
                $title, $genre, $venue_id, $description, $poster_image,
                $performance_start_date, $performance_end_date,
                $vip_floor, $vip_seats, $vip_price,
                $r_floor, $r_seats, $r_price,
                $s_floor, $s_seats, $s_price,
                $booking_start_date, $booking_end_date, $status
            );
            
            if (!mysqli_stmt_execute($perf_stmt)) {
                throw new Exception("공연 정보 저장 중 오류가 발생했습니다.");
            }
            
            $performance_id = mysqli_insert_id($connect);
            mysqli_stmt_close($perf_stmt);
            
            // 공연 회차 저장
            $insert_schedule = "
                INSERT INTO performance_schedules (performance_id, performance_date, show_time, round_name)
                VALUES (?, ?, ?, ?)
            ";
            $schedule_stmt = mysqli_prepare($connect, $insert_schedule);
            
            foreach ($schedules as $schedule) {
                mysqli_stmt_bind_param($schedule_stmt, "isss", 
                    $performance_id, 
                    $schedule['date'], 
                    $schedule['time'], 
                    $schedule['round_name']
                );
                
                if (!mysqli_stmt_execute($schedule_stmt)) {
                    throw new Exception("공연 회차 저장 중 오류가 발생했습니다.");
                }
            }
            
            mysqli_stmt_close($schedule_stmt);
            mysqli_commit($connect);
            
            $success_message = "공연이 성공적으로 등록되었습니다!";
            
            // 성공 시 관리 페이지로 리다이렉트 (3초 후)
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'admin_performances.php';
                }, 3000);
            </script>";
            
        } catch (Exception $e) {
            mysqli_rollback($connect);
            $error_message = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>새 공연 등록 - ShowTicket Admin</title>
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
            background-color: #f8f9fa;
        }
        
        .header {
            background-color: white;
            color: #333;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 1px solid #eee;
        }
        
        .nav-container {
            max-width: 1400px;
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
        
        .admin-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
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
        
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            overflow: hidden;
        }
        
        .form-section {
            padding: 2rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
            display: inline-block;
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
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .input-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .seat-config {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .seat-type {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: border-color 0.3s;
        }
        
        .seat-type:hover {
            border-color: #667eea;
        }
        
        .seat-type-title {
            font-weight: bold;
            margin-bottom: 1rem;
            text-align: center;
            padding: 0.5rem;
            border-radius: 5px;
            color: white;
        }
        
        .vip-type .seat-type-title {
            background: linear-gradient(135deg, #ffd700, #ffed4a);
            color: #333;
        }
        
        .r-type .seat-type-title {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
        }
        
        .s-type .seat-type-title {
            background: linear-gradient(135deg, #4ecdc4, #00d2d3);
        }
        
        .schedules-container {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            background: #f8f9fa;
        }
        
        .schedule-item {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .schedule-item:last-child {
            margin-bottom: 0;
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
            font-size: 1.1rem;
            padding: 1rem 2rem;
        }
        
        .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            padding: 2rem;
            background: #f8f9fa;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
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
        
        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.3rem;
        }
        
        .schedule-method-selector {
            margin-bottom: 2rem;
        }
        
        .method-tabs {
            display: flex;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.3rem;
            gap: 0.3rem;
        }
        
        .method-tab {
            flex: 1;
            padding: 0.8rem 1rem;
            background: transparent;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            color: #666;
        }
        
        .method-tab.active {
            background: #667eea;
            color: white;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .schedule-mode {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
        }
        
        .auto-schedule-container {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
        }
        
        .weekdays-container {
            margin: 1.5rem 0;
        }
        
        .weekdays-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
        }
        
        .weekday-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
        }
        
        .weekday-checkbox {
            display: none;
        }
        
        .weekday-label {
            width: 40px;
            height: 40px;
            border: 2px solid #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.3s;
            background: white;
            color: #666;
        }
        
        .weekday-label.sunday {
            color: #dc3545;
        }
        
        .weekday-label.saturday {
            color: #0066cc;
        }
        
        .weekday-checkbox:checked + .weekday-label {
            background: #667eea;
            border-color: #667eea;
            color: white;
            transform: scale(1.1);
        }
        
        .time-slots-container {
            margin: 1.5rem 0;
        }
        
        .time-slot-item {
            display: grid;
            grid-template-columns: 1fr 2fr auto;
            gap: 1rem;
            align-items: center;
            margin-bottom: 0.5rem;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .time-input, .round-input {
            margin: 0;
        }
        
        .schedule-preview {
            background: linear-gradient(135deg, #f0f4ff 0%, #e8f2ff 100%);
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .preview-content {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .preview-item {
            background: white;
            padding: 0.8rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .preview-date {
            font-weight: bold;
            color: #333;
        }
        
        .preview-time {
            color: #667eea;
        }
        
        .preview-round {
            color: #666;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .seat-config {
                grid-template-columns: 1fr;
            }
            
            .schedule-item {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- 헤더 -->
    <header class="header">
        <div class="nav-container">
            <a href="home.php" class="logo">🎭 ShowTicket<span class="admin-badge">ADMIN</span></a>
            
            <div style="display: flex; gap: 1rem; align-items: center;">
                <a href="admin_performances.php" style="color: #667eea; text-decoration: none;">← 공연 관리로 돌아가기</a>
                <span style="color: #333;">관리자 <?php echo htmlspecialchars($_SESSION['name']); ?>님</span>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- 페이지 헤더 -->
        <div class="page-header">
            <h1 class="page-title">🎪 새 공연 등록</h1>
            <p class="page-subtitle">새로운 공연 정보를 등록하고 관리하세요</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
                <br><small>3초 후 공연 관리 페이지로 이동합니다...</small>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="performanceForm">
            <div class="form-container">
                <!-- 기본 정보 -->
                <div class="form-section">
                    <h2 class="section-title">📋 기본 정보</h2>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">공연명 <span class="required">*</span></label>
                            <input type="text" name="title" class="form-control" 
                                   placeholder="공연 제목을 입력하세요" required
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">장르 <span class="required">*</span></label>
                            <select name="genre" class="form-control" required>
                                <option value="">장르 선택</option>
                                <option value="뮤지컬" <?php echo ($_POST['genre'] ?? '') == '뮤지컬' ? 'selected' : ''; ?>>뮤지컬</option>
                                <option value="연극" <?php echo ($_POST['genre'] ?? '') == '연극' ? 'selected' : ''; ?>>연극</option>
                                <option value="콘서트" <?php echo ($_POST['genre'] ?? '') == '콘서트' ? 'selected' : ''; ?>>콘서트</option>
                                <option value="오페라" <?php echo ($_POST['genre'] ?? '') == '오페라' ? 'selected' : ''; ?>>오페라</option>
                                <option value="발레" <?php echo ($_POST['genre'] ?? '') == '발레' ? 'selected' : ''; ?>>발레</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">공연장 <span class="required">*</span></label>
                            <select name="venue_id" class="form-control" required>
                                <option value="">공연장 선택</option>
                                <?php while ($venue = mysqli_fetch_assoc($venues_result)): ?>
                                    <option value="<?php echo $venue['venue_id']; ?>" 
                                            <?php echo ($_POST['venue_id'] ?? '') == $venue['venue_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($venue['venue_name']); ?> 
                                        (<?php echo htmlspecialchars($venue['location']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">공연 설명</label>
                            <textarea name="description" class="form-control" 
                                      placeholder="공연에 대한 상세한 설명을 입력하세요"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">포스터 이미지 URL</label>
                            <input type="url" name="poster_image" class="form-control" 
                                   placeholder="http://example.com/poster.jpg"
                                   value="<?php echo htmlspecialchars($_POST['poster_image'] ?? ''); ?>">
                            <div class="help-text">포스터 이미지의 URL을 입력하세요 (선택사항)</div>
                        </div>
                    </div>
                </div>

                <!-- 공연 기간 -->
                <div class="form-section">
                    <h2 class="section-title">📅 공연 기간</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">공연 시작일 <span class="required">*</span></label>
                            <input type="date" name="performance_start_date" class="form-control" required
                                   value="<?php echo $_POST['performance_start_date'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">공연 종료일 <span class="required">*</span></label>
                            <input type="date" name="performance_end_date" class="form-control" required
                                   value="<?php echo $_POST['performance_end_date'] ?? ''; ?>">
                        </div>
                    </div>
                </div>

                <!-- 예매 기간 -->
                <div class="form-section">
                    <h2 class="section-title">🎫 예매 기간</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">예매 시작 <span class="required">*</span></label>
                            <div class="input-group">
                                <input type="date" name="booking_start_date" class="form-control" required
                                       value="<?php echo $_POST['booking_start_date'] ?? ''; ?>">
                                <input type="time" name="booking_start_time" class="form-control" required
                                       value="<?php echo $_POST['booking_start_time'] ?? '09:00'; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">예매 종료 <span class="required">*</span></label>
                            <div class="input-group">
                                <input type="date" name="booking_end_date" class="form-control" required
                                       value="<?php echo $_POST['booking_end_date'] ?? ''; ?>">
                                <input type="time" name="booking_end_time" class="form-control" required
                                       value="<?php echo $_POST['booking_end_time'] ?? '23:59'; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 좌석 구성 -->
                <div class="form-section">
                    <h2 class="section-title">🪑 좌석 구성</h2>
                    <div class="seat-config">
                        <!-- VIP석 -->
                        <div class="seat-type vip-type">
                            <div class="seat-type-title">VIP석</div>
                            <div class="form-group">
                                <label class="form-label">층수</label>
                                <select name="vip_floor" class="form-control">
                                    <option value="1" <?php echo ($_POST['vip_floor'] ?? 1) == 1 ? 'selected' : ''; ?>>1층</option>
                                    <option value="2" <?php echo ($_POST['vip_floor'] ?? 1) == 2 ? 'selected' : ''; ?>>2층</option>
                                    <option value="3" <?php echo ($_POST['vip_floor'] ?? 1) == 3 ? 'selected' : ''; ?>>3층</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">좌석 수</label>
                                <input type="number" name="vip_seats" class="form-control" min="0" 
                                       value="<?php echo $_POST['vip_seats'] ?? 0; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">가격 (원)</label>
                                <input type="number" name="vip_price" class="form-control" min="0" step="1000"
                                       value="<?php echo $_POST['vip_price'] ?? 0; ?>">
                            </div>
                        </div>

                        <!-- R석 -->
                        <div class="seat-type r-type">
                            <div class="seat-type-title">R석</div>
                            <div class="form-group">
                                <label class="form-label">층수</label>
                                <select name="r_floor" class="form-control">
                                    <option value="1" <?php echo ($_POST['r_floor'] ?? 1) == 1 ? 'selected' : ''; ?>>1층</option>
                                    <option value="2" <?php echo ($_POST['r_floor'] ?? 1) == 2 ? 'selected' : ''; ?>>2층</option>
                                    <option value="3" <?php echo ($_POST['r_floor'] ?? 1) == 3 ? 'selected' : ''; ?>>3층</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">좌석 수</label>
                                <input type="number" name="r_seats" class="form-control" min="0"
                                       value="<?php echo $_POST['r_seats'] ?? 0; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">가격 (원)</label>
                                <input type="number" name="r_price" class="form-control" min="0" step="1000"
                                       value="<?php echo $_POST['r_price'] ?? 0; ?>">
                            </div>
                        </div>

                        <!-- S석 -->
                        <div class="seat-type s-type">
                            <div class="seat-type-title">S석</div>
                            <div class="form-group">
                                <label class="form-label">층수</label>
                                <select name="s_floor" class="form-control">
                                    <option value="1" <?php echo ($_POST['s_floor'] ?? 2) == 1 ? 'selected' : ''; ?>>1층</option>
                                    <option value="2" <?php echo ($_POST['s_floor'] ?? 2) == 2 ? 'selected' : ''; ?>>2층</option>
                                    <option value="3" <?php echo ($_POST['s_floor'] ?? 2) == 3 ? 'selected' : ''; ?>>3층</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">좌석 수</label>
                                <input type="number" name="s_seats" class="form-control" min="0"
                                       value="<?php echo $_POST['s_seats'] ?? 0; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">가격 (원)</label>
                                <input type="number" name="s_price" class="form-control" min="0" step="1000"
                                       value="<?php echo $_POST['s_price'] ?? 0; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="help-text" style="margin-top: 1rem; text-align: center;">
                        💡 최소 하나 이상의 좌석 타입은 설정해야 합니다
                    </div>
                </div>

                <!-- 공연 회차 -->
                <div class="form-section">
                    <h2 class="section-title">🎬 공연 회차</h2>
                    
                    <!-- 회차 생성 방식 선택 -->
                    <div class="schedule-method-selector">
                        <div class="method-tabs">
                            <button type="button" class="method-tab active" onclick="switchMethod('auto')">📅 자동 생성</button>
                            <button type="button" class="method-tab" onclick="switchMethod('manual')">➕ 수동 추가</button>
                        </div>
                    </div>

                    <!-- 자동 생성 모드 -->
                    <div id="autoScheduleMode" class="schedule-mode">
                        <div class="auto-schedule-container">
                            <h3 style="margin-bottom: 1rem; color: #667eea;">📅 기간 및 요일 선택으로 일괄 생성</h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">회차 시작일</label>
                                    <input type="date" id="scheduleStartDate" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">회차 종료일</label>
                                    <input type="date" id="scheduleEndDate" class="form-control">
                                </div>
                            </div>

                            <!-- 요일 선택 -->
                            <div class="weekdays-container">
                                <h4 style="margin-bottom: 1rem;">공연 요일 선택</h4>
                                <div class="weekdays-grid">
                                    <label class="weekday-item">
                                        <input type="checkbox" value="0" class="weekday-checkbox">
                                        <span class="weekday-label sunday">일</span>
                                    </label>
                                    <label class="weekday-item">
                                        <input type="checkbox" value="1" class="weekday-checkbox">
                                        <span class="weekday-label">월</span>
                                    </label>
                                    <label class="weekday-item">
                                        <input type="checkbox" value="2" class="weekday-checkbox">
                                        <span class="weekday-label">화</span>
                                    </label>
                                    <label class="weekday-item">
                                        <input type="checkbox" value="3" class="weekday-checkbox">
                                        <span class="weekday-label">수</span>
                                    </label>
                                    <label class="weekday-item">
                                        <input type="checkbox" value="4" class="weekday-checkbox">
                                        <span class="weekday-label">목</span>
                                    </label>
                                    <label class="weekday-item">
                                        <input type="checkbox" value="5" class="weekday-checkbox">
                                        <span class="weekday-label">금</span>
                                    </label>
                                    <label class="weekday-item">
                                        <input type="checkbox" value="6" class="weekday-checkbox">
                                        <span class="weekday-label saturday">토</span>
                                    </label>
                                </div>
                            </div>

                            <!-- 시간 설정 -->
                            <div class="time-slots-container">
                                <h4 style="margin-bottom: 1rem;">공연 시간 설정</h4>
                                <div id="timeSlotsList">
                                    <div class="time-slot-item">
                                        <input type="time" class="form-control time-input" value="19:30">
                                        <input type="text" class="form-control round-input" placeholder="회차명" value="저녁공연">
                                        <button type="button" class="btn btn-danger btn-small" onclick="removeTimeSlot(this)">삭제</button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-success btn-small" onclick="addTimeSlot()">+ 시간 추가</button>
                            </div>

                            <div style="text-align: center; margin-top: 2rem;">
                                <button type="button" class="btn btn-primary" onclick="generateSchedules()">🎭 회차 자동 생성</button>
                            </div>
                        </div>
                    </div>

                    <!-- 수동 추가 모드 -->
                    <div id="manualScheduleMode" class="schedule-mode" style="display: none;">
                        <div class="schedules-container">
                            <div id="schedulesContainer">
                                <!-- 첫 번째 회차 (기본) -->
                                <div class="schedule-item">
                                    <div class="form-group">
                                        <label class="form-label">공연일</label>
                                        <input type="date" name="schedule_date[]" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">공연시간</label>
                                        <input type="time" name="schedule_time[]" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">회차명</label>
                                        <input type="text" name="schedule_round[]" class="form-control" 
                                               placeholder="예: 1회차, 오후공연" value="1회차">
                                    </div>
                                    <div class="form-group">
                                        <button type="button" class="btn btn-danger btn-small" onclick="removeSchedule(this)">삭제</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="text-align: center; margin-top: 1rem;">
                                <button type="button" class="btn btn-success" onclick="addSchedule()">+ 회차 추가</button>
                            </div>
                        </div>
                    </div>

                    <!-- 생성된 회차 미리보기 -->
                    <div id="schedulePreview" class="schedule-preview" style="display: none;">
                        <h4 style="margin-bottom: 1rem; color: #667eea;">📋 생성된 회차 미리보기</h4>
                        <div class="preview-content">
                            <div id="previewList"></div>
                            <div style="text-align: center; margin-top: 1rem;">
                                <button type="button" class="btn btn-secondary" onclick="editSchedules()">수정</button>
                                <button type="button" class="btn btn-success" onclick="confirmSchedules()">확정</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="help-text" style="margin-top: 1rem;">
                        💡 자동 생성: 공연 기간과 요일을 선택하여 여러 회차를 한번에 생성<br>
                        💡 수동 추가: 개별 회차를 하나씩 직접 추가
                    </div>
                </div>

                <!-- 공연 상태 -->
                <div class="form-section">
                    <h2 class="section-title">⚙️ 공연 상태</h2>
                    <div class="form-group">
                        <label class="form-label">상태 <span class="required">*</span></label>
                        <select name="status" class="form-control" required>
                            <option value="upcoming" <?php echo ($_POST['status'] ?? 'upcoming') == 'upcoming' ? 'selected' : ''; ?>>예매 예정</option>
                            <option value="booking" <?php echo ($_POST['status'] ?? '') == 'booking' ? 'selected' : ''; ?>>예매중</option>
                            <option value="closed" <?php echo ($_POST['status'] ?? '') == 'closed' ? 'selected' : ''; ?>>예매 마감</option>
                            <option value="finished" <?php echo ($_POST['status'] ?? '') == 'finished' ? 'selected' : ''; ?>>공연 종료</option>
                        </select>
                        <div class="help-text">
                            일반적으로 새 공연은 "예매 예정" 상태로 등록합니다
                        </div>
                    </div>
                </div>
            </div>

            <!-- 폼 액션 -->
            <div class="form-actions">
                <a href="admin_performances.php" class="btn btn-secondary">취소</a>
                <button type="submit" class="btn btn-primary">🎪 공연 등록</button>
            </div>
        </form>
    </div>

    <script>
        let scheduleCount = 1;
        let generatedSchedules = [];

        // 회차 생성 방식 전환
        function switchMethod(method) {
            // 탭 활성화 상태 변경
            document.querySelectorAll('.method-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelector(`[onclick="switchMethod('${method}')"]`).classList.add('active');

            // 모드 전환
            if (method === 'auto') {
                document.getElementById('autoScheduleMode').style.display = 'block';
                document.getElementById('manualScheduleMode').style.display = 'none';
            } else {
                document.getElementById('autoScheduleMode').style.display = 'none';
                document.getElementById('manualScheduleMode').style.display = 'block';
            }
            
            // 미리보기 숨기기
            document.getElementById('schedulePreview').style.display = 'none';
        }

        // 시간 슬롯 추가
        function addTimeSlot() {
            const container = document.getElementById('timeSlotsList');
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot-item';
            timeSlot.innerHTML = `
                <input type="time" class="form-control time-input" value="14:00">
                <input type="text" class="form-control round-input" placeholder="회차명" value="오후공연">
                <button type="button" class="btn btn-danger btn-small" onclick="removeTimeSlot(this)">삭제</button>
            `;
            container.appendChild(timeSlot);
        }

        // 시간 슬롯 삭제
        function removeTimeSlot(button) {
            const timeSlots = document.querySelectorAll('.time-slot-item');
            if (timeSlots.length > 1) {
                button.closest('.time-slot-item').remove();
            } else {
                alert('최소 하나의 공연 시간은 있어야 합니다.');
            }
        }

        // 회차 자동 생성
        function generateSchedules() {
            const startDate = document.getElementById('scheduleStartDate').value;
            const endDate = document.getElementById('scheduleEndDate').value;
            
            if (!startDate || !endDate) {
                alert('회차 기간을 선택해주세요.');
                return;
            }

            // 선택된 요일 확인
            const selectedWeekdays = [];
            document.querySelectorAll('.weekday-checkbox:checked').forEach(checkbox => {
                selectedWeekdays.push(parseInt(checkbox.value));
            });

            if (selectedWeekdays.length === 0) {
                alert('공연 요일을 하나 이상 선택해주세요.');
                return;
            }

            // 시간 정보 수집
            const timeSlots = [];
            document.querySelectorAll('.time-slot-item').forEach(item => {
                const time = item.querySelector('.time-input').value;
                const round = item.querySelector('.round-input').value;
                if (time && round) {
                    timeSlots.push({ time, round });
                }
            });

            if (timeSlots.length === 0) {
                alert('공연 시간을 하나 이상 설정해주세요.');
                return;
            }

            // 날짜 범위 내에서 선택된 요일에 해당하는 날짜들 찾기
            generatedSchedules = [];
            const start = new Date(startDate);
            const end = new Date(endDate);
            
            for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
                const weekday = date.getDay();
                if (selectedWeekdays.includes(weekday)) {
                    const dateStr = date.toISOString().split('T')[0];
                    timeSlots.forEach(slot => {
                        generatedSchedules.push({
                            date: dateStr,
                            time: slot.time,
                            round: slot.round
                        });
                    });
                }
            }

            if (generatedSchedules.length === 0) {
                alert('선택한 조건에 해당하는 공연 날짜가 없습니다.');
                return;
            }

            // 미리보기 표시
            displaySchedulePreview();
        }

        // 미리보기 표시
        function displaySchedulePreview() {
            const previewList = document.getElementById('previewList');
            previewList.innerHTML = '';

            generatedSchedules.forEach((schedule, index) => {
                const date = new Date(schedule.date);
                const weekdays = ['일', '월', '화', '수', '목', '금', '토'];
                const dateStr = `${date.getMonth() + 1}월 ${date.getDate()}일 (${weekdays[date.getDay()]})`;
                
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.innerHTML = `
                    <div>
                        <span class="preview-date">${dateStr}</span>
                        <span class="preview-time">${schedule.time}</span>
                        <span class="preview-round">${schedule.round}</span>
                    </div>
                    <button type="button" class="btn btn-danger btn-small" onclick="removeGeneratedSchedule(${index})">삭제</button>
                `;
                previewList.appendChild(previewItem);
            });

            document.getElementById('schedulePreview').style.display = 'block';
        }

        // 생성된 회차 삭제
        function removeGeneratedSchedule(index) {
            generatedSchedules.splice(index, 1);
            displaySchedulePreview();
        }

        // 회차 수정 (다시 설정으로 돌아가기)
        function editSchedules() {
            document.getElementById('schedulePreview').style.display = 'none';
        }

        // 회차 확정 (hidden input으로 변환)
        function confirmSchedules() {
            // 수동 모드로 전환하고 기존 필드들 제거
            switchMethod('manual');
            
            const container = document.getElementById('schedulesContainer');
            container.innerHTML = '';

            // 생성된 회차들을 실제 폼 필드로 추가
            generatedSchedules.forEach((schedule, index) => {
                const scheduleItem = document.createElement('div');
                scheduleItem.className = 'schedule-item';
                scheduleItem.innerHTML = `
                    <div class="form-group">
                        <label class="form-label">공연일</label>
                        <input type="date" name="schedule_date[]" class="form-control" value="${schedule.date}" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">공연시간</label>
                        <input type="time" name="schedule_time[]" class="form-control" value="${schedule.time}" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">회차명</label>
                        <input type="text" name="schedule_round[]" class="form-control" value="${schedule.round}" readonly>
                    </div>
                    <div class="form-group">
                        <span class="btn btn-secondary btn-small" style="opacity: 0.6;">자동생성</span>
                    </div>
                `;
                container.appendChild(scheduleItem);
            });

            alert(`${generatedSchedules.length}개의 회차가 확정되었습니다.`);
            document.getElementById('schedulePreview').style.display = 'none';
            
            // 수동 추가 버튼도 숨기기 (자동 생성된 회차는 수정 불가)
            const addButton = document.querySelector('[onclick="addSchedule()"]');
            if (addButton) {
                addButton.style.display = 'none';
            }
        }

        // 수동 회차 추가 (기존 함수)
        function addSchedule() {
            scheduleCount++;
            const container = document.getElementById('schedulesContainer');
            const scheduleItem = document.createElement('div');
            scheduleItem.className = 'schedule-item';
            scheduleItem.innerHTML = `
                <div class="form-group">
                    <label class="form-label">공연일</label>
                    <input type="date" name="schedule_date[]" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">공연시간</label>
                    <input type="time" name="schedule_time[]" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">회차명</label>
                    <input type="text" name="schedule_round[]" class="form-control" 
                           placeholder="예: ${scheduleCount}회차, 저녁공연" value="${scheduleCount}회차">
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-danger btn-small" onclick="removeSchedule(this)">삭제</button>
                </div>
            `;
            container.appendChild(scheduleItem);
        }

        // 수동 회차 삭제 (기존 함수)
        function removeSchedule(button) {
            const scheduleItems = document.querySelectorAll('.schedule-item');
            if (scheduleItems.length > 1) {
                button.closest('.schedule-item').remove();
            } else {
                alert('최소 하나의 회차는 있어야 합니다.');
            }
        }

        // 폼 유효성 검사
        document.getElementById('performanceForm').addEventListener('submit', function(e) {
            // 좌석 수 검사
            const vipSeats = parseInt(document.querySelector('input[name="vip_seats"]').value) || 0;
            const rSeats = parseInt(document.querySelector('input[name="r_seats"]').value) || 0;
            const sSeats = parseInt(document.querySelector('input[name="s_seats"]').value) || 0;
            
            if (vipSeats === 0 && rSeats === 0 && sSeats === 0) {
                e.preventDefault();
                alert('최소 하나 이상의 좌석 타입은 설정해야 합니다.');
                return;
            }

            // 좌석 수가 있으면 가격도 있어야 함
            const vipPrice = parseInt(document.querySelector('input[name="vip_price"]').value) || 0;
            const rPrice = parseInt(document.querySelector('input[name="r_price"]').value) || 0;
            const sPrice = parseInt(document.querySelector('input[name="s_price"]').value) || 0;

            if ((vipSeats > 0 && vipPrice === 0) || 
                (rSeats > 0 && rPrice === 0) || 
                (sSeats > 0 && sPrice === 0)) {
                e.preventDefault();
                alert('좌석이 있는 타입은 가격을 설정해야 합니다.');
                return;
            }

            // 회차 확인 (자동 생성된 경우와 수동 입력 경우 모두 확인)
            const scheduleDates = document.querySelectorAll('input[name="schedule_date[]"]');
            if (scheduleDates.length === 0) {
                e.preventDefault();
                alert('최소 하나 이상의 공연 회차를 추가해주세요.');
                return;
            }

            // 날짜 유효성 검사
            const performanceStartInput = document.querySelector('input[name="performance_start_date"]');
            const performanceEndInput = document.querySelector('input[name="performance_end_date"]');
            const bookingStartDateInput = document.querySelector('input[name="booking_start_date"]');
            const bookingStartTimeInput = document.querySelector('input[name="booking_start_time"]');
            const bookingEndDateInput = document.querySelector('input[name="booking_end_date"]');
            const bookingEndTimeInput = document.querySelector('input[name="booking_end_time"]');

            if (!performanceStartInput || !performanceEndInput || 
                !bookingStartDateInput || !bookingStartTimeInput ||
                !bookingEndDateInput || !bookingEndTimeInput) {
                e.preventDefault();
                alert('필수 날짜 정보를 모두 입력해주세요.');
                return;
            }

            const performanceStart = new Date(performanceStartInput.value);
            const performanceEnd = new Date(performanceEndInput.value);
            const bookingStart = new Date(bookingStartDateInput.value + 'T' + bookingStartTimeInput.value);
            const bookingEnd = new Date(bookingEndDateInput.value + 'T' + bookingEndTimeInput.value);

            if (performanceStart >= performanceEnd) {
                e.preventDefault();
                alert('공연 종료일은 시작일보다 늦어야 합니다.');
                return;
            }

            if (bookingStart >= bookingEnd) {
                e.preventDefault();
                alert('예매 종료일시는 시작일시보다 늦어야 합니다.');
                return;
            }

            if (bookingEnd >= performanceEnd) {
                e.preventDefault();
                alert('예매 종료일시는 공연 종료일 이전이어야 합니다.');
                return;
            }

            // 회차 날짜 검사
            let hasValidSchedule = false;
            scheduleDates.forEach(input => {
                if (input.value) {
                    const scheduleDate = new Date(input.value);
                    if (scheduleDate >= performanceStart && scheduleDate <= performanceEnd) {
                        hasValidSchedule = true;
                    }
                }
            });

            if (!hasValidSchedule) {
                e.preventDefault();
                alert('공연 회차는 공연 기간 내의 날짜여야 합니다.');
                return;
            }

            // 최종 확인
            const confirmed = confirm('공연을 등록하시겠습니까?');
            if (!confirmed) {
                e.preventDefault();
            }
        });

        // 날짜 입력 시 자동으로 회차 날짜 범위 설정
        document.querySelector('input[name="performance_start_date"]').addEventListener('change', function() {
            const startDate = this.value;
            // 자동 생성 모드의 날짜 범위 설정
            document.getElementById('scheduleStartDate').min = startDate;
            document.getElementById('scheduleStartDate').value = startDate;
            
            // 수동 입력 모드의 날짜 범위 설정
            document.querySelectorAll('input[name="schedule_date[]"]').forEach(input => {
                input.min = startDate;
            });
        });

        document.querySelector('input[name="performance_end_date"]').addEventListener('change', function() {
            const endDate = this.value;
            // 자동 생성 모드의 날짜 범위 설정
            document.getElementById('scheduleEndDate').max = endDate;
            document.getElementById('scheduleEndDate').value = endDate;
            
            // 수동 입력 모드의 날짜 범위 설정
            document.querySelectorAll('input[name="schedule_date[]"]').forEach(input => {
                input.max = endDate;
            });
        });

        // 초기 날짜 제한 설정
        window.addEventListener('load', function() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="performance_start_date"]').min = today;
            document.querySelector('input[name="performance_end_date"]').min = today;
            document.querySelector('input[name="booking_start_date"]').min = today;
            document.querySelector('input[name="booking_end_date"]').min = today;
        });
    </script>

    <?php mysqli_close($connect); ?>
</body>
</html>