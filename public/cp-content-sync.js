(() => {
    const root = document.querySelector('[data-content-sync]');
    if (!root) return;

    const statusUrl = root.dataset.statusUrl;
    const runUrl = root.dataset.runUrl;
    const statusElement = root.querySelector('[data-sync-status]');
    const countdownElement = root.querySelector('[data-sync-countdown]');
    const startedElement = root.querySelector('[data-sync-started]');
    const sourceElement = root.querySelector('[data-sync-source]');
    const finishedElement = root.querySelector('[data-sync-finished]');
    const updatedElement = root.querySelector('[data-sync-updated]');
    const logElement = root.querySelector('[data-sync-log]');
    const messageElement = root.querySelector('[data-sync-message]');
    const form = root.querySelector('[data-sync-form]');
    const button = root.querySelector('[data-sync-button]');
    let nextRunAt = Date.parse(root.dataset.nextRun || '');
    let requestInFlight = false;
    let lastRunId = null;

    const dateText = (value) => {
        if (!value) return '–';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '–' : date.toLocaleString('de-DE');
    };

    const stateText = (state) => ({
        running: 'Läuft',
        completed: 'Abgeschlossen',
        failed: 'Fehlgeschlagen',
        interrupted: 'Unterbrochen',
    }[state] || 'Noch kein Lauf erfasst');

    const renderCountdown = () => {
        if (!(nextRunAt > 0)) {
            countdownElement.textContent = '–';
            return;
        }

        const seconds = Math.max(0, Math.ceil((nextRunAt - Date.now()) / 1000));
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        countdownElement.textContent = minutes > 0
            ? `in ${minutes} Min. ${String(remainingSeconds).padStart(2, '0')} Sek.`
            : `in ${remainingSeconds} Sek.`;
        countdownElement.dateTime = new Date(nextRunAt).toISOString();
    };

    const render = (payload) => {
        const run = payload.run;
        nextRunAt = Date.parse(payload.next_run_at || '');
        const state = run?.status || 'idle';
        statusElement.dataset.status = state;
        statusElement.textContent = stateText(state);
        startedElement.textContent = dateText(run?.started_at);
        sourceElement.textContent = run?.source || '–';
        finishedElement.textContent = dateText(run?.finished_at);

        const events = Array.isArray(run?.events) ? run.events : [];
        const logText = events.map((event) => `${dateText(event.at)} · ${event.message}`).join('\n')
            || 'Noch kein Sync-Lauf protokolliert.';
        if (logElement.textContent !== logText) {
            const wasAtBottom = logElement.scrollTop + logElement.clientHeight >= logElement.scrollHeight - 24;
            logElement.textContent = logText;
            if (wasAtBottom) logElement.scrollTop = logElement.scrollHeight;
        }

        if (run?.run_id !== lastRunId) {
            lastRunId = run?.run_id || null;
            if (state === 'interrupted') messageElement.textContent = 'Der letzte Lauf hält keine Sync-Sperre mehr. Möglicherweise wurde der Prozess beendet.';
            else if (state !== 'running') messageElement.textContent = '';
        }

        button.disabled = state === 'running' || requestInFlight;
        updatedElement.textContent = state === 'running' ? 'Lauf und Protokoll werden live aktualisiert' : 'Automatische Aktualisierung aktiv';
        renderCountdown();
    };

    const refresh = async () => {
        try {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error('Status konnte nicht geladen werden.');
            render(await response.json());
        } catch {
            updatedElement.textContent = 'Status derzeit nicht erreichbar';
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (requestInFlight) return;
        requestInFlight = true;
        button.disabled = true;
        messageElement.textContent = 'Synchronisierung wird gestartet. Das Protokoll erscheint während des Laufs.';

        const body = new FormData(form);
        try {
            const request = fetch(runUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || body.get('_token') || '',
                },
                body,
            });
            await refresh();
            const response = await request;
            if (!response.ok) throw new Error(response.status === 409 ? 'Ein Sync-Lauf ist bereits aktiv.' : 'Sync konnte nicht gestartet werden.');
            const payload = await response.json();
            render(payload);
            messageElement.textContent = payload.run?.status === 'failed'
                ? 'Der Sync ist fehlgeschlagen. Details stehen im Protokoll.'
                : payload.run?.status === 'completed'
                    ? 'Der Sync-Lauf ist beendet.'
                    : 'Der Sync-Lauf wurde angenommen.';
        } catch (error) {
            messageElement.textContent = error.message || 'Sync konnte nicht gestartet werden.';
            await refresh();
        } finally {
            requestInFlight = false;
            button.disabled = statusElement.dataset.status === 'running';
        }
    });

    refresh();
    window.setInterval(renderCountdown, 1000);
    window.setInterval(refresh, 2000);
})();
