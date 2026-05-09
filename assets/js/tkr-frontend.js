/* Tierarztkostenrechner Frontend App */
(function () {
  'use strict';

  const cfg = window.TKR || {};
  const api = cfg.apiBase || '/wp-json/tkr/v1';
  const t   = cfg.i18n  || {};

  // ---- State ----
  const state = {
    animal:    null,
    subgroup:  null,
    rule:      null,
    treatment: null,
    sex:       'any',
    result:    null,
  };

  // ---- DOM root ----
  const root = document.getElementById('tkr-app');
  if (!root) return;

  // Read shortcode attrs
  const defaultAnimal    = root.dataset.default_animal    || '';
  const defaultTreatment = root.dataset.default_treatment || '';
  const showDisclaimer   = root.dataset.show_disclaimer !== '0';

  // ---- Render helpers ----
  function h(tag, attrs, ...children) {
    const el = document.createElement(tag);
    Object.entries(attrs || {}).forEach(([k, v]) => {
      if (k === 'class') el.className = v;
      else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
      else el.setAttribute(k, v);
    });
    children.forEach(c => {
      if (c == null) return;
      el.append(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return el;
  }

  function fmt(num) {
    return Number(num).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
  }

  // ---- API ----
  async function apiFetch(path) {
    const res = await fetch(api + path, {
      headers: { 'X-WP-Nonce': cfg.nonce || '' },
    });
    if (!res.ok) throw new Error('API error ' + res.status);
    return res.json();
  }

  async function apiPost(path, body) {
    const res = await fetch(api + path, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce || '',
      },
      body: JSON.stringify(body),
    });
    if (!res.ok) throw new Error('API error ' + res.status);
    return res.json();
  }

  // ---- Render ----
  function render() {
    root.innerHTML = '';
    renderBreadcrumb();

    if (!state.animal)    { renderAnimalStep();     return; }
    if (!state.rule)      { renderRuleStep();        return; }
    if (!state.treatment) { renderTreatmentStep();  return; }
    renderResult();
  }

  // ---- Breadcrumb ----
  function renderBreadcrumb() {
    const crumbs = [];
    if (state.animal) {
      crumbs.push({ label: state.animal.animal_label_de, action: () => { state.animal = null; state.subgroup = null; state.rule = null; state.treatment = null; state.result = null; render(); } });
    }
    if (state.rule) {
      crumbs.push({ label: state.rule.rule_label_de, action: () => { state.rule = null; state.treatment = null; state.result = null; render(); } });
    }
    if (state.treatment) {
      crumbs.push({ label: state.treatment.treatment_label_de, action: null });
    }
    if (crumbs.length === 0) return;
    const wrap = h('div', { class: 'tkr-breadcrumb' });
    crumbs.forEach((c, i) => {
      if (i > 0) wrap.append(h('span', { class: 'tkr-breadcrumb-sep' }, '›'));
      if (c.action) {
        const a = h('span', { class: 'tkr-breadcrumb-item', onclick: c.action }, c.label);
        wrap.append(a);
      } else {
        wrap.append(h('span', { class: 'tkr-breadcrumb-current' }, c.label));
      }
    });
    root.append(wrap);
  }

  // ---- Step 1: Animal ----
  function renderAnimalStep() {
    const step = h('div', { class: 'tkr-step' },
      h('p', { class: 'tkr-step-heading' },
        h('span', { class: 'tkr-step-num' }, '1'),
        t.selectAnimal || 'Tierart wählen'
      )
    );
    const grid = h('div', { class: 'tkr-options' });
    step.append(grid);
    root.append(step);

    apiFetch('/animals').then(animals => {
      animals.forEach(a => {
        const btn = h('div', { class: 'tkr-option', onclick: () => selectAnimal(a) }, a.animal_label_de);
        grid.append(btn);
      });
      if (defaultAnimal) {
        const found = animals.find(a => a.animal_uid === defaultAnimal);
        if (found) selectAnimal(found);
      }
    }).catch(() => {
      grid.append(h('p', {}, 'Fehler beim Laden der Tierarten.'));
    });
  }

  function selectAnimal(animal) {
    state.animal = animal;
    if (parseInt(animal.has_subgroups)) {
      renderSubgroupStep(animal);
    } else {
      state.subgroup = null;
      render();
    }
  }

  // ---- Step 1b: Subgroup ----
  function renderSubgroupStep(animal) {
    root.innerHTML = '';
    renderBreadcrumb();
    const step = h('div', { class: 'tkr-step' },
      h('p', { class: 'tkr-step-heading' },
        h('span', { class: 'tkr-step-num' }, '1b'),
        'Untergruppe wählen'
      )
    );
    const grid = h('div', { class: 'tkr-options' });
    step.append(grid);
    root.append(step);

    apiFetch('/subgroups?animal_uid=' + encodeURIComponent(animal.animal_uid)).then(subs => {
      // "don't know" option
      const unknown = h('div', { class: 'tkr-option', onclick: () => { state.subgroup = null; render(); } }, t.dontKnowSubgroup || 'Weiß ich nicht');
      grid.append(unknown);
      subs.forEach(s => {
        const btn = h('div', { class: 'tkr-option', onclick: () => { state.subgroup = s; render(); } }, s.subgroup_label_de);
        grid.append(btn);
      });
    });
  }

  // ---- Step 2: Rule (Behandlungssituation) ----
  function renderRuleStep() {
    const step = h('div', { class: 'tkr-step' },
      h('p', { class: 'tkr-step-heading' },
        h('span', { class: 'tkr-step-num' }, '2'),
        t.selectSituation || 'Behandlungssituation wählen'
      )
    );
    const grid = h('div', { class: 'tkr-options tkr-options--list' });
    step.append(grid);
    root.append(step);

    apiFetch('/fee-rules').then(rules => {
      rules.forEach(r => {
        const btn = h('div', { class: 'tkr-option', onclick: () => { state.rule = r; render(); } }, r.rule_label_de);
        grid.append(btn);
      });
    });
  }

  // ---- Step 3+4: Treatment ----
  function renderTreatmentStep() {
    const step = h('div', { class: 'tkr-step' },
      h('p', { class: 'tkr-step-heading' },
        h('span', { class: 'tkr-step-num' }, '3'),
        t.selectTreatment || 'Behandlung wählen'
      )
    );
    const grid = h('div', { class: 'tkr-options' });
    step.append(grid);
    root.append(step);

    const qs = '?animal_uid=' + encodeURIComponent(state.animal.animal_uid)
      + (state.subgroup ? '&subgroup_uid=' + encodeURIComponent(state.subgroup.subgroup_uid) : '');

    apiFetch('/treatments' + qs).then(treatments => {
      treatments.forEach(tr => {
        const label = tr.requires_search ? (t.notKnownYet || 'Steht noch nicht fest') : tr.treatment_label_de;
        const btn = h('div', { class: 'tkr-option', onclick: () => selectTreatment(tr) }, label);
        grid.append(btn);
      });
      if (defaultTreatment) {
        const found = treatments.find(tr => tr.treatment_uid === defaultTreatment);
        if (found && !found.requires_search) selectTreatment(found);
      }
    });
  }

  function selectTreatment(treatment) {
    if (treatment.requires_search || treatment.treatment_uid === 'treatment_search') {
      renderSearchStep();
      return;
    }
    state.treatment = treatment;
    if (parseInt(treatment.requires_sex || 0)) {
      renderSexStep(treatment);
    } else {
      doCalculate();
    }
  }

  // ---- Sex selection ----
  function renderSexStep(treatment) {
    root.innerHTML = '';
    renderBreadcrumb();
    const step = h('div', { class: 'tkr-step' },
      h('p', { class: 'tkr-step-heading' },
        h('span', { class: 'tkr-step-num' }, '3b'),
        'Geschlecht des Tieres'
      )
    );
    const wrap = h('div', { class: 'tkr-sex-select' });
    [['male', 'Männlich'], ['female', 'Weiblich'], ['any', 'Unbekannt']].forEach(([val, label]) => {
      const btn = h('button', { class: 'tkr-sex-btn', onclick: () => { state.sex = val; doCalculate(); } }, label);
      wrap.append(btn);
    });
    step.append(wrap);
    root.append(step);
  }

  // ---- Search step ----
  function renderSearchStep() {
    root.innerHTML = '';
    renderBreadcrumb();
    const step = h('div', { class: 'tkr-step' });
    const heading = h('p', { class: 'tkr-step-heading' },
      h('span', { class: 'tkr-step-num' }, '4'),
      'Symptom, Behandlung oder Rasse suchen'
    );
    const wrap   = h('div', { class: 'tkr-search-wrap' });
    const input  = h('input', { class: 'tkr-search-input', type: 'text', placeholder: t.searchPlaceholder || 'Eingabe ...', autocomplete: 'off' });
    const dropd  = h('div', { class: 'tkr-autocomplete' });
    wrap.append(input, dropd);
    step.append(heading, wrap);
    root.append(step);

    let timer = null;
    input.addEventListener('input', () => {
      clearTimeout(timer);
      const q = input.value.trim();
      if (q.length < 2) { dropd.classList.remove('tkr-open'); dropd.innerHTML = ''; return; }
      timer = setTimeout(() => doSearch(q, dropd), 250);
    });
    input.addEventListener('blur', () => setTimeout(() => dropd.classList.remove('tkr-open'), 200));
    input.focus();
  }

  function doSearch(q, dropd) {
    const qs = '?q=' + encodeURIComponent(q)
      + (state.animal   ? '&animal_uid='   + encodeURIComponent(state.animal.animal_uid)     : '')
      + (state.subgroup ? '&subgroup_uid=' + encodeURIComponent(state.subgroup.subgroup_uid) : '');

    apiFetch('/search' + qs).then(results => {
      dropd.innerHTML = '';
      if (!results.length) {
        dropd.append(h('div', { class: 'tkr-autocomplete-item' }, 'Keine Ergebnisse gefunden.'));
        dropd.classList.add('tkr-open');
        return;
      }
      results.forEach(r => {
        const badge = h('span', { class: 'tkr-autocomplete-badge' }, r.term_type);
        const item  = h('div', { class: 'tkr-autocomplete-item', onclick: () => selectSearchResult(r) }, r.label, badge);
        dropd.append(item);
      });
      dropd.classList.add('tkr-open');
    });
  }

  function selectSearchResult(result) {
    if (result.treatment_uid) {
      apiFetch('/treatments?animal_uid=' + encodeURIComponent(state.animal ? state.animal.animal_uid : '')).then(treatments => {
        const found = treatments.find(tr => tr.treatment_uid === result.treatment_uid);
        if (found) {
          selectTreatment(found);
        }
      });
    }
  }

  // ---- Calculate ----
  async function doCalculate() {
    root.innerHTML = '';
    renderBreadcrumb();
    root.append(h('div', { class: 'tkr-loader' }, 'Berechnung wird durchgeführt …'));

    try {
      const data = await apiPost('/calculate', {
        animal_uid:    state.animal.animal_uid,
        treatment_uid: state.treatment.treatment_uid,
        rule_uid:      state.rule.rule_uid,
        sex:           state.sex,
      });
      state.result = data;
      renderResultPanel(data);
    } catch (e) {
      root.innerHTML = '';
      root.append(h('p', {}, 'Fehler bei der Berechnung.'));
    }
  }

  function renderResult() {
    if (state.result) { renderResultPanel(state.result); return; }
    doCalculate();
  }

  function renderResultPanel(data) {
    root.innerHTML = '';
    renderBreadcrumb();

    const panel  = h('div', { class: 'tkr-result' });
    const range  = data.range;
    const isEmrg = state.rule && state.rule.is_emergency == 1;

    if (range.mode === 'comparison') {
      const cmp = h('div', { class: 'tkr-comparison' });
      cmp.append(
        comparisonBox('Normalfall', range.normal.grand_min, range.normal.grand_max, false),
        comparisonBox('Tierärztlicher Notdienst', range.emergency.grand_min, range.emergency.grand_max, true)
      );
      panel.append(h('div', { class: 'tkr-result__label' }, 'Orientierungsrahmen nach GOT'));
      panel.append(cmp);
    } else {
      const amtClass = 'tkr-result__amount' + (isEmrg ? ' tkr-result__amount--emergency' : '');
      panel.append(
        h('div', { class: 'tkr-result__range' },
          h('div', { class: 'tkr-result__label' }, t.resultHeading || 'Orientierung nach GOT'),
          h('div', { class: amtClass }, fmt(range.grand_min) + ' – ' + fmt(range.grand_max)),
          range.fixed_fee > 0 ? h('div', { class: 'tkr-result__fixed-fee' },
            '+ ' + fmt(range.fixed_fee) + ' ' + (t.emergencyFee || 'Notdienstgebühr (einmalig)')
          ) : null
        )
      );
    }

    // Items table
    const services = (range.items || []).filter(i => i.item_type !== 'note');
    const notes    = (range.items || []).filter(i => i.item_type === 'note');

    if (services.length) {
      const table = h('table', { class: 'tkr-items' });
      table.append(h('thead', {},
        h('tr', {},
          h('th', {}, 'GOT-Nr.'),
          h('th', {}, 'Leistung'),
          h('th', {}, '1x'),
          h('th', {}, 'Faktor')
        )
      ));
      const tbody = h('tbody', {});
      services.forEach(item => {
        const factorLabel = range.mode === 'comparison'
          ? '1x – 4x'
          : (range.factor_min + 'x – ' + range.factor_max + 'x');
        tbody.append(h('tr', {},
          h('td', {}, item.got_number ? h('span', { class: 'tkr-got-badge' }, String(item.got_number)) : ''),
          h('td', {}, item.label_de || ''),
          h('td', {}, item.fee_1x != null ? fmt(item.fee_1x) : ''),
          h('td', {}, factorLabel)
        ));
      });
      notes.forEach(note => {
        tbody.append(h('tr', { class: 'tkr-note-row' },
          h('td', { colspan: '4' }, 'ℹ️ ' + (note.user_note || note.label_de || ''))
        ));
      });
      table.append(tbody);
      panel.append(table);
    }

    // Notices
    if (data.notices && data.notices.length) {
      const noticeBox = h('div', { class: 'tkr-notices' });
      data.notices.forEach(n => noticeBox.append(h('p', {}, n)));
      panel.append(noticeBox);
    }

    if (showDisclaimer) {
      panel.append(h('div', { class: 'tkr-disclaimer' }, t.disclaimer || 'Unverbindliche Orientierung. Keine Rechtsberatung, keine Diagnose.'));
    }

    root.append(panel);

    // Reset
    const resetBtn = h('button', { class: 'tkr-btn-reset', onclick: () => { Object.assign(state, { animal: null, subgroup: null, rule: null, treatment: null, sex: 'any', result: null }); render(); } }, '↺ Neue Berechnung starten');
    root.append(resetBtn);
  }

  function comparisonBox(title, min, max, isEmrg) {
    return h('div', { class: 'tkr-comparison__box' + (isEmrg ? ' tkr-comparison__box--emergency' : '') },
      h('div', { class: 'tkr-comparison__box-title' }, title),
      h('div', { class: 'tkr-comparison__box-amount' }, fmt(min) + ' – ' + fmt(max))
    );
  }

  // ---- Init ----
  render();
})();
