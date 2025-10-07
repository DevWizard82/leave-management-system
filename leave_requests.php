<?php
require_once 'employee_header.php';
require_once 'config.php';

$employeeId = $_SESSION['user_id'];

// Fetch only pending leave requests
$stmt = $conn->prepare("
    SELECT lr.id, lr.start_date, lr.end_date, lr.status, lr.created_at, lr.days_requested, lt.type_name
    FROM leave_requests lr
    JOIN leave_types lt ON lr.type_id = lt.id
    WHERE lr.user_id = ? 
      AND (lr.status = 'pending' OR (lr.status = 'approved' AND lr.end_date >= CURDATE()))
    ORDER BY lr.created_at DESC
");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$result = $stmt->get_result();

// Fetch leave types for edit modal
$resLeaveTypes = $conn->query("SELECT id, type_name FROM leave_types ORDER BY type_name ASC");
$leaveTypes = [];
while ($type = $resLeaveTypes->fetch_assoc()) {
    $leaveTypes[] = $type;
}
?>

<head>
    <!-- Litepicker CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/bundle.js"></script>
    
    <!-- SweetAlert -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        @keyframes spin { 0%{transform:rotate(0deg);}100%{transform:rotate(360deg);} }
        .spinner { animation: spin 1s linear infinite; transform-origin: 50% 50%; }
        .spinner-bg { opacity: 0.25; }
        .swal2-container {
            z-index: 9999 !important; /* or higher than navbar */
        }
    </style>
</head>

<div id="mes-demandes" class="page-content p-8">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-2">Mes Demandes</h2>
        <p class="text-gray-600">Consultez vos demandes de congé en attente</p>
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
                    <th class="px-4 py-2 border-b">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-2 text-center text-gray-500">Aucune demande en attente</td>
                    </tr>
                <?php else: ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($row['type_name']); ?></td>
                            <td class="px-4 py-2 border-b"><?= date("d/m/Y", strtotime($row['created_at'])); ?></td>
                            <td class="px-4 py-2 border-b"><?= date("d/m/Y", strtotime($row['start_date'])); ?></td>
                            <td class="px-4 py-2 border-b"><?= date("d/m/Y", strtotime($row['end_date'])); ?></td>
                            <td class="px-4 py-2 border-b">
                                <?php if ($row['status'] === 'pending'): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-yellow-100 text-yellow-700 rounded-full">
                                        <svg class="h-4 w-4 spinner" viewBox="0 0 50 50">
                                            <circle class="spinner-bg" cx="25" cy="25" r="20" stroke-width="5" fill="none" stroke="#fcd34d"/>
                                            <path fill="none" stroke="#f59e0b" stroke-width="5" d="M25 5 a20 20 0 0 1 0 40"/>
                                        </svg>
                                        En attente
                                    </span>
                                <?php elseif ($row['status'] === 'approved'): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414 0L9 11.586 6.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l7-7a1 1 0 000-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                        Approuvé
                                    </span>
                                <?php else: ?>
                                    <?= htmlspecialchars($row['status']); ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 border-b"><?= $row['days_requested']; ?></td>
                            <td class="px-4 py-2 border-b text-center flex gap-2">
                                <form action="cancel_leave.php" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler cette demande ?');">
                                    <input type="hidden" name="leave_id" value="<?= $row['id']; ?>">
                                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-sm px-3 py-1 rounded">Annuler</button>
                                </form>
                                <button onclick="openEditModal(<?= $row['id']; ?>, '<?= addslashes($row['type_name']); ?>', '<?= $row['start_date']; ?>', '<?= $row['end_date']; ?>', <?= $row['days_requested']; ?>)" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white text-sm px-3 py-1 rounded">Modifier</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editLeaveModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 transition-opacity opacity-0 duration-300">
    <div class="bg-white rounded-lg shadow-lg w-96 p-6 relative">
        <button onclick="toggleEditModal()" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700">✖</button>
        <h2 class="text-xl font-bold mb-4 text-gray-800">Modifier la demande</h2>
        <form id="editLeaveForm" method="POST" action="edit_leave.php">
            <input type="hidden" name="leave_id" id="edit_leave_id">
            
            <div class="mb-4">
                <label for="edit_leave_type" class="block text-sm font-medium text-gray-700 mb-1">Type de congé</label>
                <select id="edit_leave_type" name="leave_type" class="block w-full rounded-lg border border-gray-300 py-2 px-3">
                    <?php foreach($leaveTypes as $type): ?>
                        <option value="<?= $type['id']; ?>"><?= htmlspecialchars($type['type_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label for="edit_leave_period" class="block text-sm font-medium text-gray-700">Période</label>
                <input type="text" id="edit_leave_period" name="leave_period" class="block w-full rounded-lg border border-gray-300 py-2 px-3">
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="toggleEditModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Annuler</button>
                <button type="submit" name="submit_edit_leave" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Modifier</button>
            </div>
        </form>
    </div>
</div>
<script src="common.js?v=<?= time(); ?>"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editLeaveModal');
    const editForm = document.getElementById('editLeaveForm');
    const editLeaveId = document.getElementById('edit_leave_id');
    const editLeaveType = document.getElementById('edit_leave_type');
    const editLeavePeriod = document.getElementById('edit_leave_period');
    const tbody = document.querySelector('#mes-demandes tbody');

    // Initialize Litepicker
    new Litepicker({
        element: editLeavePeriod,
        singleMode: false,
        autoApply: true,
        minDate: new Date(),
        lang: 'fr',
        format: 'YYYY-MM-DD',
        delimiter: ' to '
    });

    // Toggle modal
    function toggleEditModal() {
        editModal.classList.toggle('hidden');
        editModal.classList.toggle('flex');
        editModal.classList.toggle('opacity-0');
        editModal.classList.toggle('opacity-100');
    }

    // Open modal with data
    function openEditModal(id, typeName, startDate, endDate, days) {
        editLeaveId.value = id;
        Array.from(editLeaveType.options).forEach(opt => {
            opt.selected = (opt.text === typeName);
        });
        editLeavePeriod.value = `${startDate} to ${endDate}`;
        toggleEditModal();
    }

    // Unified function to show SweetAlert toast
    function showToast(success, message) {
        Swal.fire({
            toast: true,
            position: 'top',
            icon: success ? 'success' : 'error',
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: success ? '#d4edda' : '#f8d7da',
            color: success ? '#155724' : '#721c24',
            customClass: { popup: 'center-top-toast' }
        });
    }

    // Fetch requests and populate table
    async function fetchRequests() {
        try {
            const res = await fetch('employee_requests_data.php');
            const data = await res.json();
            if(data.error || !Array.isArray(data.requests)) return;

            if(data.requests.length === 0) {
                tbody.innerHTML = `<tr>
                    <td colspan="7" class="px-4 py-2 text-center text-gray-500">Aucune demande en attente</td>
                </tr>`;
                return;
            }

            tbody.innerHTML = data.requests.map(row => {
                const typeSafe = row.type_name.replace(/'/g, "\\'");
                let statusLabel = '';
                if(row.status === 'pending') {
                    statusLabel = `<span class="inline-flex items-center gap-2 px-2 py-1 text-xs font-semibold bg-yellow-100 text-yellow-700 rounded-full">
                        <svg class="h-4 w-4 spinner" viewBox="0 0 50 50">
                            <circle class="spinner-bg" cx="25" cy="25" r="20" stroke-width="5" fill="none" stroke="#fcd34d"/>
                            <path fill="none" stroke="#f59e0b" stroke-width="5" d="M25 5 a20 20 0 0 1 0 40"/>
                        </svg> En attente
                    </span>`;
                } else if(row.status === 'approved') {
                    statusLabel = `<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414 0L9 11.586 6.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l7-7a1 1 0 000-1.414z" clip-rule="evenodd"/>
                        </svg> Approuvé
                    </span>`;
                } else {
                    statusLabel = row.status;
                }

                return `<tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-2 border-b">${row.type_name}</td>
                    <td class="px-4 py-2 border-b">${new Date(row.created_at).toLocaleDateString('fr-FR')}</td>
                    <td class="px-4 py-2 border-b">${new Date(row.start_date).toLocaleDateString('fr-FR')}</td>
                    <td class="px-4 py-2 border-b">${new Date(row.end_date).toLocaleDateString('fr-FR')}</td>
                    <td class="px-4 py-2 border-b">${statusLabel}</td>
                    <td class="px-4 py-2 border-b">${row.days_requested}</td>
                    <td class="px-4 py-2 border-b text-center flex gap-2">
                        <button class="cancel-btn bg-red-500 hover:bg-red-600 text-white text-sm px-3 py-1 rounded" data-id="${row.id}">Annuler</button>
                        <button class="edit-btn bg-blue-500 hover:bg-blue-600 text-white text-sm px-3 py-1 rounded" 
                            data-id="${row.id}" data-type="${typeSafe}" data-start="${row.start_date}" 
                            data-end="${row.end_date}" data-days="${row.days_requested}">Modifier</button>
                    </td>
                </tr>`;
            }).join('');

            attachEventListeners();
        } catch(err) {
            console.error(err);
        }
    }

    // Attach cancel/edit listeners
    function attachEventListeners() {
        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.onclick = async () => {
                if(!confirm('Êtes-vous sûr de vouloir annuler cette demande ?')) return;
                try {
                    const res = await fetch('cancel_leave.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'leave_id=' + encodeURIComponent(btn.dataset.id)
                    });
                    const data = await res.json();
                    showToast(data.success, data.message);
                    if(data.success) fetchRequests();
                } catch(err) { console.error(err); }
            };
        });

        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.onclick = () => {
                openEditModal(btn.dataset.id, btn.dataset.type, btn.dataset.start, btn.dataset.end, btn.dataset.days);
            };
        });
    }

    // Edit form submission
    editForm.addEventListener('submit', async e => {
        e.preventDefault();
        try {
            const formData = new FormData(editForm);
            const res = await fetch('edit_leave.php', { method: 'POST', body: formData });
            const data = await res.json();
            showToast(data.success, data.message);
            if(data.success) {
                toggleEditModal();
                fetchRequests();
            }
        } catch(err) { console.error(err); }
    });

    // Initial fetch
    fetchRequests();
    // Optional: refresh every 5s
    setInterval(fetchRequests, 5000);
});
</script>
