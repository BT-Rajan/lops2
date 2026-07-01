(function () {
  'use strict';

  // --- Mobile nav rail toggle -------------------------------------------
  var menuToggle = document.querySelector('[data-menu-toggle]');
  var rail = document.querySelector('.rail');
  var scrim = document.querySelector('.scrim');

  function closeRail() {
    if (rail) rail.classList.remove('open');
    if (scrim) scrim.classList.remove('open');
  }

  if (menuToggle && rail) {
    menuToggle.addEventListener('click', function () {
      rail.classList.toggle('open');
      if (scrim) scrim.classList.toggle('open');
    });
  }
  if (scrim) scrim.addEventListener('click', closeRail);

  // --- Theme toggle (light/dark), remembered via cookie ------------------
  var themeToggle = document.querySelector('[data-theme-toggle]');
  function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    document.cookie = 'legalops_theme=' + theme + ';path=' + (window.APP_BASE_PATH || '/') + ';max-age=31536000';
  }
  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      setTheme(current);
    });
  }

  // --- Generic modal open/close ------------------------------------------
  document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var el = document.getElementById(btn.getAttribute('data-open-modal'));
      if (el) el.classList.add('open');
    });
  });
  document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var overlay = btn.closest('.modal-overlay');
      if (overlay) overlay.classList.remove('open');
    });
  });
  document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });

  // --- Global search: live results dropdown + '/' to focus, Esc to close --
  var searchForm = document.querySelector('[data-search-form]');
  var search = document.querySelector('[data-global-search]');
  var dropdown = document.querySelector('[data-search-dropdown]');
  var searchTimer = null;
  var activeIndex = -1;

  document.addEventListener('keydown', function (e) {
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
      e.preventDefault();
      if (search) search.focus();
    }
    if (e.key === 'Escape' && search) {
      search.blur();
      closeDropdown();
    }
  });

  function closeDropdown() {
    if (dropdown) dropdown.classList.remove('open');
    activeIndex = -1;
  }

  function renderResults(items) {
    if (!dropdown) return;
    if (!items.length) {
      dropdown.innerHTML = '<div class="search-empty">No matches yet — press Enter to search everything.</div>';
    } else {
      dropdown.innerHTML = items.map(function (r) {
        return '<div class="search-result" data-url="' + r.url.replace(/"/g, '&quot;') + '">' +
          '<div><div class="sr-title">' + escapeHtml(r.title) + '</div>' +
          '<div class="sr-sub">' + escapeHtml(r.sub) + '</div></div>' +
          '<span class="sr-type">' + escapeHtml(r.type) + '</span></div>';
      }).join('');
    }
    dropdown.classList.add('open');
    activeIndex = -1;
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  if (search && dropdown && searchForm) {
    search.addEventListener('input', function () {
      var q = search.value.trim();
      clearTimeout(searchTimer);

      if (q.length < 2) {
        closeDropdown();
        return;
      }

      dropdown.innerHTML = '<div class="search-loading">Searching…</div>';
      dropdown.classList.add('open');

      searchTimer = setTimeout(function () {
        var base = window.APP_BASE_PATH || '/';
        fetch(base.replace(/\/$/, '') + '/api/search.php?q=' + encodeURIComponent(q))
          .then(function (res) { return res.json(); })
          .then(function (data) { renderResults(data.results || []); })
          .catch(function () { dropdown.innerHTML = '<div class="search-empty">Search is unavailable right now.</div>'; });
      }, 220);
    });

    search.addEventListener('focus', function () {
      if (search.value.trim().length >= 2 && dropdown.innerHTML.trim() !== '') {
        dropdown.classList.add('open');
      }
    });

    dropdown.addEventListener('click', function (e) {
      var item = e.target.closest('.search-result');
      if (item && item.dataset.url) {
        window.location.href = item.dataset.url;
      }
    });

    search.addEventListener('keydown', function (e) {
      var items = dropdown.querySelectorAll('.search-result');
      if (!items.length || !dropdown.classList.contains('open')) return;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex = Math.min(activeIndex + 1, items.length - 1);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex = Math.max(activeIndex - 1, 0);
      } else if (e.key === 'Enter' && activeIndex >= 0) {
        e.preventDefault();
        window.location.href = items[activeIndex].dataset.url;
        return;
      } else {
        return;
      }

      items.forEach(function (el, i) { el.classList.toggle('active', i === activeIndex); });
      items[activeIndex].scrollIntoView({ block: 'nearest' });
    });

    document.addEventListener('click', function (e) {
      if (!searchForm.contains(e.target)) closeDropdown();
    });
  }

  // --- Auto-dismiss flash alerts ------------------------------------------
  document.querySelectorAll('[data-flash]').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 4500);
  });

  // --- First-visit keyboard shortcut hint ----------------------------------
  // Show a "Press / to search" bubble once per session under the search pill.
  if (search && !sessionStorage.getItem('lo_search_hint_seen')) {
    sessionStorage.setItem('lo_search_hint_seen', '1');
    var pill = document.querySelector('.search-pill');
    if (pill) {
      pill.style.position = 'relative';
      var bubble = document.createElement('div');
      bubble.className = 'search-hint-bubble';
      bubble.textContent = 'Press / to search from anywhere';
      pill.appendChild(bubble);
      setTimeout(function () {
        bubble.style.transition = 'opacity .4s ease';
        bubble.style.opacity = '0';
        setTimeout(function () { bubble.remove(); }, 400);
      }, 3500);
    }
  }
})();
