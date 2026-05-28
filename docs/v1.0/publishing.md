# Publishing The Documentation

This documentation site is published automatically with GitHub Pages.

The repository uses MkDocs Material to build the public site from `docs/v1.0`. Generated output is written to `site/` during local builds and workflow runs; that directory is ignored by Git.

## Local Preview

Install the docs dependencies:

```powershell
python -m pip install -r requirements-docs.txt
```

Serve the site locally:

```powershell
mkdocs serve
```

Build the static site:

```powershell
mkdocs build --strict
```

## GitHub Pages Setup

In the GitHub repository settings:

1. Open **Settings**.
2. Open **Pages**.
3. Set **Build and deployment** source to **GitHub Actions**.
4. Push to `master` or run the `Publish documentation` workflow manually.

The workflow builds the MkDocs site and deploys the generated `site/` directory through GitHub Pages.

## What Gets Published

The public site includes:

- the v1 landing page,
- tutorials,
- module reference pages,
- release status,
- publishing instructions.