// sidebar.js
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("sidebar");
  const toggle = document.getElementById("sidebarToggle");
  const STORAGE_KEY = "goms_sidebar_collapsed";

  if (!sidebar || !toggle) return;

  // Initialize from localStorage
  const collapsed = localStorage.getItem(STORAGE_KEY) === "true";
  if (collapsed) {
    sidebar.classList.add("collapsed");
    document.body.classList.add("sidebar-collapsed");
  }

  // Toggle function
  toggle.addEventListener("click", (e) => {
    e.preventDefault();
    sidebar.classList.toggle("collapsed");
    const isCollapsed = sidebar.classList.contains("collapsed");
    document.body.classList.toggle("sidebar-collapsed", isCollapsed);
    localStorage.setItem(STORAGE_KEY, isCollapsed);
  });

  // Keyboard accessibility
  toggle.addEventListener("keydown", (ev) => {
    if (ev.key === "Enter" || ev.key === " ") {
      toggle.click();
    }
  });
});
