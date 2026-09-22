// Canonical Table of Contents bump geometry. CV timelines call this same
// path builder so width, amplitude and Bezier proportions cannot drift.
export const TOC_BUMP = Object.freeze({
    halfHeight: 15,
    depth: 9,
    shoulderRatio: .72,
    strokeWidth: 1,
});

export const buildThreadPath = ({ rail = 2, height, marks }) => {
    let cursor = 0;
    const commands = [`M ${rail} 0`];

    for (const { y, depth = TOC_BUMP.depth } of [...marks].sort((a, b) => a.y - b.y)) {
        const shoulder = Math.min(9, Math.max(3, depth * TOC_BUMP.shoulderRatio));
        const start = Math.max(cursor, y - TOC_BUMP.halfHeight);
        const end = Math.min(height, y + TOC_BUMP.halfHeight);
        const tip = rail + depth;
        commands.push(`V ${start}`);
        commands.push(`C ${rail} ${y - shoulder}, ${tip} ${y - shoulder}, ${tip} ${y}`);
        commands.push(`C ${tip} ${y + shoulder}, ${rail} ${y + shoulder}, ${rail} ${end}`);
        cursor = end;
    }

    commands.push(`V ${height}`);

    return commands.join(' ');
};
