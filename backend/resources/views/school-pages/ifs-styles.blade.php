<style>
    .ifs { --forest: #103c31; --gold: #f5ce79; --cream: #fbf8ef; color: #17372e; background: var(--cream); }
    .ifs .wrap { width: min(76rem, calc(100% - 2.5rem)); }
    .ifs a { text-decoration: none; }
    .ifs-skip { position: absolute; top: .5rem; left: .5rem; z-index: 1; padding: .5rem 1rem; background: #fff; }
    .ifs-skip:not(:focus) { clip-path: inset(50%); }
    .ifs-header { background: var(--cream); border: 0; }
    .ifs-header .masthead { align-items: center; gap: 1rem 2rem; }
    .ifs-header nav { gap: .65rem 1.45rem; }
    .ifs-header nav a { color: #23493d; font-size: .94rem; }
    .ifs-header nav a:hover, .ifs-header nav [aria-current] { text-decoration: underline; text-decoration-thickness: 2px; }
    .ifs-brand { display: inline-flex; align-items: center; gap: .85rem; color: var(--forest); font-weight: 750; }
    .ifs-monogram { display: grid; place-items: center; width: 3rem; height: 3rem; flex: none; background: var(--forest); color: #fff; border-radius: .4rem 1rem .4rem 1rem; letter-spacing: .08em; }
    .ifs .ifs-main { padding: 0; }
    .ifs-hero { background: var(--forest); color: #fff; padding: clamp(2.75rem, 7vw, 6.5rem) 0 clamp(3rem, 8vw, 7rem); }
    .ifs-hero-grid { display: grid; grid-template-columns: 1fr 1fr; gap: clamp(1.75rem, 6vw, 5rem); align-items: center; }
    .ifs-kicker { color: var(--gold); text-transform: uppercase; font-size: .82rem; font-weight: 720; letter-spacing: .16em; margin: 0 0 1rem; }
    .ifs h1, .ifs h2 { font-family: Georgia, serif; font-weight: 500; overflow-wrap: anywhere; }
    .ifs h1 { font-size: clamp(2.6rem, 6.5vw, 5.6rem); line-height: 1; letter-spacing: -.055em; }
    .ifs h2 { font-size: clamp(1.8rem, 4vw, 2.8rem); letter-spacing: -.035em; }
    .ifs-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .8rem 1.25rem; margin-top: 1.75rem; }
    .ifs-actions a { display: inline-flex; align-items: center; gap: 1rem; font-weight: 700; }
    .ifs .ifs-button { padding: .72rem 1.1rem; border-radius: 100px; background: var(--gold); color: #17372e; }
    .ifs-actions .ifs-secondary, .ifs-inner a { color: #fff; text-decoration: underline; }
    .ifs-hero-photo, .ifs-gallery figure { margin: 0; min-width: 0; }
    .ifs-hero-photo img, .ifs-gallery img { display: block; width: 100%; object-fit: cover; }
    .ifs-hero-photo img { aspect-ratio: 4/3; border-radius: 1.5rem 1.5rem 1.5rem .25rem; }
    .ifs-hero-photo figcaption { margin-top: .4rem; font-size: .82rem; }
    .ifs-hero-mark { aspect-ratio: 4/3; display: grid; place-items: center; border-radius: 1.5rem 1.5rem 1.5rem .25rem; border: 1px solid #bdaa6f; background: #184c3d; }
    .ifs-hero-mark span { display: grid; place-items: center; aspect-ratio: 1; width: 46%; border: 1px solid var(--gold); border-radius: 50%; color: var(--gold); font: clamp(2rem, 7vw, 5rem) Georgia, serif; }
    .ifs-quick-links { position: relative; margin-top: -1.55rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(13rem, 100%), 1fr)); gap: .65rem; }
    .ifs-quick-links .ifs-card { display: flex; justify-content: space-between; align-items: center; gap: .6rem; min-height: 3.6rem; padding: .25rem 1.15rem; background: #dbeee4; color: #17372e; border-radius: .75rem; font-weight: 720; }
    .ifs-quick-links .ifs-card:nth-child(2) { background: #f4dfae; }
    .ifs-quick-links .ifs-card:nth-child(3) { background: #dce7f3; }
    .ifs-quick-links .ifs-card:nth-child(4) { background: #efded5; }
    .ifs-card:hover { text-decoration: underline; }
    .ifs .ifs-copy { box-sizing: border-box; max-width: 52rem; padding-block: clamp(2rem, 6vw, 5rem); font-size: 1.075rem; }
    .ifs-copy a { text-decoration: underline; }
    .ifs-copy:empty { display: none; }
    .ifs-gallery { padding-bottom: clamp(2rem, 6vw, 5rem); }
    .ifs-gallery-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(16rem, 100%), 1fr)); gap: 1rem; }
    .ifs-gallery img { aspect-ratio: 3/2; border-radius: .8rem; }
    .ifs-gallery figcaption { padding-top: .5rem; }
    .ifs-admissions { box-sizing: border-box; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; padding: clamp(1.25rem, 4vw, 2.75rem); margin-bottom: 3rem; background: #e0ede4; border-radius: 1rem; }
    .ifs-admissions h2 { margin: 0; }
    .ifs-admissions a { font-weight: 700; }
    .ifs-inner { padding: clamp(2.5rem, 7vw, 5.5rem) 0; background: var(--forest); color: #fff; }
    .ifs-inner h1 { margin: .75rem 0 0; }
    .ifs-footer { background: #0e3027; color: #fff; padding-block: 2rem; border: 0; }
    .ifs-footer a { color: #fff; font-weight: 750; }
    .ifs-footer p { margin-bottom: 0; }

    @media (max-width: 46rem) {
        .ifs-hero-grid { grid-template-columns: 1fr; }
        .ifs-hero-photo img, .ifs-hero-mark { aspect-ratio: 3/2; }
        .ifs-header nav { width: 100%; }
    }
</style>
