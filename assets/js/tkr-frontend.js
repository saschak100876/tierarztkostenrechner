/**
 * Tierarztkostenrechner – Frontend App v1.1
 * Supports multiple shortcode instances per page via [data-tkr-instance].
 */
(function () {
  'use strict';

  const cfg = window.TKR || {};
  const api = cfg.apiBase || '/wp-json/tkr/v1';
  const t   = cfg.i18n   || {};

  // Emoji icons for animals (keyed by animal_uid)
  const ANIMAL_ICONS = {
    animal_cat:       '🐱',
    animal_dog:       '🐶',
    animal_rabbit:    '🐰',
    animal_guinea_pig:'🐹',
    animal_hamster:   '🐹',
    animal_bird:      '🐦',
    animal_horse:     '🐴',
    animal_rat:       '🐭',
    animal_mouse:     '🐭',
    animal_ferret:    '🦡',
    animal_reptile:   '🦎',
    animal_amphibian: '🐸',
  };

  // Human-readable descriptions for fee rules
  const RULE_DESCRIPTIONS = {
    rule_normal:          'GOT-Faktor 1-fach bis 3-fach – normaler Kostenrahmen.',
    rule_evening_night:   'GOT-Faktor 1-fach bis 3-fach – höhere Faktoren möglich, nicht automatisch Notdienst.',
    rule_weekend_holiday: 'GOT-Faktor 1-fach bis 3-fach – höhere Faktoren möglich, nicht automatisch Notdienst.',
    rule_emergency:       'GOT-Faktor 2-fach bis 4-fach + 50 € Notdienstgebühr einmalig pro Angelegenheit.',
    rule_unknown:         'Zeigt Vergleichsansicht: Normalfall vs. Notdienst.',
  };

  // ---- Utility ----
  function h(tag, attrs) {
    var el = document.createElement(tag);
    var children = Array.prototype.slice.call(arguments, 2);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (k === 'class') {
          el.className = v;
        } else if (k.indexOf('on') === 0 && typeof v === 'function') {
          el.addEventListener(k.slice(2).toLowerCase(), v);
        } else {
          el.setAttribute(k, v);
        }
      });
    }
    children.forEach(function (c) {
      if (c == null) return;
      el.append(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return el;
  }

  function fmt(num) {
    if (num == null || isNaN(num)) return '–';
    return Number(num).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
  }

  function apiFetch(path) {
    return fetch(api + path, { headers: { 'X-WP-Nonce': cfg.nonce || '' } })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); });
  }

  function apiPost(path, body) {
    return fetch(api + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
      body: JSON.stringify(body),
    }).then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); });
  }

  // ---- Per-instance initialiser ----
  function initInstance(root) {
    var state = { animal: null, subgroup: null, rule: null, treatment: null, sex: 'any', result: null };

    var defaultAnimal    = root.dataset.defaultAnimal    || '';
    var showDisclaimer   = root.dataset.showDisclaimer   !== '0';
    var layout           = root.dataset.layout           || 'full';

    root.classList.add('tkr-layout-' + layout);

    // ---- Render dispatcher ----
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
      var crumbs = [];
      if (state.animal) {
        crumbs.push({ label: state.animal.animal_label_de, action: resetToAnimal });
      }
      if (state.rule) {
        crumbs.push({ label: state.rule.rule_label_de, action: resetToRule });
      }
      if (state.treatment) {
        crumbs.push({ label: state.treatment.treatment_label_de || (t.notKnownYet || 'Suche'), action: null });
      }
      if (!crumbs.length) return;

      var wrap = h('div', { 'class': 'tkr-breadcrumb' });
      crumbs.forEach(function (c, i) {
        if (i > 0) wrap.append(h('span', { 'class': 'tkr-breadcrumb-sep' }, '›'));
        if (c.action) {
          wrap.append(h('span', { 'class': 'tkr-breadcrumb-item', onclick: c.action }, c.label));
        } else {
          wrap.append(h('span', { 'class': 'tkr-breadcrumb-current' }, c.label));
        }
      });
      root.append(wrap);
    }

    function resetToAnimal() {
      state.animal = null; state.subgroup = null; state.rule = null;
      state.treatment = null; state.result = null; state.sex = 'any';
      render();
    }
    function resetToRule() {
      state.rule = null; state.treatment = null; state.result = null; state.sex = 'any';
      render();
    }

    // ---- Loader ----
    function renderLoader(msg) {
      root.innerHTML = '';
      root.append(h('div', { 'class': 'tkr-loader' },
        h('div', { 'class': 'tkr-loader-spinner' }),
        h('div', {}, msg || (t.loading || 'Wird geladen …'))
      ));
    }

    // ---- Empty state ----
    function renderEmptyState() {
      root.innerHTML = '';
      root.append(h('div', { 'class': 'tkr-empty-state' },
        h('div', { 'class': 'tkr-empty-icon' }, '📋'),
        h('p', {}, t.noData || 'Es wurden noch keine Daten importiert.')
      ));
    }

    // ---- Error state ----
    function renderError(msg) {
      root.innerHTML = '';
      root.append(h('div', { 'class': 'tkr-error-state' }, msg || (t.errorLoad || 'Fehler beim Laden.')));
    }

    // ---- Step 1: Animal ----
    function renderAnimalStep() {
      var step = h('div', { 'class': 'tkr-step' },
        h('p', { 'class': 'tkr-step-heading' },
          h('span', { 'class': 'tkr-step-num' }, '1'),
          t.selectAnimal || 'Tierart wählen'
        )
      );
      var grid = h('div', { 'class': 'tkr-animal-grid' });
      step.append(grid);
      root.append(step);

      apiFetch('/animals').then(function (animals) {
        if (!animals || !animals.length) { renderEmptyState(); return; }
        animals.forEach(function (a) {
          var icon  = ANIMAL_ICONS[a.animal_uid] || '🐾';
          var card  = h('div', { 'class': 'tkr-animal-card', onclick: function () { selectAnimal(a); } },
            h('span', { 'class': 'tkr-animal-icon' }, icon),
            a.animal_label_de
          );
          grid.append(card);
        });
        if (defaultAnimal) {
          var found = animals.find(function (a) { return a.animal_uid === defaultAnimal; });
          if (found) selectAnimal(found);
        }
      }).catch(function () { renderError(t.errorLoad); });
    }

    function selectAnimal(animal) {
      state.animal = animal;
      if (parseInt(animal.has_subgroups || 0, 10)) {
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
      var step = h('div', { 'class': 'tkr-step' },
        h('p', { 'class': 'tkr-step-heading' },
          h('span', { 'class': 'tkr-step-num' }, '1b'),
          'Untergruppe wählen'
        )
      );
      var grid = h('div', { 'class': 'tkr-options' });

      var unknownBtn = h('div', { 'class': 'tkr-option', onclick: function () { state.subgroup = null; render(); } },
        t.dontKnowSubgroup || 'Weiß ich nicht'
      );
      grid.append(unknownBtn);
      step.append(grid);
      root.append(step);

      apiFetch('/subgroups?animal_uid=' + encodeURIComponent(animal.animal_uid)).then(function (subs) {
        subs.forEach(function (s) {
          var btn = h('div', { 'class': 'tkr-option', onclick: function () { state.subgroup = s; render(); } }, s.subgroup_label_de);
          grid.append(btn);
        });
      }).catch(function () {});
    }

    // ---- Step 2: Rule / Behandlungssituation ----
    function renderRuleStep() {
      var step = h('div', { 'class': 'tkr-step' },
        h('p', { 'class': 'tkr-step-heading' },
          h('span', { 'class': 'tkr-step-num' }, '2'),
          t.selectSituation || 'Behandlungssituation wählen'
        )
      );
      var list = h('div', { 'class': 'tkr-situation-list' });
      step.append(list);
      root.append(step);

      apiFetch('/fee-rules').then(function (rules) {
        if (!rules || !rules.length) { renderEmptyState(); return; }
        rules.forEach(function (r) {
          var desc = RULE_DESCRIPTIONS[r.rule_uid] || '';
          var card = h('div', { 'class': 'tkr-situation-option', onclick: function () { state.rule = r; render(); } },
            h('span', { 'class': 'tkr-situation-title' }, r.rule_label_de),
            desc ? h('span', { 'class': 'tkr-situation-desc' }, desc) : null
          );
          list.append(card);
        });
      }).catch(function () { renderError(t.errorLoad); });
    }

    // ---- Step 3+4: Treatment ----
    function renderTreatmentStep() {
      var step = h('div', { 'class': 'tkr-step' },
        h('p', { 'class': 'tkr-step-heading' },
          h('span', { 'class': 'tkr-step-num' }, '3'),
          t.selectTreatment || 'Behandlung wählen'
        )
      );
      var grid = h('div', { 'class': 'tkr-options' });
      step.append(grid);
      root.append(step);

      var qs = '?animal_uid=' + encodeURIComponent(state.animal.animal_uid)
        + (state.subgroup ? '&subgroup_uid=' + encodeURIComponent(state.subgroup.subgroup_uid) : '');

      apiFetch('/treatments' + qs).then(function (treatments) {
        if (!treatments || !treatments.length) { renderEmptyState(); return; }
        treatments.forEach(function (tr) {
          var label  = parseInt(tr.requires_search || 0, 10) ? (t.notKnownYet || 'Steht noch nicht fest') : tr.treatment_label_de;
          var isSearch = parseInt(tr.requires_search || 0, 10) || tr.treatment_uid === 'treatment_search';
          var btn = h('div', {
            'class': 'tkr-option' + (isSearch ? ' tkr-option--search' : ''),
            onclick: function () { selectTreatment(tr); }
          }, label);
          grid.append(btn);
        });
      }).catch(function () { renderError(t.errorLoad); });
    }

    function selectTreatment(treatment) {
      if (parseInt(treatment.requires_search || 0, 10) || treatment.treatment_uid === 'treatment_search') {
        renderSearchStep();
        return;
      }
      state.treatment = treatment;
      if (parseInt(treatment.requires_sex || 0, 10)) {
        renderSexStep();
      } else {
        doCalculate();
      }
    }

    // ---- Sex selection ----
    function renderSexStep() {
      root.innerHTML = '';
      renderBreadcrumb();
      var step = h('div', { 'class': 'tkr-step' },
        h('p', { 'class': 'tkr-step-heading' },
          h('span', { 'class': 'tkr-step-num' }, '3b'),
          t.sexQuestion || 'Geschlecht des Tieres'
        )
      );
      var group = h('div', { 'class': 'tkr-sex-group' });
      [
        ['male',   t.sexMale    || 'Männlich'],
        ['female', t.sexFemale  || 'Weiblich'],
        ['any',    t.sexUnknown || 'Unbekannt'],
      ].forEach(function (pair) {
        var btn = h('button', { 'class': 'tkr-sex-btn', onclick: function () { state.sex = pair[0]; doCalculate(); } }, pair[1]);
        group.append(btn);
      });
      step.append(group);
      root.append(step);
    }

    // ---- Search step ----
    function renderSearchStep() {
      root.innerHTML = '';
      renderBreadcrumb();
      var step = h('div', { 'class': 'tkr-step' },
        h('p', { 'class': 'tkr-step-heading' },
          h('span', { 'class': 'tkr-step-num' }, '4'),
          'Symptom, Behandlung oder Rasse suchen'
        )
      );
      var wrap  = h('div', { 'class': 'tkr-search-wrap' });
      var input = h('input', {
        'class': 'tkr-search-input',
        type: 'text',
        placeholder: t.searchPlaceholder || 'Suchen …',
        autocomplete: 'off',
      });
      var dropd = h('div', { 'class': 'tkr-autocomplete' });
      wrap.append(input, dropd);
      step.append(wrap);
      root.append(step);

      var timer = null;
      input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { dropd.classList.remove('tkr-open'); dropd.innerHTML = ''; return; }
        timer = setTimeout(function () { doSearch(q, dropd); }, 260);
      });
      input.addEventListener('blur', function () {
        setTimeout(function () { dropd.classList.remove('tkr-open'); }, 200);
      });
      input.focus();
    }

    function doSearch(q, dropd) {
      var qs = '?q=' + encodeURIComponent(q)
        + (state.animal   ? '&animal_uid='   + encodeURIComponent(state.animal.animal_uid)     : '')
        + (state.subgroup ? '&subgroup_uid=' + encodeURIComponent(state.subgroup.subgroup_uid) : '');

      apiFetch('/search' + qs).then(function (results) {
        dropd.innerHTML = '';
        if (!results || !results.length) {
          dropd.append(h('div', { 'class': 'tkr-autocomplete-noresult' }, t.noSearchResults || 'Keine Ergebnisse.'));
          dropd.classList.add('tkr-open');
          return;
        }
        results.forEach(function (r) {
          var badge = h('span', { 'class': 'tkr-autocomplete-badge' }, r.term_type);
          var item  = h('div', { 'class': 'tkr-autocomplete-item', onclick: function () { selectSearchResult(r); } },
            r.label, badge
          );
          dropd.append(item);
        });
        dropd.classList.add('tkr-open');
      }).catch(function () {});
    }

    function selectSearchResult(result) {
      if (!result.treatment_uid) return;
      apiFetch('/treatments?animal_uid=' + encodeURIComponent(state.animal ? state.animal.animal_uid : '')).then(function (treatments) {
        var found = treatments.find(function (tr) { return tr.treatment_uid === result.treatment_uid; });
        if (found) selectTreatment(found);
      }).catch(function () {});
    }

    // ---- Calculate ----
    function doCalculate() {
      root.innerHTML = '';
      renderLoader(t.calculating || 'Berechnung läuft …');

      apiPost('/calculate', {
        animal_uid:    state.animal.animal_uid,
        treatment_uid: state.treatment.treatment_uid,
        rule_uid:      state.rule.rule_uid,
        sex:           state.sex,
      }).then(function (data) {
        state.result = data;
        root.innerHTML = '';
        renderBreadcrumb();
        renderResultPanel(data);
        root.append(makeResetBtn());
      }).catch(function () {
        root.innerHTML = '';
        root.append(h('div', { 'class': 'tkr-error-state' }, t.errorCalc || 'Fehler bei der Berechnung.'));
        root.append(makeResetBtn());
      });
    }

    function renderResult() {
      if (state.result) {
        renderBreadcrumb();
        renderResultPanel(state.result);
        root.append(makeResetBtn());
      } else {
        doCalculate();
      }
    }

    function renderResultPanel(data) {
      var panel  = h('div', { 'class': 'tkr-result' });
      var range  = data.range || {};
      var isEmrg = state.rule && (state.rule.is_emergency == 1 || state.rule.rule_uid === 'rule_emergency');

      // Orientation badge
      panel.append(h('div', { 'class': 'tkr-orientation-badge' }, '📊 Unverbindliche Kostenorientierung'));

      if (range.mode === 'comparison') {
        var cmpWrap = h('div', { 'class': 'tkr-comparison' });
        cmpWrap.append(
          compBox(t.normalCase || 'Normalfall',            range.normal,    false),
          compBox(t.emergencyCase || 'Tierärztl. Notdienst', range.emergency, true)
        );
        panel.append(cmpWrap);
      } else {
        var amtClass = 'tkr-result__amount' + (isEmrg ? ' tkr-result__amount--emergency' : '');
        var rangeDiv = h('div', { 'class': 'tkr-result__range' },
          h('div', { 'class': 'tkr-result__label' }, t.orientationLabel || 'Kostenrahmen (unverbindlich)'),
          h('div', { 'class': amtClass }, fmt(range.grand_min) + ' – ' + fmt(range.grand_max))
        );
        if (range.fixed_fee > 0) {
          rangeDiv.append(h('div', { 'class': 'tkr-result__fixed' },
            '+ ' + fmt(range.fixed_fee) + ' ' + (t.emergencyFee || 'Notdienstgebühr (einmalig)')
          ));
        }
        panel.append(rangeDiv);
      }

      // Items
      var services = (range.items || []).filter(function (i) { return i.item_type !== 'note'; });
      var notes    = (range.items || []).filter(function (i) { return i.item_type === 'note'; });

      if (services.length) {
        var table = h('table', { 'class': 'tkr-items' },
          h('thead', {},
            h('tr', {},
              h('th', {}, 'GOT-Nr.'),
              h('th', {}, 'Leistung'),
              h('th', {}, '1-fach'),
              h('th', {}, 'Faktor')
            )
          )
        );
        var tbody = h('tbody', {});
        var factorStr = range.mode === 'comparison'
          ? '1x – 4x'
          : ((range.factor_min || '?') + 'x – ' + (range.factor_max || '?') + 'x');

        services.forEach(function (item) {
          tbody.append(h('tr', {},
            h('td', {}, item.got_number ? h('span', { 'class': 'tkr-got-badge' }, String(item.got_number)) : ''),
            h('td', {}, item.label_de || ''),
            h('td', { style: 'white-space:nowrap' }, item.fee_1x != null ? fmt(item.fee_1x) : ''),
            h('td', { style: 'white-space:nowrap' }, factorStr)
          ));
        });
        notes.forEach(function (note) {
          tbody.append(h('tr', { 'class': 'tkr-note-row' },
            h('td', { colspan: '4' }, 'ℹ️ ' + (note.label_de || note.user_note || ''))
          ));
        });
        table.append(tbody);
        panel.append(table);
      }

      // Notices
      if (data.notices && data.notices.length) {
        var noticeBox = h('div', { 'class': 'tkr-notices' });
        data.notices.forEach(function (n) { noticeBox.append(h('p', {}, n)); });
        panel.append(noticeBox);
      }

      // Disclaimer
      if (showDisclaimer) {
        panel.append(h('div', { 'class': 'tkr-disclaimer' },
          h('strong', {}, 'Hinweis: '),
          t.disclaimer || 'Unverbindliche Orientierung nach GOT. Keine Rechtsberatung, keine Diagnose.'
        ));
      }

      root.append(panel);
    }

    function compBox(title, scenario, isEmrg) {
      var cls = 'tkr-comparison__box' + (isEmrg ? ' tkr-comparison__box--emergency' : '');
      var sub = isEmrg ? ('inkl. ' + fmt(scenario.fixed_fee || 50) + ' Notdienstgebühr') : 'Faktor 1x – 3x';
      return h('div', { 'class': cls },
        h('div', { 'class': 'tkr-comparison__title' }, title),
        h('div', { 'class': 'tkr-comparison__amount' }, fmt(scenario.grand_min) + ' – ' + fmt(scenario.grand_max)),
        h('div', { 'class': 'tkr-comparison__sub' }, sub)
      );
    }

    function makeResetBtn() {
      return h('button', { 'class': 'tkr-btn-reset', onclick: resetToAnimal }, t.restartLabel || '↺ Neue Berechnung starten');
    }

    // ---- Bootstrap ----
    render();
  }

  // ---- Find all instances and initialise ----
  function bootstrap() {
    document.querySelectorAll('[data-tkr-instance]').forEach(function (el) {
      initInstance(el);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }
})();
