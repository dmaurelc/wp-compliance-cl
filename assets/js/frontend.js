(() => {
  const cfg = window.ComplianceCL || {};
  const cookieName = 'ccl_consent';
  const idKey = 'ccl_consent_uuid';

  const getConsent = () => {
    const match = document.cookie.match(new RegExp('(?:^|; )' + cookieName + '=([^;]*)'));
    if (!match) return null;
    try { return JSON.parse(atob(decodeURIComponent(match[1]))); } catch (e) { return null; }
  };

  const setCookie = (payload) => {
    const value = encodeURIComponent(btoa(JSON.stringify(payload)));
    document.cookie = `${cookieName}=${value}; Path=/; Max-Age=31536000; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
  };

  const uuid = () => {
    let value = localStorage.getItem(idKey);
    if (!value) {
      value = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = Math.random() * 16 | 0; const v = c === 'x' ? r : (r & 0x3 | 0x8); return v.toString(16);
      });
      localStorage.setItem(idKey, value);
    }
    return value;
  };

  const notifyConsentApi = (cats) => {
    if (typeof window.wp_set_consent !== 'function') return;
    ['functional', 'statistics', 'statistics-anonymous', 'marketing'].forEach(cat => {
      const map = cat.startsWith('statistics') ? 'analytics' : cat;
      try { window.wp_set_consent(cat, cats.includes(map) ? 'allow' : 'deny'); } catch (e) {}
    });
  };

  const activateBlocked = (categories) => {
    document.querySelectorAll('script[data-ccl-blocked="1"]').forEach(node => {
      if (!categories.includes(node.dataset.cclCategory)) return;
      const script = document.createElement('script');
      script.src = node.dataset.cclSrc;
      script.async = node.hasAttribute('async');
      script.defer = node.hasAttribute('defer');
      script.dataset.cclActivated = '1';
      node.replaceWith(script);
    });
  };

  const persist = async (categories, status = 'granted') => {
    const payload = { uuid: uuid(), version: cfg.consentVersion, categories, status };
    setCookie(payload);
    notifyConsentApi(categories);
    activateBlocked(categories);

    const form = new FormData();
    form.append('action', 'ccl_save_consent');
    form.append('nonce', cfg.nonce || '');
    form.append('uuid', payload.uuid);
    form.append('status', status);
    categories.forEach(c => form.append('categories[]', c));
    try { await fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form }); } catch (e) {}
    return payload;
  };

  const banner = document.getElementById('ccl-consent');
  const prefs = banner ? banner.querySelector('[data-ccl-preferences]') : null;

  const openBanner = (showPrefs = false) => {
    if (!banner) return;
    banner.hidden = false;
    if (prefs) prefs.hidden = !showPrefs;
    const current = getConsent();
    if (current && prefs) {
      prefs.querySelectorAll('[data-ccl-category]').forEach(input => {
        if (!input.disabled) input.checked = (current.categories || []).includes(input.dataset.cclCategory);
      });
    }
  };

  const closeBanner = () => { if (banner) banner.hidden = true; };

  if (banner) {
    const current = getConsent();
    if (!current || current.version !== cfg.consentVersion) openBanner(false);
    else activateBlocked(current.categories || ['necessary']);

    banner.addEventListener('click', async (event) => {
      const btn = event.target.closest('[data-ccl-action]');
      if (!btn) return;
      const action = btn.dataset.cclAction;
      if (action === 'configure') { if (prefs) prefs.hidden = false; return; }
      if (action === 'reject') { await persist(['necessary']); closeBanner(); return; }
      if (action === 'accept') { await persist(['necessary', 'functional', 'analytics', 'marketing']); closeBanner(); return; }
      if (action === 'withdraw') { await persist(['necessary'], 'revoked'); closeBanner(); return; }
      if (action === 'save') {
        const cats = ['necessary'];
        prefs.querySelectorAll('[data-ccl-category]:checked').forEach(input => { if (!cats.includes(input.dataset.cclCategory)) cats.push(input.dataset.cclCategory); });
        await persist(cats); closeBanner();
      }
    });
  }

  document.querySelectorAll('[data-ccl-open-preferences]').forEach(btn => btn.addEventListener('click', () => openBanner(true)));
})();
