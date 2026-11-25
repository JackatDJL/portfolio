import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import LiquidGlass from 'liquid-glass-react';

export function mountNameGlass(element) {
    // Chromium renders this library's SVG displacement filter with colored edge artifacts.
    // Keep its native backdrop blur and tint, but skip that filter for Chromium engines.
    if (/(?:Chrome|Chromium|Edg|OPR)\//i.test(navigator.userAgent)) {
        element.classList.add('name-glass-native-backdrop');
    }

    const root = createRoot(element);
    return {
        render(visible, width, height) {
            root.render(visible ? createElement(LiquidGlass, {
                key: `${width}:${height}`,
                displacementScale: matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 28,
                blurAmount: .08,
                saturation: 110,
                aberrationIntensity: .5,
                elasticity: 0,
                globalMousePos: { x: 0, y: 0 },
                mouseOffset: { x: 0, y: 0 },
                cornerRadius: 999,
                padding: '8px 14px',
                style: { position: 'absolute', top: '50%', left: '50%' },
            }, createElement('span', { style: { display: 'block', width, height } })) : null);
        },
        destroy() { root.unmount(); },
    };
}
