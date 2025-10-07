<?php
// ============================
// SESSION ET SÉCURITÉ
// ============================
session_name('LeaveTrackr_Employee');
session_start();

// Vérifier que l'utilisateur est employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employee') {
    header("Location: index.php");
    exit();
}

// ============================
// INCLUSIONS ET CONFIG
// ============================
require_once 'config.php';        // Connexion à la DB
require_once 'ldap_helper.php';   // Fonctions LDAP
require_once __DIR__ . '/vendor/autoload.php'; // Composer Autoload

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$ldapUser = $_ENV['LDAP_USER'];
$ldapPass = $_ENV['LDAP_PASS'];
$ldapServer = $_ENV['LDAP_SERVER'];

$avatarPhoto = getUserPhoto($_SESSION['username'], $ldapServer, $ldapUser, $ldapPass);

// ============================
// SYNCHRONISATION DES UTILISATEURS
// ============================
$usernames = [];
$resUsers = $conn->query("SELECT username FROM users");
while ($row = $resUsers->fetch_assoc()) {
    $usernames[] = $row['username'];
}
syncUsersFromAD($usernames, $conn, $ldapUser, $ldapPass);

$stmt = $conn->prepare("SELECT first_name, last_name from users where username=?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$utilisateur = $result->fetch_assoc();


$_SESSION['first_name'] = $utilisateur['first_name'];
$_SESSION['last_name'] = $utilisateur['last_name'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        // Determine page title dynamically
        $page = basename($_SERVER['PHP_SELF']);

        switch($page) {
            case 'employee_dashboard.php':
                $pageTitle = "Accueil";
                break;
            case 'employee_history.php':
                $pageTitle = "Historique";
                break;
            case 'leave_requests.php':
                $pageTitle = "Demandes";
                break;
            default:
                $pageTitle = "LeaveTrackr";
        }
    ?>
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" 
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" 
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .sort-arrows {
            cursor: pointer;
            font-size: 0.9em;
            color: #666;
            margin-left: 5px;
            user-select: none;
            transition: color 0.2s;
        }
        .sort-arrows:hover {
            color: #007bff;
        }
        .edit {
            background-color: #4caf50;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .edit, .history, .delete-holiday {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            text-decoration: none;
        }
        .edit i, .history i, .delete-holiday i {
            flex-shrink: 0;
        }
        .edit:hover {
            background-color: #45a049;
        }
        #editFormMessage {
            margin-top: 10px;
        }
        .modal-content h2 {
            text-align: center;
            margin-bottom: 25px;
            font-size: 24px;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
        }
        .form-buttons {
            text-align: right;
        }
        #editUserForm .form-group {
            margin-bottom: 15px;
        }
        #editUserForm label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        #editUserForm input, #editUserForm select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        #editUserForm input:focus, #editUserForm select:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
            outline: none;
        }
        #editUserForm .form-buttons {
            text-align: center;
            margin-top: 20px;
        }
        #editUserForm button {
            padding: 10px 25px;
            background-color: #007bff;
            color: #fff;
            font-size: 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        #editUserForm button:hover {
            background-color: #0056b3;
        }
        #editFormMessage .success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px 15px;
            margin-top: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .edit, .history {
            margin-right: 10px;
            font-size: 18px;
            text-decoration: none;
            transition: color 0.2s;
            padding: 5px 7px;
            border-radius: 12px;
        }
        .history {
            background-color: #f57c00;
            color: white;
        }


        .delete-holiday {
            margin-right: 10px;
            font-size: 18px;
            text-decoration: none;
            padding: 5px 7px;
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            background-color: #B91C1C;
            transition: color 0.3s ease-in;
        }

        .delete-holiday:hover {
            background-color: #991B1B;
        }

        .edit:hover, .history:hover, .delete-holiday:hover {
            filter: drop-shadow(2px 2px 2px rgba(0, 0, 0, 0.2));
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }
        .modal .close {
            color: #aaa;
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .modal .close:hover {
            color: #ff0000;
        }
        .modal-content {
            background: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
            position: relative;
            animation: fadeIn 0.3s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
        }
        .sidebar-transition {
            transition: all 0.3s ease;
        }
        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .active-nav {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .glass-navbar {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.85);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .search-focus {
            transition: all 0.3s ease;
        }
        .search-focus:focus {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.15);
        }
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .sidebar-overlay {
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .hamburger {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .hamburger.active .line1 {
            transform: rotate(-45deg) translate(-5px, 6px);
        }
        .hamburger.active .line2 {
            opacity: 0;
        }
        .hamburger.active .line3 {
            transform: rotate(45deg) translate(-5px, -6px);
        }
        .hamburger .line {
            width: 25px;
            height: 3px;
            background-color: #374151;
            margin: 3px 0;
            transition: 0.3s;
        }
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        @media (max-width: 1125px) {
            .main-content {
                margin-left: 0 !important;
            }
            .sidebar {
                position: fixed;
                z-index: 60;
            }
            .mobile-table-card {
                display: block;
            }
            .desktop-table {
                display: none;
            }
        }
        @media (min-width: 1126px) {
            .sidebar {
                transform: translateX(0) !important;
            }
            .hamburger-menu {
                display: none;
            }
            .mobile-table-card {
                display: none;
            }
            .desktop-table {
                display: table;
            }
        }
        .avatar-modal {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .avatar-modal.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }
        .avatar-button {
            transition: all 0.2s ease;
        }
        .avatar-button:hover {
            transform: scale(1.05);
        }
        .logout-option {
            transition: all 0.15s ease;
        }
        .logout-option:hover {
            background-color: rgb(239 68 68);
            color: white;
        }
        .logout-option:hover svg {
            color: white;
        }
        .omni-logo img {
            width: 120px;
            height: auto;
            transition: width 0.3s;
        }

        .sidebar ul li.active-nav a {
        background-color: #e2e8f0; /* Example: light gray */
        font-weight: bold;
        color: #1f2937; /* Dark text */
        }
        


    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php if (isset($_SESSION['edit_success'])): ?>
        <p style="background-color: green; color: white;"><?= htmlspecialchars($_SESSION['edit_success']); ?></p>
        <?php unset($_SESSION['edit_success']); ?>
    <?php endif; ?>
    <!-- Fixed Top Navbar -->
    <nav class="fixed top-0 left-0 right-0 z-50 glass-navbar h-16">

        <div class="flex items-center justify-between h-full px-4 md:px-6">
            <div class="flex items-center space-x-4">
                <div class="hamburger-menu md:hidden">
                    <div class="hamburger" onclick="toggleSidebar()" id="hamburgerButton">
                        <div class="line line1"></div>
                        <div class="line line2"></div>
                        <div class="line line3"></div>
                    </div>
                </div>

            </div>

            <div class="hidden md:flex items-center space-x-6 mx-auto">
                <a href="employee_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'employee_dashboard.php' ? 'bg-blue-600 text-white' : 'text-gray-700 hover:text-blue-600' ?> flex items-center gap-1 font-medium px-3 py-2 rounded-md transition-colors">
                    <i class="fa-solid fa-home"></i>
                    Acceuil
                </a>
                <a href="employee_history.php" class="<?= basename($_SERVER['PHP_SELF']) === 'employee_history.php' ? 'bg-blue-600 text-white' : 'text-gray-700 hover:text-blue-600' ?> flex items-center gap-1 font-medium px-3 py-2 rounded-md transition-colors">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Mon Historique
                </a>
                <a href="leave_requests.php" class="<?= basename($_SERVER['PHP_SELF']) === 'leave_requests.php' ? 'bg-blue-600 text-white' : 'text-gray-700 hover:text-blue-600' ?> flex items-center gap-1 font-medium px-3 py-2 rounded-md transition-colors">
                    <i class="fa-solid fa-file-lines"></i>
                    Mes demandes
                </a>

            </div>

            <div class="flex items-center space-x-2 md:space-x-4">

                <div class="omni-logo hidden sm:flex">
                    <img src="./omnidata.png" alt="Logo OmniData">
                </div>
                <div class="relative user-avatar">
                    <button id="avatarButton" class="avatar-button w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center cursor-pointer ring-2 ring-white shadow-lg focus:outline-none" onclick="toggleAvatarModal()">
                        <?php if($avatarPhoto):?>
                            <img src="<?= $avatarPhoto; ?>" class="w-10 h-10 rounded-full object-cover">
                        <?php else:?>
                            <span class="text-white font-semibold text-sm">
                                <?= strtoupper(substr($_SESSION['first_name'], 0, 1)) . strtoupper(substr($_SESSION['last_name'], 0, 1)); ?>
                            </span>
                        <?php endif;?>
                    </button>
                    <div id="avatarModal" class="avatar-modal absolute right-0 top-12 w-64 bg-white rounded-lg shadow-xl border border-gray-200 py-3 z-50">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                    <?php if($avatarPhoto):?>
                                        <img src="<?= $avatarPhoto; ?>" class="w-10 h-10 rounded-full object-cover">
                                    <?php else:?>
                                        <?= strtoupper(substr($_SESSION['first_name'], 0, 1)) . strtoupper(substr($_SESSION['last_name'], 0, 1)); ?>
                                    <?php endif;?>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900 text-sm"><?= ucfirst($_SESSION['first_name']) . ' ' . ucfirst($_SESSION['last_name']); ?></div>
                                    <div class="text-xs text-gray-500">Employé</div>
                                </div>
                            </div>
                        </div>
                        <div class="px-2 py-2" onclick="window.location.href='logout.php'">
                            <button class="logout-option w-full flex items-center space-x-3 px-3 py-2 text-sm text-gray-700 rounded-md">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                <span>Se déconnecter</span>
                            </button>
                        </div>
                        <div class="absolute -top-2 right-4 w-4 h-4 bg-white border-l border-t border-gray-200 transform rotate-45"></div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 sidebar-overlay lg:hidden" onclick="closeSidebar()"></div>
    <!-- Sidebar -->
    <div id="sidebar" class="sidebar fixed top-16 bottom-0 left-0 z-40 w-64 bg-white shadow-lg sidebar-transition lg:hidden">
        <nav class="mt-8">
            <a href="employee_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'employee_dashboard.php' ? 'active-nav' : '' ?> nav-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100 gap-x-2" id="nav-dashboard">
                <i class="fa-solid fa-home"></i>
                Acceuil
            </a>
            <a href="employee_history.php" class="<?= basename($_SERVER['PHP_SELF']) === 'employee_history.php' ? 'active-nav' : '' ?> nav-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100 gap-x-2" id="nav-history">
                <i class="fa-solid fa-clock-rotate-left"></i>
                Mon Historique
            </a>
            <a href="leave_requests.php" class="<?= basename($_SERVER['PHP_SELF']) === 'leave_requests.php' ? 'active-nav' : '' ?> nav-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100 gap-x-2" id="nav-leave_requests">
                <i class="fa-solid fa-file-lines"></i>
                Mes demandes
            </a>
        </nav>
    </div>
    <!-- Main Content -->
    <div class="main-content mt-16 min-h-screen">