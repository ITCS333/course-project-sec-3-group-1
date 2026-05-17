/*
  Requirement: Make the "Manage Assignments" page interactive.
*/

// --- Global Data Store ---
let assignments = [];

// --- Element Selections ---
const assignmentForm = document.getElementById('assignment-form');
const assignmentsTbody = document.getElementById('assignments-tbody');
const submitButton = document.getElementById('add-assignment');

// --- Functions ---

function createAssignmentRow(assignment) {
  const row = document.createElement('tr');

  const titleCell = document.createElement('td');
  titleCell.textContent = assignment.title;

  const dueDateCell = document.createElement('td');
  dueDateCell.textContent = assignment.due_date;

  const descriptionCell = document.createElement('td');
  descriptionCell.textContent = assignment.description;

  const actionsCell = document.createElement('td');

  const editButton = document.createElement('button');
  editButton.textContent = 'Edit';
  editButton.className = 'edit-btn';
  editButton.dataset.id = assignment.id;

  const deleteButton = document.createElement('button');
  deleteButton.textContent = 'Delete';
  deleteButton.className = 'delete-btn';
  deleteButton.dataset.id = assignment.id;

  actionsCell.appendChild(editButton);
  actionsCell.appendChild(deleteButton);

  row.appendChild(titleCell);
  row.appendChild(dueDateCell);
  row.appendChild(descriptionCell);
  row.appendChild(actionsCell);

  return row;
}

function renderTable() {
  assignmentsTbody.innerHTML = '';

  assignments.forEach(function (assignment) {
    const row = createAssignmentRow(assignment);
    assignmentsTbody.appendChild(row);
  });
}

async function handleAddAssignment(event) {
  event.preventDefault();

  const title = document.getElementById('assignment-title').value;
  const due_date = document.getElementById('assignment-due-date').value;
  const description = document.getElementById('assignment-description').value;

  const files = document
    .getElementById('assignment-files')
    .value
    .split('\n')
    .map(file => file.trim())
    .filter(file => file !== '');

  const editId = submitButton.dataset.editId;

  if (editId) {
    await handleUpdateAssignment(parseInt(editId), {
      title,
      due_date,
      description,
      files
    });
    return;
  }

  const response = await fetch('./api/index.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      title,
      due_date,
      description,
      files
    })
  });

  const result = await response.json();

  if (result.success === true) {
    assignments.push({
      id: result.id,
      title,
      due_date,
      description,
      files
    });

    renderTable();
    assignmentForm.reset();
  }
}

async function handleUpdateAssignment(id, fields) {
  const response = await fetch('./api/index.php', {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      id,
      title: fields.title,
      due_date: fields.due_date,
      description: fields.description,
      files: fields.files
    })
  });

  const result = await response.json();

  if (result.success === true) {
    assignments = assignments.map(function (assignment) {
      if (assignment.id === id) {
        return {
          ...assignment,
          ...fields
        };
      }
      return assignment;
    });

    renderTable();
    assignmentForm.reset();

    submitButton.textContent = 'Add Assignment';
    delete submitButton.dataset.editId;
  }
}

async function handleTableClick(event) {
  if (event.target.classList.contains('delete-btn')) {
    const id = parseInt(event.target.dataset.id);

    const response = await fetch(`./api/index.php?id=${id}`, {
      method: 'DELETE'
    });

    const result = await response.json();

    if (result.success === true) {
      assignments = assignments.filter(function (assignment) {
        return assignment.id !== id;
      });

      renderTable();
    }
  }

  if (event.target.classList.contains('edit-btn')) {
    const id = parseInt(event.target.dataset.id);

    const assignment = assignments.find(function (assignment) {
      return assignment.id === id;
    });

    if (!assignment) {
      return;
    }

    document.getElementById('assignment-title').value = assignment.title;
    document.getElementById('assignment-due-date').value = assignment.due_date;
    document.getElementById('assignment-description').value = assignment.description;
    document.getElementById('assignment-files').value = assignment.files.join('\n');

    submitButton.textContent = 'Update Assignment';
    submitButton.dataset.editId = assignment.id;
  }
}

async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result = await response.json();

  if (result.success === true) {
    assignments = result.data;
    renderTable();
  }

  assignmentForm.addEventListener('submit', handleAddAssignment);
  assignmentsTbody.addEventListener('click', handleTableClick);
}

// --- Initial Page Load ---
loadAndInitialize();
