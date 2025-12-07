document.addEventListener("DOMContentLoaded", () => {
  const mainContent = document.getElementById("mainContent");
  const navLinks = document.querySelectorAll(".nav-link");
  
  // Get current page from URL or default
  const urlParams = new URLSearchParams(window.location.search);
  const defaultPage = getDefaultPageByRole();
  
  // Load default page on initial load
  if (mainContent.innerHTML.trim() === '<div class="loading">Loading content...</div>') {
    loadPage(defaultPage);
  }

  // Set active link based on current page
  setActiveNavLink();

  // Add click handlers for nav links
  navLinks.forEach(link => {
    link.addEventListener("click", async (e) => {
      e.preventDefault();
      const page = link.getAttribute("data-page");
      
      if (!page) return;
      
      // Update URL without page reload (for bookmarking)
      updateUrl(page);
      
      // Update active state
      setActiveNavLink(page);
      
      // Load page dynamically
      await loadPage(page);
    });
  });

  // Handle browser back/forward buttons
  window.addEventListener('popstate', (event) => {
    if (event.state && event.state.page) {
      loadPage(event.state.page);
      setActiveNavLink(event.state.page);
    }
  });

  // Function to load pages dynamically
  async function loadPage(page) {
    if (!page) return;
    
    // Show loading state
    mainContent.innerHTML = `
      <div class="loading-state">
        <div class="spinner"></div>
        <p>Loading ${getPageName(page)}...</p>
      </div>
    `;
    
    try {
      // Add timestamp to prevent caching issues
      const timestamp = new Date().getTime();
      const pageUrl = `${page}?t=${timestamp}`;
      
      const response = await fetch(pageUrl);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const html = await response.text();
      
      // Check if response is valid HTML
      if (html.includes('<!DOCTYPE') || html.includes('<html')) {
        throw new Error('Received full page instead of fragment');
      }
      
      // Set content with fade animation
      mainContent.style.opacity = '0.5';
      mainContent.innerHTML = html;
      
      // Reinitialize any scripts in the loaded content
      initializeLoadedScripts();
      
      // Smooth transition
      setTimeout(() => {
        mainContent.style.opacity = '1';
        mainContent.style.transition = 'opacity 0.3s ease';
      }, 50);
      
      // Update page title
      updatePageTitle(page);
      
      // Scroll to top
      window.scrollTo({ top: 0, behavior: 'smooth' });
      
    } catch (error) {
      console.error('Error loading page:', error);
      mainContent.innerHTML = `
        <div class="error-state">
          <div class="error-icon">⚠️</div>
          <h3>Error Loading Content</h3>
          <p>Failed to load ${getPageName(page)}. Please try again.</p>
          <button onclick="location.reload()" class="retry-btn">Retry</button>
          <button onclick="loadPage('${defaultPage}')" class="home-btn">Go to Dashboard</button>
        </div>
      `;
      
      mainContent.style.opacity = '1';
    }
  }

  // Helper function to get default page based on user role
  function getDefaultPageByRole() {
    // You can get role from a data attribute or global variable
    const role = document.body.getAttribute('data-role') || 
                 document.querySelector('.sidebar-user')?.textContent?.match(/(admin|counselor|adviser|guardian)/i)?.[0]?.toLowerCase();
    
    const defaultPages = {
      'admin': 'dashboard.php',
      'counselor': 'dashboard.php',
      'adviser': 'dashboard.php',
      'guardian': 'dashboard.php',
      'student': 'dashboard.php'
    };
    
    return defaultPages[role] || 'dashboard.php';
  }

  // Helper to set active nav link
  function setActiveNavLink(activePage = null) {
    // If no page specified, get from URL
    if (!activePage) {
      const urlPage = new URLSearchParams(window.location.search).get('page');
      if (urlPage) {
        activePage = urlPage;
      }
    }
    
    navLinks.forEach(link => {
      const linkPage = link.getAttribute('data-page');
      if (linkPage === activePage) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });
  }

  // Update URL for bookmarking
  function updateUrl(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    window.history.pushState({ page }, '', url);
  }

  // Update page title based on loaded page
  function updatePageTitle(page) {
    const pageTitles = {
      'dashboard.php': 'Dashboard',
      'manage_users.php': 'Manage Users',
      'appointments.php': 'Appointments',
      'complaints.php': 'Complaints',
      'reports.php': 'Reports',
      'audit_logs.php': 'Audit Logs',
      'profile.php': 'My Profile',
      'link_student.php': 'Link Student',
      'request_appointment.php': 'Request Appointment'
    };
    
    const role = document.querySelector('.sidebar-user')?.textContent?.match(/(admin|counselor|adviser|guardian)/i)?.[0] || 'User';
    const baseTitle = `${role} - GOMS`;
    const pageName = pageTitles[page] || getPageName(page);
    
    document.title = `${pageName} | ${baseTitle}`;
  }

  // Extract page name from filename
  function getPageName(page) {
    return page.replace('.php', '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
  }

  // Reinitialize scripts in loaded content
  function initializeLoadedScripts() {
    // Find and execute any script tags in the loaded content
    const scripts = mainContent.querySelectorAll('script');
    scripts.forEach(script => {
      const newScript = document.createElement('script');
      
      // Copy all attributes
      Array.from(script.attributes).forEach(attr => {
        newScript.setAttribute(attr.name, attr.value);
      });
      
      // Copy content
      if (script.textContent) {
        newScript.textContent = script.textContent;
      }
      
      // Replace old script with new one (executes it)
      script.parentNode.replaceChild(newScript, script);
    });
    
    // Reattach event listeners to buttons/forms in loaded content
    reattachEventListeners();
  }

  // Reattach event listeners for elements in loaded content
  function reattachEventListeners() {
    // Example: Reattach form submissions
    const forms = mainContent.querySelectorAll('form');
    forms.forEach(form => {
      form.addEventListener('submit', function(e) {
        // Add your form handling logic here
        console.log('Form submitted:', this.id || 'unnamed form');
      });
    });
    
    // Example: Reattach button clicks
    const buttons = mainContent.querySelectorAll('button[data-action]');
    buttons.forEach(button => {
      button.addEventListener('click', function() {
        const action = this.getAttribute('data-action');
        console.log('Button action:', action);
      });
    });
  }

  // Add keyboard shortcuts
  document.addEventListener('keydown', (e) => {
    // Ctrl + 1-9 for quick navigation (if you have numbered nav items)
    if (e.ctrlKey && e.key >= '1' && e.key <= '9') {
      const index = parseInt(e.key) - 1;
      if (navLinks[index]) {
        e.preventDefault();
        navLinks[index].click();
      }
    }
    
    // Escape to go back to dashboard
    if (e.key === 'Escape') {
      const dashboardLink = document.querySelector('.nav-link[data-page*="dashboard"]');
      if (dashboardLink) {
        dashboardLink.click();
      }
    }
  });

  // Add some CSS styles for loading/error states
  if (!document.querySelector('#dashboard-styles')) {
    const style = document.createElement('style');
    style.id = 'dashboard-styles';
    style.textContent = `
      .loading-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--color-muted, #6b7280);
      }
      
      .spinner {
        width: 40px;
        height: 40px;
        border: 3px solid var(--color-border, #e5e7eb);
        border-top: 3px solid var(--color-primary, #2563eb);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
      }
      
      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }
      
      .error-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--color-danger, #dc2626);
      }
      
      .error-icon {
        font-size: 48px;
        margin-bottom: 20px;
      }
      
      .retry-btn, .home-btn {
        padding: 10px 20px;
        margin: 10px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
      }
      
      .retry-btn {
        background: var(--color-primary, #2563eb);
        color: white;
      }
      
      .home-btn {
        background: var(--color-secondary, #6b7280);
        color: white;
      }
      
      .retry-btn:hover, .home-btn:hover {
        opacity: 0.9;
      }
    `;
    document.head.appendChild(style);
  }
});