# Tailwind CSS 4 Configuration

This project uses Tailwind CSS 4, which introduces a new CSS-first configuration approach.

## Key Changes from Tailwind 3 to 4

### Configuration
- **No more `tailwind.config.js`** - Configuration is now done in CSS using `@theme` directive
- **No PostCSS plugin needed** - Tailwind 4 handles processing internally
- **CSS imports** - Use `@import "tailwindcss"` instead of `@tailwind` directives

### Setup Files

#### `resources/css/app.css`
Contains the main Tailwind import and theme configuration:
```css
@import "tailwindcss";

@theme {
  /* Custom theme variables */
  --font-family-sans: Figtree, ui-sans-serif, system-ui, sans-serif;
  --color-primary-500: #3b82f6;
  /* ... other theme variables */
}
```

#### `vite.config.js`
Standard Vite configuration - no special Tailwind plugin needed:
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
});
```

## Theme Customization

All theme customization is done in CSS using CSS custom properties within the `@theme` directive:

```css
@theme {
  /* Colors */
  --color-primary-50: #eff6ff;
  --color-primary-500: #3b82f6;
  --color-primary-900: #1e3a8a;

  /* Fonts */
  --font-family-sans: Figtree, ui-sans-serif, system-ui, sans-serif;

  /* Animations */
  --animate-fade-in: fade-in 0.5s ease-in-out;
}
```

## Migration Notes

### Removed Files
- `tailwind.config.js` - No longer needed
- `postcss.config.js` - No longer needed
- Component-level animation styles - Now handled globally

### Updated Dependencies
- Upgraded from `tailwindcss@^3.4.0` to `tailwindcss@^4.1.7`
- Removed `autoprefixer` and `postcss` dependencies

## Benefits

1. **Simpler setup** - No JavaScript configuration files
2. **Better performance** - Faster processing and smaller bundle sizes
3. **CSS-native** - Configuration feels more natural in CSS
4. **Better IDE support** - CSS variables provide better autocomplete and validation
5. **Reduced duplication** - Global animations instead of component-level styles
6. **Smaller bundle** - Eliminated duplicate CSS and unnecessary dependencies
