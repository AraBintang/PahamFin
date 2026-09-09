const fs = require('fs');
const file = 'C:/laragon/www/PahamFin/pages/landing.php';
let content = fs.readFileSync(file, 'utf8');

const replacements = [
    { regex: /bg-white(?!\/)/g, replace: 'bg-white dark:bg-slate-900/80' }, // Except bg-white/50 etc
    { regex: /text-dark/g, replace: 'text-dark dark:text-white' },
    { regex: /text-gray-900/g, replace: 'text-gray-900 dark:text-white' },
    { regex: /text-gray-800/g, replace: 'text-gray-800 dark:text-slate-200' },
    { regex: /text-gray-600/g, replace: 'text-gray-600 dark:text-slate-300' },
    { regex: /text-gray-500/g, replace: 'text-gray-500 dark:text-slate-400' },
    { regex: /bg-gray-50/g, replace: 'bg-gray-50 dark:bg-slate-800/80' },
    { regex: /bg-blue-50/g, replace: 'bg-blue-50 dark:bg-slate-800/80' },
    { regex: /border-gray-100/g, replace: 'border-gray-100 dark:border-slate-700' },
    { regex: /border-gray-200/g, replace: 'border-gray-200 dark:border-slate-700' },
];

replacements.forEach(r => {
    content = content.replace(r.regex, r.replace);
});

// Fix some overlapping stuff
content = content.replace(/dark:text-white dark:text-white/g, 'dark:text-white');
content = content.replace(/dark:bg-slate-900\/80 dark:bg-slate-900\/80/g, 'dark:bg-slate-900/80');

fs.writeFileSync(file, content);
console.log('Done!');
