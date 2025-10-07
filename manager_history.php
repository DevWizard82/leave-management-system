<?php
require_once 'manager_header.php';
require_once 'config.php';

$userId = $_SESSION['user_id'];

// Fetch only finished or rejected/cancelled requests
$stmt = $conn->prepare("
    SELECT lr.id, lr.start_date, lr.end_date, lr.status, lr.created_at, lr.days_requested, lt.type_name
    FROM leave_requests lr
    JOIN leave_types lt ON lr.type_id = lt.id
    WHERE lr.user_id = ? 
    AND (
            lr.status = 'cancelled' 
            OR lr.status = 'refused' 
            OR (lr.status = 'approved' AND lr.end_date < CURDATE())
        )
    ORDER BY lr.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
?>
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

</head>
<div id="history-page" class="page-content p-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-4">Historique des demandes</h2>
    <!-- Filters -->
    <div class="flex flex-wrap items-center justify-center gap-4 mb-6 bg-gray-50 p-4 rounded-lg shadow-sm">
        <!-- Status Filter -->
        <div>
            <label for="statusFilter" class="block text-sm font-medium text-gray-700">Statut</label>
            <select id="statusFilter" class="mt-1 border rounded px-3 py-2">
                <option value="">Tous</option>
                <option value="approved">Approuvé</option>
                <option value="rejected">Rejeté</option>
                <option value="cancelled">Annulé</option>
            </select>
        </div>

        <!-- Leave Type Filter -->
        <div>
            <label for="typeFilter" class="block text-sm font-medium text-gray-700">Type de congé</label>
            <select id="typeFilter" class="mt-1 border rounded px-3 py-2">
                <option value="">Tous</option>
                <?php
                // Fetch leave types dynamically
                $typesResult = $conn->query("SELECT id, type_name FROM leave_types ORDER BY type_name ASC");
                while ($type = $typesResult->fetch_assoc()):
                ?>
                    <option value="<?= $type['id']; ?>"><?= htmlspecialchars($type['type_name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Period Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700">Période</label>
            <div class="flex gap-2 mt-1">
                <input type="date" id="startDateFilter" class="border rounded px-2 py-1">
                <span class="self-center">→</span>
                <input type="date" id="endDateFilter" class="border rounded px-2 py-1">
            </div>
        </div>

        <!-- Reset Filters -->
        <div class="mt-6">
            <button id="resetFilters" class="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300 transition">
                effacer les filtres
            </button>
        </div>
        <div class="mt-4">
            <button id="exportPDF" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                Exporter PDF
            </button>
        </div>
    </div>


    <div class="bg-white rounded-lg shadow-md p-6 overflow-x-auto">
        <table class="min-w-full border-collapse border border-gray-200">
            <thead>
                <tr class="bg-gray-100 text-left">
                    <th class="px-4 py-2 border-b">Type de congé</th>
                    <th class="px-4 py-2 border-b">Date de demande</th>
                    <th class="px-4 py-2 border-b">Date de début</th>
                    <th class="px-4 py-2 border-b">Date de fin</th>
                    <th class="px-4 py-2 border-b">Statut</th>
                    <th class="px-4 py-2 border-b">Jours</th>
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
                                    case 'rejected':
                                        echo '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-red-100 text-red-700 rounded-full">Rejeté</span>';
                                        break;
                                    case 'cancelled':
                                        echo '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">Annulé</span>';
                                        break;
                                }
                                ?>
                            </td>
                            <td class="px-4 py-2 border-b"><?= $row['days_requested']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="common.js?v=<?= time(); ?>"></script>
<script>

document.getElementById('exportPDF').addEventListener('click', async () => {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'pt', 'a4');

    // ====== Get user's first and last name from PHP session ======
    const userFirstName = "<?= htmlspecialchars($_SESSION['first_name']); ?>";
    const userLastName = "<?= htmlspecialchars($_SESSION['last_name']); ?>";

    const title = `Historique de ${userFirstName} ${userLastName}`;

    // ====== Add Title ======
    doc.setFont("helvetica", "bold");
    doc.setFontSize(18);
    doc.text(title, 40, 40); // x=40, y=40

    const table = document.querySelector('#history-page table');
    if (!table) return;

    // ====== Capture the table with html2canvas ======
    const canvas = await html2canvas(table, { 
        scale: 5 // higher scale = sharper and larger text
    });
    const imgData = canvas.toDataURL('image/png');

    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();

    // Make table take almost full width
    const margin = 30;
    const pdfWidth = pageWidth - margin * 2;
    const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

    // ====== Add table image BELOW the title ======
    const startY = 70; // table starts below the title

    // If table is taller than one page, add multiple pages
    if (pdfHeight + startY > pageHeight) {
        let y = startY;
        let remainingHeight = pdfHeight;
        let position = 0;

        while (remainingHeight > 0) {
            doc.addImage(imgData, 'PNG', margin, y, pdfWidth, pdfHeight, undefined, 'FAST');
            remainingHeight -= pageHeight - startY;
            position -= pageHeight - startY;

            if (remainingHeight > 0) {
                doc.addPage();
                y = 20;
            }
        }
    } else {
        doc.addImage(imgData, 'PNG', margin, startY, pdfWidth, pdfHeight, undefined, 'FAST');
    }

    // ====== Save PDF ======
    doc.save(`historique_${userFirstName}_${userLastName}.pdf`);
});



function fetchHistory() {
    const status = document.getElementById('statusFilter').value;
    const typeId = document.getElementById('typeFilter').value;
    const startDate = document.getElementById('startDateFilter').value;
    const endDate = document.getElementById('endDateFilter').value;

    const params = new URLSearchParams({
        status: status,
        type_id: typeId,
        start_date: startDate,
        end_date: endDate
    });

    fetch('manager_history_data.php?' + params.toString())
        .then(res => res.json())
        .then(data => {
            const tbody = document.querySelector('#history-page tbody');
            tbody.innerHTML = '';

            if (data.error) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-2 text-center text-red-500">${data.error}</td></tr>`;
                return;
            }

            if (data.history.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-2 text-center text-gray-500">Aucun historique trouvé</td></tr>';
                return;
            }

            data.history.forEach(row => {
                const statusLabel = {
                    approved: '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">Approuvé</span>',
                    rejected: '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-red-100 text-red-700 rounded-full">Rejeté</span>',
                    cancelled: '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">Annulé</span>'
                }[row.status] || row.status;

                tbody.innerHTML += `
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-2 border-b">${row.type_name}</td>
                        <td class="px-4 py-2 border-b">${new Date(row.created_at).toLocaleDateString('fr-FR')}</td>
                        <td class="px-4 py-2 border-b">${new Date(row.start_date).toLocaleDateString('fr-FR')}</td>
                        <td class="px-4 py-2 border-b">${new Date(row.end_date).toLocaleDateString('fr-FR')}</td>
                        <td class="px-4 py-2 border-b">${statusLabel}</td>
                        <td class="px-4 py-2 border-b">${row.days_requested}</td>
                    </tr>
                `;
            });
        });
}

// Attach listeners
document.getElementById('statusFilter').addEventListener('change', fetchHistory);
document.getElementById('typeFilter').addEventListener('change', fetchHistory);
document.getElementById('startDateFilter').addEventListener('change', fetchHistory);
document.getElementById('endDateFilter').addEventListener('change', fetchHistory);

document.getElementById('resetFilters').addEventListener('click', () => {
    document.getElementById('statusFilter').value = '';
    document.getElementById('typeFilter').value = '';
    document.getElementById('startDateFilter').value = '';
    document.getElementById('endDateFilter').value = '';
    fetchHistory();
});

// Initial load
fetchHistory();
setInterval(fetchHistory, 5000);
</script>

</body>
</html>
