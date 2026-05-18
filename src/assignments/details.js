/*
  Requirement: Populate the assignment detail page and discussion forum.
*/

// --- Global Data Store ---
let currentAssignmentId = null;
let currentComments = [];

// --- Element Selections ---
const assignmentTitle = document.getElementById('assignment-title');
const assignmentDueDate = document.getElementById('assignment-due-date');
const assignmentDescription = document.getElementById('assignment-description');
const assignmentFilesList = document.getElementById('assignment-files-list');
const commentList = document.getElementById('comment-list');
const commentForm = document.getElementById('comment-form');
const newCommentInput = document.getElementById('new-comment');

// --- Functions ---

function getAssignmentIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

function renderAssignmentDetails(assignment) {
  assignmentTitle.textContent = assignment.title;
  assignmentDueDate.textContent = 'Due: ' + assignment.due_date;
  assignmentDescription.textContent = assignment.description;

  assignmentFilesList.innerHTML = '';

  const files = Array.isArray(assignment.files) ? assignment.files : [];

  files.forEach(function (url) {
    const listItem = document.createElement('li');
    const link = document.createElement('a');

    link.href = url;
    link.textContent = url;

    listItem.appendChild(link);
    assignmentFilesList.appendChild(listItem);
  });
}

function createCommentArticle(comment) {
  const article = document.createElement('article');

  const paragraph = document.createElement('p');
  paragraph.textContent = comment.text;

  const footer = document.createElement('footer');
  footer.textContent = 'Posted by: ' + comment.author;

  article.appendChild(paragraph);
  article.appendChild(footer);

  return article;
}

function renderComments() {
  commentList.innerHTML = '';

  currentComments.forEach(function (comment) {
    const article = createCommentArticle(comment);
    commentList.appendChild(article);
  });
}

async function handleAddComment(event) {
  event.preventDefault();

  const commentText = newCommentInput.value.trim();

  if (commentText === '') {
    return;
  }

  const response = await fetch('./api/index.php?action=comment', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      assignment_id: parseInt(currentAssignmentId),
      author: 'Student',
      text: commentText
    })
  });

  const result = await response.json();

  if (result.success === true) {
    currentComments.push(result.data);
    renderComments();
    newCommentInput.value = '';
  }
}

async function initializePage() {
  currentAssignmentId = getAssignmentIdFromURL();

  if (!currentAssignmentId) {
    assignmentTitle.textContent = 'Assignment not found.';
    return;
  }

  const assignmentResponse = await fetch('./api/index.php?id=' + currentAssignmentId);
  const assignmentResult = await assignmentResponse.json();

  const commentsResponse = await fetch(
    './api/index.php?action=comments&assignment_id=' + currentAssignmentId
  );
  const commentsResult = await commentsResponse.json();

  if (assignmentResult.success === true && assignmentResult.data) {
    renderAssignmentDetails(assignmentResult.data);
  } else {
    assignmentTitle.textContent = 'Assignment not found.';
    return;
  }

  if (commentsResult.success === true) {
    currentComments = commentsResult.data;
  } else {
    currentComments = [];
  }

  renderComments();

  commentForm.addEventListener('submit', handleAddComment);
}

// --- Initial Page Load ---
initializePage();
