<?php


require_once 'hr_header.php';

$users = $conn->query("
    SELECT id, first_name, username, last_name, leave_balance, role, created_at, bu_name
    FROM users 
    WHERE role<>'HR'
    ORDER BY created_at DESC
");
$gradients = [
    'from-blue-400 to-purple-500',
    'from-green-400 to-blue-500',
    'from-pink-400 to-red-500',
    'from-yellow-400 to-orange-500',
    'from-indigo-400 to-purple-600',
    'from-teal-400 to-cyan-500'
];
?>

<!DOCTYPE html>
<html lang="fr">
    <head>
        <style>
            .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow:auto; background-color: rgba(0,0,0,0.5); }
            .modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border-radius: 0.5rem; width: 80%; max-width: 700px; }
            .close { float: right; font-size: 1.5rem; cursor: pointer; }
            table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
            th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd; }
            .status-approved { background-color: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px; }
            .status-rejected { background-color: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; }
            .status-cancelled { background-color: #fff3cd; color: #856404; padding: 2px 6px; border-radius: 4px; }



            .filter-container {
            display: flex;
            flex-wrap: nowrap;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #fff;
            border-radius: 0.6rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            }

            .filter-inputs {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.6rem;
            }

            .filter-container label {
            font-weight: 500;
            color: #333;
            }

            .filter-container select,
            .filter-container input[type="date"] {
            padding: 0.4rem 0.6rem;
            border-radius: 0.4rem;
            border: 1px solid #ccc;
            font-size: 0.95rem;
            }

            .filter-container select:focus,
            .filter-container input[type="date"]:focus {
            border-color: #307750;
            box-shadow: 0 0 0 2px rgba(48, 119, 80, 0.2);
            outline: none;
            }

            .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            align-items: flex-end;
            }

            .button-group button {
            height: 38px;
            padding: 0 1rem;
            border-radius: 0.5rem;
            font-size: 14px;
            font-weight: 500;
            background-color: #307750;
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            }

            .button-group button:hover {
            background-color: #245d48;
            transform: translateY(-1px);
            }

            .button-group .export-excel-btn svg {
            margin-right: 0.5rem;
            }
        </style>

        <!-- CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <!-- JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>



    </head>
    <body>
        <div id="users-page" class="page-content p-4 md:p-8">
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">Gestion des utilisateurs</h2>
                    <p class="text-gray-600">Gérez vos subordonnés : modifiez leurs infos, consultez leur solde et historique de congés</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <!-- Filtres -->
                        <div class="filter-container">
                            <div class="filter-inputs">
                                <label for="filter-role">Filtrer par rôle :</label>
                                <select id="filter-role" onchange="applyFilters()">
                                    <option value="">Tous</option>
                                    <option value="Manager">Manager</option>
                                    <option value="employé">Employé</option>
                                </select>

                                <label for="filter-bu">Filtrer par BU :</label>
                                <select id="filter-bu" onchange="applyFilters()">
                                    <option value="" selected>Tous</option>
                                    <?php
                                    $buResult = $conn->query("SELECT DISTINCT bu_name FROM users WHERE role<>'HR' ORDER BY bu_name ASC");
                                    while ($bu = $buResult->fetch_assoc()) {
                                        $buName = htmlspecialchars($bu['bu_name']);
                                        echo "<option value=\"$buName\">$buName</option>";
                                    }
                                    ?>
                                </select>

                                <label for="filter-date-range">Période :</label>
                                <input type="text" id="filter-date-range" placeholder="Sélectionnez la période">
                            </div>

                            <!-- Boutons -->
                            <div class="button-group">
                                <button onclick="resetFilters()" id="reset-btn">Effacer les filtres</button>
                                <button class="export-excel-btn">
                                    <svg enable-background="new 0 0 24 24" id="Layer_1" version="1.1" viewBox="0 0 24 24" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><g><rect fill="#FFFFFF" height="17" width="11.5" x="12" y="3.5"/><path d="M23.5,21h-10c-0.2763672,0-0.5-0.2236328-0.5-0.5s0.2236328-0.5,0.5-0.5H23V4h-9.5   C13.2236328,4,13,3.7763672,13,3.5S13.2236328,3,13.5,3h10C23.7763672,3,24,3.2236328,24,3.5v17   C24,20.7763672,23.7763672,21,23.5,21z" fill="#177848"/><polygon fill="#177848" points="14,0 0,2.6086957 0,21.391304 14,24  "/><polygon fill="#FFFFFF" opacity="0.2" points="0,2.6087036 0,2.8587036 14,0.25 14,0  "/><rect fill="#177848" height="2" width="4" x="13" y="5"/><rect fill="#177848" height="2" width="4" x="18" y="5"/><rect fill="#177848" height="2" width="4" x="13" y="8"/><rect fill="#177848" height="2" width="4" x="18" y="8"/><rect fill="#177848" height="2" width="4" x="13" y="11"/><rect fill="#177848" height="2" width="4" x="18" y="11"/><rect fill="#177848" height="2" width="4" x="13" y="14"/><rect fill="#177848" height="2" width="4" x="18" y="14"/><rect fill="#177848" height="2" width="4" x="13" y="17"/><rect fill="#177848" height="2" width="4" x="18" y="17"/><polygon opacity="0.1" points="0,21.3912964 14,24 14,23.75 0,21.1412964  "/><linearGradient gradientUnits="userSpaceOnUse" id="SVGID_1_" x1="9.5" x2="23.3536377" y1="7.5" y2="21.3536377"><stop offset="0" style="stop-color:#000000;stop-opacity:0.1"/><stop offset="1" style="stop-color:#000000;stop-opacity:0"/></linearGradient><path d="M23.5,21c0.2763672,0,0.5-0.2236328,0.5-0.5V13L14,3v18H23.5z" fill="url(#SVGID_1_)"/><polygon fill="#FFFFFF" points="7.357666,12.5 9.6552734,8.3642578 9.6262817,8.3481445 7.8796387,8.4729004 6.5,10.9562378    5.225647,8.6624756 3.5758667,8.7802734 5.642334,12.5 3.5758667,16.2197266 5.225647,16.3375244 6.5,14.0437622    7.8796387,16.5270996 9.6262817,16.6518555 9.6552734,16.6357422  "/><linearGradient gradientTransform="matrix(60.9756088 0 0 60.9756088 20560.1210938 -26748.4140625)" gradientUnits="userSpaceOnUse" id="SVGID_2_" x1="-337.1860046" x2="-336.9563904" y1="438.8707886" y2="438.8707886"><stop offset="0" style="stop-color:#FFFFFF"/><stop offset="1" style="stop-color:#000000"/></linearGradient><path d="M14,0L0,2.6086957v18.782608L14,24V0z" fill="url(#SVGID_2_)" opacity="0.05"/><linearGradient gradientUnits="userSpaceOnUse" id="SVGID_3_" x1="-1.5634501" x2="25.0453987" y1="5.9615331" y2="18.369442"><stop offset="0" style="stop-color:#FFFFFF;stop-opacity:0.2"/><stop offset="1" style="stop-color:#FFFFFF;stop-opacity:0"/></linearGradient><path d="M23.5,3H14V0L0,2.6087036v18.7825928L14,24v-3h9.5c0.2763672,0,0.5-0.2236328,0.5-0.5v-17   C24,3.2236328,23.7763672,3,23.5,3z" fill="url(#SVGID_3_)"/></g><g/><g/><g/><g/><g/><g/><g/><g/><g/><g/><g/><g/><g/><g/><g/></svg>
                                Exporter
                                </button>
                            </div>
                        </div>
                <div class="table-container overflow-x-auto">
                    <table id="users-table" class=" min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Prénom</span>
                                        <span class="sort-arrows cursor-pointer" data-column="1">⇅</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Nom</span>
                                        <span class="sort-arrows cursor-pointer" data-column="2">⇅</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Reliquat</span>
                                        <span class="sort-arrows cursor-pointer" data-column="3">⇅</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Rôle</span>
                                        <span class="sort-arrows cursor-pointer" data-column="4">⇅</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Nom de la BU</span>
                                        <span class="sort-arrows cursor-pointer" data-column="5">⇅</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Date d'embauche</span>
                                        <span class="sort-arrows cursor-pointer" data-column="6">⇅</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="usersTableBody">
                            <?php $i = 0; ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <?php $randGradient = $gradients[$i % count($gradients)]; $i++; ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <?php 
                                            $photo = getUserPhoto($user['username'], $ldapServer, $ldapUser, $ldapPass); 
                                            if ($photo): ?>
                                                <img src="<?= $photo; ?>" alt="Avatar" class="w-10 h-10 rounded-full object-cover">
                                            <?php else: 
                                                $initials = strtoupper(substr($user['first_name'], 0, 1)) . strtoupper(substr($user['last_name'], 0, 1));
                                            ?>
                                                <div class="w-10 h-10 bg-gradient-to-r <?= $randGradient; ?> rounded-full flex items-center justify-center text-white font-semibold">
                                                    <?= $initials; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['first_name']); ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($user['last_name']); ?></td>
                                    <?php $balanceColor = ($user['leave_balance'] >= 21 || $user['leave_balance'] < 0) ? 'red' : 'green'; ?>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?= $balanceColor;?>-100 text-<?= $balanceColor;?>-800"><?= htmlspecialchars($user['leave_balance']); ?></span>
                                    </td>
                                    <?php $roleColor = ($user['role'] === "Manager") ? 'orange' : 'blue'; ?>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?= $roleColor;?>-100 text-<?= $roleColor;?>-800">
                                            <?php if ($user['role'] === "Employee"): ?>
                                                Employé
                                            <?php else: ?>
                                                <?=htmlspecialchars($user['role']);?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($user['bu_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($user['created_at']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="#" class="edit" data-id="<?= $user['id']; ?>"><i class="fa-solid fa-pen"></i> Modifier</a>
                                        <a href="hr_users_history.php?id=<?= $user['id']; ?>" class="history" data-id="<?= $user['id']; ?>"><i class="fa-solid fa-clock-rotate-left"></i> Historique</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                </div>
            </div>
            <div id="editFormMessage"></div>
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Modifier l'utilisateur</h2>
                    <form id="editUserForm" action="edit_user.php" method="POST">
                        <input type="hidden" name="id" id="edit-user-id">
                        <div class="form-group">
                            <label for="edit-date_emb">Date d'embauche :</label>
                            <input type="date" id="edit-date_emb" name="date_emb" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-leave-balance">Reliquat de congés :</label>
                            <input type="number" id="edit-leave-balance" name="leave_balance">
                        </div>
                        <div class="form-buttons">
                            <button type="submit">Mettre à jour</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="historyModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Historique des congés</h2>
                    <div id="historyContent">
                        <!-- Leaves will be loaded here -->
                    </div>
                            <button id="exportHistoryPDF" style="margin-top:10px; background-color:#307750; color:white; padding:6px 12px; border:none; border-radius:5px; cursor:pointer;">
                                Exporter en PDF
                            </button>
                </div>
        </div>


        </div>
        <script src="common.js?v=<?= time(); ?>"></script>
        <script src="hr_users.js?v=<?= time(); ?>"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>


        <script>
            
            const modal = document.getElementById('historyModal');
            const closeBtn = modal.querySelector('.close');
            const contentDiv = document.getElementById('historyContent');
            const exportBtn = document.getElementById('exportHistoryPDF');

            let currentUserName = ''; // Will store the selected user's name

            // Close modal handlers
            closeBtn.addEventListener('click', () => modal.style.display = 'none');
            window.addEventListener('click', e => {
                if (e.target === modal) modal.style.display = 'none';
            });

            // Function to render history table
            function renderHistoryTable(leaves) {
                if (!leaves || leaves.length === 0) {
                    contentDiv.innerHTML = '<p>Aucun historique de congés terminé pour cet utilisateur.</p>';
                    return;
                }

                let html = `<table>
                                <thead>
                                    <tr>
                                        <th>Type de congé</th>
                                        <th>Date de début</th>
                                        <th>Date de fin</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>`;

                leaves.forEach(leave => {
                    let statusClass = '';
                    if (leave.status === 'approved') statusClass = 'status-approved';
                    else if (leave.status === 'rejected') statusClass = 'status-rejected';
                    else if (leave.status === 'cancelled') statusClass = 'status-cancelled';

                    html += `<tr>
                                <td>${leave.leave_type}</td>
                                <td>${leave.start_date}</td>
                                <td>${leave.end_date}</td>
                                <td>
                                    <span class="${statusClass}">
                                        ${leave.status === 'approved' ? 'Approuvé' : leave.status === 'rejected' ? 'Rejeté' : 'Annulé'}
                                    </span>
                                </td>

                            </tr>`;
                });

                html += '</tbody></table>';
                contentDiv.innerHTML = html;
            }

            // Attach click events to history links
            document.querySelectorAll('.history').forEach(link => {
                link.addEventListener('click', e => {
                    e.preventDefault();

                    const userId = link.dataset.id;
                    const firstName = link.closest('tr').querySelector('td:nth-child(1) .text-sm').innerText;
                    const lastName = link.closest('tr').querySelector('td:nth-child(2)').innerText;
                    currentUserName = `${firstName} ${lastName}`;

                    fetch(`hr_users_history.php?id=${userId}`)
                        .then(res => res.json())
                        .then(data => {
                            renderHistoryTable(data.leaves);
                            modal.style.display = 'block';
                        })
                        .catch(() => {
                            contentDiv.innerHTML = '<p>Erreur lors du chargement de l’historique.</p>';
                            modal.style.display = 'block';
                        });
                });
            });

            // Export history to PDF
            exportBtn.addEventListener('click', () => {
                if (!currentUserName) {
                    alert("Aucun utilisateur sélectionné !");
                    return;
                }

                const table = contentDiv.querySelector('table');
                if (!table) {
                    alert("Aucun historique à exporter !");
                    return;
                }

                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.innerText);
                const rows = Array.from(table.querySelectorAll('tbody tr')).map(tr =>
                    Array.from(tr.querySelectorAll('td')).map(td => td.innerText)
                );

                const rowStyles = Array.from(table.querySelectorAll('tbody tr')).map(tr => {
                    const statusText = tr.querySelector('td:nth-child(4)').innerText.toLowerCase();
                    if (statusText === 'approved') return { fillColor: [198, 239, 206] };
                    if (statusText === 'rejected') return { fillColor: [255, 199, 206] };
                    if (statusText === 'cancelled') return { fillColor: [255, 235, 156] };
                    return {};
                });

                doc.setFontSize(14);
                doc.text(`Historique des congés de ${currentUserName}`, 14, 15);

                doc.autoTable({
                    startY: 25,
                    head: [headers],
                    body: rows,
                    rowStyles,
                    styles: { fontSize: 12 },
                    headStyles: { fillColor: [48, 119, 80] },
                    alternateRowStyles: { fillColor: [240, 240, 240] }
                });

                // Sanitize filename
                const filename = `historique_conges_${currentUserName.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s+/g, "_")}.pdf`;
                doc.save(filename);
            });





            document.addEventListener("DOMContentLoaded", function() {
                <?php if (isset($_SESSION['success_message'])): ?>
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: 'success',
                        title: "<?= addslashes($_SESSION['success_message']); ?>",
                        showConfirmButton: true,
                        timer: 3000,
                        timerProgressBar: true,
                        background: '#d4edda',
                        color: '#155724',
                        customClass: {
                            popup: 'center-top-toast'
                        }
                    });
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: 'error',
                        title: "<?= addslashes($_SESSION['error_message']); ?>",
                        showConfirmButton: true,
                        timer: 3000,
                        timerProgressBar: true,
                        background: '#f8d7da',
                        color: '#721c24',
                        customClass: {
                            popup: 'center-top-toast'
                        }
                    });
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
            });
            
        </script>



        
    </body>
</html>