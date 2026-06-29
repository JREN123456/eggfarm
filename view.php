<?php
// Start session to detect logged-in user and their account type
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'connection.php';

// Redirect to login if the user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Customer'; // Default fallback

// Get search, filter terms, and current page from GET request
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_egg = isset($_GET['egg_type']) ? trim($_GET['egg_type']) : '';
$filter_method = isset($_GET['delivery_method']) ? trim($_GET['delivery_method']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 5; // 5 items per page

// Build dynamic SQL query safely
$query = "SELECT * FROM reservations WHERE 1=1";
$params = [];
$types = "";

// ACCOUNT SCOPING: If user is a Customer, restrict data to their own records
if (strtolower($user_role) === 'customer') {
    $query .= " AND user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

// Apply Filters
if ($search !== '') {
    $query .= " AND (customer_name LIKE ? OR contact_number LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($filter_egg !== '') {
    $query .= " AND egg_type = ?";
    $params[] = $filter_egg;
    $types .= "s";
}

if ($filter_method !== '') {
    $query .= " AND delivery_method = ?";
    $params[] = $filter_method;
    $types .= "s";
}

// Fetch user notifications
$notifications = [];
$notif_stmt = $conn->prepare("SELECT title, description, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$result_notif = $notif_stmt->get_result();
while ($row = $result_notif->fetch_assoc()) {
    $notifications[] = $row;
}
$notif_stmt->close();

$unread_count = 0;
foreach ($notifications as $n) { 
    if (!$n['is_read']) $unread_count++; 
}

$query .= " ORDER BY id DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// GROUPING LOGIC FOR BUNDLES
$grouped_reservations = [];
while ($row = $result->fetch_assoc()) {
    $timestamp_key = isset($row['created_at']) ? $row['created_at'] : $row['reservation_date'];
    $group_key = $row['customer_name'] . '_' . $row['contact_number'] . '_' . $timestamp_key . '_' . $row['delivery_method'];
    
    if (!isset($grouped_reservations[$group_key])) {
        $grouped_reservations[$group_key] = [
            'id' => $row['id'], // Reference fallback for old design ID display
            'ids' => [$row['id']],
            'customer_name' => $row['customer_name'],
            'contact_number' => $row['contact_number'],
            'delivery_address' => $row['delivery_address'],
            'delivery_method' => $row['delivery_method'],
            'reservation_date' => $row['reservation_date'],
            'total_price' => 0, 
            'items' => []
        ];
    } else {
        $grouped_reservations[$group_key]['ids'][] = $row['id'];
    }

    $grouped_reservations[$group_key]['items'][] = [
        'egg_type' => $row['egg_type'],
        'quantity' => (int)$row['quantity'],
        'total_price' => (float)$row['total_price']
    ];
    $grouped_reservations[$group_key]['total_price'] += (float)$row['total_price'];
}
$stmt->close();

// PAGINATION LOGIC ON THE GROUPED ARRAY
$total_records = count($grouped_reservations);
$total_pages = ceil($total_records / $limit);
$offset = ($page - 1) * $limit;
$paged_reservations = array_slice($grouped_reservations, $offset, $limit);

// Helper function to build URL parameters cleanly for pagination links
function getPaginationUrl($page_num) {
    $params = $_GET;
    $params['page'] = $page_num;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VDVC - History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link class="no-print" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @media print {
            @page {
                size: portrait;
                margin: 0;
            }
            html, body {
                height: 100%;
                margin: 0;
                padding: 0;
                background: #ffffff !important;
            }
            body * {
                visibility: hidden;
            }
            #receipt-print-window, #receipt-print-window * {
                visibility: visible;
                display: block !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            #receipt-print-window {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100vh;
                padding: 30px;
                box-sizing: border-box;
                background: white !important;
                color: black;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 font-sans text-gray-700 antialiased min-h-screen">

    <div class="flex min-h-screen no-print">
        
        <aside class="w-64 bg-sky-500 text-white flex flex-col flex-shrink-0 shadow-xl">
            <div class="p-6 flex flex-col items-center justify-center border-b border-sky-400/40">
                <img src="vdvc.png" alt="Logo" class="w-50 h-50 object-contain mb-2">
                <span class="font-bold text-lg tracking-wide uppercase">VDVC Egg Farm</span>
                <span class="mt-1 text-[10px] bg-sky-600 px-2 py-0.5 rounded-full font-semibold uppercase tracking-wider text-sky-100">
                    <?= htmlspecialchars($user_role) ?> Panel
                </span>
            </div>
            
            <nav class="flex-1 p-4 space-y-2 mt-4">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-chart-pie w-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="reservation.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-calendar-check w-5"></i>
                    <span>Reservation</span>
                </a>
                <a href="view.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-sky-600/40 transition font-medium">
                    <i class="fa-solid fa-clock-rotate-left w-5"></i>
                    <span>Reservation History</span>
                </a>
                <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-user w-5"></i>
                    <span>My Profile</span>
                </a>
                
                <hr class="border-sky-400/30 my-2">
                <!-- Locate this inside your <nav> tag and replace it with this: -->
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
                <h1 class="text-2xl font-bold text-slate-800">📋 My Reservation History</h1>
                <div class="flex items-center space-x-4">                  
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

            <div class="max-w-7xl w-full mx-auto p-6 space-y-6">

                <div class="bg-white p-4 shadow-md rounded-xl border border-gray-200">
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        
                        <div class="md:col-span-1 relative">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Search Details</label>
                            <div class="relative">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name or contact number..." class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:border-sky-500">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400 text-xs"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Egg Size/Type</label>
                            <select name="egg_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-sky-500">
                                <option value="">All Sizes</option>
                                <?php
                                $egg_options = ['Extra Small', 'Small', 'Medium', 'Large', 'Extra Large', 'Jumbo', 'Super Jumbo', 'Double Yolk'];
                                foreach ($egg_options as $option) {
                                    $selected = ($filter_egg === $option) ? 'selected' : '';
                                    echo "<option value=\"$option\" $selected>$option</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Delivery Method</label>
                            <select name="delivery_method" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-sky-500">
                                <option value="">All Methods</option>
                                <option value="Delivery" <?= $filter_method === 'Delivery' ? 'selected' : '' ?>>Delivery</option>
                                <option value="Pickup" <?= $filter_method === 'Pickup' ? 'selected' : '' ?>>Pickup</option>
                            </select>
                        </div>

                        <div class="flex space-x-2">
                            <button type="submit" class="flex-1 bg-sky-500 hover:bg-sky-600 text-white font-semibold py-2 px-4 rounded-lg text-sm shadow transition flex items-center justify-center space-x-1">
                                <i class="fa-solid fa-filter"></i>
                                <span>Filter</span>
                            </button>
                            
                            <a href="?" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold py-2 px-4 rounded-lg text-sm border border-gray-300/70 transition flex items-center justify-center space-x-1" title="Clear Filters">
                                <i class="fa-solid fa-filter-circle-xmark"></i>
                                <span>Clear</span>
                            </a>
                        </div>

                    </form>
                </div>

                <div class="bg-white shadow-md rounded-xl border border-gray-200 overflow-hidden">

                    <div class="p-5 border-b flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50">
                        <h2 class="text-lg font-bold text-slate-800 flex items-center space-x-2">
                            <i class="fa-solid fa-list-check text-sky-500"></i>
                            <span>📋 Reservation List</span>
                        </h2>

                        <?php if ($total_pages > 1): ?>
                            <div class="flex items-center space-x-3 self-end sm:self-auto">
                                <div class="text-xs text-gray-500 hidden md:block">
                                    <span class="font-semibold text-gray-700"><?= $offset + 1 ?></span>-
                                    <span class="font-semibold text-gray-700"><?= min($offset + $limit, $total_records) ?></span> of 
                                    <span class="font-semibold text-gray-700"><?= $total_records ?></span>
                                </div>
                                <div class="inline-flex space-x-1">
                                    <a href="<?= $page > 1 ? getPaginationUrl($page - 1) : '#' ?>" 
                                       class="px-2.5 py-1.5 border rounded-lg text-xs font-semibold shadow-sm transition <?= $page > 1 ? 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed pointer-events-none' ?>">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <a href="<?= getPaginationUrl($i) ?>" 
                                           class="px-2.5 py-1.5 border rounded-lg text-xs font-bold shadow-sm transition <?= $page === $i ? 'bg-sky-500 border-sky-500 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>

                                    <a href="<?= $page < $total_pages ? getPaginationUrl($page + 1) : '#' ?>" 
                                       class="px-2.5 py-1.5 border rounded-lg text-xs font-semibold shadow-sm transition <?= $page < $total_pages ? 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed pointer-events-none' ?>">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-left">

                            <thead class="bg-slate-100 text-slate-700 uppercase text-xs font-bold tracking-wider border-b border-slate-200">
                                <tr>
                                    <th class="px-5 py-3.5">Customer</th>
                                    <th class="px-5 py-3.5">Contact</th>
                                    <th class="px-5 py-3.5">Address</th>
                                    <th class="px-5 py-3.5">Egg Breakdown</th>
                                    <th class="px-5 py-3.5 text-center">Total Qty</th>
                                    <th class="px-5 py-3.5">Method</th>
                                    <th class="px-5 py-3.5">Date</th>
                                    <th class="px-5 py-3.5 text-right">Total</th>
                                    <th class="px-5 py-3.5 text-center">Action</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100 bg-white">

                            <?php if (!empty($paged_reservations)): ?>
                                <?php foreach($paged_reservations as $row): 
                                    $receipt_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                    $total_qty = array_sum(array_column($row['items'], 'quantity'));
                                    $ids_list = implode(',', $row['ids']);
                                ?>
                                    <tr class="hover:bg-slate-50/80 transition">

                                        <td class="px-5 py-4 font-semibold text-gray-800">
                                            <?= htmlspecialchars($row['customer_name']) ?>
                                        </td>

                                        <td class="px-5 py-4 text-gray-600 font-medium">
                                            <?= htmlspecialchars($row['contact_number']) ?>
                                        </td>

                                        <td class="px-5 py-4 text-gray-500 max-w-[220px] truncate" title="<?= htmlspecialchars($row['delivery_address']) ?>">
                                            <?= htmlspecialchars($row['delivery_address']) ?>
                                        </td>

                                        <td class="px-5 py-4 space-y-1">
                                            <?php foreach($row['items'] as $item): ?>
                                                <div class="flex items-center space-x-2">
                                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide shadow-sm
                                                        <?php
                                                        switch($item['egg_type']) {
                                                            case 'Extra Small':
                                                            case 'Small': 
                                                                echo 'bg-gray-100 text-gray-700 border border-gray-200'; break;
                                                            case 'Medium': 
                                                                echo 'bg-yellow-100 text-yellow-800 border border-yellow-200'; break;
                                                            case 'Large': 
                                                                echo 'bg-orange-100 text-orange-800 border border-orange-200'; break;
                                                            case 'Extra Large':
                                                            case 'Jumbo':
                                                            case 'Super Jumbo':
                                                            case 'Double Yolk': 
                                                                echo 'bg-red-100 text-red-800 border border-red-200'; break;
                                                            default:
                                                                echo 'bg-slate-100 text-slate-700';
                                                        }
                                                        ?>">
                                                        <?= htmlspecialchars($item['egg_type']) ?>
                                                    </span>
                                                    <span class="text-xs font-bold text-gray-500">x<?= $item['quantity'] ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>

                                        <td class="px-5 py-4 font-bold text-gray-700 text-center">
                                            <?= $total_qty ?>
                                        </td>

                                        <td class="px-5 py-4">
                                            <span class="px-2.5 py-1 text-[11px] rounded-full font-bold border shadow-sm
                                                <?= $row['delivery_method'] == 'Delivery' 
                                                    ? 'bg-blue-50 text-blue-600 border-blue-200' 
                                                    : 'bg-purple-50 text-purple-600 border-purple-200' ?>">
                                                <?= htmlspecialchars($row['delivery_method']) ?>
                                            </span>
                                        </td>

                                        <td class="px-5 py-4 text-gray-600 font-medium">
                                            <?= htmlspecialchars($row['reservation_date']) ?>
                                        </td>

                                        <td class="px-5 py-4 font-extrabold text-blue-600 text-right text-base">
                                            ₱<?= number_format($row['total_price'], 2) ?>
                                        </td>

                                        <td class="px-5 py-4 text-center flex justify-center space-x-2">
                                             <button onclick="printReceipt(<?= $receipt_json ?>)" class="bg-emerald-500 hover:bg-emerald-600 text-white font-semibold text-xs py-1.5 px-3 rounded shadow transition">
                                                 <i class="fa-solid fa-print"></i>
                                            </button>
                                            <button onclick="openCancelModal('<?= $ids_list ?>')" class="bg-red-500 hover:bg-red-600 text-white font-semibold text-xs py-1.5 px-3 rounded shadow transition">
                                                 <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="px-5 py-12 text-center text-gray-400 font-medium bg-slate-50/50">
                                        <i class="fa-solid fa-folder-open text-3xl mb-3 block text-gray-300"></i>
                                        No reservation histories found matching your criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-3.5 bg-slate-50 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400 font-medium">
                        <div>
                            Showing entries <span class="text-gray-600 font-semibold"><?= $offset + 1 ?></span> to <span class="text-gray-600 font-semibold"><?= min($offset + $limit, $total_records) ?></span> of <span class="text-gray-600 font-semibold"><?= $total_records ?></span> total records.
                        </div>
                    </div>

                </div>

            </div>
            
        </div>
    </div>

    <div id="receipt-print-window" class="hidden"></div>
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

// Optional: Close the modal if the user clicks anywhere outside of the white modal container box
window.addEventListener('click', function(event) {
    const logoutModal = document.getElementById('logout_modal');
    if (event.target === logoutModal) {
        closeLogoutModal();
    }
});
    function printReceipt(data) {
        const printWindow = document.getElementById('receipt-print-window');
        
        const unitPrices = { 
            'Extra Small': 140, 'Small': 150, 'Medium': 175, 'Large': 195, 
            'Extra Large': 235, 'Jumbo': 255, 'Super Jumbo': 270, 'Double Yolk': 320 
        };
        
        let itemsHTML = '';
        data.items.forEach(item => {
            const unitPrice = unitPrices[item.egg_type] || 0;
            const computedPrice = unitPrice * parseInt(item.quantity);
            
            itemsHTML += `
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 12px; font-weight: 600;">🥚 ${item.egg_type} Size</td>
                    <td style="padding: 12px; text-align: center; color: #64748b !important;">₱${unitPrice.toFixed(2)}</td>
                    <td style="padding: 12px; text-align: center; font-weight: 700;">x ${item.quantity}</td>
                    <td style="padding: 12px; text-align: right; font-weight: 700;">₱${computedPrice.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                </tr>
            `;
        });

        printWindow.innerHTML = `
            <div style="font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 750px; height: calc(100vh - 60px); margin: 0 auto; padding: 30px; border: 1px solid #e2e8f0; background-color: #ffffff !important; display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 25px; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <img src="vdvc.png" alt="VDVC Logo" style="width: 55px; height: 55px; object-fit: contain;">
                            <div>
                                <h2 style="margin: 0; font-size: 21px; font-weight: 800; color: #1e293b !important; text-transform: uppercase;">VDVC Egg Farm</h2>
                                <span style="font-size: 11px; background-color: #e0f2fe !important; color: #0369a1 !important; padding: 2px 8px; border-radius: 9999px; font-weight: 600; text-transform: uppercase;">Reservation Receipt</span>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 11px; font-weight: 700; color: #64748b !important; text-transform: uppercase;">Reservation ID</div>
                            <div style="font-size: 19px; font-weight: 800; color: #2563eb !important;">#RES-${data.id}</div>
                        </div>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <h3 style="font-size: 13px; font-weight: 700; color: #1e293b !important; text-transform: uppercase; margin-bottom: 8px;">
                            📋 Contact & Delivery Information
                        </h3>
                        <div style="background-color: #f8fafc !important; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; font-size: 13px; line-height: 1.5;">
                            <div style="margin-bottom: 6px;"><strong>Customer Name:</strong> ${data.customer_name}</div>
                            <div style="margin-bottom: 6px;"><strong>Contact Number:</strong> ${data.contact_number}</div>
                            <div><strong>Delivery Address:</strong> ${data.delivery_address ? data.delivery_address : 'N/A (Store Pickup Specified)'}</div>
                        </div>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <h3 style="font-size: 13px; font-weight: 700; color: #1e293b !important; text-transform: uppercase; margin-bottom: 8px;">
                            📦 Reservation Details
                        </h3>
                        <div style="background-color: #f8fafc !important; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; font-size: 13px; line-height: 1.5;">
                            <div style="margin-bottom: 6px;"><strong>Delivery Method:</strong> ${data.delivery_method}</div>
                            <div><strong>Reservation Date:</strong> ${data.reservation_date}</div>
                        </div>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <h3 style="font-size: 13px; font-weight: 700; color: #1e293b !important; text-transform: uppercase; margin-bottom: 8px;">
                            📊 Order Summary Breakdown
                        </h3>
                        <table style="width: 100%; font-size: 13px; border-collapse: collapse; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                            <tbody style="color: #334155 !important;">
                                ${itemsHTML}
                                <tr style="background-color: #f8fafc !important; font-size: 14px; font-weight: 800;">
                                    <td colspan="3" style="padding: 12px; text-align: right; text-transform: uppercase;">Total Price Amount:</td>
                                    <td style="padding: 12px; text-align: right; color: #2563eb !important; font-size: 17px;">₱${parseFloat(data.total_price).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="text-align: center; border-top: 1px dashed #cbd5e1; padding-top: 20px; font-size: 11px; color: #94a3b8 !important;">
                    <p style="margin: 0; font-weight: 600; color: #64748b !important; font-size: 13px;">Thank you for your reservation with VDVC Egg Farm!</p>
                    <p style="margin: 4px 0 0 0;">System managed securely under &copy; 2026 Egg Reservation Systems.</p>
                </div>
            </div>
        `;

        window.print();
    }
    </script>

<div id="cancelModal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-xl shadow-2xl max-w-sm w-full mx-4">
        <h3 class="text-lg font-bold text-gray-800 mb-2">Confirm Cancellation</h3>
        <p class="text-sm text-gray-600 mb-6">Are you sure you want to cancel this reservation? This action cannot be undone.</p>
        <form id="cancelForm" action="cancel.php" method="POST">
            <input type="hidden" name="id" id="cancelId">
            <div class="flex space-x-3">
                <button type="button" onclick="closeCancelModal()" class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg font-semibold text-sm">Keep</button>
                <button type="submit" class="flex-1 bg-red-500 text-white py-2 rounded-lg font-semibold text-sm">Yes, Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCancelModal(idString) {
    document.getElementById('cancelId').value = idString;
    document.getElementById('cancelModal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
}

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