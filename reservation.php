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

// AJAX API Endpoint Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'log_cancellation') {
    header('Content-Type: application/json');
    add_notification($conn, $user_id, "Draft process cancelled", "Timeline reset to Draft status", "alert");
    echo json_encode(['status' => 'success']);
    exit();
}

$message = $_SESSION['success_message'] ?? '';
$is_submitted = $_SESSION['is_submitted'] ?? false;

unset($_SESSION['success_message']);
unset($_SESSION['is_submitted']);

// Price list configuration
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

$today = date('Y-m-d');

// Handle form submission and redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reservation'])) {
    $customer_name = $_POST['customer_name'];
    $contact_number = $_POST['contact_number'];
    $delivery_address = $_POST['delivery_address'];
    $delivery_method = $_POST['delivery_method'];
    $reservation_date = $_POST['reservation_date'];
    
    // Grab item bundle arrays
    $egg_types = $_POST['egg_types'] ?? [];
    $quantities = $_POST['quantities'] ?? [];

    if (!empty($egg_types) && count($egg_types) === count($quantities)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO reservations 
            (user_id, customer_name, contact_number, delivery_address, egg_type, quantity, delivery_method, reservation_date, total_price) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $total_bundle_trays = 0;

            for ($i = 0; $i < count($egg_types); $i++) {
                $egg_type = $egg_types[$i];
                $quantity = intval($quantities[$i]);
                if ($quantity <= 0) continue;

                $price_per_egg = $prices[$egg_type] ?? 0;
                $total_price = $quantity * $price_per_egg;
                $total_bundle_trays += $quantity;

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
                $stmt->execute();
            }
            $stmt->close();
            
            add_notification($conn, $user_id, "Bundle order submitted!", "Your bundle of {$total_bundle_trays} total tray(s) is processing.", "success");
            $conn->commit();

            $_SESSION['success_message'] = "Reservation bundle submitted successfully!";
            $_SESSION['is_submitted'] = true;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['success_message'] = "Error processing your bundle reservation.";
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch user notifications
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
    <title>VDVC - Customer Reservation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="bg-slate-100 font-sans text-gray-700 antialiased min-h-screen">

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-sky-500 text-white flex flex-col flex-shrink-0 shadow-xl">
        <div class="p-6 flex flex-col items-center justify-center border-b border-sky-400/40">
                <!-- Reverted back to traditional application logo file branding asset -->
                <img src="vdvc.png" alt="Logo" class="w-50 h-50 object-contain mb-2">
                <span class="font-bold text-lg tracking-wide uppercase">VDVC Egg Farm</span>
                <span class="mt-1 text-[10px] bg-sky-600 px-2 py-0.5 rounded-full font-semibold uppercase tracking-wider text-sky-100">
                    <?= htmlspecialchars($user_role) ?> Panel
                </span>
            </div>
            <nav class="flex-1 p-4 space-y-2 mt-4">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-chart-pie w-5"></i><span>Dashboard</span>
                </a>
                <a href="reservation.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-sky-600/40 transition font-medium">
                    <i class="fa-solid fa-calendar-check w-5"></i><span>Reservation</span>
                </a>
                <a href="view.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-clock-rotate-left w-5"></i><span>Reservation History</span>
                </a>
                <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-user w-5"></i><span>My Profile</span>
                </a>
                <hr class="border-sky-400/30 my-2">
                <!-- Locate this inside your <nav> tag and replace it with this: -->
<a href="javascript:void(0);" onclick="openLogoutModal()" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-red-600/80 transition font-medium text-sky-100 hover:text-white">
    <i class="fa-solid fa-right-from-bracket w-5"></i>
    <span>Logout</span>
</a>
            </nav>
            <div class="p-4 text-center text-xs text-sky-200 border-t border-sky-400/30">&copy; 2026 Egg Reservation Systems</div>
        </aside>

        <!-- Main Workspace -->
        <div class="flex-1 flex flex-col min-w-0">
            <header class="bg-white border-b border-gray-200 px-8 py-4 flex justify-between items-center relative">
                <h1 class="text-2xl font-bold text-slate-800">📝 Customer Reservation</h1>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="font-semibold text-sm text-gray-800"><?= !empty($customer_name) ? htmlspecialchars($customer_name) : 'Guest'; ?></div>
                        <div class="text-xs text-gray-400">(Customer Access)</div>
                    </div>
                    <!-- Kept user account actual photo here on the top corner context -->
                    <div class="w-10 h-10 rounded-full bg-purple-200 overflow-hidden border border-gray-300">
                        <img src="<?= $avatar_src ?>" alt="Profile" class="w-full h-full object-cover">
                    </div>
                    <!-- Notification Bell Component -->
                    <div class="relative">
                        <div onclick="toggleNotifications(event)" class="cursor-pointer relative p-1 hover:bg-gray-100 rounded-full transition">
                            <i class="fa-regular fa-bell text-gray-500 text-xl"></i>
                            <span id="bell_badge" class="absolute top-1 right-1 w-2.5 h-2.5 bg-blue-500 rounded-full border-2 border-white <?= $unread_count > 0 ? '' : 'hidden'; ?>"></span>
                        </div>
                        <div id="notification_dropdown" class="hidden absolute right-0 mt-3 w-80 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                                <span class="font-bold text-sm text-slate-800">Notifications</span>
                                <span id="unread_count" class="text-xs <?= $unread_count > 0 ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400'; ?> px-2 py-0.5 rounded-full font-medium">
                                    <?= $unread_count > 0 ? $unread_count . ' New' : '0 New'; ?>
                                </span>
                            </div>
                            <div id="notification_list" class="divide-y divide-gray-100 max-h-60 overflow-y-auto">
                                <?php if (!empty($notifications)): ?>
                                    <?php foreach ($notifications as $notif): 
                                        $bg_class = !$notif['is_read'] ? 'bg-blue-50/30' : '';
                                        $icon_bg = 'bg-blue-100 text-blue-600'; $icon_fa = 'fa-solid fa-circle-info';
                                        if ($notif['type'] === 'success') { $icon_bg = 'bg-green-100 text-green-600'; $icon_fa = 'fa-solid fa-circle-check'; }
                                        elseif ($notif['type'] === 'alert') { $icon_bg = 'bg-red-100 text-red-600'; $icon_fa = 'fa-solid fa-triangle-exclamation'; }
                                    ?>
                                        <div class="p-4 hover:bg-slate-50 transition flex space-x-3 <?= $bg_class; ?>">
                                            <div class="<?= $icon_bg; ?> rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0 mt-0.5">
                                                <i class="<?= $icon_fa; ?> text-xs"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs text-gray-700 font-semibold mb-0.5 truncate"><?= htmlspecialchars($notif['title']); ?></p>
                                                <p class="text-[11px] text-gray-500 break-words mb-1"><?= htmlspecialchars($notif['description']); ?></p>
                                                <p class="text-[9px] text-gray-400"><?= date('M d, g:i a', strtotime($notif['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div id="empty_notification_placeholder" class="p-8 text-center text-gray-400 text-xs">
                                        <i class="fa-regular fa-bell-slash text-2xl mb-2 block text-gray-300"></i>No new notifications
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="max-w-7xl w-full mx-auto p-6">
                <form id="reservation_form" action="" method="POST" onsubmit="return validateForm()" autocomplete="off" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Step 1: Info -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-bold text-slate-800">Step 1: Contact & Delivery Information</h2>
                        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Customer Name</label>
                                <div class="relative">
                                    <input type="text" id="cust_name_input" name="customer_name" placeholder="Enter full name" value="<?= htmlspecialchars($customer_name); ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                                    <i class="fa-solid fa-pen absolute right-3 top-3 text-gray-400 text-xs"></i>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Contact Number</label>
                                <input type="text" id="contact_input" name="contact_number" placeholder="(09XX) XXX-XXXX" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Delivery Address</label>
                                <input type="text" id="delivery_address_input" name="delivery_address" placeholder="Type location..." required oninput="handleAddressTyping(this.value)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 mb-2">
                                <div class="border border-gray-200 rounded-lg overflow-hidden relative h-48 bg-sky-50 z-10" id="map"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Bundle Items Configurator -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-bold text-slate-800">Step 2: Add Items to Bundle</h2>
                        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Egg Size Selection</label>
                                <div class="grid grid-cols-4 gap-2 mb-3">
                                    <?php foreach($prices as $size => $price): ?>
                                        <button type="button" data-egg="<?= $size ?>" onclick="setEgg('<?= $size ?>')" class="egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50">
                                            <span class="w-5 h-7 bg-slate-100 border border-slate-400 rounded-full inline-block shadow-inner mb-1"></span>
                                            <span class="text-[9px] text-gray-600 font-medium text-center leading-none mb-1"><?= $size ?></span>
                                            <span class="text-[9px] text-blue-600 font-bold">₱<?= $price ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <select id="egg_type_select" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-blue-500">
                                    <option value="">-- Choose Size --</option>
                                    <?php foreach($prices as $size => $price): ?>
                                        <option value="<?= $size ?>"><?= $size ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Quantity</label>
                                <input type="number" id="quantity_input" min="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                            </div>

                            <button type="button" onclick="addItemToBundle()" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-medium py-2 px-4 rounded-lg text-xs transition flex items-center justify-center space-x-2">
                                <i class="fa-solid fa-plus"></i>
                                <span>Add To Cart</span>
                            </button>

                            <hr class="border-gray-100 my-2">

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Reservation Date</label>
                                <input type="date" id="reservation_date_input" name="reservation_date" min="<?= $today ?>" onchange="updateLiveSummary()" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Delivery Method</label>
                                <input type="hidden" name="delivery_method" id="delivery_method_input" required>
                                <div class="grid grid-cols-2 gap-4">
                                    <button type="button" id="btn-delivery" onclick="setMethod('Delivery')" class="p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-gray-200 text-gray-500">
                                        <i class="fa-solid fa-truck text-lg"></i><span class="text-xs font-semibold">Delivery</span>
                                    </button>
                                    <button type="button" id="btn-pickup" onclick="setMethod('Pickup')" class="p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-gray-200 text-gray-500">
                                        <i class="fa-solid fa-shop text-lg"></i><span class="text-xs font-semibold">Pickup</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Bundle Checkout Review -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-bold text-slate-800">Step 3: Review Bundle & Submit</h2>
                        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-6">
                            <div>
                                <h3 class="text-sm font-bold text-slate-700 mb-3">Bundle Items List</h3>
                                <!-- Container for Hidden Form Array Inputs -->
                                <div id="hidden_bundle_inputs"></div>
                                
                                <div class="border border-gray-100 rounded-lg overflow-hidden text-sm">
                                    <table class="w-full text-left border-collapse">
                                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                                            <tr>
                                                <th class="p-3">Item</th>
                                                <th class="p-3 text-center">Qty</th>
                                                <th class="p-3 text-right">Total</th>
                                                <th class="p-3 text-center"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="bundle_table_body" class="divide-y divide-gray-100">
                                            <tr>
                                                <td colspan="4" class="p-4 text-center text-gray-400 text-xs">No items added to the bundle yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    
                                    <div class="divide-y divide-gray-100 bg-gray-50 p-3 text-xs space-y-2 border-t border-gray-100">
                                        <div class="flex justify-between"><span class="text-gray-500">Method:</span><span id="summary_method" class="font-semibold text-gray-700">None selected</span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Date:</span><span id="summary_date" class="font-semibold text-blue-600">None selected</span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Address:</span><span id="summary_address_display" class="font-semibold text-gray-700 max-w-[180px] truncate text-right">None</span></div>
                                    </div>
                                    <div class="flex justify-between p-4 bg-slate-50 items-center border-t border-gray-200">
                                        <span class="font-bold text-slate-800">Grand Total:</span>
                                        <span class="text-xl font-extrabold text-blue-600">₱<span id="total_display">0</span></span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">Reservation Timeline</h3>
                                <div class="relative flex items-center justify-between px-4">
                                    <div class="absolute left-4 right-4 h-1 bg-gray-200 top-1/2 -translate-y-1/2 z-0"></div>
                                    <div id="timeline_progress_bar" class="absolute left-4 h-1 bg-blue-500 top-1/2 -translate-y-1/2 z-0 transition-all duration-500 <?= $is_submitted ? 'w-full' : 'w-0'; ?>"></div>
                                    <div class="z-10 flex flex-col items-center">
                                        <div class="w-5 h-5 rounded-full bg-blue-600 border-4 border-white shadow"></div>
                                        <span class="text-xs font-semibold text-blue-600 mt-1">Draft</span>
                                    </div>
                                    <div class="z-10 flex flex-col items-center">
                                        <div class="w-5 h-5 rounded-full border-4 border-white shadow bg-gray-300 <?= $is_submitted ? 'bg-blue-600' : 'bg-gray-300'; ?>"></div>
                                        <span class="text-xs mt-1 <?= $is_submitted ? 'text-blue-600 font-semibold' : 'text-gray-400 font-medium'; ?>">Processing</span>
                                    </div>
                                    <div class="z-10 flex flex-col items-center">
                                        <div class="w-5 h-5 rounded-full border-4 border-white shadow bg-gray-300 <?= $is_submitted ? 'bg-blue-600' : 'bg-gray-300'; ?>"></div>
                                        <span class="text-xs mt-1 <?= $is_submitted ? 'text-blue-600 font-semibold' : 'text-gray-400 font-medium'; ?>">Submitted</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 pt-2">
                                <button type="submit" name="submit_reservation" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-xl shadow-md transition flex items-center justify-center space-x-2">
                                    <i class="fa-regular fa-circle-check"></i><span>Submit Bundle Reservation</span>
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

    <!-- Modals -->
    <div id="success_modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
        <div class="bg-white rounded-xl shadow-2xl border border-gray-100 max-w-md w-full p-6 text-center space-y-4">
            <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto text-3xl"><i class="fa-solid fa-circle-check"></i></div>
            <div><h3 class="text-xl font-bold text-slate-800">Success!</h3><p class="text-sm text-gray-500"><?= htmlspecialchars($message); ?></p></div>
            <button type="button" onclick="closeSuccessModal()" class="w-full bg-green-500 text-white font-semibold py-2 rounded-lg text-sm transition">Great, Thank You!</button>
        </div>
    </div>

    <div id="cancellation_modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
        <div class="bg-white rounded-xl shadow-2xl border border-gray-100 max-w-md w-full p-6 space-y-4">
            <div class="flex items-center space-x-3 text-amber-500"><i class="fa-solid fa-triangle-exclamation text-2xl"></i><h3 class="text-lg font-bold text-slate-800">Cancel Reservation?</h3></div>
            <p class="text-sm text-gray-500">Are you sure you want to cancel? All bundle items will be cleared.</p>
            <div class="flex justify-end space-x-3"><button type="button" onclick="closeCancellationModal()" class="bg-gray-100 px-4 py-2 rounded-lg text-sm">Keep Editing</button><button type="button" onclick="executeCancellation()" class="bg-red-500 text-white px-4 py-2 rounded-lg text-sm">Yes, Cancel</button></div>
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

// Optional: Close the modal if the user clicks anywhere outside of the white modal container box
window.addEventListener('click', function(event) {
    const logoutModal = document.getElementById('logout_modal');
    if (event.target === logoutModal) {
        closeLogoutModal();
    }
});


        const priceList = { 'Extra Small': 140, 'Small': 150, 'Medium': 175, 'Large': 195, 'Extra Large': 235, 'Jumbo': 255, 'Super Jumbo': 270, 'Double Yolk': 320 };
        let bundleItems = [];
        let addressLookupTimeout = null;

        // Leaflet Setup
        const map = L.map('map').setView([14.5995, 120.9842], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        const marker = L.marker([14.5995, 120.9842], { draggable: true }).addTo(map);

        function reverseGeocode(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(res => res.json()).then(data => {
                    if (data && data.display_name) {
                        document.getElementById('delivery_address_input').value = data.display_name;
                        document.getElementById('summary_address_display').innerText = data.display_name;
                    }
                });
        }
        map.on('click', e => { marker.setLatLng(e.latlng); reverseGeocode(e.latlng.lat, e.latlng.lng); });
        marker.on('dragend', () => { reverseGeocode(marker.getLatLng().lat, marker.getLatLng().lng); });

        function handleAddressTyping(val) {
            document.getElementById('summary_address_display').innerText = val || 'None';
            clearTimeout(addressLookupTimeout);
            if (!val.trim()) return;
            addressLookupTimeout = setTimeout(() => {
                fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(val)}&limit=1`)
                    .then(res => res.json()).then(data => {
                        if (data.length > 0) {
                            map.setView([data[0].lat, data[0].lon], 16);
                            marker.setLatLng([data[0].lat, data[0].lon]);
                        }
                    });
            }, 600);
        }

        // Bundle Logic Operations
        function setEgg(val) {
            document.getElementById('egg_type_select').value = val;
            document.querySelectorAll('.egg-btn').forEach(btn => {
                btn.className = btn.getAttribute('data-egg') === val 
                    ? "egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-blue-500 bg-blue-50"
                    : "egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50";
            });
        }

        function addItemToBundle() {
            const size = document.getElementById('egg_type_select').value;
            const qty = parseInt(document.getElementById('quantity_input').value);

            if (!size || isNaN(qty) || qty <= 0) {
                alert('Please select a valid size and quantity.');
                return;
            }

            const existingItem = bundleItems.find(item => item.size === size);
            if (existingItem) {
                existingItem.qty += qty;
            } else {
                bundleItems.push({ size, qty, price: priceList[size] });
            }

            document.getElementById('quantity_input').value = '';
            updateLiveSummary();
        }

        function removeBundleItem(index) {
            bundleItems.splice(index, 1);
            updateLiveSummary();
        }

        function updateLiveSummary() {
            const tbody = document.getElementById('bundle_table_body');
            const hiddenInputs = document.getElementById('hidden_bundle_inputs');
            tbody.innerHTML = '';
            hiddenInputs.innerHTML = '';

            let grandTotal = 0;

            if (bundleItems.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-gray-400 text-xs">No items added to the bundle yet.</td></tr>`;
            } else {
                bundleItems.forEach((item, index) => {
                    const rowTotal = item.price * item.qty;
                    grandTotal += rowTotal;

                    tbody.innerHTML += `
                        <tr>
                            <td class="p-3 font-medium text-gray-700">${item.size}</td>
                            <td class="p-3 text-center font-bold text-gray-600">${item.qty}</td>
                            <td class="p-3 text-right font-semibold text-gray-800">₱${rowTotal.toLocaleString()}</td>
                            <td class="p-3 text-center">
                                <button type="button" onclick="removeBundleItem(${index})" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-trash-can"></i></button>
                            </td>
                        </tr>`;

                    hiddenInputs.innerHTML += `
                        <input type="hidden" name="egg_types[]" value="${item.size}">
                        <input type="hidden" name="quantities[]" value="${item.qty}">`;
                });
            }

            document.getElementById('total_display').innerText = grandTotal.toLocaleString();
            document.getElementById('summary_date').innerText = document.getElementById('reservation_date_input').value || 'None selected';
        }

        function setMethod(method) {
            document.getElementById('delivery_method_input').value = method;
            document.getElementById('summary_method').innerText = method;
            document.getElementById('btn-delivery').className = method === 'Delivery' ? "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 border-blue-500 bg-blue-50 text-blue-600" : "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 border-gray-200 text-gray-500";
            document.getElementById('btn-pickup').className = method === 'Pickup' ? "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 border-blue-500 bg-blue-50 text-blue-600" : "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 border-gray-200 text-gray-500";
        }

        function validateForm() {
            if (bundleItems.length === 0) { alert('Please add at least one item row to your bundle layout.'); return false; }
            if (!document.getElementById('delivery_method_input').value) { alert('Please choose a delivery configuration.'); return false; }
            return true;
        }

        function openSuccessModal() { document.getElementById('success_modal').classList.remove('hidden'); }
        function closeSuccessModal() { document.getElementById('success_modal').classList.add('hidden'); }
        function openCancellationModal() { document.getElementById('cancellation_modal').classList.remove('hidden'); }
        function closeCancellationModal() { document.getElementById('cancellation_modal').classList.add('hidden'); }

        function executeCancellation() {
            closeCancellationModal();
            fetch('?action=log_cancellation', { method: 'POST' })
            .then(res => res.json()).then(data => { if(data.status === 'success') location.reload(); });
        }

        function toggleNotifications(e) { e.stopPropagation(); document.getElementById('notification_dropdown').classList.toggle('hidden'); }
        document.addEventListener('click', () => document.getElementById('notification_dropdown').classList.add('hidden'));

        <?php if (!empty($message)): ?>
            document.addEventListener('DOMContentLoaded', openSuccessModal);
        <?php endif; ?>
    </script>
</body>
</html>