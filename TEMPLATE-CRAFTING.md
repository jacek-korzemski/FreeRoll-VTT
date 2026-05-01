# Template Crafting Guide

How to create interactive character sheets and custom templates for FreeRoll VTT.

Templates are plain HTML files with special `data-*` attributes. The basics need only HTML — text fields, checkboxes, and dice buttons work out of the box. For advanced sheets you can also add a `<style>` block (auto-scoped to the template instance) and a `<script>` block (sandboxed `vtt` API for reactive computation, e.g. ability modifiers). Place your `.html` files in `backend/assets/templates/` and they'll appear in the template picker inside the notepad panel.

---

## Quick Start

Minimal working template:

```html
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>My Template</title>
</head>
<body>

  <table class="tpl-table">
    <tr>
      <td><strong>Name:</strong> <input data-field="name" type="text" class="plain wide"></td>
    </tr>
    <tr>
      <td><strong>HP:</strong> <input data-field="hp" type="text" class="box sm"></td>
    </tr>
  </table>

</body>
</html>
```

The `<title>` becomes the notepad title when a user loads the template.

---

## Editable Fields

Every editable element uses the `data-field` attribute with a **unique name**. This name is used internally to save and restore values.

### Text Input

```html
<input data-field="character_name" type="text" class="plain">
```

### Textarea (multi-line)

```html
<textarea data-field="backstory" class="plain wide" rows="5"></textarea>
```

### Checkbox

```html
<input data-field="has_proficiency" type="checkbox">
```

### Default Values

Use the standard `value` attribute. It will be used when the template is loaded for the first time:

```html
<input data-field="prof_bonus" type="text" class="box xs" value="+2">
```

---

## Input Styles

Three visual variants are available. Add them as CSS classes:

### `.plain` — Invisible input

Text appears naturally, as if it was part of the document. No border, no background. A subtle underline appears on hover and focus.

```html
<input data-field="name" type="text" class="plain">
```

Best for: names, descriptions, notes — anywhere you want seamless inline text.

### `.box` — Bordered input

Standard input with a visible border and centered text.

```html
<input data-field="armor_class" type="text" class="box">
```

Best for: numeric values, modifiers, small data points.

### `.circle` — Round stat bubble

A 36x36px circle with bold centered text. Looks like a classic RPG stat bubble.

```html
<input data-field="strength" type="text" class="circle">
```

Best for: ability scores, armor class, key stats.

### Size Modifiers

Combine with a size class:

| Class   | Width | Use case |
|---------|-------|----------|
| `.xs`   | 36px  | Single number: modifier, bonus |
| `.sm`   | 60px  | Short value: HP, level |
| `.wide` | 100%  | Full-width: name, description |

Examples:

```html
<input data-field="str_mod" type="text" class="box xs">
<input data-field="hp_current" type="text" class="box sm">
<input data-field="character_name" type="text" class="plain wide">
<textarea data-field="notes" class="plain wide" rows="6"></textarea>
```

---

## Table Structure

Use `class="tpl-table"` on tables for proper styling. Available cell classes:

```html
<table class="tpl-table">
  <tr>
    <td class="section-header">Section Title</td>    <!-- Red accent header -->
  </tr>
  <tr>
    <td class="section-header-sm">Sub-header</td>    <!-- Subtle gray header -->
  </tr>
  <tr>
    <td class="center">Centered content</td>
    <td class="right">Right-aligned content</td>
  </tr>
</table>
```

---

## Dice Roll Buttons

Add a button with `data-roll` to create an inline dice roller. Clicking it rolls the dice and sends the result to the dice panel (visible to all players).

### Basic Roll

```html
<button data-roll="d20" class="roll-btn">🎲</button>
```

Rolls a d20. Supported dice: `d4`, `d6`, `d8`, `d10`, `d12`, `d20`, `d100`.

### Roll with Multiple Dice

```html
<button data-roll="2d6" class="roll-btn">🎲</button>
```

### Roll with a Fixed Modifier

```html
<button data-roll="d20+5" class="roll-btn">🎲</button>
```

### Roll Using Field Values

Reference any field with `@field_name`. The current value of that field will be parsed as a number and added:

```html
<input data-field="str_mod" type="text" class="box xs" value="+3">
<button data-roll="d20+@str_mod" class="roll-btn">🎲</button>
```

If `str_mod` contains `+3`, the roll becomes d20+3.

### Conditional Modifier (Proficiency)

Use `+@value?@condition` syntax. The value is added **only if** the condition checkbox is checked:

```html
<input data-field="athletics" type="text" class="box xs" value="+3">
<input data-field="athletics_prof" type="checkbox">
<input data-field="prof_bonus" type="text" class="box xs" value="+2">

<button data-roll="d20+@athletics+@prof_bonus?@athletics_prof" class="roll-btn">🎲</button>
```

This means:
- Always roll d20
- Always add `@athletics` value (+3)
- Add `@prof_bonus` (+2) **only if** `@athletics_prof` checkbox is checked

Result: d20+3 (without proficiency) or d20+5 (with proficiency).

### Roll Label

Use `data-roll-label` to name the roll in the dice history:

```html
<button data-roll="d20+@athletics" data-roll-label="Athletics" class="roll-btn">🎲</button>
```

In the dice history, this will appear as: **PlayerName (Athletics): d20 [15] +3 = 18**

### Dynamic Labels

Labels can reference field values too:

```html
<input data-field="weapon_name" type="text" class="plain" value="Longsword">
<button data-roll="d20+@atk_mod" data-roll-label="Attack: @weapon_name" class="roll-btn">🎲</button>
```

Shows: **PlayerName (Attack: Longsword): d20 [12] +5 = 17**

### Button Content

The button text can be anything — emoji, text, or both:

```html
<button data-roll="d20" class="roll-btn">🎲</button>
<button data-roll="d20" class="roll-btn">🎲 Roll</button>
<button data-roll="d20" class="roll-btn">🎲 Death Save</button>
```

---

## Roll Expression Reference

| Expression | Meaning |
|---|---|
| `d20` | Roll one d20 |
| `2d6` | Roll two d6 |
| `d20+5` | Roll d20, add 5 |
| `d20+@str_mod` | Roll d20, add value from field `str_mod` |
| `d20+@str_mod+@prof?@str_prof` | Roll d20, add `str_mod`, add `prof` only if `str_prof` is checked |
| `2d6+@dmg_mod` | Roll 2d6, add value from field `dmg_mod` |

---

## Complete Example: Skill Row

A typical D&D 5e skill with proficiency toggle and one-click roll:

```html
<tr>
  <td>
    <input data-field="skill_stealth_prof" type="checkbox"> Stealth <small>(Dex)</small>
  </td>
  <td class="right">
    <input data-field="skill_stealth" type="text" class="box xs">
  </td>
  <td class="center">
    <button
      data-roll="d20+@skill_stealth+@prof?@skill_stealth_prof"
      data-roll-label="Stealth"
      class="roll-btn">🎲</button>
  </td>
</tr>
```

How it works:
1. User enters their Stealth modifier (e.g. `+2`) in the `skill_stealth` field
2. User checks the `skill_stealth_prof` checkbox if proficient
3. The `prof` field (defined elsewhere) holds the proficiency bonus (e.g. `+3`)
4. Clicking 🎲 rolls: d20 + 2 + 3 (if proficient) or d20 + 2 (if not)

---

## Custom Styling (Per-Template CSS)

You can ship CSS together with the template. Put a `<style>` block in `<head>` (or anywhere in `<body>`) and use plain CSS selectors — the runtime rewrites them so they only match elements **inside this template instance**.

### Why scoping matters

Two notes loaded with the same template (or two different templates) live side-by-side in the same page. Without scoping, `.tpl-table { background: red }` from one template would paint every table in the app. The runtime prefixes each selector with `[data-vtt-scope="<unique-id>"]`, so styles never leak out of their own instance.

### Example

```html
<head>
  <meta charset="UTF-8">
  <title>My Template</title>
  <style>
    .tpl-table { background: #fbf6ec; border-color: #c9b88e; }
    .section-header {
      background: linear-gradient(180deg, #6a2c2c, #4a1a1a);
      color: #f4e7c0;
    }
    /* Highlight auto-computed fields */
    input[data-field$="_mod"] {
      background: #efe3c4;
      font-weight: 700;
    }
  </style>
</head>
```

Internally `.tpl-table { background: #fbf6ec }` becomes `[data-vtt-scope="note-1"] .tpl-table { background: #fbf6ec }` for one note, `[data-vtt-scope="note-2"] .tpl-table { ... }` for another, etc.

### Scoping rules

| Rule type | Scoped? | Notes |
|---|---|---|
| Element/class/id selectors | yes | `.tpl-table`, `#hp`, `input[data-field="x"]` |
| Comma-separated selector lists | yes (each part) | `.a, .b` → both prefixed |
| `:root`, `html`, `body` | yes — map to scope root | use to set instance-wide background, font, etc. |
| `@media`, `@supports`, `@container`, `@layer` | yes (recursively) | inner rules are scoped |
| `@keyframes`, `@font-face`, `@import`, `@charset` | not scoped | names stay global; reference them by name |

### Tips

- The app's global CSS (e.g. `.note-template-renderer .tpl-table` from `App.css`) loads first; your scoped rules override the matching defaults because the scoped selector is more specific.
- Avoid `!important` — clean specificity is enough thanks to the `[data-vtt-scope="…"]` prefix.
- Don't try to escape your scope (no `:root :host`, no `html { ... }` expecting global reach). All rules are confined to the template root.

---

## Custom Scripts (Reactive Behavior)

Templates can include a `<script>` block that runs in a small sandbox. The script receives a `vtt` object exposing the only safe way to read/write fields and react to user input. This is how the `dnd_5e.html` template auto-computes ability modifiers from scores.

### Where to put it

Anywhere in `<body>` (typically at the end, after the markup):

```html
<body>
  <!-- ...tables, fields, buttons... -->

  <script>
    function abilityModifier(score) {
      var n = parseInt(score, 10);
      if (isNaN(n)) return '';
      var m = Math.floor((n - 10) / 2);
      return (m >= 0 ? '+' : '') + m;
    }

    function refresh(ability) {
      vtt.setField(ability + '_mod', abilityModifier(vtt.getField(ability + '_score')));
    }

    var ABILITIES = ['str','dex','con','int','wis','cha'];

    vtt.onMount(function () {
      ABILITIES.forEach(refresh);
    });

    vtt.onFieldChange(function (name) {
      var match = name.match(/^(str|dex|con|int|wis|cha)_score$/);
      if (match) refresh(match[1]);
    });
  </script>
</body>
```

### `vtt` API reference

| Member | Type | Description |
|---|---|---|
| `vtt.scopeId` | `string` | Unique id for this instance (e.g. `note-1`, `token-abc`). Useful for `console.log` debugging. |
| `vtt.root` | `HTMLElement` | The DOM container that holds the rendered template. Use `vtt.root.querySelector(...)` if you need direct DOM access; never use `document.querySelector` (would leak across instances). |
| `vtt.getField(name)` | `string \| boolean` | Current value of a `data-field`. Strings for text/textarea, booleans for checkboxes. |
| `vtt.setField(name, value)` | `void` | Update a field. Writes the DOM input AND saves to storage. Strings, numbers, or booleans (for checkboxes). |
| `vtt.onMount(cb)` | `void` | `cb()` runs once after the template is mounted and fields are populated from saved data. Good for initial computations. |
| `vtt.onFieldChange(cb)` | `void` | `cb(name, value)` runs whenever the user edits a field. Does NOT fire for changes you triggered with `vtt.setField` (no infinite loops). |
| `vtt.onDestroy(cb)` | `void` | `cb()` runs when the template unmounts (e.g. user clears the note, switches templates, closes the token panel). Use it to cancel timers, observers, etc. |
| `vtt.fields` | `object` | Read-only snapshot of values at mount time. For live values use `getField(name)`. |

### What scripts can do

- Auto-compute derived fields (modifiers, totals, save bonuses).
- React to checkbox toggles (e.g. recompute totals when proficiency changes).
- Add custom DOM behavior on `vtt.root` (e.g. tooltips, badges) — but remember the DOM is yours only inside `vtt.root`.

### What scripts cannot/should not do

- **No external requests, no globals.** Your script runs as `new Function('vtt', code)(vtt)` in the page origin. Keep it limited to the `vtt` API and standard JS built-ins.
- **No `<script src="…">`.** External scripts are ignored — only inline `<script>…</script>` blocks execute.
- **No reaching into other templates.** Stick to `vtt.root` / `vtt.getField` / `vtt.setField`. Other instances have their own scope.
- **No assuming React state.** The runtime keeps DOM, localStorage, and React state in sync via the `vtt` API. Don't poke at React internals.

### Lifecycle

```
load template → mount DOM → restore saved fields → run <script> → vtt.onMount() →
   ↓ user types in a field
   ↓ DOM updates → vtt.onFieldChange(name, value) callbacks fire
   ↓ your callback may call vtt.setField(...) → DOM + storage updated, NO recursive onFieldChange
   ...
unmount → vtt.onDestroy() callbacks fire → DOM is torn down
```

### Errors

If your script throws, the error is logged to the browser console with the prefix `[template:<scopeId>]` and the rest of the template keeps working. The same holds for individual `onFieldChange`/`onMount`/`onDestroy` callbacks — one bad handler does not break others.

### Trust note

Templates from `backend/assets/templates/` are treated as **trusted content**: their `<script>` blocks execute arbitrary JavaScript in the app's origin. Only upload templates from sources you trust (your own files, GM-curated bundles).

---

## Saving & Loading

### How Data is Stored

- Template structure (HTML) + field values (JSON) are saved together in localStorage
- Each notepad slot has independent storage
- Data persists across browser sessions

### Export Options (💾 button)

In template mode, the save button offers two options:

- **Save data (.json)** — Full backup: template HTML + all field values. Can be loaded back to restore everything exactly.
- **Export as HTML** — Generates a standalone HTML file with all values baked in. Good for printing or sharing outside VTT. Roll buttons are hidden in the exported file.

### Loading Templates

The load button (📂) offers:

- **Local file** — Load `.html` (template or notepad) or `.json` (saved template data) from disk
- **Server template** — Pick a template from `backend/assets/templates/`

When loading a `.json` file, all previously saved field values are restored. When loading an `.html` template, fields start empty (or with their `value` defaults).

---

## Deployment

1. Create your `.html` template file
2. Place it in `backend/assets/templates/`
3. It will appear in the template picker automatically
4. File name becomes the display name (underscores and hyphens are replaced with spaces, first letter capitalized)

Example: `dnd_5e.html` appears as **"Dnd 5e"** in the picker.

---

## Tips

- **Field names must be unique** across the entire template. Use prefixes like `skill_`, `save_`, `atk1_` to avoid collisions.
- **HTML first, scripts last.** You can build a fully working sheet with just `data-field` and `data-roll`. Add `<style>` and `<script>` only when you need theming or computed fields — they're optional.
- **Test locally.** Open your `.html` file in a browser to check the structure before deploying. Inputs, checkboxes, and your custom CSS will work; only the dice buttons and the `vtt` script API need the VTT runtime.
- **Use `<small>` for hints** like ability abbreviations: `Stealth <small>(Dex)</small>`.
- **Textarea for long text.** Use `<textarea>` with `rows="N"` instead of text inputs for equipment lists, backstory, notes, etc.
- **Two instances, two scopes.** The same template can be loaded into multiple notes or token panels at the same time — each has its own `data-vtt-scope`, its own styles, its own `vtt` instance. Don't share state via globals; use `data-field` values.
