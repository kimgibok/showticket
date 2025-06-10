<?php
include './dbconn.php';
session_start();

// 로그인 체크
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// URL 파라미터 가져오기
$schedule_id = $_GET['schedule_id'] ?? 0;
$performance_id = $_GET['performance_id'] ?? 0;

if (!$schedule_id || !$performance_id) {
    header("Location: home.php");
    exit();
}

// 공연 및 회차 정보 조회
$query = "
    SELECT p.*, v.venue_name, v.location, ps.performance_date, ps.show_time, ps.round_name
    FROM performances p
    JOIN venues v ON p.venue_id = v.venue_id
    JOIN performance_schedules ps ON p.performance_id = ps.performance_id
    WHERE p.performance_id = ? AND ps.schedule_id = ?
";
$stmt = mysqli_prepare($connect, $query);
mysqli_stmt_bind_param($stmt, "ii", $performance_id, $schedule_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$performance = mysqli_fetch_assoc($result);

if (!$performance) {
    header("Location: home.php");
    exit();
}

// 이미 예매된 좌석 조회 (새 테이블 구조)
$booked_query = "
    SELECT b.seat_type, b.seat_numbers 
    FROM bookings b
    JOIN booking_groups bg ON b.booking_group_id = bg.group_id
    WHERE bg.schedule_id = ? AND bg.status = 'confirmed'
";
$booked_stmt = mysqli_prepare($connect, $booked_query);
mysqli_stmt_bind_param($booked_stmt, "i", $schedule_id);
mysqli_stmt_execute($booked_stmt);
$booked_result = mysqli_stmt_get_result($booked_stmt);

$booked_seats = [];
while ($booking = mysqli_fetch_assoc($booked_result)) {
    $seat_numbers = explode(',', $booking['seat_numbers']);
    foreach ($seat_numbers as $seat_num) {
        $booked_seats[$booking['seat_type']][] = trim($seat_num);
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>좌석 선택 - <?php echo htmlspecialchars($performance['title']); ?></title>
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
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }
        
        .breadcrumb {
            grid-column: 1 / -1;
            margin-bottom: 1rem;
            color: #666;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .main-content {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }
        
        .performance-info {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid #e0e6ff;
        }
        
        .performance-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .performance-details {
            color: #666;
            font-size: 0.95rem;
        }
        
        .stage-area {
            text-align: center;
            margin: 2rem 0;
        }
        
        .stage {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 20px;
            font-size: 1.2rem;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 2rem;
        }
        
        .seat-section {
            margin-bottom: 3rem;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-align: center;
        }
        
        .vip-section .section-title {
            background: #ffd700;
            color: #333;
        }
        
        .r-section .section-title {
            background: #ff6b6b;
            color: white;
        }
        
        .s-section .section-title {
            background: #4ecdc4;
            color: white;
        }
        
        .seat-grid {
            display: grid;
            gap: 0.3rem;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .seat {
            width: 35px;
            height: 35px;
            border: 2px solid #ddd;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .seat.available {
            background: white;
            color: #333;
            border-color: #ddd;
        }
        
        .seat.available:hover {
            background: #e3f2fd;
            border-color: #2196f3;
            transform: scale(1.1);
        }
        
        .seat.selected {
            background: #667eea;
            color: white;
            border-color: #5a67d8;
            transform: scale(1.1);
        }
        
        .seat.booked {
            background: #e0e0e0;
            color: #999;
            border-color: #ccc;
            cursor: not-allowed;
        }
        
        .seat-legend {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .legend-seat {
            width: 20px;
            height: 20px;
            border-radius: 3px;
            border: 2px solid;
        }
        
        .sidebar {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            height: fit-content;
            position: sticky;
            top: 2rem;
        }
        
        .sidebar-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        .selected-seats {
            margin-bottom: 2rem;
        }
        
        .selected-seat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 0.5rem;
        }
        
        .seat-info {
            flex: 1;
        }
        
        .seat-type {
            font-weight: bold;
            color: #667eea;
        }
        
        .seat-price {
            color: #666;
            font-size: 0.9rem;
        }
        
        .remove-seat {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 0.3rem 0.6rem;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .remove-seat:hover {
            background: #c82333;
        }
        
        .total-section {
            border-top: 2px solid #667eea;
            padding-top: 1rem;
            margin-bottom: 2rem;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .total-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 1rem;
            background: transparent;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            border: 2px solid #667eea;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            text-align: center;
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
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn:disabled {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn:disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .empty-selection {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 2rem 0;
        }
        
        .booking-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .booking-notice strong {
            display: block;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 1024px) {
            .container {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .sidebar {
                position: static;
            }
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
            좌석 선택
        </div>

        <!-- 메인 콘텐츠 -->
        <div class="main-content">
            <!-- 공연 정보 -->
            <div class="performance-info">
                <div class="performance-title"><?php echo htmlspecialchars($performance['title']); ?></div>
                <div class="performance-details">
                    📍 <?php echo htmlspecialchars($performance['venue_name']); ?> | 
                    🗓️ <?php echo date('Y년 m월 d일', strtotime($performance['performance_date'])); ?> 
                    <?php echo date('H:i', strtotime($performance['show_time'])); ?> (<?php echo $performance['round_name']; ?>)
                </div>
            </div>

            <!-- 무대 -->
            <div class="stage-area">
                <div class="stage">🎭 STAGE</div>
            </div>

            <!-- VIP석 -->
            <?php if ($performance['vip_seats'] > 0): ?>
            <div class="seat-section vip-section">
                <div class="section-title">VIP석 (<?php echo $performance['vip_floor']; ?>층) - <?php echo number_format($performance['vip_price']); ?>원</div>
                <div class="seat-grid" style="grid-template-columns: repeat(<?php echo min($performance['vip_seats'], 10); ?>, 1fr);">
                    <?php for ($i = 1; $i <= $performance['vip_seats']; $i++): ?>
                        <?php
                        $is_booked = isset($booked_seats['VIP']) && in_array($i, $booked_seats['VIP']);
                        $seat_class = $is_booked ? 'seat booked' : 'seat available';
                        $onclick = $is_booked ? '' : "onclick=\"selectSeat('VIP', $i, {$performance['vip_price']})\"";
                        ?>
                        <div class="<?php echo $seat_class; ?>" data-seat-type="VIP" data-seat-number="<?php echo $i; ?>" <?php echo $onclick; ?>>
                            V<?php echo $i; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- R석 -->
            <?php if ($performance['r_seats'] > 0): ?>
            <div class="seat-section r-section">
                <div class="section-title">R석 (<?php echo $performance['r_floor']; ?>층) - <?php echo number_format($performance['r_price']); ?>원</div>
                <div class="seat-grid" style="grid-template-columns: repeat(<?php echo min($performance['r_seats'], 15); ?>, 1fr);">
                    <?php for ($i = 1; $i <= $performance['r_seats']; $i++): ?>
                        <?php
                        $is_booked = isset($booked_seats['R']) && in_array($i, $booked_seats['R']);
                        $seat_class = $is_booked ? 'seat booked' : 'seat available';
                        $onclick = $is_booked ? '' : "onclick=\"selectSeat('R', $i, {$performance['r_price']})\"";
                        ?>
                        <div class="<?php echo $seat_class; ?>" data-seat-type="R" data-seat-number="<?php echo $i; ?>" <?php echo $onclick; ?>>
                            R<?php echo $i; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- S석 -->
            <?php if ($performance['s_seats'] > 0): ?>
            <div class="seat-section s-section">
                <div class="section-title">S석 (<?php echo $performance['s_floor']; ?>층) - <?php echo number_format($performance['s_price']); ?>원</div>
                <div class="seat-grid" style="grid-template-columns: repeat(<?php echo min($performance['s_seats'], 20); ?>, 1fr);">
                    <?php for ($i = 1; $i <= $performance['s_seats']; $i++): ?>
                        <?php
                        $is_booked = isset($booked_seats['S']) && in_array($i, $booked_seats['S']);
                        $seat_class = $is_booked ? 'seat booked' : 'seat available';
                        $onclick = $is_booked ? '' : "onclick=\"selectSeat('S', $i, {$performance['s_price']})\"";
                        ?>
                        <div class="<?php echo $seat_class; ?>" data-seat-type="S" data-seat-number="<?php echo $i; ?>" <?php echo $onclick; ?>>
                            S<?php echo $i; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 좌석 범례 -->
            <div class="seat-legend">
                <div class="legend-item">
                    <div class="legend-seat" style="background: white; border-color: #ddd;"></div>
                    <span>선택 가능</span>
                </div>
                <div class="legend-item">
                    <div class="legend-seat" style="background: #667eea; border-color: #5a67d8;"></div>
                    <span>선택됨</span>
                </div>
                <div class="legend-item">
                    <div class="legend-seat" style="background: #e0e0e0; border-color: #ccc;"></div>
                    <span>예매완료</span>
                </div>
            </div>
        </div>

        <!-- 사이드바 -->
        <div class="sidebar">
            <h3 class="sidebar-title">🎫 선택한 좌석</h3>
            
            <!-- 예매 안내 -->
            <div class="booking-notice">
                <strong>💡 예매 안내</strong>
                • 좌석 선택 후 예매 진행<br>
                • 다른 좌석 타입도 함께 선택 가능<br>
                • 결제는 한 번에 처리됩니다
            </div>
            
            <div class="selected-seats" id="selectedSeats">
                <div class="empty-selection">
                    좌석을 선택해주세요
                </div>
            </div>

            <div class="total-section">
                <div class="total-row">
                    <span>선택 좌석 수:</span>
                    <span id="totalSeats">0석</span>
                </div>
                <div class="total-row">
                    <span>총 결제금액:</span>
                    <span class="total-price" id="totalPrice">0원</span>
                </div>
            </div>

            <button class="btn btn-primary" id="bookingButton" onclick="proceedToBooking()" disabled>
                예매하기
            </button>
            
            <button class="btn" onclick="history.back()" style="margin-top: 1rem;">
                이전으로
            </button>
        </div>
    </div>

    <script>
        let selectedSeats = [];
        const scheduleId = <?php echo $schedule_id; ?>;
        const performanceId = <?php echo $performance_id; ?>;

        function selectSeat(seatType, seatNumber, price) {
            const seatKey = `${seatType}-${seatNumber}`;
            const seatElement = document.querySelector(`[data-seat-type="${seatType}"][data-seat-number="${seatNumber}"]`);
            
            // 이미 선택된 좌석인지 확인
            const existingIndex = selectedSeats.findIndex(seat => seat.key === seatKey);
            
            if (existingIndex >= 0) {
                // 선택 해제
                selectedSeats.splice(existingIndex, 1);
                seatElement.classList.remove('selected');
                seatElement.classList.add('available');
            } else {
                // 새로 선택
                selectedSeats.push({
                    key: seatKey,
                    type: seatType,
                    number: seatNumber,
                    price: price
                });
                seatElement.classList.remove('available');
                seatElement.classList.add('selected');
            }
            
            updateSidebar();
        }

        function removeSeat(seatKey) {
            const index = selectedSeats.findIndex(seat => seat.key === seatKey);
            if (index >= 0) {
                const seat = selectedSeats[index];
                const seatElement = document.querySelector(`[data-seat-type="${seat.type}"][data-seat-number="${seat.number}"]`);
                
                selectedSeats.splice(index, 1);
                seatElement.classList.remove('selected');
                seatElement.classList.add('available');
                
                updateSidebar();
            }
        }

        function updateSidebar() {
            const container = document.getElementById('selectedSeats');
            const totalSeatsEl = document.getElementById('totalSeats');
            const totalPriceEl = document.getElementById('totalPrice');
            const bookingButton = document.getElementById('bookingButton');
            
            if (selectedSeats.length === 0) {
                container.innerHTML = '<div class="empty-selection">좌석을 선택해주세요</div>';
                totalSeatsEl.textContent = '0석';
                totalPriceEl.textContent = '0원';
                bookingButton.disabled = true;
            } else {
                let html = '';
                let totalPrice = 0;
                
                // 좌석 타입별로 그룹화
                const seatsByType = {};
                selectedSeats.forEach(seat => {
                    if (!seatsByType[seat.type]) {
                        seatsByType[seat.type] = [];
                    }
                    seatsByType[seat.type].push(seat);
                    totalPrice += seat.price;
                });
                
                // 타입별로 표시
                Object.keys(seatsByType).forEach(type => {
                    const seats = seatsByType[type];
                    const typeTotal = seats.reduce((sum, seat) => sum + seat.price, 0);
                    const seatNumbers = seats.map(seat => seat.number).sort((a, b) => a - b).join(', ');
                    
                    html += `
                        <div class="selected-seat-item">
                            <div class="seat-info">
                                <div class="seat-type">${type}석 ${seats.length}매</div>
                                <div class="seat-price">좌석: ${seatNumbers} | ${typeTotal.toLocaleString()}원</div>
                            </div>
                            <button class="remove-seat" onclick="removeTypeSeats('${type}')">✕</button>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
                totalSeatsEl.textContent = `${selectedSeats.length}석`;
                totalPriceEl.textContent = `${totalPrice.toLocaleString()}원`;
                bookingButton.disabled = false;
            }
        }

        function removeTypeSeats(seatType) {
            // 해당 타입의 모든 좌석 제거
            const seatsToRemove = selectedSeats.filter(seat => seat.type === seatType);
            seatsToRemove.forEach(seat => {
                const seatElement = document.querySelector(`[data-seat-type="${seat.type}"][data-seat-number="${seat.number}"]`);
                seatElement.classList.remove('selected');
                seatElement.classList.add('available');
            });
            
            selectedSeats = selectedSeats.filter(seat => seat.type !== seatType);
            updateSidebar();
        }

        function proceedToBooking() {
            if (selectedSeats.length === 0) {
                alert('좌석을 선택해주세요.');
                return;
            }
            
            // 선택한 좌석 정보를 폼으로 전송
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'booking_process.php';
            
            // 기본 정보
            form.appendChild(createHiddenInput('schedule_id', scheduleId));
            form.appendChild(createHiddenInput('performance_id', performanceId));
            
            // 좌석별로 그룹화
            const seatsByType = {};
            let totalPrice = 0;
            
            selectedSeats.forEach(seat => {
                if (!seatsByType[seat.type]) {
                    seatsByType[seat.type] = [];
                }
                seatsByType[seat.type].push(seat.number);
                totalPrice += seat.price;
            });
            
            // 각 좌석 타입별로 전송
            Object.keys(seatsByType).forEach(seatType => {
                form.appendChild(createHiddenInput(`${seatType.toLowerCase()}_seats`, seatsByType[seatType].join(',')));
            });
            
            form.appendChild(createHiddenInput('total_price', totalPrice));
            form.appendChild(createHiddenInput('seat_count', selectedSeats.length));
            
            document.body.appendChild(form);
            form.submit();
        }

        function createHiddenInput(name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            return input;
        }

        // 디버깅을 위한 예매된 좌석 정보 출력
        console.log('Booked seats:', <?php echo json_encode($booked_seats); ?>);
    </script>

    <?php
    mysqli_stmt_close($stmt);
    mysqli_stmt_close($booked_stmt);
    mysqli_close($connect);
    ?>
</body>
</html>