const feedback = document.querySelector('#feedback');
const signIn = document.querySelector('#sign-in');
const editor = document.querySelector('#listing-editor');

async function request(path, method = 'GET', data) {
    const response = await fetch(path, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: data === undefined ? undefined : JSON.stringify(data),
    });

    if (response.status === 204) {
        return null;
    }

    const payload = await response.json();
    if (!response.ok) {
        const errors = Object.values(payload.errors ?? {}).flat();
        throw new Error(errors.join(' ') || payload.message || 'The request failed.');
    }

    return payload;
}

function report(message, error = false) {
    feedback.textContent = message;
    feedback.toggleAttribute('data-error', error);
}

async function run(button, task) {
    button.disabled = true;
    report('');
    try {
        await task();
    } catch (error) {
        report(error.message, true);
    } finally {
        if (button.isConnected) {
            button.disabled = false;
        }
    }
}

function formValues(form) {
    const values = new FormData(form);
    values.delete('_token');
    return Object.fromEntries(values);
}

signIn?.addEventListener('submit', event => {
    event.preventDefault();
    run(event.submitter, async () => {
        await request('/api/admin/login', 'POST', formValues(signIn));
        window.location.reload();
    });
});

if (editor) {
    const status = document.querySelector('#listing-status');
    const type = document.querySelector('#listing-type');
    const cityForm = document.querySelector('#create-city');
    const areaForm = document.querySelector('#create-area');
    const editorCity = editor.elements.namedItem('city_id');
    const editorArea = editor.elements.namedItem('area_id');
    const filterCity = document.querySelector('#filter-city');
    const filterArea = document.querySelector('#filter-area');
    const rows = document.querySelector('#listing-rows');
    const previous = document.querySelector('#previous-page');
    const next = document.querySelector('#next-page');
    const title = document.querySelector('#editor-title');
    const cancel = document.querySelector('#cancel-edit');
    const pageLabel = document.querySelector('#listing-page');
    let listingId = null;
    let page = 1;
    let lastPage = 1;
    let listRequest = 0;

    function setOptions(select, places, selected = select.value) {
        select.replaceChildren(select.options[0]);
        for (const place of places) {
            select.add(new Option(place.name, place.id));
        }
        select.value = String(selected ?? '');
        if (select.selectedIndex < 0) {
            select.selectedIndex = 0;
        }
    }

    async function loadCities() {
        const { data } = await request('/api/admin/cities');
        for (const select of [editorCity, filterCity, areaForm.elements.namedItem('city_id')]) {
            setOptions(select, data);
        }
    }

    async function loadAreas(city, area, selected) {
        const cityId = city.value;
        const places = cityId ? (await request(`/api/admin/cities/${cityId}/areas`)).data : [];
        if (city.value === cityId) {
            setOptions(area, places, selected);
        }
    }

    function resetEditor() {
        listingId = null;
        editor.reset();
        setOptions(editorArea, [], '');
        title.textContent = 'New listing';
        cancel.hidden = true;
    }

    async function editListing(listing) {
        listingId = listing.id;
        for (const field of editor.elements) {
            if (field.name && field !== editorArea) {
                field.value = listing[field.name] ?? '';
            }
        }
        setOptions(editorArea, [], '');
        await loadAreas(editorCity, editorArea, listing.area_id);
        title.textContent = 'Edit listing';
        cancel.hidden = false;
        editor.elements.namedItem('name').focus();
    }

    function action(label, handler, disabled = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.disabled = disabled;
        button.addEventListener('click', () => handler(button));
        return button;
    }

    function showRow(listing) {
        const row = rows.insertRow();
        row.insertCell().textContent = listing.name;
        row.insertCell().textContent = listing.listing_status.replaceAll('_', ' ');
        row.insertCell().textContent = listing.verified_at ? 'Verified' : 'Unverified';
        const actions = row.insertCell();

        const moderate = (button, operation, message) => run(button, async () => {
            await request(`/api/admin/listings/${listing.id}/${operation}`, 'POST');
            await loadListings(page);
            report(message);
        });

        actions.append(
            action('Edit', button => run(button, () => editListing(listing))),
            action('Verify', button => moderate(button, 'verify', 'Listing verified.'), Boolean(listing.verified_at)),
            action('Publish', button => moderate(button, 'publish', 'Listing published.'),
                !listing.verified_at || listing.status !== 'active' || listing.listing_status === 'published'),
            action('Suspend', button => moderate(button, 'suspend', 'Listing suspended.'), listing.listing_status === 'suspended'),
        );
    }

    async function loadListings(requestedPage = 1) {
        const serial = ++listRequest;
        const query = new URLSearchParams({ page: requestedPage });
        if (status.value) {
            query.set('listing_status', status.value);
        }

        if (type.value) {
            query.set('institute_type', type.value);
        }

        if (filterCity.value) {
            query.set('city_id', filterCity.value);
        }
        if (filterArea.value) {
            query.set('area_id', filterArea.value);
        }
        const payload = await request(`/api/admin/listings?${query}`);
        if (serial !== listRequest) {
            return;
        }

        page = payload.meta.current_page;
        lastPage = payload.meta.last_page;
        if (page > lastPage) {
            await loadListings(lastPage);
            return;
        }

        rows.replaceChildren();
        for (const listing of payload.data) {
            showRow(listing);
        }
        if (!payload.data.length) {
            const cell = rows.insertRow().insertCell();
            cell.colSpan = 4;
            cell.textContent = 'No listings match this filter.';
        }
        previous.disabled = page <= 1;
        next.disabled = page >= lastPage;
        pageLabel.textContent = `Page ${page} of ${lastPage}`;
    }

    function loadPage(requestedPage) {
        loadListings(requestedPage).catch(error => report(error.message, true));
    }

    editor.addEventListener('submit', event => {
        event.preventDefault();
        run(event.submitter, async () => {
            const updating = listingId !== null;
            const path = updating ? `/api/admin/listings/${listingId}` : '/api/admin/listings';
            await request(path, updating ? 'PATCH' : 'POST', formValues(editor));
            resetEditor();
            await loadListings(updating ? page : 1);
            report(updating ? 'Listing updated.' : 'Draft listing created.');
        });
    });

    cityForm.addEventListener('submit', event => {
        event.preventDefault();
        run(event.submitter, async () => {
            await request('/api/admin/cities', 'POST', formValues(cityForm));
            cityForm.reset();
            await loadCities();
            report('City added.');
        });
    });

    areaForm.addEventListener('submit', event => {
        event.preventDefault();
        run(event.submitter, async () => {
            const { city_id, ...details } = formValues(areaForm);
            await request(`/api/admin/cities/${city_id}/areas`, 'POST', details);
            areaForm.elements.namedItem('name').value = '';
            await Promise.all([loadAreas(editorCity, editorArea), loadAreas(filterCity, filterArea)]);
            report('Area added.');
        });
    });

    editorCity.addEventListener('change', () => {
        setOptions(editorArea, [], '');
        loadAreas(editorCity, editorArea).catch(error => report(error.message, true));
    });
    filterCity.addEventListener('change', () => {
        setOptions(filterArea, [], '');
        loadAreas(filterCity, filterArea).catch(error => report(error.message, true));
        loadPage(1);
    });
    filterArea.addEventListener('change', () => loadPage(1));
    cancel.addEventListener('click', resetEditor);
    status.addEventListener('change', () => loadPage(1));
    type.addEventListener('change', () => loadPage(1));
    previous.addEventListener('click', () => loadPage(Math.max(1, page - 1)));
    next.addEventListener('click', () => loadPage(Math.min(lastPage, page + 1)));
    document.querySelector('#sign-out').addEventListener('click', event => {
        run(event.currentTarget, async () => {
            await request('/api/logout', 'POST');
            window.location.reload();
        });
    });
    loadCities().catch(error => report(error.message, true));
    loadPage(1);
}
