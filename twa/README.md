# TWA Packaging Guide

This project includes a PWA and TWA-ready setup.

## 1) Deploy the Laravel app with HTTPS
- Use a production domain, e.g. `https://your-production-domain.com`.
- Ensure `/manifest.webmanifest`, `/sw.js`, `/icons/*` are publicly reachable.

## 2) Update Digital Asset Links
- Edit `public/.well-known/assetlinks.json`:
  - Replace `package_name` with your Android package ID.
  - Replace SHA-256 fingerprint with your signing key fingerprint.

## 3) Install Bubblewrap
```bash
npm i -g @bubblewrap/cli
```

## 4) Initialize TWA project
```bash
cd twa
bubblewrap init --manifest https://your-production-domain.com/manifest.webmanifest
```

Use values from `twa-manifest.json` while answering prompts.

## 5) Build Android App Bundle
```bash
bubblewrap build
```

This generates an `.aab` file for Play Store upload.

## 6) Verify PWA quality
Before publishing, run Lighthouse in Chrome and ensure:
- Installable PWA checks pass.
- Offline page works.
- Service worker controls pages.

## Notes
- TWA requires HTTPS and a valid domain association.
- Keep icons, app name, theme color, and package ID consistent across manifest and TWA config.
