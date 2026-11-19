(() => {
    const instances = new WeakMap();
    const element = (tag, className, text = '') => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        node.textContent = text;
        return node;
    };

    const bind = root => {
        if (instances.has(root) || !root.isConnected) return;
        const controller = new AbortController();
        const status = root.querySelector('[data-sync-state]');
        const countdown = root.querySelector('[data-sync-countdown]');
        const logs = root.querySelector('[data-sync-logs]');
        const button = root.querySelector('[data-sync-start]');
        const conflictsOpen = root.querySelector('[data-sync-conflicts-open]');
        const conflictsPanel = root.querySelector('[data-sync-conflicts]');
        const conflictsList = root.querySelector('[data-sync-conflict-list]');
        const conflictsMessage = root.querySelector('[data-sync-conflict-message]');
        const resolveButton = root.querySelector('[data-sync-resolve]');
        let nextRun;
        let refreshInterval;
        let tickInterval;
        let refreshPromise;
        let refreshRequested = false;
        let conflictMeta = [];
        let conflictFingerprint = null;
        let conflictFiles = [];
        let conflictExpanded = false;
        let conflictLoading = false;
        let conflictBusy = false;
        let conflictUnavailable = false;
        let recoveryRequired = false;
        let resolutionPending = false;
        let hasConflicts = false;
        let syncNotice = '';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        const updateResolveButton = () => {
            const ready = conflictFiles.length > 0 && conflictFiles.every(file => file.binary
                ? Boolean(file.choice)
                : file.mode_only_conflict
                    ? Boolean(file.modeChoice)
                    : file.sections.length > 0 && file.sections.every(section => Boolean(section.choice))
                        && (!file.mode_conflict || Boolean(file.modeChoice)));
            resolveButton.disabled = !ready || conflictLoading || conflictBusy || conflictUnavailable || recoveryRequired || resolutionPending;
        };

        const setConflictMessage = (message = '') => {
            conflictsMessage.textContent = message;
            conflictsMessage.hidden = !message;
        };

        const showVersion = (container, label, value) => {
            const item = element('div', 'app-content-sync-version');
            item.append(element('p', 'app-content-sync-side-title text-sm', label));
            item.append(element('pre', '', value || '—'));
            container.append(item);
        };

        const renderConflicts = data => {
            conflictsList.replaceChildren();
            conflictFiles = data.files.map(file => ({ ...file, choice: '', modeChoice: '', sections: file.sections.map(section => ({ ...section, choice: '', text: section.ours || '' })) }));
            conflictFingerprint = data.fingerprint;

            conflictFiles.forEach(file => {
                const card = element('article', 'app-content-sync-file');
                const path = element('h3', 'app-content-sync-file-path');
                const code = element('code', '', file.path);
                path.append(code);
                card.append(path);

                if (file.binary) {
                    card.append(element('p', 'mt-2 text-sm text-gray-500', 'Binärdatei. Wähle die Version für diese Datei einzeln aus.'));
                    const choice = element('label', 'app-content-sync-choice', 'Version für diese Datei');
                    const select = document.createElement('select');
                    const placeholder = element('option', '', 'Version auswählen');
                    placeholder.value = '';
                    select.append(placeholder);
                    for (const [value, label, available, deletes] of [
                        ['ours', 'Lokale Version', file.ours_available, file.ours_deletes],
                        ['theirs', 'Eingehende Version', file.theirs_available, file.theirs_deletes],
                    ]) {
                        const option = element('option', '', deletes ? `${label} (Datei entfernen)` : label);
                        option.value = value;
                        option.disabled = !available && !deletes;
                        select.append(option);
                    }
                    if (!file.ours_available && !file.ours_deletes && !file.theirs_available && !file.theirs_deletes) {
                        card.append(element('p', 'mt-2 text-sm text-gray-500', 'Dieser Binärkonflikt ist zu groß oder hat ein nicht unterstütztes Dateiformat. Der Kandidat bleibt unverändert.'));
                    }
                    select.addEventListener('change', () => {
                        file.choice = select.value;
                        updateResolveButton();
                    }, { signal: controller.signal });
                    choice.append(select);
                    card.append(choice);
                } else {
                    if (file.mode_conflict) {
                        const label = element('label', 'app-content-sync-choice', 'Dateirecht');
                        const select = document.createElement('select');
                        const placeholder = element('option', '', 'Dateirecht auswählen');
                        placeholder.value = '';
                        select.append(placeholder);
                        for (const [value, executable] of [['ours', file.ours_executable], ['theirs', file.theirs_executable]]) {
                            const option = element('option', '', `${value === 'ours' ? 'Lokale' : 'Eingehende'} Version: ${executable ? 'ausführbar' : 'nicht ausführbar'}`);
                            option.value = value;
                            select.append(option);
                        }
                        select.addEventListener('change', () => {
                            file.modeChoice = select.value;
                            updateResolveButton();
                        }, { signal: controller.signal });
                        label.append(select);
                        card.append(label);
                    }
                    if (file.mode_only_conflict) {
                        card.append(element('p', 'mt-2 text-sm text-gray-500', 'Der Dateiinhalt ist auf beiden Seiten gleich. Wähle nur das gewünschte Dateirecht.'));
                    }
                    file.sections.forEach(section => {
                        const panel = element('fieldset', 'app-content-sync-section');
                        const legend = element('legend', 'font-medium', `Konfliktabschnitt ${section.id + 1}`);
                        panel.append(legend);

                        if (section.protected) {
                            panel.append(element('p', 'mt-2 text-sm text-gray-500', 'Geschützte CV-Werte bleiben verborgen. Wähle für diesen Abschnitt eine Version.'));
                        } else {
                            const versions = element('div', 'app-content-sync-versions');
                            if (section.base !== null) showVersion(versions, 'Gemeinsame Grundlage', section.base);
                            showVersion(versions, 'Lokale Version', section.ours);
                            showVersion(versions, 'Eingehende Version', section.theirs);
                            panel.append(versions);
                        }

                        const label = element('label', 'app-content-sync-choice', 'Auflösung für diesen Abschnitt');
                        const select = document.createElement('select');
                        const placeholder = element('option', '', 'Entscheidung auswählen');
                        placeholder.value = '';
                        select.append(placeholder);
                        for (const [value, text] of [['ours', 'Lokale Version verwenden'], ['theirs', 'Eingehende Version verwenden']]) {
                            const option = element('option', '', text);
                            option.value = value;
                            select.append(option);
                        }
                        if (!section.protected) {
                            const option = element('option', '', 'Manuell zusammenführen');
                            option.value = 'manual';
                            select.append(option);
                        }
                        const manual = document.createElement('textarea');
                        manual.className = 'app-content-sync-manual';
                        manual.setAttribute('aria-label', `Manuelle Auflösung für ${file.path}, Abschnitt ${section.id + 1}`);
                        manual.value = section.text;
                        manual.hidden = true;
                        select.addEventListener('change', () => {
                            section.choice = select.value;
                            manual.hidden = select.value !== 'manual';
                            updateResolveButton();
                        }, { signal: controller.signal });
                        manual.addEventListener('input', () => { section.text = manual.value; }, { signal: controller.signal });
                        label.append(select);
                        panel.append(label, manual);
                        card.append(panel);
                    });
                }

                conflictsList.append(card);
            });

            updateResolveButton();
        };

        const loadConflicts = async () => {
            if (conflictLoading) return;
            conflictLoading = true;
            conflictsOpen.disabled = true;
            resolveButton.disabled = true;
            setConflictMessage('Konfliktabschnitte werden geladen …');
            try {
                const response = await fetch(root.dataset.conflictsUrl, {
                    headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal,
                });
                if (!response.ok) throw new Error('Konfliktdetails sind nicht verfügbar.');
                const data = await response.json();
                if (!root.isConnected || !data.available || !data.fingerprint) throw new Error('Der Kandidatenkonflikt hat sich geändert. Lade den Status neu.');
                renderConflicts(data);
                setConflictMessage('Wähle für jeden Abschnitt eine Auflösung. Der Kandidat bleibt bis zur Validierung getrennt.');
            } catch (error) {
                if (error.name !== 'AbortError') setConflictMessage(error.message || 'Konfliktdetails sind nicht verfügbar.');
            } finally {
                conflictLoading = false;
                conflictsOpen.disabled = false;
                updateResolveButton();
            }
        };

        const refreshStatus = async () => {
            const response = await fetch(root.dataset.statusUrl, {
                headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal,
            });
            if (!response.ok) throw new Error('Status konnte nicht geladen werden.');
            const data = await response.json();
            const wasNearBottom = logs.scrollHeight - logs.scrollTop - logs.clientHeight <= 16;
            const previousScrollTop = logs.scrollTop;
            conflictBusy = Boolean(data.conflicts?.busy);
            conflictUnavailable = Boolean(data.conflicts?.unavailable);
            if (!conflictBusy && !conflictUnavailable) {
                recoveryRequired = Boolean(data.conflicts?.recovery_required);
            }
            if (data.running) syncNotice = '';
            if (!conflictBusy && !conflictUnavailable) {
                conflictMeta = data.conflicts?.files || [];
                hasConflicts = conflictMeta.length > 0;
                if (!hasConflicts) {
                    conflictFiles = [];
                    conflictFingerprint = null;
                    conflictsList.replaceChildren();
                }
            }
            conflictsOpen.hidden = !hasConflicts || conflictUnavailable || recoveryRequired;
            conflictsPanel.hidden = !hasConflicts || !conflictExpanded || conflictUnavailable || recoveryRequired;
            conflictsOpen.textContent = conflictExpanded ? 'Konflikte ausblenden' : 'Konflikte prüfen';

            status.textContent = conflictBusy
                ? 'Sync-Lock belegt. Konfliktdaten und Entwürfe bleiben erhalten.'
                : recoveryRequired
                    ? 'Kandidat muss nach unvollständigem Rollback manuell geprüft werden. Sync-Start ist gesperrt.'
                    : conflictUnavailable
                    ? 'Kandidatenstatus nicht verfügbar. Der Kandidat braucht eine manuelle Prüfung.'
                : data.running
                ? `Lauf aktiv seit ${data.started_at ? new Date(data.started_at).toLocaleString('de-DE') : 'soeben'}`
                : hasConflicts
                    ? `${conflictMeta.length} Konfliktdatei(en) warten auf Prüfung.`
                    : syncNotice || 'Kein Lauf aktiv';
            logs.textContent = data.logs || 'Noch keine Läufe protokolliert.';
            logs.scrollTop = wasNearBottom ? logs.scrollHeight : previousScrollTop;
            nextRun = new Date(data.next_run_at);
            button.disabled = data.running || conflictBusy || conflictUnavailable || recoveryRequired || hasConflicts;
            updateResolveButton();

            if (conflictExpanded && hasConflicts && conflictFiles.length === 0 && !conflictLoading) loadConflicts();
        };

        const refresh = () => {
            refreshRequested = true;
            if (refreshPromise) return refreshPromise;
            refreshPromise = (async () => {
                let lastError;
                while (refreshRequested && !controller.signal.aborted) {
                    refreshRequested = false;
                    try {
                        await refreshStatus();
                        lastError = null;
                    } catch (error) {
                        if (error.name === 'AbortError') throw error;
                        lastError = error;
                        status.textContent = error.message;
                    }
                }
                if (lastError) throw lastError;
            })().finally(() => { refreshPromise = undefined; });
            return refreshPromise;
        };

        const tick = () => {
            if (!nextRun || !Number.isFinite(nextRun.getTime())) return;
            const seconds = Math.max(0, Math.ceil((nextRun - Date.now()) / 1000));
            countdown.textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        };

        conflictsOpen.addEventListener('click', () => {
            conflictExpanded = !conflictExpanded;
            conflictsPanel.hidden = !conflictExpanded || !hasConflicts;
            conflictsOpen.textContent = conflictExpanded ? 'Konflikte ausblenden' : 'Konflikte prüfen';
            if (conflictExpanded && conflictFiles.length === 0) loadConflicts();
        }, { signal: controller.signal });
        root.querySelector('[data-sync-conflicts-reload]').addEventListener('click', () => {
            conflictFiles = [];
            conflictFingerprint = null;
            loadConflicts();
        }, { signal: controller.signal });

        resolveButton.addEventListener('click', async () => {
            if (!conflictFingerprint || resolutionPending) return;
            resolutionPending = true;
            updateResolveButton();
            setConflictMessage('Kandidat wird geprüft und erst nach erfolgreicher Validierung fortgesetzt …');
            const resolutions = conflictFiles.map(file => file.binary
                ? { path: file.path, choice: file.choice }
                : { path: file.path, sections: file.sections.map(section => ({
                    id: section.id,
                    choice: section.choice,
                    ...(section.choice === 'manual' ? { text: section.text } : {}),
                })), ...(file.mode_conflict ? { mode_choice: file.modeChoice } : {}) });
            try {
                const response = await fetch(root.dataset.resolveUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ fingerprint: conflictFingerprint, resolutions }),
                    signal: controller.signal,
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    if (data.recovery_required) {
                        recoveryRequired = true;
                        syncNotice = data.message || 'Der Kandidat muss manuell geprüft werden; Sync-Start ist gesperrt.';
                        await refresh();
                    }
                    throw new Error(data.message || 'Auflösung oder Kandidatenprüfung fehlgeschlagen. Der Live-Content wurde nicht geändert.');
                }
                syncNotice = data.started ? '' : (data.message || 'Konflikt aufgelöst. Starte die reguläre Synchronisierung mit dem Button oben.');
                conflictExpanded = false;
                conflictFiles = [];
                conflictFingerprint = null;
                setConflictMessage(data.message || (data.started
                    ? 'Kandidat validiert. Die reguläre Synchronisierung läuft.'
                    : 'Konflikt aufgelöst. Starte die reguläre Synchronisierung mit dem Button oben.'));
                await refresh();
            } catch (error) {
                if (error.name !== 'AbortError') setConflictMessage(error.message || 'Auflösung fehlgeschlagen. Lade die Konflikte neu.');
            } finally {
                resolutionPending = false;
                updateResolveButton();
            }
        }, { signal: controller.signal });

        button.addEventListener('click', async () => {
            button.disabled = true;
            syncNotice = '';
            try {
                const response = await fetch(root.dataset.startUrl, {
                    method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, signal: controller.signal,
                });
                if (!response.ok && response.status !== 409) throw new Error('Manueller Lauf konnte nicht gestartet werden.');
                await refresh();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    status.textContent = error.message;
                    button.disabled = false;
                }
            }
        }, { signal: controller.signal });

        instances.set(root, {
            dispose() {
                clearInterval(refreshInterval);
                clearInterval(tickInterval);
                controller.abort();
                instances.delete(root);
            },
        });
        refreshInterval = setInterval(() => refresh().catch(error => {
            if (error.name !== 'AbortError') status.textContent = error.message;
        }), 2000);
        tickInterval = setInterval(tick, 250);
        refresh().then(tick).catch(error => {
            if (error.name !== 'AbortError') status.textContent = error.message;
        });
    };

    const scan = node => {
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        if (node.matches('[data-content-sync]')) bind(node);
        node.querySelectorAll('[data-content-sync]').forEach(bind);
    };
    const dispose = node => {
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        if (node.matches('[data-content-sync]')) instances.get(node)?.dispose();
        node.querySelectorAll('[data-content-sync]').forEach(root => instances.get(root)?.dispose());
    };

    new MutationObserver(records => records.forEach(record => {
        record.addedNodes.forEach(scan);
        record.removedNodes.forEach(dispose);
    })).observe(document.documentElement, { childList: true, subtree: true });
    scan(document.documentElement);
})();
