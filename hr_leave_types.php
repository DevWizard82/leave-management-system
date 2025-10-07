<?php
require_once 'hr_header.php';

// ---------------------- ADD LEAVE TYPE ----------------------
if (isset($_POST['add_leave_type'])) {
    $type_name = mysqli_real_escape_string($conn, $_POST['type_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $affecte_le_reliquat = isset($_POST['affecte_le_reliquat']) ? 1 : 0;

    if (empty($type_name)) {
        $_SESSION['add_type_error'] = "Veuillez entrer le nom du type de congé.";
    } else {
        $sql = "INSERT INTO leave_types (type_name, description, affecte_le_reliquat)
                VALUES ('$type_name', '$description', '$affecte_le_reliquat')";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['add_type_success'] = "Le nouveau type de congé a été ajouté avec succès.";
        } else {
            $_SESSION['add_type_error'] = "Erreur lors de l'ajout du type de congé : " . mysqli_error($conn);
        }
    }
    header("Location: hr_leave_types.php");
    exit();
}

// ---------------------- DELETE LEAVE TYPE ----------------------
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $sql = "DELETE FROM leave_types WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['delete_type_success'] = "Type de congé supprimé avec succès.";
    } else {
        $_SESSION['delete_type_error'] = "Erreur lors de la suppression : " . mysqli_error($conn);
    }
    header("Location: hr_leave_types.php");
    exit();
}

// ---------------------- EDIT LEAVE TYPE ----------------------
if (isset($_POST['edit_leave_type'])) {
    $id = intval($_POST['leave_type_id']);
    $type_name = mysqli_real_escape_string($conn, $_POST['type_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $affecte_le_reliquat = isset($_POST['affecte_le_reliquat']) ? 1 : 0;

    $sql = "UPDATE leave_types
            SET type_name='$type_name', description='$description', affecte_le_reliquat='$affecte_le_reliquat'
            WHERE id=$id";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['edit_type_success'] = "Type de congé mis à jour avec succès.";
    } else {
        $_SESSION['edit_type_error'] = "Erreur lors de la mise à jour : " . mysqli_error($conn);
    }

    header("Location: hr_leave_types.php");
    exit();
}

// ---------------------- FETCH LEAVE TYPES ----------------------
$leave_types = $conn->query("
    SELECT id, type_name, description, affecte_le_reliquat, created_at
    FROM leave_types 
    ORDER BY created_at DESC;
");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div id="leave-types-page" class="page-content p-8">
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Gestion des types de congés</h2>
            <p class="text-gray-600">Ajoutez, modifiez ou supprimez les différents types de congés disponibles dans l'entreprise.</p>
        </div>
        <button onclick="showAddTypeModal()" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-lg hover:shadow-lg transition-all duration-200 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
            </svg>
            Ajouter un type
        </button>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="table-container">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <div class="flex items-center space-x-1">
                                <span>Nom du type</span>
                                <span class="sort-arrows cursor-pointer" data-column="1">⇅</span>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <div class="flex items-center space-x-1">
                                <span>Description</span>
                                <span class="sort-arrows cursor-pointer" data-column="2">⇅</span>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <div class="flex items-center space-x-1">
                                <span>Affecte le reliquat</span>
                                <span class="sort-arrows cursor-pointer" data-column="3">⇅</span>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <div class="flex items-center space-x-1">
                                <span>Créé le</span>
                                <span class="sort-arrows cursor-pointer" data-column="4">⇅</span>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($type = $leave_types->fetch_assoc()): ?>
                    <tr>
                        <td class="px-6 py-4"><?= htmlspecialchars($type['type_name']); ?></td>
                        <td class="px-6 py-4"><?= htmlspecialchars($type['description']); ?></td>
                        <td class="px-6 py-4">
                            <?= $type['affecte_le_reliquat'] ? '<span class="text-green-600 font-bold">Oui</span>' : '<span class="text-red-600 font-bold">Non</span>'; ?>
                        </td>
                        <td class="px-6 py-4"><?= htmlspecialchars($type['created_at']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <!-- Edit button -->
                            <a href="#" class="edit" 
                            data-id="<?= $type['id']; ?>"
                            data-name="<?= htmlspecialchars($type['type_name']); ?>"
                            data-description="<?= htmlspecialchars($type['description']); ?>"
                            data-affecte="<?= $type['affecte_le_reliquat']; ?>">
                                <i class="fa-solid fa-pen"></i> Modifier
                            </a>

                            <!-- Delete button -->
                            <a href="#" class="delete delete-type" onclick="showDeleteModal(<?= $type['id']; ?>)">
                                <i class="fa-solid fa-trash"></i> Supprimer
                            </a>
                        </td>

                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ADD LEAVE TYPE MODAL -->
    <div id="addTypeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-opacity duration-300 opacity-0">
        <div class="relative top-24 mx-auto p-6 border w-96 shadow-2xl rounded-2xl bg-white transform transition-transform duration-300 scale-95">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Ajouter un nouveau type de congé</h3>
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nom du type</label>
                    <input type="text" name="type_name" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <input type="text" name="description" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div class="mb-4 flex items-center">
                    <input type="checkbox" name="affecte_le_reliquat" id="affecte_le_reliquat" class="mr-2">
                    <label for="affecte_le_reliquat" class="text-sm text-gray-700">Ce type affecte le reliquat</label>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideAddTypeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">Annuler</button>
                    <button type="submit" name="add_leave_type" class="px-4 py-2 bg-blue-600 text-white rounded-md">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT LEAVE TYPE MODAL -->
    <div id="editTypeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto hidden z-50 transition-opacity duration-300 opacity-0">
        <div class="relative top-24 mx-auto p-6 border w-96 shadow-2xl rounded-2xl bg-white transform transition-transform duration-300 scale-95">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Modifier le type de congé</h3>
            <form method="POST" action="">
                <input type="hidden" name="leave_type_id" id="edit_leave_type_id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nom du type</label>
                    <input type="text" name="type_name" id="edit_type_name" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <input type="text" name="description" id="edit_description" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div class="mb-4 flex items-center">
                    <input type="checkbox" name="affecte_le_reliquat" id="edit_affecte_le_reliquat" class="mr-2">
                    <label for="edit_affecte_le_reliquat" class="text-sm text-gray-700">Ce type affecte le reliquat</label>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideEditTypeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md">Annuler</button>
                    <button type="submit" name="edit_leave_type" class="px-4 py-2 bg-yellow-600 text-white rounded-md">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE MODAL -->
    <div id="deleteTypeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-2xl w-96 p-6 text-center transform scale-95 transition-transform duration-300">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Supprimer le type de congé</h3>
            <p class="text-sm text-gray-700 mb-6">Êtes-vous sûr de vouloir supprimer ce type de congé ? Cette action est irréversible.</p>
            <div class="flex justify-center space-x-4">
                <button onclick="hideDeleteModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">Annuler</button>
                <a href="#" id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Oui, supprimer</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const getCellValue = (row, index) => row.children[index].innerText.trim();

        const comparer = (index, asc) => (a, b) => {
            const v1 = getCellValue(a, index);
            const v2 = getCellValue(b, index);

            return v1 !== '' && v2 !== '' && !isNaN(v1) && !isNaN(v2)
                ? (v1 - v2) * (asc ? 1 : -1)
                : v1.toString().localeCompare(v2) * (asc ? 1 : -1);
        };

        document.querySelectorAll(".sort-arrows").forEach(header => {
            header.addEventListener("click", function () {
                const table = header.closest("table");
                const tbody = table.querySelector("tbody");
                const rows = Array.from(tbody.querySelectorAll("tr"));
                const index = parseInt(header.dataset.column) - 1;
                const asc = header.classList.toggle("asc");

                rows.sort(comparer(index, asc));
                rows.forEach(row => tbody.appendChild(row));
            });
        });
    });


    function showAddTypeModal() {
        document.getElementById('addTypeModal').classList.remove('hidden', 'opacity-0');
    }
    function hideAddTypeModal() {
        document.getElementById('addTypeModal').classList.add('hidden', 'opacity-0');
    }

    function showEditTypeModal(id, name, description, affecte) {
        document.getElementById('edit_leave_type_id').value = id;
        document.getElementById('edit_type_name').value = name;
        document.getElementById('edit_description').value = description;
        document.getElementById('edit_affecte_le_reliquat').checked = affecte == 1;
        document.getElementById('editTypeModal').classList.remove('hidden', 'opacity-0');
    }
    function hideEditTypeModal() {
        document.getElementById('editTypeModal').classList.add('hidden', 'opacity-0');
    }

    function showDeleteModal(id) {
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.href = "hr_leave_types.php?delete_id=" + id;
        document.getElementById('deleteTypeModal').classList.remove('hidden', 'opacity-0');
    }
    function hideDeleteModal() {
        document.getElementById('deleteTypeModal').classList.add('hidden', 'opacity-0');
    }

    // Attach edit buttons
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.edit').forEach(btn => {
            btn.addEventListener('click', function () {
                showEditTypeModal(
                    this.dataset.id,
                    this.dataset.name,
                    this.dataset.description,
                    this.dataset.affecte
                );
            });
        });
    });
</script>

<?php if (isset($_SESSION['add_type_success'])): ?>
<script>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: '<?= $_SESSION['add_type_success']; ?>',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});
</script>
<?php unset($_SESSION['add_type_success']); endif; ?>

<?php if (isset($_SESSION['add_type_error'])): ?>
<script>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'error',
    title: '<?= $_SESSION['add_type_error']; ?>',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});
</script>
<?php unset($_SESSION['add_type_error']); endif; ?>


<?php if (isset($_SESSION['delete_type_success'])): ?>
<script>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: '<?= $_SESSION['delete_type_success']; ?>',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});
</script>
<?php unset($_SESSION['delete_type_success']); endif; ?>

<?php if (isset($_SESSION['delete_type_error'])): ?>
<script>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'error',
    title: '<?= $_SESSION['delete_type_error']; ?>',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});
</script>
<?php unset($_SESSION['delete_type_error']); endif; ?>


<?php if (isset($_SESSION['edit_type_success'])): ?>
<script>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: '<?= $_SESSION['edit_type_success']; ?>',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});
</script>
<?php unset($_SESSION['edit_type_success']); endif; ?>

<?php if (isset($_SESSION['edit_type_error'])): ?>
<script>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'error',
    title: '<?= $_SESSION['edit_type_error']; ?>',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});
</script>
<?php unset($_SESSION['edit_type_error']); endif; ?>
<script src="./common.js?v=<?= time(); ?>"></script>

</body>
</html>
