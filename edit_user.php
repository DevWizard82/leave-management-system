<?php
// ============================
// edit.php - Modifier un utilisateur
// ============================

// --- Connexion à la base de données ---
require_once 'config.php';

session_start();

// ============================
// 1. Vérification et récupération de l'ID depuis l'URL
// ============================
// Vérifie si l'ID est passé dans l'URL, sinon erreur


$id = intval($_POST['id']); // Conversion en entier pour plus de sécurité

// ============================
// 2. Récupération des données de l'utilisateur
// ============================
$stmt = $conn->prepare("
    SELECT id, username, role, leave_balance, created_at 
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Si aucun utilisateur trouvé, afficher un message et arrêter
if (!$user) {
    die("Utilisateur introuvable.");
}

// ============================
// 3. Traitement du formulaire lorsqu'il est soumis
// ============================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Récupération des valeurs envoyées par le formulaire
    $date_emb = trim($_POST['date_emb']);
    $leave_balance = intval($_POST['leave_balance']);

    // Préparer la requête SQL pour mettre à jour les données
    $stmt = $conn->prepare("
        UPDATE users 
        SET created_at = ?, leave_balance = ? 
        WHERE id = ?
    ");

    // Liaison des paramètres (s = string, i = integer)
    $stmt->bind_param("sii", $date_emb, $leave_balance, $id);

    // Messages de succès ou d'erreur
    $successMessage = "";
    $errorMessage = "";

    if ($stmt->execute()) {
        // Si la mise à jour réussit
        $_SESSION['success_message'] = "Utilisateur mis à jour avec succès !";
        header("Location: hr_users.php");

        // Redirection vers la page principale avec un message
        exit();
    } else {
        // Si une erreur survient
        $errorMessage = "Erreur lors de la mise à jour : " . $conn->error;
    }

    $stmt->close();
}
?>
