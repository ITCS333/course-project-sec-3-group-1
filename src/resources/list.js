/*
  Requirement: Populate the "Course Resources" list page.

  Instructions:
  1. Link this file to `list.html` using:
     <script src="list.js" defer></script>

  2. In `list.html`, add id="resource-list-section" to the
     <section> element that will contain the resource articles.

  3. Implement the TODOs below.
*/

// --- Element Selections ---
// Select the section for the resource list
const resourceListSection = document.querySelector('#resource-list-section');

// --- Functions ---

/**
 * Creates a resource article element.
 * @param {Object} resource
 * @returns {HTMLElement}
 */
function createResourceArticle(resource) {

  const { id, title, description, link } = resource;

  const article = document.createElement('article');
  article.classList.add('resource-card');

  article.innerHTML = `
    <h2>${title}</h2>

    <p>${description || 'No description available.'}</p>

    <p>
      <a href="${link}" target="_blank" rel="noopener noreferrer">
        Open Resource
      </a>
    </p>

    <p>
      <a href="details.html?id=${id}">
        View Resource & Discussion
      </a>
    </p>
  `;

  return article;
}

/**
 * Loads resources from the API and displays them.
 */
async function loadResources() {

  try {

    const response = await fetch('./api/index.php');

    const result = await response.json();

    // Clear existing content
    resourceListSection.innerHTML = '';

    if (result.success && Array.isArray(result.data)) {

      result.data.forEach(resource => {

        const article = createResourceArticle(resource);

        resourceListSection.appendChild(article);
      });

    } else {

      resourceListSection.innerHTML = `
        <p>Failed to load resources.</p>
      `;
    }

  } catch (error) {

    console.error('Error loading resources:', error);

    resourceListSection.innerHTML = `
      <p>An error occurred while loading resources.</p>
    `;
  }
}

// --- Initial Page Load ---
loadResources();