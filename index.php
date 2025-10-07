<?php
session_start();

$usernameValue = $_SESSION['pending_user_username'] ?? '';
$passwordValue = $_SESSION['pending_user_password'] ?? '';

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeaveTrackr - Login</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body>
    <div class="big-container">
        <h1 class="titles">Portail des congés</h1>
        <h3 class="titles desc-big">Des congés simplifiés, des équipes renforcées</h3>
        <div class="container">
            <img src="./omnidata.png" alt="omnidata logo">
            <form action="login.php" method="POST">
                <h3 class="hello">Bienvenue !</h3>
                <p class="access">Accédez à votre compte de gestion des congés</p>
                <p>
                    <input type="text" name="username" id="username" placeholder="nom d'utisateur" required value="<?= htmlspecialchars($usernameValue) ?>">
                </p>
                <p class="password-container">
                    <input type="password" name="password" id="password" placeholder="mot de passe" required value="<?= htmlspecialchars($passwordValue) ?>">
                    <span class="toggle-password" onclick="togglePassword()"><i class="fa-solid fa-eye"></i></span>
                </p>
                <?php if (isset($_SESSION['pending_user'])):?>
                <p>
                    <input type="date" name="date_embauche">
                </p>
                <?php endif; ?>
                <p>
                    <button type="submit" name="login">Se connecter</button>
                </p>
            </form>
            <p class="small-text">Besoin d'aide ? Contactez votre administrateur RH</p>
        </div>
    </div>
    
    <script src="index.js?v=<?php echo time(); ?>"></script>
    <script>
        <?php if (isset($_SESSION['login_error'])): ?>
            Swal.fire({
                toast: true,
                position: 'top',
                icon: 'error',
                title: "<?= addslashes($_SESSION['login_error']); ?>",
                showConfirmButton: true,
                timer: 3000,
                timerProgressBar: true,
                background: '#f8d7da',
                color: 'white',
                customClass: {
                    popup: 'center-top-toast'
                }
            });
            <?php unset($_SESSION['login_error']); ?>
        <?php endif; ?>
    </script>
</body>

</html>