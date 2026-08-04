import type { Config } from 'tailwindcss';
import forms from '@tailwindcss/forms';
// eslint-disable-next-line @typescript-eslint/no-require-imports
const mydsPreset = require('./node_modules/@govtechmy/myds-style/tailwind.config.js');

const config: Config = {
    presets: [mydsPreset],
    darkMode: 'class',

    content: ['./app/**/*.{ts,tsx}', './components/**/*.{ts,tsx}'],

    plugins: [forms],
};

export default config;
