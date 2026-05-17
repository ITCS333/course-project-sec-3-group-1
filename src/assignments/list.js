/*
  Requirement: Populate the "Course Assignments" list page.
*/

// --- Element Selections ---
const assignmentListSection = document.getElementById('assignment-list-section');

// --- Functions ---

function createAssignmentArticle(assignment) {
  const article = document.createElement('article');

  const title = document.createElement('h2');
  title.textContent = assignment.title;

  const dueDate = document.createElement('p');
  dueDate.textContent = 'Due: ' + assignment.due_date;

  const description = document.createElement('p');
  description.textContent = assignment.description;

  const detailsLink = document.createElement('a');
  detailsLink.href = 'details.html?id=' + assignment.id;
  detailsLink.textContent = 'View Details & Discussion';

  article.appendChild(title);
  article.appendChild(dueDate);
  article.appendChild(description);
  article.appendChild(detailsLink);

  return article;
}

async function loadAssignments() {
  const response = await fetch('./api/index.php');
  const result = await response.json();

  assignmentListSection.innerHTML = '';

  if (result.success === true) {
    result.data.forEach(function (assignment) {
      const article = createAssignmentArticle(assignment);
      assignmentListSection.appendChild(article);
    });
  }
}

loadAssignments();

