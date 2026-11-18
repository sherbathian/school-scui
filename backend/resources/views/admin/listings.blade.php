<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Directory listings · Schoolhouse</title>
    <style>
        body { margin: 0; background: #f4f6fa; color: #172636; font: 16px system-ui, sans-serif; }
        header, main { max-width: 68rem; margin: auto; padding: 1.25rem; }
        header { display: flex; justify-content: space-between; align-items: center; }
        h1 { margin: 0; font-size: 1.6rem; }
        section, form { background: white; border: 1px solid #d7dee7; border-radius: .5rem; padding: 1.25rem; margin-bottom: 1rem; }
        section form { border: 0; padding: 0; margin: 0; }
        label { display: grid; gap: .35rem; }
        input, select, textarea, button { font: inherit; padding: .55rem; border: 1px solid #9daabb; border-radius: .3rem; }
        button { cursor: pointer; background: white; color: #172636; }
        button[type=submit] { background: #174b72; color: white; }
        button:disabled { cursor: default; opacity: .55; }
        .fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: .75rem; margin-bottom: 1rem; }
        .table-scroll { overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: .7rem; border-bottom: 1px solid #d7dee7; text-align: left; }
        #feedback { min-height: 1.4rem; }
        #feedback[data-error] { color: #9b2222; }
        nav { margin-top: 1rem; }
    </style>
    <script src="{{ asset('admin-listings.js') }}" defer></script>
</head>
<body>
    <header>
        <h1>Directory listings</h1>
        @auth
            <button id="sign-out" type="button">Sign out</button>
        @endauth
    </header>
    <main>
        <p id="feedback" role="status"></p>
        @guest
            <form id="sign-in" method="post" action="/api/admin/login">
                @csrf
                <h2>Sign in</h2>
                <div class="fields">
                    <label>Email <input name="email" type="email" autocomplete="username" required></label>
                    <label>Password <input name="password" type="password" autocomplete="current-password" required></label>
                </div>
                <button type="submit">Sign in</button>
            </form>
        @else
            <section>
                <h2 id="editor-title">New listing</h2>
                <form id="listing-editor" method="post" aria-labelledby="editor-title">
                    <div class="fields">
                        <label>Institute name <input name="name" maxlength="255" required></label>
                        <label>Institute type
                            <select name="institute_type">
                                <option value="school">School</option>
                                <option value="college">College</option>
                                <option value="university">University</option>
                                <option value="training_institute">Training institute</option>
                            </select>
                        </label>
                        <label>Short name <input name="short_name" maxlength="80"></label>
                        <label>Branch name <input name="branch_name" maxlength="100"></label>
                        <label>Tagline <input name="tagline" maxlength="160"></label>
                        <label>City <select name="city_id"><option value="">No city selected</option></select></label>
                        <label>Area <select name="area_id"><option value="">No area selected</option></select></label>
                        <label>Street address <input name="address_line" maxlength="255"></label>
                        <label>Public phone <input name="phone" type="tel" maxlength="30"></label>
                        <label>Public email <input name="email" type="email" maxlength="190"></label>
                        <label>Website <input name="website_url" type="url" maxlength="255"></label>
                        <label>Description <textarea name="description" maxlength="10000"></textarea></label>
                    </div>
                    <button type="submit">Save listing</button>
                    <button id="cancel-edit" type="button" hidden>Cancel edit</button>
                </form>
            </section>
            <section>
                <details>
                    <summary>Manage locations</summary>
                    <div class="fields">
                        <form id="create-city" method="post">
                            <h3>Add city</h3>
                            <label>Name <input name="name" maxlength="100" required></label>
                            <label>Province or territory <input name="province" maxlength="100"></label>
                            <button type="submit">Add city</button>
                        </form>
                        <form id="create-area" method="post">
                            <h3>Add area</h3>
                            <label>City <select name="city_id" required><option value="">Select a city</option></select></label>
                            <label>Name <input name="name" maxlength="120" required></label>
                            <label>Type
                                <select name="area_type">
                                    <option value="locality">Locality</option>
                                    <option value="sector">Sector</option>
                                    <option value="housing_scheme">Housing scheme</option>
                                </select>
                            </label>
                            <button type="submit">Add area</button>
                        </form>
                    </div>
                </details>
            </section>
            <section>
                <div class="fields">
                    <label>Listing status
                        <select id="listing-status">
                            <option value="">All listings</option>
                            <option value="draft">Draft</option>
                            <option value="pending_review">Pending review</option>
                            <option value="published">Published</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </label>
                    <label>Filter by institute type
                        <select id="listing-type">
                            <option value="">All types</option>
                            <option value="school">Schools</option>
                            <option value="college">Colleges</option>
                            <option value="university">Universities</option>
                            <option value="training_institute">Training institutes</option>
                        </select>
                    </label>
                    <label>Filter by city <select id="filter-city"><option value="">All cities</option></select></label>
                    <label>Filter by area <select id="filter-area"><option value="">All areas</option></select></label>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Name</th><th>Status</th><th>Verification</th><th>Actions</th></tr></thead>
                        <tbody id="listing-rows"></tbody>
                    </table>
                </div>
                <nav aria-label="Listing pages">
                    <button id="previous-page" type="button" disabled>Previous</button>
                    <span id="listing-page"></span>
                    <button id="next-page" type="button" disabled>Next</button>
                </nav>
            </section>
        @endguest
    </main>
</body>
</html>
