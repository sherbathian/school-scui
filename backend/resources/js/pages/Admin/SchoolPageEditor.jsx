import { useState } from 'react';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { Button } from '../../components/ui/button.jsx';
import { api } from '../../admin-api.js';
import { isSchoolPageLink, readSchoolPage } from './school-page-content.js';

const extensions = [StarterKit.configure({
    heading: { levels: [2, 3] },
    link: { openOnClick: false, isAllowedUri: isSchoolPageLink },
    blockquote: false,
    code: false,
    codeBlock: false,
    horizontalRule: false,
    strike: false,
    underline: false,
})];

function EditorToolbar({ editor }) {
    const [editingLink, setEditingLink] = useState(false);
    const [url, setUrl] = useState('');
    const [linkError, setLinkError] = useState(false);
    const active = useEditorState({
        editor,
        selector: ({ editor }) => ({
            heading: [2, 3].find(level => editor.isActive('heading', { level })) ?? 0,
            bold: editor.isActive('bold'),
            italic: editor.isActive('italic'),
            bulletList: editor.isActive('bulletList'),
            orderedList: editor.isActive('orderedList'),
            link: editor.isActive('link'),
            hasSelection: !editor.state.selection.empty,
            canUndo: editor.can().undo(),
            canRedo: editor.can().redo(),
        }),
    });

    function applyLink() {
        const href = url.trim();
        if (!isSchoolPageLink(href)) {
            setLinkError(true);
            return;
        }
        editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
        setEditingLink(false);
    }

    return <>
        <div className="flex flex-wrap items-center gap-2 border-b border-[var(--line)] p-2" role="group" aria-label="Text formatting">
            <select aria-label="Text style" value={active.heading} onChange={event => {
                const level = Number(event.target.value);
                const chain = editor.chain().focus();
                (level ? chain.setHeading({ level }) : chain.setParagraph()).run();
            }}>
                <option value={0}>Paragraph</option>
                <option value={2}>Heading 2</option>
                <option value={3}>Heading 3</option>
            </select>
            <Button type="button" size="sm" variant="outline" aria-pressed={active.bold}
                onClick={() => editor.chain().focus().toggleBold().run()}>Bold</Button>
            <Button type="button" size="sm" variant="outline" aria-pressed={active.italic}
                onClick={() => editor.chain().focus().toggleItalic().run()}>Italic</Button>
            <Button type="button" size="sm" variant="outline" aria-pressed={active.bulletList}
                onClick={() => editor.chain().focus().toggleBulletList().run()}>Bullet list</Button>
            <Button type="button" size="sm" variant="outline" aria-pressed={active.orderedList}
                onClick={() => editor.chain().focus().toggleOrderedList().run()}>Numbered list</Button>
            <Button type="button" size="sm" variant="outline" aria-expanded={editingLink}
                disabled={!active.link && !active.hasSelection}
                onClick={() => { setUrl(editor.getAttributes('link').href ?? ''); setLinkError(false); setEditingLink(!editingLink); }}>Link</Button>
            <Button type="button" size="sm" variant="outline" disabled={!active.canUndo}
                onClick={() => editor.chain().focus().undo().run()}>Undo</Button>
            <Button type="button" size="sm" variant="outline" disabled={!active.canRedo}
                onClick={() => editor.chain().focus().redo().run()}>Redo</Button>
        </div>
        {editingLink && <div className="grid gap-2 border-b border-[var(--line)] p-2">
            <label>Link address
                <input inputMode="url" value={url} placeholder="https://example.com"
                    onChange={event => setUrl(event.target.value)}
                    onKeyDown={event => { if (event.key === 'Enter') { event.preventDefault(); applyLink(); } }} />
            </label>
            {linkError && <p role="alert">Enter an address beginning with https:// or http://.</p>}
            <div className="flex flex-wrap gap-2">
                <Button type="button" size="sm" onClick={applyLink}>Apply link</Button>
                {active.link && <Button type="button" size="sm" variant="outline"
                    onClick={() => { editor.chain().focus().extendMarkRange('link').unsetLink().run(); setEditingLink(false); }}>
                    Remove link
                </Button>}
                <Button type="button" size="sm" variant="outline" onClick={() => setEditingLink(false)}>Cancel</Button>
            </div>
        </div>}
    </>;
}

export default function SchoolPageEditor({ name, content, disabled }) {
    const [original] = useState(() => readSchoolPage(content));
    const [serialized, setSerialized] = useState(content);
    const [preview, setPreview] = useState(null);
    const [previewError, setPreviewError] = useState('');
    const [previewLoading, setPreviewLoading] = useState(false);
    const [unsupported, setUnsupported] = useState(!original);
    const editor = useEditor({
        extensions,
        content: original?.document ?? '',
        immediatelyRender: false,
        enableContentCheck: true,
        onContentError: () => setUnsupported(true),
        editorProps: { attributes: { class: 'school-page-writing', role: 'textbox', 'aria-label': 'Page content', 'aria-multiline': 'true' } },
        onUpdate: ({ editor }) => {
            if (original) setSerialized(JSON.stringify({ ...original.envelope, document: editor.getJSON() }));
        },
    });

    async function showPreview(event) {
        const form = new FormData(event.currentTarget.form);
        setPreview(null);
        setPreviewError('');
        if (!form.get('school_id')) {
            setPreviewError('Choose a branch to preview the page.');
            return;
        }

        setPreviewLoading(true);
        try {
            const result = await api('admin/services/school-pages/preview', 'POST', {
                school_id: form.get('school_id'), title: form.get('title') || null, content: serialized || null,
            });
            setPreview(result.data.html);
        } catch (error) {
            setPreviewError(error.message);
        } finally {
            setPreviewLoading(false);
        }
    }

    return <fieldset className="col-span-full min-w-0" disabled={disabled}>
        <legend>Page content</legend>
        <input type="hidden" name={name} value={serialized ?? ''} />
        {unsupported ? <p className="mt-2 text-sm">This page uses an unsupported format. Its saved content will be preserved.</p> :
            <div className="mt-2 overflow-hidden rounded-lg border border-[var(--line)]">
                {editor && <EditorToolbar editor={editor} />}
                <EditorContent editor={editor} />
            </div>}
        {!unsupported && original.legacyCount > 0 &&
            <p className="mt-2 text-sm">Unsupported blocks are retained in the saved page data.</p>}
        <div className="mt-3 flex flex-wrap gap-2">
            <Button type="button" variant="outline" disabled={previewLoading} onClick={showPreview}>
                {previewLoading ? 'Loading preview…' : 'Preview page'}
            </Button>
            {preview && <Button type="button" variant="outline" onClick={() => setPreview(null)}>Close preview</Button>}
        </div>
        {previewError && <p role="alert" className="mt-2 text-sm">{previewError}</p>}
        {preview && <iframe title="School page preview" sandbox="" srcDoc={preview}
            className="mt-3 min-h-96 w-full rounded-lg border border-[var(--line)]" />}
    </fieldset>;
}
