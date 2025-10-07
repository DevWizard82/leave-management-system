<?php
require_once 'config.php';
session_start();

$managerId = $_SESSION['user_id'];

$typeId = isset($_GET['type']) && $_GET['type'] !== '' ? intval($_GET['type']) : null;
$startDate = !empty($_GET['start']) ? $_GET['start'] : null;
$endDate = !empty($_GET['end']) ? $_GET['end'] : null;

// Base query
$query = "
    SELECT 
        lr.id, lr.start_date, lr.end_date, lr.days_requested, lr.created_at, 
        u.first_name, u.last_name,
        lt.type_name
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.id
    JOIN leave_types lt ON lr.type_id = lt.id
    WHERE lr.manager_id = ? AND lr.status = 'pending'
";

$params = [$managerId];
$types = "i";

// Add filters
if ($typeId) {
    $query .= " AND lr.type_id = ?";
    $params[] = $typeId;
    $types .= "i";
}
if ($startDate) {
    $query .= " AND lr.start_date >= ?";
    $params[] = $startDate;
    $types .= "s";
}
if ($endDate) {
    $query .= " AND lr.end_date <= ?";
    $params[] = $endDate;
    $types .= "s";
}

$allowedSorts = ['first_name', 'last_name', 'type_name', 'start_date', 'end_date', 'days_requested', 'created_at'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts) ? $_GET['sort'] : 'created_at';
$direction = isset($_GET['direction']) && in_array(strtoupper($_GET['direction']), ['ASC', 'DESC']) ? $_GET['direction'] : 'ASC';

$query .= " ORDER BY $sort $direction";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result();





// Output rows
if ($requests->num_rows === 0) {
    echo '<tr>
            <td colspan="7" class="px-4 py-4 text-center text-gray-500">
                Aucune demande en attente.
            </td>
          </tr>';
    exit;
}

while ($row = $requests->fetch_assoc()) {
    $id = $row['id']; // store ID in a variable for easier use
    $employee = htmlspecialchars($row['first_name'].' '.$row['last_name']);
    $typeName = htmlspecialchars($row['type_name']);
    $start = date("d/m/Y", strtotime($row['start_date']));
    $end = date("d/m/Y", strtotime($row['end_date']));
    $days = $row['days_requested'];
    $submitted = date("d/m/Y", strtotime($row['created_at']));

    echo '<tr class="hover:bg-gray-50 transition">
            <td class="px-4 py-2 border-b">'.$employee.'</td>
            <td class="px-4 py-2 border-b">'.$typeName.'</td>
            <td class="px-4 py-2 border-b">'.$start.'</td>
            <td class="px-4 py-2 border-b">'.$end.'</td>
            <td class="px-4 py-2 border-b">'.$days.'</td>
            <td class="px-4 py-2 border-b">'.$submitted.'</td>
            <td class="px-4 py-2 border-b text-center">
                <div class="flex justify-center gap-2 flex-wrap">
                    <form method="POST">
                        <input type="hidden" name="leave_id" value="'.$id.'">
                        <button type="submit" name="action" value="approved" 
                            class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 transition w-full sm:w-auto">
                            Approuver
                        </button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="leave_id" value="'.$id.'">
                        <button type="submit" name="action" value="refused" 
                            class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition w-full sm:w-auto">
                            Refuser
                        </button>
                    </form>
                </div>
            </td>
          </tr>';
}

