# Lucide icons

The interface draws its icons from [Lucide](https://lucide.dev), installed as the
`lucide-static` npm package.

Lucide is ISC licensed. Copyright (c) Lucide Icons and Contributors. The licence
text ships with the package at `node_modules/lucide-static/LICENSE`.

## How it fits together

| File | Role |
| --- | --- |
| `config/icons.php` | The choice of drawing. Project name on the left, Lucide file on the right. |
| `resources/icons/lucide.php` | Generated. The inner markup of each drawing, ready to inline. |
| `resources/views/components/icon.blade.php` | The component views use. |
| `app/Console/Commands/SyncLucideIcons.php` | The command that bakes one into the other. |

## Changing an icon

1. Edit `config/icons.php`.
2. Run `php artisan icons:sync`.
3. Commit both files.

Views ask for an icon by meaning, never by file name:

```blade
<x-icon name="mail" size="sm" />
```

That keeps the drawing set swappable. Changing what "mail" looks like is a
change to `config/icons.php` alone, and no view has to be touched.

## Why the drawings are baked into a committed file

Icons render as inline SVG rather than as a font or an image, so the drawing has
to be available when a page is built. `node_modules` is a build time dependency
and is not deployed, so the sync command copies the shapes out of the package
into a plain PHP array that is committed with the project.

The result is that a deployed server renders every icon without npm ever
running, and a reviewer can read exactly which drawings the product uses without
installing a package first.

`resources/icons/lucide.php` is generated. Editing it by hand works until the
next sync overwrites it. Change `config/icons.php` instead.

## Verifying the baked file is current

```text
php artisan icons:sync --check
```

Exits non zero when the committed file no longer matches the configuration, so a
stale bake fails a check instead of shipping quietly.
