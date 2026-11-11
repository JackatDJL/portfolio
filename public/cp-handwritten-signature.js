(() => {
    const selector = '[data-handwritten-signature]';
    const initializedRoots = new WeakMap();
    const initialize = root => {
        const canvas = root.querySelector('[data-handwritten-signature-canvas]');
        const status = root.querySelector('[data-handwritten-status]');
        const preview = root.querySelector('[data-handwritten-preview]');
        const previewStatus = root.querySelector('[data-handwritten-preview-status]');
        const saveButton = root.querySelector('[data-handwritten-save]');
        const removeBackground = root.querySelector('[data-handwritten-remove-background]');
        const clearButton = root.querySelector('[data-handwritten-clear]');
        const uploadInput = root.querySelector('[data-handwritten-upload]');
        const cropButton = root.querySelector('[data-handwritten-crop]');
        const deleteButton = root.querySelector('[data-handwritten-delete]');
        if ([canvas, status, preview, previewStatus, saveButton, removeBackground, clearButton, uploadInput, cropButton, deleteButton].some(element => !element)) {
            return false;
        }
        const controls = [canvas, status, preview, previewStatus, saveButton, removeBackground, clearButton, uploadInput, cropButton, deleteButton];
        const initializedControls = initializedRoots.get(root);
        if (initializedControls?.every((element, index) => element === controls[index])) return true;
        initializedRoots.set(root, controls);

        const context = canvas.getContext('2d', { willReadFrequently: true });
    let strokes = [];
    let activeStroke = null;
    let savedSource = false;
    let uploadedImage = false;
    let previewDataUrl = null;

    const markPreviewStale = message => {
        previewDataUrl = null;
        saveButton.disabled = true;
        previewStatus.textContent = message;
        previewStatus.dataset.state = 'stale';
    };

    const markPreviewCurrent = image => {
        previewDataUrl = image;
        saveButton.disabled = false;
        previewStatus.textContent = 'Vorschau entspricht den aktuellen Einstellungen.';
        previewStatus.dataset.state = 'current';
    };

    root.querySelectorAll('button').forEach(button => button.classList.add('app-cp-button'));

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const endpoint = () => location.pathname.split('/globals/')[0] + '/applications/handwritten-signature';

    const clearCanvas = () => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        strokes = [];
        uploadedImage = false;
        preview.replaceChildren();
        markPreviewStale('Vorschau veraltet. Erstelle nach einer Änderung eine neue Vorschau.');
    };

    const coordinates = event => {
        const bounds = canvas.getBoundingClientRect();
        return {
            x: Math.max(0, Math.min(1, (event.clientX - bounds.left) / bounds.width)),
            y: Math.max(0, Math.min(1, (event.clientY - bounds.top) / bounds.height)),
        };
    };

    canvas.addEventListener('pointerdown', event => {
        event.preventDefault();
        markPreviewStale('Zeichnung geändert. Erstelle die Vorschau erneut, bevor du speicherst.');
        canvas.setPointerCapture(event.pointerId);
        uploadedImage = false;
        activeStroke = [coordinates(event)];
        strokes.push(activeStroke);
        const point = coordinates(event);
        context.beginPath();
        context.moveTo(point.x * canvas.width, point.y * canvas.height);
        context.lineWidth = 5;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.strokeStyle = '#17231f';
        context.lineTo(point.x * canvas.width + 0.01, point.y * canvas.height + 0.01);
        context.stroke();
    });
    canvas.addEventListener('pointermove', event => {
        if (!activeStroke) return;
        event.preventDefault();
        const point = coordinates(event);
        activeStroke.push(point);
        context.lineTo(point.x * canvas.width, point.y * canvas.height);
        context.stroke();
    });
    const endStroke = () => { activeStroke = null; };
    canvas.addEventListener('pointerup', endStroke);
    canvas.addEventListener('pointercancel', endStroke);

    const processCanvas = trim => {
        const image = context.getImageData(0, 0, canvas.width, canvas.height);
        const pixels = image.data;
        let left = canvas.width, top = canvas.height, right = -1, bottom = -1;
        for (let y = 0; y < canvas.height; y++) {
            for (let x = 0; x < canvas.width; x++) {
                const index = (y * canvas.width + x) * 4;
                const lightBackground = pixels[index + 3] > 0 && pixels[index] > 248 && pixels[index + 1] > 248 && pixels[index + 2] > 248;
                if (removeBackground.checked && pixels[index + 3] > 0 && pixels[index] > 244 && pixels[index + 1] > 244 && pixels[index + 2] > 244) {
                    pixels[index + 3] = 0;
                }
                if (pixels[index + 3] > 8 && !lightBackground) {
                    left = Math.min(left, x); right = Math.max(right, x);
                    top = Math.min(top, y); bottom = Math.max(bottom, y);
                }
            }
        }
        if (right < left || bottom < top) throw new Error('Bitte zuerst eine Unterschrift zeichnen oder hochladen.');
        const pad = trim ? 16 : 0;
        left = Math.max(0, left - pad); top = Math.max(0, top - pad);
        right = Math.min(canvas.width - 1, right + pad); bottom = Math.min(canvas.height - 1, bottom + pad);
        const output = document.createElement('canvas');
        output.width = right - left + 1; output.height = bottom - top + 1;
        const croppedImage = context.createImageData(output.width, output.height);
        for (let y = 0; y < output.height; y++) {
            const from = ((top + y) * canvas.width + left) * 4;
            croppedImage.data.set(pixels.subarray(from, from + output.width * 4), y * output.width * 4);
        }
        output.getContext('2d').putImageData(croppedImage, 0, 0);
        return output.toDataURL('image/png');
    };

    const showPreview = dataUrl => {
        const image = document.createElement('img');
        image.src = dataUrl;
        image.alt = 'Vorschau der privaten Handschrift';
        let heading = preview.querySelector('h4');
        if (!heading) {
            heading = document.createElement('h4');
            heading.textContent = 'Vorschau';
        }
        preview.replaceChildren(heading, image);
    };

    clearButton.addEventListener('click', () => {
        clearCanvas();
        status.textContent = 'Aktuelle Zeichnung geleert. Eine gespeicherte Vorlage bleibt bis zum Speichern unverändert.';
    });

    uploadInput.addEventListener('change', event => {
        const file = event.target.files?.[0];
        if (!file) return;
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
            status.textContent = 'Bitte PNG, JPEG oder WebP bis höchstens 10 MB auswählen.';
            event.target.value = '';
            return;
        }
        const image = new Image();
        image.onload = () => {
            clearCanvas();
            const scale = Math.min((canvas.width - 32) / image.width, (canvas.height - 32) / image.height);
            const width = image.width * scale, height = image.height * scale;
            context.drawImage(image, (canvas.width - width) / 2, (canvas.height - height) / 2, width, height);
            uploadedImage = true;
            URL.revokeObjectURL(image.src);
            markPreviewStale('Bild geändert. Erstelle die Vorschau erneut, bevor du speicherst.');
            status.textContent = 'Bild geladen. Beim Zuschneiden werden leere Ränder entfernt.';
        };
        image.onerror = () => { status.textContent = 'Das Bild konnte nicht geöffnet werden.'; };
        image.src = URL.createObjectURL(file);
    });

    removeBackground.addEventListener('change', () => {
        markPreviewStale('Hintergrundoption geändert. Erstelle die Vorschau erneut, bevor du speicherst.');
    });

    cropButton.addEventListener('click', () => {
        try {
            previewDataUrl = processCanvas(true);
            showPreview(previewDataUrl);
            markPreviewCurrent(previewDataUrl);
            status.textContent = 'Vorschau zugeschnitten. Speichern übernimmt die Änderung.';
        } catch (error) {
            markPreviewStale('Keine aktuelle Vorschau. Zeichne oder lade eine Signatur und erstelle die Vorschau erneut.');
            status.textContent = error.message;
        }
    });

    saveButton.addEventListener('click', async event => {
        const button = event.currentTarget;
        if (!previewDataUrl) {
            status.textContent = 'Erstelle zuerst eine aktuelle Vorschau.';
            return;
        }
        if (savedSource && !window.confirm('Die gespeicherte private Signaturvorlage ersetzen?')) return;
        const imageToSave = previewDataUrl;
        button.disabled = true;
        try {
            const response = await fetch(endpoint(), {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ image: imageToSave, strokes: uploadedImage ? [] : strokes }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Speichern fehlgeschlagen.');
            savedSource = true;
            if (previewDataUrl === imageToSave) {
                showPreview(imageToSave);
                markPreviewCurrent(imageToSave);
                status.textContent = 'Private Signaturvorlage verschlüsselt gespeichert.';
            } else {
                status.textContent = 'Die Vorschau wurde gespeichert. Eine zwischenzeitliche Änderung ist noch nicht gespeichert; erstelle dafür eine neue Vorschau.';
            }
        } catch (error) { status.textContent = error.message; }
        finally { button.disabled = !previewDataUrl; }
    });

    deleteButton.addEventListener('click', async event => {
        if (!savedSource || !window.confirm('Private Signaturvorlage endgültig löschen? Bewerbungen mit aktiver Handschrift verhindern das Löschen.')) return;
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const response = await fetch(endpoint(), { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Löschen fehlgeschlagen.');
            savedSource = false;
            clearCanvas();
            status.textContent = 'Private Signaturvorlage gelöscht.';
        } catch (error) { status.textContent = error.message; }
        finally { button.disabled = false; }
    });

    fetch(endpoint(), { headers: { Accept: 'application/json' } }).then(async response => {
        if (!response.ok) throw new Error('Private Signaturvorlage konnte nicht geladen werden.');
        const data = await response.json();
        savedSource = Boolean(data.available);
        if (data.source?.image) {
            const image = new Image();
            image.onload = () => {
                context.clearRect(0, 0, canvas.width, canvas.height);
                const scale = Math.min((canvas.width - 32) / image.width, (canvas.height - 32) / image.height);
                const width = image.width * scale, height = image.height * scale;
                context.drawImage(image, (canvas.width - width) / 2, (canvas.height - height) / 2, width, height);
                URL.revokeObjectURL(image.src);
                showPreview(data.source.image);
                strokes = Array.isArray(data.source.strokes) ? data.source.strokes : [];
                markPreviewCurrent(data.source.image);
                status.textContent = 'Gespeicherte private Signatur geladen.';
            };
            image.src = data.source.image;
        } else {
            status.textContent = 'Noch keine private Signaturvorlage gespeichert.';
        }
    }).catch(error => { status.textContent = error.message; });
        return true;
    };

    const initializeTree = node => {
        const roots = new Set();
        if (node === document) {
            document.querySelectorAll(selector).forEach(root => roots.add(root));
        } else if (node instanceof Element) {
            if (node.matches(selector)) roots.add(node);
            const parent = node.closest(selector);
            if (parent) roots.add(parent);
            node.querySelectorAll(selector).forEach(root => roots.add(root));
        }
        roots.forEach(initialize);
    };

    initializeTree(document);
    const observer = new MutationObserver(records => {
        records.forEach(record => record.addedNodes.forEach(initializeTree));
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
