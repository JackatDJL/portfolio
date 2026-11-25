(() => {
    const instances = new WeakMap();

    const formatBytes = value => {
        const bytes = Number(value) || 0;
        if (bytes < 1024) return `${bytes} Byte`;
        const units = ['KiB', 'MiB', 'GiB'];
        let amount = bytes;
        for (const unit of units) {
            amount /= 1024;
            if (amount < 1024 || unit === 'GiB') return `${amount.toLocaleString('de-DE', { maximumFractionDigits: 1 })} ${unit}`;
        }
    };

    const bind = root => {
        if (instances.has(root) || !root.isConnected) return;
        const controller = new AbortController();
        const status = root.querySelector('[data-pdf-running]');
        const caches = root.querySelector('[data-pdf-caches]');
        const logs = root.querySelector('[data-pdf-logs]');
        const message = root.querySelector('[data-pdf-message]');
        const recompile = root.querySelector('[data-pdf-recompile]');
        const clear = root.querySelector('[data-pdf-clear]');
        let refreshTimer;
        let pending = false;
        let refreshing = false;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        const showMessage = value => {
            message.textContent = value || '';
            message.hidden = !value;
        };

        const refresh = async () => {
            if (refreshing) return;
            refreshing = true;
            try {
                const response = await fetch(root.dataset.statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal });
                if (!response.ok) throw new Error('PDF-Status konnte nicht geladen werden.');
                const data = await response.json();
                status.textContent = data.running ? 'Eine PDF-Kompilierung oder Cache-Operation läuft.' : 'Keine Kompilierung aktiv.';
                caches.replaceChildren();
                for (const [key, label] of [['public_cv', 'Öffentliche Lebensläufe'], ['applications', 'Bewerbungs-PDFs']]) {
                    const item = document.createElement('div');
                    const term = document.createElement('dt');
                    term.textContent = label;
                    const detail = document.createElement('dd');
                    const value = data.caches?.[key] || { files: 0, bytes: 0 };
                    detail.textContent = `${Number(value.files) || 0} PDFs · ${formatBytes(value.bytes)}`;
                    item.append(term, detail);
                    caches.append(item);
                }
                logs.textContent = data.logs || 'Noch keine PDF-Compiler-Logs vorhanden.';
            } catch (error) {
                if (error.name === 'AbortError') return;
                status.textContent = error.message;
            } finally {
                refreshing = false;
            }
        };

        const runAction = async (url, confirmText) => {
            if (pending || (confirmText && !window.confirm(confirmText))) return;
            pending = true;
            recompile.disabled = true;
            clear.disabled = true;
            showMessage('Aktion läuft …');
            try {
                const response = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, signal: controller.signal });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.message || 'PDF-Aktion fehlgeschlagen.');
                showMessage(data.message || 'Aktion abgeschlossen.');
                await refresh();
            } catch (error) {
                if (error.name === 'AbortError') return;
                showMessage(error.message);
                await refresh();
            } finally {
                pending = false;
                recompile.disabled = false;
                clear.disabled = false;
            }
        };

        recompile.addEventListener('click', () => runAction(
            root.dataset.recompileUrl,
            'Den öffentlichen Lebenslauf jetzt mit den gespeicherten Daten neu kompilieren?',
        ), { signal: controller.signal });
        clear.addEventListener('click', () => runAction(
            root.dataset.clearUrl,
            'Alle zwischengespeicherten öffentlichen Lebensläufe und Bewerbungs-PDFs entfernen?',
        ), { signal: controller.signal });
        root.querySelector('[data-pdf-refresh]').addEventListener('click', refresh, { signal: controller.signal });
        refresh();
        refreshTimer = window.setInterval(() => { if (root.isConnected) refresh(); }, 5000);
        instances.set(root, {
            dispose() {
                window.clearInterval(refreshTimer);
                controller.abort();
                instances.delete(root);
            },
        });
    };

    const scan = node => {
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        if (node.matches('[data-pdf-compiler]')) bind(node);
        node.querySelectorAll('[data-pdf-compiler]').forEach(bind);
    };
    const dispose = node => {
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        if (node.matches('[data-pdf-compiler]')) instances.get(node)?.dispose();
        node.querySelectorAll('[data-pdf-compiler]').forEach(root => instances.get(root)?.dispose());
    };
    new MutationObserver(records => records.forEach(record => {
        record.addedNodes.forEach(scan);
        record.removedNodes.forEach(dispose);
    })).observe(document.documentElement, { childList: true, subtree: true });
    scan(document.documentElement);
})();
