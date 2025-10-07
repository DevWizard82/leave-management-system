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

// ------------------ SIDEBAR ACTIVE ITEM ------------------
function scrollToSection(element) {
  document
    .querySelectorAll(".sidebar li")
    .forEach((li) => li.classList.remove("active"));
  element.classList.add("active");

  const targetId = element.getAttribute("data-target");
  const targetSection = document.getElementById(targetId);
  if (!targetSection) return;

  // Scroll 80px above the section
  const topPos =
    targetSection.getBoundingClientRect().top + window.scrollY - 80;

  window.scrollTo({
    top: topPos,
    behavior: "smooth",
  });
}

// ------------------ SCROLL SPY ------------------
document.addEventListener("DOMContentLoaded", () => {
  const sections = document.querySelectorAll("main > div[id]");
  const sidebarItems = document.querySelectorAll(".sidebar li");

  function updateActiveSection() {
    let closestsection = null;
    let minDistance = Infinity;

    sections.forEach((section) => {
      //calculate the distance between the section's top and the vw

      const distance = Math.abs(section.getBoundingClientRect().top);

      if (distance < minDistance) {
        minDistance = distance;
        closestsection = section;
      }
    });

    if (closestsection) {
      sidebarItems.forEach((item) => {
        item.classList.remove("active");
      });
      const activeItem = document.querySelector(
        `.sidebar li[data-target="${closestsection.id}"]`
      );
      if (activeItem) {
        activeItem.classList.add("active");
      }
    }
  }

  window.addEventListener("scroll", updateActiveSection);
  window.addEventListener("resize", updateActiveSection);
  updateActiveSection(); // initial highlight
});

// ------------------ BUTTON RIPPLE EFFECT ------------------
function setupButtonRipple(button) {
  button.addEventListener("mouseover", (event) => {
    const rect = button.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;
    const size = Math.sqrt(rect.width ** 2 + rect.height ** 2) * 2;

    button.style.setProperty("--xPos", x + "px");
    button.style.setProperty("--yPos", y + "px");
    button.style.setProperty("--ripple-size", size + "px");
  });
}

// ------------------ COLUMN INDEX DETECTION ------------------
let roleColumnIndex = 3;
let dateColumnIndex = 4;

function detectColumnIndexes() {
  const headers = document.getElementById("users-table").tHead.rows[0].cells;
  for (let i = 0; i < headers.length; i++) {
    const text = headers[i].textContent.trim().toLowerCase();
    if (text.includes("rôle")) roleColumnIndex = i;
    if (text.includes("date d'embauche")) dateColumnIndex = i;
  }
}

// ------------------ RESET FILTERS ------------------
function resetFilters() {
  document.getElementById("filter-role").value = "";
  document.getElementById("filter-bu").value = "";
  document.getElementById("filter-date-range")._flatpickr.clear();
  applyFilters();
}

// ------------------ SORTING ------------------
let sortAscending = false;
function getColumnIndex(column) {
  switch (column) {
    case "name":
      return 1;
    case "last_name":
      return 2;
    case "leave_balance":
      return 3;
    case "role":
      return 4;
    case "bu_name":
      return 5;
    case "created_at":
      return 6;
    default:
      return 1;
  }
}
function initTableSorting() {
  const table = document.getElementById("users-table");
  const tbody = table.querySelector("tbody");
  const headers = table.querySelectorAll(".sort-arrows");

  headers.forEach((header) => {
    header.dataset.order = "asc"; // default sort order

    header.addEventListener("click", () => {
      const columnIndex = parseInt(header.dataset.column); // get column index
      const order = header.dataset.order === "desc" ? "asc" : "desc";
      header.dataset.order = order;

      const rowsArray = Array.from(tbody.querySelectorAll("tr"));

      rowsArray.sort((a, b) => {
        const aText = a
          .querySelector(`td:nth-child(${columnIndex})`)
          .textContent.trim();
        const bText = b
          .querySelector(`td:nth-child(${columnIndex})`)
          .textContent.trim();

        // Detect if value is a date (dd-mm-yyyy)
        const datePattern = /^\d{2}-\d{2}-\d{4}$/;
        if (datePattern.test(aText) && datePattern.test(bText)) {
          const parseDate = (text) => {
            const [day, month, year] = text.split("-");
            return new Date(year, month - 1, day);
          };
          return order === "asc"
            ? parseDate(aText) - parseDate(bText)
            : parseDate(bText) - parseDate(aText);
        }

        // Detect if value is numeric
        if (!isNaN(aText) && !isNaN(bText)) {
          return order === "asc" ? aText - bText : bText - aText;
        }

        // Default: string comparison
        return order === "asc"
          ? aText.localeCompare(bText)
          : bText.localeCompare(aText);
      });

      // Append sorted rows back to tbody
      rowsArray.forEach((row) => tbody.appendChild(row));
    });
  });
}

// ------------------ EXPORT FILTERED TABLE ------------------
function exportFilteredToExcel() {
  const rows = document.querySelectorAll("#users-table tbody tr");
  const exportData = [];

  rows.forEach((row) => {
    if (row.style.display !== "none") {
      const cells = row.querySelectorAll("td");
      exportData.push({
        first_name: cells[0].innerText,
        last_name: cells[1].innerText,
        leave_balance: cells[2].innerText,
        role: cells[3].innerText,
        bu_name: cells[4].innerText,
        created_at: cells[5].innerText,
      });
    }
  });

  if (!exportData.length) return alert("No data to export!");

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

// ------------------ FILTERING ------------------
function applyFilters(selectedDates = null) {
  detectColumnIndexes();
  const roleFilter = document.getElementById("filter-role").value.toLowerCase();
  const buFilter = document.getElementById("filter-bu").value.toLowerCase();
  const dateInput = document.getElementById("filter-date-range").value;

  let startDate = null,
    endDate = null;

  if (selectedDates && selectedDates.length === 2) {
    startDate = selectedDates[0];
    endDate = selectedDates[1];
  } else if (dateInput.includes(" to ")) {
    const [start, end] = dateInput.split(" to ");
    const [sDay, sMonth, sYear] = start.split("-");
    const [eDay, eMonth, eYear] = end.split("-");
    startDate = new Date(sYear, sMonth - 1, sDay);
    endDate = new Date(eYear, eMonth - 1, eDay);
  }

  const rows = document.querySelectorAll("#users-table tbody tr");

  rows.forEach((row) => {
    const roleCell = row.cells[roleColumnIndex].textContent.toLowerCase();
    const buCell = row.cells[4].textContent.toLowerCase();
    const [day, month, year] =
      row.cells[dateColumnIndex].textContent.split("-");
    const createdDate = new Date(year, month - 1, day);

    const matchesRole = !roleFilter || roleCell.includes(roleFilter);
    const matchesBU = !buFilter || buCell.includes(buFilter);
    let matchesDate = true;
    if (startDate) matchesDate = createdDate >= startDate;
    if (endDate) matchesDate = matchesDate && createdDate <= endDate;

    row.style.display = matchesRole && matchesBU && matchesDate ? "" : "none";
  });
}

document
  .getElementById("filter-role")
  .addEventListener("change", () => applyFilters());

// ------------------ INITIALIZATION ------------------
document.addEventListener("DOMContentLoaded", () => {
  detectColumnIndexes();
  initTableSorting();

  const buttons = document.querySelectorAll("#reset-btn, .export-excel-btn");
  buttons.forEach((btn) => setupButtonRipple(btn));

  // ------------------ MODERN FLATPICKR DATE RANGE ------------------
  flatpickr("#filter-date-range", {
    mode: "range",
    dateFormat: "d-m-Y",
    altInput: true,
    altFormat: "F j, Y",
    allowInput: true,
    onChange: function (selectedDates) {
      applyFilters(selectedDates);
    },
  });
});
