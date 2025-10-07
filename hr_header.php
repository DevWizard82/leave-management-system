<?php
// ============================
// SESSION ET SÉCURITÉ
// ============================
ob_start();
session_name('LeaveTrackr_HR');
session_start();

// Vérifier que l'utilisateur est HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
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
$resUsers = $conn->query("SELECT username FROM users WHERE role<>'HR'");
while ($row = $resUsers->fetch_assoc()) {
    $usernames[] = $row['username'];
}
syncUsersFromAD($usernames, $conn, $ldapUser, $ldapPass);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>portail de congés - HR</title>
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

        .edit, .history, .delete-holiday, .delete {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            text-decoration: none;
        }
        .edit i, .history i, .delete-holiday i, .delete i {
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


        .delete-holiday, .delete {
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

        .delete-holiday:hover, .delete:hover {
            background-color: #991B1B;
        }

        .edit:hover, .history:hover, .delete-holiday:hover, .delete:hover {
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
            background: transparent;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.5); 
            backdrop-filter: blur(10px);
            z-index: 999;
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
        @media (max-width: 768px) {
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
        @media (min-width: 769px) {
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

        .navbar-avatar {
            width: 40px;         /* or whatever size you want */
            height: 40px;
            border-radius: 50%;  /* makes it perfectly circular */
            object-fit: cover;   /* ensures the image scales nicely */
        }

        .sidebar ul li.active-nav a {
        background-color: #e2e8f0; /* Example: light gray */
        font-weight: bold;
        color: #1f2937; /* Dark text */
        }
        body {
            background: linear-gradient(135deg, #fefefe, #f0f4f8);
        }

    </style>
</head>
<body class="font-sans">
    <?php if (isset($_SESSION['edit_success'])): ?>
        <p style="background-color: green; color: white;"><?= htmlspecialchars($_SESSION['edit_success']); ?></p>
        <?php unset($_SESSION['edit_success']); ?>
    <?php endif; ?>
    <!-- Fixed Top Navbar -->
    <nav class="sticky top-0 left-0 right-0 z-50 glass-navbar h-16">
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
            <?php if (basename($_SERVER['PHP_SELF']) === 'hr_users.php'):?>
            <div class="hidden md:flex flex-1 max-w-md mx-8">
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input id="search-users" onkeyup="filterUsers()" type="text" placeholder="" 
                        class="search-focus block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-full leading-5 bg-white/70 backdrop-blur-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>
            <?php endif; ?>
            <div class="flex items-center space-x-2 md:space-x-4">
                <div class="omni-logo hidden sm:flex">
                    <img src="./omnidata.png" alt="Logo OmniData">
                </div>
                <div class="relative user-avatar">
                    <button id="avatarButton" class="avatar-button w-10 h-10 rounded-full flex items-center justify-center cursor-pointer ring-2 ring-white shadow-lg focus:outline-none" onclick="toggleAvatarModal()">
                        <?php if ($avatarPhoto): ?>
                            <img src="<?= $avatarPhoto ?>" class="w-full h-full rounded-full object-cover">
                        <?php else: ?>
                            <span class="text-white font-semibold text-sm">
                                <?= strtoupper(substr($_SESSION['first_name'], 0, 1)) . strtoupper(substr($_SESSION['last_name'], 0, 1)); ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div id="avatarModal" class="avatar-modal absolute right-0 top-12 w-64 bg-white rounded-lg shadow-xl border border-gray-200 py-3 z-50">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                    <?php if ($avatarPhoto): ?>
                                        <img src="<?= $avatarPhoto ?>" class="w-full h-full rounded-full object-cover">
                                    <?php else: ?>
                                        <?= strtoupper(substr($_SESSION['first_name'], 0, 1)) . strtoupper(substr($_SESSION['last_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900 text-sm"><?= ucfirst($_SESSION['first_name']) . ' ' . ucfirst($_SESSION['last_name']); ?></div>
                                    <div class="text-xs text-gray-500">Ressources humaines</div>
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
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 sidebar-overlay md:hidden" onclick="closeSidebar()"></div>
    <!-- Sidebar -->
    <div id="sidebar" class="sidebar fixed top-16 bottom-0 left-0 z-40 w-64 bg-white shadow-lg sidebar-transition">
        <nav class="mt-8">
            <a href="hr_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'hr_dashboard.php' ? 'active-nav' : '' ?> nav-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100" id="nav-dashboard">
                <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                </svg>
                Tableau de bord
            </a>
            <a href="hr_users.php" class="<?= basename($_SERVER['PHP_SELF']) === 'hr_users.php' ? 'active-nav' : '' ?> nav-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100" id="nav-users">
                <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
                </svg>
                Gestion des utilisateurs
            </a>
            <a href="hr_leave_types.php" class="<?= basename($_SERVER['PHP_SELF']) === 'hr_leave_types.php' ? 'active-nav' : '' ?> nav-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100">
                <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M6 2a1 1 0 000 2h8a1 1 0 100-2H6zM4 6a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V8a2 2 0 00-2-2H4zm0 2h12v8H4V8z"></path>
                </svg>
                Gestion des types de congés
            </a>
            <a href="hr_holidays.php" class="<?= basename($_SERVER['PHP_SELF']) === 'hr_holidays.php' ? 'active-nav' : '' ?> nav-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100" id="nav-holidays">
                <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                </svg>
                Gestion des jours fériés
            </a>
            <a href="hr_leave_requests.php" class="<?= basename($_SERVER['PHP_SELF']) === 'hr_leave_requests.php' ? 'active-nav' : '' ?> nav-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-paper-plane" style="margin-right: 12px;"></i>
                Mes demandes
            </a>
            <a href="hr_history.php" class="<?= basename($_SERVER['PHP_SELF']) === 'hr_history.php' ? 'active-nav' : '' ?> nav-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100" id="nav-history">
                <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                </svg>
                Mon Historique
            </a>
            <a href="hr_reports.php" class="<?= basename($_SERVER['PHP_SELF']) === 'hr_reports.php' ? 'active-nav' : '' ?> nav-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100" id="nav-reports">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M9 17V9m4 8V5m4 12v-4"></path>
                </svg>
                Rapports
            </a>
        </nav>
    </div>
    <!-- Main Content -->
    <div class="main-content md:ml-64 mt-16 min-h-screen">