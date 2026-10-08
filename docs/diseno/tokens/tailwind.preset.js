// Preset de Tailwind para Club Aponte Rivera. Uso: presets: [require('./tailwind.preset.js')]
// Sirve también para el tema personalizado de Filament (resources/css/filament/admin/tailwind.config.js).
module.exports = {
  theme: {
    extend: {
      colors: {
        ink: { DEFAULT: '#231d18', 2: '#5c534b', 3: '#3d352e' },
        paper: '#faf7f2',
        surface: '#ffffff',
        sunken: '#ece7df',
        line: { DEFAULT: '#e7e0d6', 2: '#efe9e0' },
        field: '#cfc6ba',
        brand: { DEFAULT: '#1f5c4f', strong: '#163f37', soft: '#e2ede8' },
        success: { DEFAULT: '#0f6b3a', ink: '#1e7a46' },
        danger: '#a31d15',
        warn: { DEFAULT: '#9a4f00', ink: '#7a3f00', soft: '#fff6e6', line: '#efd3a0' },
        cashier: { ink: '#0f0d0b', ink2: '#4a433c', key: '#f1ede7', placeholder: '#b5ada3' },
        tier: {
          bronce: { bg: '#ecdcc8', ink: '#5a3a1e' },
          plata: { bg: '#dde3e6', ink: '#2e3a42' },
          oro: { bg: '#f4c542', ink: '#3d2a00' },
          diamante: { bg: '#1c1a36', ink: '#f5f1ff', accent: '#9d8cff' },
        },
      },
      fontFamily: {
        display: ['"Bricolage Grotesque"', 'system-ui', 'sans-serif'],
        sans: ['Figtree', 'system-ui', 'sans-serif'],
        mono: ['ui-monospace', 'Menlo', 'monospace'],
      },
      fontSize: {
        hero: ['128px', { lineHeight: '0.9', letterSpacing: '-0.03em', fontWeight: '800' }],
        '3xl': ['56px', { lineHeight: '1', fontWeight: '800' }],
        '2xl': ['32px', { lineHeight: '1.1', fontWeight: '700' }],
        xl: ['22px', { lineHeight: '1.2', fontWeight: '600' }],
        lg: ['17px', { lineHeight: '1.4', fontWeight: '700' }],
        md: ['15px', { lineHeight: '1.45' }],
        sm: ['13px', { lineHeight: '1.4', fontWeight: '600' }],
        overline: ['12px', { lineHeight: '1', letterSpacing: '0.06em', fontWeight: '700' }],
      },
      borderRadius: { xs: '6px', sm: '8px', md: '14px', lg: '20px', xl: '24px' },
      spacing: { touch: '44px', 'touch-cashier': '64px', input: '52px', btn: '56px' },
      boxShadow: { float: '0 8px 24px rgba(61,42,0,.18)' },
    },
  },
};
