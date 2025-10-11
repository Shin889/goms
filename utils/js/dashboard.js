document.addEventListener("DOMContentLoaded", () => {
  const mainContent = document.getElementById("mainContent");
  const navLinks = document.querySelectorAll(".nav-link");

  // Default page
  loadPage("manage_users.php");

  navLinks.forEach(link => {
    link.addEventListener("click", async (e) => {
      e.preventDefault();
      const page = link.getAttribute("data-page");

      // Update active state
      navLinks.forEach(l => l.classList.remove("active"));
      link.classList.add("active");

      // Load page dynamically
      await loadPage(page);
    });
  });

  async function loadPage(page) {
    mainContent.innerHTML = `<div class="loading">Loading...</div>`;
    try {
      const response = await fetch(page);
      if (!response.ok) throw new Error("Failed to load page");
      const html = await response.text();
      mainContent.innerHTML = html;
    } catch (error) {
      mainContent.innerHTML = `<div class="error">Error loading content. Please try again.</div>`;
      console.error(error);
    }
  }
});
