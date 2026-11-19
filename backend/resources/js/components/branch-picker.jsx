import { useEffect, useId, useRef, useState } from 'react';
import { NavigationIcon } from './admin-navigation.jsx';

export function BranchPicker({ branch, branches, onSelect }) {
    const id = useId();
    const listId = `${id}-options`;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(-1);
    const input = useRef(null);
    const list = useRef(null);
    const terms = query.trim().toLowerCase().split(/\s+/);
    const matches = branches.filter(item => terms.every(term => item.name.toLowerCase().includes(term)));

    useEffect(() => {
        if (active >= 0) list.current?.children[active]?.scrollIntoView({ block: 'nearest' });
    }, [active]);

    function close() {
        setOpen(false);
        setQuery('');
        setActive(-1);
    }

    function select(item) {
        close();
        if (item?.id !== branch?.id) onSelect(item?.id);
    }

    function keydown(event) {
        if (event.key === 'Escape' && open) {
            event.preventDefault();
            close();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setOpen(true);
            if (matches.length) {
                const direction = event.key === 'ArrowDown' ? 1 : -1;
                setActive(current => current < 0 ? (direction === 1 ? 0 : matches.length - 1)
                    : Math.min(matches.length - 1, Math.max(0, current + direction)));
            }
        } else if (event.key === 'Enter' && open && matches.length) {
            event.preventDefault();
            select(matches[active] ?? matches[0]);
        }
    }

    return <div className="branch-picker mb-4 max-w-sm">
        <label htmlFor={id}>Branch</label>
        <div className="branch-picker-field">
            <NavigationIcon name="search" />
            <input ref={input} id={id} type="text" role="combobox" aria-autocomplete="list"
                aria-expanded={open} aria-controls={listId}
                aria-activedescendant={open && matches[active] ? `${listId}-${matches[active].id}` : undefined}
                autoComplete="off" placeholder={open && branch ? branch.name : 'Search branches…'}
                value={open ? query : branch?.name ?? ''}
                onFocus={() => setOpen(true)} onClick={() => setOpen(true)} onBlur={close} onKeyDown={keydown}
                onChange={event => { setQuery(event.target.value); setOpen(true); setActive(-1); }} />
            <button type="button" tabIndex={-1} aria-label={open ? 'Close branch options' : 'Show branch options'}
                onMouseDown={event => event.preventDefault()}
                onClick={() => { if (open) close(); else { input.current?.focus(); setOpen(true); } }}>
                <NavigationIcon name="chevron" />
            </button>
        </div>
        <div className="branch-picker-popover" hidden={!open}>
            <ul id={listId} ref={list} role="listbox" aria-label="Branches">
                {open && matches.map((item, index) => <li key={item.id} id={`${listId}-${item.id}`} role="option"
                    aria-selected={item.id === branch?.id} data-active={index === active || undefined}
                    onMouseDown={event => event.preventDefault()} onClick={() => select(item)}>
                    {item.name}
                    {item.id === branch?.id && <NavigationIcon name="check" />}
                </li>)}
            </ul>
            {open && matches.length === 0 && <p role="status">No matching branches.</p>}
        </div>
        {branch && <button className="branch-picker-clear" type="button" onClick={() => select(null)}>Clear branch</button>}
    </div>;
}
