(() => {
    const instances = new WeakMap();

    const bind = root => {
        if (instances.has(root) || !root.isConnected) return;
        const controller = new AbortController();
        const status = root.querySelector('[data-sync-state]');
        const logs = root.querySelector('[data-sync-logs]');
        const button = root.querySelector('[data-sync-start]');
        let nextRun;
        let refreshInterval;
        let tickInterval;
        let refreshPromise;
        let refreshRequested = false;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const refreshStatus = async () => {
            const response = await fetch(root.dataset.statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal });
            if (!response.ok) throw new Error('Status konnte nicht geladen werden.');
            const data = await response.json();
            const wasNearBottom = logs.scrollHeight - logs.scrollTop - logs.clientHeight <= 16;
            const previousScrollTop = logs.scrollTop;
            status.textContent = data.running
                ? `Lauf aktiv seit ${data.started_at ? new Date(data.started_at).toLocaleString('de-DE') : 'soeben'}`
                : 'Kein Lauf aktiv';
            logs.textContent = data.logs || 'Noch keine Läufe protokolliert.';
            logs.scrollTop = wasNearBottom ? logs.scrollHeight : previousScrollTop;
            nextRun = new Date(data.next_run_at);
            button.disabled = data.running;
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
            root.querySelector('[data-sync-countdown]').textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        };
        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                const response = await fetch(root.dataset.startUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, signal: controller.signal });
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
