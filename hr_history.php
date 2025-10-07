<?php
require_once 'hr_header.php';
require_once 'config.php';

$hrId = $_SESSION['user_id'];

// Fetch only HR's leave history: cancelled, refused, or past approved
$stmt = $conn->prepare("
    SELECT lr.id, lr.start_date, lr.end_date, lr.status, lr.created_at, lt.type_name
    FROM leave_requests lr
    JOIN leave_types lt ON lr.type_id = lt.id
    WHERE lr.user_id = ? 
      AND (
            lr.status IN ('cancelled', 'refused') 
            OR (lr.status = 'approved' AND lr.end_date < CURDATE())
      )
    ORDER BY lr.created_at DESC
");
$stmt->bind_param("i", $hrId);
$stmt->execute();
$result = $stmt->get_result();
?>

<div id="hr-history-page" class="page-content p-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-4">Historique de vos demandes</h2>

    <div class="bg-white rounded-lg shadow-md p-6 overflow-x-auto">
        <table class="min-w-full border-collapse border border-gray-200">
            <thead>
                <tr class="bg-gray-100 text-left">
                    <th class="px-4 py-2 border-b">Type de congé</th>
                    <th class="px-4 py-2 border-b">Date de demande</th>
                    <th class="px-4 py-2 border-b">Date de début</th>
                    <th class="px-4 py-2 border-b">Date de fin</th>
                    <th class="px-4 py-2 border-b">Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-2 text-center text-gray-500">Aucun historique trouvé</td>
                    </tr>
                <?php else: ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($row['type_name']); ?></td>
                            <td class="px-4 py-2 border-b"><?= date("d/m/Y", strtotime($row['created_at'])); ?></td>
                            <td class="px-4 py-2 border-b"><?= date("d/m/Y", strtotime($row['start_date'])); ?></td>
                            <td class="px-4 py-2 border-b"><?= date("d/m/Y", strtotime($row['end_date'])); ?></td>
                            <td class="px-4 py-2 border-b">
                                <?php
                                switch($row['status']){
                                    case 'approved':
                                        echo '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">Approuvé</span>';
                                        break;
                                    case 'refused':
                                        echo '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-red-100 text-red-700 rounded-full">Refusé</span>';
                                        break;
                                    case 'cancelled':
                                        echo '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">Annulé</span>';
                                        break;
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="common.js?v=<?= time(); ?>"></script>
<script>
async function fetchHRHistory() {
    try {
        const res = await fetch('hr_history_data.php');
        const data = await res.json();

        const tbody = document.querySelector('#hr-history-page tbody');
        tbody.innerHTML = '';

        if (!data.error && data.history.length > 0) {
            data.history.forEach(row => {
                const statusLabel = {
                    approved: '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">Approuvé</span>',
                    refused: '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-red-100 text-red-700 rounded-full">Refusé</span>',
                    cancelled: '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">Annulé</span>'
                }[row.status] || row.status;

                tbody.innerHTML += `
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-2 border-b">${row.type_name}</td>
                        <td class="px-4 py-2 border-b">${new Date(row.created_at).toLocaleDateString('fr-FR')}</td>
                        <td class="px-4 py-2 border-b">${new Date(row.start_date).toLocaleDateString('fr-FR')}</td>
                        <td class="px-4 py-2 border-b">${new Date(row.end_date).toLocaleDateString('fr-FR')}</td>
                        <td class="px-4 py-2 border-b">${statusLabel}</td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-2 text-center text-gray-500">Aucun historique trouvé</td></tr>';
        }
    } catch (err) {
        console.error('Error fetching HR history:', err);
    }
}

// Initial fetch
fetchHRHistory();
// Refresh every 5 seconds
setInterval(fetchHRHistory, 5000);
</script>
</body>
</html>
