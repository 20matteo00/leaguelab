document.addEventListener('DOMContentLoaded', function () {

    // ── CREATE/EDIT: MODALITÀ ─────────────────────────────────────────────────
    const modalityEl = document.querySelector('[name="modality"]');
    const participantsEl = document.getElementById('participants_groups');
    const groupsEl = document.getElementById('num_groups');
    const qualifiedEl = document.getElementById('qualifiers');

    function onModalityChange() {
        const panels = { '1': 'mod-campionato', '2': 'mod-eliminazione', '3': 'mod-gruppi' };
        Object.values(panels).forEach(id => document.getElementById(id)?.classList.add('d-none'));
        const target = panels[modalityEl.value];
        if (target) document.getElementById(target)?.classList.remove('d-none');
    }

    function onParticipantsChange() {
        const n = parseInt(participantsEl.value);
        const saved = groupsEl.dataset.saved;
        groupsEl.innerHTML = '<option value="">-- seleziona --</option>';
        qualifiedEl.innerHTML = '<option value="">-- seleziona --</option>';
        if (!n) return;
        for (let i = 2; i <= n / 2; i++) {
            if (n % i === 0) groupsEl.appendChild(new Option(i, i, false, String(i) === saved));
        }
        if (saved) onGroupsChange();
    }

    function onGroupsChange() {
        const n = parseInt(participantsEl.value);
        const g = parseInt(groupsEl.value);
        const saved = qualifiedEl.dataset.saved;
        qualifiedEl.innerHTML = '<option value="">-- seleziona --</option>';
        if (!n || !g) return;
        const perGroup = n / g;
        for (let i = 1; i <= perGroup; i++) {
            qualifiedEl.appendChild(new Option(`${i} per gruppo`, i, false, String(i) === saved));
        }
    }

    modalityEl?.addEventListener('change', onModalityChange);
    participantsEl?.addEventListener('change', onParticipantsChange);
    groupsEl?.addEventListener('change', onGroupsChange);

    if (modalityEl) onModalityChange();
    if (participantsEl) {
        const savedP = participantsEl.dataset.saved;
        if (savedP) {
            participantsEl.value = savedP;
            onParticipantsChange();
        }
    }

    // ── SUBMIT: disabilita i campi nei pannelli nascosti ──────────────────────
    document.querySelector('form')?.addEventListener('submit', function () {
        document.querySelectorAll('#mod-campionato, #mod-eliminazione, #mod-gruppi').forEach(div => {
            if (div.classList.contains('d-none')) {
                div.querySelectorAll('input, select').forEach(el => el.disabled = true);
            }
        });
    });

    // ── CONFIGURE MODE 1: CAMPIONATO ──────────────────────────────────────────
    // renderLevels è esposta globalmente così il bottone "Aggiorna" può chiamarla
    // e il PHP inline può chiamarla dopo aver iniettato ALL_TEAMS/EXISTING_LEVELS
    window.renderLevels = function () {
        const container = document.getElementById('levels-container');
        if (!container) return;

        const n = parseInt(document.getElementById('num_levels').value) || 1;
        container.innerHTML = '';

        for (let lvl = 1; lvl <= n; lvl++) {
            const saved = (window.EXISTING_LEVELS?.[lvl]) || {};
            const numTeams = saved.num_teams || 0;
            const promotion = saved.promotion_spots || 0;
            const relegation = saved.relegation_spots || 0;

            const div = document.createElement('div');
            div.className = 'card mb-3';
            div.innerHTML = `
                <div class="card-header fw-semibold bg-light">
                    Livello ${lvl}
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Squadre nel livello</label>
                            <input type="number" name="level_${lvl}_teams"
                                   class="form-control level-teams-input"
                                   data-level="${lvl}"
                                   value="${numTeams}" min="2" max="64">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">
                                Promozioni
                                ${lvl === 1 ? '<span class="text-muted small">(N/A - top level)</span>' : ''}
                            </label>
                            <input type="number" name="level_${lvl}_promotion"
                                   class="form-control"
                                   value="${promotion}" min="0"
                                   ${lvl === 1 ? 'disabled' : ''}>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">
                                Retrocessioni
                                <span class="text-muted small" id="last-level-note-${lvl}"></span>
                            </label>
                            <input type="number" name="level_${lvl}_relegation"
                                   class="form-control"
                                   value="${relegation}" min="0">
                        </div>
                    </div>
                    <label class="form-label">Seleziona Squadre (livello ${lvl})</label>
                    <select name="level_${lvl}_team_ids[]" class="form-select" size="10" multiple>
                        ${(window.ALL_TEAMS || []).map(t =>
                `<option value="${t.id}">${t.name}</option>`
            ).join('')}
                    </select>
                    <small class="text-muted">Tieni premuto Ctrl/Cmd per selezionarne più di uno</small>
                </div>
            `;
            container.appendChild(div);

            const sel = div.querySelector(`select[name="level_${lvl}_team_ids[]"]`);

            // Counter
            const counterEl = document.createElement('div');
            counterEl.className = 'level-counter mt-1 fw-semibold small';
            div.querySelector('.card-body').appendChild(counterEl);

            function updateLevelCounter() {
                const n = sel.selectedOptions.length;
                counterEl.className = n > 0
                    ? 'level-counter mt-1 fw-semibold text-success small'
                    : 'level-counter mt-1 fw-semibold text-muted small';
                counterEl.textContent = n > 0 ? `✔ ${n} squadre selezionate` : '';
            }

            // Un solo listener che fa entrambe le cose
            sel.addEventListener('change', function () {
                const chosen = Array.from(this.selectedOptions).map(o => o.value);

                document.querySelectorAll('[name$="_team_ids[]"]').forEach(other => {
                    if (other === this) return;
                    Array.from(other.options).forEach(opt => {
                        if (chosen.includes(opt.value)) {
                            opt.disabled = true;
                            opt.selected = false;
                        } else {
                            const takenByOthers = Array.from(
                                document.querySelectorAll('[name$="_team_ids[]"]')
                            ).filter(s => s !== other && s !== this)
                                .some(s => Array.from(s.selectedOptions).map(o => o.value).includes(opt.value));
                            if (!takenByOthers) opt.disabled = false;
                        }
                    });
                });

                updateLevelCounter();
            });

            updateLevelCounter();
        }

        const lastNote = document.getElementById(`last-level-note-${n}`);
        if (lastNote) lastNote.textContent = '(N/A - bottom level)';
        const lastInput = container.querySelector(`input[name="level_${n}_relegation"]`);
        if (lastInput) lastInput.disabled = true;
    };

    // ── CONFIGURE MODE 2: ELIMINAZIONE ───────────────────────────────────────
    const elimSel = document.getElementById('elim-teams');
    if (elimSel) {
        function updateElimCounter() {
            const n = elimSel.selectedOptions.length;
            const el = document.getElementById('elim-counter');
            if (!el) return;
            if (n === window.ELIM_REQUIRED) {
                el.className = 'mt-2 fw-semibold text-success';
                el.textContent = `✔ ${n} squadre selezionate`;
            } else {
                el.className = 'mt-2 fw-semibold text-danger';
                el.textContent = `${n} / ${window.ELIM_REQUIRED} squadre selezionate`;
            }
        }
        elimSel.addEventListener('change', updateElimCounter);
        updateElimCounter();
    }

    // ── CONFIGURE MODE 3: GIRONI ─────────────────────────────────────────────
    document.querySelectorAll('.group-select').forEach(sel => {
        const req = parseInt(sel.dataset.required);
        const counter = sel.closest('.card-body')?.querySelector('.group-counter');

        function updateGroupCounter() {
            if (!counter) return;
            const n = sel.selectedOptions.length;
            if (n === req) {
                counter.className = 'group-counter mt-1 fw-semibold text-success small';
                counter.textContent = `✔ ${n} / ${req}`;
            } else {
                counter.className = 'group-counter mt-1 fw-semibold text-danger small';
                counter.textContent = `${n} / ${req}`;
            }
        }

        sel.addEventListener('change', function () {
            const chosen = Array.from(this.selectedOptions).map(o => o.value);

            document.querySelectorAll('.group-select').forEach(other => {
                if (other === this) return;
                Array.from(other.options).forEach(opt => {
                    if (chosen.includes(opt.value)) {
                        opt.disabled = true;
                        opt.selected = false;
                    } else {
                        const takenByOthers = Array.from(document.querySelectorAll('.group-select'))
                            .filter(s => s !== other && s !== this)
                            .some(s => Array.from(s.selectedOptions).map(o => o.value).includes(opt.value));
                        if (!takenByOthers) opt.disabled = false;
                    }
                });
                other.dispatchEvent(new Event('input'));
            });

            updateGroupCounter();
        });

        sel.addEventListener('input', updateGroupCounter);
        updateGroupCounter();
    });

});