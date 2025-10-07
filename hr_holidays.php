<?php
require_once 'hr_header.php';



if (isset($_POST['add_holiday'])) {
    $holiday_name = mysqli_real_escape_string($conn, $_POST['holiday_name']);
    $holiday_start = $_POST['holiday_start'];
    $holiday_end = $_POST['holiday_end'];

    // Validate required fields
    if (empty($holiday_name) || empty($holiday_start) || empty($holiday_end)) {
        $_SESSION['add_holiday_error'] = "Veuillez remplir tous les champs requis";
    } else {
        $sql = "INSERT INTO holidays (holiday_name, holiday_start, holiday_end)
                VALUES ('$holiday_name', '$holiday_start', '$holiday_end')";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['add_holiday_success'] = "Le nouveau jour férié a été ajouté avec succès";
            header("Location: hr_holidays.php");
            exit;
        } else {
            $_SESSION['add_holiday_error'] = "Impossible d'ajouter le jour férié. Veuillez vérifier que tous les champs sont remplis correctement et que la date n'existe pas déjà.";
            header("Location: hr_holidays.php");
            exit;
        }
    }
}



// ---------------------- DELETE HOLIDAY ----------------------
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']); // sanitize input
    $sql = "DELETE FROM holidays WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['delete_holiday_success'] = 'Jour férié supprimé avec succès !';
        header("Location: hr_holidays.php"); // redirect to refresh page
        exit();
    } else {
        $_SESSION['delete_holiday_error'] = "Erreur: " . mysqli_error($conn);
        header("Location: hr_holidays.php"); // redirect even on error
        exit();
    }
}




$holidayNamesFR = [
    "Ras l' âm"                  => "Nouvel An",
    "Takdim watikat al-istiqlal" => "Fête du Travail",
    "Id Yennayer"                => "Fête du Trône",
    "Oued Ed-Dahab Day"          => "Zikra Oued Ed-Dahab",
    "Thawrat al malik wa shâab" => "Révolution du Roi et le Peuple",
    "Eid Al Chabab"              => "Journée de la Jeunesse",
    "Eid Al Massira Al Khadra"   => "Marche Verte",
    "Eid Al Istiqulal"           => "Indépendance",
    "Eid Ash-Shughl"             => "Aïd el-Fitr",
    "Eid Al-Ârch"                => "Aïd el-Adha"
];







if (isset($_POST['edit_holiday'])) {
    $id = intval($_POST['holiday_id']); 
    $holiday_name = mysqli_real_escape_string($conn, $_POST['holiday_name']);
    $holiday_start = $_POST['holiday_start'];
    $holiday_end = $_POST['holiday_end'];

    $sql = "UPDATE holidays 
            SET holiday_name='$holiday_name', holiday_start='$holiday_start', holiday_end='$holiday_end'
            WHERE id=$id";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['edit_holiday_success'] = 'Jour férié mis à jour !';
    } else {
        $_SESSION['edit_holiday_error'] = "Erreur: " . mysqli_error($conn);
    }

    header("Location: hr_holidays.php");
    exit();
}




$holidays = $conn->query("
    SELECT id, holiday_name, created_at, updated_at, holiday_start, holiday_end
    FROM holidays 
    ORDER BY updated_at;
");

?>

<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <!-- JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <div id="holidays-page" class="page-content p-8">
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">Gestion des jours fériés</h2>
                    <p class="text-gray-600">Gérez les jours fériés : ajoutez, modifiez ou supprimez les jours fériés et dates spéciales de l’entreprise.</p>
                </div>
                <button onclick="showAddHolidayModal()" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-lg hover:shadow-lg transition-all duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                    </svg>
                    Ajouter un jour férié
                </button>
            </div>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="table-container">
                    <table id="users-table" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Nom du jour férié</span>
                                        <span class="sort-arrows cursor-pointer" data-column="1">⇅</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Créé le</span>
                                        <span class="sort-arrows cursor-pointer" data-column="2">⇅</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Mis à jour le</span>
                                        <span class="sort-arrows cursor-pointer" data-column="3">⇅</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Début du jour férié</span>
                                        <span class="sort-arrows cursor-pointer" data-column="4">⇅</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Fin du jour férié</span>
                                        <span class="sort-arrows cursor-pointer" data-column="5">⇅</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="usersTableBody">
                            <?php while ($holiday = $holidays->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($holidayNamesFR[$holiday['holiday_name']] ?? $holiday['holiday_name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($holiday['created_at']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full bg-white-100 text-gray-800"><?= htmlspecialchars($holiday['updated_at']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full bg-white-100 text-gray-800"><?= htmlspecialchars($holiday['holiday_start']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($holiday['holiday_end']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="#" class="edit" 
                                            data-id="<?= $holiday['id']; ?>">
                                            <i class="fa-solid fa-pen"></i> Modifier
                                        </a>
                                        <a href="#" class="delete delete-holiday" onclick="showDeleteModal(<?= $holiday['id']; ?>)">
                                            <i class="fa-solid fa-trash"></i> Supprimer
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="addHolidayModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-opacity duration-300 opacity-0">
                <div class="relative top-24 mx-auto p-6 border w-96 shadow-2xl rounded-2xl bg-white transform transition-transform duration-300 scale-95">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Ajouter un nouveau jour férié</h3>
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom du jour férié</label>
                                <input type="text" name="holiday_name" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Début du jour férié</label>
                                <input type="date" name="holiday_start" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Fin du jour férié</label>
                                <input type="date" name="holiday_end" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="hideAddHolidayModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">Annuler</button>
                                <button type="submit" name="add_holiday" class="px-4 py-2 bg-blue-600 text-white rounded-md">Ajouter jour férié</button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>


            <!-- popup pour modifier un jour ferie -->
            <div id="editHolidayModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto hidden z-50 opacity-0 transition-opacity duration-300">
                <div class="relative top-24 mx-auto p-6 border w-96 shadow-2xl rounded-2xl bg-white transform scale-95 transition-transform duration-300">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Modifier le jour férié</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="holiday_id" id="edit_holiday_id">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom du jour férié</label>
                                <input type="text" name="holiday_name" id="edit_holiday_name" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Début du jour férié</label>
                                <input type="date" name="holiday_start" id="edit_holiday_start" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Fin du jour férié</label>
                                <input type="date" name="holiday_end" id="edit_holiday_end" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="hideEditHolidayModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">Annuler</button>
                                <button type="submit" name="edit_holiday" class="px-4 py-2 bg-yellow-600 text-white rounded-md">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>


            <!-- popup pour supprimer un jour férié -->
            <div id="deleteHolidayModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
                <div class="bg-white rounded-2xl shadow-2xl w-96 p-6 text-center transform scale-95 transition-transform duration-300">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Supprimer le jour férié</h3>
                    <input type="hidden" id="delete_holiday_id">
                    <p class="text-sm text-gray-700 mb-6">Êtes-vous sûr de vouloir supprimer ce jour férié ? Cette action est irréversible.</p>
                    <div class="flex justify-center space-x-4">
                        <button onclick="hideDeleteModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">Annuler</button>
                        <a href="#" id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Oui, supprimer</a>
                    </div>
                </div>
            </div>




        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                <?php if (isset($_SESSION['add_holiday_success'])): ?>
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: 'success',
                        title: "<?= addslashes($_SESSION['add_holiday_success']); ?>",
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        background: '#d4edda',
                        color: '#155724',
                        customClass: {
                            popup: 'center-top-toast'
                        }
                    });
                    <?php unset($_SESSION['add_holiday_success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['add_holiday_error'])): ?>
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: 'error',
                        title: "<?= addslashes($_SESSION['add_holiday_error']); ?>",
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        background: '#f8d7da',
                        color: '#721c24',
                        customClass: {
                            popup: 'center-top-toast'
                        }
                    });
                    <?php unset($_SESSION['add_holiday_error']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['delete_holiday_success'])): ?>
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: 'success',
                        title: "<?= addslashes($_SESSION['delete_holiday_success']); ?>",
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        background: '#d4edda',
                        color: '#155724',
                        customClass: {
                            popup: 'center-top-toast'
                        }
                    });
                    <?php unset($_SESSION['delete_holiday_success']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['delete_holiday_error'])): ?>
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: 'success',
                        title: "<?= addslashes($_SESSION['delete_holiday_error']); ?>",
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        background: '#d4edda',
                        color: '#155724',
                        customClass: {
                            popup: 'center-top-toast'
                        }
                    });
                    <?php unset($_SESSION['delete_holiday_error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['edit_holiday_success'])): ?>
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: 'success',
                        title: "<?= addslashes($_SESSION['edit_holiday_success']); ?>",
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        background: '#d4edda',
                        color: '#155724',
                        customClass: {
                            popup: 'center-top-toast'
                        }
                    });
                    <?php unset($_SESSION['edit_holiday_success']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['edit_holiday_error'])): ?>
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: 'success',
                        title: "<?= addslashes($_SESSION['edit_holiday_error']); ?>",
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        background: '#d4edda',
                        color: '#155724',
                        customClass: {
                            popup: 'center-top-toast'
                        }
                    });
                    <?php unset($_SESSION['edit_holiday_error']); ?>
                <?php endif; ?>

            });
        </script>
        <script src="common.js?v=<?= time(); ?>"></script>
        <script src="hr_holidays.js?v=<?= time(); ?>"></script>
        <script src="https://cdn.jsdelivr.net/npm/drkmd-js@1.0.0/dist/drkmd.min.js"></script>

    </body>
</html>