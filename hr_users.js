document.addEventListener("DOMContentLoaded", function () {
  // ------------------ MODAL EDIT ------------------
  const modal = document.getElementById("editModal");
  const closeBtn = document.querySelector(".close");
  const editLinks = document.querySelectorAll(".edit");
  const editUserIdInput = document.getElementById("edit-user-id");

  editLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      e.preventDefault();
      const userId = this.getAttribute("data-id");
      const row = this.closest("tr");
      const leaveBalance = row.cells[2].innerText;
      const dateEmb = row.cells[5].innerText;

      editUserIdInput.value = userId;
      document.getElementById("edit-date_emb").value = dateEmb;
      document.getElementById("edit-leave-balance").value = leaveBalance;

      modal.style.display = "block";
    });
  });

  closeBtn.addEventListener("click", () => (modal.style.display = "none"));
  window.addEventListener("click", (e) => {
    if (e.target === modal) modal.style.display = "none";
  });

  // ------------------ TABLE FILTERS ------------------
  let roleColumnIndex = 3;
  let dateColumnIndex = 5;

  function detectColumnIndexes() {
    const headers = document.getElementById("users-table").tHead.rows[0].cells;
    for (let i = 0; i < headers.length; i++) {
      const text = headers[i].textContent.trim().toLowerCase();
      if (text.includes("rôle")) roleColumnIndex = i;
      if (text.includes("date d'embauche")) dateColumnIndex = i;
    }
  }

  function applyFilters(selectedDates = null) {
    detectColumnIndexes();
    const roleFilter = document
      .getElementById("filter-role")
      .value.toLowerCase();
    const buFilter = document.getElementById("filter-bu").value.toLowerCase();
    const dateInput = document.getElementById("filter-date-range").value;

    let startDate = null,
      endDate = null;

    if (selectedDates && selectedDates.length === 2) {
      startDate = selectedDates[0];
      endDate = selectedDates[1];
    } else if (dateInput.includes(" to ")) {
      const [start, end] = dateInput.split(" to ");
      const [sDay, sMonth, sYear] = start.split("-").map(Number);
      const [eDay, eMonth, eYear] = end.split("-").map(Number);
      startDate = new Date(sYear, sMonth - 1, sDay);
      endDate = new Date(eYear, eMonth - 1, eDay);
    }

    const rows = document.querySelectorAll("#users-table tbody tr");
    rows.forEach((row) => {
      const roleCell = row.cells[roleColumnIndex].textContent.toLowerCase();
      const buCell = row.cells[4].textContent.toLowerCase();
      const dateCell = row.cells[dateColumnIndex].textContent;
      const [dDay, dMonth, dYear] = dateCell.split("-").map(Number);
      const rowDate = new Date(dYear, dMonth - 1, dDay);

      let showRow = true;
      if (roleFilter && !roleCell.includes(roleFilter)) showRow = false;
      if (buFilter && !buCell.includes(buFilter.toLowerCase())) showRow = false;
      if (startDate && endDate && (rowDate < startDate || rowDate > endDate))
        showRow = false;

      row.style.display = showRow ? "" : "none";
    });
  }

  function resetFilters() {
    document.getElementById("filter-role").value = "";
    document.getElementById("filter-bu").value = "";
    document.getElementById("filter-date-range")._flatpickr.clear();
    applyFilters();
  }

  // ------------------ TABLE SORTING ------------------
  function initTableSorting() {
    const table = document.getElementById("users-table");
    const tbody = table.querySelector("tbody");
    const headers = table.querySelectorAll(".sort-arrows");

    headers.forEach((header) => {
      header.dataset.order = "asc";
      header.addEventListener("click", () => {
        const columnIndex = parseInt(header.dataset.column);
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

          const datePattern = /^\d{2}-\d{2}-\d{4}$/;
          if (datePattern.test(aText) && datePattern.test(bText)) {
            const parseDate = (text) => {
              const [day, month, year] = text.split("-").map(Number);
              return new Date(year, month - 1, day);
            };
            return order === "asc"
              ? parseDate(aText) - parseDate(bText)
              : parseDate(bText) - parseDate(aText);
          }

          if (!isNaN(aText) && !isNaN(bText))
            return order === "asc" ? aText - bText : bText - aText;

          return order === "asc"
            ? aText.localeCompare(bText)
            : bText.localeCompare(aText);
        });

        rowsArray.forEach((row) => tbody.appendChild(row));
      });
    });
  }

  // ------------------ EXPORT ------------------
  function exportFilteredToExcel() {
    const rows = document.querySelectorAll("#users-table tbody tr");
    const exportData = [];

    rows.forEach((row) => {
      if (row.style.display !== "none") {
        const cells = row.querySelectorAll("td");
        exportData.push({
          first_name: cells[0].textContent.trim(),
          last_name: cells[1].textContent.trim(),
          leave_balance: cells[2].textContent.trim(),
          role: cells[3].textContent.trim(),
          bu_name: cells[4].textContent.trim(),
          created_at: cells[5].textContent.trim(),
        });
      }
    });

    if (!exportData.length) return alert("Aucune donnée à exporter !");

    const form = document.createElement("form");
    form.method = "POST";
    form.action = "export_excel.php";
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "filteredData";
    input.value = JSON.stringify(exportData);
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
  }

  // ------------------ FLATPICKR ------------------
  flatpickr("#filter-date-range", {
    mode: "range",
    dateFormat: "d-m-Y",
    onChange: applyFilters,
  });

  // ------------------ EVENT LISTENERS ------------------
  document
    .getElementById("filter-role")
    .addEventListener("change", applyFilters);
  document.getElementById("filter-bu").addEventListener("change", applyFilters);

  document.getElementById("reset-btn")?.addEventListener("click", resetFilters);
  document
    .querySelector(".export-excel-btn")
    ?.addEventListener("click", exportFilteredToExcel);

  initTableSorting();
  applyFilters();
});

// ------------------ SEARCH BY NAME ------------------
function filterUsers() {
  const input = document.getElementById("search-users").value.toLowerCase();
  const rows = document
    .getElementById("users-table")
    .querySelectorAll("tbody tr");

  rows.forEach((row) => {
    const firstName = row.cells[0].textContent.toLowerCase();
    const lastName = row.cells[1].textContent.toLowerCase();
    const fullName = `${firstName} ${lastName}`;
    row.style.display = fullName.includes(input) ? "" : "none";
  });
}
