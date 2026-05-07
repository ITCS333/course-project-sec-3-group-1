let users = [];
const userTableBody = document.getElementById("user-table-body");
const addUserForm = document.getElementById("add-user-form");
const changePasswordForm = document.getElementById("password-form");
const searchInput = document.getElementById("search-input");
const tableHeaders = document.querySelectorAll("#user-table thead th");
function createUserRow(user) {
  const tr = document.createElement("tr");
  tr.innerHTML = `
    <td>${user.name}</td>
    <td>${user.email}</td>
    <td>${user.is_admin == 1 ? "Yes" : "No"}</td>
    <td>
      <button class="edit-btn btn btn-warning btn-sm" data-id="${user.id}">
        Edit
      </button>
      <button class="delete-btn btn btn-danger btn-sm" data-id="${user.id}">
        Delete
      </button>
    </td>
  `;
  return tr;
}
function renderTable(userArray) {
  userTableBody.innerHTML = "";
  userArray.forEach(user => {
    const row = createUserRow(user);
    userTableBody.appendChild(row);
  });
}
async function handleChangePassword(event) {
  event.preventDefault();
  const currentPassword =
    document.getElementById("current-password").value;
  const newPassword =
    document.getElementById("new-password").value;
  const confirmPassword =
    document.getElementById("confirm-password").value;
  if (newPassword !== confirmPassword) {
    alert("Passwords do not match.");
    return;
  }
  if (newPassword.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }
  try {
    const response = await fetch(
      "../api/index.php?action=change_password",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          id: 1,
          current_password: currentPassword,
          new_password: newPassword
        })
      }
    );
    const result = await response.json();
    if (result.success) {
      alert("Password updated successfully!");
      document.getElementById("current-password").value = "";
      document.getElementById("new-password").value = "";
      document.getElementById("confirm-password").value = "";
    } else {
      alert(result.message);
    }
  } catch (error) {
    console.error(error);
    alert("Something went wrong.");
  }
}
async function handleAddUser(event) {
  event.preventDefault();
  const name =
    document.getElementById("user-name").value.trim();
  const email =
    document.getElementById("user-email").value.trim();
  const password =
    document.getElementById("default-password").value;
  const isAdmin =
    document.getElementById("is-admin").value;
  if (!name || !email || !password) {
    alert("Please fill out all required fields.");
    return;
  }
  if (password.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }
  try {
    const response = await fetch("../api/index.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        name: name,
        email: email,
        password: password,
        is_admin: isAdmin
      })
    });
    const result = await response.json();
    if (response.ok && result.success) {
      await loadUsersAndInitialize();
      addUserForm.reset();
    } else {
      alert(result.message);
    }
  } catch (error) {
    console.error(error);
    alert("Something went wrong.");
  }
}
async function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    const id = event.target.dataset.id;
    try {
      const response = await fetch(
        "../api/index.php?id=" + id,
        {
          method: "DELETE"
        }
      );
      const result = await response.json();
      if (result.success) {
        users = users.filter(user => user.id != id);
        renderTable(users);
      } else {
        alert(result.message);
      }
    } catch (error) {
      console.error(error);
      alert("Something went wrong.");
    }
  }
  if (event.target.classList.contains("edit-btn")) {
    const id = event.target.dataset.id;
    const user = users.find(user => user.id == id);
    const newName = prompt("Enter new name:", user.name);
    if (!newName) return;
    try {
      const response = await fetch("../api/index.php", {
        method: "PUT",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          id: id,
          name: newName
        })
      });
      const result = await response.json();
      if (result.success) {
        await loadUsersAndInitialize();
      } else {
        alert(result.message);
      }
    } catch (error) {
      console.error(error);
      alert("Something went wrong.");
    }
  }
}
function handleSearch(event) {
  const searchTerm = searchInput.value.toLowerCase();
  if (searchTerm === "") {
    renderTable(users);
    return;
  }
  const filteredUsers = users.filter(user => {
    return (
      user.name.toLowerCase().includes(searchTerm) ||
      user.email.toLowerCase().includes(searchTerm)
    );
  });
  renderTable(filteredUsers);
}
function handleSort(event) {
  const index = event.currentTarget.cellIndex;
  let property = "";
  if (index === 0) property = "name";
  if (index === 1) property = "email";
  if (index === 2) property = "is_admin";
  if (!property) return;
  let direction = event.currentTarget.dataset.sortDir || "asc";
  direction = direction === "asc" ? "desc" : "asc";
  event.currentTarget.dataset.sortDir = direction;
  users.sort((a, b) => {
    let comparison = 0;
    if (property === "is_admin") {
      comparison = a[property] - b[property];
    } else {
      comparison = a[property].localeCompare(b[property]);
    }
    return direction === "asc"
      ? comparison
      : -comparison;
  });
  renderTable(users);
}
async function loadUsersAndInitialize() {
  try {
    const response = await fetch("../api/index.php");
    if (!response.ok) {
      alert("Failed to load users.");
      return;
    }
    const result = await response.json();
    users = result.data;
    renderTable(users);
    changePasswordForm.addEventListener(
      "submit",
      handleChangePassword
    );
    addUserForm.addEventListener(
      "submit",
      handleAddUser
    );
    userTableBody.addEventListener(
      "click",
      handleTableClick
    );
    searchInput.addEventListener(
      "input",
      handleSearch
    );
    tableHeaders.forEach(th => {
      th.addEventListener(
        "click",
        handleSort
      );
    });
  } catch (error) {
    console.error(error);
    alert("Error loading users.");
  }
}
loadUsersAndInitialize();
