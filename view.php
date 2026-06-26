<?php
require 'connection.php';

// Get search and filter terms from GET request
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_egg = isset($_GET['egg_type']) ? trim($_GET['egg_type']) : '';
$filter_method = isset($_GET['delivery_method']) ? trim($_GET['delivery_method']) : '';

// Build dynamic SQL query safely
$query = "SELECT * FROM reservations WHERE 1=1";
$params = [];
$types = "";

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

$query .= " ORDER BY id DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VDVC - History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 font-sans text-gray-700 antialiased min-h-screen">

    <div class="flex min-h-screen">
        
        <aside class="w-64 bg-sky-500 text-white flex flex-col flex-shrink-0 shadow-xl">
            <div class="p-6 flex flex-col items-center justify-center border-b border-sky-400/40">
                <img src="vdvc.png" alt="Logo" class="w-50 h-50">
                <span class="font-bold text-lg tracking-wide uppercase">VDVC Egg Farm</span>
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
                <a href="view.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl bg-sky-600 font-semibold shadow-inner transition">
                    <i class="fa-solid fa-clock-rotate-left w-5"></i>
                    <span>Reservation History</span>
                </a>
                <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl hover:bg-sky-600/50 transition font-medium">
                    <i class="fa-solid fa-user w-5"></i>
                    <span>My Profile</span>
                </a>
            </nav>
            
            <div class="p-4 text-center text-xs text-sky-200 border-t border-sky-400/30">
                &copy; 2026 Egg Reservation Systems
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">

            <header class="bg-white border-b border-gray-200 px-8 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-slate-800">📋 Reservation History</h1>
            </header>

            <div class="max-w-7xl w-full mx-auto p-6 space-y-6">

                <div class="bg-white p-4 shadow-md rounded-xl border border-gray-200">
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        
                        <div class="md:col-span-1 relative">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Search Customer</label>
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
                            
                            <!-- Balanced, clean Clear Filters action button -->
                            <a href="?" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold py-2 px-4 rounded-lg text-sm border border-gray-300/70 transition flex items-center justify-center space-x-1" title="Clear Filters">
                                <i class="fa-solid fa-filter-circle-xmark"></i>
                                <span>Clear</span>
                            </a>
                        </div>

                    </form>
                </div>

                <div class="bg-white shadow-md rounded-xl border border-gray-200 overflow-hidden">

                    <div class="p-5 border-b flex justify-between items-center bg-slate-50">
                        <h2 class="text-lg font-bold text-slate-800 flex items-center space-x-2">
                            <i class="fa-solid fa-list-check text-sky-500"></i>
                            <span>📋 Reservation List</span>
                        </h2>
                        <?php if ($search !== '' || $filter_egg !== '' || $filter_method !== ''): ?>
                            <span class="text-xs bg-sky-100 text-sky-700 px-2.5 py-1 rounded-full font-medium">
                                Found <?= $result->num_rows ?> filtered result(s)
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-left">

                            <thead class="bg-slate-100 text-slate-700 uppercase text-xs font-bold tracking-wider border-b border-slate-200">
                                <tr>
                                    <th class="px-5 py-3.5">Customer</th>
                                    <th class="px-5 py-3.5">Contact</th>
                                    <th class="px-5 py-3.5">Address</th>
                                    <th class="px-5 py-3.5">Egg Type</th>
                                    <th class="px-5 py-3.5 text-center">Qty</th>
                                    <th class="px-5 py-3.5">Method</th>
                                    <th class="px-5 py-3.5">Date</th>
                                    <th class="px-5 py-3.5 text-right">Total</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100 bg-white">

                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
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

                                        <td class="px-5 py-4">
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide shadow-sm
                                                <?php
                                                switch($row['egg_type']) {
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
                                                <?= htmlspecialchars($row['egg_type']) ?>
                                            </span>
                                        </td>

                                        <td class="px-5 py-4 font-bold text-gray-700 text-center">
                                            <?= htmlspecialchars($row['quantity']) ?>
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

                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-5 py-12 text-center text-gray-400 font-medium bg-slate-50/50">
                                        <i class="fa-solid fa-folder-open text-3xl mb-3 block text-gray-300"></i>
                                        No reservation histories found matching your criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            </tbody>
                        </table>
                    </div>

                </div>

            </div>
            
        </div>
    </div>

</body>
</html>
<?php 
$stmt->close(); 
?>