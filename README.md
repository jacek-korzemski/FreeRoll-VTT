## FreeRoll VTT – simple self‑hosted virtual table

FreeRoll VTT is a **lightweight Virtual TableTop** that you can build once and host on a **simple PHP/Apache (or nginx) server** – no databases, no Docker, no always‑on Node server.

### Key features

- **Scenes & map management**
  - multiple scenes with **create / rename / duplicate / delete**
  - instant **scene switching** for all connected players
  - per‑scene state: background, fog of war, map elements, tokens

- **Background maps**
  - background images prepared for a **64×64 px grid**
  - upload backgrounds to `backend/assets/backgrounds`
  - on‑the‑fly **zoom, offset and reset** of the background (position & scale)

- **Map elements & tokens**
  - separate browsers for **map elements** (`backend/assets/map`) and **tokens** (`backend/assets/tokens`)
  - drag & drop assets onto the grid, snapping to cells
  - **collision checks** so you do not stack multiple tokens/elements on one cell by accident
  - **eraser tool** to quickly remove placed map elements

- **Fog of war (grid‑based)**
  - per‑cell fog of war stored as a bitmap in backend state
  - **edit mode for Game Master only**, with circular brush and configurable size
  - **reveal / hide mode**, including **Reveal all** and **Hide all**
  - optional **50% opacity preview for GM** so you can see both map and fog

- **Dice rolling**
  - standard dice: **d4, d6, d8, d10, d12, d20, d100**, with any number of dice
  - **per‑player name**, saved in browser
  - modifier support and **readable roll history** (shared across clients via backend)
  - optional **Legend of the Five Rings (L5R) module** with correct ring/skill dice faces, explosive dice and totals (success / opportunity / strife)

- **Notepads & templates**
  - up to **3 parallel WYSIWYG notepads** per user
  - **save / load content as JSON** and export as HTML
  - support for **HTML templates from the server** (`backend/assets/templates/*.html`) – great for character sheets
  - central **Notes Template context** so macros can read fields from templates

- **Macro editor**
  - visual editor for **dice macros** using expressions like `2d6+@str`
  - macros can read values from **named fields in notepad templates** (e.g. character sheet stats)
  - sort, edit, import/export macros and run them directly into the dice history

- **PDF reader panel**
  - list and open server PDFs from `backend/assets/papers`
  - **local browser storage** for additional PDFs (per client, no upload required)
  - lazy‑loaded pages for good performance even on large books

- **Ping tool**
  - Game Master can **ping a grid cell**; all players’ views scroll there with an animated highlight
  - optional **clear ping** so new players are not auto‑focused on old pings

- **Uploads from UI**
  - built‑in upload panel for GMs to send **tokens, map elements, backgrounds, templates and PDFs** directly to the server
  - basic validation of file types and size, with detailed error messages

- **Simple auth & GM mode**
  - **player password** and **GM password**, configured in `build/.env`
  - login page served by `index.php` with localized text (from `VTT_LANGUAGE`)
  - backend keeps track of **GM vs player** and restricts sensitive actions (uploads, fog editing, scene edits, etc.)

- **Multi‑language support (en / pl)**
  - UI strings stored in `frontend/src/lang/translations.json`
  - language chosen in `build/.env` (`VTT_LANGUAGE=en` or `pl`)

---

## Requirements

### To build the app (maintainers)

- **Node.js 18+** (`https://nodejs.org/`)
- **Windows** (if you want to use the provided `build.bat` / `build_pl.bat` helpers)

### To create another room from an existing package (no Node)

- **Windows** with PowerShell (for `clone.bat`)
- an existing `build/` folder (from `build.bat` or from a downloaded ZIP)

### Server to run the built package

- **PHP 7.4+**
- Web server that supports:
  - `.htaccess` files (Apache) **or**
  - equivalent configuration on nginx (rewrites + protection of `.env` and data files)

The final `build` folder is static assets + a small PHP backend, so it can be hosted on most shared PHP hostings.

---

## Fast start on Windows

### 1. Build the package (requires Node.js)

In the project root run **one** of:

- `build.bat` – interactive build script (prompts in English, default UI language: **en**)
- `build_pl.bat` – interactive build script (prompts in Polish, default UI language: **pl**)

The script will ask you for deployment settings (passwords, base path, UI language, L5R, CORS). It then runs `npm run build` once and assembles the `build/` folder.

All deployment settings are stored in **`build/.env`** (see [`deploy.env.example`](deploy.env.example)). The frontend bundle is path‑ and language‑agnostic; `index.php` reads `.env` at runtime.

### 2. Clone another room (no Node.js)

If you already have a `build/` folder and need another instance (different path, passwords, or language):

```bat
clone.bat
```

The script copies the existing package to a new folder and writes a fresh `build/.env`. Session data (`backend/data/state.json`) is reset so the new room starts empty.

### 3. Deploy the package

- **Upload the contents of `build/`** (not the folder itself) to the target directory on your server that matches `VTT_BASE_PATH` in `.env`.
- Put your assets on the server side into:
  - `backend/assets/map/` – map elements
  - `backend/assets/tokens/` – tokens
  - `backend/assets/backgrounds/` – background maps
  - `backend/assets/papers/` – PDFs for the reader
  - `backend/assets/templates/` – HTML templates for notepads / character sheets
- Ensure that `backend/data/` is **writable** by the web server user, e.g. on Linux:
  - `chmod 755 backend/data/` (or more permissive, depending on your hosting)

Then open your configured URL (e.g. `https://yourdomain.com/vtt/room1/`) and log in with the configured player / GM password.

To change passwords or base path later, edit `build/.env` on the server (or run `clone.bat` locally and re‑upload).

---

## For developers

### Run backend in dev mode (no auth, CORS for localhost)

```bash
cd backend
php -S localhost:8080
```

This starts the PHP built‑in server on `http://localhost:8080` and enables development shortcuts in `api.php` (automatic auth bypass, optional GM override via `?gm=1` or `dev_gm` cookie).

### Run frontend with Vite dev server

```bash
cd frontend
npm install
npm run dev
```

By default the frontend dev server runs on `http://localhost:5173` and talks to the backend at `http://localhost:8080/backend/api.php`. You can change those in your local `.env` / config if needed.
