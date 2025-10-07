let isModalOpen = false;
let isAvatarModalOpen = false;

function toggleAvatarModal() {
  if (isAvatarModalOpen) {
    hideAvatarModal();
  } else {
    showAvatarModal();
  }
}

function showAvatarModal() {
  const modal = document.getElementById("avatarModal");
  const button = document.getElementById("avatarButton");
  modal.classList.add("show");
  button.classList.add("active");
  isAvatarModalOpen = true;
  setTimeout(() => {
    document.addEventListener("click", handleAvatarClickOutside);
  }, 100);
}

function hideAvatarModal() {
  const modal = document.getElementById("avatarModal");
  const button = document.getElementById("avatarButton");
  modal.classList.remove("show");
  button.classList.remove("active");
  isAvatarModalOpen = false;
  document.removeEventListener("click", handleAvatarClickOutside);
}

function handleAvatarClickOutside(event) {
  const modal = document.getElementById("avatarModal");
  const button = document.getElementById("avatarButton");
  if (!modal.contains(event.target) && !button.contains(event.target)) {
    hideAvatarModal();
  }
}

function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("sidebarOverlay");
  const hamburger = document.getElementById("hamburgerButton");
  sidebar.classList.toggle("open");
  overlay.classList.toggle("show");
  hamburger.classList.toggle("active");
}

function closeSidebar() {
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("sidebarOverlay");
  const hamburger = document.getElementById("hamburgerButton");
  sidebar.classList.remove("open");
  overlay.classList.remove("show");
  hamburger.classList.remove("active");
}

document.querySelectorAll(".nav-item").forEach((item) => {
  item.addEventListener("click", function () {
    if (window.innerWidth >= 1125) {
      closeSidebar();
    }
  });
});

window.addEventListener("resize", function () {
  if (window.innerWidth >= 1125) {
    closeSidebar();
  }
});

document.addEventListener("keydown", function (event) {
  if (event.key === "Escape" && isAvatarModalOpen) {
    hideAvatarModal();
  }
});

document.addEventListener("DOMContentLoaded", function () {
  const logoutButton = document.querySelector(".logout-option");
  if (logoutButton) {
    logoutButton.addEventListener("click", function () {
      window.location.href = "logout.php";
    });
  }
});
