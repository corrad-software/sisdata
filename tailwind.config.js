import forms from '@tailwindcss/forms';
import mydsPreset from './node_modules/@govtechmy/myds-style/tailwind.config.js';

/** @type {import('tailwindcss').Config} */
export default {
    presets: [mydsPreset],
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    plugins: [forms],
};
