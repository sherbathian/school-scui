export function isSchoolPageLink(value) {
    try {
        return /^https?:\/\//i.test(value) && Boolean(new URL(value).hostname);
    } catch {
        return false;
    }
}

function textContent(text) {
    return (typeof text === 'string' ? text : '').split('\n').flatMap((line, index) => [
        ...(index > 0 ? [{ type: 'hardBreak' }] : []),
        ...(line ? [{ type: 'text', text: line }] : []),
    ]);
}

function legacyNode(block) {
    switch (block?.type) {
        case 'heading':
            return { type: 'heading', attrs: { level: 2 }, content: textContent(block.text) };
        case 'paragraph':
            return { type: 'paragraph', content: textContent(block.text) };
        case 'list': {
            const items = Array.isArray(block.items) ? block.items.filter(item => typeof item === 'string') : [];
            return items.length ? { type: 'bulletList', content: items.map(text => ({
                type: 'listItem', content: [{ type: 'paragraph', content: textContent(text) }],
            })) } : null;
        }
        case 'link':
            return isSchoolPageLink(block.url) && typeof block.text === 'string' && block.text
                ? { type: 'paragraph', content: [{ type: 'text', text: block.text, marks: [{ type: 'link', attrs: { href: block.url } }] }] }
                : null;
        default:
            return null;
    }
}

export function readSchoolPage(content) {
    try {
        const value = content ? JSON.parse(content) : [];
        if (!value || typeof value !== 'object') return null;
        const envelope = Array.isArray(value) ? { blocks: value } : value;
        const legacy = Array.isArray(envelope.blocks) ? envelope.blocks : [];
        const blocks = legacy.map(legacyNode);
        const converted = blocks.filter(Boolean);
        return {
            envelope,
            legacyCount: blocks.filter(block => !block).length,
            document: envelope.document ?? { type: 'doc', content: converted.length ? converted : [{ type: 'paragraph' }] },
        };
    } catch {
        return null;
    }
}
