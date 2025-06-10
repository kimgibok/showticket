<?php
include './dbconn.php';
session_start();

// 로그인 체크
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// POST 데이터 받기
$schedule_id = $_POST['schedule_id'] ?? 0;
$performance_id = $_POST['performance_id'] ?? 0;
$total_price = $_POST['total_price'] ?? 0;
$seat_count = $_POST['seat_count'] ?? 0;

// 좌석 정보 받기
$vip_seats = $_POST['vip_seats'] ?? '';
$r_seats = $_POST['r_seats'] ?? '';
$s_seats = $_POST['s_seats'] ?? '';

if (!$schedule_id || !$performance_id || !$total_price || !$seat_count) {
    header("Location: home.php");
    exit();
}

// 공연 정보 조회
$perf_query = "
    SELECT p.title, p.genre, p.vip_price, p.r_price, p.s_price, 
           v.venue_name, ps.performance_date, ps.show_time, ps.round_name
    FROM performances p
    JOIN venues v ON p.venue_id = v.venue_id  
    JOIN performance_schedules ps ON p.performance_id = ps.performance_id
    WHERE p.performance_id = ? AND ps.schedule_id = ?
";
$perf_stmt = mysqli_prepare($connect, $perf_query);
mysqli_stmt_bind_param($perf_stmt, "ii", $performance_id, $schedule_id);
mysqli_stmt_execute($perf_stmt);
$perf_result = mysqli_stmt_get_result($perf_stmt);
$performance = mysqli_fetch_assoc($perf_result);

if (!$performance) {
    header("Location: home.php");
    exit();
}

// 선택된 좌석 정보 정리
$selected_seats = [];
if (!empty($vip_seats)) {
    $seat_numbers = explode(',', $vip_seats);
    $selected_seats[] = [
        'type' => 'VIP',
        'numbers' => $vip_seats,
        'count' => count($seat_numbers),
        'unit_price' => $performance['vip_price'],
        'subtotal' => $performance['vip_price'] * count($seat_numbers)
    ];
}
if (!empty($r_seats)) {
    $seat_numbers = explode(',', $r_seats);
    $selected_seats[] = [
        'type' => 'R',
        'numbers' => $r_seats,
        'count' => count($seat_numbers),
        'unit_price' => $performance['r_price'],
        'subtotal' => $performance['r_price'] * count($seat_numbers)
    ];
}
if (!empty($s_seats)) {
    $seat_numbers = explode(',', $s_seats);
    $selected_seats[] = [
        'type' => 'S',
        'numbers' => $s_seats,
        'count' => count($seat_numbers),
        'unit_price' => $performance['s_price'],
        'subtotal' => $performance['s_price'] * count($seat_numbers)
    ];
}

// 총 금액 재계산 (보안)
$calculated_total = 0;
foreach ($selected_seats as $seat_info) {
    $calculated_total += $seat_info['subtotal'];
}

// 폼 제출 처리
$error_message = "";
$success = false;
$booking_group_id = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_booking'])) {
    $payment_method = $_POST['payment_method'] ?? '';
    $special_request = trim($_POST['special_request'] ?? '');
    
    if (empty($payment_method)) {
        $error_message = "결제 방법을 선택해주세요.";
    } elseif ($calculated_total != $total_price) {
        $error_message = "가격 정보가 올바르지 않습니다.";
    } else {
        // 트랜잭션 시작
        mysqli_begin_transaction($connect);
        
        try {
            // 좌석 중복 예매 체크
            $conflict = false;
            foreach ($selected_seats as $seat_info) {
                $check_query = "
                    SELECT b.seat_numbers 
                    FROM bookings b
                    JOIN booking_groups bg ON b.booking_group_id = bg.group_id
                    WHERE bg.schedule_id = ? AND b.seat_type = ? AND bg.status = 'confirmed'
                ";
                $check_stmt = mysqli_prepare($connect, $check_query);
                mysqli_stmt_bind_param($check_stmt, "is", $schedule_id, $seat_info['type']);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                
                while ($existing = mysqli_fetch_assoc($check_result)) {
                    $existing_seats = explode(',', $existing['seat_numbers']);
                    $new_seats = explode(',', $seat_info['numbers']);
                    
                    if (array_intersect($existing_seats, $new_seats)) {
                        $conflict = true;
                        break 2;
                    }
                }
                mysqli_stmt_close($check_stmt);
            }
            
            if ($conflict) {
                throw new Exception("선택하신 좌석 중 이미 예매된 좌석이 있습니다.");
            }
            
            // 예매 그룹 ID 생성 (예: BG_20250608_001234)
            $booking_group_id = 'BG_' . date('Ymd') . '_' . sprintf('%06d', rand(1, 999999));
            
            // 중복 체크 (혹시 같은 ID가 있다면 다시 생성)
            $duplicate_check = "SELECT group_id FROM booking_groups WHERE group_id = ?";
            $dup_stmt = mysqli_prepare($connect, $duplicate_check);
            mysqli_stmt_bind_param($dup_stmt, "s", $booking_group_id);
            mysqli_stmt_execute($dup_stmt);
            $dup_result = mysqli_stmt_get_result($dup_stmt);
            
            while (mysqli_num_rows($dup_result) > 0) {
                $booking_group_id = 'BG_' . date('Ymd') . '_' . sprintf('%06d', rand(1, 999999));
                mysqli_stmt_execute($dup_stmt);
                $dup_result = mysqli_stmt_get_result($dup_stmt);
            }
            mysqli_stmt_close($dup_stmt);
            
            // 1. 예매 그룹 정보 저장
            $group_query = "
                INSERT INTO booking_groups (
                    group_id, user_id, schedule_id, total_price, 
                    payment_method, special_request
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";
            $group_stmt = mysqli_prepare($connect, $group_query);
            mysqli_stmt_bind_param($group_stmt, "ssiiss", 
                $booking_group_id,
                $_SESSION['user_id'], 
                $schedule_id,
                $calculated_total,
                $payment_method,
                $special_request
            );
            
            if (!mysqli_stmt_execute($group_stmt)) {
                throw new Exception("예매 그룹 생성 중 오류가 발생했습니다.");
            }
            mysqli_stmt_close($group_stmt);
            
            // 2. 각 좌석 타입별로 예매 정보 저장
            foreach ($selected_seats as $seat_info) {
                $booking_query = "
                    INSERT INTO bookings (
                        booking_group_id, seat_type, seat_numbers, 
                        seat_count, unit_price, subtotal
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ";
                $booking_stmt = mysqli_prepare($connect, $booking_query);
                mysqli_stmt_bind_param($booking_stmt, "sssiii", 
                    $booking_group_id,
                    $seat_info['type'],
                    $seat_info['numbers'],
                    $seat_info['count'],
                    $seat_info['unit_price'],
                    $seat_info['subtotal']
                );
                
                if (!mysqli_stmt_execute($booking_stmt)) {
                    throw new Exception("좌석 예매 처리 중 오류가 발생했습니다.");
                }
                mysqli_stmt_close($booking_stmt);
            }
            
            // 커밋
            mysqli_commit($connect);
            $success = true;
            
        } catch (Exception $e) {
            // 롤백
            mysqli_rollback($connect);
            $error_message = $e->getMessage();
        }
    }
}

mysqli_stmt_close($perf_stmt);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>예매하기 - ShowTicket</title>
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
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
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
        
        .page-title {
            font-size: 2rem;
            margin-bottom: 2rem;
            color: #333;
            text-align: center;
        }
        
        .booking-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        .performance-info {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid #e0e6ff;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 0.8rem;
        }
        
        .info-label {
            font-weight: bold;
            color: #667eea;
            min-width: 100px;
            margin-right: 1rem;
        }
        
        .info-value {
            color: #333;
        }
        
        .seats-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .seat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem;
            background: white;
            border-radius: 5px;
            margin-bottom: 0.5rem;
            border: 1px solid #e9ecef;
        }
        
        .seat-type {
            font-weight: bold;
            color: #667eea;
        }
        
        .seat-numbers {
            color: #666;
            font-size: 0.9rem;
        }
        
        .seat-price {
            font-weight: bold;
            color: #333;
        }
        
        .seat-details {
            font-size: 0.85rem;
            color: #888;
            margin-top: 0.2rem;
        }
        
        .total-section {
            border-top: 2px solid #667eea;
            padding-top: 1rem;
            text-align: right;
        }
        
        .total-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .payment-section {
            margin-bottom: 2rem;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .payment-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-option:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .payment-option.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        
        .payment-option input[type="radio"] {
            margin-right: 0.8rem;
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: transparent;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
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
        
        .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        .btn-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .success-section {
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        .success-title {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #28a745;
        }
        
        .success-message {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 1rem;
        }
        
        .booking-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f0f4ff;
            border-radius: 8px;
            border: 1px solid #e0e6ff;
        }
    </style>
</head>
<body>
    <!-- 헤더 -->
    <header class="header">
        <div class="nav-container">
            <a href="home.php" class="logo">🎭 ShowTicket</a>
        </div>
    </header>

    <div class="container">
        <!-- 브레드크럼 -->
        <div class="breadcrumb">
            <a href="home.php">홈</a> > 
            <a href="performance_detail.php?id=<?php echo $performance_id; ?>"><?php echo htmlspecialchars($performance['title']); ?></a> > 
            예매 확인
        </div>

        <?php if ($success): ?>
            <!-- 예매 완료 -->
            <div class="booking-card">
                <div class="success-section">
                    <div class="success-icon">🎉</div>
                    <h1 class="success-title">예매가 완료되었습니다!</h1>
                    <div class="booking-number">
                        📝 예매번호: <?php echo $booking_group_id; ?>
                    </div>
                    <p class="success-message">
                        예매 정보는 마이페이지에서 확인하실 수 있습니다.<br>
                        공연 당일 예매 확인서를 지참해주세요.
                    </p>
                    <div class="btn-actions">
                        <a href="my_bookings.php" class="btn btn-primary">내 예매 조회</a>
                        <a href="home.php" class="btn">메인으로</a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <h1 class="page-title">🎫 예매하기</h1>

            <!-- 공연 정보 -->
            <div class="booking-card">
                <h2 class="section-title">공연 정보</h2>
                <div class="performance-info">
                    <div class="info-row">
                        <span class="info-label">공연명</span>
                        <span class="info-value"><?php echo htmlspecialchars($performance['title']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">장르</span>
                        <span class="info-value"><?php echo $performance['genre']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">공연장</span>
                        <span class="info-value"><?php echo htmlspecialchars($performance['venue_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">일시</span>
                        <span class="info-value">
                            <?php echo date('Y년 m월 d일', strtotime($performance['performance_date'])); ?> 
                            <?php echo date('H:i', strtotime($performance['show_time'])); ?> 
                            (<?php echo $performance['round_name']; ?>)
                        </span>
                    </div>
                </div>
            </div>

            <!-- 선택된 좌석 정보 -->
            <div class="booking-card">
                <h2 class="section-title">선택 좌석</h2>
                <div class="seats-info">
                    <?php foreach ($selected_seats as $seat_info): ?>
                        <div class="seat-item">
                            <div>
                                <div class="seat-type"><?php echo $seat_info['type']; ?>석 <?php echo $seat_info['count']; ?>매</div>
                                <div class="seat-numbers">좌석번호: <?php echo $seat_info['numbers']; ?></div>
                                <div class="seat-details">
                                    <?php echo number_format($seat_info['unit_price']); ?>원 × <?php echo $seat_info['count']; ?>매
                                </div>
                            </div>
                            <div class="seat-price"><?php echo number_format($seat_info['subtotal']); ?>원</div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="total-section">
                        <div class="total-price">총 결제금액: <?php echo number_format($calculated_total); ?>원</div>
                    </div>
                </div>
            </div>

            <!-- 결제 및 예매 정보 입력 -->
            <form method="POST" action="" id="bookingForm">
                <!-- 숨겨진 필드들 -->
                <input type="hidden" name="schedule_id" value="<?php echo $schedule_id; ?>">
                <input type="hidden" name="performance_id" value="<?php echo $performance_id; ?>">
                <input type="hidden" name="total_price" value="<?php echo $calculated_total; ?>">
                <input type="hidden" name="seat_count" value="<?php echo $seat_count; ?>">
                <?php if (!empty($vip_seats)): ?>
                    <input type="hidden" name="vip_seats" value="<?php echo htmlspecialchars($vip_seats); ?>">
                <?php endif; ?>
                <?php if (!empty($r_seats)): ?>
                    <input type="hidden" name="r_seats" value="<?php echo htmlspecialchars($r_seats); ?>">
                <?php endif; ?>
                <?php if (!empty($s_seats)): ?>
                    <input type="hidden" name="s_seats" value="<?php echo htmlspecialchars($s_seats); ?>">
                <?php endif; ?>

                <div class="booking-card">
                    <h2 class="section-title">결제 방법</h2>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-error">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="payment-section">
                        <div class="payment-methods">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="card" required>
                                💳 신용/체크카드
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="bank" required>
                                🏦 무통장입금
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="mobile" required>
                                📱 휴대폰 결제
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="special_request">특별 요청사항 (선택)</label>
                        <textarea id="special_request" name="special_request" class="form-control" rows="4" 
                                  placeholder="휠체어석, 알레르기 등 특별한 요청사항이 있으시면 입력해주세요."></textarea>
                    </div>

                    <div class="btn-actions">
                        <button type="button" onclick="history.back()" class="btn btn-secondary">이전으로</button>
                        <button type="submit" name="confirm_booking" class="btn btn-primary">예매 완료</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // 결제 방법 선택 시 스타일 변경
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // 모든 옵션에서 selected 클래스 제거
                document.querySelectorAll('.payment-option').forEach(option => {
                    option.classList.remove('selected');
                });
                
                // 선택된 옵션에 selected 클래스 추가
                this.closest('.payment-option').classList.add('selected');
            });
        });

        // 폼 제출 전 확인
        document.getElementById('bookingForm')?.addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!paymentMethod) {
                e.preventDefault();
                alert('결제 방법을 선택해주세요.');
                return;
            }
            
            const confirmed = confirm('예매를 완료하시겠습니까?\n\n예매 완료 후에는 취소/변경이 제한될 수 있습니다.');
            if (!confirmed) {
                e.preventDefault();
            }
        });
    </script>

    <?php mysqli_close($connect); ?>
</body>
</html>