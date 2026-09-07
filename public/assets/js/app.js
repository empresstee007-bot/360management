/**
 * 360Management ERP — Interactive JavaScript Engine
 */

document.addEventListener('DOMContentLoaded', () => {
  // Mobile Navigation Toggle
  const navToggle = document.getElementById('navToggle');
  const navMenu = document.getElementById('navMenu');

  if (navToggle && navMenu) {
    navToggle.addEventListener('click', () => {
      navMenu.classList.toggle('show');
    });
  }

  // Interactive AI Prompt Chips Simulator
  const promptChips = document.querySelectorAll('.prompt-chip');
  const promptResultBox = document.getElementById('promptResultBox');

  const promptResponses = {
    sales: {
      head: '● AI Sales Intelligence Summary (Today)',
      text: 'No real sales have been recorded yet. Start selling from the Beverage POS to build today\'s report.'
    },
    inventory: {
      head: '● AI Inventory & Reorder Forecast',
      text: 'No real inventory has been entered yet. Add beverage products and opening stock before using reorder forecasts.'
    },
    payments: {
      head: '● AI Payment Alert Reconciliation',
      text: 'No bank alerts have been imported yet. Connect a mailbox or enter real payment records to begin reconciliation.'
    },
    health: {
      head: '● AI System Health Status',
      text: 'The workspace is ready for real beverage data. Operational, finance, and inventory insights will appear after real records are entered.'
    }
  };

  promptChips.forEach(chip => {
    chip.addEventListener('click', () => {
      promptChips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');

      const category = chip.getAttribute('data-prompt');
      if (promptResponses[category] && promptResultBox) {
        const resp = promptResponses[category];
        promptResultBox.innerHTML = `
          <div class="prompt-result-head">
            <span>${resp.head}</span>
            <span>High Confidence (98%)</span>
          </div>
          <p>${resp.text}</p>
        `;
      }
    });
  });

  // Password Visibility Toggle
  const togglePasswordBtns = document.querySelectorAll('[data-toggle-password]');
  togglePasswordBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-toggle-password');
      const input = document.getElementById(targetId);
      if (input) {
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        btn.textContent = type === 'password' ? '👁' : '🙈';
      }
    });
  });
});

// Sidebar Collapsible Submenu Toggle
function toggleSubmenu(event, element) {
  if (event) event.stopPropagation();
  const parentGroup = element.closest('.bev-nav-item-group') || element.parentElement;
  const subMenu = parentGroup.querySelector('.bev-sub-menu');
  const arrow = parentGroup.querySelector('.bev-submenu-arrow');
  
  if (subMenu) {
    const isHidden = window.getComputedStyle(subMenu).display === 'none';
    subMenu.style.display = isHidden ? 'flex' : 'none';
    if (arrow) arrow.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
  }
}
