import { useRef, useState } from 'react';
import { Button } from '../../components/ui/button.jsx';

const blockLabels = { heading: 'Heading', paragraph: 'Paragraph', list: 'Bullet list' };

function readContent(content) {
    try {
        const value = content ? JSON.parse(content) : [];
        const blocks = Array.isArray(value) ? value : value?.blocks;

        return Array.isArray(blocks)
            ? { blocks: blocks.map((block, id) => ({ block, id })), wrapper: Array.isArray(value) ? null : value }
            : null;
    } catch {
        return null;
    }
}

function blockLabel(block) {
    if ((block?.type === 'heading' || block?.type === 'paragraph') && typeof block.text === 'string') {
        return blockLabels[block.type];
    }
    if (block?.type === 'list' && Array.isArray(block.items) && block.items.every(item => typeof item === 'string')) {
        return blockLabels.list;
    }
    return null;
}

export default function SchoolPageEditor({ name, content, disabled }) {
    const [initial] = useState(() => readContent(content));
    const [updated, setUpdated] = useState(null);
    const blocks = updated ?? initial?.blocks ?? [];
    const nextId = useRef(blocks.length);
    const values = updated?.map(({ block }) => block);
    const serialized = updated === null
        ? content
        : JSON.stringify(initial.wrapper ? { ...initial.wrapper, blocks: values } : values);

    function changeBlocks(change) {
        setUpdated(previous => change(previous ?? initial.blocks));
    }

    function addBlock(type) {
        const item = { id: nextId.current++, block: type === 'list' ? { type, items: [''] } : { type, text: '' } };
        changeBlocks(current => [...current, item]);
    }

    function updateBlock(id, changes) {
        changeBlocks(current => current.map(item => item.id === id
            ? { ...item, block: { ...item.block, ...changes } }
            : item));
    }

    function moveBlock(position, offset) {
        changeBlocks(current => {
            const next = [...current];
            const [item] = next.splice(position, 1);
            next.splice(position + offset, 0, item);
            return next;
        });
    }

    return <fieldset className="col-span-full min-w-0" disabled={disabled}>
        <legend>Page content</legend>
        <input type="hidden" name={name} value={serialized ?? ''} />
        {!initial ? <p className="mt-2 text-sm">This page uses an unsupported format. Its saved content will be preserved.</p> : <>
            <ol className="mt-2 grid list-none gap-3 p-0">
                {blocks.map(({ block, id }, position) => {
                    const label = blockLabel(block);
                    const description = `${label ?? 'block'} ${position + 1}`;
                    return <li key={id} className="grid gap-3 rounded-lg border border-[var(--line)] p-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <strong className="text-sm">{position + 1}. {label ?? 'Unsupported block'}</strong>
                            <div className="flex flex-wrap gap-2">
                                <Button type="button" variant="outline" size="sm" aria-label={`Move ${description} up`}
                                    disabled={position === 0} onClick={() => moveBlock(position, -1)}>Move up</Button>
                                <Button type="button" variant="outline" size="sm" aria-label={`Move ${description} down`}
                                    disabled={position === blocks.length - 1} onClick={() => moveBlock(position, 1)}>Move down</Button>
                                <Button type="button" variant="outline" size="sm" aria-label={`Remove ${description}`}
                                    onClick={() => changeBlocks(current => current.filter(item => item.id !== id))}>Remove</Button>
                            </div>
                        </div>
                        {!label ? <p className="text-sm">This block will be preserved unless you remove it.</p>
                            : block.type === 'heading' ? <label>Heading text
                                <input value={block.text} onChange={event => updateBlock(id, { text: event.target.value })} />
                            </label> : block.type === 'paragraph' ? <label>Paragraph text
                                <textarea rows={3} value={block.text} onChange={event => updateBlock(id, { text: event.target.value })} />
                            </label> : <label>List items, one per line
                                <textarea rows={3} value={block.items.join('\n')}
                                    onChange={event => updateBlock(id, { items: event.target.value.split('\n') })} />
                            </label>}
                    </li>;
                })}
            </ol>
            <div className="mt-3 flex flex-wrap gap-2">
                {Object.entries(blockLabels).map(([type, label]) =>
                    <Button key={type} type="button" variant="outline" onClick={() => addBlock(type)}>
                        Add {label.toLowerCase()}
                    </Button>)}
            </div>
        </>}
    </fieldset>;
}
