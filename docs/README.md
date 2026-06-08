# GitHub Pages

Dokumentasi HTML bilingual (English / Indonesia) dengan **Tailwind CSS CDN**, layout docs-style (navbar + sidebar + content).

## Aktifkan GitHub Pages

1. Buka **Settings → Pages** di repository GitHub
2. **Source:** Deploy from a branch
3. **Branch:** `dev` (atau `main`) → folder **`/docs`**
4. Save

Dokumentasi akan tersedia di:

```
https://pimphand.github.io/gdrive/
```

## Preview lokal

Buka langsung di browser:

```bash
xdg-open docs/index.html
```

Atau jalankan server sederhana:

```bash
cd docs && python3 -m http.server 8080
# buka http://localhost:8080
```
