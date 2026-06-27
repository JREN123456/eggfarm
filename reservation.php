<?php
require 'connection.php';

// Start session to store submission status safely across redirect
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Enforcement: Kick back to logging panel if user identifier isn't tracked
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Match the active logged-in parameters
$user_id = $_SESSION['user_id'];
$customer_name = $_SESSION['fullname'] ?? '';
$user_role = $_SESSION['role'] ?? 'Customer';

// Helper function to insert notifications easily into the database
function add_notification($conn, $user_id, $title, $description, $type = 'info') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, description, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $description, $type);
    $stmt->execute();
    $stmt->close();
}

// AJAX API Endpoint Processing: Handle background cancellation logs seamlessly without breaking layout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'log_cancellation') {
    header('Content-Type: application/json');
    add_notification($conn, $user_id, "Draft process cancelled", "Timeline reset to Draft status", "alert");
    echo json_encode(['status' => 'success']);
    exit();
}

// Check if we just redirected from a successful submission (for structural template behaviors)
$message = $_SESSION['success_message'] ?? '';
$is_submitted = $_SESSION['is_submitted'] ?? false;

// Clear session variables immediately so a normal refresh wipes layout updates back to zero
unset($_SESSION['success_message']);
unset($_SESSION['is_submitted']);

// Initialize variables as completely empty on page load/refresh
$contact_number = '';
$delivery_address = '';
$egg_type = '';
$quantity = 0;
$delivery_method = '';
$reservation_date = '';

// Handle form submission and redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reservation'])) {
    // Get form data safely
    $customer_name = $_POST['customer_name'];
    $contact_number = $_POST['contact_number'];
    $delivery_address = $_POST['delivery_address'];
    $egg_type = $_POST['egg_type'];
    $quantity = $_POST['quantity'];
    $delivery_method = $_POST['delivery_method'];
    $reservation_date = $_POST['reservation_date'];

    // Pricing logic
    $prices = [
        'Extra Small' => 140,
        'Small' => 150,
        'Medium' => 175,
        'Large' => 195,
        'Extra Large' => 235,
        'Jumbo' => 255,
        'Super Jumbo' => 270,
        'Double Yolk' => 320
    ];

    $price_per_egg = $prices[$egg_type] ?? 0;
    $total_price = $quantity * $price_per_egg;

    // Insert into database mapping individual user_id tracking records
    $stmt = $conn->prepare("INSERT INTO reservations 
    (user_id, customer_name, contact_number, delivery_address, egg_type, quantity, delivery_method, reservation_date, total_price) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "issssissd",
        $user_id,
        $customer_name,
        $contact_number,
        $delivery_address,
        $egg_type,
        $quantity,
        $delivery_method,
        $reservation_date,
        $total_price
    );

    if ($stmt->execute()) {
        // Log notification entry securely into persistence table 
        add_notification($conn, $user_id, "Order submitted successfully!", "Your order for {$quantity} tray(s) of {$egg_type} eggs is processing.", "success");
    }
    $stmt->close();

    // Store states in session for the immediate next render
    $_SESSION['success_message'] = "Reservation submitted successfully!";
    $_SESSION['is_submitted'] = true;
    
    // Redirect to the exact same page using a clean GET request
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

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

// Calculate total unread counter badge digits dynamically
$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unread_count++;
}

$prices = [
    'Extra Small' => 140,
    'Small' => 150,
    'Medium' => 175,
    'Large' => 195,
    'Extra Large' => 235,
    'Jumbo' => 255,
    'Super Jumbo' => 270,
    'Double Yolk' => 320
];

$price_per_egg = $prices[$egg_type] ?? 0; 
$total_price = $quantity * $price_per_egg;
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VDVC - Customer Reservation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body class="bg-slate-100 font-sans text-gray-700 antialiased min-h-screen">

    <div class="flex min-h-screen">
        
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
                <a href="reservation.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-sky-600/40 transition font-medium">
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
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-red-600/80 transition font-medium text-sky-100 hover:text-white">
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
                <h1 class="text-2xl font-bold text-slate-800">📝 Customer Reservation</h1>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="font-semibold text-sm text-gray-800"><?php echo !empty($customer_name) ? htmlspecialchars($customer_name) : 'Guest'; ?></div>
                        <div class="text-xs text-gray-400">(Customer Access)</div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-purple-200 overflow-hidden border border-gray-300">
                        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=150" alt="Profile">
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

            <div class="max-w-7xl w-full mx-auto p-6">
                <form id="reservation_form" action="" method="POST" onsubmit="return validateForm()" autocomplete="off" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="space-y-4">
                        <h2 class="text-lg font-bold text-slate-800">Step 1: Contact & Delivery Information</h2>
                        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Customer Name</label>
                                <div class="relative">
                                    <input type="text" id="cust_name_input" name="customer_name" placeholder="Enter full name" value="<?php echo htmlspecialchars($customer_name); ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                                    <i class="fa-solid fa-pen absolute right-3 top-3 text-gray-400 text-xs"></i>
                                </div>
                                <span class="text-xs text-gray-400 mt-1 block">Enter Customer Name</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Contact Number</label>
                                <input type="text" id="contact_input" name="contact_number" placeholder="(09XX) XXX-XXXX" value="<?php echo htmlspecialchars($contact_number); ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                                <span class="text-xs text-gray-400 mt-1 block">Enter Contact Number</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Delivery Address</label>
                                <input type="text" id="delivery_address_input" name="delivery_address" placeholder="Type location to auto-pin or drag marker..." value="<?php echo htmlspecialchars($delivery_address); ?>" required oninput="handleAddressTyping(this.value)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 mb-2">
                                
                                <div class="border border-gray-200 rounded-lg overflow-hidden relative h-48 bg-sky-50 z-10" id="map"></div>
                                <span class="text-xs text-gray-400 mt-1 block">Type your exact area address above or drag the marker directly</span>
                            </div>

                        </div>
                    </div>

                    <div class="space-y-4">
                        <h2 class="text-lg font-bold text-slate-800">Step 2: Reservation Details</h2>
                        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Egg Size/Type Selection</label>
                                <div class="grid grid-cols-4 gap-2 mb-3">
                                    <button type="button" data-egg="Extra Small" onclick="setEgg('Extra Small')" class="egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50">
                                        <span class="w-6 h-8 bg-slate-100 border border-slate-400 rounded-full inline-block shadow-inner mb-1"></span>
                                        <span class="text-[10px] text-gray-600 font-medium">Extra Small</span>
                                        <span class="text-[9px] text-blue-600 font-bold mt-0.5">₱140 / tray</span>
                                    </button>
                                    <button type="button" data-egg="Small" onclick="setEgg('Small')" class="egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50">
                                        <span class="w-6 h-8 bg-slate-100 border border-slate-400 rounded-full inline-block shadow-inner mb-1"></span>
                                        <span class="text-[10px] text-gray-600 font-medium">Small</span>
                                        <span class="text-[9px] text-blue-600 font-bold mt-0.5">₱150 / tray</span>
                                    </button>
                                    <button type="button" data-egg="Medium" onclick="setEgg('Medium')" class="egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50">
                                        <span class="w-6 h-8 bg-slate-100 border border-slate-400 rounded-full inline-block shadow-inner mb-1"></span>
                                        <span class="text-[10px] text-gray-600 font-medium">Medium</span>
                                        <span class="text-[9px] text-blue-600 font-bold mt-0.5">₱175 / tray</span>
                                    </button>
                                    <button type="button" data-egg="Large" onclick="setEgg('Large')" class="egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50">
                                        <span class="w-6 h-8 bg-slate-100 border border-slate-400 rounded-full inline-block shadow-inner mb-1"></span>
                                        <span class="text-[10px] text-gray-600 font-medium">Large</span>
                                        <span class="text-[9px] text-blue-600 font-bold mt-0.5">₱195 / tray</span>
                                    </button>
                                    <button type="button" data-egg="Extra Large" onclick="setEgg('Extra Large')" class="egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50">
                                        <span class="w-6 h-8 bg-slate-100 border border-slate-400 rounded-full inline-block shadow-inner mb-1"></span>
                                        <span class="text-[10px] text-gray-600 font-medium">Extra Large</span>
                                        <span class="text-[9px] text-blue-600 font-bold mt-0.5">₱235 / tray</span>
                                    </button>
                                    <button type="button" data-egg="Jumbo" onclick="setEgg('Jumbo')" class="egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50">
                                        <span class="w-6 h-8 bg-slate-100 border border-slate-400 rounded-full inline-block shadow-inner mb-1"></span>
                                        <span class="text-[10px] text-gray-600 font-medium">Jumbo</span>
                                        <span class="text-[9px] text-blue-600 font-bold mt-0.5">₱255 / tray</span>
                                    </button>
                                    <button type="button" data-egg="Super Jumbo" onclick="setEgg('Super Jumbo')" class="egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50">
                                        <span class="w-6 h-8 bg-slate-100 border border-slate-400 rounded-full inline-block shadow-inner mb-1"></span>
                                        <span class="text-[10px] text-gray-600 font-medium">Super Jumbo</span>
                                        <span class="text-[9px] text-blue-600 font-bold mt-0.5">₱270 / tray</span>
                                    </button>
                                    <button type="button" data-egg="Double Yolk" onclick="setEgg('Double Yolk')" class="egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50">
                                        <span class="w-6 h-8 bg-slate-100 border border-slate-400 rounded-full inline-block shadow-inner mb-1"></span>
                                        <span class="text-[10px] text-gray-600 font-medium">Double Yolk</span>
                                        <span class="text-[9px] text-blue-600 font-bold mt-0.5">₱320 / tray</span>
                                    </button>
                                </div>
                                
                                <select name="egg_type" id="egg_type_select" onchange="updateLivePrice()" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-blue-500">
                                    <option value="" <?php echo $egg_type == '' ? 'selected' : ''; ?>>-- Choose Size --</option>
                                    <option value="Extra Small" <?php echo $egg_type == 'Extra Small' ? 'selected' : ''; ?>> Extra Small</option>
                                    <option value="Small" <?php echo $egg_type == 'Small' ? 'selected' : ''; ?>>Small</option>
                                    <option value="Medium" <?php echo $egg_type == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="Large" <?php echo $egg_type == 'Large' ? 'selected' : ''; ?>>Large</option>
                                    <option value="Extra Large" <?php echo $egg_type == 'Extra Large' ? 'selected' : ''; ?>>Extra Large</option>
                                    <option value="Jumbo" <?php echo $egg_type == 'Jumbo' ? 'selected' : ''; ?>>Jumbo</option>
                                    <option value="Super Jumbo" <?php echo $egg_type == 'Super Jumbo' ? 'selected' : ''; ?>>Super Jumbo</option>
                                    <option value="Double Yolk" <?php echo $egg_type == 'Double Yolk' ? 'selected' : ''; ?>>Double Yolk</option>
                                </select>
                                <span class="text-xs text-gray-400 mt-1 block">Select Egg Size/Type</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Quantity</label>
                                <input type="number" id="quantity_input" name="quantity" min="1" oninput="updateLivePrice()" value="<?php echo htmlspecialchars($quantity > 0 ? $quantity : ''); ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                                <span class="text-xs text-gray-400 mt-1 block">Enter Quantity</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Reservation Date</label>
                                <input type="date" id="reservation_date_input" name="reservation_date" min="<?php echo $today; ?>" onchange="updateLivePrice()" value="<?php echo htmlspecialchars($reservation_date); ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                                <span class="text-xs text-gray-400 mt-1 block">Select a date</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Delivery Method</label>
                                <input type="hidden" name="delivery_method" id="delivery_method_input" value="<?php echo htmlspecialchars($delivery_method); ?>">
                                <div class="grid grid-cols-2 gap-4">
                                    <button type="button" id="btn-delivery" onclick="setMethod('Delivery')" class="p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-gray-200 text-gray-500">
                                        <i class="fa-solid fa-truck text-lg"></i>
                                        <span class="text-xs font-semibold">Delivery</span>
                                    </button>
                                    <button type="button" id="btn-pickup" onclick="setMethod('Pickup')" class="p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-gray-200 text-gray-500">
                                        <i class="fa-solid fa-shop text-lg"></i>
                                        <span class="text-xs font-semibold">Pickup</span>
                                    </button>
                                </div>
                                <span class="text-xs text-gray-400 mt-2 block">Select Delivery Method (Delivery / Pickup)</span>
                            </div>

                        </div>
                    </div>

                    <div class="space-y-4">
                        <h2 class="text-lg font-bold text-slate-800">Step 3: Review & Submit</h2>
                        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-6">
                            
                            <div>
                                <h3 class="text-sm font-bold text-slate-700 mb-3">Order Summary</h3>
                                <div class="border border-gray-100 rounded-lg divide-y divide-gray-100 text-sm">
                                    <div class="flex justify-between p-3 bg-gray-50 rounded-t-lg">
                                        <span id="summary_egg_type" class="text-gray-600 font-medium"><?php echo !empty($egg_type) ? htmlspecialchars($egg_type) : 'None selected'; ?></span>
                                        <span id="summary_quantity" class="font-bold text-gray-800">x <?php echo htmlspecialchars($quantity); ?></span>
                                    </div>
                                    <div class="flex justify-between p-3">
                                        <span class="text-gray-500">Method:</span>
                                        <span id="summary_method" class="font-semibold text-gray-700"><?php echo !empty($delivery_method) ? htmlspecialchars($delivery_method) : 'None selected'; ?></span>
                                    </div>
                                    <div class="flex justify-between p-3">
                                        <span class="text-gray-500">Date:</span>
                                        <span id="summary_date" class="font-semibold text-blue-600"><?php echo !empty($reservation_date) ? htmlspecialchars($reservation_date) : 'None selected'; ?></span>
                                    </div>
                                    <div class="flex justify-between p-3">
                                        <span class="text-gray-500">Address:</span>
                                        <span id="summary_address_display" class="font-semibold text-gray-700 max-w-[180px] truncate text-right"><?php echo !empty($delivery_address) ? htmlspecialchars($delivery_address) : 'None'; ?></span>
                                    </div>
                                    <div class="flex justify-between p-3 bg-slate-50 rounded-b-lg items-center">
                                        <span class="font-bold text-slate-800">Total:</span>
                                        <span class="text-lg font-extrabold text-blue-600">P<span id="total_display"><?php echo number_format($total_price); ?></span></span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">Reservation Timeline</h3>
                                <div class="relative flex items-center justify-between px-4">
                                    <div class="absolute left-4 right-4 h-1 bg-gray-200 top-1/2 -translate-y-1/2 z-0"></div>
                                    
                                    <div id="timeline_progress_bar" class="absolute left-4 h-1 bg-blue-500 top-1/2 -translate-y-1/2 z-0 transition-all duration-500 <?php echo $is_submitted ? 'w-full' : 'w-0'; ?>"></div>
                                    
                                    <div class="z-10 flex flex-col items-center">
                                        <div class="w-5 h-5 rounded-full bg-blue-600 border-4 border-white shadow"></div>
                                        <span class="text-xs font-semibold text-blue-600 mt-1">Draft</span>
                                    </div>
                                    
                                    <div class="z-10 flex flex-col items-center">
                                        <div id="node_processing" class="w-5 h-5 rounded-full border-4 border-white shadow bg-gray-300 transition-colors duration-500 <?php echo $is_submitted ? 'bg-blue-600' : 'bg-gray-300'; ?>"></div>
                                        <span id="text_processing" class="text-xs mt-1 transition-colors duration-500 <?php echo $is_submitted ? 'text-blue-600 font-semibold' : 'text-gray-400 font-medium'; ?>">Processing</span>
                                    </div>
                                    
                                    <div class="z-10 flex flex-col items-center">
                                        <div id="node_submitted" class="w-5 h-5 rounded-full border-4 border-white shadow bg-gray-300 transition-colors duration-500 <?php echo $is_submitted ? 'bg-blue-600' : 'bg-gray-300'; ?>"></div>
                                        <span id="text_submitted" class="text-xs font-medium text-gray-400 mt-1 transition-colors duration-500 <?php echo $is_submitted ? 'text-blue-600 font-semibold' : 'text-gray-400 font-medium'; ?>">Submitted</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 pt-2">
                                <button type="submit" name="submit_reservation" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-xl shadow-md transition flex items-center justify-center space-x-2">
                                    <i class="fa-regular fa-circle-check"></i>
                                    <span>Submit Reservation</span>
                                </button>
                                
                                <button type="button" onclick="openCancellationModal()" class="w-full text-center text-sm text-gray-500 hover:text-red-500 transition font-medium py-1 block">
                                    <i class="fa-solid fa-xmark mr-1"></i> Cancel pending reservation
                                </button>
                            </div>

                        </div>
                    </div>

                </form>
            </div>
            
        </div>
    </div>

    <!-- Success Modal Window Popup -->
    <div id="success_modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
        <div class="bg-white rounded-xl shadow-2xl border border-gray-100 max-w-md w-full p-6 text-center space-y-4 scale-95 transition-transform duration-200">
            <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto text-3xl">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="space-y-1">
                <h3 class="text-xl font-bold text-slate-800">Success!</h3>
                <p id="success_modal_message" class="text-sm text-gray-500 leading-relaxed">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            </div>
            <div class="pt-2">
                <button type="button" onclick="closeSuccessModal()" class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold px-4 py-2 rounded-lg text-sm shadow-md transition">
                    Great, Thank You!
                </button>
            </div>
        </div>
    </div>

    <div id="cancellation_modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[100] p-4 animate-fade-in">
        <div class="bg-white rounded-xl shadow-2xl border border-gray-100 max-w-md w-full p-6 space-y-4 scale-95 transition-transform duration-200">
            <div class="flex items-center space-x-3 text-amber-500">
                <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
                <h3 class="text-lg font-bold text-slate-800">Cancel Reservation?</h3>
            </div>
            <p class="text-sm text-gray-500 leading-relaxed">
                Are you sure you want to cancel your pending reservation workflow? All fields will be wiped and reset. This action cannot be undone.
            </p>
            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" onclick="closeCancellationModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold px-4 py-2 rounded-lg text-sm transition">
                    Keep Editing
                </button>
                <button type="button" onclick="executeCancellation()" class="bg-red-500 hover:bg-red-600 text-white font-semibold px-4 py-2 rounded-lg text-sm shadow-md transition">
                    Yes, Cancel Process
                </button>
            </div>
        </div>
    </div>

    <script>
        const priceList = { 'Extra Small': 140, 'Small': 150, 'Medium': 175, 'Large': 195, 'Extra Large': 235, 'Jumbo': 255, 'Super Jumbo': 270, 'Double Yolk': 320 };
        let addressLookupTimeout = null;

        // Leaflet Map Configuration
        const defaultLat = 14.5995;
        const defaultLng = 120.9842;
        const map = L.map('map').setView([defaultLat, defaultLng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        const marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

        function reverseGeocode(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.display_name) {
                        const address = data.display_name;
                        document.getElementById('delivery_address_input').value = address;
                        updateSummaryAddress(address);
                    }
                })
                .catch(err => console.warn(err));
        }

        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            reverseGeocode(e.latlng.lat, e.latlng.lng);
        });

        marker.on('dragend', function(e) {
            const position = marker.getLatLng();
            reverseGeocode(position.lat, position.lng);
        });

        function updateSummaryAddress(value) {
            document.getElementById('summary_address_display').innerText = value ? value : 'None';
        }

        function handleAddressTyping(value) {
            updateSummaryAddress(value);
            clearTimeout(addressLookupTimeout);
            if (!value.trim()) return;

            addressLookupTimeout = setTimeout(() => {
                fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(value)}&limit=1`)
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.length > 0) {
                            const lat = parseFloat(data[0].lat);
                            const lon = parseFloat(data[0].lon);
                            
                            marker.setLatLng([lat, lon]);
                            map.setView([lat, lon], 16); 
                        }
                    })
                    .catch(err => console.warn("Auto-pin address lookup failed: ", err));
            }, 600);
        }

        function openSuccessModal() {
            document.getElementById('success_modal').classList.remove('hidden');
        }

        function closeSuccessModal() {
            document.getElementById('success_modal').classList.add('hidden');
        }

        function openCancellationModal() {
            document.getElementById('cancellation_modal').classList.remove('hidden');
        }

        function closeCancellationModal() {
            document.getElementById('cancellation_modal').classList.add('hidden');
        }

        function executeCancellation() {
            closeCancellationModal();
            
            // Send an asynchronous post request via Fetch API to record the cancellation into database logs natively
            fetch('?action=log_cancellation', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    // Prepend notification element instantly inside the active user interface layout matching database structure
                    prependCancellationUiNotification();
                }
            })
            .catch(error => console.error('Error recording cancellation log:', error));

            // Clear Form State fields
            document.getElementById('cust_name_input').value = '';
            document.getElementById('contact_input').value = '';
            document.getElementById('delivery_address_input').value = '';
            document.getElementById('quantity_input').value = '';
            document.getElementById('egg_type_select').value = '';
            document.getElementById('delivery_method_input').value = '';
            document.getElementById('reservation_date_input').value = '';
            
            document.querySelectorAll('.egg-btn').forEach(btn => {
                btn.className = "egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50";
            });
            document.getElementById('btn-delivery').className = "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-gray-200 text-gray-500";
            document.getElementById('btn-pickup').className = "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-gray-200 text-gray-500";
            
            updateLivePrice();
            updateSummaryAddress('');

            resetTimelineTracker();
        }

        function resetTimelineTracker() {
            const bar = document.getElementById('timeline_progress_bar');
            const nodeProcessing = document.getElementById('node_processing');
            const textProcessing = document.getElementById('text_processing');
            const nodeSubmitted = document.getElementById('node_submitted');
            const textSubmitted = document.getElementById('text_submitted');

            if (bar) bar.style.width = '0%';
            if (nodeProcessing) nodeProcessing.className = "w-5 h-5 rounded-full border-4 border-white shadow bg-gray-300 transition-colors duration-500";
            if (textProcessing) textProcessing.className = "text-xs font-medium text-gray-400 mt-1 transition-colors duration-500";
            if (nodeSubmitted) nodeSubmitted.className = "w-5 h-5 rounded-full border-4 border-white shadow bg-gray-300 transition-colors duration-500";
            if (textSubmitted) textSubmitted.className = "text-xs font-medium text-gray-400 mt-1 transition-colors duration-500";
        }

        function prependCancellationUiNotification() {
            const badge = document.getElementById('bell_badge');
            if (badge) badge.classList.remove('hidden');

            const unreadCount = document.getElementById('unread_count');
            let currentUnread = parseInt(unreadCount.innerText) || 0;
            currentUnread++;
            unreadCount.innerText = currentUnread + " New";
            unreadCount.className = "text-xs bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full font-medium";

            const list = document.getElementById('notification_list');
            const placeholder = document.getElementById('empty_notification_placeholder');
            if(placeholder) placeholder.remove();

            const newNotifHtml = `
                <div class="p-4 hover:bg-slate-50 transition flex space-x-3 bg-blue-50/30">
                    <div class="bg-red-100 text-red-600 rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-700 font-semibold mb-0.5 truncate">Draft process cancelled</p>
                        <p class="text-[11px] text-gray-500 break-words mb-1">Timeline reset to Draft status</p>
                        <p class="text-[9px] text-gray-400">Just now</p>
                    </div>
                </div>
            `;
            list.insertAdjacentHTML('afterbegin', newNotifHtml);
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

        document.addEventListener('click', function() {
            if (document.getElementById('notification_dropdown')) {
                document.getElementById('notification_dropdown').classList.add('hidden');
            }
        });

        function setEgg(val) {
            document.getElementById('egg_type_select').value = val;
            const buttons = document.querySelectorAll('.egg-btn');
            buttons.forEach(btn => {
                if(btn.getAttribute('data-egg') === val) {
                    btn.className = "egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-blue-500 bg-blue-50";
                } else {
                    btn.className = "egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50";
                }
            });
            updateLivePrice();
        }

        function setMethod(method) {
            document.getElementById('delivery_method_input').value = method;
            document.getElementById('summary_method').innerText = method;
            const deliveryBtn = document.getElementById('btn-delivery');
            const pickupBtn = document.getElementById('btn-pickup');
            
            if(method === 'Delivery') {
                deliveryBtn.className = "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-blue-500 bg-blue-50 text-blue-600";
                pickupBtn.className = "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-gray-200 text-gray-500";
            } else {
                pickupBtn.className = "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-blue-500 bg-blue-50 text-blue-600";
                deliveryBtn.className = "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-gray-200 text-gray-500";
            }
        }

        function updateLivePrice() {
            const size = document.getElementById('egg_type_select').value;
            const qtyInput = document.getElementById('quantity_input').value;
            const dateInput = document.getElementById('reservation_date_input').value;
            const qty = qtyInput ? parseInt(qtyInput) : 0;
            
            document.getElementById('summary_egg_type').innerText = size ? size : 'None selected';
            document.getElementById('summary_quantity').innerText = 'x ' + qty;
            document.getElementById('summary_date').innerText = dateInput ? dateInput : 'None selected';

            const unitPrice = priceList[size] || 0;
            const liveTotal = unitPrice * qty;
            
            document.getElementById('total_display').innerText = liveTotal.toLocaleString();
        }

        function validateForm() {
            const deliveryMethod = document.getElementById('delivery_method_input').value;
            if (!deliveryMethod) {
                alert('Please select a Delivery Method (Delivery or Pickup) before submitting.');
                return false;
            }

            const chosenDate = document.getElementById('reservation_date_input').value;
            const todayStr = new Date().toISOString().split('T')[0];
            if (chosenDate < todayStr) {
                alert('The reservation date cannot be in the past.');
                return false;
            }

            return true;
        }

        window.addEventListener('pageshow', (event) => {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                document.getElementById('reservation_form').reset();
            }
        });

        // Trigger popup upon post-submission load dynamically
        <?php if (!empty($message)): ?>
            document.addEventListener('DOMContentLoaded', () => {
                openSuccessModal();
            });
        <?php endif; ?>
    </script>
</body>
</html>