import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist/build/pdf.mjs';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.mjs?url';

GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

const initPdfPreview = async (viewer) => {
    const url = viewer.dataset.pdfUrl;
    const loading = viewer.querySelector('[data-pdf-loading]');
    const pagesElement = viewer.querySelector('[data-pdf-pages]');
    const error = viewer.querySelector('[data-pdf-error]');
    if (!url || !(pagesElement instanceof HTMLElement)) return;

    const setError = () => {
        loading?.setAttribute('hidden', '');
        pagesElement.hidden = true;
        error?.removeAttribute('hidden');
    };

    try {
        const loadingTask = getDocument({ url });
        loadingTask.onProgress = ({ loaded, total }) => {
            if (loading instanceof HTMLElement && total) loading.textContent = `Dokument wird geladen … ${Math.round((loaded / total) * 100)} %`;
        };
        const documentProxy = await loadingTask.promise;
        const page = await documentProxy.getPage(1);
        const baseViewport = page.getViewport({ scale: 1 });
        pagesElement.style.aspectRatio = `${baseViewport.width} / ${baseViewport.height}`;
        const pageElement = document.createElement('figure');
        const canvas = document.createElement('canvas');
        pageElement.className = 'pdf-viewer__page';
        pageElement.dataset.pdfPage = '1';
        pageElement.setAttribute('aria-label', `Erste Seite von ${documentProxy.numPages}`);
        pageElement.append(canvas);
        pagesElement.append(pageElement);

        let renderTask;
        let resizeFrame;
        const render = async () => {
            renderTask?.cancel();
            const widthScale = pagesElement.clientWidth / baseViewport.width;
            const heightScale = pagesElement.clientHeight / baseViewport.height;
            const viewport = page.getViewport({ scale: Math.max(0.1, Math.min(widthScale, heightScale)) });
            const outputScale = Math.min(window.devicePixelRatio || 1, 2);
            canvas.width = Math.floor(viewport.width * outputScale);
            canvas.height = Math.floor(viewport.height * outputScale);
            canvas.style.width = `${viewport.width}px`;
            canvas.style.height = `${viewport.height}px`;
            pageElement.style.width = `${viewport.width}px`;
            pageElement.style.height = `${viewport.height}px`;
            const context = canvas.getContext('2d', { alpha: false });
            if (!context) throw new Error('Canvas context unavailable');
            renderTask = page.render({ canvasContext: context, viewport, transform: [outputScale, 0, 0, outputScale, 0, 0] });
            try {
                await renderTask.promise;
                pageElement.dataset.pdfRendered = 'true';
            } catch (renderError) {
                if (renderError?.name !== 'RenderingCancelledException') setError();
            }
        };

        await render();
        loading?.setAttribute('hidden', '');
        new ResizeObserver(() => {
            window.cancelAnimationFrame(resizeFrame);
            resizeFrame = window.requestAnimationFrame(render);
        }).observe(pagesElement);
    } catch {
        setError();
    }
};

document.querySelectorAll('[data-pdf-viewer]').forEach((viewer) => {
    if (viewer instanceof HTMLElement) initPdfPreview(viewer);
});
