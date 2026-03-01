# Docsify GitHub Pages — Scribe AI Docs

This folder serves the documentation site via [GitHub Pages](https://pages.github.com/) using [Docsify](https://docsify.js.org/).

## How it Works

The markdown files in `resources/docs/1.0/` are the **source of truth**. A GitHub Actions workflow (`.github/workflows/docs.yml`) copies them into `docs/` and deploys to GitHub Pages on every push to `main`.

## Local Preview

```bash
# Install docsify-cli globally
npm i docsify-cli -g

# Serve the docs folder
docsify serve docs
```

Then visit `http://localhost:3000`.

## Manual Sync (if needed)

```powershell
Copy-Item -Path resources/docs/1.0/*.md -Destination docs/ -Force
```
