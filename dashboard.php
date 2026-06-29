<?php
require 'connection.php';

// Start session to safely manage state across pages if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Enforcement: Kick back to logging panel if user identifier isn't tracked
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch session parameters synchronized within login_process.php
$user_id = $_SESSION['user_id'];
$customer_name = $_SESSION['fullname'] ?? 'Guest';
$user_role = $_SESSION['role'] ?? 'Customer';

// --- DATABASE METRICS AGGREGATION & GROUPING LOGIC (SYNCHRONIZED WITH VIEW.PHP) ---

// Pull all account-specific reservations to replicate grouping architecture accurately
$all_reservations = [];
$query_stmt = $conn->prepare("SELECT * FROM reservations WHERE user_id = ? ORDER BY id DESC");
$query_stmt->bind_param("i", $user_id);
if ($query_stmt->execute()) {
    $res_query = $query_stmt->get_result();
    while ($row = $res_query->fetch_assoc()) {
        $all_reservations[] = $row;
    }
}
$query_stmt->close();

// Process grouping structure matching view.php parameters exactly
$grouped_reservations = [];
$total_trays = 0;
$total_spent = 0;

foreach ($all_reservations as $row) {
    $timestamp_key = isset($row['created_at']) ? $row['created_at'] : $row['reservation_date'];
    $group_key = $row['customer_name'] . '_' . $row['contact_number'] . '_' . $timestamp_key . '_' . $row['delivery_method'];
    
    if (!isset($grouped_reservations[$group_key])) {
        $grouped_reservations[$group_key] = [
            'id' => $row['id'],
            'egg_type' => $row['egg_type'], // Fallback structural indicator
            'reservation_date' => $row['reservation_date'],
            'delivery_method' => $row['delivery_method'],
            'total_price' => 0, 
            'items' => []
        ];
    }

    $grouped_reservations[$group_key]['items'][] = [
        'egg_type' => $row['egg_type'],
        'quantity' => (int)$row['quantity'],
        'total_price' => (float)$row['total_price']
    ];
    
    $grouped_reservations[$group_key]['total_price'] += (float)$row['total_price'];
    $total_trays += (int)$row['quantity'];
    $total_spent += (float)$row['total_price'];
}

// Complete assignments reflecting grouped statistics
$total_reservations = count($grouped_reservations);
$recent_activities = array_slice($grouped_reservations, 0, 5); // Limit dashboard recent items list to top 5 bundles

// Fetch all persistent historical user notifications to render inside dropdown
$notifications = [];
$notif_stmt = $conn->prepare("SELECT id, title, description, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$result = $notif_stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$notif_stmt->close();

$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unread_count++;
}

// Fetch Profile Picture Filename for Top Header Right Panel
$db_profile_photo = "";
$photo_stmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ? LIMIT 1");
$photo_stmt->bind_param("i", $user_id);
if ($photo_stmt->execute()) {
    $photo_res = $photo_stmt->get_result();
    if ($user_row = $photo_res->fetch_assoc()) {
        $db_profile_photo = $user_row['profile_photo'] ?? '';
    }
}
$photo_stmt->close();

// Determine dynamic avatar source path for the user header
$avatar_src = (!empty($db_profile_photo) && file_exists("uploads/" . $db_profile_photo)) 
    ? "uploads/" . htmlspecialchars($db_profile_photo) 
    : "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=150";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VDVC - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 font-sans text-gray-700 antialiased min-h-screen">

    <div class="flex min-h-screen">
        
        <!-- Left Navigation Bar -->
        <aside class="w-64 bg-sky-500 text-white flex flex-col flex-shrink-0 shadow-xl">
            <div class="p-6 flex flex-col items-center justify-center border-b border-sky-400/40">
                <img src="vdvc.png" alt="Logo" class="w-50 h-50 object-contain mb-2">
                <span class="font-bold text-lg tracking-wide uppercase">VDVC Egg Farm</span>
                <span class="mt-1 text-[10px] bg-sky-600 px-2 py-0.5 rounded-full font-semibold uppercase tracking-wider text-sky-100">
                    <?= htmlspecialchars($user_role) ?> Panel
                </span>
            </div>
            
            <nav class="flex-1 p-4 space-y-2 mt-4">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-sky-600/40 transition font-medium">
                    <i class="fa-solid fa-chart-pie w-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="reservation.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-calendar-check w-5"></i>
                    <span>Reservation</span>
                </a>
                <a href="view.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-clock-rotate-left w-5"></i>
                    <span>Reservation History</span>
                </a>
                <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-user w-5"></i>
                    <span>My Profile</span>
                </a>
                
                <hr class="border-sky-400/30 my-2">
                <a href="javascript:void(0);" onclick="openLogoutModal()" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-red-600/80 transition font-medium text-sky-100 hover:text-white">
                    <i class="fa-solid fa-right-from-bracket w-5"></i>
                    <span>Logout</span>
                </a>
            </nav>
            
            <div class="p-4 text-center text-xs text-sky-200 border-t border-sky-400/30">
                &copy; 2026 Egg Reservation Systems
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">

            <header class="bg-white border-b border-gray-200 px-8 py-4 flex justify-between items-center relative">
                <h1 class="text-2xl font-bold text-slate-800">📊 Dashboard</h1>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="font-semibold text-sm text-gray-800"><?php echo !empty($customer_name) ? htmlspecialchars($customer_name) : 'Guest'; ?></div>
                        <div class="text-xs text-gray-400">(Customer Access)</div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-purple-200 overflow-hidden border border-gray-300">
                        <img src="<?= $avatar_src ?>" alt="Profile" class="w-full h-full object-cover">
                    </div>
                    
                    <div class="relative">
                        <div onclick="toggleNotifications(event)" class="cursor-pointer relative p-1 hover:bg-gray-100 rounded-full transition">
                            <i class="fa-regular fa-bell text-gray-500 text-xl"></i>
                            <span id="bell_badge" class="absolute top-1 right-1 w-2.5 h-2.5 bg-blue-500 rounded-full border-2 border-white <?php echo $unread_count > 0 ? '' : 'hidden'; ?>"></span>
                        </div>

                        <div id="notification_dropdown" class="hidden absolute right-0 mt-3 w-80 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                                <span class="font-bold text-sm text-slate-800">Notifications</span>
                                <span id="unread_count" class="text-xs <?php echo $unread_count > 0 ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400'; ?> px-2 py-0.5 rounded-full font-medium">
                                    <?php echo $unread_count > 0 ? $unread_count . ' New' : '0 New'; ?>
                                </span>
                            </div>
                            <div id="notification_list" class="divide-y divide-gray-100 max-h-60 overflow-y-auto">
                                <?php if (!empty($notifications)): ?>
                                    <?php foreach ($notifications as $notif): 
                                        $bg_class = !$notif['is_read'] ? 'bg-blue-50/30' : '';
                                        $icon_bg = 'bg-blue-100 text-blue-600';
                                        $icon_fa = 'fa-solid fa-circle-info';
                                        
                                        if ($notif['type'] === 'success') {
                                            $icon_bg = 'bg-green-100 text-green-600';
                                            $icon_fa = 'fa-solid fa-circle-check';
                                        } elseif ($notif['type'] === 'alert') {
                                            $icon_bg = 'bg-red-100 text-red-600';
                                            $icon_fa = 'fa-solid fa-triangle-exclamation';
                                        }
                                    ?>
                                        <div class="p-4 hover:bg-slate-50 transition flex space-x-3 <?php echo $bg_class; ?>">
                                            <div class="<?php echo $icon_bg; ?> rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0 mt-0.5">
                                                <i class="<?php echo $icon_fa; ?> text-xs"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs text-gray-700 font-semibold mb-0.5 truncate"><?php echo htmlspecialchars($notif['title']); ?></p>
                                                <p class="text-[11px] text-gray-500 break-words mb-1"><?php echo htmlspecialchars($notif['description']); ?></p>
                                                <p class="text-[9px] text-gray-400"><?php echo date('M d, g:i a', strtotime($notif['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div id="empty_notification_placeholder" class="p-8 text-center text-gray-400 text-xs">
                                        <i class="fa-regular fa-bell-slash text-2xl mb-2 block text-gray-300"></i>
                                        No new notifications
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </header>

            <main class="max-w-7xl w-full mx-auto p-6 space-y-8">
                
                <div class="bg-gradient-to-r from-sky-400 to-blue-500 rounded-2xl p-6 text-white shadow-md flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold mb-1">Welcome back, <?php echo htmlspecialchars($customer_name); ?>!</h2>
                        <p class="text-sky-100 text-sm">Monitor your egg orders, pending fulfillments, and quickly make new reservations.</p>
                    </div>
                    <a href="reservation.php" class="bg-white text-blue-600 font-semibold px-5 py-2.5 rounded-xl shadow-sm hover:bg-sky-50 transition text-sm">
                        <i class="fa-solid fa-plus mr-1"></i> New Reservation
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex items-center justify-between">
                        <div>
                            <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Bookings</span>
                            <span class="text-3xl font-extrabold text-slate-800"><?php echo number_format($total_reservations); ?></span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex items-center justify-between">
                        <div>
                            <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Trays Ordered</span>
                            <span class="text-3xl font-extrabold text-slate-800"><?php echo number_format($total_trays); ?></span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-egg"></i>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex items-center justify-between">
                        <div>
                            <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Valuation</span>
                            <span class="text-3xl font-extrabold text-blue-600">₱<?php echo number_format($total_spent, 2); ?></span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 lg:col-span-2 space-y-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-bold text-slate-800">Recent Reservations</h3>
                                <p class="text-xs text-gray-400">Your latest system modifications and orders</p>
                            </div>
                            <a href="view.php" class="text-sm font-semibold text-blue-600 hover:text-blue-700 transition">View All</a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm border-collapse">
                                <thead>
                                    <tr class="border-b border-gray-100 text-gray-400 font-semibold text-xs uppercase bg-gray-50/70">
                                        <th class="py-3 px-4">Order Details</th>
                                        <th class="py-3 px-4">Date</th>
                                        <th class="py-3 px-4">Method</th>
                                        <th class="py-3 px-4 text-right">Price</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-600">
                                    <?php if (!empty($recent_activities)): ?>
                                        <?php foreach ($recent_activities as $activity): 
                                            $is_bundle = count($activity['items']) > 1;
                                            $total_item_qty = array_sum(array_column($activity['items'], 'quantity'));
                                        ?>
                                            <tr class="hover:bg-slate-50/80 transition">
                                                <td class="py-3.5 px-4 font-medium text-slate-800">
                                                    <div class="flex items-center space-x-2">
                                                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                                        <span>
                                                            <?php if ($is_bundle): ?>
                                                                <span class="font-bold text-amber-600 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded text-xs">Bundle</span>
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars($activity['items'][0]['egg_type']); ?>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span class="text-xs text-gray-400">(x<?php echo $total_item_qty; ?>)</span>
                                                    </div>
                                                </td>
                                                <td class="py-3.5 px-4 text-xs"><?php echo htmlspecialchars($activity['reservation_date']); ?></td>
                                                <td class="py-3.5 px-4">
                                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $activity['delivery_method'] === 'Delivery' ? 'bg-sky-50 text-sky-600' : 'bg-purple-50 text-purple-600'; ?>">
                                                        <?php echo htmlspecialchars($activity['delivery_method']); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3.5 px-4 text-right font-bold text-slate-800">₱<?php echo number_format((float)$activity['total_price'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="p-8 text-center text-gray-400 text-xs">
                                                <i class="fa-regular fa-folder-open text-2xl mb-2 block text-gray-300"></i>
                                                No reservation history found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-6">
                        
                        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 space-y-4">
                            <h3 class="text-base font-bold text-slate-800">Quick Operations</h3>
                            <div class="grid grid-cols-1 gap-3">
                                <a href="reservation.php" class="flex items-center space-x-3 p-3 rounded-lg border border-gray-100 hover:border-blue-500 hover:bg-blue-50/30 transition group">
                                    <div class="w-9 h-9 rounded-md bg-blue-50 text-blue-500 flex items-center justify-center text-sm group-hover:bg-blue-100">
                                        <i class="fa-solid fa-calendar-plus"></i>
                                    </div>
                                    <div class="text-left">
                                        <div class="text-xs font-bold text-slate-800">Book Trays</div>
                                        <div class="text-[10px] text-gray-400">Reserve next batch distribution</div>
                                    </div>
                                </a>

                                <a href="profile.php" class="flex items-center space-x-3 p-3 rounded-lg border border-gray-100 hover:border-sky-500 hover:bg-sky-50/30 transition group">
                                    <div class="w-9 h-9 rounded-md bg-sky-50 text-sky-500 flex items-center justify-center text-sm group-hover:bg-sky-100">
                                        <i class="fa-solid fa-user-gear"></i>
                                    </div>
                                    <div class="text-left">
                                        <div class="text-xs font-bold text-slate-800">Profile Settings</div>
                                        <div class="text-[10px] text-gray-400">Update delivery address or password</div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-slate-800 to-slate-900 p-5 rounded-xl shadow-sm text-white space-y-3">
                            <div class="flex items-center space-x-2 text-amber-400 text-xs font-bold uppercase tracking-wider">
                                <i class="fa-solid fa-circle-info"></i>
                                <span>Operational Guidelines</span>
                            </div>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                Regular tray batch preparation completes within 24-48 hours. Ensure delivery addresses are accurately pinned via the Leaflet geographic layout engine tool map prior to checking tracking variables.
                            </p>
                        </div>

                    </div>

                </div>

            </main>
            
        </div>
    </div>

    <!-- Interactive Logout Confirmation Modal -->
    <div id="logout_modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
        <div class="bg-white rounded-xl shadow-2xl border border-gray-100 max-w-sm w-full p-6 space-y-4 transform transition-all">
            <div class="flex items-center space-x-3 text-red-500">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center text-red-600">
                    <i class="fa-solid fa-right-from-bracket text-base"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800">Confirm Logout</h3>
            </div>
            <p class="text-sm text-gray-500">Are you sure you want to end your session? You will need to log back in to manage reservations.</p>
            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" onclick="closeLogoutModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold transition">
                    Stay Logged In
                </button>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold text-center transition">
                    Yes, Logout
                </a>
            </div>
        </div>
    </div>

    <script> 
    function openLogoutModal() {
        document.getElementById('logout_modal').classList.remove('hidden');
    }

    function closeLogoutModal() {
        document.getElementById('logout_modal').classList.add('hidden');
    }

    window.addEventListener('click', function(event) {
        const logoutModal = document.getElementById('logout_modal');
        if (event.target === logoutModal) {
            closeLogoutModal();
        }
    });
    
    function toggleNotifications(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('notification_dropdown');
        dropdown.classList.toggle('hidden');
        
        if(!dropdown.classList.contains('hidden')) {
            const badge = document.getElementById('bell_badge');
            if(badge) badge.classList.add('hidden');
            const unreadCount = document.getElementById('unread_count');
            if(unreadCount) {
                unreadCount.innerText = "0 New";
                unreadCount.className = "text-xs bg-gray-100 text-gray-400 px-2 py-0.5 rounded-full font-medium";
            }
        }
    }
    </script>
</body>
</html>