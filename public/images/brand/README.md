# Brand assets

Every file here is a derivative of one of the two supplied source logos in
`resources/`:

| Source | Used for |
|---|---|
| `it-lms-logo-only.png` | The mark on its own: the favicon, the app icon, and anywhere the brand shows without the name. |
| `it-lms-logo-with-text.png` | The lockup, used in the public header, which carries the product name inside the artwork. |

## Why there are derivatives

The supplied files are large. The mark is 800 by 800 at 919 KB and the lockup is
3966 by 1586 at 2.4 MB. Served at the size they actually appear, that is roughly
six hundred times more data than the display needs, on every page load.

The files here are the same artwork resampled to the sizes that are used:

| File | Pixels | For |
|---|---|---|
| `lms-mark-512.png` | 512 | High density displays that want the large mark. |
| `lms-mark-256.png` | 256 | The largest mark the interface asks for. |
| `lms-mark-128.png` | 128 | The default mark in the navigation. |
| `lms-mark-64.png` | 64 | Small marks and high density favicons. |
| `lms-lockup-600.png` | 600 by 240 | Large lockups, such as the footer on a wide screen. |
| `lms-lockup-300.png` | 300 by 120 | The default lockup in the public header. |

The resampling is a box filter with colour weighted by alpha, so a transparent
edge does not bleed a white fringe when the artwork is composited on the dark
theme.

## Rules

- Never edit a derivative by hand. Change the source in `resources/` and
  regenerate.
- Never place a protected or private file in this folder.
- Do not copy the brand into FOR_UI or any external repository.
- The lockup lettering is dark navy on a transparent background, so it needs the
  light plate the logo component puts behind it on the dark theme. Removing that
  plate makes the name unreadable.
