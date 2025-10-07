// ------------------ Add Holiday Modal ------------------
function showAddHolidayModal() {
  const modal = document.getElementById("addHolidayModal");
  const modalContent = modal.querySelector(":scope > div"); // direct inner div

  modal.classList.remove("hidden");

  setTimeout(() => {
    modal.classList.add("opacity-100");
    modalContent.classList.remove("scale-95");
    modalContent.classList.add("scale-100");
  }, 10);
}

function hideAddHolidayModal() {
  const modal = document.getElementById("addHolidayModal");
  const modalContent = modal.querySelector(":scope > div");

  modal.classList.remove("opacity-100");
  modalContent.classList.remove("scale-100");
  modalContent.classList.add("scale-95");

  setTimeout(() => {
    modal.classList.add("hidden");
  }, 300);
}

// ------------------ Edit Holiday Modal ------------------
document.querySelectorAll(".edit").forEach((btn) => {
  btn.addEventListener("click", (e) => {
    e.preventDefault();
    const id = btn.dataset.id;

    // Find the row
    const row = btn.closest("tr");

    // Populate fields
    document.getElementById("edit_holiday_id").value = id;
    document.getElementById("edit_holiday_name").value = row.querySelector(
      "td:nth-child(1) .text-sm"
    ).innerText;
    document.getElementById("edit_holiday_start").value = row.querySelector(
      "td:nth-child(4) span"
    ).innerText;
    document.getElementById("edit_holiday_end").value =
      row.querySelector("td:nth-child(5)").innerText;

    // Show modal with animation
    const modal = document.getElementById("editHolidayModal");
    const modalContent = modal.querySelector(":scope > div");

    modal.classList.remove("hidden");
    setTimeout(() => {
      modal.classList.remove("opacity-0");
      modal.classList.add("opacity-100");
      modalContent.classList.remove("scale-95");
      modalContent.classList.add("scale-100");
    }, 10);
  });
});

function hideEditHolidayModal() {
  const modal = document.getElementById("editHolidayModal");
  const modalContent = modal.querySelector(":scope > div");

  modal.classList.remove("opacity-100");
  modal.classList.add("opacity-0");
  modalContent.classList.remove("scale-100");
  modalContent.classList.add("scale-95");

  setTimeout(() => {
    modal.classList.add("hidden");
  }, 300);
}

document.addEventListener("DOMContentLoaded", () => {
  const table = document.getElementById("users-table"); // your holidays table
  const tbody = table.querySelector("tbody");
  const headers = table.querySelectorAll(".sort-arrows");

  headers.forEach((header) => {
    header.dataset.order = "asc"; // default ascending
    header.addEventListener("click", () => {
      const columnIndex = parseInt(header.dataset.column); // 1-based from your HTML
      const order = header.dataset.order === "desc" ? "asc" : "desc";
      header.dataset.order = order;

      const rowsArray = Array.from(tbody.querySelectorAll("tr"));
      rowsArray.sort((a, b) => {
        let aText = a
          .querySelector(`td:nth-child(${columnIndex})`)
          .textContent.trim();
        let bText = b
          .querySelector(`td:nth-child(${columnIndex})`)
          .textContent.trim();

        // Check for date format dd-mm-yyyy
        const datePattern = /^\d{4}-\d{2}-\d{2}$/; // for your holiday dates (yyyy-mm-dd)
        if (datePattern.test(aText) && datePattern.test(bText)) {
          const parseDate = (text) => {
            const [year, month, day] = text.split("-").map(Number);
            return new Date(year, month - 1, day);
          };
          return order === "asc"
            ? parseDate(aText) - parseDate(bText)
            : parseDate(bText) - parseDate(aText);
        }

        // Check if numeric
        if (!isNaN(aText) && !isNaN(bText))
          return order === "asc" ? aText - bText : bText - aText;

        // Default: string compare
        return order === "asc"
          ? aText.localeCompare(bText)
          : bText.localeCompare(aText);
      });

      // Append sorted rows
      rowsArray.forEach((row) => tbody.appendChild(row));
    });
  });
});

// ------------------ DELETE HOLIDAY MODAL ------------------
function showDeleteModal(id) {
  const modal = document.getElementById("deleteHolidayModal");
  modal.classList.remove("hidden");
  modal.classList.add("flex");

  // Set the confirm button href
  const confirmBtn = document.getElementById("confirmDeleteBtn");
  confirmBtn.href = "hr_holidays.php?delete_id=" + id;
}

function hideDeleteModal() {
  const modal = document.getElementById("deleteHolidayModal");
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

// Optional: Close modal if clicking outside
window.addEventListener("click", (e) => {
  const modal = document.getElementById("deleteHolidayModal");
  if (e.target === modal) hideDeleteModal();
});
